<?php
require 'auth_guard.php';   // redirects to login.php if not logged in, provides $_SESSION
require 'database.php';     // provides $conn (mysqli)

$user_id = $_SESSION['user_id'];
$action  = $_GET['action'] ?? 'list';
$errors  = [];
$success = $_GET['success'] ?? '';

$frequency_options = ['Daily', 'Weekly', 'Monthly'];
$status_options    = ['Pending', 'Completed'];
$emoji_options     = [
    '✅', '💪', '📚', '💧', '🏃', '🧘', '😴', '🥗',
    '🦷', '📝', '🧹', '🎵', '💻', '🌱', '⏰', '🎯',
    '🧠', '❤️', '🚶', '🛏️', '🧼', '📖', '🏋️', '☀️',
];

// ---------------------------------------------------------
// Handle form submissions (Add / Edit / Delete)
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_action'])) {

    // ---------- DELETE ----------
    if ($_POST['form_action'] === 'delete') {
        $habit_id = (int)($_POST['habit_id'] ?? 0);

        $stmt = $conn->prepare("DELETE FROM habit_records WHERE habit_id = ? AND user_id = ?");
        $stmt->bind_param("ii", $habit_id, $user_id);
        $stmt->execute();

        header("Location: habit_tracker.php?success=deleted");
        exit();
    }

    // ---------- TOGGLE STATUS (quick complete / undo) ----------
    if ($_POST['form_action'] === 'toggle_status') {
        $habit_id = (int)($_POST['habit_id'] ?? 0);
        $new_status = ($_POST['new_status'] ?? '') === 'Completed' ? 'Completed' : 'Pending';

        $stmt = $conn->prepare(
            "UPDATE habit_records SET completion_status = ?
             WHERE habit_id = ? AND user_id = ?"
        );
        $stmt->bind_param("sii", $new_status, $habit_id, $user_id);
        $stmt->execute();

        $ok = $new_status === 'Completed' ? 'completed' : 'reopened';
        header("Location: habit_tracker.php?success=" . $ok);
        exit();
    }

    // ---------- ADD or EDIT (shared validation) ----------
    if ($_POST['form_action'] === 'add' || $_POST['form_action'] === 'edit') {
        $habit_name        = trim($_POST['habit_name'] ?? '');
        $target_frequency  = trim($_POST['target_frequency'] ?? '');
        $completion_status = trim($_POST['completion_status'] ?? '');
        $habit_date        = trim($_POST['habit_date'] ?? '');
        $emoji             = trim($_POST['emoji'] ?? '');

        // Validation
        if ($habit_name === '' || strlen($habit_name) > 100) {
            $errors[] = "Please enter a valid habit name (max 100 characters).";
        }
        if (!in_array($target_frequency, $frequency_options, true)) {
            $errors[] = "Please select a valid target frequency.";
        }
        if (!in_array($completion_status, $status_options, true)) {
            $errors[] = "Please select a valid completion status.";
        }
        if (!in_array($emoji, $emoji_options, true)) {
            $errors[] = "Please select a valid emoji for the habit.";
        }
        $date_obj = DateTime::createFromFormat('Y-m-d', $habit_date);
        if (!$date_obj || $date_obj->format('Y-m-d') !== $habit_date) {
            $errors[] = "Please enter a valid date.";
        }

        if (empty($errors)) {
            if ($_POST['form_action'] === 'add') {
                $stmt = $conn->prepare(
                    "INSERT INTO habit_records (user_id, habit_name, target_frequency, completion_status, habit_date, emoji)
                     VALUES (?, ?, ?, ?, ?, ?)"
                );
                $stmt->bind_param("isssss", $user_id, $habit_name, $target_frequency, $completion_status, $habit_date, $emoji);

                if ($stmt->execute()) {
                    header("Location: habit_tracker.php?success=added");
                    exit();
                } else {
                    $errors[] = "Something went wrong while saving. Please try again.";
                }

            } else { // edit
                $habit_id = (int)($_POST['habit_id'] ?? 0);
                $stmt = $conn->prepare(
                    "UPDATE habit_records
                     SET habit_name = ?, target_frequency = ?, completion_status = ?, habit_date = ?, emoji = ?
                     WHERE habit_id = ? AND user_id = ?"
                );
                $stmt->bind_param("sssssii", $habit_name, $target_frequency, $completion_status, $habit_date, $emoji, $habit_id, $user_id);
                if ($stmt->execute()) {
                    header("Location: habit_tracker.php?success=updated");
                    exit();
                } else {
                    $errors[] = "Something went wrong while updating. Please try again.";
                }
            }
        }

        // If validation failed, re-show the form with entered values preserved
        $action = $_POST['form_action'];
        $form_values = [
            'habit_name'        => $habit_name,
            'target_frequency'  => $target_frequency,
            'completion_status' => $completion_status,
            'habit_date'        => $habit_date,
            'emoji'             => $emoji,
            'habit_id'          => $_POST['habit_id'] ?? null,
        ];
    }
}

