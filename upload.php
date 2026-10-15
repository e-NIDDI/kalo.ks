<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/db.php';

$user_id = (int)($_SESSION['user_id'] ?? 1);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Metodë e pavlefshme.");
}

// Validate file upload
if (!isset($_FILES['file']) && !isset($_FILES['file_path'])) {
    die("Asnjë skedar nuk u dërgua.");
}

$file_info = $_FILES['file'] ?? $_FILES['file_path'];
if ($file_info['error'] !== UPLOAD_ERR_OK) {
    die("Ngarkimi i skedarit dështoi.");
}

// Validate inputs
$title = trim($_POST['title'] ?? '');
$description = trim($_POST['description'] ?? '');
$file_type = trim($_POST['file_type'] ?? '');
$fakulteti = trim($_POST['fakulteti'] ?? '');
$lenda = trim($_POST['lenda'] ?? '');

if (empty($title) || empty($fakulteti) || empty($lenda)) {
    die("Ju lutem plotësoni të gjitha fushat e detyrueshme.");
}

// Allowed extensions
$allowed_extensions = ['pdf', 'png', 'jpg', 'jpeg', 'zip', 'rar', 'doc', 'docx'];
$max_size = 50 * 1024 * 1024; // 50MB

$file_ext = strtolower(pathinfo($file_info['name'], PATHINFO_EXTENSION));

if (!in_array($file_ext, $allowed_extensions)) {
    die("Formati i skedarit nuk lejohet. Formatet e lejuara: " . implode(', ', $allowed_extensions));
}

if ($file_info['size'] > $max_size) {
    die("Madhësia e skedarit tejkalon limitin prej 50MB.");
}

// Create uploads directory
$upload_dir = __DIR__ . '/uploads/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Generate unique filename
$unique_name = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($file_info['name']));
$target_file = $upload_dir . $unique_name;
$web_file_path = 'uploads/' . $unique_name;

if (!move_uploaded_file($file_info['tmp_name'], $target_file)) {
    die("Dështoi ruajtja e skedarit.");
}

// Calculate readable size
$size_mb = round($file_info['size'] / (1024 * 1024), 2);
$formatted_size = $size_mb > 0.05 ? $size_mb . 'MB' : round($file_info['size'] / 1024, 1) . 'KB';

try {
    // Resolve course_id
    $courseStmt = $pdo->prepare("SELECT id FROM courses WHERE code = ? OR id = ? LIMIT 1");
    $courseStmt->execute([$lenda, (int)$lenda]);
    $course = $courseStmt->fetch();
    
    if (!$course) {
        // Fallback: pick first course of faculty
        $fCourse = $pdo->prepare("SELECT id FROM courses WHERE faculty_id = (SELECT id FROM faculties WHERE code = ? LIMIT 1) LIMIT 1");
        $fCourse->execute([$fakulteti]);
        $course = $fCourse->fetch();
        if (!$course) {
            die("Lënda nuk u gjet.");
        }
    }
    $course_id = $course['id'];
    
    // Insert material
    $stmt = $pdo->prepare("
        INSERT INTO materials 
        (user_id, course_id, title, description, file_path, file_type, file_size, views_count, downloads_count, upvotes_count, is_verified, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, 0, 0, 0, 0, NOW())
    ");
    $stmt->execute([
        $user_id, $course_id, $title, $description, 
        $web_file_path, ($file_type ?: $file_ext), $formatted_size
    ]);
    
    $material_id = $pdo->lastInsertId();
    
    // Award 15 initial reputation points for contributing
    $stmt = $pdo->prepare("UPDATE users SET reputation_points = reputation_points + 15 WHERE id = ?");
    $stmt->execute([$user_id]);
    
    // Create notification
    $stmt = $pdo->prepare("INSERT INTO notifications (user_id, message, created_at) VALUES (?, ?, NOW())");
    $stmt->execute([
        $user_id, 
        "Materiali '$title' u ngarkua me sukses! Keni fituar +15 pikë reputacioni."
    ]);
    
    header("Location: profile.php?uploaded=1");
    exit;
    
} catch (PDOException $e) {
    die("Gabim në bazën e të dhënave: " . $e->getMessage());
}
