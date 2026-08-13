<?php
require 'auth_guard.php';
require 'database.php';

$user_id = $_SESSION['user_id'];

// Get search, mood filter, and date range filter inputs
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$mood_filter = isset($_GET['mood_filter']) ? trim($_GET['mood_filter']) : '';
$date_range = isset($_GET['date_range']) ? trim($_GET['date_range']) : '';
$start_date = isset($_GET['start_date']) ? trim($_GET['start_date']) : '';
$end_date = isset($_GET['end_date']) ? trim($_GET['end_date']) : '';

// Build the SQL query dynamically using correct column names from your database schema
$sql = "SELECT journal_id, title, content, mood AS mood_status, entry_date AS date, created_at FROM journal_entries WHERE user_id = ?";
$params = [$user_id];
$types = "i";

if (!empty($search)) {
    $sql .= " AND (title LIKE ? OR content LIKE ?)";
    $search_param = "%" . $search . "%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ss";
}

if (!empty($mood_filter)) {
    $sql .= " AND mood = ?";
    $params[] = $mood_filter;
    $types .= "s";
}

// Append Date Preset Filters using the entry_date column
// Date Range logic including custom range support
if (!empty($date_range)) {
    $today = date('Y-m-d');
    switch ($date_range) {
        case 'today':
            $sql .= " AND entry_date = ?";
            $params[] = $today;
            $types .= "s";
            break;
        case 'yesterday':
            $yesterday = date('Y-m-d', strtotime('-1 day'));
            $sql .= " AND entry_date = ?";
            $params[] = $yesterday;
            $types .= "s";
            break;
        case 'last_7':
            $past_date = date('Y-m-d', strtotime('-7 days'));
            $sql .= " AND entry_date >= ?";
            $params[] = $past_date;
            $types .= "s";
            break;
        case 'last_30':
            $past_date = date('Y-m-d', strtotime('-30 days'));
            $sql .= " AND entry_date >= ?";
            $params[] = $past_date;
            $types .= "s";
            break;
        case 'last_90':
            $past_date = date('Y-m-d', strtotime('-90 days'));
            $sql .= " AND entry_date >= ?";
            $params[] = $past_date;
            $types .= "s";
            break;
        case 'last_year':
            $past_date = date('Y-m-d', strtotime('-1 year'));
            $sql .= " AND entry_date >= ?";
            $params[] = $past_date;
            $types .= "s";
            break;
        case 'custom':
            if (!empty($start_date) && !empty($end_date)) {
                $sql .= " AND entry_date BETWEEN ? AND ?";
                $params[] = $start_date;
                $params[] = $end_date;
                $types .= "ss";
            }
            break;
    }
}


$sql .= " ORDER BY entry_date DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

// Count total entries for the summary card
$count_stmt = $conn->prepare("SELECT COUNT(*) as total FROM journal_entries WHERE user_id = ?");
$count_stmt->bind_param("i", $user_id);
$count_stmt->execute();
$total_entries = $count_stmt->get_result()->fetch_assoc()['total'];
$count_stmt->close();

// Get the most frequent mood
$mood_stmt = $conn->prepare("SELECT mood AS mood_status, COUNT(*) as count FROM journal_entries WHERE user_id = ? GROUP BY mood ORDER BY count DESC LIMIT 1");
$mood_stmt->bind_param("i", $user_id);
$mood_stmt->execute();
$mood_result = $mood_stmt->get_result()->fetch_assoc();
$frequent_mood = $mood_result ? $mood_result['mood_status'] : 'None yet';
$mood_stmt->close();

// Fetch all entry dates safely in PHP
$activity_stmt = $conn->prepare("SELECT * FROM journal_entries WHERE user_id = ?");
$activity_stmt->bind_param("i", $user_id);
$activity_stmt->execute();
$activity_res = $activity_stmt->get_result();

$last_entry_date = null;
while ($row = $activity_res->fetch_assoc()) {
    $d = $row['entry_date'] ?? ($row['date'] ?? ($row['created_at'] ?? null));
    if ($d && ($last_entry_date === null || $d > $last_entry_date)) {
        $last_entry_date = $d;
    }
}
$activity_stmt->close();

