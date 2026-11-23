# Banana Quest

A puzzle adventure game for the CIS046-3 *Software for Enterprise* assignment.
Players register, log in, and solve number puzzles ("find the missing banana number")
fetched live from the **Banana API**. Correct answers earn Star Points, which can be spent
on hints. Daily streaks, achievements and an adventure map track progress through
three realms (15 puzzles in total).

**Source repository:** `<add your GitHub repository URL here>`

---

## Requirements

- PHP 8.1 or newer (tested on 8.3) with the `pdo_mysql` extension
  (`curl` is used if installed; otherwise PHP streams are used)
- MySQL or MariaDB (InnoDB) - e.g. XAMPP
- Internet access (the puzzles come from `https://marcconrad.com/uob/banana/`)

## Setup (XAMPP)

1. Copy the `banana-quest` folder into `htdocs`.
2. Start **Apache** and **MySQL** in the XAMPP control panel.
3. Open phpMyAdmin -> **Import** -> choose `database/schema.sql` -> *Go*.
   (This creates the `banana_quest` database and its three tables.)
4. Database settings default to `localhost`, port `3306`, user `root`, empty password.
   If yours differ, copy `backend/config.local.example.php` to `backend/config.local.php`
   and edit it. That file is git-ignored, so credentials are never committed.
5. Browse to `http://localhost/banana-quest/`, create an account and play.

To run without XAMPP: `php -S localhost:8000` from the project folder.

## Project structure

```
banana-quest/
|-- index.php, dashboard.php        landing page and player dashboard
|-- pages/                          login, register, logout, puzzle, adventure map, profile
|-- backend/
|   |-- config.php                  settings, hardened session, PDO connection
|   |-- helpers.php                 escaping, CSRF, login guards, JSON replies
|   |-- services/                   business logic (no HTML)
|   |   |-- AuthService.php           registration, login, logout
|   |   |-- GameService.php           puzzles, hints, points, streaks
|   |   `-- BananaApiService.php      client for the external Banana API
|   `-- api/                        JSON endpoints used by the browser
|       |-- puzzles.php  submit-answer.php  hints.php  points.php
|-- assets/
|   |-- js/    api.js (fetch client)  game.js (events)  points.js (event listener)
|   |-- css/   common.css + one stylesheet per page
|   `-- img/   realm artwork
|-- database/schema.sql             database structure
`-- docs/                           this file and AI-ACKNOWLEDGEMENTS.md
```

The code is split in layers: **pages** show HTML, **api** endpoints speak JSON,
**services** hold the rules, and **config/helpers** provide shared plumbing.
Pages and endpoints stay thin, which keeps the logic testable and easy to change.

## How the four assignment themes appear in the code

### 1. Version control
Developed with Git and hosted on GitHub. Database structure is versioned as
`database/schema.sql` alongside the code, and secrets are kept out of the repository
through `.gitignore` (`backend/config.local.php`, `.env`). The service/API/page split gives
each commit a clear, small scope (e.g. "add AuthService", "move puzzle logic into GameService").

### 2. Event-driven programming
`assets/js/game.js` reacts to user and system events instead of reloading pages:

| Event | Handler |
|---|---|
| `DOMContentLoaded` | load the current puzzle |
| `input` on the answer box | keep digits only, enable/disable Submit |
| `submit` on the answer form | send answer via `fetch`, update the page |
| `submit` on the difficulty form | change difficulty before the first attempt |
| `click` on Hint / Reveal / Next | buy a hint, reveal the answer, load another puzzle |
| `bq:points-changed` (custom event) | `points.js` refreshes the Star Points banner |

Handlers are `async`: the page stays responsive while the server answers.
The map page (`pages/adventure.php`) also uses click events for its realm carousel.
`game.js` announces point changes with a custom event and `points.js` listens, so the two
files stay decoupled (publish/subscribe).

### 3. Interoperability
`BananaApiService` calls the third-party Banana API over **HTTPS**, receiving **JSON**
(`{"question": "<image url>", "solution": <digit>}`). The call is made on the server so the
solution never reaches the browser. The response is validated (status code, JSON shape,
HTTPS, allow-listed image host, numeric solution), requests have timeouts, and failures
produce a friendly error instead of a crash. The browser talks to *our own* JSON API
(`backend/api/`), which is a second interface between systems written in different languages
(JavaScript client, PHP server, SQL database).

### 4. Virtual identity
`AuthService` handles identity:
- Passwords are stored only as `password_hash()` (bcrypt) and checked with `password_verify()`.
- A server-side **session** identifies the player; the browser only holds the session cookie,
  which is `HttpOnly` (no JavaScript access), `SameSite=Lax`, and `Secure` on HTTPS.
- `session_regenerate_id(true)` after login (prevents session fixation), idle timeout after 30 minutes,
  and logout destroys the session and expires the cookie.
- **CSRF tokens** protect every state-changing request (forms and API calls).
- One generic login error message, and a basic per-session throttle after 5 failed attempts.
- Alternatives considered: JWTs (stateless, but harder to revoke), OAuth/social login
  (less password handling, more external dependency), 2FA (stronger, more setup).

## Security summary

Prepared statements everywhere (SQL injection), output escaping with `e()` / `textContent`
(XSS), CSRF tokens, ownership checks on every puzzle (`WHERE id = ? AND user_id = ?`),
server-side answer checking, database transactions with row locks for points (no double
rewards or negative balances).

## Testing performed

Tested against a clean MariaDB 10.11 database built from `database/schema.sql`:
registration (validation, duplicates), login/logout, session cookie flags, CSRF rejection,
wrong/correct answers, attempt limits, difficulty locking, hints (no double charge), reveal,
insufficient points, other-player puzzle access, repeated answers (no double reward), streak
bonus and ledger totals, Banana API response validation, API-down error handling, login
throttle, and the browser events in `game.js` (simulated with jsdom).

## Known limitations

- The login throttle is per session; a production system would throttle by IP/account in the database.
- No email verification or password reset.
- Treasury and map-unlock features from the original plan were not built.
- The puzzles depend on the external Banana API being online.

## Credits

- Banana API and example code: Marc Conrad, University of Bedfordshire
  (<https://marcconrad.com/uob/banana/>).
- Fonts: Fredoka and Nunito via Google Fonts.
- Use of AI assistance is declared in `docs/AI-ACKNOWLEDGEMENTS.md`.
