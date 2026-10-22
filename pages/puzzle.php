
<?php
require_once __DIR__ . '/../backend/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];
$error = '';
$message = '';
$correct = false;
$revealedAnswer = null;
$hintText = '';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle puzzle actions.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    $action = (string) ($_POST['action'] ?? 'submit_answer');

    if (!is_string($token) ||
        !hash_equals($_SESSION['csrf_token'], $token)) {
        $error = 'Invalid form submission. Please try again.';
    } elseif (
        !isset($_POST['session_id']) ||
        !ctype_digit((string) $_POST['session_id'])
    ) {
        $error = 'Invalid puzzle. Please try again.';
    } else {
        $puzzleId = (int) $_POST['session_id'];

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare(
                'SELECT id, solution, is_solved, hint_used,
                        answer_revealed
                 FROM game_sessions
                 WHERE id = ? AND user_id = ?
                 FOR UPDATE'
            );
            $stmt->execute([$puzzleId, $userId]);
            $submittedPuzzle = $stmt->fetch();

            if (!$submittedPuzzle) {
                $error = 'Puzzle not found.';
            } elseif ((int) $submittedPuzzle['is_solved'] === 1) {
                $message = 'You have already solved this puzzle!';
            } elseif (
                (int) $submittedPuzzle['answer_revealed'] === 1
            ) {
                $revealedAnswer = (string) $submittedPuzzle['solution'];
                $message = 'The answer for this puzzle was already revealed.';
            } elseif ($action === 'get_hint') {

                if ((int) $submittedPuzzle['hint_used'] === 1) {
                    $number = (int) $submittedPuzzle['solution'];

                    $hintText = ($number % 2 === 0)
                        ? '🌿 Royal clue: The missing number is EVEN.'
                        : '🌿 Royal clue: The missing number is ODD.';

                    $message = 'You have already purchased this hint.';
                } else {
                    // Deduct 10 points only if the balance is sufficient.
                    $spend = $pdo->prepare(
                        'UPDATE users
                         SET star_points = star_points - 10
                         WHERE id = ? AND star_points >= 10'
                    );
                    $spend->execute([$userId]);

                    if ($spend->rowCount() !== 1) {
                        $error = 'You need 10 Star Points to buy a hint.';
                    } else {
                        $mark = $pdo->prepare(
                            'UPDATE game_sessions
                             SET hint_used = 1
                             WHERE id = ? AND user_id = ?
                               AND hint_used = 0
                               AND is_solved = 0
                               AND answer_revealed = 0'
                        );
                        $mark->execute([$puzzleId, $userId]);

                        if ($mark->rowCount() !== 1) {
                            throw new RuntimeException(
                                'Could not save hint status.'
                            );
                        }

                        $history = $pdo->prepare(
                            'INSERT INTO point_transactions
                             (user_id, points_change, reason)
                             VALUES (?, -10, ?)'
                        );
                        $history->execute([
                            $userId,
                            'Hint for puzzle #' . $puzzleId
                        ]);

                        $number = (int) $submittedPuzzle['solution'];

                        $hintText = ($number % 2 === 0)
                            ? '🌿 Royal clue: The missing number is EVEN.'
                            : '🌿 Royal clue: The missing number is ODD.';

                        $message = 'Hint unlocked! 10 Star Points used.';
                    }
                }

            } elseif ($action === 'reveal_answer') {

                // Deduct 50 points only if the player can afford it.
                $spend = $pdo->prepare(
                    'UPDATE users
                     SET star_points = star_points - 50
                     WHERE id = ? AND star_points >= 50'
                );
                $spend->execute([$userId]);

                if ($spend->rowCount() !== 1) {
                    $error = 'You need 50 Star Points to reveal the answer.';
                } else {
                    $mark = $pdo->prepare(
                        'UPDATE game_sessions
                         SET answer_revealed = 1
                         WHERE id = ? AND user_id = ?
                           AND is_solved = 0
                           AND answer_revealed = 0'
                    );
                    $mark->execute([$puzzleId, $userId]);

                    if ($mark->rowCount() !== 1) {
                        throw new RuntimeException(
                            'Could not save revealed answer status.'
                        );
                    }

                    $history = $pdo->prepare(
                        'INSERT INTO point_transactions
                         (user_id, points_change, reason)
                         VALUES (?, -50, ?)'
                    );
                    $history->execute([
                        $userId,
                        'Revealed answer for puzzle #' . $puzzleId
                    ]);

                    $revealedAnswer =
                        (string) $submittedPuzzle['solution'];

                    $message =
                        'Answer revealed! No solving reward was given.';
                }

            } elseif ($action === 'submit_answer') {

                $answer = trim((string) ($_POST['answer'] ?? ''));

                if ($answer === '' || strlen($answer) > 100) {
                    $error = 'Please enter your answer.';
                } elseif (
                    hash_equals(
                        (string) $submittedPuzzle['solution'],
                        $answer
                    )
                ) {
                    $update = $pdo->prepare(
                        'UPDATE game_sessions
                         SET is_solved = 1
                         WHERE id = ? AND user_id = ?
                           AND is_solved = 0
                           AND answer_revealed = 0'
                    );
                    $update->execute([$puzzleId, $userId]);

                    if ($update->rowCount() === 1) {
                        $reward = $pdo->prepare(
                            'UPDATE users
                             SET star_points = star_points + 20
                             WHERE id = ?'
                        );
                        $reward->execute([$userId]);

                        $history = $pdo->prepare(
                            'INSERT INTO point_transactions
                             (user_id, points_change, reason)
                             VALUES (?, 20, ?)'
                        );
                        $history->execute([
                            $userId,
                            'Solved puzzle #' . $puzzleId
                        ]);

                        $correct = true;
                        $message =
                            'Correct! You earned 20 Star Points!';
                    } else {
                        $message = 'This puzzle is already completed.';
                    }
                } else {
                    $message = 'Not quite! Try again.';
                }

            } else {
                $error = 'Unknown puzzle action.';
            }

            $pdo->commit();

        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log($e->getMessage());
            $error = 'Something went wrong. Please try again.';
        }
    }
}

