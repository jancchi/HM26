<?php
session_start();
require_once 'db.php';

$adminPassword = 'admin0100';
$allowedCategories = [
    'Employee Search',
    'Investor Search',
    'Event Speaking',
    'Marketing Materials Sharing',
    'Sales Support',
    'Client Search',
    'Other',
];
$allowedStatuses = ['New', 'Nová', 'Assigned', 'In Progress', 'Done (Waiting Approval)', 'Resolved', 'Vyriešená'];

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function canonicalCategory(string $category): string
{
    $category = trim($category);

    $map = [
        'Hladanie zamestnanca' => 'Employee Search',
        'Hľadanie zamestnanca' => 'Employee Search',
        'Hladanie investora' => 'Investor Search',
        'Hľadanie investora' => 'Investor Search',
        'Speaking na evente' => 'Event Speaking',
        'Zdielanie marketingovych podkladov' => 'Marketing Materials Sharing',
        'Zdielanie marketyngovych podkladov' => 'Marketing Materials Sharing',
        'Zdieľanie marketingových podkladov' => 'Marketing Materials Sharing',
        'Podpora v oblasti sales' => 'Sales Support',
        'Hladanie klientov' => 'Client Search',
        'Hľadanie klientov' => 'Client Search',
        'Ine' => 'Other',
        'Iné' => 'Other',
    ];

    if (isset($map[$category])) {
        return $map[$category];
    }

    $normalized = normalizeCategoryKey($category);
    if ($normalized === 'hladaniezamestnanca' || $normalized === 'employeesearch') {
        return 'Employee Search';
    }
    if ($normalized === 'hladanieinvestora' || $normalized === 'investorsearch') {
        return 'Investor Search';
    }
    if ($normalized === 'speakingnaevente' || $normalized === 'eventspeaking') {
        return 'Event Speaking';
    }
    if ($normalized === 'zdielaniemarketingovychpodkladov' || $normalized === 'zdielaniemarketyngovychpodkladov' || $normalized === 'marketingmaterialssharing') {
        return 'Marketing Materials Sharing';
    }
    if ($normalized === 'podporavoblastisales' || $normalized === 'salessupport') {
        return 'Sales Support';
    }
    if ($normalized === 'hladanieklientov' || $normalized === 'clientsearch') {
        return 'Client Search';
    }
    if ($normalized === 'ine' || $normalized === 'other') {
        return 'Other';
    }

    return $category;
}

function normalizeCategoryKey(string $category): string
{
    $clean = mb_strtolower(trim($category), 'UTF-8');
    $clean = strtr($clean, [
        'á' => 'a',
        'ä' => 'a',
        'č' => 'c',
        'ď' => 'd',
        'é' => 'e',
        'ě' => 'e',
        'í' => 'i',
        'ĺ' => 'l',
        'ľ' => 'l',
        'ň' => 'n',
        'ó' => 'o',
        'ô' => 'o',
        'ŕ' => 'r',
        'š' => 's',
        'ť' => 't',
        'ú' => 'u',
        'ý' => 'y',
        'ž' => 'z',
    ]);

    return preg_replace('/[^a-z0-9]/', '', $clean) ?? '';
}

function asciiCategory(string $category): string
{
    $map = [
        'Employee Search' => 'Hladanie zamestnanca',
        'Investor Search' => 'Hladanie investora',
        'Event Speaking' => 'Speaking na evente',
        'Marketing Materials Sharing' => 'Zdielanie marketingovych podkladov',
        'Sales Support' => 'Podpora v oblasti sales',
        'Client Search' => 'Hladanie klientov',
        'Other' => 'Ine',
    ];

    return $map[$category] ?? $category;
}

function localizedCategory(string $category): string
{
    $map = [
        'Employee Search' => 'Hľadanie zamestnanca',
        'Investor Search' => 'Hľadanie investora',
        'Event Speaking' => 'Speaking na evente',
        'Marketing Materials Sharing' => 'Zdieľanie marketingových podkladov',
        'Sales Support' => 'Podpora v oblasti sales',
        'Client Search' => 'Hľadanie klientov',
        'Other' => 'Iné',
    ];

    return $map[$category] ?? $category;
}

function categoryVariants(string $category): array
{
    $raw = trim($category);
    $canonical = canonicalCategory($raw);
    $localized = localizedCategory($canonical);
    $ascii = asciiCategory($canonical);

    return array_values(array_unique([$raw, $canonical, $localized, $ascii]));
}

function statusClass(string $status): string
{
    if ($status === 'New' || $status === 'Nová') {
        return 'new';
    }
    if ($status === 'Assigned') {
        return 'assigned';
    }
    if ($status === 'In Progress') {
        return 'progress';
    }
    if ($status === 'Done (Waiting Approval)') {
        return 'waiting';
    }
    return 'resolved';
}

function isResolvedStatus(string $status): bool
{
    return $status === 'Resolved' || $status === 'Vyriešená';
}

