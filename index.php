<?php
require 'auth_guard.php';
require 'database.php';

$user_id = $_SESSION['user_id'];

// ---------------------------------------------------------
// Date ranges
// ---------------------------------------------------------
$this_week_start = date('Y-m-d', strtotime('monday this week'));
$this_week_end   = date('Y-m-d', strtotime('sunday this week'));
$last_week_start = date('Y-m-d', strtotime('monday last week'));
$last_week_end   = date('Y-m-d', strtotime('sunday last week'));

// ---------------------------------------------------------
// Exercise Tracker data (original + summary)
// ---------------------------------------------------------
$stmt = $conn->prepare(
    "SELECT COUNT(*) AS workouts, COALESCE(SUM(calories_burned),0) AS calories, COALESCE(SUM(duration_minutes),0) AS minutes
     FROM exercise_records WHERE user_id = ? AND exercise_date BETWEEN ? AND ?"
);
$stmt->bind_param("iss", $user_id, $this_week_start, $this_week_end);
$stmt->execute();
$week_stats = $stmt->get_result()->fetch_assoc();

$stmt = $conn->prepare(
    "SELECT COALESCE(SUM(calories_burned),0) AS calories
     FROM exercise_records WHERE user_id = ? AND exercise_date BETWEEN ? AND ?"
);
$stmt->bind_param("iss", $user_id, $last_week_start, $last_week_end);
$stmt->execute();
$last_week_calories = (int) $stmt->get_result()->fetch_assoc()['calories'];

$trend_text = null;
if ($last_week_calories > 0) {
    $change = round((($week_stats['calories'] - $last_week_calories) / $last_week_calories) * 100);
    $trend_text = ($change >= 0 ? '+' . $change . '%' : $change . '%') . ' vs last week';
} elseif ($week_stats['calories'] > 0) {
    $trend_text = 'New this week';
}

$stmt = $conn->prepare(
    "SELECT activity_type, COUNT(*) AS cnt FROM exercise_records WHERE user_id = ? GROUP BY activity_type ORDER BY cnt DESC LIMIT 1"
);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$top_activity = $stmt->get_result()->fetch_assoc();

$stmt = $conn->prepare(
    "SELECT * FROM exercise_records WHERE user_id = ? ORDER BY exercise_date DESC, exercise_id DESC LIMIT 4"
);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$recent_records = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$has_any_records = !empty($recent_records);

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

// ---------------------------------------------------------
// Habit summary + week matrix
// ---------------------------------------------------------
$stmt = $conn->prepare(
    "SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN completion_status = 'Completed' THEN 1 ELSE 0 END) AS completed,
        SUM(CASE WHEN completion_status = 'Pending'   THEN 1 ELSE 0 END) AS pending
     FROM habit_records WHERE user_id = ?"
);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$habit = $stmt->get_result()->fetch_assoc();
$habit_total     = (int)($habit['total'] ?? 0);
$habit_completed = (int)($habit['completed'] ?? 0);
$habit_pending   = (int)($habit['pending'] ?? 0);
$habit_rate      = $habit_total ? round(($habit_completed / $habit_total) * 100) : 0;

$habit_catalog = [];
$week_matrix   = [];
$week_dates    = [];
$monday = strtotime('monday this week');
for ($i = 0; $i < 7; $i++) {
    $d = date('Y-m-d', strtotime("+$i days", $monday));
    $week_dates[] = $d;
    $week_matrix[$d] = [];
}

$stmt = $conn->prepare(
    "SELECT habit_name, emoji, target_frequency, habit_date, completion_status
     FROM habit_records WHERE user_id = ? ORDER BY habit_id DESC"
);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $name = $row['habit_name'];
    if (!isset($habit_catalog[$name])) {
        $habit_catalog[$name] = [
            'habit_name' => $name,
            'emoji'      => $row['emoji'] ?? '✅',
            'frequency'  => $row['target_frequency'],
        ];
    }
    if (isset($week_matrix[$row['habit_date']])) {
        $week_matrix[$row['habit_date']][$name] = $row['completion_status'];
    }
}
$catalog_list  = array_values($habit_catalog);
$catalog_count = count($catalog_list);
$catalog_display = array_slice($catalog_list, 0, 6);
$catalog_display_count = count($catalog_display);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Dashboard - Student Routine Organizer</title>
<link rel="stylesheet" href="style.css">
<style>
/* 4-column snapshot row */
.snapshot-row-4 {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 14px;
  margin-bottom: 20px;
}
@media (max-width: 900px) {
  .snapshot-row-4 { grid-template-columns: 1fr 1fr; }
}
@media (max-width: 500px) {
  .snapshot-row-4 { grid-template-columns: 1fr; }
}

