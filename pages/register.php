
<?php
require_once __DIR__ . '/../backend/config.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $email === '' || $password === '') {
        $error = 'Please fill in every field.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($username) < 3 || strlen($username) > 50) {
        $error = 'Username must be 3–50 characters long.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } else {
        $check = $pdo->prepare(
            'SELECT id FROM users WHERE username = ? OR email = ?'
        );
        $check->execute([$username, $email]);

        if ($check->fetch()) {
            $error = 'That username or email is already registered.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);

            $insert = $pdo->prepare(
                'INSERT INTO users (username, email, password_hash)
                 VALUES (?, ?, ?)'
            );
            $insert->execute([$username, $email, $hash]);

            $userId = (int) $pdo->lastInsertId();

            $points = $pdo->prepare(
                'INSERT INTO point_transactions
                 (user_id, points_change, reason)
                 VALUES (?, ?, ?)'
            );
            $points->execute([
                $userId,
                100,
                'Welcome bonus'
            ]);

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
    <link rel="stylesheet" href="assets/css/common.css">
    <link rel="stylesheet" href="../assets/css/register.css">
</head>
<body class="register-page">
    <main class="welcome">
        <p class="eyebrow">YOUR ADVENTURE BEGINS</p>
        <h1>Join the Kingdom</h1>
        <p class="subtitle">
            Create your account and receive 100 Star Points.
        </p>

        <?php if ($error !== ''): ?>
            <p class="status"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>

        <?php if ($success !== ''): ?>
            <p class="status"><?= htmlspecialchars($success) ?></p>
            <p><a href="../index.php">Return to homepage</a></p>
        <?php else: ?>
            <form method="POST" action="">
                <p>
                    <input
                        type="text"
                        name="username"
                        placeholder="Choose a username"
                        minlength="3"
                        maxlength="50"
                        required
                    >
                </p>
                <p>
                    <input
                        type="email"
                        name="email"
                        placeholder="Your email address"
                        required
                    >
                </p>
                <p>
                    <input
                        type="password"
                        name="password"
                        placeholder="Create a password (8+ characters)"
                        minlength="8"
                        required
                    >
                </p>
                <button type="submit" class="quest-button">
                    Create My Account
                </button>
            </form>
        <?php endif; ?>
    </main>
</body>
</html>
