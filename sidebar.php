<?php
$current_page = basename($_SERVER['PHP_SELF']);

function nav_active($page, $current) {
    return $page === $current ? 'active' : '';
}
?>
<aside class="sidebar">
  <div class="sidebar-logo">
    <span class="sidebar-logo-text">📚 Routinely</span>
  </div>

  <nav class="sidebar-nav">
    <a href="index.php" class="sidebar-link <?= nav_active('index.php', $current_page) ?>">🏠 Dashboard</a>
    <a href="exercise_tracker.php" class="sidebar-link <?= nav_active('exercise_tracker.php', $current_page) ?>">🏃‍♂️ Exercise Tracker</a>
    <a href="diary_journal.php" class="sidebar-link <?= nav_active('diary_journal.php', $current_page) ?>">📖 Diary Journal</a>
    <a href="money_tracker.php" class="sidebar-link <?= nav_active('money_tracker.php', $current_page) ?>">💰 Money Tracker</a>
    <a href="habit_tracker.php" class="sidebar-link <?= nav_active('habit_tracker.php', $current_page) ?>">✅ Habit Tracker</a>
  </nav>

  <div class="sidebar-footer">
    <div class="sidebar-user"><?= htmlspecialchars($_SESSION['name'] ?? 'User') ?></div>
    <a href="logout.php" class="sidebar-link">🚪 Logout</a>
  </div>
</aside>