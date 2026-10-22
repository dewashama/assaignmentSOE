
<?php
require_once __DIR__ . '/../backend/config.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Please enter your username and password.';
    } else {
        $stmt = $pdo->prepare(
            'SELECT id, username, password_hash
             FROM users
             WHERE username = ?'
        );
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            session_regenerate_id(true);

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];

            header('Location: ../dashboard.php');
            exit;
        } else {
            $error = 'Incorrect username or password.';
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
            <p class="status">
                <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
            </p>
        <?php endif; ?>

        <form method="POST" action="">
            <p>
                <input
                    type="text"
                    name="username"
                    placeholder="Your username"
                    autocomplete="username"
                    required
                >
            </p>
            <p>
                <input
                    type="password"
                    name="password"
                    placeholder="Your password"
                    autocomplete="current-password"
                    required
                >
            </p>
            <button type="submit" class="quest-button">
                Enter the Kingdom
            </button>
        </form>

        <p class="coming-soon">
            New to the kingdom?
            <a href="register.php">Create an account</a>
        </p>
    </main>
</body>
</html>