/* Smaller combined exercise card */
.exercise-combo-card {
  background: linear-gradient(135deg, #22c55e, #16a34a);
  color: #fff;
  border-radius: var(--radius);
  padding: 14px 16px;
  box-shadow: 0 4px 16px rgba(15, 23, 42, 0.06);
  display: flex;
  flex-direction: column;
  min-height: 140px;
}
.exercise-combo-card .icon-chip {
  width: 32px; height: 32px;
  border-radius: 10px;
  display: flex; align-items: center; justify-content: center;
  font-size: 1rem;
  margin-bottom: 10px;
  background: rgba(255,255,255,0.22);
}
.exercise-combo-card .combo-top {
  display: flex;
  gap: 12px;
  align-items: flex-start;
  flex: 1;
}
.exercise-combo-card .combo-block { flex: 1; min-width: 0; }
.exercise-combo-card .combo-label {
  font-size: 0.72rem;
  opacity: 0.85;
  font-weight: 500;
  margin-bottom: 2px;
}
.exercise-combo-card .combo-value {
  font-size: 1.35rem;
  font-weight: 700;
  letter-spacing: -0.3px;
  line-height: 1.15;
  word-break: break-word;
}
.exercise-combo-card .combo-sub {
  font-size: 0.68rem;
  opacity: 0.9;
  margin-top: 2px;
}
.exercise-combo-card .combo-divider {
  width: 1px;
  background: rgba(255,255,255,0.25);
  align-self: stretch;
}

/* Smaller habit gauge card */
.habit-gauge-card {
  background: #fff;
  border-radius: var(--radius);
  padding: 12px 12px 10px;
  box-shadow: 0 4px 16px rgba(15, 23, 42, 0.06);
  border: 1px solid rgba(15, 23, 42, 0.04);
  display: flex;
  flex-direction: column;
  align-items: center;
  min-height: 140px;
}
.habit-gauge-card .gauge-title {
  align-self: flex-start;
  font-size: 0.78rem;
  font-weight: 600;
  color: var(--theme-text);
  margin-bottom: 2px;
}
.habit-gauge-wrap {
  position: relative;
  width: 120px;
  height: 76px;
}
.habit-gauge-wrap svg {
  width: 120px;
  height: 76px;
  overflow: visible;
}
.habit-gauge-center {
  position: absolute;
  left: 0; right: 0;
  bottom: 2px;
  text-align: center;
}
.habit-gauge-center .pct {
  font-size: 1.2rem;
  font-weight: 700;
  color: var(--theme-text);
  line-height: 1.1;
}
.habit-gauge-center .sub {
  font-size: 0.62rem;
  color: var(--theme-text-muted);
  margin-top: 1px;
}
.habit-gauge-legend {
  display: flex;
  gap: 8px;
  margin-top: 6px;
  font-size: 0.65rem;
  color: var(--theme-text-muted);
  flex-wrap: wrap;
  justify-content: center;
}
.habit-gauge-legend span {
  display: inline-flex;
  align-items: center;
  gap: 4px;
}
.legend-dot {
  width: 8px; height: 8px;
  border-radius: 50%;
  display: inline-block;
}
.legend-dot.completed { background: #22c55e; }
.legend-dot.pending {
  background: repeating-linear-gradient(
    -45deg, #94a3b8, #94a3b8 2px, transparent 2px, transparent 4px
  );
  border: 1px solid #94a3b8;
}

/* Blank placeholder cards */
.snapshot-placeholder {
  background: #fff;
  border-radius: var(--radius);
  border: 1.5px dashed #d1d5db;
  min-height: 140px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #9ca3af;
  font-size: 0.8rem;
  font-weight: 500;
}

/* Compact habit list + week */
.dash-habit-wrap {
  display: grid;
  grid-template-columns: 140px 1fr;
  gap: 12px;
}
@media (max-width: 600px) {
  .dash-habit-wrap { grid-template-columns: 1fr; }
}
.dash-habit-list { font-size: 0.8rem; }
.dash-habit-list .item {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 5px 0;
  border-bottom: 1px solid var(--theme-border);
}
.dash-habit-list .item:last-child { border-bottom: none; }
.dash-habit-list .em { width: 18px; text-align: center; }
.dash-habit-list .nm {
  flex: 1;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  font-weight: 500;
}
.dash-week-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.72rem;
}
.dash-week-table th, .dash-week-table td {
  padding: 5px 4px;
  text-align: center;
  border-bottom: 1px solid var(--theme-border);
}
.dash-week-table th {
  color: var(--theme-text-muted);
  font-weight: 500;
  font-size: 0.68rem;
}
.dash-week-table td.d {
  text-align: left;
  white-space: nowrap;
  font-weight: 500;
  min-width: 72px;
}
.dash-week-table .bar-wrap {
  display: flex;
  align-items: center;
  gap: 5px;
  min-width: 70px;
}
.dash-week-table .bar-track {
  flex: 1;
  height: 7px;
  background: #eef0f4;
  border-radius: 999px;
  overflow: hidden;
}
.dash-week-table .bar-fill {
  height: 100%;
  background: #1f2430;
  border-radius: 999px;
}
.dash-week-table .pct {
  font-weight: 600;
  min-width: 28px;
  font-size: 0.68rem;
}
.dash-check {
  display: inline-flex;
  width: 16px; height: 16px;
  border-radius: 3px;
  align-items: center;
  justify-content: center;
  font-size: 0.6rem;
}
.dash-check.on { background: #3b82f6; color: #fff; }
.dash-check.off { border: 1.5px solid #cbd5e1; background: #fff; }

.section-title-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 12px;
}
.section-title-row h2 {
  margin: 0;
  font-size: 1.05rem;
  font-weight: 600;
}
</style>
</head>
<body>
<div class="app-layout">
  <?php include 'sidebar.php'; ?>
  <div class="main-content">
  <div class="container">

    <div class="page-header">
      <div>
        <h1 style="margin-bottom:2px;">Welcome back, <?= htmlspecialchars($_SESSION['name']) ?> 👋</h1>
        <p class="text-muted" style="margin:0;">Here's your routine overview.</p>
      </div>
    </div>

    <!-- ===================== TOP SNAPSHOT (4 columns) ===================== -->
    <div class="snapshot-row-4">

      <!-- 1. Exercise combo (smaller) -->
      <div class="exercise-combo-card">
        <div class="icon-chip">🔥</div>
        <div class="combo-top">
          <div class="combo-block">
            <div class="combo-label">Workouts this week</div>
            <div class="combo-value"><?= (int)$week_stats['workouts'] ?></div>
            <div class="combo-sub">
              <?= (int)$week_stats['calories'] ?> kcal · <?= (int)$week_stats['minutes'] ?> min
            </div>
          </div>
          <div class="combo-divider"></div>
          <div class="combo-block">
            <div class="combo-label">Top activity</div>
            <div class="combo-value" style="font-size:1.05rem;">
              <?= $top_activity ? htmlspecialchars($top_activity['activity_type']) : '—' ?>
            </div>
            <div class="combo-sub">
              <?= $top_activity ? (int)$top_activity['cnt'] . ' session' . ((int)$top_activity['cnt'] === 1 ? '' : 's') : 'No data yet' ?>
            </div>
          </div>
        </div>
      </div>

      <!-- 2. Habit Progress gauge (smaller) -->
      <?php
        $arc_r = 45;
        $arc_len = 3.14159 * $arc_r;
        $completed_len = $habit_total > 0 ? ($habit_completed / $habit_total) * $arc_len : 0;
        $pending_len   = $habit_total > 0 ? ($habit_pending / $habit_total) * $arc_len : $arc_len;
      ?>
      <div class="habit-gauge-card">
        <div class="gauge-title">Habit Progress</div>
        <div class="habit-gauge-wrap">
          <svg viewBox="0 0 120 76" aria-hidden="true">
            <path d="M 15 68 A 45 45 0 0 1 105 68"
                  fill="none" stroke="#e2e8f0" stroke-width="12"
                  stroke-linecap="round"/>
            <defs>
              <pattern id="pendingStripes" patternUnits="userSpaceOnUse" width="5" height="5" patternTransform="rotate(-45)">
                <line x1="0" y1="0" x2="0" y2="5" stroke="#94a3b8" stroke-width="2"/>
              </pattern>
            </defs>
            <?php if ($habit_pending > 0 && $habit_total > 0): ?>
            <path d="M 15 68 A 45 45 0 0 1 105 68"
                  fill="none" stroke="url(#pendingStripes)" stroke-width="12"
                  stroke-linecap="round"
                  stroke-dasharray="<?= round($pending_len, 1) ?> <?= round($arc_len, 1) ?>"
                  stroke-dashoffset="<?= round(-$completed_len, 1) ?>"/>
            <?php endif; ?>
            <?php if ($habit_completed > 0 && $habit_total > 0): ?>
            <path d="M 15 68 A 45 45 0 0 1 105 68"
                  fill="none" stroke="#22c55e" stroke-width="12"
                  stroke-linecap="round"
                  stroke-dasharray="<?= round($completed_len, 1) ?> <?= round($arc_len, 1) ?>"
                  stroke-dashoffset="0"/>
            <?php endif; ?>
          </svg>
          <div class="habit-gauge-center">
            <div class="pct"><?= $habit_rate ?>%</div>
            <div class="sub"><?= $habit_completed ?>/<?= $habit_total ?> done</div>
          </div>
        </div>
        <div class="habit-gauge-legend">
          <span><i class="legend-dot completed"></i> Done</span>
          <span><i class="legend-dot pending"></i> Pending</span>
        </div>
      </div>

      <!-- 3. Placeholder -->
      <div class="snapshot-placeholder">Coming soon</div>

      <!-- 4. Placeholder -->
      <div class="snapshot-placeholder">Coming soon</div>
    </div>

    <?php if (!$has_any_records && $catalog_display_count === 0): ?>

      <div class="card">
        <p class="text-muted">No activity yet. Log a workout or add a habit to get started.</p>
      </div>

    <?php else: ?>

      <!-- ===================== BOTTOM: original chart + recent ===================== -->
      <div class="dashboard-row">
        <div class="card chart-card">
          <strong>Calories burned - last 7 days</strong>
          <?php if ($has_any_records): ?>
            <canvas id="caloriesChart" style="margin-top:14px;"></canvas>
          <?php else: ?>
            <p class="text-muted" style="margin-top:14px; font-size:0.85rem;">No workout data for the chart yet.</p>
          <?php endif; ?>
        </div>

        <div class="card">
          <strong>Recent activity</strong>
          <div style="margin-top:12px;">
            <?php if (empty($recent_records)): ?>
              <p class="text-muted" style="font-size:0.85rem; margin:0;">No workouts yet.</p>
            <?php else: ?>
              <?php foreach ($recent_records as $r): ?>
                <div class="mini-activity-item">
                  <span class="badge"><?= htmlspecialchars($r['activity_type']) ?></span>
                  <span class="text-muted"><?= htmlspecialchars($r['exercise_date']) ?></span>
                  <span><?= (int) $r['calories_burned'] ?> kcal</span>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- Habit List + This Week -->
      <div class="card" style="margin-top:16px;">
        <div class="section-title-row">
          <h2>✅ Habits</h2>
        </div>
        <?php if ($catalog_display_count === 0): ?>
          <p class="text-muted" style="margin:0; font-size:0.85rem;">No habits yet.</p>
          <a href="habit_tracker.php?action=add" class="btn btn-primary btn-sm" style="margin-top:10px;">+ Add habit</a>
        <?php else: ?>
          <div class="dash-habit-wrap">
            <div class="dash-habit-list">
              <div class="text-muted" style="font-size:0.72rem; font-weight:600; margin-bottom:4px;">Habit List</div>
              <?php foreach ($catalog_display as $h): ?>
                <div class="item">
                  <span class="em"><?= htmlspecialchars($h['emoji']) ?></span>
                  <span class="nm" title="<?= htmlspecialchars($h['habit_name']) ?>"><?= htmlspecialchars($h['habit_name']) ?></span>
                </div>
              <?php endforeach; ?>
              <?php if ($catalog_count > $catalog_display_count): ?>
                <div class="text-muted" style="font-size:0.72rem; margin-top:4px;">+<?= $catalog_count - $catalog_display_count ?> more</div>
              <?php endif; ?>
            </div>

            <div style="overflow-x:auto;">
              <div class="text-muted" style="font-size:0.72rem; font-weight:600; margin-bottom:4px;">This Week</div>
              <table class="dash-week-table">
                <thead>
                  <tr>
                    <th style="text-align:left;">Date</th>
                    <th style="text-align:left;">Progress</th>
                    <?php foreach ($catalog_display as $h): ?>
                      <th title="<?= htmlspecialchars($h['habit_name']) ?>"><?= htmlspecialchars($h['emoji']) ?></th>
                    <?php endforeach; ?>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($week_dates as $d):
                    $done = 0;
                    $statuses = [];
                    foreach ($catalog_display as $h) {
                      $st = $week_matrix[$d][$h['habit_name']] ?? null;
                      $statuses[] = $st;
                      if ($st === 'Completed') $done++;
                    }
                    $pct = $catalog_display_count ? round(($done / $catalog_display_count) * 100) : 0;
                    $is_today = ($d === date('Y-m-d'));
                  ?>
                    <tr style="<?= $is_today ? 'background:#f0fdf4;' : '' ?>">
                      <td class="d"><?= date('D j', strtotime($d)) ?><?= $is_today ? '*' : '' ?></td>
                      <td>
                        <div class="bar-wrap">
                          <div class="bar-track"><div class="bar-fill" style="width:<?= $pct ?>%;"></div></div>
                          <span class="pct"><?= $pct ?>%</span>
                        </div>
                      </td>
                      <?php foreach ($statuses as $st): ?>
                        <td>
                          <?php if ($st === 'Completed'): ?>
                            <span class="dash-check on">✓</span>
                          <?php else: ?>
                            <span class="dash-check off"></span>
                          <?php endif; ?>
                        </td>
                      <?php endforeach; ?>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        <?php endif; ?>
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
