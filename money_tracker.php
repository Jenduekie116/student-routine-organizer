<?php
require 'auth_guard.php';
require 'database.php';

$u_id = $_SESSION['user_id'];
$msg = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    $action = $_POST['action'];

    // --- CREATE OPERATION ---
    if ($action == 'add') {
        $type = $_POST['transaction_type'];
        $category = $_POST['category'];
        $description = trim($_POST['description']);
        $amount = $_POST['amount'];
        $date = $_POST['transaction_date'];

        if ($description == '' || $category == '' || $date == '') {
            $msg = "<div class='alert alert-error'>Error: All fields are required.</div>";
        } elseif (!is_numeric($amount) || floatval($amount) <= 0) {
            $msg = "<div class='alert alert-error'>Error: Amount must be greater than 0.</div>";
        } else {
            try {
                $stmt = $conn->prepare("INSERT INTO transactions (user_id, transaction_type, category, description, amount, transaction_date) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("isssds", $u_id, $type, $category, $description, $amount, $date);

                if ($stmt->execute()) {
                    $msg = "<div class='alert alert-success'>Transaction added successfully!</div>";
                } else {
                    throw new Exception("Database Insert Failed: " . $stmt->error);
                }
            } catch (Exception $e) {
                error_log("Transaction Insert Error: " . $e->getMessage());
                $msg = "<div class='alert alert-error'>System error: Could not save the transaction.</div>";
            }
        }
    }
    // --- UPDATE OPERATION ---
    elseif ($action == 'edit') {
        $t_id = $_POST['t_id'];
        $type = $_POST['transaction_type'];
        $category = $_POST['category'];
        $description = trim($_POST['description']);
        $amount = $_POST['amount'];
        $date = $_POST['transaction_date'];

        if ($description == '' || $category == '' || $date == '') {
            $msg = "<div class='alert alert-error'>Error: All fields are required.</div>";
        } elseif (!is_numeric($amount) || floatval($amount) <= 0) {
            $msg = "<div class='alert alert-error'>Error: Amount must be greater than 0.</div>";
        } else {
            try {
                $stmt = $conn->prepare("UPDATE transactions SET transaction_type=?, category=?, description=?, amount=?, transaction_date=? WHERE transaction_id=? AND user_id=?");
                $stmt->bind_param("sssdsii", $type, $category, $description, $amount, $date, $t_id, $u_id);

                if ($stmt->execute()) {
                    $msg = "<div class='alert alert-success'>Transaction updated successfully!</div>";
                } else {
                    throw new Exception("Database Update Failed");
                }
            } catch (Exception $e) {
                error_log("Transaction Update Error: " . $e->getMessage());
                $msg = "<div class='alert alert-error'>System error: Could not update the transaction.</div>";
            }
        }
    }

    // --- DELETE OPERATION (soft delete) ---
    elseif ($action == 'delete') {
        $t_id = $_POST['t_id'];
        $stmt = $conn->prepare("DELETE FROM transactions WHERE transaction_id=? AND user_id=?");
        $stmt->bind_param("ii", $t_id, $u_id);
        if ($stmt->execute()) {
            $msg = "<div class='alert alert-success'>Transaction deleted successfully!</div>";
        }
    }
}

// --- FILTERING AND SORTING ---
$filter_type = $_GET['search_type'] ?? '';
$filter_date = $_GET['search_date'] ?? '';

// FIX: whitelist is now enforced on the value actually used in the SQL query,
// not just on whether we write it to the cookie. Previously an arbitrary
// $_GET['sort_by'] value could reach the query string directly.
$allowed_sort_values = ['DESC', 'ASC'];

if (isset($_GET['sort_by']) && in_array($_GET['sort_by'], $allowed_sort_values, true)) {
    $sort_by = $_GET['sort_by'];
    setcookie("Money_Sort", $sort_by, time() + (86400 * 30), "/");
} elseif (isset($_COOKIE['Money_Sort']) && in_array($_COOKIE['Money_Sort'], $allowed_sort_values, true)) {
    $sort_by = $_COOKIE['Money_Sort'];
} else {
    $sort_by = 'DESC';
}

$sql = "SELECT * FROM transactions WHERE user_id = ?";
$params = [$u_id];
$types = "i";

if ($filter_type != '') {
    $sql .= " AND transaction_type = ?";
    $params[] = $filter_type;
    $types .= "s";
}
if ($filter_date != '') {
    $sql .= " AND transaction_date = ?";
    $params[] = $filter_date;
    $types .= "s";
}

