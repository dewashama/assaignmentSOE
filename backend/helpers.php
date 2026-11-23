<?php
/**
 * Small shared helpers: output escaping, CSRF protection, login guards, JSON replies.
 *
 * AI assistance: structured/rewritten with Claude (Anthropic), Nov 2026 - see docs/AI-ACKNOWLEDGEMENTS.md.
 */

declare(strict_types=1);

/** Escape text for safe output inside HTML (XSS defence). */
function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/** Return the session's CSRF token, creating it on first use. */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Constant-time comparison of a submitted token with the session token. */
function csrf_valid(?string $token): bool
{
    return is_string($token)
        && !empty($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

/** Hidden form field containing the CSRF token. */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/** Page guard: send anonymous visitors to the login page. */
function require_login(string $loginUrl = 'login.php'): int
{
    if (empty($_SESSION['user_id'])) {
        header('Location: ' . $loginUrl);
        exit;
    }
    return (int) $_SESSION['user_id'];
}

/** Send a JSON response and stop. */
function json_response(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * API guard: requires POST, a logged-in user and a valid CSRF token
 * (sent by game.js in the X-CSRF-Token header). Returns the user id.
 */
function api_require_post_user(): int
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        header('Allow: POST');
        json_response(['ok' => false, 'message' => 'Method not allowed.'], 405);
    }
    if (empty($_SESSION['user_id'])) {
        json_response(['ok' => false, 'message' => 'Please log in again.'], 401);
    }
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? '');
    if (!csrf_valid($token)) {
        json_response(['ok' => false, 'message' => 'Invalid request. Please refresh the page.'], 403);
    }
    return (int) $_SESSION['user_id'];
}

/** API guard for read-only GET endpoints. */
function api_require_get_user(): int
{
    if (empty($_SESSION['user_id'])) {
        json_response(['ok' => false, 'message' => 'Please log in again.'], 401);
    }
    return (int) $_SESSION['user_id'];
}
