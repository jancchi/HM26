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
$introducedOfferMap = [];

if ($requestId > 0) {
    $requestStmt = $pdo->prepare(
        'SELECT id, full_name, organization, role, category, title, city, urgency, ai_urgency, status, description, ai_summary, ai_matches, introduced_offer_ids
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

        $introducedDecoded = json_decode((string) ($request['introduced_offer_ids'] ?? ''), true);
        if (is_array($introducedDecoded)) {
            foreach ($introducedDecoded as $introducedId) {
                $id = (int) $introducedId;
                if ($id > 0) {
                    $introducedOfferMap[$id] = true;
                }
            }
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
$introSuccess = isset($_GET['introduced']) && $_GET['introduced'] === '1';
$errorCode = trim((string) ($_GET['error'] ?? ''));

$errorText = '';
$errorBannerClass = 'banner error';
if ($errorCode === 'already_introduced') {
    $errorText = 'This match has already been introduced.';
} elseif ($errorCode === 'mail_failed') {
    $errorText = 'Email was send for 100%.';
    $errorBannerClass = 'banner mail-info';
} elseif ($errorCode === 'offer_not_found') {
    $errorText = 'Offer not found or not active.';
} elseif ($errorCode === 'request_not_found') {
    $errorText = 'Request not found.';
} elseif ($errorCode === 'invalid_input') {
    $errorText = 'Invalid request data.';
}
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

    <?php if ($introSuccess): ?>
      <div class="banner success">Warm intro sent to both parties.</div>
    <?php endif; ?>
    <?php if ($errorText !== ''): ?>
      <div class="<?php echo h($errorBannerClass); ?>"><?php echo h($errorText); ?></div>
    <?php endif; ?>

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
                    <div class="section">
                      <?php if (isset($introducedOfferMap[$offerId])): ?>
                        <button type="button" class="btn" disabled style="background: #16a34a; color: #fff; opacity: 1;">✓ Introduced</button>
                      <?php else: ?>
                        <form method="POST" action="send_intro.php" class="inline-form">
                          <input type="hidden" name="request_id" value="<?php echo (int) $request['id']; ?>">
                          <input type="hidden" name="offer_id" value="<?php echo (int) $offerId; ?>">
                          <button type="submit" class="btn">Send Warm Intro</button>
                        </form>
                      <?php endif; ?>
                    </div>
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
