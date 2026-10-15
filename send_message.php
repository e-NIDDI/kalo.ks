<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Metodë e pavlefshme.']);
    exit;
}

$logged_user_id = $_SESSION['user_id'] ?? 1;
$faculty_id = (int)($_POST['faculty_id'] ?? 0);
$message_text = trim($_POST['message'] ?? '');

if ($faculty_id <= 0 || $message_text === '') {
    echo json_encode(['success' => false, 'error' => 'Ju lutem shkruani një mesazh.']);
    exit;
}

if (mb_strlen($message_text) > 1500) {
    $message_text = mb_substr($message_text, 0, 1500);
}

try {
    $stmt = $pdo->prepare("
        INSERT INTO live_chat_messages (faculty_id, user_id, message, created_at)
        VALUES (?, ?, ?, NOW())
    ");
    $stmt->execute([$faculty_id, $logged_user_id, $message_text]);

    echo json_encode([
        'success' => true,
        'message_id' => (int)$pdo->lastInsertId()
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