function pickWorkerForCategory(PDO $pdo, string $requestCategory): ?array
{
    $canonicalRequestCategory = canonicalCategory($requestCategory);

    $candidateStmt = $pdo->prepare(
        'SELECT w.id, w.full_name, wc.category, COUNT(r.id) AS open_count
         FROM workers w
         INNER JOIN worker_categories wc ON wc.worker_id = w.id
         LEFT JOIN requests r
           ON r.assigned_worker_id = w.id
           AND (r.status = :assigned OR r.status = :in_progress OR r.status = :in_progress_legacy)
         WHERE w.active = 1
         GROUP BY w.id, w.full_name, wc.category'
    );
    $candidateStmt->execute([
        ':assigned' => 'Assigned',
        ':in_progress' => 'In Progress',
        ':in_progress_legacy' => 'V riešení',
    ]);

    $best = null;
    foreach ($candidateStmt->fetchAll() as $row) {
        $workerCategory = canonicalCategory((string) ($row['category'] ?? ''));
        if ($workerCategory !== $canonicalRequestCategory) {
            continue;
        }

        $openCount = (int) ($row['open_count'] ?? 0);
        $id = (int) ($row['id'] ?? 0);
        if ($id <= 0) {
            continue;
        }

        if ($best === null || $openCount < $best['open_count'] || ($openCount === $best['open_count'] && $id < $best['id'])) {
            $best = [
                'id' => $id,
                'full_name' => (string) ($row['full_name'] ?? ''),
                'open_count' => $openCount,
            ];
        }
    }

    return $best;
}

function workerEligibleForCategory(PDO $pdo, int $workerId, string $requestCategory): bool
{
    if ($workerId <= 0) {
        return false;
    }

    $workerCategoryStmt = $pdo->prepare(
        'SELECT wc.category
         FROM workers w
         INNER JOIN worker_categories wc ON wc.worker_id = w.id
         WHERE w.id = :worker_id AND w.active = 1'
    );
    $workerCategoryStmt->execute([':worker_id' => $workerId]);

    $canonicalRequestCategory = canonicalCategory($requestCategory);
    foreach ($workerCategoryStmt->fetchAll() as $row) {
        if (canonicalCategory((string) ($row['category'] ?? '')) === $canonicalRequestCategory) {
            return true;
        }
    }

    return false;
}

function redirectWithFilters(array $allowedCategories, array $allowedStatuses): void
{
    $query = [];
    $category = trim($_POST['current_category'] ?? '');
    $status = trim($_POST['current_status'] ?? '');
    $unassigned = trim($_POST['current_unassigned'] ?? '');

    if (in_array($category, $allowedCategories, true)) {
        $query['category'] = $category;
    }
    if (in_array($status, $allowedStatuses, true)) {
        $query['status'] = $status;
    }
    if ($unassigned === '1') {
        $query['unassigned'] = '1';
    }

    $location = 'admin.php';
    if (!empty($query)) {
        $location .= '?' . http_build_query($query);
    }

    header('Location: ' . $location);
    exit;
}

if (isset($_GET['logout']) && $_GET['logout'] === '1') {
    $_SESSION = [];
    session_destroy();
    header('Location: admin.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dismiss_notice'])) {
    unset($_SESSION['admin_notice']);
    header('Location: admin.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_password']) && !isset($_SESSION['admin_logged_in'])) {
    if (trim($_POST['login_password']) === $adminPassword) {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_notice'] = ['type' => 'success', 'text' => 'Welcome back, admin.'];
        header('Location: admin.php');
        exit;
    }
    $loginError = 'Invalid password.';
}

