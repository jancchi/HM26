<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: admin.php');
    exit;
}

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

$requestId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

$request = null;
$matches = [];
$offersById = [];

if ($requestId > 0) {
    $requestStmt = $pdo->prepare(
        'SELECT id, full_name, organization, role, category, title, city, urgency, ai_urgency, status, description, ai_summary, ai_matches
         FROM requests
         WHERE id = :id
         LIMIT 1'
    );
    $requestStmt->execute([':id' => $requestId]);
    $request = $requestStmt->fetch();

    if ($request) {
        $decoded = json_decode((string) ($request['ai_matches'] ?? ''), true);
        if (is_array($decoded)) {
            $matches = $decoded;
        }

        if (!empty($matches)) {
            $ids = [];
            foreach ($matches as $m) {
                if (isset($m['offer_id'])) {
                    $id = (int) $m['offer_id'];
                    if ($id > 0) {
                        $ids[$id] = true;
                    }
                }
            }

            if (!empty($ids)) {
                $placeholders = [];
                $params = [];
                $index = 1;
                foreach (array_keys($ids) as $id) {
                    $key = ':id' . $index;
                    $placeholders[] = $key;
                    $params[$key] = $id;
                    $index++;
                }

                $offersStmt = $pdo->prepare(
                    'SELECT id, full_name, email, organization, role, skills_description, category, city
                     FROM offers
                     WHERE active = 1 AND id IN (' . implode(', ', $placeholders) . ')'
                );
                $offersStmt->execute($params);
                foreach ($offersStmt->fetchAll() as $offer) {
                    $offersById[(int) $offer['id']] = $offer;
                }
            }
        }
    }
}

$matchCount = count($matches);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Request Matches</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <div class="container">
    <div class="header-row">
      <div>
        <h1 class="title">AI Matches</h1>
        <p class="subtitle">Request to community offer matching</p>
      </div>
      <div class="nav-links">
        <a class="nav-link" href="admin.php">Back to Admin</a>
        <a class="nav-link" href="offers.php">Community Offers</a>
      </div>
    </div>

    <?php if (!$request): ?>
      <div class="panel section">
        <p class="muted">Request not found.</p>
      </div>
    <?php else: ?>
      <div class="split section" style="align-items: flex-start;">
        <div class="panel">
          <div class="meta-label muted">Request Details</div>
          <div class="section"><strong>Title:</strong> <?php echo h((string) ($request['title'] ?? '')); ?></div>
          <div class="section"><strong>Name:</strong> <?php echo h((string) ($request['full_name'] ?? '')); ?></div>
          <div class="section"><strong>Organization:</strong> <?php echo h((string) ($request['organization'] ?? '')); ?></div>
          <div class="section"><strong>Role:</strong> <?php echo h((string) ($request['role'] ?? '')); ?></div>
          <div class="section"><strong>Category:</strong> <?php echo h((string) ($request['category'] ?? '')); ?></div>
          <div class="section"><strong>City:</strong> <?php echo h((string) ($request['city'] ?? '')); ?></div>
          <div class="section"><strong>Urgency:</strong> <?php echo h((string) ($request['urgency'] ?? '')); ?></div>
          <div class="section"><strong>AI Urgency:</strong> <?php echo h((string) ($request['ai_urgency'] ?? '')); ?></div>
          <div class="section"><strong>Status:</strong> <?php echo h((string) ($request['status'] ?? '')); ?></div>
          <div class="section"><strong>Description:</strong><br><?php echo nl2br(h((string) ($request['description'] ?? ''))); ?></div>
          <div class="section"><strong>AI Summary:</strong><br><?php echo nl2br(h((string) ($request['ai_summary'] ?? ''))); ?></div>
        </div>

        <div class="panel">
          <h2 class="title" style="font-size: 1.4rem;">Top <?php echo (int) $matchCount; ?> Community Matches</h2>

          <?php if (empty($matches)): ?>
            <p class="muted section">No matches available for this request yet.</p>
          <?php else: ?>
            <div class="list section">
              <?php foreach ($matches as $match): ?>
                <?php
                  $offerId = (int) ($match['offer_id'] ?? 0);
                  $score = (int) ($match['score'] ?? 0);
                  $reason = (string) ($match['reason'] ?? '');
                  $offer = $offersById[$offerId] ?? null;
                  if (!$offer) {
                      continue;
                  }
                  $scoreColor = '#dc2626';
                  if ($score >= 8) {
                      $scoreColor = '#16a34a';
                  } elseif ($score >= 5) {
                      $scoreColor = '#d97706';
                  }
                ?>
                <div class="list-item" style="display: grid; grid-template-columns: auto 1fr; gap: 12px; align-items: start;">
                  <div style="width: 42px; height: 42px; border-radius: 999px; background: <?php echo h($scoreColor); ?>; color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 700;">
                    <?php echo (int) $score; ?>
                  </div>
                  <div>
                    <div><strong><?php echo h((string) ($offer['full_name'] ?? '')); ?></strong></div>
                    <div class="muted"><?php echo h((string) ($offer['role'] ?? '')); ?></div>
                    <div class="muted"><?php echo h((string) ($offer['organization'] ?? '')); ?></div>
                    <div class="muted">City: <?php echo h((string) ($offer['city'] ?? '')); ?></div>
                    <div class="section"><?php echo nl2br(h((string) ($offer['skills_description'] ?? ''))); ?></div>
                    <blockquote style="margin: 8px 0; padding: 8px 10px; border-left: 3px solid #16a34a; font-style: italic;">
                      <?php echo h($reason); ?>
                    </blockquote>
                    <?php $email = trim((string) ($offer['email'] ?? '')); ?>
                    <?php if ($email !== ''): ?>
                      <a class="nav-link" href="mailto:<?php echo h($email); ?>">Contact by Email</a>
                    <?php endif; ?>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>

          <form method="POST" action="rematch.php" class="section">
            <input type="hidden" name="request_id" value="<?php echo (int) $request['id']; ?>">
            <button type="submit" class="btn">Re-run AI Matching</button>
          </form>
        </div>
      </div>
    <?php endif; ?>
  </div>
</body>
</html>
