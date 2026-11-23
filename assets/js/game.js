/**
 * game.js - puzzle page behaviour, driven by user events.
 *
 * Events handled here:
 *   DOMContentLoaded      -> load the current puzzle from the server
 *   submit (answer form)  -> send the answer, show the result without reloading
 *   input  (answer box)   -> keep only digits and enable/disable the Submit button
 *   submit (difficulty)   -> change difficulty before the first attempt
 *   click  (hint/reveal)  -> buy a hint or reveal the answer (with a confirm dialog)
 *   click  (next puzzle)  -> request another puzzle
 *
 * Events fired here:
 *   bq:points-changed     -> picked up by points.js to refresh the banner
 *
 * Each handler is async: it awaits the server's JSON reply (see api.js) and then
 * updates only the parts of the page that changed. All server text is inserted
 * with textContent (never innerHTML), so it cannot inject markup (XSS defence).
 *
 * AI assistance: structured/rewritten with Claude (Anthropic), Nov 2026 - see docs/AI-ACKNOWLEDGEMENTS.md.
 */
(function () {
    'use strict';

    const $ = (id) => document.getElementById(id);
    const el = {
        message:   $('message'),
        hintBox:   $('hint-box'),
        revealBox: $('revealed-box'),
        revealVal: $('revealed-value'),
        area:      $('puzzle-area'),
        finished:  $('finished'),
        between:   $('between'),
        betweenTx: $('between-text'),
        nextBtn:   $('next-btn'),
        image:     $('puzzle-image'),
        diffForm:  $('difficulty-form'),
        diffSel:   $('difficulty'),
        diffBtn:   $('difficulty-btn'),
        diffLock:  $('difficulty-locked'),
        mode:      $('mode'),
        left:      $('attempts-left'),
        max:       $('attempts-max'),
        reward:    $('reward'),
        form:      $('answer-form'),
        answer:    $('answer'),
        answerBtn: $('answer-btn'),
        hintBtn:   $('hint-btn'),
        revealBtn: $('reveal-btn'),
        note:      $('action-note'),
    };

    let puzzle = null;      // current puzzle (never contains the solution)
    let busy = false;       // blocks double-clicks while a request is running

    // ---- small UI helpers ------------------------------------------------------

    function show(node, visible) { node.hidden = !visible; }

    function showMessage(text, kind) {
        el.message.textContent = text;
        el.message.className = 'status' + (kind ? ' message-' + kind : '');
        show(el.message, text !== '');
    }

    function setBusy(state) {
        busy = state;
        [el.answerBtn, el.hintBtn, el.revealBtn, el.diffBtn, el.nextBtn].forEach((b) => {
            if (b) { b.disabled = state; }
        });
        if (!state) { syncAnswerButton(); syncHintButton(); }
    }

    function announcePoints(points, solvedCount) {
        document.dispatchEvent(new CustomEvent('bq:points-changed', {
            detail: { points, solvedCount },
        }));
    }

    function syncAnswerButton() { el.answerBtn.disabled = busy || el.answer.value === ''; }
    function syncHintButton()   { el.hintBtn.disabled = busy || !puzzle || puzzle.hint_used; }

    // ---- rendering ---------------------------------------------------------------

    function renderPuzzle(p) {
        puzzle = p;

        el.image.src = p.image_url;
        el.diffSel.value = p.difficulty;
        el.diffSel.disabled = p.locked;
        show(el.diffBtn, !p.locked);
        show(el.diffLock, p.locked);
        el.mode.textContent = p.difficulty.charAt(0).toUpperCase() + p.difficulty.slice(1);
        el.left.textContent = p.attempts_left;
        el.max.textContent = p.max_attempts;
        el.reward.textContent = p.reward;

        show(el.hintBox, Boolean(p.hint_text));
        el.hintBox.textContent = p.hint_text || '';
        el.note.textContent = p.hint_used
            ? "You've already purchased this puzzle's hint. It won't charge you again."
            : 'A hint costs 10 points. Revealing the answer costs 50 points and skips the solving reward.';

        syncHintButton();
        syncAnswerButton();
        show(el.area, true);
        show(el.between, false);
        show(el.finished, false);
    }

    function showBetween(text, buttonLabel) {
        puzzle = null;
        show(el.area, false);
        el.betweenTx.textContent = text;
        el.nextBtn.textContent = buttonLabel;
        show(el.between, true);
    }

    function showFinished() {
        puzzle = null;
        show(el.area, false);
        show(el.between, false);
        show(el.finished, true);
    }

    // ---- loading -------------------------------------------------------------------

    async function loadPuzzle() {
        setBusy(true);
        showMessage('Fetching a puzzle from the royal archives…', '');
        show(el.revealBox, false);
        el.answer.value = '';

        const data = await BananaApi.getPuzzle();
        setBusy(false);

        if (!data.ok) {
            showMessage(data.message || 'Could not load a puzzle.', 'error');
            showBetween('The puzzle service did not answer.', 'Try Again 🍌');
            return;
        }

        showMessage('', '');
        announcePoints(data.points, data.solved_count);

        if (data.finished) { showFinished(); } else { renderPuzzle(data.puzzle); el.answer.focus(); }
    }

    // ---- event handlers ----------------------------------------------------------------

    el.answer.addEventListener('input', () => {
        el.answer.value = el.answer.value.replace(/\D/g, '').slice(0, 10);
        syncAnswerButton();
    });

    el.form.addEventListener('submit', async (event) => {
        event.preventDefault();               // stop the full-page reload
        if (busy || !puzzle) { return; }

        setBusy(true);
        const data = await BananaApi.submitAnswer(puzzle.id, el.answer.value);
        setBusy(false);

        if (!data.ok) { showMessage(data.message, 'error'); return; }

        if (data.correct) {
            showMessage(data.message, 'success');
            announcePoints(data.points, data.solved_count);
            if (data.solved_count >= 15) { showFinished(); }
            else { showBetween('Your next challenge awaits, brave adventurer!', 'Next Puzzle ✨'); }
        } else if (data.failed) {
            showMessage(data.message, 'error');
            showBetween("You used all your attempts on this puzzle. Don't give up, brave adventurer!",
                        'Try the Next Puzzle ✨');
        } else {
            puzzle.attempts_left = data.attempts_left;
            puzzle.locked = true;
            el.left.textContent = data.attempts_left;
            el.diffSel.disabled = true;
            show(el.diffBtn, false);
            show(el.diffLock, true);
            showMessage(data.message, 'error');
            el.answer.select();
        }
    });

    el.diffForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (busy || !puzzle) { return; }

        setBusy(true);
        const data = await BananaApi.setDifficulty(puzzle.id, el.diffSel.value);
        setBusy(false);

        if (!data.ok) { showMessage(data.message, 'error'); return; }
        showMessage(data.message, 'success');
        renderPuzzle(data.puzzle);
    });

    el.hintBtn.addEventListener('click', async () => {
        if (busy || !puzzle) { return; }

        setBusy(true);
        const data = await BananaApi.buyHint(puzzle.id);
        setBusy(false);

        if (!data.ok) { showMessage(data.message, 'error'); return; }
        showMessage(data.message, 'success');
        renderPuzzle(data.puzzle);
        if (typeof data.points === 'number') { announcePoints(data.points, undefined); }
    });

    el.revealBtn.addEventListener('click', async () => {
        if (busy || !puzzle) { return; }
        if (!window.confirm('Reveal the answer for 50 Star Points? You will not earn the solving reward.')) {
            return;
        }

        setBusy(true);
        const data = await BananaApi.revealAnswer(puzzle.id);
        setBusy(false);

        if (!data.ok) { showMessage(data.message, 'error'); return; }
        showMessage(data.message, '');
        el.revealVal.textContent = data.revealed_answer;
        show(el.revealBox, true);
        announcePoints(data.points, undefined);
        showBetween('This puzzle does not count toward your 15 solved puzzles.', 'Try Another Puzzle 🍌');
    });

    el.nextBtn.addEventListener('click', loadPuzzle);

    document.addEventListener('DOMContentLoaded', loadPuzzle);
})();
