<?php
/**
 * Paid help endpoint.  POST session_id, action = hint | reveal
 *   hint   -> costs 10 Star Points, returns an odd/even clue
 *   reveal -> costs 50 Star Points, returns the answer and closes the puzzle
 *
 * AI assistance: structured/rewritten with Claude (Anthropic), Nov 2026 - see docs/AI-ACKNOWLEDGEMENTS.md.
 */

declare(strict_types=1);
require_once __DIR__ . '/../config.php';

$userId   = api_require_post_user();
$puzzleId = filter_var($_POST['session_id'] ?? null, FILTER_VALIDATE_INT);
$action   = (string) ($_POST['action'] ?? '');

if (!$puzzleId || !in_array($action, ['hint', 'reveal'], true)) {
    json_response(['ok' => false, 'message' => 'Invalid request.'], 400);
}

$game   = new GameService($pdo, new BananaApiService());
$result = $action === 'hint'
    ? $game->buyHint($userId, $puzzleId)
    : $game->revealAnswer($userId, $puzzleId);

json_response($result, $result['ok'] ? 200 : 422);
