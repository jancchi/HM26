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
$city = trim((string) ($payload['city'] ?? ''));
$phone = trim((string) ($payload['phone'] ?? ''));
$urgencyInput = trim((string) ($payload['urgency'] ?? 'medium'));
$deadline = trim((string) ($payload['deadline'] ?? ''));
$budgetRaw = trim((string) ($payload['budget'] ?? ''));
$helpTypeInput = trim((string) ($payload['help_type'] ?? ($payload['helpType'] ?? 'volunteer')));
$tagsInput = $payload['tags_text'] ?? ($payload['tagsText'] ?? ($payload['tags'] ?? ''));
$description = trim((string) ($payload['description'] ?? ''));

$roleMap = [
    'startup' => 'Startup',
    'investor' => 'Investor',
    'service_provider' => 'Service Provider',
    'member' => 'Community Member',
    'Startup' => 'Startup',
    'Investor' => 'Investor',
    'Service Provider' => 'Service Provider',
    'Community Member' => 'Community Member',
];

$categoryMap = [
    'HIRING' => 'Employee Search',
    'INVESTOR_INTRO' => 'Investor Search',
    'SPEAKING_OPPORTUNITY' => 'Event Speaking',
    'MARKETING_SUPPORT' => 'Marketing Materials Sharing',
    'SALES_SUPPORT' => 'Sales Support',
    'PARTNERSHIP' => 'Client Search',
    'PRODUCT_FEEDBACK' => 'Other',
    'LEGAL_FINANCE' => 'Other',
    'OPERATIONS' => 'Other',
    'OTHER' => 'Other',
    'Employee Search' => 'Employee Search',
    'Investor Search' => 'Investor Search',
    'Event Speaking' => 'Event Speaking',
    'Marketing Materials Sharing' => 'Marketing Materials Sharing',
    'Sales Support' => 'Sales Support',
    'Client Search' => 'Client Search',
    'Other' => 'Other',
    'Hľadanie zamestnanca' => 'Employee Search',
    'Hľadanie investora' => 'Investor Search',
    'Speaking na evente' => 'Event Speaking',
    'Zdieľanie marketingových podkladov' => 'Marketing Materials Sharing',
    'Podpora v oblasti sales' => 'Sales Support',
    'Hľadanie klientov' => 'Client Search',
    'Iné' => 'Other',
];

$allowedUrgencies = ['low', 'medium', 'high'];
$allowedHelpTypes = ['volunteer', 'financial', 'material', 'other'];

$role = $roleMap[$roleInput] ?? '';
$category = $categoryMap[$categoryInput] ?? '';
$urgency = in_array($urgencyInput, $allowedUrgencies, true) ? $urgencyInput : 'medium';
$helpType = in_array($helpTypeInput, $allowedHelpTypes, true) ? $helpTypeInput : 'volunteer';

if ($city === '') {
    $city = '-';
}

if ($title === '') {
    $title = mb_substr($description !== '' ? $description : 'Request', 0, 120, 'UTF-8');
}

$budget = null;
if ($budgetRaw !== '' && is_numeric($budgetRaw)) {
    $budgetValue = (float) $budgetRaw;
    if ($budgetValue >= 0) {
        $budget = $budgetValue;
    }
}

$tagsText = '';
if (is_array($tagsInput)) {
    $cleanTags = [];
    foreach ($tagsInput as $tag) {
        if (!is_scalar($tag)) {
            continue;
        }
        $tagValue = trim((string) $tag);
        if ($tagValue !== '') {
            $cleanTags[] = $tagValue;
        }
    }
    $tagsText = implode(', ', $cleanTags);
} elseif (is_scalar($tagsInput)) {
    $tagsText = trim((string) $tagsInput);
}

$hasRequiredValues = $name !== ''
    && $email !== ''
    && $role !== ''
    && $category !== ''
    && $description !== ''
    && $title !== ''
    && $city !== '';

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
        'INSERT INTO requests (
            full_name, email, organization, role, category, title, city, phone,
            description, urgency, deadline, budget, help_type, tags_text
        ) VALUES (
            :full_name, :email, :organization, :role, :category, :title, :city, :phone,
            :description, :urgency, :deadline, :budget, :help_type, :tags_text
        )'
    );

    $stmt->execute([
        ':full_name' => $name,
        ':email' => $email,
        ':organization' => $organization !== '' ? $organization : null,
        ':role' => $role,
        ':category' => $category,
        ':title' => $title,
        ':city' => $city,
        ':phone' => $phone !== '' ? $phone : null,
        ':description' => $fullDescription,
        ':urgency' => $urgency,
        ':deadline' => $deadline !== '' ? $deadline : null,
        ':budget' => $budget,
        ':help_type' => $helpType,
        ':tags_text' => $tagsText !== '' ? $tagsText : null,
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
        'status' => 'New',
        'createdAt' => gmdate('c'),
    ], 201);
} catch (Throwable $e) {
    respond(['message' => 'Failed to create request'], 500);
}