// Determine status text based on last entry
if ($last_entry_date === date('Y-m-d')) {
    $module_status = "Updated Today ✨";
    $status_color = "#166534"; // Dark Green
    $status_bg = "#dcfce7";    // Soft Green
} elseif ($last_entry_date) {
    $module_status = "Last: " . date('M j, Y', strtotime($last_entry_date));
    $status_color = "#1e40af"; // Dark Blue
    $status_bg = "#e0f2fe";    // Soft Blue
} else {
    $module_status = "No Entries Yet 📝";
    $status_color = "#854d0e"; // Dark Yellow/Brown
    $status_bg = "#fef9c3";    // Soft Yellow
}

function getMoodColor($mood)
{
    switch (strtolower($mood)) {
        case 'happy':
            return ['bg' => '#22c55e', 'text' => '#ffffff']; // Solid Green
        case 'excited':
            return ['bg' => '#f59e0b', 'text' => '#ffffff']; // Solid Yellow/Amber
        case 'sad':
            return ['bg' => '#3b82f6', 'text' => '#ffffff']; // Solid Blue
        case 'anxious':
            return ['bg' => '#f97316', 'text' => '#ffffff']; // Solid Red
        case 'neutral':
            return ['bg' => '#64748b', 'text' => '#ffffff']; // Solid Slate Gray
        default:
            return ['bg' => '#0f172a', 'text' => '#ffffff']; // Default fallback
    }
}

$mood_colors = getMoodColor($frequent_mood);

function getMoodData($mood)
{
    switch (strtolower($mood)) {
        case 'happy':
            return ['bg' => '#22c55e', 'text' => '#ffffff', 'emoji' => '😊'];
        case 'excited':
            return ['bg' => '#f59e0b', 'text' => '#ffffff', 'emoji' => '😆'];
        case 'sad':
            return ['bg' => '#3b82f6', 'text' => '#ffffff', 'emoji' => '🥲'];
        case 'anxious':
            return ['bg' => '#f97316', 'text' => '#ffffff', 'emoji' => '😰'];
        case 'neutral':
            return ['bg' => '#64748b', 'text' => '#ffffff', 'emoji' => '😐'];
        default:
            return ['bg' => '#0f172a', 'text' => '#ffffff', 'emoji' => '📊'];
    }
}