// Count genuinely solved puzzles.
// Revealed answers do not count as solved.
$stmt = $pdo->prepare(
    'SELECT COUNT(*)
     FROM game_sessions
     WHERE user_id = ? AND is_solved = 1'
);
$stmt->execute([$userId]);
$solvedCount = min(15, (int) $stmt->fetchColumn());

// Load the current active puzzle.
$puzzle = false;

if ($solvedCount < 15) {
    $stmt = $pdo->prepare(
        'SELECT id, puzzle_image_url, is_solved,
                hint_used, answer_revealed, solution
         FROM game_sessions
         WHERE user_id = ?
           AND is_solved = 0
           AND answer_revealed = 0
         ORDER BY id DESC
         LIMIT 1'
    );
    $stmt->execute([$userId]);
    $puzzle = $stmt->fetch();

    if ($puzzle && (int) $puzzle['hint_used'] === 1) {
        $number = (int) $puzzle['solution'];

        $hintText = ($number % 2 === 0)
            ? '🌿 Royal clue: The missing number is EVEN.'
            : '🌿 Royal clue: The missing number is ODD.';
    }
}

// Request a new API puzzle if there is no active puzzle.
if (
    !$puzzle &&
    !$correct &&
    $message === '' &&
    $error === '' &&
    $revealedAnswer === null &&
    $solvedCount < 15
) {
    $apiUrl = 'https://marcconrad.com/uob/banana/api.php?out=json';
    $curl = curl_init($apiUrl);

    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_FOLLOWLOCATION => false
    ]);

    $response = curl_exec($curl);
    $httpStatus = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $curlError = curl_error($curl);
    curl_close($curl);

    if ($response === false || $httpStatus !== 200) {
        error_log('Banana API error: ' . $curlError);
        $error = 'The puzzle service is unavailable. Try again later.';
    } else {
        $data = json_decode($response, true);

        $imageUrl = is_array($data)
            ? ($data['question'] ?? '')
            : '';

        $solution = is_array($data)
            ? ($data['solution'] ?? null)
            : null;

        $imageHost = is_string($imageUrl)
            ? parse_url($imageUrl, PHP_URL_HOST)
            : null;

        if (
            !is_string($imageUrl) ||
            !filter_var($imageUrl, FILTER_VALIDATE_URL) ||
            parse_url($imageUrl, PHP_URL_SCHEME) !== 'https' ||
            $imageHost !== 'www.sanfoh.com' ||
            !is_scalar($solution) ||
            !preg_match('/^\d+$/', (string) $solution)
        ) {
            $error = 'The puzzle service returned an unexpected response.';
        } else {
            $insert = $pdo->prepare(
                'INSERT INTO game_sessions
                 (user_id, puzzle_api_id, puzzle_image_url, solution)
                 VALUES (?, ?, ?, ?)'
            );

            $insert->execute([
                $userId,
                'banana-api',
                $imageUrl,
                (string) $solution
            ]);

            $puzzle = [
                'id' => (int) $pdo->lastInsertId(),
                'puzzle_image_url' => $imageUrl,
                'is_solved' => 0,
                'hint_used' => 0,
                'answer_revealed' => 0
            ];
        }
    }
}

