<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

try {
    $totalRequests = (int) $pdo->query('SELECT COUNT(*) FROM requests')->fetchColumn();

    $resolvedStmt = $pdo->prepare('SELECT COUNT(*) FROM requests WHERE status = :status OR status = :legacy_status');
    $resolvedStmt->execute([
        ':status' => 'Resolved',
        ':legacy_status' => 'Vyriešená',
    ]);
    $resolvedRequests = (int) $resolvedStmt->fetchColumn();

    $openStmt = $pdo->prepare('SELECT COUNT(*) FROM requests WHERE status = :new_status OR status = :new_legacy_status OR status = :in_progress_status OR status = :in_progress_legacy_status OR status = :assigned_status');
    $openStmt->execute([
        ':new_status' => 'New',
        ':new_legacy_status' => 'Nová',
        ':in_progress_status' => 'In Progress',
        ':in_progress_legacy_status' => 'V riešení',
        ':assigned_status' => 'Assigned',
    ]);
    $activeRequests = (int) $openStmt->fetchColumn();

    $helpers = max(1, (int) ceil($totalRequests * 0.6));
    $successRate = $totalRequests > 0
        ? (int) round(($resolvedRequests / $totalRequests) * 100)
        : 0;

    respond([
        'activeRequests' => $activeRequests,
        'completedRequests' => $resolvedRequests,
        'activeHelpers' => $helpers,
        'successRate' => $successRate,
    ]);
} catch (Throwable $e) {
    respond([
        'message' => 'Failed to load stats',
    ], 500);
}
