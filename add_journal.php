<?php
require 'auth_guard.php';
require 'database.php';


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user_id = $_SESSION['user_id'];
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    // Use null coalescing operator to prevent warnings if the form field name differs (e.g., mood_status)
    $mood = trim($_POST['mood'] ?? ($_POST['mood_status'] ?? '')); 
    // Use null coalescing operator to prevent warnings if the form field name differs (e.g., date)
    $entry_date = $_POST['entry_date'] ?? ($_POST['date'] ?? ''); 

    if (!empty($title) && !empty($content) && !empty($mood) && !empty($entry_date)) {
        // Updated INSERT query to use `mood` and `entry_date` columns matching your database schema
        $stmt = $conn->prepare("INSERT INTO journal_entries (user_id, title, content, mood, entry_date) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("issss", $user_id, $title, $content, $mood, $entry_date);
        
        if ($stmt->execute()) {
            header("Location: diary_journal.php?success=1");
            exit();
        } else {
            $error = "Error adding entry. Please try again.";
        }
        $stmt->close();
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
    <title>Journal Entry - Routine Organizer</title>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        :root {
            --bg-color: #f8fafc;
            --card-bg: #ffffff;
            --border-color: #e2e8f0;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --primary-dark: #22504e;
        }

        body {
            font-family: 'Manrope', sans-serif;
            background-color: var(--bg-color);
            color: var(--text-main);
            margin: 0;
            padding: 0;
        }


        /* Form Card Container */
        .form-container {
            max-width: 700px;
            margin: 40px auto;
            background-color: var(--card-bg);
            border-radius: 16px;
            padding: 30px 40px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.02);
        }

        .form-header h2 {
            margin: 0 0 8px 0;
            font-size: 22px;
            color: var(--text-main);
        }

        .form-header p {
            margin: 0 0 24px 0;
            color: var(--text-muted);
            font-size: 14px;
        }

        /* Form Controls */
        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--text-main);
        }

        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            background-color: #f8fafc;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 12px 16px;
            outline: none;
            font-size: 14px;
            font-family: 'Manrope', sans-serif;
            color: var(--text-main);
            box-sizing: border-box;
            transition: all 0.2s;
        }

        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            border-color: #10b981;
            background-color: #ffffff;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 140px;
        }

        /* Buttons */
        .form-actions {
            display: flex;
            gap: 12px;
            margin-top: 30px;
        }

        .btn-primary {
            background-color: var(--primary-dark);
            color: white;
            border: none;
            border-radius: 30px;
            padding: 10px 20px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .btn-secondary {
            background-color: #f1f5f9;
            color: var(--text-main);
            border: none;
            border-radius: 30px;
            padding: 10px 20px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
    </style>
</head>
<body>
    <div class="app-layout">
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="container">
        <div class="form-container" style="background-color: var(--card-bg); border-radius: 16px; padding: 35px 40px; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
            
            <div class="form-header" style="margin-bottom: 30px;">
                <h2 style="margin: 0 0 6px 0; font-size: 22px; color: var(--text-main);">Edit Journal Entry</h2>
                <p style="margin: 0; color: var(--text-muted); font-size: 14px;">Record your thoughts, experiences, and daily reflections.</p>
            </div>

            <form action="" method="POST">
                <!-- 2-Column Grid Wrapper -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px; align-items: start;">
                    
                    <!-- Left Column: Date, Title, Mood -->
                    <div style="display: flex; flex-direction: column; gap: 20px;">
                        <div class="form-group" style="margin: 0;">
                            <label style="display: block; font-size: 14px; font-weight: 600; margin-bottom: 8px; color: var(--text-main);">Entry Date</label>
                            <input type="date" name="date" value="<?php echo htmlspecialchars($date ?? date('Y-m-d')); ?>" required style="width: 100%; background-color: #f8fafc; border: 1px solid var(--border-color); border-radius: 12px; padding: 12px 16px; outline: none; font-size: 14px; font-family: 'Manrope', sans-serif; color: var(--text-main); box-sizing: border-box;">
                        </div>

                        <div class="form-group" style="margin: 0;">
                            <label style="display: block; font-size: 14px; font-weight: 600; margin-bottom: 8px; color: var(--text-main);">Entry Title</label>
                            <input type="text" name="title" placeholder="e.g., Productive Day" value="<?php echo htmlspecialchars($title ?? ''); ?>" required style="width: 100%; background-color: #f8fafc; border: 1px solid var(--border-color); border-radius: 12px; padding: 12px 16px; outline: none; font-size: 14px; font-family: 'Manrope', sans-serif; color: var(--text-main); box-sizing: border-box;">
                        </div>

                        <div class="form-group" style="margin: 0;">
                            <label style="display: block; font-size: 14px; font-weight: 600; margin-bottom: 8px; color: var(--text-main);">Mood Status</label>
                            <select name="mood_status" required style="width: 100%; background-color: #f8fafc; border: 1px solid var(--border-color); border-radius: 12px; padding: 12px 16px; outline: none; font-size: 14px; font-family: 'Manrope', sans-serif; color: var(--text-main); box-sizing: border-box; cursor: pointer;">
                                <option value="Happy" <?php if(isset($mood_status) && $mood_status == 'Happy') echo 'selected'; ?>>😊 Happy</option>
                                <option value="Excited" <?php if(isset($mood_status) && $mood_status == 'Excited') echo 'selected'; ?>>🎉 Excited</option>
                                <option value="Neutral" <?php if(isset($mood_status) && $mood_status == 'Neutral') echo 'selected'; ?>>😐 Neutral</option>
                                <option value="Sad" <?php if(isset($mood_status) && $mood_status == 'Sad') echo 'selected'; ?>>🌧️ Sad</option>
                                <option value="Anxious" <?php if(isset($mood_status) && $mood_status == 'Anxious') echo 'selected'; ?>>⚡ Anxious</option>
                            </select>
                        </div>
                    </div>

                    <!-- Right Column: Journal Content (Taller Textarea) -->
                    <div style="display: flex; flex-direction: column; height: 100%;">
                        <div class="form-group" style="margin: 0; display: flex; flex-direction: column; height: 100%;">
                            <label style="display: block; font-size: 14px; font-weight: 600; margin-bottom: 8px; color: var(--text-main);">Journal Content</label>
                            <textarea name="content" placeholder="Write your thoughts here..." required style="width: 100%; flex-grow: 1; min-height: 235px; background-color: #f8fafc; border: 1px solid var(--border-color); border-radius: 12px; padding: 12px 16px; outline: none; font-size: 14px; font-family: 'Manrope', sans-serif; color: var(--text-main); box-sizing: border-box; resize: vertical;"><?php echo htmlspecialchars($content ?? ''); ?></textarea>
                        </div>
                    </div>

                </div>

                <!-- Form Action Buttons -->
                <div class="form-actions" style="display: flex; gap: 12px; margin-top: 30px; border-top: 1px solid var(--border-color); padding-top: 25px;">
                    <button type="submit" class="btn-primary" style="background-color: var(--primary-dark, #0f172a); color: white; border: none; border-radius: 30px; padding: 10px 22px; font-size: 14px; font-weight: 600; cursor: pointer;">Save Entry</button>
                    <a href="diary_journal.php" class="btn-secondary" style="background-color: #f1f5f9; color: var(--text-main); text-decoration: none; border-radius: 30px; padding: 10px 22px; font-size: 14px; font-weight: 600; display: inline-flex; align-items: center; justify-content: center;">Cancel</a>
                </div>
            </form>

        </div>
    </div>

</body>
</html>