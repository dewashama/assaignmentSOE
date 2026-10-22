
<?php

// Start a session so we can remember the logged-in player.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database connection settings for local XAMPP.
$host = 'localhost';
$dbname = 'banana_quest';
$username = 'root';
$password = ''; // Default local XAMPP setup; yours may differ.

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );

} catch (PDOException $e) {
    // Log the technical error instead of showing database details.
    error_log($e->getMessage());

    http_response_code(500);
    exit('Unable to connect to the game database. Check your XAMPP settings.');
}
