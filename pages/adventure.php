
<?php
require_once __DIR__ . '/../backend/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$stmt = $pdo->prepare(
    'SELECT username, star_points FROM users WHERE id = ?'
);
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user) {
    session_unset();
    session_destroy();
    header('Location: login.php');
    exit;
}

$progressStmt = $pdo->prepare(
    'SELECT COUNT(*) FROM game_sessions
     WHERE user_id = ? AND is_solved = 1'
);
$progressStmt->execute([$_SESSION['user_id']]);
$solvedCount = (int) $progressStmt->fetchColumn();

$realms = [
    [
        'name' => 'Banana Kingdom',
        'subtitle' => 'Where every great story begins',
        'description' => 'Visit the sunny kingdom, meet its cheerful residents, and begin your banana puzzle quest!',
        'image' => '../assets/img/banana-kingdom.webp',
        'emoji' => '🏰',
        'start' => 1,
        'end' => 5,
        'unlock' => 0,
        'color' => 'kingdom'
    ],
    [
        'name' => 'Whispering Woods',
        'subtitle' => 'A little forest full of secrets',
        'description' => 'Follow the winding woodland paths, discover hidden wonders, and solve the puzzles of the forest.',
        'image' => '../assets/img/whispering-woods.webp',
        'emoji' => '🌳',
        'start' => 6,
        'end' => 10,
        'unlock' => 5,
        'color' => 'woods'
    ],
    [
        'name' => 'Crystal Mountains',
        'subtitle' => 'A sparkling adventure awaits',
        'description' => 'Climb into a magical world of glittering crystals and snowy peaks to prove your puzzle-solving skills.',
        'image' => '../assets/img/crystal-mountains.webp',
        'emoji' => '💎',
        'start' => 11,
        'end' => 15,
        'unlock' => 10,
        'color' => 'mountains'
    ]
];

$currentRealm = 0;

