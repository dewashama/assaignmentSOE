<?php
/**
 * Banana Quest - configuration and bootstrap.
 *
 * Every page and API endpoint includes this file first. It:
 *   1. loads database settings (config.local.php overrides the defaults),
 *   2. starts a hardened session (virtual identity),
 *   3. opens the PDO connection used by the services,
 *   4. loads shared helpers.
 *
 * NOTE: <?php must stay the very first thing in this file; any output before
 * session_start() breaks cookies and redirects.
 *
 * AI assistance: structured/rewritten with Claude (Anthropic), Nov 2026 - see docs/AI-ACKNOWLEDGEMENTS.md.
 */

declare(strict_types=1);

// ---- 1. Settings -----------------------------------------------------------
$config = [
    'db_host' => 'localhost',
    'db_port' => 3306,
    'db_name' => 'banana_quest',
    'db_user' => 'root',
    'db_pass' => '',   // default XAMPP; override in config.local.php
];

$localConfig = __DIR__ . '/config.local.php';
if (is_file($localConfig)) {
    $config = array_merge($config, (array) require $localConfig);
}

define('SESSION_IDLE_TIMEOUT', 30 * 60); // log out after 30 minutes of inactivity
date_default_timezone_set('Asia/Kathmandu');

// ---- 2. Session / cookie settings -------------------------------------------
if (session_status() === PHP_SESSION_NONE) {
    $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

    session_set_cookie_params([
        'lifetime' => 0,          // session cookie, removed when the browser closes
        'path'     => '/',
        'secure'   => $isHttps,   // only sent over HTTPS when HTTPS is used
        'httponly' => true,       // JavaScript cannot read the session cookie (XSS defence)
        'samesite' => 'Lax',      // not sent on cross-site POSTs (CSRF defence)
    ]);
    ini_set('session.use_strict_mode', '1'); // reject session IDs the server did not issue
    session_start();
}

// Expire idle sessions.
if (isset($_SESSION['user_id'])) {
    if (isset($_SESSION['last_activity']) &&
        time() - $_SESSION['last_activity'] > SESSION_IDLE_TIMEOUT) {
        $_SESSION = [];
        session_regenerate_id(true);
    } else {
        $_SESSION['last_activity'] = time();
    }
}

// ---- 3. Database -------------------------------------------------------------
try {
    $pdo = new PDO(
        "mysql:host={$config['db_host']};port={$config['db_port']};dbname={$config['db_name']};charset=utf8mb4",
        $config['db_user'],
        $config['db_pass'],
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    error_log($e->getMessage()); // technical detail goes to the log, not the browser
    http_response_code(500);
    exit('Unable to connect to the game database. Check backend/config.local.php.');
}

// ---- 4. Helpers and services -------------------------------------------------
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/services/AuthService.php';
require_once __DIR__ . '/services/BananaApiService.php';
require_once __DIR__ . '/services/GameService.php';