$sql .= " ORDER BY transaction_date " . $sort_by; // safe now: $sort_by is guaranteed 'DESC' or 'ASC'
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$transactions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Calculate totals for Income and Expenses
$stmtTotal = $conn->prepare("
    SELECT
        SUM(CASE WHEN transaction_type = 'Income' THEN amount ELSE 0 END) AS total_income,
        SUM(CASE WHEN transaction_type = 'Expense' THEN amount ELSE 0 END) AS total_expense
    FROM transactions WHERE user_id = ?");
$stmtTotal->bind_param("i", $u_id);
$stmtTotal->execute();
$summary = $stmtTotal->get_result()->fetch_assoc();
$total_income = $summary['total_income'] ?? 0;
$total_expense = $summary['total_expense'] ?? 0;
$balance = $total_income - $total_expense;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Money Tracker</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .filter-bar { background: #f8fafc; padding: 15px; border-radius: 8px; margin-bottom: 20px; display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap; }
        .filter-bar>div { flex: 1; min-width: 150px; }
        .filter-bar input, .filter-bar select { padding: 8px; border: 1px solid #cbd5e0; border-radius: 4px; width: 100%; box-sizing: border-box; }
        .table-wrapper { overflow-x: auto; background: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05); }
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th, td { padding: 12px 15px; border-bottom: 1px solid #e2e8f0; }
        th { background: #edf2f7; color: #4a5568; font-weight: bold; text-transform: uppercase; font-size: 0.85rem; }
        .action-icon { cursor: pointer; padding: 5px; margin-right: 5px; font-size: 1.2rem; }
        .income { color: #38a169; font-weight: bold; }
        .expense { color: #e53e3e; font-weight: bold; }
    </style>
</head>
<body>
    <div class="app-layout">
        <?php include 'sidebar.php'; ?>
        <div class="main-content">
            <div class="container">
                <div class="page-header">
                    <h1>💰 My Money Tracker</h1>
                    <button class="btn btn-primary" onclick="openAddModal()">+ Add Transaction</button>
                </div>

                <div style="display: flex; gap: 20px; margin-bottom: 25px;">
                    <div class="card" style="flex: 1;">
                        <p class="text-muted" style="margin: 0 0 5px 0;">Total Income</p>
                        <h2 style="color: #38a169; margin: 0;">RM <?= number_format($total_income, 2) ?></h2>
                    </div>
                    <div class="card" style="flex: 1;">
                        <p class="text-muted" style="margin: 0 0 5px 0;">Total Expense</p>
                        <h2 style="color: #e53e3e; margin: 0;">RM <?= number_format($total_expense, 2) ?></h2>
                    </div>
                    <div class="card" style="flex: 1;">
                        <p class="text-muted" style="margin: 0 0 5px 0;">Net Balance</p>
                        <h2 style="color: <?= $balance >= 0 ? '#38a169' : '#e53e3e' ?>; margin: 0;">RM <?= number_format($balance, 2) ?></h2>
                    </div>
                </div>

                <?= $msg ?>

                <form method="GET" class="filter-bar">
                    <div><label>Type</label>
                        <select name="search_type">
                            <option value="">All</option>
                            <option value="Income" <?= $filter_type == 'Income' ? 'selected' : '' ?>>Income</option>
                            <option value="Expense" <?= $filter_type == 'Expense' ? 'selected' : '' ?>>Expense</option>
                        </select>
                    </div>
                    <div><label>Date</label><input type="date" name="search_date" value="<?= htmlspecialchars($filter_date) ?>"></div>
                    <div><label>Sort By</label>
                        <select name="sort_by">
                            <option value="DESC" <?= $sort_by == 'DESC' ? 'selected' : '' ?>>Newest First</option>
                            <option value="ASC" <?= $sort_by == 'ASC' ? 'selected' : '' ?>>Oldest First</option>
                        </select>
                    </div>
                    <div style="flex: 0; min-width: 180px;">
                        <label style="visibility: hidden; display: block;">Action</label>
                        <div style="display: flex; gap: 10px;">
                            <button type="submit" class="btn btn-primary" style="flex: 1;">Filter</button>
                            <a href="money_tracker.php" class="btn" style="flex: 1; text-align:center;">Clear</a>
                        </div>
                    </div>
                </form>

                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Type</th>
                                <th>Category</th>
                                <th>Description</th>
                                <th>Amount (RM)</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($transactions) > 0): ?>
                                <?php foreach ($transactions as $t):
                                    $safeDesc = htmlspecialchars(addslashes($t['description']));
                                    $safeCat = htmlspecialchars(addslashes($t['category']));
                                    $safeDate = htmlspecialchars($t['transaction_date']);
                                    $safeAmount = htmlspecialchars($t['amount']);
                                    $safeType = htmlspecialchars($t['transaction_type']);
                                ?>
                                    <tr>
                                        <td><?= date('d M Y', strtotime($t['transaction_date'])) ?></td>
                                        <td>
                                            <span class="<?= $t['transaction_type'] == 'Income' ? 'income' : 'expense' ?>">
                                                <?= htmlspecialchars($t['transaction_type']) ?>
                                            </span>
                                        </td>
                                        <td><?= htmlspecialchars($t['category']) ?></td>
                                        <td><?= htmlspecialchars($t['description']) ?></td>
                                        <td style="font-weight: bold;"><?= number_format($t['amount'], 2) ?></td>
                                        <td>
                                            <span class="action-icon" onclick="openEditModal(<?= $t['transaction_id'] ?>, '<?= $safeType ?>', '<?= $safeCat ?>', '<?= $safeDesc ?>', '<?= $safeAmount ?>', '<?= $safeDate ?>')" title="Edit">✏️</span>
                                            <span class="action-icon" onclick="confirmDelete(<?= $t['transaction_id'] ?>)" title="Delete">🗑️</span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="6" style="text-align: center; padding: 20px;">No transactions found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal for Add/Edit -->
    <div id="formModal" class="modal" style="display:none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5);">
        <div class="modal-content" style="background: white; margin: 10% auto; padding: 20px; width: 80%; max-width: 500px; border-radius: 8px;">
            <span onclick="closeModals()" style="float: right; cursor: pointer; font-size: 1.5rem;">&times;</span>
            <h2 id="modalTitle" style="color: #2b6cb0; margin-bottom: 20px;">Add Transaction</h2>
            <form method="POST" action="">
                <input type="hidden" id="formAction" name="action" value="add">
                <input type="hidden" id="t_id" name="t_id" value="">

                <div style="margin-bottom: 15px;">
                    <label style="display:block; margin-bottom:5px;">Transaction Type</label>
                    <select id="transaction_type" name="transaction_type" style="width:100%; padding:8px;" required>
                        <option value="Expense">Expense</option>
                        <option value="Income">Income</option>
                    </select>
                </div>

                <div style="margin-bottom: 15px;">
                    <label style="display:block; margin-bottom:5px;">Category</label>
                    <select id="category" name="category" style="width:100%; padding:8px;" required>
                        <option value="Food & Dining">Food & Dining</option>
                        <option value="Transportation">Transportation</option>
                        <option value="Education">Education</option>
                        <option value="Entertainment">Entertainment</option>
                        <option value="Salary/Allowance">Salary/Allowance</option>
                        <option value="Other">Other</option>
                    </select>
                </div>

                <div style="margin-bottom: 15px;">
                    <label style="display:block; margin-bottom:5px;">Description</label>
                    <input type="text" id="description" name="description" style="width:100%; padding:8px; box-sizing:border-box;" required>
                </div>

                <div style="margin-bottom: 15px;">
                    <label style="display:block; margin-bottom:5px;">Amount (RM)</label>
                    <input type="number" id="amount" name="amount" min="0.01" step="0.01" style="width:100%; padding:8px; box-sizing:border-box;" required>
                </div>

                <div style="margin-bottom: 15px;">
                    <label style="display:block; margin-bottom:5px;">Date</label>
                    <input type="date" id="transaction_date" name="transaction_date" style="width:100%; padding:8px; box-sizing:border-box;" required>
                </div>

                <button type="submit" id="submitBtn" style="width:100%; padding:10px; background:#2b6cb0; color:white; border:none; border-radius:4px; cursor:pointer;">Save Transaction</button>
            </form>
        </div>
    </div>

    <form id="deleteForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="t_id" id="delete_t_id">
    </form>

    <script>
        function closeModals() {
            document.getElementById('formModal').style.display = 'none';
        }

        function openAddModal() {
            document.getElementById('modalTitle').innerText = "Add New Transaction";
            document.getElementById('formAction').value = "add";
            document.getElementById('submitBtn').innerText = "Save Transaction";

            document.getElementById('t_id').value = "";
            document.getElementById('transaction_type').value = "Expense";
            document.getElementById('category').value = "Food & Dining";
            document.getElementById('description').value = "";
            document.getElementById('amount').value = "";
            document.getElementById('transaction_date').value = "";

            document.getElementById('formModal').style.display = 'block';
        }

        function openEditModal(id, type, category, description, amount, date) {
            document.getElementById('modalTitle').innerText = "Edit Transaction";
            document.getElementById('formAction').value = "edit";
            document.getElementById('submitBtn').innerText = "Update Transaction";

            document.getElementById('t_id').value = id;
            document.getElementById('transaction_type').value = type;
            document.getElementById('category').value = category;
            document.getElementById('description').value = description;
            document.getElementById('amount').value = amount;
            document.getElementById('transaction_date').value = date;

            document.getElementById('formModal').style.display = 'block';
        }

        function confirmDelete(id) {
            if (confirm("Are you sure you want to delete this transaction?")) {
                document.getElementById('delete_t_id').value = id;
                document.getElementById('deleteForm').submit();
            }
        }
    </script>
</body>
</html>

<?php
if (isset($conn)) {
    $conn->close();
}
?>