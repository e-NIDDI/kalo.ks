<?php
require_once __DIR__ . '/db.php';

// Set CORS headers
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=utf-8');

// Get endpoint parameter
$endpoint = isset($_GET['endpoint']) ? trim($_GET['endpoint']) : '';

switch ($endpoint) {
    case 'materials':
        getMaterials();
        break;
    case 'courses':
        getCourses();
        break;
    case 'leaderboard':
        getLeaderboard();
        break;
    default:
        http_response_code(404);
        echo json_encode(['error' => 'Endpoint not found. Available: materials, courses, leaderboard']);
        break;
}

function getMaterials() {
    global $pdo;
    
    $limit = isset($_GET['limit']) ? max(1, min(100, (int)$_GET['limit'])) : 10;
    $offset = isset($_GET['offset']) ? max(0, (int)$_GET['offset']) : 0;
    
    $sql = "
        SELECT m.id, m.title, m.description, m.file_type, m.file_size, 
               m.views_count, m.downloads_count, m.upvotes_count, m.is_verified, m.created_at,
               c.name AS course_name, c.code AS course_code,
               f.name AS faculty_name, f.code AS faculty_code,
               u.full_name AS author_name, u.avatar_url AS author_avatar, u.reputation_points AS author_reputation
        FROM materials m
        JOIN courses c ON m.course_id = c.id
        JOIN faculties f ON c.faculty_id = f.id
        JOIN users u ON m.user_id = u.id
        ORDER BY m.created_at DESC
        LIMIT {$limit} OFFSET {$offset}
    ";
    
    $stmt = $pdo->query($sql);
    $materials = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'count' => count($materials),
        'data' => $materials
    ]);
}

function getCourses() {
    global $pdo;
    
    $stmt = $pdo->query("
        SELECT f.id AS faculty_id, f.name AS faculty_name, f.code AS faculty_code,
               c.id AS course_id, c.name AS course_name, c.code AS course_code, 
               c.professor_name, c.semester
        FROM faculties f
        LEFT JOIN courses c ON c.faculty_id = f.id
        ORDER BY f.name, c.name
    ");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Group by faculty
    $grouped = [];
    foreach ($rows as $row) {
        $facultyCode = $row['faculty_code'];
        if (!isset($grouped[$facultyCode])) {
            $grouped[$facultyCode] = [
                'id' => $row['faculty_id'],
                'name' => $row['faculty_name'],
                'code' => $row['faculty_code'],
                'courses' => []
            ];
        }
        if ($row['course_id']) {
            $grouped[$facultyCode]['courses'][] = [
                'id' => $row['course_id'],
                'name' => $row['course_name'],
                'code' => $row['course_code'],
                'professor_name' => $row['professor_name'],
                'semester' => $row['semester']
            ];
        }
    }
    
    echo json_encode([
        'success' => true,
        'data' => array_values($grouped)
    ]);
}

function getLeaderboard() {
    global $pdo;
    
    $limit = isset($_GET['limit']) ? max(1, min(100, (int)$_GET['limit'])) : 20;
    $faculty = isset($_GET['faculty']) ? trim($_GET['faculty']) : '';
    
    $sql = "
        SELECT u.id, u.full_name, u.email, u.avatar_url, u.reputation_points, u.role,
               f.name AS faculty_name, f.code AS faculty_code,
               COUNT(DISTINCT m.id) AS total_files,
               COALESCE(SUM(m.upvotes_count), 0) AS total_votes
        FROM users u
        LEFT JOIN faculties f ON u.faculty_id = f.id
        LEFT JOIN materials m ON u.id = m.user_id
    ";
    
    $params = [];
    if (!empty($faculty)) {
        $sql .= " WHERE f.code = ?";
        $params[] = $faculty;
    }
    
    $sql .= " GROUP BY u.id ORDER BY u.reputation_points DESC LIMIT {$limit}";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Add rank
    $ranked = [];
    $rank = 1;
    foreach ($users as $user) {
        $user['rank'] = $rank++;
        $user['total_files'] = (int)$user['total_files'];
        $user['total_votes'] = (int)$user['total_votes'];
        $user['reputation_points'] = (int)$user['reputation_points'];
        $ranked[] = $user;
    }
    
    echo json_encode([
        'success' => true,
        'count' => count($ranked),
        'data' => $ranked
    ]);
}
