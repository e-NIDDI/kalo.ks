<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Metodë e pavlefshme.");
}

$material_id = (int)($_POST['material_id'] ?? 0);
$comment_text = trim($_POST['comment_text'] ?? '');
$user_id = (int)($_SESSION['user_id'] ?? 1);

if ($material_id <= 0 || empty($comment_text)) {
    die("Ju lutem shkruani një koment.");
}

try {
    // Insert comment
    $stmt = $pdo->prepare("
        INSERT INTO comments (material_id, user_id, comment_text, created_at) 
        VALUES (?, ?, ?, NOW())
    ");
    $stmt->execute([$material_id, $user_id, $comment_text]);
    
    // Award 2 reputation points for active discussion
    $stmt = $pdo->prepare("UPDATE users SET reputation_points = reputation_points + 2 WHERE id = ?");
    $stmt->execute([$user_id]);
    
    // Create notification
    $stmt = $pdo->prepare("INSERT INTO notifications (user_id, message, created_at) VALUES (?, ?, NOW())");
    $stmt->execute([$user_id, "Keni komentuar në një material! +2 pikë reputacioni."]);
    
    header("Location: view.php?id=" . $material_id . "#komentet");
    exit;
    
} catch (PDOException $e) {
    die("Gabim në bazën e të dhënave: " . $e->getMessage());
}
