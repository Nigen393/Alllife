<?php
// index.php — the front door.
// Logged in?  Go to the dashboard.
// Not logged in?  Go to the login page.

require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    header('Location: dashboard.php');
} else {
    header('Location: login.php');
}
exit;
