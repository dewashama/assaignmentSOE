<?php
/**
 * Logout - POST only, with a CSRF token, so a malicious site cannot log a
 * player out by embedding a link or image that points here.
 *
 * AI assistance: structured/rewritten with Claude (Anthropic), Nov 2026 - see docs/AI-ACKNOWLEDGEMENTS.md.
 */

declare(strict_types=1);
require_once __DIR__ . '/../backend/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_valid($_POST['csrf_token'] ?? null)) {
    (new AuthService($pdo))->logout();
    header('Location: login.php');
    exit;
}

// Anything else (e.g. someone typing the URL) just goes back to the dashboard.
header('Location: ../dashboard.php');
exit;