foreach ($realms as $index => $realm) {
    if ($solvedCount >= $realm['unlock']) {
        $currentRealm = $index;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Adventure Map | Banana Quest</title>

    <link rel="stylesheet" href="../assets/css/common.css">
    <link rel="stylesheet" href="../assets/css/adventure.css">
</head>

<body class="adventure-page">

<header class="map-header">
    <a class="brand" href="../dashboard.php">
        🍌 <span>Banana Quest</span>
    </a>

    <nav class="map-nav">
        <a href="../dashboard.php">My Kingdom</a>
        <a href="puzzle.php">Play Puzzle ✨</a>
        <a href="logout.php">Log Out</a>
    </nav>
</header>

<main class="map-container">

    <section class="map-intro">
        <span class="intro-decoration">✦ &nbsp; ✧ &nbsp; ✦</span>

        <p class="eyebrow">YOUR STORY CONTINUES</p>

        <h1>The Adventure Map</h1>

        <p>
            Hello, <?= htmlspecialchars(
                $user['username'],
                ENT_QUOTES,
                'UTF-8'
            ) ?>!
            Choose a realm and let your next adventure begin.
        </p>

        <div class="progress-note">
            ⭐ <?= (int) $user['star_points'] ?> Star Points
            <span>•</span>
            🍌 <?= $solvedCount ?> puzzles solved
        </div>
    </section>

    <section class="realm-explorer" aria-label="Explore the realms">

        <div class="carousel-heading">
            <span>📖</span>
            <h2>Choose Your Next Chapter</h2>
            <span>🌼</span>
        </div>

        <div class="realm-carousel" id="realmCarousel">

            <?php foreach ($realms as $index => $realm):
                $locked = $solvedCount < $realm['unlock'];
                $completed = $solvedCount >= $realm['end'];
                $realmSolved = max(
                    0,
                    min(5, $solvedCount - $realm['unlock'])
                );
            ?>

            <article
                class="realm-card <?= htmlspecialchars($realm['color']) ?>
                <?= $locked ? 'locked' : '' ?>"
                data-realm="<?= $index ?>"
                aria-label="<?= htmlspecialchars($realm['name']) ?>"
            >

                <div class="realm-art">
                    <img
                        src="<?= htmlspecialchars($realm['image']) ?>"
                        alt="Illustrated map of <?= htmlspecialchars($realm['name']) ?>"
                        loading="<?= $index === $currentRealm ? 'eager' : 'lazy' ?>"
                    >

                    <?php if ($locked): ?>
                        <div class="locked-overlay">
                            <span class="lock-icon">🔒</span>
                            <strong>Still a Mystery!</strong>
                            <span>Solve <?= $realm['unlock'] ?> puzzles to unlock</span>
                        </div>
                    <?php endif; ?>

                    <span class="realm-number">
                        Chapter <?= $index + 1 ?>
                    </span>

                    <?php if ($completed): ?>
                        <span class="completed-stamp">✦ COMPLETED ✦</span>
                    <?php endif; ?>
                </div>

                <div class="realm-details">
                    <p class="realm-subtitle">
                        <?= htmlspecialchars($realm['subtitle']) ?>
                    </p>

                    <h3>
                        <?= $realm['emoji'] ?>
                        <?= htmlspecialchars($realm['name']) ?>
                    </h3>

                    <p class="realm-description">
                        <?= htmlspecialchars($realm['description']) ?>
                    </p>

                    <div class="stage-heading">
                        <span>Adventure progress</span>
                        <span><?= $realmSolved ?>/5 stages</span>
                    </div>

                    <div class="stage-track">
                        <div
                            class="stage-fill"
                            style="width: <?= $realmSolved * 20 ?>%"
                        ></div>
                    </div>

                    <div class="stage-dots" aria-label="Five stages">
                        <?php for ($stage = 0; $stage < 5; $stage++): ?>
                            <span class="<?= $stage < $realmSolved ? 'stage-done' : '' ?>">
                                <?= $stage < $realmSolved ? '★' : '✧' ?>
                            </span>
                        <?php endfor; ?>
                    </div>

                    <?php if ($locked): ?>
                        <button class="realm-button unavailable" disabled>
                            🔒 Realm Locked
                        </button>
                    <?php else: ?>
                        <a class="realm-button"
                           href="puzzle.php">
                            <?= $completed ? '↻ Play More Puzzles' : '✦ Enter This Realm' ?>
                        </a>
                    <?php endif; ?>
                </div>
            </article>

            <?php endforeach; ?>

        </div>

        <div class="carousel-controls">
            <button
                type="button"
                class="arrow-button"
                id="prevRealm"
                aria-label="Previous realm"
            >←</button>

            <div class="carousel-dots" id="carouselDots">
                <?php foreach ($realms as $index => $realm): ?>
                    <button
                        type="button"
                        class="carousel-dot <?= $index === $currentRealm ? 'active' : '' ?>"
                        data-slide="<?= $index ?>"
                        aria-label="Show <?= htmlspecialchars($realm['name']) ?>"
                    ></button>
                <?php endforeach; ?>
            </div>

            <button
                type="button"
                class="arrow-button"
                id="nextRealm"
                aria-label="Next realm"
            >→</button>
        </div>

        <p class="swipe-hint">☁️ Swipe or use the arrows to explore the map ☁️</p>
    </section>

    <a class="back-link" href="../dashboard.php">
        ← Return to Your Kingdom
    </a>

    <footer class="map-footer">
        Made with 🍌, magic, and a little bit of curiosity
    </footer>

</main>

<script>
const carousel = document.getElementById('realmCarousel');
const cards = Array.from(carousel.querySelectorAll('.realm-card'));
const dots = Array.from(document.querySelectorAll('.carousel-dot'));
const previous = document.getElementById('prevRealm');
const next = document.getElementById('nextRealm');

let activeIndex = <?= $currentRealm ?>;

function showRealm(index) {
    activeIndex = (index + cards.length) % cards.length;

    const card = cards[activeIndex];

    carousel.scrollTo({
        left: card.offsetLeft - cards[0].offsetLeft,
        behavior: 'smooth'
    });

    dots.forEach((dot, i) => {
        dot.classList.toggle('active', i === activeIndex);
    });
}

previous.addEventListener('click', () => {
    showRealm(activeIndex - 1);
});

next.addEventListener('click', () => {
    showRealm(activeIndex + 1);
});

dots.forEach((dot, index) => {
    dot.addEventListener('click', () => showRealm(index));
});

carousel.addEventListener('keydown', event => {
    if (event.key === 'ArrowLeft') showRealm(activeIndex - 1);
    if (event.key === 'ArrowRight') showRealm(activeIndex + 1);
});

// Update the selected dot when the player swipes the carousel.
let scrollTimeout;

carousel.addEventListener('scroll', () => {
    clearTimeout(scrollTimeout);

    scrollTimeout = setTimeout(() => {
        const carouselCenter =
            carousel.getBoundingClientRect().left +
            carousel.clientWidth / 2;

        let closestIndex = 0;
        let closestDistance = Infinity;

        cards.forEach((card, index) => {
            const rect = card.getBoundingClientRect();
            const cardCenter = rect.left + rect.width / 2;
            const distance = Math.abs(carouselCenter - cardCenter);

            if (distance < closestDistance) {
                closestDistance = distance;
                closestIndex = index;
            }
        });

        activeIndex = closestIndex;

        dots.forEach((dot, index) => {
            dot.classList.toggle('active', index === activeIndex);
        });
    }, 100);
});

window.addEventListener('load', () => showRealm(activeIndex));
</script>

</body>
</html>
