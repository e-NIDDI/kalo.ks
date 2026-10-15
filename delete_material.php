<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/db.php';

$logged_user_id = $_SESSION['user_id'] ?? 1;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Kërkesë e pavlefshme.");
}

$material_id = (int)($_POST['material_id'] ?? 0);
$is_ajax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') || 
           (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);

if ($material_id <= 0) {
    if ($is_ajax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'ID e pavlefshme e materialit.']);
        exit;
    }
    die("ID e pavlefshme e materialit.");
}

try {
    // Check viewer's role and material ownership
    $uStmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $uStmt->execute([$logged_user_id]);
    $userRole = $uStmt->fetchColumn() ?: 'student';

    $mStmt = $pdo->prepare("SELECT id, user_id, file_path, title FROM materials WHERE id = ?");
    $mStmt->execute([$material_id]);
    $mat = $mStmt->fetch();

    if (!$mat) {
        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Materiali nuk u gjet.']);
            exit;
        }
        die("Materiali nuk u gjet.");
    }

    if ((int)$mat['user_id'] !== (int)$logged_user_id && $userRole !== 'admin') {
        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Nuk keni autorizim për të fshirë këtë material.']);
            exit;
        }
        die("Nuk keni autorizim.");
    }

    // Cascade delete relations
    $pdo->prepare("DELETE FROM comments WHERE material_id = ?")->execute([$material_id]);
    $pdo->prepare("DELETE FROM upvotes WHERE material_id = ?")->execute([$material_id]);
    $pdo->prepare("DELETE FROM saved_materials WHERE material_id = ?")->execute([$material_id]);
    $pdo->prepare("DELETE FROM materials WHERE id = ?")->execute([$material_id]);

    // Unlink file if stored locally
    if (!empty($mat['file_path'])) {
        $localPath = __DIR__ . '/' . ltrim($mat['file_path'], '/');
        if (is_file($localPath)) {
            @unlink($localPath);
        }
    }

    if ($is_ajax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'Materiali u fshi me sukses.']);
        exit;
    }

    // Redirect back to previous page or profile
    $redirect = $_SERVER['HTTP_REFERER'] ?? 'profile.php?deleted=1';
    header("Location: " . $redirect);
    exit;

} catch (PDOException $e) {
    if ($is_ajax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
    die("Gabim në bazën e të dhënave: " . $e->getMessage());
}
