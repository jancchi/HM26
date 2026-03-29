<?php
require_once 'db.php';

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
    header('Location: index.php?success=1&id=' . urlencode($newId));
    exit;
} catch (Exception $e) {
    header('Location: index.php?error=1');
    exit;
}
?>