// Get the latest Star Points balance.
$stmt = $pdo->prepare(
    'SELECT star_points FROM users WHERE id = ?'
);
$stmt->execute([$userId]);
$player = $stmt->fetch();
$points = $player ? (int) $player['star_points'] : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Royal Puzzle | Banana Quest</title>

    <link rel="stylesheet" href="../assets/css/common.css">
    <link rel="stylesheet" href="../assets/css/puzzle.css">

    <style>
        .puzzle-actions {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 12px;
            margin: 18px 0;
        }

        .puzzle-actions form {
            margin: 0;
        }

        .hint-button,
        .reveal-button {
            border: 2px dashed rgba(115, 85, 48, .35);
            border-radius: 18px;
            padding: 12px 18px;
            font: inherit;
            font-weight: 700;
            cursor: pointer;
            box-shadow: 0 4px 0 rgba(90, 65, 35, .12);
            transition: transform .18s ease, filter .18s ease;
        }

        .hint-button {
            background: #fff0b8;
            color: #765018;
        }

        .reveal-button {
            background: #e9dcff;
            color: #573b87;
        }

        .hint-button:hover,
        .reveal-button:hover {
            transform: translateY(-2px);
            filter: brightness(1.03);
        }

        .hint-button:disabled {
            opacity: .6;
            cursor: not-allowed;
        }

        .hint-box,
        .revealed-box {
            margin: 16px auto;
            padding: 14px 18px;
            max-width: 520px;
            border-radius: 18px;
            border: 2px dashed #d6b96b;
            background: #fff8df;
            color: #654b20;
            font-weight: 700;
        }

        .revealed-box {
            background: #f0e8ff;
            border-color: #bba2e7;
            color: #573b87;
        }

        .action-note {
            font-size: .92rem;
            opacity: .85;
        }
    </style>
</head>

