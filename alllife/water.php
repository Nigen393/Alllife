<?php
// water.php — the full water tracker.
// Lets the user:
//   • log a drink (quick buttons for 250/500/750 ml + custom form)
//   • see today's entries (with delete)
//   • see weekly, monthly and yearly averages
//   • see the last 14 days on a chart

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_login();
$userId = current_user_id();

$errors  = [];
$success = '';

// ----------------------------------------------------------------------
// 1. Handle form submissions
// ----------------------------------------------------------------------

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // --- Delete one entry ---
    if ($action === 'delete') {
        $id = (int)($_POST['entry_id'] ?? 0);
        // The "AND user_id = ?" stops a user deleting someone else's row.
        $stmt = $db->prepare('DELETE FROM water_logs WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);
        $success = 'Entry removed.';
    }

    // --- Add a new entry ---
    if ($action === 'add') {
        $amount   = (int)($_POST['amount_ml'] ?? 0);
        $loggedAt = trim($_POST['logged_at'] ?? '');  // optional

        if ($amount < 1 || $amount > 5000) {
            $errors[] = 'Amount must be between 1 and 5000 ml.';
        }

        // If the user picked a date/time, validate it; otherwise use NOW.
        if ($loggedAt !== '') {
            // HTML <input type="datetime-local"> gives us 'YYYY-MM-DDTHH:MM'.
            // SQLite likes 'YYYY-MM-DD HH:MM:SS', so normalise.
            $ts = strtotime($loggedAt);
            if ($ts === false) {
                $errors[] = 'Invalid date/time.';
            } else {
                $loggedAt = date('Y-m-d H:i:s', $ts);
            }
        }

        if (empty($errors)) {
            if ($loggedAt !== '') {
                $stmt = $db->prepare(
                    'INSERT INTO water_logs (user_id, amount_ml, logged_at) VALUES (?, ?, ?)'
                );
                $stmt->execute([$userId, $amount, $loggedAt]);
            } else {
                // No date given → DB default of CURRENT_TIMESTAMP fills it.
                $stmt = $db->prepare(
                    'INSERT INTO water_logs (user_id, amount_ml) VALUES (?, ?)'
                );
                $stmt->execute([$userId, $amount]);
            }
            $success = 'Logged ' . number_format($amount) . ' ml.';
        }
    }
}

// ----------------------------------------------------------------------
// 2. Fetch everything we need to render
// ----------------------------------------------------------------------

// The user's personal daily target.
$stmt = $db->prepare('SELECT water_target_ml FROM users WHERE id = ?');
$stmt->execute([$userId]);
$target = (int)$stmt->fetch()['water_target_ml'];

// Today's individual entries, newest first.
$today = date('Y-m-d');
$stmt = $db->prepare(
    "SELECT id, amount_ml, logged_at
     FROM water_logs
     WHERE user_id = ? AND substr(logged_at, 1, 10) = ?
     ORDER BY logged_at DESC, id DESC"
);
$stmt->execute([$userId, $today]);
$todayEntries = $stmt->fetchAll();

// Today's running total (sum of the entries above).
$todayTotal = 0;
foreach ($todayEntries as $e) { $todayTotal += (int)$e['amount_ml']; }

// Progress bar percentage (capped at 100 so it never overflows visually).
$progressPct = $target > 0 ? min(100, round($todayTotal / $target * 100)) : 0;

// A tiny helper: daily average over the last N days.
function daily_average(PDO $db, int $userId, int $days): int {
    $from = date('Y-m-d', strtotime("-" . ($days - 1) . " days"));
    $stmt = $db->prepare(
        "SELECT COALESCE(SUM(amount_ml), 0) AS total
         FROM water_logs
         WHERE user_id = ? AND substr(logged_at, 1, 10) >= ?"
    );
    $stmt->execute([$userId, $from]);
    $total = (int)$stmt->fetch()['total'];
    return (int)round($total / $days);
}

$avgWeek  = daily_average($db, $userId, 7);
$avgMonth = daily_average($db, $userId, 30);
$avgYear  = daily_average($db, $userId, 365);

// Chart data: per-day totals for the last 14 days.
// One query to grab everything, then we fill gaps with zeros.
$from14 = date('Y-m-d', strtotime('-13 days'));
$stmt = $db->prepare(
    "SELECT substr(logged_at, 1, 10) AS day, COALESCE(SUM(amount_ml), 0) AS total
     FROM water_logs
     WHERE user_id = ? AND substr(logged_at, 1, 10) >= ?
     GROUP BY day"
);
$stmt->execute([$userId, $from14]);
$raw = [];
foreach ($stmt->fetchAll() as $row) {
    $raw[$row['day']] = (int)$row['total'];
}