// ---------------------------------------------------------
// Load record for Edit form (GET request, first load)
// ---------------------------------------------------------
$edit_record = null;
if ($action === 'edit' && !isset($form_values)) {
    $id = (int)($_GET['id'] ?? 0);
    $stmt = $conn->prepare("SELECT * FROM habit_records WHERE habit_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $edit_record = $result->fetch_assoc();

    if (!$edit_record) {
        header("Location: habit_tracker.php?success=notfound");
        exit();
    }
    $form_values = $edit_record;
}

// ---------------------------------------------------------
// Load list + simple stats (for 'list' view)
// ---------------------------------------------------------
$records = [];
$total_habits     = 0;
$total_completed  = 0;
$total_pending    = 0;

// Search & sort (list view only)
$search = trim($_GET['q'] ?? '');
$sort   = $_GET['sort'] ?? 'date_desc';

$allowed_sorts = [
    'date_desc' => 'habit_date DESC, habit_id DESC',
    'date_asc'  => 'habit_date ASC, habit_id ASC',
    'name_asc'  => 'habit_name ASC, habit_id DESC',
    'name_desc' => 'habit_name DESC, habit_id DESC',
];
$order_sql = $allowed_sorts[$sort] ?? $allowed_sorts['date_desc'];
if (!isset($allowed_sorts[$sort])) {
    $sort = 'date_desc';
}

if ($action === 'list') {
    if ($search !== '') {
        $like = '%' . $search . '%';
        $stmt = $conn->prepare(
            "SELECT * FROM habit_records
             WHERE user_id = ?
               AND (habit_name LIKE ? OR target_frequency LIKE ? OR completion_status LIKE ? OR habit_date LIKE ?)
             ORDER BY $order_sql"
        );
        $stmt->bind_param("issss", $user_id, $like, $like, $like, $like);
    } else {
        $stmt = $conn->prepare("SELECT * FROM habit_records WHERE user_id = ? ORDER BY $order_sql");
        $stmt->bind_param("i", $user_id);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $records[] = $row;
        $total_habits++;
        if ($row['completion_status'] === 'Completed') {
            $total_completed++;
        } else {
            $total_pending++;
        }
    }
}

$completion_rate = $total_habits ? round(($total_completed / $total_habits) * 100) : 0;

$success_messages = [
    'added'     => 'Habit record added successfully.',
    'updated'   => 'Habit record updated successfully.',
    'deleted'   => 'Habit record deleted.',
    'notfound'  => 'That record could not be found.',
    'completed' => 'Habit marked as completed.',
    'reopened'  => 'Habit marked as pending again.',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Habit Tracker - Student Routine Organizer</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<div class="app-layout">
  <?php include 'sidebar.php'; ?>
  <div class="main-content">
  <div class="container">

  <div class="page-header">
    <h1>✅ Habit Tracker</h1>
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
        <div class="stat-label">Total habits</div>
        <div class="stat-value"><?= $total_habits ?></div>
      </div>
      <div class="card stat-card">
        <div class="stat-label">Completed</div>
        <div class="stat-value"><?= $total_completed ?></div>
      </div>
      <div class="card stat-card">
        <div class="stat-label">Pending</div>
        <div class="stat-value"><?= $total_pending ?></div>
      </div>
      <div class="card stat-card">
        <div class="stat-label">Completion rate</div>
        <div class="stat-value"><?= $completion_rate ?>%</div>
      </div>
    </div>

    <!-- ===================== TABLE ===================== -->
    <div class="card">
      <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px; flex-wrap:wrap; gap:10px;">
        <strong>My habit records</strong>
        <a href="habit_tracker.php?action=add" class="btn btn-primary btn-sm">+ Add habit</a>
      </div>

      <!-- Search & Sort controls -->
      <form method="GET" action="habit_tracker.php" style="display:flex; flex-wrap:wrap; gap:10px; align-items:center; margin-bottom:16px;">
        <input type="hidden" name="action" value="list">
        <input type="search" name="q" class="form-control" style="width:220px; flex:1; min-width:160px;"
               placeholder="Search by keyword..." value="<?= htmlspecialchars($search) ?>">
        <select name="sort" class="form-control" style="width:auto; min-width:180px;">
          <option value="date_desc" <?= $sort === 'date_desc' ? 'selected' : '' ?>>Date: Latest first</option>
          <option value="date_asc"  <?= $sort === 'date_asc'  ? 'selected' : '' ?>>Date: Oldest first</option>
          <option value="name_asc"  <?= $sort === 'name_asc'  ? 'selected' : '' ?>>Name: A → Z</option>
          <option value="name_desc" <?= $sort === 'name_desc' ? 'selected' : '' ?>>Name: Z → A</option>
        </select>
        <button type="submit" class="btn btn-primary btn-sm">Apply</button>
        <?php if ($search !== '' || $sort !== 'date_desc'): ?>
          <a href="habit_tracker.php" class="btn btn-sm">Clear</a>
        <?php endif; ?>
      </form>

      <?php if (empty($records)): ?>
        <?php if ($search !== ''): ?>
          <p class="text-muted">No records match "<?= htmlspecialchars($search) ?>". Try a different keyword.</p>
        <?php else: ?>
          <p class="text-muted">No habit records yet. Click "Add habit" to start tracking your first habit.</p>
        <?php endif; ?>
      <?php else: ?>
        <table class="data-table">
          <thead>
            <tr>
              <th>Habit name</th>
              <th>Frequency</th>
              <th>Status</th>
              <th>Date</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($records as $r): ?>
              <tr>
                <td>
                  <span style="font-size:1.25rem; margin-right:6px; vertical-align:middle;"><?= htmlspecialchars($r['emoji'] ?? '✅') ?></span>
                  <span class="badge"><?= htmlspecialchars($r['habit_name']) ?></span>
                </td>
                <td><?= htmlspecialchars($r['target_frequency']) ?></td>
                <td>
                  <?php if ($r['completion_status'] === 'Completed'): ?>
                    <span style="color: var(--theme-success); font-weight: 500;">Completed</span>
                  <?php else: ?>
                    <span style="color: var(--theme-text-muted);">Pending</span>
                  <?php endif; ?>
                </td>
                <td><?= htmlspecialchars($r['habit_date']) ?></td>
                <td style="white-space:nowrap;">
                  <!-- Quick toggle: Mark done / Undo -->
                  <form method="POST" style="display:inline;">
                    <input type="hidden" name="form_action" value="toggle_status">
                    <input type="hidden" name="habit_id" value="<?= (int)$r['habit_id'] ?>">
                    <?php if ($r['completion_status'] === 'Pending'): ?>
                      <input type="hidden" name="new_status" value="Completed">
                      <button type="submit" class="btn btn-sm btn-primary" title="Mark as completed">✓ Done</button>
                    <?php else: ?>
                      <input type="hidden" name="new_status" value="Pending">
                      <button type="submit" class="btn btn-sm" title="Mark as pending again">Undo</button>
                    <?php endif; ?>
                  </form>
                  <a href="habit_tracker.php?action=edit&id=<?= (int)$r['habit_id'] ?>" class="btn btn-sm">Edit</a>
                  <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this habit record?');">
                    <input type="hidden" name="form_action" value="delete">
                    <input type="hidden" name="habit_id" value="<?= (int)$r['habit_id'] ?>">
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
      <h3 style="margin-top:0;"><?= $action === 'add' ? 'Add habit record' : 'Edit habit record' ?></h3>

      <form method="POST">
        <input type="hidden" name="form_action" value="<?= $action ?>">
        <?php if ($action === 'edit'): ?>
          <input type="hidden" name="habit_id" value="<?= (int)($form_values['habit_id']) ?>">
        <?php endif; ?>

        <div class="form-group">
          <label>Habit emoji</label>
          <div style="display:flex; flex-wrap:wrap; gap:8px;">
            <?php
              $selected_emoji = $form_values['emoji'] ?? '✅';
              foreach ($emoji_options as $em):
            ?>
              <label style="cursor:pointer; font-size:1.4rem; line-height:1; padding:6px 8px; border-radius:10px; border:2px solid <?= $selected_emoji === $em ? 'var(--theme-primary)' : 'var(--theme-border)' ?>; background:<?= $selected_emoji === $em ? 'var(--theme-primary-light)' : '#fff' ?>;">
                <input type="radio" name="emoji" value="<?= htmlspecialchars($em) ?>"
                       <?= $selected_emoji === $em ? 'checked' : '' ?>
                       style="display:none;" required>
                <?= $em ?>
              </label>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="form-group">
          <label>Habit name</label>
          <input type="text" name="habit_name" class="form-control" maxlength="100"
                 value="<?= htmlspecialchars($form_values['habit_name'] ?? '') ?>" required
                 placeholder="e.g. Drink 8 glasses of water">
        </div>

        <div class="form-group">
          <label>Target frequency</label>
          <select name="target_frequency" class="form-control" required>
            <option value="">-- Select --</option>
            <?php foreach ($frequency_options as $opt): ?>
              <option value="<?= $opt ?>" <?= (($form_values['target_frequency'] ?? '') === $opt) ? 'selected' : '' ?>>
                <?= $opt ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <label>Completion status</label>
          <select name="completion_status" class="form-control" required>
            <option value="">-- Select --</option>
            <?php foreach ($status_options as $opt): ?>
              <option value="<?= $opt ?>" <?= (($form_values['completion_status'] ?? '') === $opt) ? 'selected' : '' ?>>
                <?= $opt ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <label>Target Date</label>
          <input type="date" name="habit_date" class="form-control"
                 value="<?= htmlspecialchars($form_values['habit_date'] ?? '') ?>" required>
        </div>

        <button type="submit" class="btn btn-primary"><?= $action === 'add' ? 'Add habit' : 'Save changes' ?></button>
        <a href="habit_tracker.php" class="btn">Cancel</a>
      </form>
    </div>

    <script>
    // Highlight selected emoji on click
    document.querySelectorAll('input[name="emoji"]').forEach(function (radio) {
      radio.addEventListener('change', function () {
        document.querySelectorAll('input[name="emoji"]').forEach(function (r) {
          var label = r.parentElement;
          if (r.checked) {
            label.style.borderColor = 'var(--theme-primary)';
            label.style.background = 'var(--theme-primary-light)';
          } else {
            label.style.borderColor = 'var(--theme-border)';
            label.style.background = '#fff';
          }
        });
      });
    });
    </script>

  <?php endif; ?>

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
