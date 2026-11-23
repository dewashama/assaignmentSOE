<?php
/**
 * AuthService - registration, login and logout (virtual identity).
 *
 * Design notes (discussed in the video):
 *  - Passwords are never stored: only password_hash() output (bcrypt by default,
 *    salted automatically) and verified with password_verify().
 *  - After login the session ID is regenerated to prevent session fixation.
 *  - The identity lives in a server-side session; the browser only holds the
 *    session cookie (httponly + samesite, set in config.php).
 *  - Login uses one generic error message so attackers cannot tell whether the
 *    username or the password was wrong.
 *  - A simple per-session throttle slows down password guessing. It is only a
 *    basic defence (a script can drop its cookie); a production system would
 *    throttle per IP/account in the database or a cache.
 *
 * AI assistance: structured/rewritten with Claude (Anthropic), Nov 2026 - see docs/AI-ACKNOWLEDGEMENTS.md.
 */

declare(strict_types=1);

final class AuthService
{
    private const MAX_FAILED_LOGINS = 5;
    private const LOCKOUT_SECONDS   = 60;
    public  const WELCOME_BONUS     = 100;

    public function __construct(private PDO $pdo)
    {
    }

    /** @return string|null error message, or null on success */
    public function register(string $username, string $email, string $password): ?string
    {
        $username = trim($username);
        $email    = trim($email);

        if ($username === '' || $email === '' || $password === '') {
            return 'Please fill in every field.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return 'Please enter a valid email address.';
        }
        if (!preg_match('/^.{3,50}$/u', $username)) { // counts characters, no mbstring needed
            return 'Username must be 3-50 characters long.';
        }
        if (strlen($password) < 8) {
            return 'Password must be at least 8 characters long.';
        }

        $check = $this->pdo->prepare('SELECT id FROM users WHERE username = ? OR email = ?');
        $check->execute([$username, $email]);
        if ($check->fetch()) {
            return 'That username or email is already registered.';
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);

        try {
            $this->pdo->beginTransaction();

            // The welcome bonus is stored on the balance AND in the ledger.
            $insert = $this->pdo->prepare(
                'INSERT INTO users (username, email, password_hash, star_points)
                 VALUES (?, ?, ?, ?)'
            );
            $insert->execute([$username, $email, $hash, self::WELCOME_BONUS]);
            $userId = (int) $this->pdo->lastInsertId();

            $ledger = $this->pdo->prepare(
                'INSERT INTO point_transactions (user_id, points_change, reason) VALUES (?, ?, ?)'
            );
            $ledger->execute([$userId, self::WELCOME_BONUS, 'Welcome bonus']);

            $this->pdo->commit();
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log($e->getMessage());
            // 23000 = unique constraint hit by a simultaneous registration.
            return $e->getCode() === '23000'
                ? 'That username or email is already registered.'
                : 'Could not create the account. Please try again.';
        }

        return null;
    }

    /** @return string|null error message, or null on success */
    public function login(string $username, string $password): ?string
    {
        $username = trim($username);

        if ($username === '' || $password === '') {
            return 'Please enter your username and password.';
        }

        // Basic brute-force throttle.
        $lockedUntil = (int) ($_SESSION['login_locked_until'] ?? 0);
        if ($lockedUntil > time()) {
            return 'Too many attempts. Please wait a minute and try again.';
        }

        $stmt = $this->pdo->prepare(
            'SELECT id, username, password_hash FROM users WHERE username = ?'
        );
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            $_SESSION['login_failures'] = (int) ($_SESSION['login_failures'] ?? 0) + 1;
            if ($_SESSION['login_failures'] >= self::MAX_FAILED_LOGINS) {
                $_SESSION['login_locked_until'] = time() + self::LOCKOUT_SECONDS;
                $_SESSION['login_failures']     = 0;
            }
            return 'Incorrect username or password.';
        }

        // Upgrade the stored hash if PHP's default algorithm/cost has improved.
        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            $this->pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
                ->execute([password_hash($password, PASSWORD_DEFAULT), $user['id']]);
        }

        session_regenerate_id(true); // new session ID after privilege change
        unset($_SESSION['login_failures'], $_SESSION['login_locked_until']);
        $_SESSION['user_id']       = (int) $user['id'];
        $_SESSION['username']      = $user['username'];
        $_SESSION['last_activity'] = time();

        return null;
    }

    /** Destroy the session and expire the browser's session cookie. */
    public function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires'  => time() - 42000,
                'path'     => $p['path'],
                'domain'   => $p['domain'],
                'secure'   => $p['secure'],
                'httponly' => $p['httponly'],
                'samesite' => $p['samesite'] ?: 'Lax',
            ]);
        }
        session_destroy();
    }
}
