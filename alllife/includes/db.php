<?php
// db.php — shared SQLite connection for the whole app.
// Any page that needs the database just writes:
//     require_once __DIR__ . '/../includes/db.php';
// and then uses the $db variable.

$dbDir  = __DIR__ . '/../db';
$dbFile = $dbDir . '/alllife.db';

// Make sure the db folder exists (first run will create it).
if (!is_dir($dbDir)) {
    mkdir($dbDir, 0775, true);
}

try {
    $db = new PDO('sqlite:' . $dbFile);
    // Throw exceptions on SQL errors so we notice bugs early.
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Return rows as associative arrays ($row['username'] not $row[0]).
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    // Turn on foreign keys (SQLite has them off by default).
    $db->exec('PRAGMA foreign_keys = ON;');
} catch (PDOException $e) {
    die('Database connection failed: ' . $e->getMessage());
}
