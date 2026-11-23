<?php
/**
 * Puzzle endpoint.
 *   GET  -> current puzzle state (fetches a new puzzle from the Banana API if none is open)
 *   POST -> action=set_difficulty, session_id, difficulty
 * Returns JSON. The puzzle solution is never included.
 *
 * AI assistance: structured/rewritten with Claude (Anthropic), Nov 2026 - see docs/AI-ACKNOWLEDGEMENTS.md.
 */

declare(strict_types=1);
require_once __DIR__ . '/../config.php';

$game = new GameService($pdo, new BananaApiService());

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $userId = api_require_get_user();
    $state  = $game->getCurrentState($userId);
    json_response($state, $state['ok'] ? 200 : 502);
}

$userId = api_require_post_user();
$puzzleId = filter_var($_POST['session_id'] ?? null, FILTER_VALIDATE_INT);
if (($_POST['action'] ?? '') !== 'set_difficulty' || !$puzzleId) {
    json_response(['ok' => false, 'message' => 'Invalid request.'], 400);
}

$result = $game->setDifficulty($userId, $puzzleId, (string) ($_POST['difficulty'] ?? ''));
json_response($result, $result['ok'] ? 200 : 422);
