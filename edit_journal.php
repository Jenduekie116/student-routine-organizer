<?php
require 'auth_guard.php';
require 'database.php';

$user_id = $_SESSION['user_id'];
$journal_id = $_GET['id'] ?? 0;

// 1. Fetch existing entry data when the page loads
$stmt = $conn->prepare("SELECT title, content, mood, entry_date FROM journal_entries WHERE journal_id = ? AND user_id = ?");
$stmt->bind_param("ii", $journal_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // If entry doesn't exist or doesn't belong to the user, redirect back
    header("Location: diary_journal.php?success=notfound");
    exit();
}

$row = $result->fetch_assoc();
$title = $row['title'];
$content = $row['content'];
$mood_status = $row['mood'];
$date = $row['entry_date'];
$stmt->close();

$error = '';

// 2. Handle form submission when updating the entry
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $title = trim($_POST['title']);
    $content = trim($_POST['content']);
    $mood_status = $_POST['mood_status'];
    $date = $_POST['date'];

    if (!empty($title) && !empty($content) && !empty($mood_status) && !empty($date)) {
        $update_stmt = $conn->prepare("UPDATE journal_entries SET title = ?, content = ?, mood = ?, entry_date = ? WHERE journal_id = ? AND user_id = ?");
        $update_stmt->bind_param("ssssii", $title, $content, $mood_status, $date, $journal_id, $user_id);

        if ($update_stmt->execute()) {
            header("Location: diary_journal.php?updated=1");
            exit();
        } else {
            $error = "Error updating entry.";
        }
        $update_stmt->close();
    } else {
        $error = "All fields are required.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Journal Entry - Routine Organizer</title>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        body { font-family: 'Manrope', sans-serif; }
        .form-group textarea { resize: vertical; min-height: 140px; }
    </style>
</head>
<body>
    <div class="app-layout">
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="container">
        <div class="card form-container">

            <div class="form-header" style="margin-bottom: 30px;">
                <h2 style="margin: 0 0 6px 0;">Edit Journal Entry</h2>
                <p class="text-muted" style="margin: 0;">Record your thoughts, experiences, and daily reflections.</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form action="" method="POST">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px; align-items: start;">

                    <div style="display: flex; flex-direction: column; gap: 20px;">
                        <div class="form-group" style="margin: 0;">
                            <label>Entry Date</label>
                            <input type="date" name="date" class="form-control" value="<?php echo htmlspecialchars($date ?? date('Y-m-d')); ?>" required>
                        </div>

                        <div class="form-group" style="margin: 0;">
                            <label>Entry Title</label>
                            <input type="text" name="title" class="form-control" placeholder="e.g., Productive Day" value="<?php echo htmlspecialchars($title ?? ''); ?>" required>
                        </div>

                        <div class="form-group" style="margin: 0;">
                            <label>Mood Status</label>
                            <select name="mood_status" class="form-control" required>
                                <option value="Happy" <?php if(isset($mood_status) && $mood_status == 'Happy') echo 'selected'; ?>>😊 Happy</option>
                                <option value="Excited" <?php if(isset($mood_status) && $mood_status == 'Excited') echo 'selected'; ?>>🎉 Excited</option>
                                <option value="Neutral" <?php if(isset($mood_status) && $mood_status == 'Neutral') echo 'selected'; ?>>😐 Neutral</option>
                                <option value="Sad" <?php if(isset($mood_status) && $mood_status == 'Sad') echo 'selected'; ?>>🌧️ Sad</option>
                                <option value="Anxious" <?php if(isset($mood_status) && $mood_status == 'Anxious') echo 'selected'; ?>>⚡ Anxious</option>
                            </select>
                        </div>
                    </div>

                    <div style="display: flex; flex-direction: column; height: 100%;">
                        <div class="form-group" style="margin: 0; display: flex; flex-direction: column; height: 100%;">
                            <label>Journal Content</label>
                            <textarea name="content" class="form-control" style="flex-grow:1;" placeholder="Write your thoughts here..." required><?php echo htmlspecialchars($content ?? ''); ?></textarea>
                        </div>
                    </div>

                </div>

                <div style="display: flex; gap: 12px; margin-top: 30px; border-top: 1px solid var(--theme-border); padding-top: 25px;">
                    <button type="submit" class="btn btn-primary">Save Entry</button>
                    <a href="diary_journal.php" class="btn">Cancel</a>
                </div>
            </form>

        </div>
    </div>
    </div>
</div>
</body>
</html>