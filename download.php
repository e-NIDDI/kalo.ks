<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/db.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    die("ID e pavlefshme.");
}

try {
    $stmt = $pdo->prepare("SELECT id, title, file_path, file_type FROM materials WHERE id = ?");
    $stmt->execute([$id]);
    $mat = $stmt->fetch();

    if (!$mat) {
        die("Materiali nuk u gjet.");
    }

    // Increment downloads counter
    $upd = $pdo->prepare("UPDATE materials SET downloads_count = downloads_count + 1 WHERE id = ?");
    $upd->execute([$id]);

    $filePath = __DIR__ . '/' . ltrim($mat['file_path'], '/');

    if (!empty($mat['file_path']) && is_file($filePath)) {
        $mime = 'application/octet-stream';
        if (strtolower($mat['file_type']) === 'pdf') $mime = 'application/pdf';
        elseif (in_array(strtolower($mat['file_type']), ['zip', 'rar'])) $mime = 'application/zip';

        header('Content-Description: File Transfer');
        header('Content-Type: ' . $mime);
        header('Content-Disposition: attachment; filename="' . basename($mat['file_path']) . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($filePath));
        readfile($filePath);
        exit;
    } else {
        // If file is external URL or demo placeholder, redirect to view or simulate download
        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $mat['title']) . '.' . strtolower($mat['file_type'] ?: 'txt') . '"');
        echo "Kalo.ks - Platforma Studentore e Provimeve\n";
        echo "Materiali: " . $mat['title'] . "\n";
        echo "Skedari: " . $mat['file_path'] . "\n\n";
        echo "Përmbajtja e këtij materiali u gjenerua për qëllime demonstrimi në mjedisin e testimit.";
        exit;
    }
} catch (PDOException $e) {
    die("Gabim: " . $e->getMessage());
}