<body class="puzzle-page">
<main class="welcome">

    <p class="eyebrow">🌼 THE ROYAL PUZZLE CHAMBER 🌼</p>
    <h1>Solve the Puzzle!</h1>

    <p class="points-banner">
        ✨ Star Points: <?= $points ?> ✨
    </p>

    <p class="status">
        Adventure progress: <?= $solvedCount ?> / 15 puzzles
    </p>

    <?php if ($error !== ''): ?>
        <p class="status message-error">
            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
        </p>
    <?php endif; ?>

    <?php if ($message !== ''): ?>
        <p class="status <?= $correct ? 'message-success' : '' ?>">
            <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
        </p>
    <?php endif; ?>

    <?php if ($hintText !== ''): ?>
        <div class="hint-box">
            <?= htmlspecialchars($hintText, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <?php if ($revealedAnswer !== null): ?>

        <div class="revealed-box">
            🔮 The correct answer was
            <strong>
                <?= htmlspecialchars(
                    $revealedAnswer,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </strong>.
        </div>

        <p class="action-note">
            This puzzle does not count toward your 15 solved puzzles
            and does not earn a solving reward.
        </p>

        <a class="quest-button" href="puzzle.php">
            Try Another Puzzle 🍌
        </a>

    <?php elseif ($solvedCount >= 15): ?>

        <section class="grand-finale">
            <h2>👑 You completed Banana Quest!</h2>
            <p>
                You've solved all 15 puzzles and explored the
                Banana Kingdom, Whispering Woods, and Crystal Mountains!
            </p>
            <p>You're a true Royal Champion! 🌟</p>
        </section>

        <a class="quest-button" href="adventure.php">
            View My Adventure Map 🗺️
        </a>

    <?php elseif ($correct): ?>

        <p>Your next challenge awaits, brave adventurer!</p>

        <a class="quest-button" href="puzzle.php">
            Next Puzzle ✨
        </a>

    <?php elseif (
        $puzzle &&
        !(int) $puzzle['is_solved'] &&
        !(int) $puzzle['answer_revealed']
    ): ?>

        <p class="subtitle">
            Study the picture and find the missing number.
        </p>

        <div class="puzzle-frame">
            <img
                class="puzzle-image"
                src="<?= htmlspecialchars(
                    $puzzle['puzzle_image_url'],
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"
                alt="Banana number puzzle"
            >
        </div>

        <form method="POST">
            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars(
                    $_SESSION['csrf_token'],
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"
            >

            <input
                type="hidden"
                name="session_id"
                value="<?= (int) $puzzle['id'] ?>"
            >

            <p>What is the missing number? 🍌</p>

            <input
                type="number"
                name="answer"
                placeholder="Your magical answer..."
                required
            >

            <button
                type="submit"
                name="action"
                value="submit_answer"
                class="quest-button"
            >
                Submit Answer ✨
            </button>
        </form>

        <div class="puzzle-actions">

            <form method="POST">
                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= htmlspecialchars(
                        $_SESSION['csrf_token'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
                >

                <input
                    type="hidden"
                    name="session_id"
                    value="<?= (int) $puzzle['id'] ?>"
                >

                <button
                    type="submit"
                    name="action"
                    value="get_hint"
                    class="hint-button"
                    <?= (int) $puzzle['hint_used'] === 1
                        ? 'disabled'
                        : '' ?>
                >
                    🍃 Get a Hint — 10 ⭐
                </button>
            </form>

            <form
                method="POST"
                onsubmit="return confirm(
                    'Reveal the answer for 50 Star Points? ' +
                    'You will not earn the 20-point solving reward.'
                );"
            >
                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= htmlspecialchars(
                        $_SESSION['csrf_token'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
                >

                <input
                    type="hidden"
                    name="session_id"
                    value="<?= (int) $puzzle['id'] ?>"
                >

                <button
                    type="submit"
                    name="action"
                    value="reveal_answer"
                    class="reveal-button"
                >
                    🔮 Reveal Answer — 50 ⭐
                </button>
            </form>

        </div>

        <?php if ((int) $puzzle['hint_used'] === 1): ?>
            <p class="action-note">
                You've already purchased this puzzle's hint.
                It won't charge you again.
            </p>
        <?php else: ?>
            <p class="action-note">
                A hint costs 10 points. Revealing the answer costs
                50 points and skips the solving reward.
            </p>
        <?php endif; ?>

        <p class="coming-soon">
            A correct answer earns you 20 Star Points!
        </p>

    <?php endif; ?>

    <p>
        <a class="back-link" href="adventure.php">
            ← Back to Adventure Map
        </a>
    </p>

    <p>
        <a class="back-link" href="../dashboard.php">
            Back to Your Kingdom
        </a>
    </p>

</main>
</body>
</html>
