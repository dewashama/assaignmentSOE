/**
 * api.js - tiny client for Banana Quest's own backend (JSON over HTTP).
 *
 * Every call is asynchronous: fetch() returns a Promise and the page keeps
 * running while the server answers. The CSRF token (rendered into the page by
 * PHP) is sent in a header on POST requests so the server can reject forged
 * cross-site requests.
 *
 * AI assistance: structured/rewritten with Claude (Anthropic), Nov 2026 - see docs/AI-ACKNOWLEDGEMENTS.md.
 */
(function (global) {
    'use strict';

    const root = document.getElementById('game');
    const base = root ? root.dataset.api : '../backend/api';
    const csrf = root ? root.dataset.csrf : '';

    async function request(endpoint, { method = 'GET', data = null } = {}) {
        const options = { method, credentials: 'same-origin', headers: {} };

        if (method === 'POST') {
            options.headers['X-CSRF-Token'] = csrf;
            options.body = new URLSearchParams(data || {});
        }

        let response;
        try {
            response = await fetch(`${base}/${endpoint}`, options);
        } catch (networkError) {
            return { ok: false, message: 'Could not reach the server. Check your connection.' };
        }

        if (response.status === 401) {          // session expired
            window.location.href = 'login.php';
            return { ok: false, message: 'Please log in again.' };
        }

        try {
            return await response.json();
        } catch (parseError) {
            return { ok: false, message: 'The server sent an unexpected reply.' };
        }
    }

    global.BananaApi = {
        getPuzzle:     ()                      => request('puzzles.php'),
        getPoints:     ()                      => request('points.php'),
        setDifficulty: (id, difficulty)        => request('puzzles.php',       { method: 'POST', data: { action: 'set_difficulty', session_id: id, difficulty } }),
        submitAnswer:  (id, answer)            => request('submit-answer.php', { method: 'POST', data: { session_id: id, answer } }),
        buyHint:       (id)                    => request('hints.php',         { method: 'POST', data: { action: 'hint',   session_id: id } }),
        revealAnswer:  (id)                    => request('hints.php',         { method: 'POST', data: { action: 'reveal', session_id: id } }),
    };
})(window);
