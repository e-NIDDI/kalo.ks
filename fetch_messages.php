<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');

$faculty_id = (int)($_GET['faculty_id'] ?? 0);
$last_id = (int)($_GET['last_id'] ?? 0);

if ($faculty_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Fakulteti i pavlefshëm.']);
    exit;
}

try {
    if ($last_id > 0) {
        $stmt = $pdo->prepare("
            SELECT m.id, m.faculty_id, m.user_id, m.message, m.created_at,
                   u.full_name, u.avatar_url, u.role, u.reputation_points
            FROM live_chat_messages m
            JOIN users u ON m.user_id = u.id
            WHERE m.faculty_id = ? AND m.id > ?
            ORDER BY m.created_at ASC
            LIMIT 100
        ");
        $stmt->execute([$faculty_id, $last_id]);
    } else {
        $stmt = $pdo->prepare("
            SELECT * FROM (
                SELECT m.id, m.faculty_id, m.user_id, m.message, m.created_at,
                       u.full_name, u.avatar_url, u.role, u.reputation_points
                FROM live_chat_messages m
                JOIN users u ON m.user_id = u.id
                WHERE m.faculty_id = ?
                ORDER BY m.created_at DESC
                LIMIT 50
            ) AS sub ORDER BY created_at ASC
        ");
        $stmt->execute([$faculty_id]);
    }

    $messages = $stmt->fetchAll();
    echo json_encode([
        'success' => true,
        'messages' => $messages
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
