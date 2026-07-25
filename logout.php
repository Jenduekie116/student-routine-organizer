<?php
// logout.php
session_start();

// Destroy all session memory
$_SESSION = array();
session_destroy();

// Redirect back to login page
header("Location: login.php?status=logged_out");
exit();
?>