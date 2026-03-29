<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/config.php';
require_once dirname(__DIR__, 2) . '/integrations.php';

function hasRequestColumn(PDO $pdo, string $columnName): bool
{
    static $columns = null;

    if ($columns === null) {
        $columns = [];
        try {
            $stmt = $pdo->query('SHOW COLUMNS FROM requests');
            foreach ($stmt->fetchAll() as $row) {
                if (isset($row['Field'])) {
                    $columns[] = (string) $row['Field'];
                }
            }
        } catch (Throwable $e) {
            $columns = [];
        }
    }

    return in_array($columnName, $columns, true);
}

function persistAiAnalysis(PDO $pdo, int $requestId, array $analysis): void
{
    if ($requestId <= 0) {
        return;
    }

    $candidateColumns = [
        'ai_category' => $analysis['category'] ?? null,
        'ai_urgency' => $analysis['urgency'] ?? null,
        'ai_summary' => $analysis['summary'] ?? null,
        'ai_recommended_member_profile' => $analysis['recommended_member_profile'] ?? null,
        'ai_raw_json' => $analysis['raw_json'] ?? null,
    ];

    $sets = [];
    $params = [':id' => $requestId];
    foreach ($candidateColumns as $column => $value) {
        if (!hasRequestColumn($pdo, $column)) {
            continue;
        }
        $sets[] = $column . ' = :' . $column;
        $params[':' . $column] = $value;
    }

    if (empty($sets)) {
        return;
    }

    try {
        $stmt = $pdo->prepare('UPDATE requests SET ' . implode(', ', $sets) . ' WHERE id = :id');
        $stmt->execute($params);
    } catch (Throwable $e) {
    }
}

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

    $analysis = analyzeRequestWithGemini([
        'full_name' => $name,
        'email' => $email,
        'organization' => $organization,
        'role' => $role,
        'category' => $category,
        'title' => $title,
        'description' => $description,
    ]);
    persistAiAnalysis($pdo, (int) $requestId, $analysis);

    respond([
        'id' => $requestId,
        'status' => 'Nová',
        'createdAt' => gmdate('c'),
    ], 201);
} catch (Throwable $e) {
    respond(['message' => 'Failed to create request'], 500);
}
