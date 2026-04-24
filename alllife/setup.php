<?php
// setup.php — run this ONCE (visit it in the browser, or run from CLI)
// to create the SQLite database file and all the tables AllLife needs.
// Safe to run again: every CREATE TABLE uses "IF NOT EXISTS".

require_once __DIR__ . '/includes/db.php';

// --- Drop features we no longer build --------------------------------
// The journal / custom-goals features were cut from the scope.  These
// DROPs clean up any tables left over from earlier setup runs.  They
// only drop tables that exist, so it's safe to run at any time.  None
// of the kept tables (users, water_logs, calorie_logs, gym_*) are touched,
// so your test user and any logged data stay put.
$cleanup = [
    'DROP TABLE IF EXISTS journal_entries',
    'DROP TABLE IF EXISTS custom_goals',
    'DROP TABLE IF EXISTS custom_goal_logs',
];

$statements = [

    // --- USERS ------------------------------------------------------------
    // One row per registered user. Password is stored as a HASH, never plain.
    "CREATE TABLE IF NOT EXISTS users (
        id              INTEGER PRIMARY KEY AUTOINCREMENT,
        full_name       TEXT    NOT NULL,
        email           TEXT    NOT NULL UNIQUE,
        username        TEXT    NOT NULL UNIQUE,
        password_hash   TEXT    NOT NULL,
        calorie_target  INTEGER NOT NULL DEFAULT 2000,
        water_target_ml INTEGER NOT NULL DEFAULT 2000,
        created_at      TEXT    NOT NULL DEFAULT CURRENT_TIMESTAMP
    )",

    // --- WATER ------------------------------------------------------------
    // Each row = one drink. We store the amount in ml and when it was logged.
    "CREATE TABLE IF NOT EXISTS water_logs (
        id         INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id    INTEGER NOT NULL,
        amount_ml  INTEGER NOT NULL,
        logged_at  TEXT    NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )",

    // --- CALORIES ---------------------------------------------------------
    // Each row = one food/meal entry.
    "CREATE TABLE IF NOT EXISTS calorie_logs (
        id         INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id    INTEGER NOT NULL,
        meal_name  TEXT    NOT NULL,
        calories   INTEGER NOT NULL,
        logged_at  TEXT    NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )",

    // --- GYM SESSIONS -----------------------------------------------------
    // A session is one workout (e.g. 'Push day — 24/04/2026').
    "CREATE TABLE IF NOT EXISTS gym_sessions (
        id            INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id       INTEGER NOT NULL,
        session_name  TEXT    NOT NULL,
        session_date  TEXT    NOT NULL,
        notes         TEXT,
        created_at    TEXT    NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )",

    // --- GYM EXERCISES ----------------------------------------------------
    // Each session has many exercises (e.g. Bench press 3x8 @ 60kg).
    "CREATE TABLE IF NOT EXISTS gym_exercises (
        id             INTEGER PRIMARY KEY AUTOINCREMENT,
        session_id     INTEGER NOT NULL,
        exercise_name  TEXT    NOT NULL,
        sets           INTEGER NOT NULL,
        reps           INTEGER NOT NULL,
        weight_kg      REAL    NOT NULL DEFAULT 0,
        FOREIGN KEY (session_id) REFERENCES gym_sessions(id) ON DELETE CASCADE
    )",
];

echo "<h1>AllLife — Database Setup</h1>";
echo "<pre>";

// 1. Clean up any tables from features we've removed.
foreach ($cleanup as $sql) {
    try {
        $db->exec($sql);
        preg_match('/DROP TABLE IF EXISTS (\w+)/', $sql, $m);
        echo "[DROP]  " . ($m[1] ?? '(unknown)') . "\n";
    } catch (PDOException $e) {
        echo "[ERROR] " . $e->getMessage() . "\n";
    }
}

// 2. Create any tables that don't exist yet.
foreach ($statements as $sql) {
    try {
        $db->exec($sql);
        preg_match('/CREATE TABLE IF NOT EXISTS (\w+)/', $sql, $m);
        echo "[OK]    " . ($m[1] ?? '(unknown)') . "\n";
    } catch (PDOException $e) {
        echo "[ERROR] " . $e->getMessage() . "\n";
    }
}

echo "</pre>";
echo "<p>Done. You can now open <a href='index.php'>index.php</a>.</p>";
