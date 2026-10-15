<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/db.php';

$logged_user_id = $_SESSION['user_id'] ?? 4;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Metodë e pavlefshme.");
}

$material_id = (int)($_POST['material_id'] ?? 0);

if ($material_id <= 0) {
    die("ID e pavlefshme e materialit.");
}

try {
    // Check admin privilege
    $uStmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $uStmt->execute([$logged_user_id]);
    $role = $uStmt->fetchColumn();

    if ($role !== 'admin') {
        die("Qasja e refuzuar. Vetëm administratorët mund të verifikojnë materiale.");
    }

    // Get material info and uploader
    $stmt = $pdo->prepare("SELECT user_id, title, is_verified FROM materials WHERE id = ?");
    $stmt->execute([$material_id]);
    $material = $stmt->fetch();
    
    if (!$material) {
        die("Materiali nuk u gjet.");
    }
    
    if ((int)$material['is_verified'] === 1) {
        header("Location: admin.php?already_verified=1");
        exit;
    }
    
    // Verify the material
    $stmt = $pdo->prepare("UPDATE materials SET is_verified = 1 WHERE id = ?");
    $stmt->execute([$material_id]);
    
    // Award +20 reputation points to uploader
    $stmt = $pdo->prepare("UPDATE users SET reputation_points = reputation_points + 20 WHERE id = ?");
    $stmt->execute([$material['user_id']]);
    
    // Create notification for uploader
    $stmt = $pdo->prepare("INSERT INTO notifications (user_id, message, created_at) VALUES (?, ?, NOW())");
    $stmt->execute([
        $material['user_id'], 
        "Materiali juaj '" . $material['title'] . "' u verifikua nga moderatori! Keni fituar +20 pikë reputacioni."
    ]);
    
    header("Location: admin.php?verified=1");
    exit;
    
} catch (PDOException $e) {
    die("Gabim në bazën e të dhënave: " . $e->getMessage());
}
