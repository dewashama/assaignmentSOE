<?php
/**
 * GameService - all game rules in one place (puzzles, hints, points, streaks).
 *
 * Pages and API endpoints stay thin: they check the request, call a method here
 * and return the result. Every method that changes points or puzzle state runs
 * in a database transaction and locks the puzzle row (SELECT ... FOR UPDATE), so
 * two simultaneous requests cannot double-award points or spend them twice.
 *
 * Methods return plain arrays: ['ok' => bool, 'message' => string, ...extra].
 * The puzzle solution is only ever returned by revealAnswer().
 *
 * AI assistance: structured/rewritten with Claude (Anthropic), Nov 2026 - see docs/AI-ACKNOWLEDGEMENTS.md.
 */

declare(strict_types=1);

final class GameService
{
    public const TOTAL_PUZZLES = 15;
    public const HINT_COST     = 10;
    public const REVEAL_COST   = 50;
    public const STREAK_BONUS  = 5;

    public const REWARDS         = ['easy' => 20, 'medium' => 30, 'hard' => 50];
    public const ATTEMPT_LIMITS  = ['easy' => 10, 'medium' => 5, 'hard' => 3];

    public function __construct(
        private PDO $pdo,
        private BananaApiService $banana
    ) {
    }

    // ---- Read-only queries ---------------------------------------------------

