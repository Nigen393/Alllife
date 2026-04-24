<?php
// logout.php — kills the session and sends the user back to the login page.
require_once __DIR__ . '/includes/auth.php';
log_out_user();
header('Location: login.php');
exit;
