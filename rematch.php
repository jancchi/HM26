<?php
session_start();
require_once 'db.php';
require_once 'config.php';
require_once 'integrations.php';

if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: admin.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: admin.php');
    exit;
}

$requestId = (int) ($_POST['request_id'] ?? 0);
if ($requestId <= 0) {
    header('Location: admin.php');
    exit;
}

try {
    $requestStmt = $pdo->prepare(
        'SELECT id, title, category, role, description, city, urgency, help_type, tags_text
         FROM requests
         WHERE id = :id
         LIMIT 1'
    );
    $requestStmt->execute([':id' => $requestId]);
    $request = $requestStmt->fetch();

    if (!$request) {
        header('Location: admin.php');
        exit;
    }

    $offersStmt = $pdo->query(
        'SELECT id, full_name, role, skills_description, category, city
         FROM offers
         WHERE active = 1
         ORDER BY created_at DESC
         LIMIT 50'
    );
    $allOffers = $offersStmt->fetchAll();

    $matches = [];
    if (!empty($allOffers)) {
        $matches = matchRequestToOffers([
            'title' => (string) ($request['title'] ?? ''),
            'category' => (string) ($request['category'] ?? ''),
            'role' => (string) ($request['role'] ?? ''),
            'description' => (string) ($request['description'] ?? ''),
            'city' => (string) ($request['city'] ?? ''),
            'urgency' => (string) ($request['urgency'] ?? ''),
            'help_type' => (string) ($request['help_type'] ?? ''),
            'tags_text' => (string) ($request['tags_text'] ?? ''),
        ], $allOffers);
    }

    $updateStmt = $pdo->prepare('UPDATE requests SET ai_matches = :m WHERE id = :id');
    $updateStmt->execute([
        ':m' => !empty($matches) ? json_encode($matches, JSON_UNESCAPED_UNICODE) : null,
        ':id' => $requestId,
    ]);
} catch (Exception $e) {
}

header('Location: matches.php?id=' . urlencode((string) $requestId));
exit;
?>
