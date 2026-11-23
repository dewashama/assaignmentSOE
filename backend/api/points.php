<?php
/**
 * Points endpoint.  GET -> current Star Points balance and solved-puzzle count.
 *
 * AI assistance: structured/rewritten with Claude (Anthropic), Nov 2026 - see docs/AI-ACKNOWLEDGEMENTS.md.
 */

declare(strict_types=1);
require_once __DIR__ . '/../config.php';

$userId = api_require_get_user();
$game   = new GameService($pdo, new BananaApiService());

json_response([
    'ok'           => true,
    'points'       => $game->getPoints($userId),
    'solved_count' => $game->getSolvedCount($userId),
    'total'        => GameService::TOTAL_PUZZLES,
]);