$chartLabels = [];
$chartData   = [];
for ($i = 13; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-{$i} days"));
    $chartLabels[] = date('d M', strtotime($date));      // "24 Apr"
    $chartData[]   = $raw[$date] ?? 0;                    // 0 if no logs that day
}

$pageTitle = 'Water tracker';
require_once __DIR__ . '/includes/header.php';
?>

<h1>💧 Water tracker</h1>
<p class="tagline">Log your drinks and watch your hydration trends over time.</p>

<?php foreach ($errors as $err): ?>
    <div class="error"><?= htmlspecialchars($err) ?></div>
<?php endforeach; ?>
<?php if ($success !== ''): ?>
    <div class="success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<!-- TODAY'S TOTAL ---------------------------------------------------- -->
<div class="card">
    <h2>Today</h2>

    <div class="stat-row">
        <div class="stat-label">
            Progress
            <span class="stat-value">
                <?= number_format($todayTotal) ?> / <?= number_format($target) ?> ml
                (<?= $progressPct ?>%)
            </span>
        </div>
        <div class="progress">
            <div class="progress-bar" style="width: <?= $progressPct ?>%"></div>
        </div>
    </div>
</div>

<!-- LOG A DRINK ------------------------------------------------------- -->
<div class="card">
    <h2>Log a drink</h2>

    <!-- Quick-add buttons: one-click logging for common amounts. -->
    <div class="quick-add">
        <?php foreach ([250, 500, 750, 1000] as $ml): ?>
            <form method="post" style="display: inline;">
                <input type="hidden" name="action"    value="add">
                <input type="hidden" name="amount_ml" value="<?= $ml ?>">
                <button type="submit" class="btn-quick">+<?= $ml ?> ml</button>
            </form>
        <?php endforeach; ?>
    </div>

    <!-- Custom form for any other amount or a back-dated entry. -->
    <form method="post" style="margin-top: 1rem;">
        <input type="hidden" name="action" value="add">

        <label>Amount (ml)</label>
        <input type="number" name="amount_ml" min="1" max="5000" step="1"
               placeholder="e.g. 300" required>

        <label>Date &amp; time (optional — leave blank for now)</label>
        <input type="datetime-local" name="logged_at">

        <button type="submit">Log it</button>
    </form>
</div>

<!-- AVERAGES --------------------------------------------------------- -->
<div class="card">
    <h2>Your averages</h2>
    <div class="stat-grid">
        <div class="stat-box">
            <div class="stat-number"><?= number_format($avgWeek) ?></div>
            <div class="stat-caption">ml / day<br><small>last 7 days</small></div>
        </div>
        <div class="stat-box">
            <div class="stat-number"><?= number_format($avgMonth) ?></div>
            <div class="stat-caption">ml / day<br><small>last 30 days</small></div>
        </div>
        <div class="stat-box">
            <div class="stat-number"><?= number_format($avgYear) ?></div>
            <div class="stat-caption">ml / day<br><small>last year</small></div>
        </div>
    </div>
</div>

<!-- CHART: LAST 14 DAYS ---------------------------------------------- -->
<div class="card">
    <h2>Last 14 days</h2>
    <canvas id="waterChart" height="110"></canvas>
</div>

<!-- TODAY'S ENTRIES LIST --------------------------------------------- -->
<div class="card">
    <h2>Today's entries</h2>
    <?php if (empty($todayEntries)): ?>
        <p style="color: #6b7280;">Nothing logged yet today. Use the buttons above to start.</p>
    <?php else: ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Time</th>
                    <th>Amount</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($todayEntries as $e): ?>
                    <tr>
                        <td><?= htmlspecialchars(date('H:i', strtotime($e['logged_at']))) ?></td>
                        <td><?= number_format((int)$e['amount_ml']) ?> ml</td>
                        <td>
                            <form method="post" style="display: inline;"
                                  onsubmit="return confirm('Delete this entry?');">
                                <input type="hidden" name="action"   value="delete">
                                <input type="hidden" name="entry_id" value="<?= (int)$e['id'] ?>">
                                <button type="submit" class="btn-link-danger">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<!-- Chart.js from the CDN — tiny, no install needed. -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
<script>
// Pull the PHP arrays into JavaScript via json_encode (safe).
const labels = <?= json_encode($chartLabels) ?>;
const data   = <?= json_encode($chartData) ?>;
const target = <?= (int)$target ?>;

new Chart(document.getElementById('waterChart').getContext('2d'), {
    type: 'bar',
    data: {
        labels: labels,
        datasets: [{
            label: 'Water (ml)',
            data: data,
            backgroundColor: '#60a5fa',
            borderRadius: 4,
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { display: false },
            tooltip: {
                callbacks: {
                    label: (ctx) => ctx.parsed.y.toLocaleString() + ' ml'
                }
            },
            // Draw a horizontal line at the user's daily target.
            annotation: undefined
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: (v) => v.toLocaleString() + ' ml'
                }
            }
        }
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
