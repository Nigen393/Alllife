<?php
// register.php — create a new AllLife account.
// Shows the form on GET, processes it on POST.

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

// Already logged in? No need to register again — bounce to dashboard.
if (is_logged_in()) {
    header('Location: dashboard.php');
    exit;
}

$errors = [];
// We remember what the user typed (except passwords) so they don't have to
// retype everything if there's an error.
$old = ['full_name' => '', 'email' => '', 'username' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // --- 1. Grab the inputs, trimming whitespace off names/emails/usernames.
    $fullName = trim($_POST['full_name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm']  ?? '';

    $old['full_name'] = $fullName;
    $old['email']     = $email;
    $old['username']  = $username;

    // --- 2. Check everything is sensible.
    if ($fullName === '') {
        $errors[] = 'Please enter your full name.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }
    if (strlen($username) < 3) {
        $errors[] = 'Username must be at least 3 characters.';
    }
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }
    if ($password !== $confirm) {
        $errors[] = 'Passwords do not match.';
    }

    // --- 3. Check username/email aren't already in use.
    if (empty($errors)) {
        $check = $db->prepare(
            'SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1'
        );
        $check->execute([$username, $email]);
        if ($check->fetch()) {
            $errors[] = 'That username or email is already taken.';
        }
    }

    // --- 4. All good? Insert the user and log them in.
    if (empty($errors)) {
        // NEVER store plain passwords. password_hash() does the secure hashing.
        $hash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $db->prepare(
            'INSERT INTO users (full_name, email, username, password_hash)
             VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$fullName, $email, $username, $hash]);
        $newUserId = (int)$db->lastInsertId();

        log_in_user($newUserId, $username);
        header('Location: dashboard.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Register — AllLife</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<main class="container">
    <h1>Create your AllLife account</h1>

    <div class="card">
        <?php foreach ($errors as $err): ?>
            <div class="error"><?= htmlspecialchars($err) ?></div>
        <?php endforeach; ?>

        <form method="post" action="register.php" autocomplete="on">
            <label>Full name</label>
            <input type="text" name="full_name"
                   value="<?= htmlspecialchars($old['full_name']) ?>" required>

            <label>Email</label>
            <input type="email" name="email"
                   value="<?= htmlspecialchars($old['email']) ?>" required>

            <label>Username</label>
            <input type="text" name="username"
                   value="<?= htmlspecialchars($old['username']) ?>" required>

            <label>Password (at least 8 characters)</label>
            <input type="password" name="password" required>

            <label>Confirm password</label>
            <input type="password" name="confirm" required>

            <button type="submit">Create account</button>
        </form>

        <p style="margin-top: 1rem;">
            Already have an account? <a href="login.php">Log in here</a>.
        </p>
    </div>
</main>
</body>
</html>