$mood_data = getMoodData($frequent_mood);
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <title>Diary Journal Dashboard</title>
    <!-- Google Font: Manrope -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        :root {
            --primary-color: #1f4c4a;
            --primary-dark: #047857;
            --bg-color: #f8f8f8;
            --card-bg: #ffffff;
            --text-main: #1e293b;
            --text-muted: #0d0d0e;
            --border-color: #e2e8f0;
        }

        body {
            font-family: 'Manrope', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background-color: var(--bg-color);
            color: var(--text-main);
            margin: 0;
            padding: 0;
        }

        /* Unified container width for both Navbar and Main Content */
        .layout-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        /* Main container spacing */
        .container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }

        /* Top Navigation Bar */
        .navbar {
            background-color: var(--bg-color);
            border-bottom: 1px solid var(--border-color);
            padding: 40px 0;
        }

        /* Flex distribution happens here inside the layout container */
        .navbar-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .navbar-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: bold;
            font-size: 18px;
            color: var(--text-main);
        }

        .brand-logo {
            width: 24px;
            height: 24px;
            background: #10b981;
            border-radius: 6px;
        }

        /* Center Pill Container */
        .nav-pill-container {
            background-color: #f0f0f0;
            padding: 4px;
            /* Added slight padding back so pills look padded inside the track */
            border-radius: 50px;
            display: flex;
            gap: 4px;
            align-items: center;
        }

        .nav-pill-container a {
            text-decoration: none;
            color: var(--text-muted);
            font-size: 14px;
            font-weight: 500;
            padding: 8px 16px;
            /* Balanced padding */
            border-radius: 40px;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .nav-pill-container a.active {
            background-color: #22504e;
            color: white;
        }

        .nav-pill-container a:hover:not(.active) {
            color: var(--text-main);
            background-color: #e2e8f0;
        }

        /* Right Side Actions */
        .navbar-right {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .icon-btn {
            width: 38px;
            height: 38px;
            background-color: #f1f5f9;
            border: none;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            color: var(--text-main);
            transition: background 0.2s;
        }

        .icon-btn:hover {
            background-color: #e2e8f0;
        }

        .profile-pill {
            background-color: #f1f5f9;
            padding: 4px 12px 4px 4px;
            border-radius: 40px;
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
        }

        .profile-pill img {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            object-fit: cover;
        }

        /* Main Container */
        .container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }

        .header-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }

        .header-section h1 {
            margin: 0;
            font-size: 24px;
        }

        .btn-primary {
            background-color: var(--primary-color);
            border: none;
            color: white;
            padding: 10px 18px;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 600;
            transition: background 0.2s;
        }

        .btn-primary:hover {
            background-color: var(--primary-dark);
        }

        /* Styled Button with Icon Container */
        .btn-icon-wrapper {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px 8px 8px;
            font-size: 14px;
            box-shadow: none;
        }

        .icon-circle {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 22px;
            height: 22px;
            background-color: rgba(255, 255, 255, 0.8);
            border-radius: 50%;
            flex-shrink: 0;
            color: #000000;
        }

        /* Matching Reset Button Style */
        .btn-reset {
            padding: 8px 16px;
            font-size: 14px;
            border-radius: 50px;
            box-shadow: none;
        }

        /* Summary Cards */
        .cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .card {
            background-color: var(--card-bg);
            border-radius: 16px;
            padding: 20px;

        }

        .card.highlight {
            background: linear-gradient(135deg, #a1e870, #92dc5f);
            color: white;
            border: none;
        }

        .card h3 {
            margin: 0 0 10px 0;
            font-size: 14px;
            color: var(--text-muted);
        }

        .card.highlight h3 {
            color: var(--text-muted);
        }

        .card .metric {
            font-size: 28px;
            font-weight: bold;
            margin: 0;
            color: var(--text-muted);
        }

        /* Filter & Search Toolbar */
        .toolbar {
            background-color: var(--card-bg);
            border: none;
            border-radius: 16px;
            padding: 16px 20px;
            display: flex;
            gap: 12px;
            margin-bottom: 25px;
            align-items: center;
            flex-wrap: wrap;
        }

        /* Unified inputs styling */
        .toolbar input {
            background-color: #f8fafc;
            border: none;
            border-radius: 16px;
            padding: 10px 16px;
            outline: none;
            font-size: 14px;
            font-family: 'Manrope', sans-serif;
            color: var(--text-main);
            transition: all 0.2s;
            flex: 1;
            min-width: 200px;
        }

        .toolbar input:focus {
            border-color: #10b981;
            background-color: #ffffff;
        }

        /* Style the dropdown select box container */
        .toolbar select {
            background-color: #f8fafc;
            border: 1px solid var(--border-color);
            border-radius: 30px;
            padding: 10px 36px 10px 16px;
            outline: none;
            font-size: 14px;
            font-family: 'Manrope', sans-serif;
            color: var(--text-main);
            cursor: pointer;
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%2364748b' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 14px center;
            background-size: 14px;
            transition: all 0.2s;
        }

        .toolbar select:focus {
            border-color: #10b981;
            background-color: #ffffff;
        }

        /* Style the dropdown options list popup */
        .toolbar select option {
            background-color: #ffffff;
            color: #0f172a;
            padding: 12px 16px;
            font-family: 'Manrope', sans-serif;
        }

        .toolbar select option:hover,
        .toolbar select option:checked {
            background-color: #f1f5f9;
            color: #0f172a;
        }

        .btn-secondary {
            background-color: #f1f5f9;
            color: var(--text-main);
            border: none;
            padding: 8px 14px;
            border-radius: 50px;
            cursor: pointer;
            font-weight: 500;
        }

        .btn-secondary:hover {
            background-color: #e2e8f0;
        }

        /* Table Card Container */
        .table-card {
            background-color: var(--card-bg);

            border-radius: 16px;
            overflow: hidden;

        }

        table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
        }

        th {
            background-color: #f8fafc;
            color: var(--text-muted);
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: 15px 20px;
            border-bottom: 1px solid var(--border-color);
        }

        td {
            padding: 15px 20px;
            border-bottom: 1px solid var(--border-color);
            font-size: 14px;
        }

        tr:last-child td {
            border-bottom: none;
        }

        .mood-badge {
            background-color: #ecfdf5;
            color: var(--primary-dark);
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .actions a {
            text-decoration: none;
            font-weight: 600;
            margin-right: 10px;
        }

        .actions a.edit {
            color: #2563eb;
        }

        .actions a.delete {
            color: #dc2626;
        }

        .alert-success {
            background-color: #ecfdf5;
            color: var(--primary-dark);
            padding: 12px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #a7f3d0;
        }
    </style>
</head>

<body>
    <div class="app-layout">
        <?php include 'sidebar.php'; ?>
        <div class="main-content">

            <div class="container">

                <!-- Header Title & Action -->
                <div class="header-section">
                    <div>
                        <h1>Diary Journal Overview</h1>
                        <p style="color: var(--text-muted); margin: 5px 0 0 0;">Record and reflect on your daily
                            experiences
                            and
                            moods.</p>
                    </div>
                    <a href="add_journal.php" class="btn-primary"
                        style="display: inline-flex; align-items: center; gap: 10px; padding: 10px 18px 10px 10px;">
                        <span
                            style="display: inline-flex; align-items: center; justify-content: center; width: 26px; height: 26px; background-color: rgba(157, 232, 111); border-radius: 50%; flex-shrink: 0; color: #000000;">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="12" y1="5" x2="12" y2="19"></line>
                                <line x1="5" y1="12" x2="19" y2="12"></line>
                            </svg>
                        </span>
                        Add New Entry
                    </a>
                </div>

                <!-- Success Notifications -->
                <?php if (isset($_GET['success'])): ?>
                    <div class="alert-success">Journal entry added successfully!</div>
                <?php elseif (isset($_GET['updated'])): ?>
                    <div class="alert-success">Journal entry updated successfully!</div>
                <?php elseif (isset($_GET['deleted'])): ?>
                    <div class="alert-success">Journal entry deleted successfully!</div>
                <?php endif; ?>

                <!-- Summary Cards Row (3 Columns) -->
                <div class="cards-grid" style="grid-template-columns: repeat(3, 1fr);">
                    <div class="card highlight">
                        <h3>Total Journal Entries</h3>
                        <p class="metric"><?php echo $total_entries; ?></p>
                    </div>

                    <div class="card"
                        style="background-color: <?php echo $mood_data['bg']; ?>; transition: background-color 0.3s ease;">
                        <h3 style="color: <?php echo $mood_data['text']; ?>; opacity: 0.8;">Most Frequent Mood</h3>
                        <p class="metric"
                            style="color: <?php echo $mood_data['text']; ?>; margin-top: 5px; display: flex; align-items: center; gap: 10px;">
                            <span><?php echo $mood_data['emoji']; ?></span>
                            <span><?php echo htmlspecialchars($frequent_mood); ?></span>
                        </p>
                    </div>

                    <div class="card"
                        style="background-color: <?php echo $status_bg; ?>; transition: background-color 0.3s ease;">
                        <h3 style="color: <?php echo $status_color; ?>; opacity: 0.8;">Module Status</h3>
                        <p class="metric"
                            style="font-size: 20px; color: <?php echo $status_color; ?>; margin-top: 5px; font-weight: 700;">
                            <?php echo $module_status; ?>
                        </p>
                    </div>
                </div>

                <!-- Search and Filter Toolbar -->
                <form method="GET" action="" class="toolbar">
                    <input type="text" name="search" placeholder="Search by title or content..."
                        value="<?php echo htmlspecialchars($search); ?>">

                    <select name="mood_filter">
                        <option value="">All Moods</option>
                        <option value="Happy" <?php if ($mood_filter == 'Happy')
                            echo 'selected'; ?>>Happy</option>
                        <option value="Sad" <?php if ($mood_filter == 'Sad')
                            echo 'selected'; ?>>Sad</option>
                        <option value="Excited" <?php if ($mood_filter == 'Excited')
                            echo 'selected'; ?>>Excited
                        </option>
                        <option value="Anxious" <?php if ($mood_filter == 'Anxious')
                            echo 'selected'; ?>>Anxious
                        </option>
                        <option value="Neutral" <?php if ($mood_filter == 'Neutral')
                            echo 'selected'; ?>>Neutral
                        </option>
                    </select>

                    <!-- Date Range Preset Filter with Onchange Trigger -->
                    <select name="date_range" id="dateRangeSelect" onchange="toggleCustomDates(this.value)">
                        <option value="">All Time</option>
                        <option value="today" <?php if (isset($selected_date_range) && $selected_date_range == 'today')
                            echo 'selected'; ?>>Today</option>
                        <option value="yesterday" <?php if (isset($selected_date_range) && $selected_date_range == 'yesterday')
                            echo 'selected'; ?>>Yesterday</option>
                        <option value="last_7" <?php if (isset($selected_date_range) && $selected_date_range == 'last_7')
                            echo 'selected'; ?>>Last 7 Days</option>
                        <option value="last_30" <?php if (isset($selected_date_range) && $selected_date_range == 'last_30')
                            echo 'selected'; ?>>Last 30 Days</option>
                        <option value="last_90" <?php if (isset($selected_date_range) && $selected_date_range == 'last_90')
                            echo 'selected'; ?>>Last 90 Days</option>
                        <option value="last_year" <?php if (isset($selected_date_range) && $selected_date_range == 'last_year')
                            echo 'selected'; ?>>Last 1 Year</option>
                        <option value="custom" <?php if (isset($date_range) && $date_range == 'custom')
                            echo 'selected'; ?>>Custom Range</option>
                    </select>

                    <!-- Custom Date Inputs (Show/Hide dynamically) -->
                    <div id="custom-date-inputs"
                        style="display: <?php echo (isset($date_range) && $date_range == 'custom') ? 'flex' : 'none'; ?>; gap: 8px; align-items: center;">
                        <input type="date" name="start_date"
                            value="<?php echo htmlspecialchars($_GET['start_date'] ?? ''); ?>"
                            style="padding: 8px 12px; border-radius: 12px; border: 1px solid var(--border-color); background: #f8fafc; font-family: 'Manrope', sans-serif; font-size: 13px;">
                        <span style="color: var(--text-muted); font-size: 13px;">to</span>
                        <input type="date" name="end_date"
                            value="<?php echo htmlspecialchars($_GET['end_date'] ?? ''); ?>"
                            style="padding: 8px 12px; border-radius: 12px; border: 1px solid var(--border-color); background: #f8fafc; font-family: 'Manrope', sans-serif; font-size: 13px;">
                    </div>

                    <a href="diary_journal.php" style="text-decoration: none;">
                        <button type="button" class="btn-secondary btn-reset"
                            style="display: inline-flex; align-items: center; gap: 6px; cursor: pointer; border-radius: 30px;">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="18" y1="6" x2="6" y2="18"></line>
                                <line x1="6" y1="6" x2="18" y2="18"></line>
                            </svg>
                            Reset
                        </button>
                    </a>

                    <button type="submit" class="btn-primary btn-icon-wrapper" style="border: none; cursor: pointer;">
                        <span class="icon-circle">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon>
                            </svg>
                        </span>
                        Filter
                    </button>


                </form>

                <script>
                    function toggleCustomDates(value) {
                        const customDiv = document.getElementById('custom-date-inputs');
                        if (value === 'custom') {
                            customDiv.style.display = 'flex';
                        } else {
                            customDiv.style.display = 'none';
                        }
                    }
                </script>

                <!-- Main Data Table Card -->
                <div class="table-card">
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Title</th>
                                <th>Mood Status</th>
                                <th>Content Preview</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result->num_rows > 0): ?>
                                <?php while ($row = $result->fetch_assoc()):
                                    $mood_status = $row['mood_status'] ?? 'Neutral';
                                    $mood_info = getMoodData($mood_status);
                                    $entry_date = $row['entry_date'] ?? ($row['date'] ?? ($row['created_at'] ?? ''));
                                    $content_text = $row['content_preview'] ?? ($row['content'] ?? ($row['body'] ?? ''));

                                    // Safely find the correct primary key name from the row
                                    $entry_id = $row['id'] ?? ($row['journal_id'] ?? ($row['entry_id'] ?? 0));
                                    $edit_url = "edit_journal.php?id=" . $entry_id;
                                    $delete_url = "delete_journal.php?id=" . $entry_id;
                                    $row_unique_id = "journal-row-" . $entry_id;
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($entry_date); ?></td>
                                        <td style="font-weight: 600; color: var(--text-main);">
                                            <?php echo htmlspecialchars($row['title']); ?>
                                        </td>
                                        <td>
                                            <span
                                                style="display: inline-flex; align-items: center; gap: 6px; padding: 4px 12px; border-radius: 20px; background-color: <?php echo $mood_info['bg']; ?>; color: <?php echo $mood_info['text']; ?>; font-size: 13px; font-weight: 500;">
                                                <span><?php echo $mood_info['emoji']; ?></span>
                                                <span><?php echo htmlspecialchars($mood_status); ?></span>
                                            </span>
                                        </td>
                                        <td style="color: var(--text-muted);">
                                            <?php echo htmlspecialchars(substr($content_text, 0, 50)) . '...'; ?>
                                        </td>
                                        <td style="white-space: nowrap;">
                                            <!-- View / Toggle Icon -->
                                            <a href="#" onclick="toggleRow(event, '<?php echo $row_unique_id; ?>', this)"
                                                title="View Full Content"
                                                style="color: #0369a1; background: #e0f2fe; padding: 6px; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center; text-decoration: none; margin-right: 6px; transition: all 0.2s;">
                                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none"
                                                    stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                                    stroke-linejoin="round">
                                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                                    <circle cx="12" cy="12" r="3"></circle>
                                                </svg>
                                            </a>

                                            <!-- Edit Icon -->
                                            <a href="<?php echo $edit_url; ?>" title="Edit Entry"
                                                style="color: #1e40af; background: #dbeafe; padding: 6px; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center; text-decoration: none; margin-right: 6px; transition: all 0.2s;">
                                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none"
                                                    stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                                    stroke-linejoin="round">
                                                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                                </svg>
                                            </a>

                                            <!-- Delete Icon -->
                                            <a href="<?php echo $delete_url; ?>" title="Delete Entry"
                                                style="color: #991b1b; background: #fee2e2; padding: 6px; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center; text-decoration: none; transition: all 0.2s;"
                                                onclick="return confirm('Are you sure you want to delete this entry?');">
                                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none"
                                                    stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                                    stroke-linejoin="round">
                                                    <polyline points="3 6 5 6 21 6"></polyline>
                                                    <path
                                                        d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2">
                                                    </path>
                                                    <line x1="10" y1="11" x2="10" y2="17"></line>
                                                    <line x1="14" y1="11" x2="14" y2="17"></line>
                                                </svg>
                                            </a>
                                        </td>
                                    </tr>

                                    <!-- Hidden Expandable Row for Full Content -->
                                    <tr id="<?php echo $row_unique_id; ?>" style="display: none; background-color: #f8fafc;">
                                        <td colspan="5"
                                            style="padding: 20px 25px; color: var(--text-main); border-bottom: 1px solid var(--border-color);">
                                            <div
                                                style="font-size: 12px; font-weight: 700; text-transform: uppercase; color: var(--text-muted); margin-bottom: 6px; letter-spacing: 0.5px;">
                                                Full Reflection Content</div>
                                            <p style="margin: 0; line-height: 1.6; white-space: pre-line; font-size: 14px;">
                                                <?php echo htmlspecialchars($content_text); ?>
                                            </p>
                                        </td>
                                    </tr>

                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" style="text-align: center; color: var(--text-muted); padding: 40px;">No
                                        journal
                                        entries found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- JavaScript to handle dropdown expand/collapse toggle -->
                <script>
                    function toggleRow(event, rowId, element) {
                        event.preventDefault();
                        const row = document.getElementById(rowId);
                        if (row.style.display === 'none') {
                            row.style.display = 'table-row';
                            element.style.backgroundColor = '#bae6fd'; // Darker blue when active
                        } else {
                            row.style.display = 'none';
                            element.style.backgroundColor = '#e0f2fe'; // Original soft blue
                        }
                    }
                </script>
            </div>

        </div>

</body>

</html>