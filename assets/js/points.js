/**
 * points.js - keeps the Star Points / progress banner up to date.
 *
 * It does not know anything about the puzzle code. game.js simply announces
 * "bq:points-changed" (a custom DOM event) and this file reacts to it. That is
 * the publish/subscribe style of event-driven programming: the sender does not
 * call the receiver directly, so either side can change independently.
 *
 * AI assistance: structured/rewritten with Claude (Anthropic), Nov 2026 - see docs/AI-ACKNOWLEDGEMENTS.md.
 */
(function () {
    'use strict';

    const pointsEl   = document.getElementById('points');
    const progressEl = document.getElementById('progress');

    document.addEventListener('bq:points-changed', (event) => {
        const { points, solvedCount } = event.detail;

        if (pointsEl && Number.isFinite(points)) {
            pointsEl.textContent = String(points);
        }
        if (progressEl && Number.isFinite(solvedCount)) {
            progressEl.textContent = String(solvedCount);
        }
    });
})();
