<?php
require 'auth_guard.php'; 
require 'database.php';

$user_id = $_SESSION['user_id'];
$action  = $_GET['action'] ?? 'list';
$errors  = [];
$success = $_GET['success'] ?? '';

$activity_options = ['Jogging', 'Cycling', 'Gym', 'Swimming', 'Other'];

// ---------------------------------------------------------
// Handle form submissions (Add / Edit / Delete)
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_action'])) {

    // ---------- DELETE ----------
    if ($_POST['form_action'] === 'delete') {
        $exercise_id = (int)($_POST['exercise_id'] ?? 0);

        $stmt = $conn->prepare("DELETE FROM exercise_records WHERE exercise_id = ? AND user_id = ?");
        $stmt->bind_param("ii", $exercise_id, $user_id);
        $stmt->execute();

        header("Location: exercise_tracker.php?success=deleted");
        exit();
    }

    // ---------- ADD or EDIT (shared validation) ----------
    if ($_POST['form_action'] === 'add' || $_POST['form_action'] === 'edit') {
        $activity_type    = trim($_POST['activity_type'] ?? '');
        $duration_minutes = trim($_POST['duration_minutes'] ?? '');
        $calories_burned  = trim($_POST['calories_burned'] ?? '');
        $exercise_date    = trim($_POST['exercise_date'] ?? '');
        $notes            = trim($_POST['notes'] ?? '');

        // Validation
        if ($activity_type === '' || strlen($activity_type) > 50) {
            $errors[] = "Please select a valid activity type.";
        }
        if (!ctype_digit($duration_minutes) || (int)$duration_minutes <= 0) {
            $errors[] = "Duration must be a positive whole number (minutes).";
        }
        if (!ctype_digit($calories_burned) || (int)$calories_burned <= 0) {
            $errors[] = "Calories burned must be a positive whole number.";
        }
        $date_obj = DateTime::createFromFormat('Y-m-d', $exercise_date);
        if (!$date_obj || $date_obj->format('Y-m-d') !== $exercise_date) {
            $errors[] = "Please enter a valid date.";
        }
        if (strlen($notes) > 255) {
            $errors[] = "Notes must be under 255 characters.";
        }

        if (empty($errors)) {
            $duration_minutes = (int)$duration_minutes;
            $calories_burned  = (int)$calories_burned;

            if ($_POST['form_action'] === 'add') {
                $stmt = $conn->prepare(
                    "INSERT INTO exercise_records (user_id, activity_type, duration_minutes, calories_burned, exercise_date, notes)
                     VALUES (?, ?, ?, ?, ?, ?)"
                );
                $stmt->bind_param("isiiss", $user_id, $activity_type, $duration_minutes, $calories_burned, $exercise_date, $notes);

                if ($stmt->execute()) {
                    header("Location: exercise_tracker.php?success=added");
                    exit();
                } else {
                    $errors[] = "Something went wrong while saving. Please try again.";
                }

            } else { // edit
                $exercise_id = (int)($_POST['exercise_id'] ?? 0);
                $stmt = $conn->prepare(
                    "UPDATE exercise_records
                     SET activity_type = ?, duration_minutes = ?, calories_burned = ?, exercise_date = ?, notes = ?
                     WHERE exercise_id = ? AND user_id = ?"
                );
                $stmt->bind_param("siissii", $activity_type, $duration_minutes, $calories_burned, $exercise_date, $notes, $exercise_id, $user_id);
                if ($stmt->execute()) {
                    header("Location: exercise_tracker.php?success=updated");
                    exit();
                } else {
                    $errors[] = "Something went wrong while updating. Please try again.";
                }
            }
        }

        // If validation failed, re-show the form with entered values preserved
        $action = $_POST['form_action'];
        $form_values = [
            'activity_type'    => $activity_type,
            'duration_minutes' => $duration_minutes,
            'calories_burned'  => $calories_burned,
            'exercise_date'    => $exercise_date,
            'notes'            => $notes,
            'exercise_id'      => $_POST['exercise_id'] ?? null,
        ];
    }
}

// ---------------------------------------------------------
// Load record for Edit form (GET request, first load)
// ---------------------------------------------------------
$edit_record = null;
if ($action === 'edit' && !isset($form_values)) {
    $id = (int)($_GET['id'] ?? 0);
    $stmt = $conn->prepare("SELECT * FROM exercise_records WHERE exercise_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $edit_record = $result->fetch_assoc();

    if (!$edit_record) {
        header("Location: exercise_tracker.php?success=notfound");
        exit();
    }
    $form_values = $edit_record;
}

// ---------------------------------------------------------
// Load list + simple stats (for 'list' view)
// ---------------------------------------------------------
$records = [];
$total_workouts = 0;
$total_calories = 0;
$total_minutes  = 0;

if ($action === 'list') {
    $stmt = $conn->prepare("SELECT * FROM exercise_records WHERE user_id = ? ORDER BY exercise_date DESC, exercise_id DESC");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $records[] = $row;
        $total_workouts++;
        $total_calories += (int)$row['calories_burned'];
        $total_minutes  += (int)$row['duration_minutes'];
    }
}

