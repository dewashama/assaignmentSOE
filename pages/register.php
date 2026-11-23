<?php
/**
 * Registration page - validation and password hashing happen in AuthService.
 *
 * AI assistance: structured/rewritten with Claude (Anthropic), Nov 2026 - see docs/AI-ACKNOWLEDGEMENTS.md.
 */

declare(strict_types=1);
require_once __DIR__ . '/../backend/config.php';

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid($_POST['csrf_token'] ?? null)) {
        $error = 'Invalid form submission. Please try again.';
    } else {
        $auth  = new AuthService($pdo);
        $error = (string) $auth->register(
            (string) ($_POST['username'] ?? ''),
            (string) ($_POST['email'] ?? ''),
            (string) ($_POST['password'] ?? '')
        );

        if ($error === '') {
            $success = 'Account created! Your adventure awaits.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Join the Kingdom | Banana Quest</title>
    <link rel="stylesheet" href="../assets/css/common.css">
    <link rel="stylesheet" href="../assets/css/register.css">
</head>
<body class="register-page">
    <main class="welcome">
        <p class="eyebrow">YOUR ADVENTURE BEGINS</p>
        <h1>Join the Kingdom</h1>
        <p class="subtitle">
            Create your account and receive <?= AuthService::WELCOME_BONUS ?> Star Points.
        </p>

        <?php if ($error !== ''): ?>
            <p class="status"><?= e($error) ?></p>
        <?php endif; ?>

        <?php if ($success !== ''): ?>
            <p class="status"><?= e($success) ?></p>
            <p><a class="quest-button" href="login.php">Log in to begin</a></p>
        <?php else: ?>
            <form method="POST" action="">
                <?= csrf_field() ?>
                <p>
                    <input type="text" name="username" placeholder="Choose a username"
                           minlength="3" maxlength="50" autocomplete="username" required>
                </p>
                <p>
                    <input type="email" name="email" placeholder="Your email address"
                           autocomplete="email" required>
                </p>
                <p>
                    <input type="password" name="password" placeholder="Create a password (8+ characters)"
                           minlength="8" autocomplete="new-password" required>
                </p>
                <button type="submit" class="quest-button">Create My Account</button>
            </form>
        <?php endif; ?>

        <p class="coming-soon">
            Already a member? <a href="login.php">Log in</a>
        </p>
    </main>
</body>
</html>
