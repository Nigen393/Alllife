<?php
// login.php — log into an existing AllLife account.
// Lets the user sign in with EITHER their username OR their email.

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

// Already logged in? Skip the form.
if (is_logged_in()) {
    header('Location: dashboard.php');
    exit;
}

$error    = '';
$username = '';   // remembered so the field isn't wiped on error

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Please fill in both fields.';
    } else {
        // Look the user up by username OR email (either works).
        $stmt = $db->prepare(
            'SELECT id, username, password_hash
             FROM users
             WHERE username = ? OR email = ?
             LIMIT 1'
        );
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch();

        // password_verify checks the plain password against the stored hash.
        if ($user && password_verify($password, $user['password_hash'])) {
            log_in_user((int)$user['id'], $user['username']);
            header('Location: dashboard.php');
            exit;
        } else {
            // Deliberately vague — don't leak whether the username exists.
            $error = 'Wrong username or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Log in — AllLife</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<main class="container">
    <h1>Log in to AllLife</h1>

    <div class="card">
        <?php if ($error !== ''): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="post" action="login.php" autocomplete="on">
            <label>Username or email</label>
            <input type="text" name="username"
                   value="<?= htmlspecialchars($username) ?>" required>

            <label>Password</label>
            <input type="password" name="password" required>

            <button type="submit">Log in</button>
        </form>

        <p style="margin-top: 1rem;">
            No account yet? <a href="register.php">Register here</a>.
        </p>
    </div>
</main>
</body>
</html>
