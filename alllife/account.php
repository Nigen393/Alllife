<?php
// account.php — let the user view and change their personal details,
// their daily targets, and their password.

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

require_login();
$userId = current_user_id();

$errors  = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // We use a hidden "form" field so one page can serve both forms (details
    // + password change) without them stepping on each other.
    $form = $_POST['form'] ?? '';

    // -----------------------------------------------------------------
    // FORM 1: personal details + daily targets
    // -----------------------------------------------------------------
    if ($form === 'details') {
        $fullName   = trim($_POST['full_name'] ?? '');
        $email      = trim($_POST['email'] ?? '');
        $calTarget  = (int)($_POST['calorie_target'] ?? 0);
        $waterTgt   = (int)($_POST['water_target_ml'] ?? 0);

        if ($fullName === '') $errors[] = 'Please enter your full name.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Please enter a valid email.';
        if ($calTarget < 500 || $calTarget > 10000) $errors[] = 'Calorie target must be between 500 and 10000.';
        if ($waterTgt < 500 || $waterTgt > 10000) $errors[] = 'Water target must be between 500 and 10000 ml.';

        // Email mustn't clash with another user's email.
        if (empty($errors)) {
            $chk = $db->prepare('SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1');
            $chk->execute([$email, $userId]);
            if ($chk->fetch()) $errors[] = 'That email is already used by another account.';
        }

        if (empty($errors)) {
            $stmt = $db->prepare(
                'UPDATE users
                 SET full_name = ?, email = ?, calorie_target = ?, water_target_ml = ?
                 WHERE id = ?'
            );
            $stmt->execute([$fullName, $email, $calTarget, $waterTgt, $userId]);
            $success = 'Your details have been saved.';
        }
    }

    // -----------------------------------------------------------------
    // FORM 2: password change
    // -----------------------------------------------------------------
    if ($form === 'password') {
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if (strlen($new) < 8) $errors[] = 'New password must be at least 8 characters.';
        if ($new !== $confirm) $errors[] = 'New passwords do not match.';

        if (empty($errors)) {
            $stmt = $db->prepare('SELECT password_hash FROM users WHERE id = ?');
            $stmt->execute([$userId]);
            $row = $stmt->fetch();
            if (!$row || !password_verify($current, $row['password_hash'])) {
                $errors[] = 'Current password is wrong.';
            }
        }

        if (empty($errors)) {
            $newHash = password_hash($new, PASSWORD_DEFAULT);
            $stmt    = $db->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
            $stmt->execute([$newHash, $userId]);
            $success = 'Password updated.';
        }
    }
}

// Pull the freshest user data AFTER any save so the form shows new values.
$stmt = $db->prepare(
    'SELECT full_name, email, username, calorie_target, water_target_ml FROM users WHERE id = ?'
);
$stmt->execute([$userId]);
$user = $stmt->fetch();

$pageTitle = 'Account settings';
require_once __DIR__ . '/includes/header.php';
?>

<h1>Account settings</h1>

<?php foreach ($errors as $err): ?>
    <div class="error"><?= htmlspecialchars($err) ?></div>
<?php endforeach; ?>

<?php if ($success !== ''): ?>
    <div class="success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<!-- Personal details form -->
<div class="card">
    <h2>Your details</h2>

    <form method="post" action="account.php">
        <input type="hidden" name="form" value="details">

        <label>Full name</label>
        <input type="text" name="full_name"
               value="<?= htmlspecialchars($user['full_name']) ?>" required>

        <label>Email</label>
        <input type="email" name="email"
               value="<?= htmlspecialchars($user['email']) ?>" required>

        <label>Username (cannot be changed)</label>
        <input type="text" value="<?= htmlspecialchars($user['username']) ?>" disabled>

        <label>Daily water target (ml)</label>
        <input type="number" name="water_target_ml" min="500" max="10000" step="50"
               value="<?= (int)$user['water_target_ml'] ?>" required>

        <label>Daily calorie target (kcal)</label>
        <input type="number" name="calorie_target" min="500" max="10000" step="50"
               value="<?= (int)$user['calorie_target'] ?>" required>

        <button type="submit">Save details</button>
    </form>
</div>

<!-- Password change form -->
<div class="card">
    <h2>Change password</h2>

    <form method="post" action="account.php">
        <input type="hidden" name="form" value="password">

        <label>Current password</label>
        <input type="password" name="current_password" required>

        <label>New password (at least 8 characters)</label>
        <input type="password" name="new_password" required>

        <label>Confirm new password</label>
        <input type="password" name="confirm_password" required>

        <button type="submit">Change password</button>
    </form>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
