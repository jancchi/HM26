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
$offerId = (int) ($_POST['offer_id'] ?? 0);

if ($requestId <= 0 || $offerId <= 0) {
    $safeId = $requestId > 0 ? $requestId : 0;
    header('Location: matches.php?id=' . urlencode((string) $safeId) . '&error=invalid_input');
    exit;
}

$requestStmt = $pdo->prepare(
    'SELECT id, full_name, email, title, category, role, city, description, introduced_offer_ids
     FROM requests
     WHERE id = :id
     LIMIT 1'
);
$requestStmt->execute([':id' => $requestId]);
$request = $requestStmt->fetch();

if (!$request) {
    header('Location: matches.php?id=' . urlencode((string) $requestId) . '&error=request_not_found');
    exit;
}

$offerStmt = $pdo->prepare(
    'SELECT id, full_name, email, organization, role, skills_description, category, city
     FROM offers
     WHERE id = :id AND active = 1
     LIMIT 1'
);
$offerStmt->execute([':id' => $offerId]);
$offer = $offerStmt->fetch();

if (!$offer) {
    header('Location: matches.php?id=' . urlencode((string) $requestId) . '&error=offer_not_found');
    exit;
}

$introduced = json_decode((string) ($request['introduced_offer_ids'] ?? ''), true);
if (!is_array($introduced)) {
    $introduced = [];
}

$normalized = [];
foreach ($introduced as $value) {
    $id = (int) $value;
    if ($id > 0) {
        $normalized[$id] = true;
    }
}

if (isset($normalized[$offerId])) {
    header('Location: matches.php?id=' . urlencode((string) $requestId) . '&error=already_introduced');
    exit;
}

$body = generateWarmIntroEmail($request, $offer);
if (trim($body) === '') {
    $body = 'Hello, this is a warm introduction from the 0100 Ecosystem Team.';
}

$subject = 'Introduction: ' . (string) $request['full_name'] . ' <> ' . (string) $offer['full_name'] . ' | 0100 Ecosystem';

$hostRaw = trim((string) ($_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? '')));
if ($hostRaw === '') {
    $hostRaw = 'localhost';
}

$hostOnly = strtolower($hostRaw);
if (strpos($hostOnly, ':') !== false) {
    $parts = explode(':', $hostOnly, 2);
    $hostOnly = (string) ($parts[0] ?? $hostOnly);
}
$hostOnly = preg_replace('/[^a-z0-9.-]/', '', $hostOnly) ?: 'localhost';

if (!preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/', $hostOnly)) {
    $hostOnly = 'localhost.localdomain';
}

$fromAddress = 'cansat.cando@gmail.com';
$headers = [
    'From: ' . $fromAddress,
    'Reply-To: ' . $fromAddress,
    'MIME-Version: 1.0',
    'Content-Type: text/plain; charset=UTF-8',
    'X-Mailer: PHP/' . PHP_VERSION,
];
$headersText = implode("\r\n", $headers);

$requestEmail = trim((string) ($request['email'] ?? ''));
$offerEmail = trim((string) ($offer['email'] ?? ''));
if ($requestEmail === '' || $offerEmail === '') {
    header('Location: matches.php?id=' . urlencode((string) $requestId) . '&error=mail_failed');
    exit;
}

$bodyToRequester = $body
    . "\n\nContact: " . (string) $offer['full_name'] . ' <' . $offerEmail . '>';
$bodyToOffer = $body
    . "\n\nContact: " . (string) $request['full_name'] . ' <' . $requestEmail . '>';

$sentRequester = false;
$sentOffer = false;
try {
    $sentRequester = @mail($requestEmail, $subject, $bodyToRequester, $headersText);
    $sentOffer = @mail($offerEmail, $subject, $bodyToOffer, $headersText);

    if (!$sentRequester || !$sentOffer) {
        $fallbackHeaders = 'From: ' . $fromAddress;
        if (!$sentRequester) {
            $sentRequester = @mail($requestEmail, $subject, $bodyToRequester, $fallbackHeaders);
        }
        if (!$sentOffer) {
            $sentOffer = @mail($offerEmail, $subject, $bodyToOffer, $fallbackHeaders);
        }
    }
} catch (Throwable $e) {
    $sentRequester = false;
    $sentOffer = false;
}

if (!$sentRequester || !$sentOffer) {
    header('Location: matches.php?id=' . urlencode((string) $requestId) . '&error=mail_failed');
    exit;
}

$normalized[$offerId] = true;
$updatedIds = array_map('intval', array_keys($normalized));
sort($updatedIds);

$updateStmt = $pdo->prepare('UPDATE requests SET introduced_offer_ids = :introduced_offer_ids WHERE id = :id');
$updateStmt->execute([
    ':introduced_offer_ids' => json_encode($updatedIds, JSON_UNESCAPED_UNICODE),
    ':id' => $requestId,
]);

header('Location: matches.php?id=' . urlencode((string) $requestId) . '&introduced=1');
exit;
?>
