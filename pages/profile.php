<?php

require_once __DIR__ . '/../backend/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];

date_default_timezone_set('Asia/Kathmandu');


// ==========================================
// GET PLAYER INFORMATION
// ==========================================

$stmt = $pdo->prepare(
    'SELECT id, username, email, star_points,
            created_at, current_streak,
            longest_streak, last_streak_date
     FROM users
     WHERE id = ?'
);

$stmt->execute([$userId]);

$user = $stmt->fetch();

if (!$user) {
    (new AuthService($pdo))->logout();
    header('Location: login.php');
    exit;
}


// ==========================================
// GET PUZZLE STATISTICS
// ==========================================

$stmt = $pdo->prepare(
    'SELECT
        COUNT(*) AS total_solved,
        SUM(CASE WHEN difficulty = "easy" THEN 1 ELSE 0 END) AS easy_solved,
        SUM(CASE WHEN difficulty = "medium" THEN 1 ELSE 0 END) AS medium_solved,
        SUM(CASE WHEN difficulty = "hard" THEN 1 ELSE 0 END) AS hard_solved
     FROM game_sessions
     WHERE user_id = ? AND is_solved = 1'
);

$stmt->execute([$userId]);

$stats = $stmt->fetch();

$totalSolved = (int) $stats['total_solved'];
$easySolved = (int) $stats['easy_solved'];
$mediumSolved = (int) $stats['medium_solved'];
$hardSolved = (int) $stats['hard_solved'];


// ==========================================
// CALCULATE CURRENT STREAK
// ==========================================

$currentStreak = (int) $user['current_streak'];
$longestStreak = (int) $user['longest_streak'];

$today = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime('-1 day'));

$lastStreakDate = $user['last_streak_date'];


// If the player missed yesterday and hasn't played today,
// their displayed current streak is zero.

if (
    $lastStreakDate !== $today &&
    $lastStreakDate !== $yesterday
) {
    $currentStreak = 0;
}


// ==========================================
// GET RECENT POINT TRANSACTIONS
// ==========================================

$stmt = $pdo->prepare(
    'SELECT points_change, reason, created_at
     FROM point_transactions
     WHERE user_id = ?
     ORDER BY id DESC
     LIMIT 5'
);

$stmt->execute([$userId]);

$recentTransactions = $stmt->fetchAll();


// ==========================================
// PLAYER ACHIEVEMENTS
// ==========================================

$achievements = [
    [
        'name' => 'First Steps',
        'description' => 'Solve your first puzzle.',
        'icon' => '🌱',
        'unlocked' => $totalSolved >= 1
    ],
    [
        'name' => 'Puzzle Explorer',
        'description' => 'Solve 5 puzzles.',
        'icon' => '🗺️',
        'unlocked' => $totalSolved >= 5
    ],
    [
        'name' => 'Royal Thinker',
        'description' => 'Solve 10 puzzles.',
        'icon' => '👑',
        'unlocked' => $totalSolved >= 10
    ],
    [
        'name' => 'Banana Champion',
        'description' => 'Complete all 15 puzzles.',
        'icon' => '🍌',
        'unlocked' => $totalSolved >= 15
    ],
    [
        'name' => 'Streak Starter',
        'description' => 'Reach a 3-day streak.',
        'icon' => '🔥',
        'unlocked' => $longestStreak >= 3
    ],
    [
        'name' => 'Streak Master',
        'description' => 'Reach a 7-day streak.',
        'icon' => '⭐',
        'unlocked' => $longestStreak >= 7
    ]
];

$unlockedCount = 0;

foreach ($achievements as $achievement) {
    if ($achievement['unlocked']) {
        $unlockedCount++;
    }
}

$memberSince = date(
    'F Y',
    strtotime($user['created_at'])
);

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>My Profile | Banana Quest</title>

    <link rel="stylesheet" href="../assets/css/common.css">

    <link rel="stylesheet" href="../assets/css/profile.css">

</head>


<body class="profile-page">

