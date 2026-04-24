<?php
// header.php — the top of every logged-in page.
// Include it with:
//     $pageTitle = 'Water tracker';
//     require_once __DIR__ . '/includes/header.php';

// These files must already be loaded before the header, but we require them
// again just to be safe (require_once is a no-op if already loaded).
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

// Anyone who includes header.php is viewing a logged-in page.
require_login();

// Default title if the page forgot to set one.
$pageTitle = $pageTitle ?? 'AllLife';

// Work out which page we're on so we can highlight the active nav link.
// basename(...) returns just 'water.php' even if we're nested somewhere.
$currentPage = basename($_SERVER['PHP_SELF']);

// Small helper: returns ' active' if the given filename is the current page.
function navActive(string $file): string {
    global $currentPage;
    return $currentPage === $file ? ' active' : '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($pageTitle) ?> — AllLife</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

<nav class="navbar">
    <div class="nav-inner">
        <a href="dashboard.php" class="nav-brand">AllLife</a>

        <ul class="nav-links">
            <li><a class="nav-link<?= navActive('dashboard.php') ?>" href="dashboard.php">Home</a></li>
            <li><a class="nav-link<?= navActive('water.php') ?>"     href="water.php">Water</a></li>
            <li><a class="nav-link<?= navActive('calories.php') ?>"  href="calories.php">Calories</a></li>
            <li><a class="nav-link<?= navActive('gym.php') ?>"       href="gym.php">Gym</a></li>
        </ul>

        <div class="nav-user">
            <a class="nav-link<?= navActive('account.php') ?>" href="account.php">
                <?= htmlspecialchars(current_username() ?? 'Account') ?>
            </a>
            <a class="nav-link nav-logout" href="logout.php">Log out</a>
        </div>
    </div>
</nav>

<main class="container">
