-- Banana Quest database schema (MySQL / MariaDB, InnoDB, utf8mb4)
-- Import with phpMyAdmin: Import tab -> choose this file.
-- Reconstructed from the queries used in the PHP code; compare it with your
-- own phpMyAdmin export and adjust any column types if they differ.

CREATE DATABASE IF NOT EXISTS banana_quest
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE banana_quest;

DROP TABLE IF EXISTS point_transactions;
DROP TABLE IF EXISTS game_sessions;
DROP TABLE IF EXISTS users;

CREATE TABLE users (
    id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    username         VARCHAR(50)  NOT NULL,
    email            VARCHAR(255) NOT NULL,
    password_hash    VARCHAR(255) NOT NULL,          -- password_hash() output, never plain text
    star_points      INT          NOT NULL DEFAULT 0, -- running balance (also logged in point_transactions)
    current_streak   INT UNSIGNED NOT NULL DEFAULT 0,
    longest_streak   INT UNSIGNED NOT NULL DEFAULT 0,
    last_streak_date DATE         NULL,
    created_at       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_username (username),
    UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB;

-- One row per puzzle fetched from the Banana API for a player.
-- The solution stays on the server and is never sent to the browser
-- unless the player pays to reveal it.
CREATE TABLE game_sessions (
    id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id          INT UNSIGNED NOT NULL,
    puzzle_api_id    VARCHAR(50)  NOT NULL DEFAULT 'banana-api',
    puzzle_image_url VARCHAR(500) NOT NULL,
    solution         VARCHAR(20)  NOT NULL,
    difficulty       ENUM('easy','medium','hard') NOT NULL DEFAULT 'easy',
    attempts_used    TINYINT UNSIGNED NOT NULL DEFAULT 0,
    hint_used        TINYINT(1)   NOT NULL DEFAULT 0,
    answer_revealed  TINYINT(1)   NOT NULL DEFAULT 0,
    is_solved        TINYINT(1)   NOT NULL DEFAULT 0,
    is_failed        TINYINT(1)   NOT NULL DEFAULT 0,
    created_at       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_sessions_user (user_id, is_solved),
    CONSTRAINT fk_sessions_user FOREIGN KEY (user_id)
        REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Ledger of every points change (welcome bonus, rewards, hints, reveals, streaks).
CREATE TABLE point_transactions (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id       INT UNSIGNED NOT NULL,
    points_change INT          NOT NULL,
    reason        VARCHAR(255) NOT NULL,
    created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_points_user (user_id),
    CONSTRAINT fk_points_user FOREIGN KEY (user_id)
        REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB;
