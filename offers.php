<?php
require_once 'db.php';

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim((string) ($_POST['full_name'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $organization = trim((string) ($_POST['organization'] ?? ''));
    $role = trim((string) ($_POST['role'] ?? ''));
    $skillsDescription = trim((string) ($_POST['skills_description'] ?? ''));
    $category = trim((string) ($_POST['category'] ?? ''));
    $city = trim((string) ($_POST['city'] ?? ''));

    $valid = $fullName !== ''
        && $email !== ''
        && filter_var($email, FILTER_VALIDATE_EMAIL)
        && in_array($role, $allowedRoles, true)
        && $skillsDescription !== ''
        && $city !== ''
        && ($category === '' || in_array($category, $allowedCategories, true));

    if (!$valid) {
        header('Location: offers.php?error=1');
        exit;
    }

    try {
        $insertStmt = $pdo->prepare(
            'INSERT INTO offers (full_name, email, organization, role, skills_description, category, city, active)
             VALUES (:full_name, :email, :organization, :role, :skills_description, :category, :city, 1)'
        );
        $insertStmt->execute([
            ':full_name' => $fullName,
            ':email' => $email,
            ':organization' => $organization !== '' ? $organization : null,
            ':role' => $role,
            ':skills_description' => $skillsDescription,
            ':category' => $category !== '' ? $category : null,
            ':city' => $city,
        ]);
        header('Location: offers.php?success=1');
        exit;
    } catch (Exception $e) {
        header('Location: offers.php?error=1');
        exit;
    }
}

$offersStmt = $pdo->query('SELECT id, full_name, organization, role, skills_description, category, city FROM offers WHERE active = 1 ORDER BY created_at DESC');
$offers = $offersStmt->fetchAll();

$success = isset($_GET['success']) && $_GET['success'] === '1';
$error = isset($_GET['error']) && $_GET['error'] === '1';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Community Offers</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <div class="container">
    <div class="header-row">
      <div>
        <h1 class="title">Community Offers</h1>
        <p class="subtitle">Share what you can offer to the 0100 ecosystem</p>
      </div>
      <div class="nav-links">
        <a class="nav-link" href="index.php">Public Form</a>
        <a class="nav-link" href="admin.php">Admin</a>
      </div>
    </div>

    <?php if ($success): ?>
      <div class="banner success">Offer submitted successfully.</div>
    <?php elseif ($error): ?>
      <div class="banner error">Offer submission failed. Check required fields.</div>
    <?php endif; ?>

    <div class="panel section">
      <div class="meta-label muted">Submit an Offer</div>
      <form method="POST" action="offers.php" class="form-grid section">
        <div class="form-group">
          <label for="full_name">Full Name *</label>
          <input id="full_name" name="full_name" type="text" required>
        </div>
        <div class="form-group">
          <label for="email">Email *</label>
          <input id="email" name="email" type="email" required>
        </div>
        <div class="form-group">
          <label for="organization">Organization</label>
          <input id="organization" name="organization" type="text">
        </div>
        <div class="form-group">
          <label for="role">Role *</label>
          <select id="role" name="role" required>
            <option value="">Select role</option>
            <?php foreach ($allowedRoles as $role): ?>
              <option value="<?php echo h($role); ?>"><?php echo h($role); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label for="category">Category</label>
          <select id="category" name="category">
            <option value="">Any</option>
            <?php foreach ($allowedCategories as $category): ?>
              <option value="<?php echo h($category); ?>"><?php echo h($category); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label for="city">City *</label>
          <input id="city" name="city" type="text" required>
        </div>
        <div class="form-group" style="grid-column: 1 / -1;">
          <label for="skills_description">Skills Description *</label>
          <textarea id="skills_description" name="skills_description" rows="5" required></textarea>
        </div>
        <button type="submit" class="btn">Submit Offer</button>
      </form>
    </div>

    <div class="panel section">
      <div class="meta-label muted">Active Community Offers</div>
      <?php if (empty($offers)): ?>
        <p class="muted section">No active offers yet.</p>
      <?php else: ?>
        <div class="list section">
          <?php foreach ($offers as $offer): ?>
            <div class="list-item">
              <div><strong><?php echo h((string) $offer['full_name']); ?></strong></div>
              <div class="muted"><?php echo h((string) $offer['role']); ?></div>
              <div class="muted"><?php echo h((string) ($offer['organization'] ?? '')); ?></div>
              <div class="muted">City: <?php echo h((string) $offer['city']); ?></div>
              <?php if (!empty($offer['category'])): ?>
                <div class="section"><span class="pill"><?php echo h((string) $offer['category']); ?></span></div>
              <?php endif; ?>
              <p class="section"><?php echo nl2br(h((string) $offer['skills_description'])); ?></p>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</body>
</html>
