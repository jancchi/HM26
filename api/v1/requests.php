<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    respond(['message' => 'Method not allowed'], 405);
}

$payload = json_input();

$name = trim((string) ($payload['name'] ?? ''));
$email = trim((string) ($payload['email'] ?? ''));
$organization = trim((string) ($payload['organization'] ?? ''));
$roleInput = trim((string) ($payload['role'] ?? ''));
$categoryInput = trim((string) ($payload['category'] ?? ''));
$title = trim((string) ($payload['title'] ?? ''));
$description = trim((string) ($payload['description'] ?? ''));

$roleMap = [
    'startup' => 'Startup',
    'investor' => 'Investor',
    'service_provider' => 'Service Provider',
    'member' => 'Community Member',
];

$categoryMap = [
    'HIRING' => 'Hľadanie zamestnanca',
    'INVESTOR_INTRO' => 'Hľadanie investora',
    'SPEAKING_OPPORTUNITY' => 'Speaking na evente',
    'MARKETING_SUPPORT' => 'Zdieľanie marketingových podkladov',
    'SALES_SUPPORT' => 'Podpora v oblasti sales',
    'PARTNERSHIP' => 'Hľadanie klientov',
    'PRODUCT_FEEDBACK' => 'Iné',
    'LEGAL_FINANCE' => 'Iné',
    'OPERATIONS' => 'Iné',
    'OTHER' => 'Iné',
];

$role = $roleMap[$roleInput] ?? '';
$category = $categoryMap[$categoryInput] ?? '';

$hasRequiredValues = $name !== ''
    && $email !== ''
    && $role !== ''
    && $category !== ''
    && $description !== '';

if (!$hasRequiredValues || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respond([
        'message' => 'Overenie údajov zlyhalo',
        'detail' => [
            ['loc' => ['body', 'name'], 'msg' => 'Meno je povinné'],
            ['loc' => ['body', 'email'], 'msg' => 'Platný email je povinný'],
            ['loc' => ['body', 'role'], 'msg' => 'Rola je povinná'],
            ['loc' => ['body', 'category'], 'msg' => 'Kategória je povinná'],
            ['loc' => ['body', 'description'], 'msg' => 'Popis je povinný'],
        ],
    ], 422);
}

$fullDescription = $title !== ''
    ? $title . "\n\n" . $description
    : $description;

try {
    $stmt = $pdo->prepare(
        'INSERT INTO requests (full_name, email, organization, role, category, description)
         VALUES (:full_name, :email, :organization, :role, :category, :description)'
    );

    $stmt->execute([
        ':full_name' => $name,
        ':email' => $email,
        ':organization' => $organization !== '' ? $organization : null,
        ':role' => $role,
        ':category' => $category,
        ':description' => $fullDescription,
    ]);

    $requestId = (string) $pdo->lastInsertId();

    respond([
        'id' => $requestId,
        'status' => 'Nová',
        'createdAt' => gmdate('c'),
    ], 201);
} catch (Throwable $e) {
    respond(['message' => 'Failed to create request'], 500);
}
