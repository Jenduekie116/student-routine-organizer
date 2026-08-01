<?php
require 'auth_guard.php';
require 'database.php';

$user_id = $_SESSION['user_id'];

// ---------------------------------------------------------
// Exercise Tracker dashboard data
// ---------------------------------------------------------

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

// Last week's calories (trend comparison only)
$stmt = $conn->prepare(
    "SELECT COALESCE(SUM(calories_burned),0) AS calories
     FROM exercise_records WHERE user_id = ? AND exercise_date BETWEEN ? AND ?"
);
$stmt->bind_param("iss", $user_id, $last_week_start, $last_week_end);
$stmt->execute();
$last_week_calories = (int) $stmt->get_result()->fetch_assoc()['calories'];

$trend_text = null;
$trend_class = '';
if ($last_week_calories > 0) {
    $change = round((($week_stats['calories'] - $last_week_calories) / $last_week_calories) * 100);
    $trend_text = ($change >= 0 ? '+' . $change . '%' : $change . '%') . ' vs last week';
    $trend_class = $change >= 0 ? 'trend-up' : 'trend-down';
} elseif ($week_stats['calories'] > 0) {
    $trend_text = 'New this week';
    $trend_class = 'trend-up';
}

// Most frequent activity (all-time)
$stmt = $conn->prepare(
    "SELECT activity_type, COUNT(*) AS cnt FROM exercise_records WHERE user_id = ? GROUP BY activity_type ORDER BY cnt DESC LIMIT 1"
);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$top_activity = $stmt->get_result()->fetch_assoc();

// Recent 4 records
$stmt = $conn->prepare(
    "SELECT * FROM exercise_records WHERE user_id = ? ORDER BY exercise_date DESC, exercise_id DESC LIMIT 4"
);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$recent_records = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$has_any_records = !empty($recent_records);

// Last 7 calendar days of calories, for the chart
$daily_calories = [];
$stmt = $conn->prepare(
    "SELECT exercise_date, SUM(calories_burned) AS calories
     FROM exercise_records
     WHERE user_id = ? AND exercise_date >= (CURDATE() - INTERVAL 6 DAY)
     GROUP BY exercise_date"
);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $daily_calories[$row['exercise_date']] = (int) $row['calories'];
}

$chart_labels = [];
$chart_values = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $chart_labels[] = date('D', strtotime($d));
    $chart_values[] = $daily_calories[$d] ?? 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Dashboard - Student Routine Organizer</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<div class="app-layout">
  <?php include 'sidebar.php'; ?>
  <div class="main-content">
  <div class="container">

    <div class="page-header">
      <div>
        <h1 style="margin-bottom:2px;">Welcome back, <?= htmlspecialchars($_SESSION['name']) ?> 👋</h1>
        <p class="text-muted" style="margin:0;">Here's your exercise activity overview.</p>
      </div>
      <a href="exercise_tracker.php?action=add" class="btn btn-primary">+ Log workout</a>
    </div>

    <?php if (!$has_any_records): ?>

      <div class="card">
        <p class="text-muted">No workouts logged yet. Click "Log workout" above to get started.</p>
      </div>

    <?php else: ?>

      <!-- ===================== HIGHLIGHT STAT CARDS ===================== -->
      <div class="grid-4">
        <div class="highlight-card hl-hero">
          <div class="icon-chip">🔥</div>
          <div class="stat-label">Workouts this week</div>
          <div class="stat-value"><?= (int) $week_stats['workouts'] ?></div>
        </div>
        <div class="highlight-card hl-blue">
          <div class="icon-chip">⚡</div>
          <div class="stat-label">Calories this week</div>
          <div class="stat-value"><?= (int) $week_stats['calories'] ?></div>
          <?php if ($trend_text): ?>
            <div style="font-size:0.75rem; margin-top:4px; font-weight:500;"><?= htmlspecialchars($trend_text) ?></div>
          <?php endif; ?>
        </div>
        <div class="highlight-card hl-amber">
          <div class="icon-chip">⏱️</div>
          <div class="stat-label">Minutes this week</div>
          <div class="stat-value"><?= (int) $week_stats['minutes'] ?></div>
        </div>
        <div class="highlight-card hl-pink">
          <div class="icon-chip">🏆</div>
          <div class="stat-label">Top activity</div>
          <div class="stat-value" style="font-size:1.3rem;">
            <?= $top_activity ? htmlspecialchars($top_activity['activity_type']) : '-' ?>
          </div>
        </div>
      </div>

      <!-- ===================== CHART + RECENT ACTIVITY ===================== -->
      <div class="dashboard-row">
        <div class="card chart-card">
          <strong>Calories burned - last 7 days</strong>
          <canvas id="caloriesChart" style="margin-top:14px;"></canvas>
        </div>

        <div class="card">
          <strong>Recent activity</strong>
          <div style="margin-top:12px;">
            <?php foreach ($recent_records as $r): ?>
              <div class="mini-activity-item">
                <span class="badge"><?= htmlspecialchars($r['activity_type']) ?></span>
                <span class="text-muted"><?= htmlspecialchars($r['exercise_date']) ?></span>
                <span><?= (int) $r['calories_burned'] ?> kcal</span>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

    <?php endif; ?>

  </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
<script>
const ctx = document.getElementById('caloriesChart');
if (ctx) {
  new Chart(ctx, {
    type: 'bar',
    data: {
      labels: <?= json_encode($chart_labels) ?>,
      datasets: [{
        label: 'Calories burned',
        data: <?= json_encode($chart_values) ?>,
        backgroundColor: '#22c55e',
        hoverBackgroundColor: '#16a34a',
        borderRadius: 8,
        maxBarThickness: 34
      }]
    },
    options: {
      responsive: true,
      plugins: { legend: { display: false } },
      scales: {
        y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: '#eef0f4' } },
        x: { grid: { display: false } }
      }
    }
  });
}
</script>
</body>
</html>
<?php
if (isset($conn)) {
    $conn->close();
}
?>