<?php
// dashboard.php — the logged-in home page.
// Shows a quick "snapshot" of today and cards linking to every tracker.

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

$pageTitle = 'Dashboard';
require_once __DIR__ . '/includes/header.php';

$userId = current_user_id();

// --- Pull user's name + personal targets ---------------------------------
$stmt = $db->prepare(
    'SELECT full_name, username, calorie_target, water_target_ml FROM users WHERE id = ?'
);
$stmt->execute([$userId]);
$user = $stmt->fetch();

// --- Today's numbers -----------------------------------------------------
// SQLite date() with 'now' gives us today's date in YYYY-MM-DD.  Comparing
// substr(logged_at, 1, 10) matches anything logged today.
$today = date('Y-m-d');

$stmt = $db->prepare(
    "SELECT COALESCE(SUM(amount_ml), 0) AS total
     FROM water_logs
     WHERE user_id = ? AND substr(logged_at, 1, 10) = ?"
);
$stmt->execute([$userId, $today]);
$waterToday = (int)$stmt->fetch()['total'];

$stmt = $db->prepare(
    "SELECT COALESCE(SUM(calories), 0) AS total
     FROM calorie_logs
     WHERE user_id = ? AND substr(logged_at, 1, 10) = ?"
);
$stmt->execute([$userId, $today]);
$caloriesToday = (int)$stmt->fetch()['total'];

// Most recent gym session (just the one row, if any).
$stmt = $db->prepare(
    "SELECT session_name, session_date
     FROM gym_sessions
     WHERE user_id = ?
     ORDER BY session_date DESC, id DESC
     LIMIT 1"
);
$stmt->execute([$userId]);
$lastGym = $stmt->fetch();

// --- Progress percentages (cap at 100% so the bar never overflows) -------
$waterPct    = $user['water_target_ml'] > 0
    ? min(100, round($waterToday / $user['water_target_ml'] * 100))
    : 0;
$caloriePct  = $user['calorie_target'] > 0
    ? min(100, round($caloriesToday / $user['calorie_target'] * 100))
    : 0;
?>

<h1>Welcome back, <?= htmlspecialchars($user['full_name']) ?> 👋</h1>
<p class="tagline">Here's your snapshot for today.</p>

<!-- Today's snapshot bars -->
<div class="card">
    <h2>Today</h2>

    <div class="stat-row">
        <div class="stat-label">
            💧 Water
            <span class="stat-value">
                <?= number_format($waterToday) ?> / <?= number_format((int)$user['water_target_ml']) ?> ml
            </span>
        </div>
        <div class="progress">
            <div class="progress-bar" style="width: <?= $waterPct ?>%"></div>
        </div>
    </div>

    <div class="stat-row">
        <div class="stat-label">
            🍎 Calories
            <span class="stat-value">
                <?= number_format($caloriesToday) ?> / <?= number_format((int)$user['calorie_target']) ?> kcal
            </span>
        </div>
        <div class="progress">
            <div class="progress-bar" style="width: <?= $caloriePct ?>%"></div>
        </div>
    </div>

    <div class="stat-row">
        <div class="stat-label">
            💪 Last gym session
            <span class="stat-value">
                <?php if ($lastGym): ?>
                    <?= htmlspecialchars($lastGym['session_name']) ?>
                    on <?= htmlspecialchars($lastGym['session_date']) ?>
                <?php else: ?>
                    None yet
                <?php endif; ?>
            </span>
        </div>
    </div>
</div>

<!-- Feature cards -->
<h2 style="margin-top: 2rem;">Your trackers</h2>

<div class="grid">

    <a href="water.php" class="feature-card">
        <span class="feature-emoji">💧</span>
        <h3>Water tracker</h3>
        <p>Log how much you drink and watch your daily/weekly/monthly averages.</p>
    </a>

    <a href="calories.php" class="feature-card">
        <span class="feature-emoji">🍎</span>
        <h3>Calorie tracker</h3>
        <p>Track meals against your target and stay on top of your intake.</p>
    </a>

    <a href="gym.php" class="feature-card">
        <span class="feature-emoji">💪</span>
        <h3>Gym tracker</h3>
        <p>Log sessions and exercises, and see your progression over time.</p>
    </a>

    <a href="account.php" class="feature-card">
        <span class="feature-emoji">⚙️</span>
        <h3>Account settings</h3>
        <p>Change your details, water and calorie targets, or password.</p>
    </a>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
