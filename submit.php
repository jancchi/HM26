<?php
require_once 'db.php';
require_once 'config.php';
require_once 'integrations.php';

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
        } catch (Exception $e) {
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
    } catch (Exception $e) {
    }
}

$allowedRoles = ['Startup', 'Investor', 'Service Provider', 'Community Member'];
$allowedCategories = [
    'Employee Search',
    'Investor Search',
    'Event Speaking',
    'Marketing Materials Sharing',
    'Sales Support',
    'Client Search',
    'Other',
];
$allowedUrgencies = ['low', 'medium', 'high'];
$allowedHelpTypes = ['volunteer', 'financial', 'material', 'other'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php?error=1');
    exit;
}

$fullName = htmlspecialchars(trim($_POST['full_name'] ?? ''), ENT_QUOTES, 'UTF-8');
$email = htmlspecialchars(trim($_POST['email'] ?? ''), ENT_QUOTES, 'UTF-8');
$organization = htmlspecialchars(trim($_POST['organization'] ?? ''), ENT_QUOTES, 'UTF-8');
$role = htmlspecialchars(trim($_POST['role'] ?? ''), ENT_QUOTES, 'UTF-8');
$category = htmlspecialchars(trim($_POST['category'] ?? ''), ENT_QUOTES, 'UTF-8');
$description = htmlspecialchars(trim($_POST['description'] ?? ''), ENT_QUOTES, 'UTF-8');
$title = htmlspecialchars(trim($_POST['title'] ?? ''), ENT_QUOTES, 'UTF-8');
$city = htmlspecialchars(trim($_POST['city'] ?? ''), ENT_QUOTES, 'UTF-8');
$phone = htmlspecialchars(trim($_POST['phone'] ?? ''), ENT_QUOTES, 'UTF-8');
$urgency = htmlspecialchars(trim($_POST['urgency'] ?? 'medium'), ENT_QUOTES, 'UTF-8');
$deadline = htmlspecialchars(trim($_POST['deadline'] ?? ''), ENT_QUOTES, 'UTF-8');
$budgetRaw = trim((string) ($_POST['budget'] ?? ''));
$helpType = htmlspecialchars(trim($_POST['help_type'] ?? 'volunteer'), ENT_QUOTES, 'UTF-8');
$tagsText = htmlspecialchars(trim($_POST['tags_text'] ?? ''), ENT_QUOTES, 'UTF-8');

$hasRequiredValues = $fullName !== ''
    && $email !== ''
    && $role !== ''
    && $category !== ''
    && $description !== ''
    && $title !== ''
    && $city !== '';

$budget = null;
if ($budgetRaw !== '') {
    if (!is_numeric($budgetRaw)) {
        header('Location: index.php?error=1');
        exit;
    }

    $budget = (float) $budgetRaw;
    if ($budget < 0) {
        header('Location: index.php?error=1');
        exit;
    }
}

$isValid = $hasRequiredValues
    && in_array($role, $allowedRoles, true)
    && in_array($category, $allowedCategories, true)
    && in_array($urgency, $allowedUrgencies, true)
    && in_array($helpType, $allowedHelpTypes, true)
    && filter_var($email, FILTER_VALIDATE_EMAIL);

if (!$isValid) {
    header('Location: index.php?error=1');
    exit;
}

if ($deadline !== '') {
    $date = DateTime::createFromFormat('Y-m-d', $deadline);
    if (!$date || $date->format('Y-m-d') !== $deadline) {
        header('Location: index.php?error=1');
        exit;
    }
}

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
        ':full_name' => $fullName,
        ':email' => $email,
        ':organization' => $organization !== '' ? $organization : null,
        ':role' => $role,
        ':category' => $category,
        ':title' => $title,
        ':city' => $city,
        ':phone' => $phone !== '' ? $phone : null,
        ':description' => $description,
        ':urgency' => $urgency,
        ':deadline' => $deadline !== '' ? $deadline : null,
        ':budget' => $budget,
        ':help_type' => $helpType,
        ':tags_text' => $tagsText !== '' ? $tagsText : null,
    ]);

    $newId = (string) $pdo->lastInsertId();

    $analysis = analyzeRequestWithGemini([
        'full_name' => $fullName,
        'email' => $email,
        'organization' => $organization,
        'role' => $role,
        'category' => $category,
        'title' => $title,
        'city' => $city,
        'description' => $description,
        'urgency' => $urgency,
        'deadline' => $deadline,
        'budget' => $budget,
        'help_type' => $helpType,
        'tags_text' => $tagsText,
    ]);
    persistAiAnalysis($pdo, (int) $newId, $analysis);

    try {
        $offersStmt = $pdo->query('SELECT id, full_name, role, skills_description, category, city FROM offers WHERE active = 1 ORDER BY created_at DESC LIMIT 50');
        $allOffers = $offersStmt->fetchAll();
        if (!empty($allOffers)) {
            $matches = matchRequestToOffers([
                'title' => $title,
                'category' => $category,
                'role' => $role,
                'description' => $description,
                'city' => $city,
                'urgency' => $urgency,
                'help_type' => $helpType,
                'tags_text' => $tagsText,
            ], $allOffers);
            if (!empty($matches)) {
                $pdo->prepare('UPDATE requests SET ai_matches = :m WHERE id = :id')
                    ->execute([':m' => json_encode($matches, JSON_UNESCAPED_UNICODE), ':id' => (int) $newId]);
            }
        }
    } catch (Exception $e) {
    }

    header('Location: index.php?success=1&id=' . urlencode($newId));
    exit;
} catch (Exception $e) {
    header('Location: index.php?error=1');
    exit;
}
?>
