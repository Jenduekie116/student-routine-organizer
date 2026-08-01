<?php
require 'auth_guard.php';
require 'database.php';

$user_id = $_SESSION['user_id'];

// ---------------------------------------------------------
// Exercise Tracker widget data
// ---------------------------------------------------------

// Current week (Mon-Sun) and previous week date ranges
$this_week_start = date('Y-m-d', strtotime('monday this week'));
$this_week_end   = date('Y-m-d', strtotime('sunday this week'));
$last_week_start = date('Y-m-d', strtotime('monday last week'));
$last_week_end   = date('Y-m-d', strtotime('sunday last week'));

// This week's totals
$stmt = $conn->prepare(
    "SELECT COUNT(*) AS workouts, COALESCE(SUM(calories_burned),0) AS calories, COALESCE(SUM(duration_minutes),0) AS minutes
     FROM exercise_records WHERE user_id = ? AND exercise_date BETWEEN ? AND ?"
);
$stmt->bind_param("iss", $user_id, $this_week_start, $this_week_end);
$stmt->execute();
$week_stats = $stmt->get_result()->fetch_assoc();

// Last week's calories (for trend comparison only)
$stmt = $conn->prepare(
    "SELECT COALESCE(SUM(calories_burned),0) AS calories
     FROM exercise_records WHERE user_id = ? AND exercise_date BETWEEN ? AND ?"
);
$stmt->bind_param("iss", $user_id, $last_week_start, $last_week_end);
$stmt->execute();
$last_week_calories = (int) $stmt->get_result()->fetch_assoc()['calories'];

// Trend text vs last week
$trend_text = null;
$trend_class = '';
if ($last_week_calories > 0) {
    $change = round((($week_stats['calories'] - $last_week_calories) / $last_week_calories) * 100);
    $trend_text = ($change >= 0 ? '↑ ' . $change . '%' : '↓ ' . abs($change) . '%') . ' vs last week';
    $trend_class = $change >= 0 ? 'trend-up' : 'trend-down';
} elseif ($week_stats['calories'] > 0) {
    $trend_text = 'New this week';
    $trend_class = 'trend-up';
}

// Last workout logged (most recent record overall)
$stmt = $conn->prepare(
    "SELECT * FROM exercise_records WHERE user_id = ? ORDER BY exercise_date DESC, exercise_id DESC LIMIT 1"
);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$last_workout = $stmt->get_result()->fetch_assoc();

$last_workout_text = 'No workouts logged yet';
if ($last_workout) {
    $days_ago = (int) floor((strtotime(date('Y-m-d')) - strtotime($last_workout['exercise_date'])) / 86400);
    if ($days_ago === 0) {
        $when = 'Today';
    } elseif ($days_ago === 1) {
        $when = 'Yesterday';
    } else {
        $when = $days_ago . ' days ago';
    }
    $last_workout_text = $last_workout['activity_type'] . ' - ' . $when;
}

// Most frequent activity type (all-time)
$stmt = $conn->prepare(
    "SELECT activity_type, COUNT(*) AS cnt FROM exercise_records WHERE user_id = ? GROUP BY activity_type ORDER BY cnt DESC LIMIT 1"
);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$top_activity = $stmt->get_result()->fetch_assoc();

// Recent 3 records
$stmt = $conn->prepare(
    "SELECT * FROM exercise_records WHERE user_id = ? ORDER BY exercise_date DESC, exercise_id DESC LIMIT 3"
);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$recent_records = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$has_any_records = !empty($recent_records) || $last_workout;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Dashboard - Student Routine Organizer</title>
<link rel="stylesheet" href="style.css">
<style>
.module-card {
  text-align: center;
  padding: 40px 20px;
  text-decoration: none;
  display: block;
  color: inherit;
}
</style>
</head>
<body>
<div class="container">

  <div style="margin-bottom: 30px; text-align: center;">
    <h1 style="color: var(--theme-blue);">Welcome back, <?= htmlspecialchars($_SESSION['name']) ?> 👋</h1>
    <p class="text-muted">Select a module below to manage your daily routines.</p>
  </div>

  <!-- ===================== EXERCISE TRACKER WIDGET ===================== -->
  <div class="card" style="margin-bottom: 24px;">
    <div class="widget-header">
      <h3>🏃‍♂️ Exercise Tracker</h3>
      <a href="exercise_tracker.php?action=add" class="btn btn-primary btn-sm">+ Log workout</a>
    </div>

    <?php if (!$has_any_records): ?>
      <p class="text-muted">No workouts logged yet. Click "Log workout" to get started.</p>
    <?php else: ?>

      <div class="grid-4" style="margin-bottom: 18px;">
        <div class="stat-card">
          <div class="stat-label">Workouts this week</div>
          <div class="stat-value"><?= (int) $week_stats['workouts'] ?></div>
        </div>
        <div class="stat-card">
          <div class="stat-label">Calories this week</div>
          <div class="stat-value"><?= (int) $week_stats['calories'] ?></div>
          <?php if ($trend_text): ?>
            <div class="<?= $trend_class ?>" style="font-size: 0.75rem; margin-top: 2px;">
              <?= htmlspecialchars($trend_text) ?>
            </div>
          <?php endif; ?>
        </div>
        <div class="stat-card">
          <div class="stat-label">Minutes this week</div>
          <div class="stat-value"><?= (int) $week_stats['minutes'] ?></div>
        </div>
        <div class="stat-card">
          <div class="stat-label">Top activity</div>
          <div class="stat-value" style="font-size: 1.1rem;">
            <?= $top_activity ? htmlspecialchars($top_activity['activity_type']) : '-' ?>
          </div>
        </div>
      </div>

      <p class="text-muted" style="font-size: 0.85rem; margin-bottom: 10px;">
        Last workout: <?= htmlspecialchars($last_workout_text) ?>
      </p>

      <div>
        <?php foreach ($recent_records as $r): ?>
          <div class="mini-activity-item">
            <span class="badge"><?= htmlspecialchars($r['activity_type']) ?></span>
            <span class="text-muted"><?= htmlspecialchars($r['exercise_date']) ?></span>
            <span><?= (int) $r['calories_burned'] ?> kcal</span>
          </div>
        <?php endforeach; ?>
      </div>

    <?php endif; ?>
  </div>

  <!-- ===================== MODULE GRID ===================== -->
  <div class="grid-4" style="margin-top: 10px;">
    <a href="exercise_tracker.php" class="card clickable module-card">
      <div class="module-icon">🏃‍♂️</div>
      <h3>Exercise Tracker</h3>
      <p class="text-muted" style="font-size: 0.9rem;">Record workouts & monitor fitness</p>
    </a>

    <a href="diary_journal.php" class="card clickable module-card">
      <div class="module-icon">📖</div>
      <h3>Diary Journal</h3>
      <p class="text-muted" style="font-size: 0.9rem;">Track reflections & daily moods</p>
    </a>

    <a href="money_tracker.php" class="card clickable module-card">
      <div class="module-icon">💰</div>
      <h3>Money Tracker</h3>
      <p class="text-muted" style="font-size: 0.9rem;">Manage income & expenses</p>
    </a>

    <a href="habit_tracker.php" class="card clickable module-card">
      <div class="module-icon">✅</div>
      <h3>Habit Tracker</h3>
      <p class="text-muted" style="font-size: 0.9rem;">Monitor daily habit progress</p>
    </a>
  </div>

</div>
</body>
</html>
<?php
if (isset($conn)) {
    $conn->close();
}
?>