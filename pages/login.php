<?php
/**
 * Login page - uses AuthService (password_verify + session) and a CSRF token.
 *
 * AI assistance: structured/rewritten with Claude (Anthropic), Nov 2026 - see docs/AI-ACKNOWLEDGEMENTS.md.
 */

declare(strict_types=1);
require_once __DIR__ . '/../backend/config.php';

// Already logged in? Go straight to the dashboard.
if (!empty($_SESSION['user_id'])) {
    header('Location: ../dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid($_POST['csrf_token'] ?? null)) {
        $error = 'Invalid form submission. Please try again.';
    } else {
        $auth  = new AuthService($pdo);
        $error = $auth->login((string) ($_POST['username'] ?? ''), (string) ($_POST['password'] ?? ''));

        if ($error === null) {
            header('Location: ../dashboard.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enter the Kingdom | Banana Quest</title>
    <link rel="stylesheet" href="../assets/css/common.css">
    <link rel="stylesheet" href="../assets/css/login.css">
</head>
<body class="login-page">
    <main class="welcome">
        <p class="eyebrow">THE KINGDOM REMEMBERS YOU</p>
        <h1>Welcome Back</h1>
        <p class="subtitle">Your next adventure awaits, traveller.</p>

        <?php if ($error !== ''): ?>
            <p class="status"><?= e($error) ?></p>
        <?php endif; ?>

        <form method="POST" action="">
            <?= csrf_field() ?>
            <p>
                <input type="text" name="username" placeholder="Your username"
                       autocomplete="username" required>
            </p>
            <p>
                <input type="password" name="password" placeholder="Your password"
                       autocomplete="current-password" required>
            </p>
            <button type="submit" class="quest-button">Enter the Kingdom</button>
        </form>

        <p class="coming-soon">
            New to the kingdom? <a href="register.php">Create an account</a>
        </p>
    </main>
</body>
</html>
