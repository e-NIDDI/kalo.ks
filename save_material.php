<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Metodë e pavlefshme.");
}

$material_id = (int)($_POST['material_id'] ?? $_GET['id'] ?? $_POST['id'] ?? 0);
$user_id = (int)($_SESSION['user_id'] ?? 1);

if ($material_id <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'ID e pavlefshme e materialit.']);
    exit;
}

try {
    // Check if already saved
    $stmt = $pdo->prepare("SELECT id FROM saved_materials WHERE user_id = ? AND material_id = ?");
    $stmt->execute([$user_id, $material_id]);
    $existing = $stmt->fetch();
    
    if ($existing) {
        // Toggle off: remove
        $stmt = $pdo->prepare("DELETE FROM saved_materials WHERE user_id = ? AND material_id = ?");
        $stmt->execute([$user_id, $material_id]);
        $response = ['success' => true, 'action' => 'removed', 'saved' => false, 'message' => 'U hoq nga koleksioni.'];
    } else {
        // Add bookmark
        $stmt = $pdo->prepare("INSERT INTO saved_materials (user_id, material_id, created_at) VALUES (?, ?, NOW())");
        $stmt->execute([$user_id, $material_id]);
        $response = ['success' => true, 'action' => 'saved', 'saved' => true, 'message' => 'U ruajt në koleksion!'];
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}
