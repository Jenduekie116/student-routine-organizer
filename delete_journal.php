<?php
require 'auth_guard.php';   // guarantees $_SESSION['user_id'] is set before this point
require 'database.php';

$user_id = $_SESSION['user_id'];
$entry_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($entry_id > 0) {
    $stmt = $conn->prepare("DELETE FROM journal_entries WHERE journal_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $entry_id, $user_id);

    if ($stmt->execute()) {
        header("Location: diary_journal.php?deleted=1");
        exit();
    } else {
        echo "Error deleting entry.";
    }
    $stmt->close();
} else {
    header("Location: diary_journal.php");
    exit();
}
?>