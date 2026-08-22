<?php
require 'auth_guard.php';
require 'database.php';
require 'journal_validation.php';

$title = '';
$content = '';
$mood_status = '';
$date = date('Y-m-d');
$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user_id = $_SESSION['user_id'];
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $mood_status = trim($_POST['mood_status'] ?? '');
    $date = trim($_POST['date'] ?? '');

    $errors = validateJournalEntry($title, $content, $mood_status, $date);

    if (empty($errors)) {
        $stmt = $conn->prepare("INSERT INTO journal_entries (user_id, title, content, mood, entry_date) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("issss", $user_id, $title, $content, $mood_status, $date);

        if ($stmt->execute()) {
            header("Location: diary_journal.php?success=1");
            exit();
        } else {
            // Log the real DB error for debugging; never show it to the user.
            error_log("Diary Journal insert failed: " . $stmt->error);
            $error = "Something went wrong while saving your entry. Please try again.";
        }
        $stmt->close();
    } else {
        $error = implode(' ', $errors);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Journal Entry - Routine Organizer</title>
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
                <h2 style="margin: 0 0 6px 0;">Add Journal Entry</h2>
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
                            <input type="date" name="date" class="form-control" value="<?php echo htmlspecialchars($date); ?>" required>
                        </div>

                        <div class="form-group" style="margin: 0;">
                            <label>Entry Title</label>
                            <input type="text" name="title" class="form-control" placeholder="e.g., Productive Day" value="<?php echo htmlspecialchars($title); ?>" required>
                        </div>

                        <div class="form-group" style="margin: 0;">
                            <label>Mood Status</label>
                            <select name="mood_status" class="form-control" required>
                                <option value="Happy" <?php if($mood_status == 'Happy') echo 'selected'; ?>>😊 Happy</option>
                                <option value="Excited" <?php if($mood_status == 'Excited') echo 'selected'; ?>>🎉 Excited</option>
                                <option value="Neutral" <?php if($mood_status == 'Neutral') echo 'selected'; ?>>😐 Neutral</option>
                                <option value="Sad" <?php if($mood_status == 'Sad') echo 'selected'; ?>>🌧️ Sad</option>
                                <option value="Anxious" <?php if($mood_status == 'Anxious') echo 'selected'; ?>>⚡ Anxious</option>
                            </select>
                        </div>
                    </div>

                    <div style="display: flex; flex-direction: column; height: 100%;">
                        <div class="form-group" style="margin: 0; display: flex; flex-direction: column; height: 100%;">
                            <label>Journal Content</label>
                            <textarea name="content" class="form-control" style="flex-grow:1;" placeholder="Write your thoughts here..." required><?php echo htmlspecialchars($content); ?></textarea>
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