    public function getPoints(int $userId): int
    {
        $stmt = $this->pdo->prepare('SELECT star_points FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        return (int) $stmt->fetchColumn();
    }

    public function getSolvedCount(int $userId): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM game_sessions WHERE user_id = ? AND is_solved = 1'
        );
        $stmt->execute([$userId]);
        return min(self::TOTAL_PUZZLES, (int) $stmt->fetchColumn());
    }

    /**
     * Current puzzle state for the player, creating a new puzzle from the Banana
     * API when none is open. Never includes the solution.
     *
     * @return array{ok: bool, ...}
     */
    public function getCurrentState(int $userId): array
    {
        $solved = $this->getSolvedCount($userId);
        $base   = ['ok' => true, 'points' => $this->getPoints($userId),
                   'solved_count' => $solved, 'total' => self::TOTAL_PUZZLES];

        if ($solved >= self::TOTAL_PUZZLES) {
            return $base + ['finished' => true, 'puzzle' => null];
        }

        $stmt = $this->pdo->prepare(
            'SELECT * FROM game_sessions
             WHERE user_id = ? AND is_solved = 0 AND answer_revealed = 0 AND is_failed = 0
             ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute([$userId]);
        $row = $stmt->fetch();

        if (!$row) {
            try {
                $fetched = $this->banana->fetchPuzzle();
            } catch (RuntimeException $e) {
                return ['ok' => false, 'message' => $e->getMessage()];
            }
            $insert = $this->pdo->prepare(
                'INSERT INTO game_sessions (user_id, puzzle_api_id, puzzle_image_url, solution)
                 VALUES (?, ?, ?, ?)'
            );
            $insert->execute([$userId, 'banana-api', $fetched['image_url'], $fetched['solution']]);

            $stmt = $this->pdo->prepare('SELECT * FROM game_sessions WHERE id = ?');
            $stmt->execute([(int) $this->pdo->lastInsertId()]);
            $row = $stmt->fetch();
        }

        return $base + ['finished' => false, 'puzzle' => $this->publicView($row)];
    }

    // ---- Player actions ------------------------------------------------------

    public function setDifficulty(int $userId, int $puzzleId, string $difficulty): array
    {
        if (!isset(self::REWARDS[$difficulty])) {
            return $this->fail('Please select a valid difficulty.');
        }

        return $this->inTransaction(function () use ($userId, $puzzleId, $difficulty) {
            $row = $this->lockPuzzle($userId, $puzzleId);
            if ($early = $this->closedPuzzleResult($row)) {
                return $early;
            }
            if ((int) $row['attempts_used'] > 0 || (int) $row['hint_used'] === 1) {
                return $this->fail('Difficulty is locked once you start playing or use a hint.');
            }

            $this->pdo->prepare('UPDATE game_sessions SET difficulty = ? WHERE id = ?')
                ->execute([$difficulty, $puzzleId]);

            $row['difficulty'] = $difficulty;
            return $this->ok('Difficulty changed to ' . ucfirst($difficulty) . '! Good luck! 🍌', [
                'puzzle' => $this->publicView($row),
            ]);
        });
    }

    public function buyHint(int $userId, int $puzzleId): array
    {
        return $this->inTransaction(function () use ($userId, $puzzleId) {
            $row = $this->lockPuzzle($userId, $puzzleId);
            if ($early = $this->closedPuzzleResult($row)) {
                return $early;
            }

            if ((int) $row['hint_used'] === 1) {
                return $this->ok('You have already purchased this hint.', [
                    'puzzle' => $this->publicView($row),
                ]);
            }

            if (!$this->spendPoints($userId, self::HINT_COST, 'Hint for puzzle #' . $puzzleId)) {
                return $this->fail('You need ' . self::HINT_COST . ' Star Points to buy a hint.');
            }

            $this->pdo->prepare('UPDATE game_sessions SET hint_used = 1 WHERE id = ?')
                ->execute([$puzzleId]);
            $row['hint_used'] = 1;

            return $this->ok('Hint unlocked! ' . self::HINT_COST . ' Star Points used.', [
                'puzzle' => $this->publicView($row),
                'points' => $this->getPoints($userId),
            ]);
        });
    }

    public function revealAnswer(int $userId, int $puzzleId): array
    {
        return $this->inTransaction(function () use ($userId, $puzzleId) {
            $row = $this->lockPuzzle($userId, $puzzleId);
            if ($early = $this->closedPuzzleResult($row)) {
                return $early;
            }

            if (!$this->spendPoints($userId, self::REVEAL_COST, 'Revealed answer for puzzle #' . $puzzleId)) {
                return $this->fail('You need ' . self::REVEAL_COST . ' Star Points to reveal the answer.');
            }

            $this->pdo->prepare('UPDATE game_sessions SET answer_revealed = 1 WHERE id = ?')
                ->execute([$puzzleId]);

            return $this->ok('Answer revealed! No solving reward was given.', [
                'revealed_answer' => (string) $row['solution'],
                'points'          => $this->getPoints($userId),
                'puzzle_closed'   => true,
            ]);
        });
    }

    public function submitAnswer(int $userId, int $puzzleId, string $answer): array
    {
        $answer = trim($answer);
        if ($answer === '' || !preg_match('/^\d{1,10}$/', $answer)) {
            return $this->fail('Please enter a whole number.');
        }

        return $this->inTransaction(function () use ($userId, $puzzleId, $answer) {
            $row = $this->lockPuzzle($userId, $puzzleId);
            if ($early = $this->closedPuzzleResult($row)) {
                return $early;
            }

            $difficulty = $row['difficulty'];

            // Compare as integers so "07" and "7" are the same answer.
            if ((string) (int) $answer === (string) (int) $row['solution']) {
                return $this->handleCorrect($userId, $puzzleId, $difficulty);
            }
            return $this->handleWrong($puzzleId, $row, $difficulty);
        });
    }

    // ---- Private helpers -----------------------------------------------------

    private function handleCorrect(int $userId, int $puzzleId, string $difficulty): array
    {
        $this->pdo->prepare('UPDATE game_sessions SET is_solved = 1 WHERE id = ?')
            ->execute([$puzzleId]);

        $reward = self::REWARDS[$difficulty];
        $this->addPoints($userId, $reward, ucfirst($difficulty) . ' puzzle #' . $puzzleId);

        $message = "Correct! You earned $reward Star Points! 🌟";
        $bonus   = $this->updateStreak($userId);
        if ($bonus > 0) {
            $message .= ' Plus ' . $bonus . ' daily streak bonus points! 🔥';
        }

        return $this->ok($message, [
            'correct'      => true,
            'reward'       => $reward,
            'streak_bonus' => $bonus,
            'points'       => $this->getPoints($userId),
            'solved_count' => $this->getSolvedCount($userId),
            'puzzle_closed' => true,
        ]);
    }

    private function handleWrong(int $puzzleId, array $row, string $difficulty): array
    {
        $max      = self::ATTEMPT_LIMITS[$difficulty];
        $used     = (int) $row['attempts_used'] + 1;
        $failed   = $used >= $max ? 1 : 0;

        $this->pdo->prepare('UPDATE game_sessions SET attempts_used = ?, is_failed = ? WHERE id = ?')
            ->execute([$used, $failed, $puzzleId]);

        $left = max(0, $max - $used);
        if ($failed) {
            return $this->ok('Oh no! You ran out of attempts! You can try the next puzzle. 🍌', [
                'correct' => false, 'attempts_left' => 0, 'failed' => true, 'puzzle_closed' => true,
            ]);
        }
        return $this->ok("Not quite! You have $left attempts remaining.", [
            'correct' => false, 'attempts_left' => $left, 'failed' => false,
        ]);
    }

    /** Daily streak: +1 for consecutive days, reset otherwise. Returns bonus points given. */
    private function updateStreak(int $userId): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT current_streak, longest_streak, last_streak_date FROM users WHERE id = ? FOR UPDATE'
        );
        $stmt->execute([$userId]);
        $u = $stmt->fetch();

        $today = date('Y-m-d');
        if ($u['last_streak_date'] === $today) {
            return 0; // already counted today
        }

        $streak  = ($u['last_streak_date'] === date('Y-m-d', strtotime('-1 day')))
            ? (int) $u['current_streak'] + 1
            : 1;
        $longest = max((int) $u['longest_streak'], $streak);

        $this->pdo->prepare(
            'UPDATE users SET current_streak = ?, longest_streak = ?, last_streak_date = ? WHERE id = ?'
        )->execute([$streak, $longest, $today, $userId]);

        $this->addPoints($userId, self::STREAK_BONUS, 'Daily streak bonus');
        return self::STREAK_BONUS;
    }

    private function addPoints(int $userId, int $amount, string $reason): void
    {
        $this->pdo->prepare('UPDATE users SET star_points = star_points + ? WHERE id = ?')
            ->execute([$amount, $userId]);
        $this->pdo->prepare(
            'INSERT INTO point_transactions (user_id, points_change, reason) VALUES (?, ?, ?)'
        )->execute([$userId, $amount, $reason]);
    }

    /** Atomic "spend if enough balance"; returns false when the player cannot afford it. */
    private function spendPoints(int $userId, int $cost, string $reason): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE users SET star_points = star_points - ? WHERE id = ? AND star_points >= ?'
        );
        $stmt->execute([$cost, $userId, $cost]);
        if ($stmt->rowCount() !== 1) {
            return false;
        }
        $this->pdo->prepare(
            'INSERT INTO point_transactions (user_id, points_change, reason) VALUES (?, ?, ?)'
        )->execute([$userId, -$cost, $reason]);
        return true;
    }

    /** Fetch and lock one of the player's puzzles (must run inside a transaction). */
    private function lockPuzzle(int $userId, int $puzzleId): array|false
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM game_sessions WHERE id = ? AND user_id = ? FOR UPDATE'
        );
        $stmt->execute([$puzzleId, $userId]);
        return $stmt->fetch();
    }

    /** If the puzzle is missing or already finished, return the reply to send instead. */
    private function closedPuzzleResult(array|false $row): ?array
    {
        if (!$row) {
            return $this->fail('Puzzle not found.');
        }
        if ((int) $row['is_solved'] === 1) {
            return $this->fail('You have already solved this puzzle!');
        }
        if ((int) $row['is_failed'] === 1) {
            return $this->fail('You have already used all attempts for this puzzle.');
        }
        if ((int) $row['answer_revealed'] === 1) {
            return $this->fail('The answer for this puzzle was already revealed.');
        }
        return null;
    }

    /** Puzzle data that is safe to send to the browser (no solution). */
    private function publicView(array $row): array
    {
        $difficulty = $row['difficulty'];
        $max        = self::ATTEMPT_LIMITS[$difficulty];
        $used       = (int) $row['attempts_used'];
        $hintUsed   = (int) $row['hint_used'] === 1;

        return [
            'id'            => (int) $row['id'],
            'image_url'     => $row['puzzle_image_url'],
            'difficulty'    => $difficulty,
            'max_attempts'  => $max,
            'attempts_left' => max(0, $max - $used),
            'reward'        => self::REWARDS[$difficulty],
            'hint_used'     => $hintUsed,
            'hint_text'     => $hintUsed ? $this->hintText((int) $row['solution']) : null,
            'locked'        => $used > 0 || $hintUsed,
        ];
    }

    private function hintText(int $solution): string
    {
        return '🌿 Royal clue: The missing number is ' . ($solution % 2 === 0 ? 'EVEN.' : 'ODD.');
    }

    /** Run $work in a transaction; roll back and report a friendly error on failure. */
    private function inTransaction(callable $work): array
    {
        try {
            $this->pdo->beginTransaction();
            $result = $work();
            // Only failures of "expected" kind return ok=false; commit either way
            // because nothing was written in those branches.
            $this->pdo->commit();
            return $result;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log($e->getMessage());
            return $this->fail('Something went wrong. Please try again.');
        }
    }

    private function ok(string $message, array $extra = []): array
    {
        return ['ok' => true, 'message' => $message] + $extra;
    }

    private function fail(string $message): array
    {
        return ['ok' => false, 'message' => $message];
    }
}
