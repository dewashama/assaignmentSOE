
<?php
session_start();

// Send logged-in users to their dashboard.
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

// Otherwise, start with the login page.
header('Location: login.php');
exit;
