<?php
session_start();
require_once 'db.php';

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function statusClass(string $status): string
{
    if ($status === 'New' || $status === 'Nová') {
        return 'new';
    }
    if ($status === 'Assigned') {
        return 'assigned';
    }
    if ($status === 'In Progress' || $status === 'V riešení') {
        return 'progress';
    }
    if ($status === 'Done (Waiting Approval)') {
        return 'waiting';
    }
    if ($status === 'Resolved' || $status === 'Vyriešená') {
        return 'resolved';
    }
    return 'new';
}

if (isset($_GET['logout']) && $_GET['logout'] === '1') {
    unset($_SESSION['worker_id'], $_SESSION['worker_name']);
    header('Location: worker.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dismiss_notice'])) {
    unset($_SESSION['worker_notice']);
    header('Location: worker.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['worker_login']) && !isset($_SESSION['worker_id'])) {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    $loginStmt = $pdo->prepare('SELECT id, full_name, password_plain FROM workers WHERE username = :username AND active = 1 LIMIT 1');
    $loginStmt->execute([':username' => $username]);
    $worker = $loginStmt->fetch();

    if ($worker && hash_equals($worker['password_plain'], $password)) {
        $_SESSION['worker_id'] = (int) $worker['id'];
        $_SESSION['worker_name'] = $worker['full_name'];
        $_SESSION['worker_notice'] = ['type' => 'success', 'text' => 'Login successful.'];
        header('Location: worker.php');
        exit;
    }

    $loginError = 'Invalid worker credentials.';
}

if (!isset($_SESSION['worker_id'])) {
    ?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Worker Login</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <div class="center-wrap">
    <div class="card" style="max-width: 520px;">
      <h1 class="title">Worker Login</h1>
      <p class="subtitle">Assigned request execution panel</p>

      <?php if (!empty($loginError)): ?>
        <div class="banner error"><?php echo h($loginError); ?></div>
      <?php endif; ?>

      <form method="POST" action="worker.php" class="form-grid section">
        <input type="hidden" name="worker_login" value="1">
        <div class="form-group">
          <label for="username">Username</label>
          <input id="username" name="username" type="text" required>
        </div>
        <div class="form-group">
          <label for="password">Password</label>
          <input id="password" name="password" type="password" required>
        </div>
        <button type="submit" class="btn block">Sign In</button>
      </form>

      <div class="section">
        <a class="nav-link" href="index.php">Back to Public Form</a>
      </div>
    </div>
  </div>
</body>
</html>
    <?php
    exit;
}

$workerId = (int) $_SESSION['worker_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['task_request_id'], $_POST['task_action'])) {
    $requestId = (int) ($_POST['task_request_id'] ?? 0);
    $action = trim($_POST['task_action']);

    $taskStmt = $pdo->prepare('SELECT id, status, admin_note FROM requests WHERE id = :id AND assigned_worker_id = :worker_id LIMIT 1');
    $taskStmt->execute([
        ':id' => $requestId,
        ':worker_id' => $workerId,
    ]);
    $task = $taskStmt->fetch();

    if (!$task) {
        $_SESSION['worker_notice'] = ['type' => 'error', 'text' => 'Task not found.'];
        header('Location: worker.php');
        exit;
    }

    $currentStatus = $task['status'];
    $newStatus = null;

    if ($action === 'start' && $currentStatus === 'Assigned') {
        $newStatus = 'In Progress';
    }

    if ($action === 'submit' && ($currentStatus === 'In Progress' || $currentStatus === 'V riešení')) {
        $newStatus = 'Done (Waiting Approval)';
    }

    if ($newStatus === null) {
        $_SESSION['worker_notice'] = ['type' => 'error', 'text' => 'Invalid action for current status.'];
        header('Location: worker.php');
        exit;
    }

    $workerMessage = trim((string) ($_POST['worker_message'] ?? ''));
    $mergedAdminNote = (string) ($task['admin_note'] ?? '');
    if ($action === 'submit' && $workerMessage !== '') {
        $stamp = date('Y-m-d H:i');
        $entry = 'Worker update (' . $stamp . '): ' . $workerMessage;
        $mergedAdminNote = trim($mergedAdminNote) !== ''
            ? trim($mergedAdminNote) . "\n\n" . $entry
            : $entry;
    }

    $updateStmt = $pdo->prepare('UPDATE requests SET status = :status, admin_note = :admin_note WHERE id = :id AND assigned_worker_id = :worker_id');
    $updateStmt->execute([
        ':status' => $newStatus,
        ':admin_note' => trim($mergedAdminNote) !== '' ? $mergedAdminNote : null,
        ':id' => $requestId,
        ':worker_id' => $workerId,
    ]);

    $_SESSION['worker_notice'] = ['type' => 'success', 'text' => 'Task #' . $requestId . ' updated to ' . $newStatus . '.'];
    header('Location: worker.php');
    exit;
}

$tasksStmt = $pdo->prepare(
    'SELECT id, full_name, email, role, category, title, city, description, status, ai_urgency, admin_note, rejection_note, is_vip, created_at
     FROM requests
     WHERE assigned_worker_id = :worker_id
     ORDER BY updated_at DESC, created_at DESC'
);
$tasksStmt->execute([':worker_id' => $workerId]);
$tasks = $tasksStmt->fetchAll();

$notice = $_SESSION['worker_notice'] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Worker Dashboard</title>
  <link rel="stylesheet" href="style.css">
</head>
<body class="worker-dashboard">
  <div class="container">
    <div class="header-row">
      <div>
        <h1 class="title">Worker Dashboard</h1>
        <p class="subtitle">Welcome, <?php echo h($_SESSION['worker_name']); ?></p>
      </div>
      <div class="nav-links">
        <button type="button" class="nav-link" id="theme-toggle">Switch Theme</button>
        <a class="nav-link" href="index.php">Public Form</a>
        <a class="nav-link" href="admin.php">Admin</a>
        <a class="nav-link" href="worker.php?logout=1">Logout</a>
      </div>
    </div>

    <?php if ($notice): ?>
      <div class="banner banner-note <?php echo $notice['type'] === 'error' ? 'error' : 'success'; ?>">
        <span><?php echo h($notice['text']); ?></span>
        <form method="POST" action="worker.php">
          <button type="submit" name="dismiss_notice" value="1" class="ok-btn">OK</button>
        </form>
      </div>
      <?php unset($_SESSION['worker_notice']); ?>
    <?php endif; ?>

    <div class="table-card section">
      <div class="table-scroll">
        <table>
          <thead>
            <tr>
              <th>ID</th>
              <th>Requester</th>
              <th>Email</th>
              <th>Role</th>
              <th>Category</th>
              <th>Title</th>
              <th>City</th>
              <th>AI Priority</th>
              <th>Status</th>
              <th>Admin Notes</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($tasks)): ?>
              <tr>
                <td colspan="11" class="muted">No assigned tasks yet.</td>
              </tr>
            <?php else: ?>
              <?php foreach ($tasks as $task): ?>
                <tr>
                  <td><?php echo (int) $task['id']; ?></td>
                  <td class="<?php echo (int) $task['is_vip'] === 1 ? 'vip-name' : ''; ?>"><?php echo h($task['full_name']); ?></td>
                  <td><?php echo h($task['email']); ?></td>
                  <td><?php echo h($task['role']); ?></td>
                  <td><?php echo h($task['category']); ?></td>
                  <td><?php echo h($task['title']); ?></td>
                  <td><?php echo h($task['city']); ?></td>
                  <td>
                    <?php
                      $aiUrgencyRaw = $task['ai_urgency'] ?? null;
                      $aiUrgency = null;
                      if ($aiUrgencyRaw !== null && $aiUrgencyRaw !== '' && is_numeric((string) $aiUrgencyRaw)) {
                          $aiUrgency = (int) $aiUrgencyRaw;
                      }
                    ?>
                    <?php if ($aiUrgency !== null): ?>
                      <?php
                        $priorityClass = 'priority-none';
                        $priorityLabel = 'LOW';
                        if ($aiUrgency >= 8) {
                            $priorityClass = 'priority-high';
                            $priorityLabel = 'HIGH';
                        } elseif ($aiUrgency >= 5) {
                            $priorityClass = 'priority-med';
                            $priorityLabel = 'MED';
                        } elseif ($aiUrgency >= 1) {
                            $priorityClass = 'priority-low';
                            $priorityLabel = 'LOW';
                        }
                      ?>
                      <span class="priority-badge <?php echo h($priorityClass); ?>"><?php echo h($priorityLabel); ?></span>
                      <div class="muted tiny">Score: <?php echo (int) $aiUrgency; ?>/10</div>
                    <?php else: ?>
                      <span class="muted">AI pending</span>
                    <?php endif; ?>
                  </td>
                  <td><span class="badge <?php echo statusClass($task['status']); ?>"><?php echo h($task['status']); ?></span></td>
                  <td>
                    <?php if (!empty($task['admin_note'])): ?>
                      <div class="note-box"><strong>Admin:</strong> <?php echo h($task['admin_note']); ?></div>
                    <?php endif; ?>
                    <?php if (!empty($task['rejection_note'])): ?>
                      <div class="note-box"><strong>Rejected with note:</strong> <?php echo h($task['rejection_note']); ?></div>
                    <?php endif; ?>
                    <?php if (empty($task['admin_note']) && empty($task['rejection_note'])): ?>
                      <span class="muted">No notes</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <form method="POST" action="worker.php" class="inline-form">
                      <input type="hidden" name="task_request_id" value="<?php echo (int) $task['id']; ?>">

                      <?php if ($task['status'] === 'Assigned'): ?>
                        <button type="submit" name="task_action" value="start" class="btn">Start Work</button>
                      <?php elseif ($task['status'] === 'In Progress' || $task['status'] === 'V riešení'): ?>
                        <textarea name="worker_message" rows="2" placeholder="Optional note for admin (what was done, blockers, next step)"></textarea>
                        <button type="submit" name="task_action" value="submit" class="btn">Mark Done</button>
                      <?php else: ?>
                        <span class="muted">No action</span>
                      <?php endif; ?>
                    </form>
                  </td>
                </tr>
                <tr>
                  <td colspan="11">
                    <div class="worker-description-block">
                      <strong>Description</strong>
                      <p class="worker-description-full"><?php echo nl2br(h($task['description'])); ?></p>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <script>
    (function () {
      var themeToggle = document.getElementById('theme-toggle');
      var storedTheme = localStorage.getItem('halmake_theme');

      if (storedTheme === 'light') {
        document.documentElement.setAttribute('data-theme', 'light');
      } else {
        document.documentElement.setAttribute('data-theme', 'dark');
      }

      if (!themeToggle) {
        return;
      }

      themeToggle.addEventListener('click', function () {
        var current = document.documentElement.getAttribute('data-theme') || 'dark';
        var next = current === 'dark' ? 'light' : 'dark';
        document.documentElement.setAttribute('data-theme', next);
        localStorage.setItem('halmake_theme', next);
      });
    })();
  </script>
</body>
</html>
