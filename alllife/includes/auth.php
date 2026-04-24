<?php
// auth.php — everything to do with "who is logged in right now?".
// Any page that wants to know about the user should:
//     require_once __DIR__ . '/includes/auth.php';

// Make sure PHP's session system is started. Safe to call on every page —
// the "if" stops us from starting it twice.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Returns true if the user is currently logged in.
 */
function is_logged_in(): bool
{
    return isset($_SESSION['user_id']);
}

/**
 * Returns the logged-in user's id, or null if nobody is logged in.
 */
function current_user_id(): ?int
{
    return $_SESSION['user_id'] ?? null;
}

/**
 * Returns the logged-in user's username, or null if nobody is logged in.
 */
function current_username(): ?string
{
    return $_SESSION['username'] ?? null;
}

/**
 * Call this at the top of any page that logged-out users shouldn't see.
 * If they aren't logged in, they get bounced to login.php.
 */
function require_login(): void
{
    if (!is_logged_in()) {
        header('Location: login.php');
        exit;
    }
}

/**
 * Stores the user's id and username in the session so they're "logged in".
 */
function log_in_user(int $userId, string $username): void
{
    // Regenerate the session id to prevent session-fixation attacks.
    session_regenerate_id(true);
    $_SESSION['user_id']  = $userId;
    $_SESSION['username'] = $username;
}

/**
 * Wipes the session so the user is logged out.
 */
function log_out_user(): void
{
    $_SESSION = [];

    // Also clear the session cookie from the browser.
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();
}
