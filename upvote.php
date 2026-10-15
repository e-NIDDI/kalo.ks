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
    // Check if user already upvoted
    $stmt = $pdo->prepare("SELECT id FROM upvotes WHERE user_id = ? AND material_id = ?");
    $stmt->execute([$user_id, $material_id]);
    $existing = $stmt->fetch();
    
    if ($existing) {
        // Remove upvote (toggle off)
        $stmt = $pdo->prepare("DELETE FROM upvotes WHERE user_id = ? AND material_id = ?");
        $stmt->execute([$user_id, $material_id]);
        
        // Decrement upvotes_count
        $stmt = $pdo->prepare("UPDATE materials SET upvotes_count = GREATEST(0, upvotes_count - 1) WHERE id = ?");
        $stmt->execute([$material_id]);
        
        // Deduct 5 reputation points from uploader
        $stmt = $pdo->prepare("SELECT user_id FROM materials WHERE id = ?");
        $stmt->execute([$material_id]);
        $mat = $stmt->fetch();
        if ($mat) {
            $stmt = $pdo->prepare("UPDATE users SET reputation_points = GREATEST(0, reputation_points - 5) WHERE id = ?");
            $stmt->execute([$mat['user_id']]);
        }
        
        $action = 'removed';
        $upvoted = false;
    } else {
        // Add upvote
        $stmt = $pdo->prepare("INSERT INTO upvotes (user_id, material_id, created_at) VALUES (?, ?, NOW())");
        $stmt->execute([$user_id, $material_id]);
        
        // Increment upvotes_count
        $stmt = $pdo->prepare("UPDATE materials SET upvotes_count = upvotes_count + 1 WHERE id = ?");
        $stmt->execute([$material_id]);
        
        // Award 5 reputation points to uploader
        $stmt = $pdo->prepare("SELECT user_id FROM materials WHERE id = ?");
        $stmt->execute([$material_id]);
        $mat = $stmt->fetch();
        if ($mat) {
            $stmt = $pdo->prepare("UPDATE users SET reputation_points = reputation_points + 5 WHERE id = ?");
            $stmt->execute([$mat['user_id']]);
        }
        
        $action = 'added';
        $upvoted = true;
    }
    
    // Get updated count
    $stmt = $pdo->prepare("SELECT upvotes_count FROM materials WHERE id = ?");
    $stmt->execute([$material_id]);
    $count = (int)$stmt->fetchColumn();
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'action' => $action,
        'upvoted' => $upvoted,
        'count' => $count
    ]);
    exit;
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}
