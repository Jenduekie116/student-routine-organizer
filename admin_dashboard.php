<?php
require 'auth_guard.php';   // confirms the user is logged in
require 'database.php';

// ---------------------------------------------------------
// Admin-only access control
// auth_guard.php only checks that *someone* is logged in - it does not check
// role. Without this check, any student who knows/guesses this URL could
// view every user's data. This is the one place in the app that gates by role.
// ---------------------------------------------------------
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

// ---------------------------------------------------------
// System-wide summary counts
// No user input is used in these queries, so plain query() is safe here -
// nothing is concatenated from $_GET/$_POST.
// ---------------------------------------------------------
$total_users        = $conn->query("SELECT COUNT(*) AS c FROM users")->fetch_assoc()['c'];
$total_students     = $conn->query("SELECT COUNT(*) AS c FROM users WHERE role = 'student'")->fetch_assoc()['c'];
$total_admins       = $conn->query("SELECT COUNT(*) AS c FROM users WHERE role = 'admin'")->fetch_assoc()['c'];
$total_exercise     = $conn->query("SELECT COUNT(*) AS c FROM exercise_records")->fetch_assoc()['c'];
$total_journal      = $conn->query("SELECT COUNT(*) AS c FROM journal_entries")->fetch_assoc()['c'];
$total_transactions = $conn->query("SELECT COUNT(*) AS c FROM transactions WHERE is_deleted = 0")->fetch_assoc()['c'];
$total_habits       = $conn->query("SELECT COUNT(*) AS c FROM habit_records")->fetch_assoc()['c'];

// ---------------------------------------------------------
// All registered users, with per-module activity counts
// ---------------------------------------------------------
$users_result = $conn->query(
    "SELECT u.user_id, u.name, u.email, u.role, u.created_at,
        (SELECT COUNT(*) FROM exercise_records er WHERE er.user_id = u.user_id) AS exercise_count,
        (SELECT COUNT(*) FROM journal_entries je WHERE je.user_id = u.user_id) AS journal_count,
        (SELECT COUNT(*) FROM transactions t WHERE t.user_id = u.user_id AND t.is_deleted = 0) AS transaction_count,
        (SELECT COUNT(*) FROM habit_records hr WHERE hr.user_id = u.user_id) AS habit_count
     FROM users u
     ORDER BY u.created_at DESC"
);
$users = $users_result->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Admin Dashboard - Student Routine Organizer</title>
<link rel="stylesheet" href="style.css">
<style>
.admin-stats-grid {
  display: grid;
  grid-template-columns: repeat(5, 1fr);
  gap: 14px;
  margin-bottom: 20px;
}
@media (max-width: 1000px) { .admin-stats-grid { grid-template-columns: repeat(3, 1fr); } }
@media (max-width: 600px)  { .admin-stats-grid { grid-template-columns: 1fr 1fr; } }

.role-badge { display: inline-block; padding: 2px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 500; }
.role-badge.admin   { background: #fce7f3; color: #831843; }
.role-badge.student { background: #dbeafe; color: #1e40af; }
</style>
</head>
<body>
<div class="app-layout">

  <!-- Minimal admin-only sidebar - deliberately does not link to the 4
       student modules, since this account's role is administrative, not
       a student tracking their own routines. -->
  <aside class="sidebar">
    <div class="sidebar-logo"><span class="sidebar-logo-text">🛡️ Admin Panel</span></div>
    <nav class="sidebar-nav">
      <a href="admin_dashboard.php" class="sidebar-link active">🛡️ Admin Dashboard</a>
    </nav>
    <div class="sidebar-footer">
      <div class="sidebar-user"><?= htmlspecialchars($_SESSION['name']) ?> (Admin)</div>
      <a href="logout.php" class="sidebar-link">🚪 Logout</a>
    </div>
  </aside>

  <div class="main-content">
  <div class="container">

    <div class="page-header">
      <h1>🛡️ Admin Dashboard</h1>
    </div>

    <!-- ===================== SYSTEM-WIDE SUMMARY ===================== -->
    <div class="admin-stats-grid">
      <div class="card stat-card">
        <div class="stat-label">Total users</div>
        <div class="stat-value"><?= (int)$total_users ?></div>
      </div>
      <div class="card stat-card">
        <div class="stat-label">Exercise records</div>
        <div class="stat-value"><?= (int)$total_exercise ?></div>
      </div>
      <div class="card stat-card">
        <div class="stat-label">Journal entries</div>
        <div class="stat-value"><?= (int)$total_journal ?></div>
      </div>
      <div class="card stat-card">
        <div class="stat-label">Transactions</div>
        <div class="stat-value"><?= (int)$total_transactions ?></div>
      </div>
      <div class="card stat-card">
        <div class="stat-label">Habits tracked</div>
        <div class="stat-value"><?= (int)$total_habits ?></div>
      </div>
    </div>

    <div class="card" style="margin-bottom: 20px;">
      <strong>Role breakdown</strong>
      <div style="margin-top:10px; display:flex; gap:20px;">
        <div><span class="role-badge student">Students</span> <?= (int)$total_students ?></div>
        <div><span class="role-badge admin">Admins</span> <?= (int)$total_admins ?></div>
      </div>
    </div>

    <!-- ===================== ALL REGISTERED USERS ===================== -->
    <div class="card">
      <strong>All registered users</strong>

      <?php if (empty($users)): ?>
        <p class="text-muted" style="margin-top:14px;">No users registered yet.</p>
      <?php else: ?>
        <table class="data-table" style="margin-top:14px;">
          <thead>
            <tr>
              <th>Name</th>
              <th>Email</th>
              <th>Role</th>
              <th>Joined</th>
              <th>Exercise</th>
              <th>Journal</th>
              <th>Transactions</th>
              <th>Habits</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($users as $u): ?>
              <tr>
                <td><?= htmlspecialchars($u['name']) ?></td>
                <td class="text-muted"><?= htmlspecialchars($u['email']) ?></td>
                <td>
                  <span class="role-badge <?= $u['role'] === 'admin' ? 'admin' : 'student' ?>">
                    <?= htmlspecialchars(ucfirst($u['role'])) ?>
                  </span>
                </td>
                <td class="text-muted"><?= date('M j, Y', strtotime($u['created_at'])) ?></td>
                <td><?= (int)$u['exercise_count'] ?></td>
                <td><?= (int)$u['journal_count'] ?></td>
                <td><?= (int)$u['transaction_count'] ?></td>
                <td><?= (int)$u['habit_count'] ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>

  </div>
  </div>
</div>
</body>
</html>
<?php
if (isset($conn)) {
    $conn->close();
}
?>