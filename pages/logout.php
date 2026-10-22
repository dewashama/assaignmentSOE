
<?php
require_once __DIR__ . '/../backend/config.php';

$_SESSION = [];
session_destroy();

header('Location: login.php');
exit;
