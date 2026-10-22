
<?php
require_once __DIR__ . '/backend/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: pages/login.php');
    exit;
}

$stmt = $pdo->prepare(
    'SELECT username, star_points
     FROM users
     WHERE id = ?'
);
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

$progressStmt = $pdo->prepare(
    'SELECT COUNT(*) AS solved_count
     FROM game_sessions
     WHERE user_id = ? AND is_solved = 1'
);
$progressStmt->execute([$_SESSION['user_id']]);
$progress = $progressStmt->fetch();

$solvedCount = (int) $progress['solved_count'];

if (!$user) {
    session_unset();
    session_destroy();
    header('Location: pages/login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Royal Dashboard | Banana Quest</title>
    <link rel="stylesheet" href="assets/css/common.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
</head>
<body class="dashboard-page">
    <main class="welcome">
        <p class="eyebrow">WELCOME TO YOUR KINGDOM</p>
        <h1>
            Hello, <?= htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8') ?>!
        </h1>
        
<div class="status">
    ✨ Your adventure is just beginning! ✨
</div>

<div class="stats">
    <div class="stat-card">
        <p class="stat-label">YOUR STAR POINTS</p>
        <h2><?= (int) $user['star_points'] ?> ✦</h2>
    </div>

    <div class="stat-card">
        <p class="stat-label">PUZZLES SOLVED</p>
        <h2><?= $solvedCount ?></h2>
    </div>

    <div class="stat-card">
        <p class="stat-label">NEXT MILESTONE</p>
        <h2>
            <?= 5 - ($solvedCount % 5) ?>
            <span class="stat-small">to go</span>
        </h2>
    </div>
</div>


<p class="subtitle">Your next chapter awaits, brave adventurer! ✨</p>

<div class="dashboard-actions">
    <a href="pages/adventure.php" class="quest-button">
        🗺️ Check Maps
    </a>

    <a href="pages/puzzle.php" class="quest-button">
        🍌 Play Puzzle
    </a>

    <a href="pages/logout.php" class="quest-button logout-button">
        🌙 Log Out
    </a>
</div>



<p>
    <a href="pages/adventure.php" class="quest-button">
        🗺️ Explore the Adventure Map
    </a>
</p>


<p>
    <a href="pages/puzzle.php" class="quest-button">
        Play Puzzle ✨
    </a>
</p>

<a href="pages/logout.php" class="quest-button">
    Log Out
</a>

    </main>
</body>
</html>