if (!isset($_SESSION['admin_logged_in'])) {
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Login</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <div class="center-wrap">
    <div class="card" style="max-width: 460px;">
      <h1 class="title">Admin Login</h1>
      <p class="subtitle">Request Management Access</p>
      <?php if (!empty($loginError)): ?>
        <div class="banner error"><?php echo h($loginError); ?></div>
      <?php endif; ?>
      <form method="POST" action="admin.php" class="form-grid section">
        <div class="form-group">
          <label for="login_password">Password</label>
          <input type="password" id="login_password" name="login_password" required>
        </div>
        <button type="submit" class="btn block">Login</button>
      </form>
    </div>
  </div>
</body>
</html>
    <?php
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_vip_request_id'], $_POST['toggle_vip_value'])) {
    $requestId = (int) ($_POST['toggle_vip_request_id'] ?? 0);
    $vipValue = trim($_POST['toggle_vip_value']) === '1' ? 1 : 0;
    if ($requestId > 0) {
        $vipStmt = $pdo->prepare('UPDATE requests SET is_vip = :is_vip WHERE id = :id');
        $vipStmt->execute([
            ':is_vip' => $vipValue,
            ':id' => $requestId,
        ]);
        $_SESSION['admin_notice'] = ['type' => 'success', 'text' => 'VIP status updated for request #' . $requestId . '.'];
    }
    redirectWithFilters($allowedCategories, $allowedStatuses);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_offer_id'])) {
    $offerId = (int) ($_POST['delete_offer_id'] ?? 0);
    if ($offerId > 0) {
        try {
            $deleteOfferStmt = $pdo->prepare('DELETE FROM offers WHERE id = :id');
            $deleteOfferStmt->execute([':id' => $offerId]);
            $_SESSION['admin_notice'] = ['type' => 'success', 'text' => 'Community offer deleted.'];
        } catch (Exception $e) {
            $_SESSION['admin_notice'] = ['type' => 'error', 'text' => 'Could not delete offer.'];
        }
    }
    header('Location: admin.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_done_request_id'])) {
    $requestId = (int) ($_POST['delete_done_request_id'] ?? 0);
    if ($requestId > 0) {
        $requestStmt = $pdo->prepare('SELECT status FROM requests WHERE id = :id LIMIT 1');
        $requestStmt->execute([':id' => $requestId]);
        $request = $requestStmt->fetch();

        if (!$request || !isResolvedStatus((string) ($request['status'] ?? ''))) {
            $_SESSION['admin_notice'] = ['type' => 'error', 'text' => 'Only resolved tasks can be deleted.'];
            redirectWithFilters($allowedCategories, $allowedStatuses);
        }

        $deleteRequestStmt = $pdo->prepare('DELETE FROM requests WHERE id = :id');
        $deleteRequestStmt->execute([':id' => $requestId]);
        $_SESSION['admin_notice'] = ['type' => 'success', 'text' => 'Done task deleted.'];
    }
    redirectWithFilters($allowedCategories, $allowedStatuses);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['worker_name'], $_POST['worker_username'], $_POST['worker_password'])) {
    $workerName = trim($_POST['worker_name']);
    $workerUsername = trim($_POST['worker_username']);
    $workerPassword = trim($_POST['worker_password']);
    $workerCategory = canonicalCategory(trim($_POST['worker_category'] ?? ''));

    if ($workerName === '' || $workerUsername === '' || $workerPassword === '' || !in_array($workerCategory, $allowedCategories, true)) {
        $_SESSION['admin_notice'] = ['type' => 'error', 'text' => 'Worker creation failed. Fill all fields and select a valid category.'];
        header('Location: admin.php');
        exit;
    }

    try {
        $pdo->beginTransaction();
        $workerStmt = $pdo->prepare('INSERT INTO workers (full_name, username, password_plain, active) VALUES (:name, :username, :password, 1)');
        $workerStmt->execute([
            ':name' => $workerName,
            ':username' => $workerUsername,
            ':password' => $workerPassword,
        ]);
        $newWorkerId = (int) $pdo->lastInsertId();
        $categoryStmt = $pdo->prepare('INSERT INTO worker_categories (worker_id, category) VALUES (:worker_id, :category)');
        $categoryStmt->execute([
            ':worker_id' => $newWorkerId,
            ':category' => $workerCategory,
        ]);
        $pdo->commit();
        $_SESSION['admin_notice'] = ['type' => 'success', 'text' => 'Worker added successfully.'];
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['admin_notice'] = ['type' => 'error', 'text' => 'Could not add worker. Username may already exist.'];
    }
    header('Location: admin.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_request_id'])) {
    $requestId = (int) ($_POST['assign_request_id'] ?? 0);
    $adminNote = trim($_POST['admin_note'] ?? '');

    $requestStmt = $pdo->prepare('SELECT id, category FROM requests WHERE id = :id LIMIT 1');
    $requestStmt->execute([':id' => $requestId]);
    $request = $requestStmt->fetch();
    if (!$request) {
        $_SESSION['admin_notice'] = ['type' => 'error', 'text' => 'Request not found.'];
        redirectWithFilters($allowedCategories, $allowedStatuses);
    }

    $requestCategory = canonicalCategory((string) $request['category']);
    $worker = pickWorkerForCategory($pdo, $requestCategory);

    if (!$worker) {
        $_SESSION['admin_notice'] = ['type' => 'error', 'text' => 'No active worker in this category.'];
        redirectWithFilters($allowedCategories, $allowedStatuses);
    }

    $assignStmt = $pdo->prepare('UPDATE requests SET assigned_worker_id = :worker_id, status = :status, admin_note = :admin_note, rejection_note = NULL WHERE id = :id');
    $assignStmt->execute([
        ':worker_id' => (int) $worker['id'],
        ':status' => 'Assigned',
        ':admin_note' => $adminNote !== '' ? $adminNote : null,
        ':id' => $requestId,
    ]);

    $_SESSION['admin_notice'] = ['type' => 'success', 'text' => 'Request #' . $requestId . ' assigned to ' . $worker['full_name'] . '.'];
    redirectWithFilters($allowedCategories, $allowedStatuses);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['manual_assign_request_id'], $_POST['manual_worker_id'])) {
    $requestId = (int) ($_POST['manual_assign_request_id'] ?? 0);
    $workerId = (int) ($_POST['manual_worker_id'] ?? 0);
    $adminNote = trim($_POST['manual_admin_note'] ?? '');

    $requestStmt = $pdo->prepare('SELECT id, category FROM requests WHERE id = :id LIMIT 1');
    $requestStmt->execute([':id' => $requestId]);
    $request = $requestStmt->fetch();
    if (!$request) {
        $_SESSION['admin_notice'] = ['type' => 'error', 'text' => 'Request not found for manual assignment.'];
        redirectWithFilters($allowedCategories, $allowedStatuses);
    }

    $requestCategory = canonicalCategory((string) $request['category']);
    if (!workerEligibleForCategory($pdo, $workerId, $requestCategory)) {
        $_SESSION['admin_notice'] = ['type' => 'error', 'text' => 'Worker is not valid for this category.'];
        redirectWithFilters($allowedCategories, $allowedStatuses);
    }

    $updateStmt = $pdo->prepare('UPDATE requests SET assigned_worker_id = :worker_id, status = :status, admin_note = :admin_note, rejection_note = NULL WHERE id = :id');
    $updateStmt->execute([
        ':worker_id' => $workerId,
        ':status' => 'Assigned',
        ':admin_note' => $adminNote !== '' ? $adminNote : null,
        ':id' => $requestId,
    ]);

    $_SESSION['admin_notice'] = ['type' => 'success', 'text' => 'Manual assignment saved for request #' . $requestId . '.'];
    redirectWithFilters($allowedCategories, $allowedStatuses);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['backfill_unassigned'])) {
    $fetchUnassignedStmt = $pdo->prepare(
        'SELECT id, category, status FROM requests WHERE assigned_worker_id IS NULL ORDER BY created_at ASC'
    );
    $fetchUnassignedStmt->execute();
    $unassignedRequests = $fetchUnassignedStmt->fetchAll();

    $assignedCount = 0;
    foreach ($unassignedRequests as $item) {
        $requestStatus = (string) ($item['status'] ?? '');
        if (isResolvedStatus($requestStatus)) {
            continue;
        }

        $requestId = (int) ($item['id'] ?? 0);
        if ($requestId <= 0) {
            continue;
        }

        $requestCategory = canonicalCategory((string) ($item['category'] ?? ''));
        $worker = pickWorkerForCategory($pdo, $requestCategory);
        if ($worker === null) {
            continue;
        }

        $updateStmt = $pdo->prepare(
            'UPDATE requests
             SET assigned_worker_id = :worker_id,
                 status = :status,
                 rejection_note = NULL
             WHERE id = :id'
        );
        $updateStmt->execute([
            ':worker_id' => (int) $worker['id'],
            ':status' => 'Assigned',
            ':id' => $requestId,
        ]);

        $assignedCount++;
    }

    $_SESSION['admin_notice'] = [
        'type' => 'success',
        'text' => 'Backfill complete. Assigned ' . $assignedCount . ' unassigned request(s).',
    ];
    header('Location: admin.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_decision_request_id'], $_POST['admin_decision'])) {
    $requestId = (int) ($_POST['admin_decision_request_id'] ?? 0);
    $decision = trim($_POST['admin_decision']);
    $rejectionNote = trim($_POST['rejection_note'] ?? '');

    if ($decision === 'approve') {
        $stmt = $pdo->prepare('UPDATE requests SET status = :status, rejection_note = NULL WHERE id = :id AND status = :waiting');
        $stmt->execute([
            ':status' => 'Resolved',
            ':id' => $requestId,
            ':waiting' => 'Done (Waiting Approval)',
        ]);
        $_SESSION['admin_notice'] = ['type' => 'success', 'text' => 'Request approved.'];
    } else {
        if ($rejectionNote === '') {
            $_SESSION['admin_notice'] = ['type' => 'error', 'text' => 'Rejection note is required.'];
            redirectWithFilters($allowedCategories, $allowedStatuses);
        }
        $stmt = $pdo->prepare('UPDATE requests SET status = :status, rejection_note = :note WHERE id = :id AND status = :waiting');
        $stmt->execute([
            ':status' => 'In Progress',
            ':note' => $rejectionNote,
            ':id' => $requestId,
            ':waiting' => 'Done (Waiting Approval)',
        ]);
        $_SESSION['admin_notice'] = ['type' => 'success', 'text' => 'Request sent back to worker with note.'];
    }

    redirectWithFilters($allowedCategories, $allowedStatuses);
}

