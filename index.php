<?php

require 'auth_guard.php';
require 'database.php'; 

?>
<!DOCTYPE html>
<html lang="en">
<head>
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
        .module-icon {
            font-size: 3rem;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
<div class="container">
    <div style="margin-bottom: 30px; text-align: center;">
        <h1 style="color: var(--theme-blue);">Welcome back,👋</h1>
        <p class="text-muted">Select a module below to manage your daily routines.</p>
    </div>

    <!-- The 4 Modules Grid -->
    <div class="grid-4" style="margin-top: 40px;">
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