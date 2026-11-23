<?php
/**
 * Answer endpoint.  POST session_id, answer  (CSRF token in X-CSRF-Token header).
 * The answer is checked on the server against the stored solution.
 *
 * AI assistance: structured/rewritten with Claude (Anthropic), Nov 2026 - see docs/AI-ACKNOWLEDGEMENTS.md.
 */

declare(strict_types=1);
require_once __DIR__ . '/../config.php';

$userId   = api_require_post_user();
$puzzleId = filter_var($_POST['session_id'] ?? null, FILTER_VALIDATE_INT);

if (!$puzzleId) {
    json_response(['ok' => false, 'message' => 'Invalid puzzle.'], 400);
}

$game   = new GameService($pdo, new BananaApiService());
$result = $game->submitAnswer($userId, $puzzleId, (string) ($_POST['answer'] ?? ''));
json_response($result, $result['ok'] ? 200 : 422);