$categoryFilter = trim($_GET['category'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');
$unassignedFilter = isset($_GET['unassigned']) && $_GET['unassigned'] === '1' ? '1' : '';

$requiredAiColumns = [
    'ai_summary',
    'ai_urgency',
    'ai_category',
    'ai_recommended_member_profile',
];
$availableRequestColumns = [];
try {
    $columnsStmt = $pdo->query('SHOW COLUMNS FROM requests');
    foreach ($columnsStmt->fetchAll() as $columnRow) {
        if (isset($columnRow['Field'])) {
            $availableRequestColumns[] = (string) $columnRow['Field'];
        }
    }
} catch (Exception $e) {
    $availableRequestColumns = [];
}
$hasAiColumns = count(array_diff($requiredAiColumns, $availableRequestColumns)) === 0;

$aiSelectSql = $hasAiColumns
    ? 'r.ai_summary, r.ai_urgency, r.ai_category, r.ai_recommended_member_profile'
    : 'NULL AS ai_summary, NULL AS ai_urgency, NULL AS ai_category, NULL AS ai_recommended_member_profile';

if (!in_array($categoryFilter, $allowedCategories, true)) {
    $categoryFilter = '';
}
if (!in_array($statusFilter, $allowedStatuses, true)) {
    $statusFilter = '';
}

$statsTotal = (int) $pdo->query('SELECT COUNT(*) FROM requests')->fetchColumn();
$statsNewStmt = $pdo->prepare('SELECT COUNT(*) FROM requests WHERE status = :status');
$statsNewStmt->execute([':status' => 'New']);
$statsNew = (int) $statsNewStmt->fetchColumn();
$statsNewLegacyStmt = $pdo->prepare('SELECT COUNT(*) FROM requests WHERE status = :status');
$statsNewLegacyStmt->execute([':status' => 'Nová']);
$statsNew += (int) $statsNewLegacyStmt->fetchColumn();
$statsActiveStmt = $pdo->prepare('SELECT COUNT(*) FROM requests WHERE status = :assigned OR status = :progress OR status = :progress_legacy');
$statsActiveStmt->execute([':assigned' => 'Assigned', ':progress' => 'In Progress', ':progress_legacy' => 'V riešení']);
$statsActive = (int) $statsActiveStmt->fetchColumn();
$statsDoneStmt = $pdo->prepare('SELECT COUNT(*) FROM requests WHERE status = :status');
$statsDoneStmt->execute([':status' => 'Resolved']);
$statsDone = (int) $statsDoneStmt->fetchColumn();
$statsDoneLegacyStmt = $pdo->prepare('SELECT COUNT(*) FROM requests WHERE status = :status');
$statsDoneLegacyStmt->execute([':status' => 'Vyriešená']);
$statsDone += (int) $statsDoneLegacyStmt->fetchColumn();
// <!-- MODIFIED: add waiting-approval metric query -->
$statsWaitingStmt = $pdo->prepare('SELECT COUNT(*) FROM requests WHERE status = :status');
$statsWaitingStmt->execute([':status' => 'Done (Waiting Approval)']);
$statsWaiting = (int) $statsWaitingStmt->fetchColumn();

$workersByCategoryStmt = $pdo->prepare('SELECT wc.category, w.id, w.full_name FROM workers w INNER JOIN worker_categories wc ON wc.worker_id = w.id WHERE w.active = 1 ORDER BY wc.category, w.full_name');
$workersByCategoryStmt->execute();
$workersByCategoryRows = $workersByCategoryStmt->fetchAll();
$workersByCategory = [];
foreach ($workersByCategoryRows as $row) {
    $categoryKey = canonicalCategory((string) $row['category']);
    $normalizedKey = normalizeCategoryKey($categoryKey);
    if (!isset($workersByCategory[$categoryKey])) {
        $workersByCategory[$categoryKey] = [];
    }
    $workersByCategory[$categoryKey][] = [
        'id' => (int) $row['id'],
        'full_name' => $row['full_name'],
    ];

    if (!isset($workersByCategory[$normalizedKey])) {
        $workersByCategory[$normalizedKey] = [];
    }
    $workersByCategory[$normalizedKey][] = [
        'id' => (int) $row['id'],
        'full_name' => $row['full_name'],
    ];
}

// <!-- MODIFIED: include fields required by compact table and AI panel -->
$sql = 'SELECT r.id, r.full_name, r.email, r.organization, r.role, r.category, r.title, r.city, r.description,
               r.status, r.created_at, r.is_vip, r.admin_note, r.rejection_note, r.assigned_worker_id, r.ai_matches,
               ' . $aiSelectSql . ',
               w.full_name AS worker_name
         FROM requests r
         LEFT JOIN workers w ON w.id = r.assigned_worker_id';
$conditions = [];
$params = [];
if ($categoryFilter !== '') {
    $conditions[] = 'r.category = :category';
    $params[':category'] = $categoryFilter;
}
if ($statusFilter !== '') {
    $conditions[] = 'r.status = :status';
    $params[':status'] = $statusFilter;
}
if ($unassignedFilter === '1') {
    $conditions[] = 'r.assigned_worker_id IS NULL';
}
if (!empty($conditions)) {
    $sql .= ' WHERE ' . implode(' AND ', $conditions);
}
$sql .= ' ORDER BY r.created_at DESC';
$listStmt = $pdo->prepare($sql);
$listStmt->execute($params);
$requests = $listStmt->fetchAll();
$displayedCount = count($requests);

$clientsStmt = $pdo->prepare('SELECT id, full_name, email, is_vip FROM requests ORDER BY created_at DESC');
$clientsStmt->execute();
$clients = $clientsStmt->fetchAll();

$offers = [];
try {
    $offersStmt = $pdo->query('SELECT id, full_name, organization, role, city, category FROM offers WHERE active = 1 ORDER BY created_at DESC');
    $offers = $offersStmt->fetchAll();
} catch (Exception $e) {
    $offers = [];
}

$notice = $_SESSION['admin_notice'] ?? null;
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Dashboard - Request Management</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <div class="container">
    <div class="header-row">
      <div>
        <h1 class="title">Admin Dashboard - Request Management</h1>
        <p class="subtitle">0100 ecosystem operations panel</p>
      </div>
      <div class="nav-links">
        <button type="button" class="nav-link" id="theme-toggle">Switch Theme</button>
        <a class="nav-link" href="index.php">Public Form</a>
        <a class="nav-link" href="worker.php">Worker Page</a>
        <form method="POST" action="admin.php" style="margin: 0;">
          <button type="submit" name="backfill_unassigned" value="1" class="nav-link" style="background: transparent; cursor: pointer;">Backfill Unassigned</button>
        </form>
        <a class="nav-link" href="admin.php?logout=1">Logout</a>
      </div>
    </div>

    <?php if ($notice): ?>
      <div class="banner banner-note <?php echo $notice['type'] === 'error' ? 'error' : 'success'; ?>">
        <span><?php echo h($notice['text']); ?></span>
        <form method="POST" action="admin.php">
          <button type="submit" name="dismiss_notice" value="1" class="ok-btn">OK</button>
        </form>
      </div>
      <?php unset($_SESSION['admin_notice']); ?>
    <?php endif; ?>

    <div class="panel" style="margin-top: 22px; margin-bottom: 16px;">
      <div class="meta-label muted">Filters</div>
      <form method="GET" action="admin.php" class="filter-bar section" id="filterForm">
        <div class="filter-item">
          <label for="filter_category">Category</label>
          <select id="filter_category" name="category">
            <option value="">All categories</option>
            <?php foreach ($allowedCategories as $category): ?>
              <option value="<?php echo h($category); ?>" <?php echo $categoryFilter === $category ? 'selected' : ''; ?>><?php echo h($category); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="filter-item">
          <label for="filter_status">Status</label>
          <select id="filter_status" name="status">
            <option value="">All statuses</option>
            <?php foreach ($allowedStatuses as $status): ?>
              <option value="<?php echo h($status); ?>" <?php echo $statusFilter === $status ? 'selected' : ''; ?>><?php echo h($status); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="filter-item">
          <label for="filter_unassigned">Assignment</label>
          <select id="filter_unassigned" name="unassigned">
            <option value="">All requests</option>
            <option value="1" <?php echo $unassignedFilter === '1' ? 'selected' : ''; ?>>Unassigned only</option>
          </select>
        </div>
        <button type="submit" class="btn">Apply Filters</button>
        <a href="admin.php" class="nav-link">Clear Filters</a>
      </form>
    </div>

    <!-- MODIFIED: add waiting-approval stat card -->
    <div class="stats">
      <div class="stat-card"><div class="meta-label muted">Total Requests</div><div class="stat-value"><?php echo $statsTotal; ?></div></div>
      <div class="stat-card"><div class="meta-label muted">New</div><div class="stat-value"><?php echo $statsNew; ?></div></div>
      <div class="stat-card"><div class="meta-label muted">Assigned / In Progress</div><div class="stat-value"><?php echo $statsActive; ?></div></div>
      <div class="stat-card"><div class="meta-label muted">Resolved</div><div class="stat-value"><?php echo $statsDone; ?></div></div>
      <div class="stat-card"><div class="meta-label muted">Waiting Approval</div><div class="stat-value"><?php echo $statsWaiting; ?></div></div>
    </div>

    <!-- MODIFIED: move view summary above workers/clients -->
    <div class="panel" style="margin-bottom: 16px;">
      <div class="meta-label muted">View Summary</div>
      <p class="section">Showing <?php echo $displayedCount; ?> request(s)</p>
    </div>

    <!-- MODIFIED: move table card above workers/clients -->
    <div class="table-card">
      <div class="table-scroll">
        <table>
          <thead>
            <tr>
              <th>ID</th>
              <th>Name + Org</th>
              <th>Category</th>
              <th>Status</th>
              <th>Worker</th>
              <th>Matches</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($requests)): ?>
               <tr><td colspan="7" class="muted">No requests found for current filter view.</td></tr>
            <?php else: ?>
              <?php foreach ($requests as $request): ?>
                <?php $rowCategory = canonicalCategory((string) $request['category']); ?>
                <?php $rowCategoryKey = normalizeCategoryKey($rowCategory); ?>
                <?php
                  $aiUrgencyRaw = $request['ai_urgency'];
                  $aiUrgency = null;
                  if ($aiUrgencyRaw !== null && $aiUrgencyRaw !== '' && is_numeric((string) $aiUrgencyRaw)) {
                      $aiUrgency = (int) $aiUrgencyRaw;
                  }

                  $aiCategoryValue = trim((string) ($request['ai_category'] ?? ''));
                  $aiSummaryValue = trim((string) ($request['ai_summary'] ?? ''));
                  $aiRecommendedProfileValue = trim((string) ($request['ai_recommended_member_profile'] ?? ''));
                  $aiUrgencyValue = $aiUrgency !== null ? (string) $aiUrgency : '';
                  $isAiEmpty = $aiCategoryValue === '' && $aiSummaryValue === '' && $aiRecommendedProfileValue === '' && $aiUrgencyValue === '';
                  $detailsId = 'request-details-' . (int) $request['id'];
                ?>
                <tr class="request-main-row">
                  <td><?php echo (int) $request['id']; ?></td>
                  <td>
                    <div class="name-org-wrap">
                      <div class="<?php echo (int) $request['is_vip'] === 1 ? 'vip-name' : ''; ?>"><strong><?php echo h($request['full_name']); ?></strong></div>
                      <div class="muted"><?php echo h((string) ($request['organization'] ?? '—')); ?></div>
                    </div>
                  </td>
                  <td><?php echo h($rowCategory); ?></td>
                  <td><span class="badge <?php echo statusClass($request['status']); ?>"><?php echo h($request['status']); ?></span></td>
                  <td>
                    <?php if (!empty($request['worker_name'])): ?>
                      <?php echo h($request['worker_name']); ?>
                    <?php else: ?>
                      <span class="muted">Unassigned</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <a href="matches.php?id=<?php echo (int) $request['id']; ?>" class="btn btn-subtle">View Matches</a>
                  </td>
                  <td>
                    <!-- MODIFIED: keep collapsed actions to icon buttons only -->
                    <div class="actions-icon-row">
                      <button type="button" class="icon-btn js-toggle-details" data-target="<?php echo h($detailsId); ?>" aria-expanded="false" aria-controls="<?php echo h($detailsId); ?>" title="Expand details">
                        <span class="chevron">▼</span>
                      </button>
                      <?php if ($request['status'] === 'Done (Waiting Approval)'): ?>
                      <form method="POST" action="admin.php" class="inline-icon-form">
                        <input type="hidden" name="admin_decision_request_id" value="<?php echo (int) $request['id']; ?>">
                        <input type="hidden" name="admin_decision" value="approve">
                        <input type="hidden" name="current_category" value="<?php echo h($categoryFilter); ?>">
                        <input type="hidden" name="current_status" value="<?php echo h($statusFilter); ?>">
                        <input type="hidden" name="current_unassigned" value="<?php echo h($unassignedFilter); ?>">
                        <button type="submit" class="icon-btn icon-approve" title="Quick approve">✓</button>
                      </form>
                      <?php endif; ?>
                      <form method="POST" action="admin.php" class="inline-icon-form">
                        <input type="hidden" name="toggle_vip_request_id" value="<?php echo (int) $request['id']; ?>">
                        <input type="hidden" name="toggle_vip_value" value="<?php echo (int) $request['is_vip'] === 1 ? '0' : '1'; ?>">
                        <button type="submit" class="icon-btn <?php echo (int) $request['is_vip'] === 1 ? 'icon-vip-on' : 'icon-vip-off'; ?>" title="Toggle VIP">★</button>
                      </form>
                      <?php if (isResolvedStatus((string) $request['status'])): ?>
                      <form method="POST" action="admin.php" class="inline-icon-form" onsubmit="return confirm('Delete this done task permanently?');">
                        <input type="hidden" name="delete_done_request_id" value="<?php echo (int) $request['id']; ?>">
                        <input type="hidden" name="current_category" value="<?php echo h($categoryFilter); ?>">
                        <input type="hidden" name="current_status" value="<?php echo h($statusFilter); ?>">
                        <input type="hidden" name="current_unassigned" value="<?php echo h($unassignedFilter); ?>">
                        <button type="submit" class="icon-btn" title="Delete done task">🗑</button>
                      </form>
                      <?php endif; ?>
                    </div>
                  </td>
                </tr>
                <tr id="<?php echo h($detailsId); ?>" class="request-detail-row" hidden>
                  <td colspan="7">
                    <!-- MODIFIED: expanded detail row with notes, AI panel, forms and created-at -->
                    <div class="request-detail-panel">
                      <div class="detail-meta-row">
                        <div><strong>Role:</strong> <?php echo h($request['role']); ?></div>
                        <div><strong>Email:</strong> <?php echo h($request['email']); ?></div>
                        <div><strong>Created at:</strong> <?php echo h($request['created_at']); ?></div>
                      </div>

                      <div class="detail-section">
                        <div class="detail-label">Full Description</div>
                        <div class="detail-value"><?php echo nl2br(h($request['description'])); ?></div>
                      </div>

                      <?php if (!empty($request['admin_note']) || !empty($request['rejection_note'])): ?>
                      <div class="detail-notes-grid">
                        <?php if (!empty($request['admin_note'])): ?><div class="note-box"><strong>Admin note:</strong> <?php echo h($request['admin_note']); ?></div><?php endif; ?>
                        <?php if (!empty($request['rejection_note'])): ?><div class="note-box"><strong>Rejection note:</strong> <?php echo h($request['rejection_note']); ?></div><?php endif; ?>
                      </div>
                      <?php endif; ?>

                      <div class="ai-panel">
                        <div class="ai-heading">✦ AI Analysis</div>
                        <?php if ($isAiEmpty): ?>
                          <div class="muted">AI analysis pending or not available</div>
                        <?php else: ?>
                          <div class="ai-grid">
                            <div class="ai-item">
                              <div class="ai-label">AI Category</div>
                              <div class="ai-value"><?php echo $aiCategoryValue !== '' ? h($aiCategoryValue) : '—'; ?></div>
                            </div>
                            <div class="ai-item">
                              <div class="ai-label">AI Priority</div>
                              <div class="ai-value"><?php echo $aiUrgencyValue !== '' ? h($aiUrgencyValue) : '—'; ?></div>
                            </div>
                            <div class="ai-item">
                              <div class="ai-label">AI Summary</div>
                              <div class="ai-value"><?php echo $aiSummaryValue !== '' ? h($aiSummaryValue) : '—'; ?></div>
                            </div>
                            <div class="ai-item">
                              <div class="ai-label">AI Recommended Profile</div>
                              <div class="ai-value"><?php echo $aiRecommendedProfileValue !== '' ? h($aiRecommendedProfileValue) : '—'; ?></div>
                            </div>
                          </div>
                        <?php endif; ?>
                      </div>

                      <?php if (empty($request['assigned_worker_id']) && !isResolvedStatus((string) $request['status'])): ?>
                        <div class="detail-actions-grid">
                          <form method="POST" action="admin.php" class="inline-form">
                            <input type="hidden" name="assign_request_id" value="<?php echo (int) $request['id']; ?>">
                            <input type="hidden" name="current_category" value="<?php echo h($categoryFilter); ?>">
                            <input type="hidden" name="current_status" value="<?php echo h($statusFilter); ?>">
                            <input type="hidden" name="current_unassigned" value="<?php echo h($unassignedFilter); ?>">
                            <textarea name="admin_note" rows="2" placeholder="Optional note to worker"></textarea>
                            <button type="submit" class="btn">Auto Assign</button>
                          </form>
                          <form method="POST" action="admin.php" class="inline-form">
                            <input type="hidden" name="manual_assign_request_id" value="<?php echo (int) $request['id']; ?>">
                            <input type="hidden" name="current_category" value="<?php echo h($categoryFilter); ?>">
                            <input type="hidden" name="current_status" value="<?php echo h($statusFilter); ?>">
                            <input type="hidden" name="current_unassigned" value="<?php echo h($unassignedFilter); ?>">
                            <select name="manual_worker_id" required>
                              <option value="">Select worker (<?php echo h($rowCategory); ?>)</option>
                              <?php foreach (($workersByCategory[$rowCategory] ?? $workersByCategory[$rowCategoryKey] ?? []) as $workerOption): ?>
                                <option value="<?php echo (int) $workerOption['id']; ?>"><?php echo h($workerOption['full_name']); ?></option>
                              <?php endforeach; ?>
                            </select>
                            <textarea name="manual_admin_note" rows="2" placeholder="Optional note to worker"></textarea>
                            <button type="submit" class="btn btn-subtle">Assign Selected Worker</button>
                          </form>
                        </div>
                      <?php elseif ($request['status'] === 'Done (Waiting Approval)'): ?>
                        <form method="POST" action="admin.php" class="inline-form detail-approve-form">
                          <input type="hidden" name="admin_decision_request_id" value="<?php echo (int) $request['id']; ?>">
                          <input type="hidden" name="current_category" value="<?php echo h($categoryFilter); ?>">
                          <input type="hidden" name="current_status" value="<?php echo h($statusFilter); ?>">
                          <input type="hidden" name="current_unassigned" value="<?php echo h($unassignedFilter); ?>">
                          <div class="inline-row">
                            <button type="submit" name="admin_decision" value="approve" class="btn">Approve</button>
                            <button type="submit" name="admin_decision" value="reject" class="btn btn-danger">Reject</button>
                          </div>
                          <textarea name="rejection_note" rows="2" placeholder="Required when rejecting"></textarea>
                        </form>
                      <?php else: ?>
                        <span class="muted">No additional action available.</span>
                      <?php endif; ?>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="split section">
      <div class="panel">
        <div class="meta-label muted">Workers</div>
        <div class="form-grid section">
          <?php foreach ($allowedCategories as $category): ?>
            <div class="form-group">
              <label>Workers: <?php echo h($category); ?></label>
              <select>
                <option value="">Select worker</option>
                <?php foreach (($workersByCategory[$category] ?? []) as $worker): ?>
                  <option value="<?php echo (int) $worker['id']; ?>"><?php echo h($worker['full_name']); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          <?php endforeach; ?>
        </div>

        <hr class="section-divider">

        <div class="meta-label muted">Add Worker</div>
        <form method="POST" action="admin.php" class="form-grid section">
          <div class="form-group"><label for="worker_name">Full Name</label><input id="worker_name" name="worker_name" type="text" required></div>
          <div class="form-group"><label for="worker_username">Username</label><input id="worker_username" name="worker_username" type="text" required></div>
          <div class="form-group"><label for="worker_password">Password</label><input id="worker_password" name="worker_password" type="text" required></div>
          <div class="form-group">
            <label for="worker_category">Primary Category</label>
            <select id="worker_category" name="worker_category" required>
              <option value="">Select category</option>
              <?php foreach ($allowedCategories as $category): ?>
                <option value="<?php echo h($category); ?>"><?php echo h($category); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <button type="submit" class="btn">Add Worker</button>
        </form>
      </div>

      <div class="panel">
        <div class="meta-label muted">Clients</div>
        <div class="list">
          <?php foreach ($clients as $client): ?>
            <div class="list-item">
              <div class="<?php echo (int) $client['is_vip'] === 1 ? 'vip-name' : ''; ?>"><strong><?php echo h($client['full_name']); ?></strong></div>
              <div class="muted"><?php echo h($client['email']); ?></div>
              <form method="POST" action="admin.php" class="inline-row section">
                <input type="hidden" name="toggle_vip_request_id" value="<?php echo (int) $client['id']; ?>">
                <input type="hidden" name="toggle_vip_value" value="0">
                <label class="inline-row" style="gap: 6px;">
                  <input
                    type="checkbox"
                    name="toggle_vip_value"
                    value="1"
                    <?php echo (int) $client['is_vip'] === 1 ? 'checked' : ''; ?>
                    onchange="this.form.submit()"
                  >
                  <span class="tiny">VIP</span>
                </label>
              </form>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="panel">
        <div class="meta-label muted">Community Offers</div>
        <?php if (empty($offers)): ?>
          <p class="muted section">No active offers found.</p>
        <?php else: ?>
          <div class="list">
            <?php foreach ($offers as $offer): ?>
              <div class="list-item">
                <div><strong><?php echo h((string) ($offer['full_name'] ?? '')); ?></strong></div>
                <div class="muted"><?php echo h((string) ($offer['role'] ?? '')); ?></div>
                <div class="muted"><?php echo h((string) ($offer['organization'] ?? '')); ?></div>
                <div class="muted">City: <?php echo h((string) ($offer['city'] ?? '')); ?></div>
                <div class="muted">Category: <?php echo h((string) ($offer['category'] ?? '')); ?></div>
                <form method="POST" action="admin.php" class="inline-row section" onsubmit="return confirm('Delete this community offer?');">
                  <input type="hidden" name="delete_offer_id" value="<?php echo (int) $offer['id']; ?>">
                  <button type="submit" class="btn btn-danger">Delete Offer</button>
                </form>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>

  </div>

  <!-- MODIFIED: add expandable sub-row toggle behavior -->
  <script>
    (function () {
      var filterForm = document.getElementById('filterForm');
      var categorySelect = document.getElementById('filter_category');
      var statusSelect = document.getElementById('filter_status');
      var unassignedSelect = document.getElementById('filter_unassigned');
      var themeToggle = document.getElementById('theme-toggle');
      var detailToggles = document.querySelectorAll('.js-toggle-details');

      if (filterForm && categorySelect && statusSelect && unassignedSelect) {
        categorySelect.addEventListener('change', function () { filterForm.submit(); });
        statusSelect.addEventListener('change', function () { filterForm.submit(); });
        unassignedSelect.addEventListener('change', function () { filterForm.submit(); });
      }

      var storedTheme = localStorage.getItem('halmake_theme');
      if (storedTheme === 'light') {
        document.documentElement.setAttribute('data-theme', 'light');
      } else {
        document.documentElement.setAttribute('data-theme', 'dark');
      }

      if (themeToggle) {
        themeToggle.addEventListener('click', function () {
          var current = document.documentElement.getAttribute('data-theme') || 'dark';
          var next = current === 'dark' ? 'light' : 'dark';
          document.documentElement.setAttribute('data-theme', next);
          localStorage.setItem('halmake_theme', next);
        });
      }

      detailToggles.forEach(function (toggle) {
        toggle.addEventListener('click', function () {
          var targetId = toggle.getAttribute('data-target');
          if (!targetId) {
            return;
          }

          var detailRow = document.getElementById(targetId);
          if (!detailRow) {
            return;
          }

          var isExpanded = toggle.getAttribute('aria-expanded') === 'true';
          if (isExpanded) {
            toggle.setAttribute('aria-expanded', 'false');
            detailRow.hidden = true;
            detailRow.classList.remove('is-open');
          } else {
            toggle.setAttribute('aria-expanded', 'true');
            detailRow.hidden = false;
            detailRow.classList.add('is-open');
          }
        });
      });
    })();
  </script>
</body>
</html>