<main class="profile-container">


    <!-- PAGE HEADER -->

    <header class="profile-header">

        <a href="../dashboard.php" class="back-link">
            ← Back to Kingdom
        </a>

        <p class="eyebrow">🌼 YOUR ROYAL RECORD 🌼</p>

        <h1>My Adventurer Profile</h1>

        <p>
            Every brave adventurer has a story. Here's yours!
        </p>

    </header>


    <!-- PLAYER CARD -->

    <section class="player-card">

        <div class="avatar-circle">
            🍌
        </div>

        <div class="player-info">

            <p class="player-label">ROYAL ADVENTURER</p>

            <h2>
                <?= htmlspecialchars(
                    $user['username'],
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </h2>

            <p>
                <?= htmlspecialchars(
                    $user['email'],
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </p>

            <p class="member-since">
                🌿 Adventurer since <?= htmlspecialchars($memberSince) ?>
            </p>

        </div>

        <div class="player-points">

            <span>✨</span>

            <strong>
                <?= (int) $user['star_points'] ?>
            </strong>

            <small>Star Points</small>

        </div>

    </section>


    <!-- STREAK CARD -->

    <section class="streak-card">

        <div class="streak-icon">🔥</div>

        <div class="streak-content">

            <p class="streak-label">YOUR DAILY STREAK</p>

            <h2>
                <?= $currentStreak ?>
                <?= $currentStreak === 1 ? 'Day' : 'Days' ?>
            </h2>

            <p>

                <?php if ($lastStreakDate === $today): ?>

                    Amazing! You've completed a puzzle today.
                    Come back tomorrow to keep your streak alive! 🌟

                <?php elseif ($lastStreakDate === $yesterday): ?>

                    Your streak is waiting!
                    Complete a puzzle today to keep it going! 🍌

                <?php else: ?>

                    Your next adventure starts today.
                    Solve a puzzle to begin a new streak! 🌱

                <?php endif; ?>

            </p>

        </div>

        <div class="streak-record">

            <span>🏆</span>

            <strong><?= $longestStreak ?></strong>

            <small>Longest streak</small>

        </div>

    </section>


    <!-- STREAK REWARD INFORMATION -->

    <div class="streak-reward">

        ✨ Complete at least one puzzle every day to earn
        <strong>5 bonus Star Points</strong> and maintain your streak!

    </div>


    <!-- STATISTICS -->

    <section class="profile-section">

        <h2>📖 Your Adventure Statistics</h2>

        <div class="stats-grid">

            <div class="stat-card">

                <span>🧩</span>

                <strong><?= $totalSolved ?></strong>

                <small>Puzzles Solved</small>

            </div>


            <div class="stat-card">

                <span>🌱</span>

                <strong><?= $easySolved ?></strong>

                <small>Easy Puzzles</small>

            </div>


            <div class="stat-card">

                <span>🌟</span>

                <strong><?= $mediumSolved ?></strong>

                <small>Medium Puzzles</small>

            </div>


            <div class="stat-card">

                <span>👑</span>

                <strong><?= $hardSolved ?></strong>

                <small>Hard Puzzles</small>

            </div>

        </div>

    </section>


    <!-- ACHIEVEMENTS -->

    <section class="profile-section">

        <div class="section-heading">

            <h2>🏅 Achievement Collection</h2>

            <span>
                <?= $unlockedCount ?> / <?= count($achievements) ?> unlocked
            </span>

        </div>


        <div class="achievement-grid">

            <?php foreach ($achievements as $achievement): ?>

                <article class="achievement-card
                    <?= $achievement['unlocked'] ? 'unlocked' : 'locked' ?>">

                    <div class="achievement-icon">

                        <?= $achievement['icon'] ?>

                    </div>

                    <div>

                        <h3>
                            <?= htmlspecialchars($achievement['name']) ?>
                        </h3>

                        <p>
                            <?= htmlspecialchars($achievement['description']) ?>
                        </p>

                        <small>

                            <?= $achievement['unlocked']
                                ? '✨ Unlocked!'
                                : '🔒 Not unlocked yet' ?>

                        </small>

                    </div>

                </article>

            <?php endforeach; ?>

        </div>

    </section>


    <!-- RECENT POINT HISTORY -->

    <section class="profile-section">

        <h2>✨ Recent Star Point History</h2>

        <?php if (count($recentTransactions) > 0): ?>

            <div class="history-list">

                <?php foreach ($recentTransactions as $transaction): ?>

                    <div class="history-item">

                        <div class="history-description">

                            <strong>
                                <?= htmlspecialchars(
                                    $transaction['reason'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </strong>

                            <small>
                                <?= date(
                                    'd M Y, h:i A',
                                    strtotime($transaction['created_at'])
                                ) ?>
                            </small>

                        </div>

                        <strong class="<?= (int) $transaction['points_change'] >= 0
                            ? 'points-earned'
                            : 'points-spent' ?>">

                            <?= (int) $transaction['points_change'] > 0
                                ? '+'
                                : '' ?><?= (int) $transaction['points_change'] ?>
                            ⭐

                        </strong>

                    </div>

                <?php endforeach; ?>

            </div>

        <?php else: ?>

            <p class="empty-history">
                Your adventure history will appear here!
            </p>

        <?php endif; ?>

    </section>


    <!-- NAVIGATION -->

    <footer class="profile-footer">

        <a href="adventure.php" class="quest-button">
            🗺️ Adventure Map
        </a>

        <a href="puzzle.php" class="quest-button">
            🍌 Play Puzzle
        </a>

        <a href="../dashboard.php" class="back-link">
            Return to Kingdom
        </a>

    </footer>


</main>

</body>

</html>