$success_messages = [
    'added'    => 'Exercise record added successfully.',
    'updated'  => 'Exercise record updated successfully.',
    'deleted'  => 'Exercise record deleted.',
    'notfound' => 'That record could not be found.',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Exercise Tracker - Student Routine Organizer</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">

  <div class="page-header">
    <h1>🏃‍♂️ Exercise Tracker</h1>
    <a href="index.php" class="back-link">&larr; Back to dashboard</a>
  </div>

  <?php if (isset($success_messages[$success])): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success_messages[$success]) ?></div>
  <?php endif; ?>

  <?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
      <?php foreach ($errors as $err): ?>
        <div><?= htmlspecialchars($err) ?></div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if ($action === 'list'): ?>

    <!-- ===================== STATS ===================== -->
    <div class="grid-4" style="margin-bottom: 24px;">
      <div class="card stat-card">
        <div class="stat-label">Total workouts</div>
        <div class="stat-value"><?= $total_workouts ?></div>
      </div>
      <div class="card stat-card">
        <div class="stat-label">Total calories burned</div>
        <div class="stat-value"><?= $total_calories ?></div>
      </div>
      <div class="card stat-card">
        <div class="stat-label">Total minutes exercised</div>
        <div class="stat-value"><?= $total_minutes ?></div>
      </div>
      <div class="card stat-card">
        <div class="stat-label">Avg. calories / workout</div>
        <div class="stat-value"><?= $total_workouts ? round($total_calories / $total_workouts) : 0 ?></div>
      </div>
    </div>

    <!-- ===================== TABLE ===================== -->
    <div class="card">
      <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px;">
        <strong>My exercise records</strong>
        <a href="exercise_tracker.php?action=add" class="btn btn-primary btn-sm">+ Add record</a>
      </div>

      <?php if (empty($records)): ?>
        <p class="text-muted">No exercise records yet. Click "Add record" to log your first workout.</p>
      <?php else: ?>
        <table class="data-table">
          <thead>
            <tr>
              <th>Date</th>
              <th>Activity</th>
              <th>Duration (min)</th>
              <th>Calories</th>
              <th>Notes</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($records as $r): ?>
              <tr>
                <td><?= htmlspecialchars($r['exercise_date']) ?></td>
                <td><span class="badge"><?= htmlspecialchars($r['activity_type']) ?></span></td>
                <td><?= (int)$r['duration_minutes'] ?></td>
                <td><?= (int)$r['calories_burned'] ?></td>
                <td class="text-muted"><?= htmlspecialchars($r['notes'] ?: '-') ?></td>
                <td style="white-space:nowrap;">
                  <a href="exercise_tracker.php?action=edit&id=<?= (int)$r['exercise_id'] ?>" class="btn btn-sm">Edit</a>
                  <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this exercise record?');">
                    <input type="hidden" name="form_action" value="delete">
                    <input type="hidden" name="exercise_id" value="<?= (int)$r['exercise_id'] ?>">
                    <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>

  <?php elseif ($action === 'add' || $action === 'edit'): ?>

    <!-- ===================== ADD / EDIT FORM ===================== -->
    <div class="card" style="max-width: 520px;">
      <h3 style="margin-top:0;"><?= $action === 'add' ? 'Add exercise record' : 'Edit exercise record' ?></h3>

      <form method="POST">
        <input type="hidden" name="form_action" value="<?= $action ?>">
        <?php if ($action === 'edit'): ?>
          <input type="hidden" name="exercise_id" value="<?= (int)($form_values['exercise_id']) ?>">
        <?php endif; ?>

        <div class="form-group">
          <label>Activity type</label>
          <select name="activity_type" class="form-control" required>
            <option value="">-- Select --</option>
            <?php foreach ($activity_options as $opt): ?>
              <option value="<?= $opt ?>" <?= (($form_values['activity_type'] ?? '') === $opt) ? 'selected' : '' ?>>
                <?= $opt ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <label>Duration (minutes)</label>
          <input type="number" min="1" name="duration_minutes" class="form-control"
                 value="<?= htmlspecialchars($form_values['duration_minutes'] ?? '') ?>" required>
        </div>

        <div class="form-group">
          <label>Calories burned</label>
          <input type="number" min="1" name="calories_burned" class="form-control"
                 value="<?= htmlspecialchars($form_values['calories_burned'] ?? '') ?>" required>
        </div>

        <div class="form-group">
          <label>Date</label>
          <input type="date" name="exercise_date" class="form-control"
                 value="<?= htmlspecialchars($form_values['exercise_date'] ?? '') ?>" required>
        </div>

        <div class="form-group">
          <label>Notes (optional)</label>
          <textarea name="notes" class="form-control" rows="3"><?= htmlspecialchars($form_values['notes'] ?? '') ?></textarea>
        </div>

        <button type="submit" class="btn btn-primary"><?= $action === 'add' ? 'Add record' : 'Save changes' ?></button>
        <a href="exercise_tracker.php" class="btn">Cancel</a>
      </form>
    </div>

  <?php endif; ?>

</div>
</body>
</html>
<?php
if (isset($conn)) {
    $conn->close();
}
?>