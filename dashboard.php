<?php
/**
 * Player dashboard: balance, progress and navigation.
 *
 * AI assistance: structured/rewritten with Claude (Anthropic), Nov 2026 - see docs/AI-ACKNOWLEDGEMENTS.md.
 */

declare(strict_types=1);
require_once __DIR__ . '/backend/config.php';

$userId = require_login('pages/login.php');

$stmt = $pdo->prepare('SELECT username FROM users WHERE id = ?');
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) { // account was deleted while the session was still active
    (new AuthService($pdo))->logout();
    header('Location: pages/login.php');
    exit;
}

$game        = new GameService($pdo, new BananaApiService());
$points      = $game->getPoints($userId);
$solvedCount = $game->getSolvedCount($userId);
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
        <h1>Hello, <?= e($user['username']) ?>!</h1>

        <div class="status">✨ Your adventure is just beginning! ✨</div>

        <div class="stats">
            <div class="stat-card">
                <p class="stat-label">YOUR STAR POINTS</p>
                <h2><?= $points ?> ✦</h2>
            </div>
            <div class="stat-card">
                <p class="stat-label">PUZZLES SOLVED</p>
                <h2><?= $solvedCount ?></h2>
            </div>
            <div class="stat-card">
                <p class="stat-label">NEXT MILESTONE</p>
                <h2><?= 5 - ($solvedCount % 5) ?> <span class="stat-small">to go</span></h2>
            </div>
        </div>

        <p class="subtitle">Your next chapter awaits, brave adventurer! ✨</p>

        <div class="dashboard-actions">
            <a href="pages/adventure.php" class="quest-button">🗺️ Adventure Map</a>
            <a href="pages/puzzle.php" class="quest-button">🍌 Play Puzzle</a>
            <a href="pages/profile.php" class="quest-button">👑 My Profile</a>

            <form method="POST" action="pages/logout.php" class="inline-form">
                <?= csrf_field() ?>
                <button type="submit" class="quest-button logout-button">🌙 Log Out</button>
            </form>
        </div>
    </main>
</body>
</html>
