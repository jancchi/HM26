<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

try {
    $totalRequests = (int) $pdo->query('SELECT COUNT(*) FROM requests')->fetchColumn();

    $resolvedStmt = $pdo->prepare('SELECT COUNT(*) FROM requests WHERE status = :status');
    $resolvedStmt->execute([':status' => 'Vyriešená']);
    $resolvedRequests = (int) $resolvedStmt->fetchColumn();

    $openStmt = $pdo->prepare('SELECT COUNT(*) FROM requests WHERE status = :new_status OR status = :in_progress_status');
    $openStmt->execute([
        ':new_status' => 'Nová',
        ':in_progress_status' => 'V riešení',
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
