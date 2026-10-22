
<?php
require_once __DIR__ . '/backend/config.php';

$isLoggedIn = isset($_SESSION['user_id']);
$username = $_SESSION['username'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Banana Quest | A Royal Adventure</title>
    <link rel="stylesheet" href="assets/css/common.css">
    <link rel="stylesheet" href="assets/css/index.css">
</head>
<body class="home-page">
    <main class="welcome">
        <p class="eyebrow">THE SEVEN REALMS AWAIT</p>

        <h1>Banana Quest</h1>

        <p class="subtitle">
            A royal adventure. A kingdom of puzzles.
            One very ambitious banana prince.
        </p>

        <?php if ($isLoggedIn): ?>
            <p class="status">
                Welcome back,
                <?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?>!
            </p>

            <a href="dashboard.php" class="quest-button">
                Continue Your Adventure
            </a>

            <p class="coming-soon">
                Your kingdom is waiting for you.
            </p>
        <?php else: ?>
            <p class="status">
                ✦ Your royal adventure begins here ✦
            </p>

            <p>
                <a href="pages/login.php" class="quest-button">
                    Enter the Kingdom
                </a>
            </p>

            <p>
                <a href="pages/register.php">
                    Create an Account
                </a>
            </p>
        <?php endif; ?>
    </main>
</body>
</html>
