<?php
// calories.php — the full calorie tracker.
// Lets the user:
//   • look up a product by barcode (via the Open Food Facts free API)
//     and auto-fill the meal name + calories
//   • log a meal (name + calorie count) manually if they prefer
//   • see today's entries (with delete)
//   • see 7/30/365-day averages
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
        $stmt = $db->prepare('DELETE FROM calorie_logs WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);
        $success = 'Entry removed.';
    }

    // --- Add a new meal ---
    if ($action === 'add') {
        $mealName = trim($_POST['meal_name'] ?? '');
        $cals     = (int)($_POST['calories'] ?? 0);
        $loggedAt = trim($_POST['logged_at'] ?? '');

        if ($mealName === '') $errors[] = 'Meal name is required.';
        if ($cals < 1 || $cals > 10000) $errors[] = 'Calories must be between 1 and 10000.';

        if ($loggedAt !== '') {
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
                    'INSERT INTO calorie_logs (user_id, meal_name, calories, logged_at)
                     VALUES (?, ?, ?, ?)'
                );
                $stmt->execute([$userId, $mealName, $cals, $loggedAt]);
            } else {
                $stmt = $db->prepare(
                    'INSERT INTO calorie_logs (user_id, meal_name, calories) VALUES (?, ?, ?)'
                );
                $stmt->execute([$userId, $mealName, $cals]);
            }
            $success = 'Logged "' . $mealName . '" (' . number_format($cals) . ' kcal).';
        }
    }
}

// ----------------------------------------------------------------------
// 2. Fetch data for rendering
// ----------------------------------------------------------------------

$stmt = $db->prepare('SELECT calorie_target FROM users WHERE id = ?');
$stmt->execute([$userId]);
$target = (int)$stmt->fetch()['calorie_target'];

$today = date('Y-m-d');
$stmt = $db->prepare(
    "SELECT id, meal_name, calories, logged_at
     FROM calorie_logs
     WHERE user_id = ? AND substr(logged_at, 1, 10) = ?
     ORDER BY logged_at DESC, id DESC"
);
$stmt->execute([$userId, $today]);
$todayEntries = $stmt->fetchAll();

$todayTotal = 0;
foreach ($todayEntries as $e) { $todayTotal += (int)$e['calories']; }

$progressPct = $target > 0 ? min(100, round($todayTotal / $target * 100)) : 0;

// Daily average over the last N days.
function daily_calorie_average(PDO $db, int $userId, int $days): int {
    $from = date('Y-m-d', strtotime("-" . ($days - 1) . " days"));
    $stmt = $db->prepare(
        "SELECT COALESCE(SUM(calories), 0) AS total
         FROM calorie_logs
         WHERE user_id = ? AND substr(logged_at, 1, 10) >= ?"
    );
    $stmt->execute([$userId, $from]);
    $total = (int)$stmt->fetch()['total'];
    return (int)round($total / $days);
}

$avgWeek  = daily_calorie_average($db, $userId, 7);
$avgMonth = daily_calorie_average($db, $userId, 30);
$avgYear  = daily_calorie_average($db, $userId, 365);

// 14-day chart data (grouped in one query, then gap-filled).
$from14 = date('Y-m-d', strtotime('-13 days'));
$stmt = $db->prepare(
    "SELECT substr(logged_at, 1, 10) AS day, COALESCE(SUM(calories), 0) AS total
     FROM calorie_logs
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
    $chartLabels[] = date('d M', strtotime($date));
    $chartData[]   = $raw[$date] ?? 0;
}

$pageTitle = 'Calorie tracker';
require_once __DIR__ . '/includes/header.php';
?>

<h1>🍎 Calorie tracker</h1>
<p class="tagline">Log meals — type them manually or look up a barcode.</p>

<?php foreach ($errors as $err): ?>
    <div class="error"><?= htmlspecialchars($err) ?></div>
<?php endforeach; ?>
<?php if ($success !== ''): ?>
    <div class="success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<!-- TODAY'S PROGRESS -------------------------------------------------- -->
<div class="card">
    <h2>Today</h2>

    <div class="stat-row">
        <div class="stat-label">
            Calories consumed
            <span class="stat-value">
                <?= number_format($todayTotal) ?> / <?= number_format($target) ?> kcal
                (<?= $progressPct ?>%)
            </span>
        </div>
        <div class="progress">
            <div class="progress-bar" style="width: <?= $progressPct ?>%"></div>
        </div>
    </div>
</div>

<!-- LOG A MEAL ------------------------------------------------------- -->
<div class="card">
    <h2>Log a meal</h2>

    <!--
        Barcode lookup (optional). JavaScript hits the Open Food Facts API and
        fills in the meal name + calories for you.  You can still edit them
        before submitting.
    -->
    <div class="barcode-row">
        <input type="text" id="barcode" placeholder="Barcode e.g. 5000159407236"
               autocomplete="off">
        <button type="button" id="lookup-btn" class="btn-quick">Look up</button>
    </div>
    <div id="lookup-status" class="lookup-status"></div>

    <!-- The actual log form. -->
    <form method="post" id="log-form">
        <input type="hidden" name="action" value="add">

        <label>Meal name</label>
        <input type="text" name="meal_name" id="meal_name"
               placeholder="e.g. Chicken sandwich" required>

        <label>Calories (kcal)</label>
        <input type="number" name="calories" id="calories"
               min="1" max="10000" step="1" placeholder="e.g. 450" required>

        <label>Date &amp; time (optional — leave blank for now)</label>
        <input type="datetime-local" name="logged_at">

        <button type="submit">Log meal</button>
    </form>

    <p class="help-text">
        Barcode lookup uses the free
        <a href="https://world.openfoodfacts.org" target="_blank" rel="noopener">Open Food Facts</a>
        database. Some products may not be listed — you can always type meals manually.
    </p>
</div>

<!-- AVERAGES --------------------------------------------------------- -->
<div class="card">
    <h2>Your averages</h2>
    <div class="stat-grid">
        <div class="stat-box">
            <div class="stat-number"><?= number_format($avgWeek) ?></div>
            <div class="stat-caption">kcal / day<br><small>last 7 days</small></div>
        </div>
        <div class="stat-box">
            <div class="stat-number"><?= number_format($avgMonth) ?></div>
            <div class="stat-caption">kcal / day<br><small>last 30 days</small></div>
        </div>
        <div class="stat-box">
            <div class="stat-number"><?= number_format($avgYear) ?></div>
            <div class="stat-caption">kcal / day<br><small>last year</small></div>
        </div>
    </div>
</div>

<!-- CHART ------------------------------------------------------------ -->
<div class="card">
    <h2>Last 14 days</h2>
    <canvas id="calorieChart" height="110"></canvas>
</div>

<!-- TODAY'S ENTRIES ------------------------------------------------- -->
<div class="card">
    <h2>Today's meals</h2>
    <?php if (empty($todayEntries)): ?>
        <p style="color: #6b7280;">Nothing logged yet today.</p>
    <?php else: ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Time</th>
                    <th>Meal</th>
                    <th>Calories</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($todayEntries as $e): ?>
                    <tr>
                        <td><?= htmlspecialchars(date('H:i', strtotime($e['logged_at']))) ?></td>
                        <td><?= htmlspecialchars($e['meal_name']) ?></td>
                        <td><?= number_format((int)$e['calories']) ?> kcal</td>
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

<!-- Chart.js from the CDN (same one water.php uses — browsers cache it). -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
<script>
// ------- 14-day calorie chart -------------------------------------------
new Chart(document.getElementById('calorieChart').getContext('2d'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($chartLabels) ?>,
        datasets: [{
            label: 'Calories',
            data: <?= json_encode($chartData) ?>,
            backgroundColor: '#fbbf24',
            borderRadius: 4,
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { display: false },
            tooltip: {
                callbacks: { label: (ctx) => ctx.parsed.y.toLocaleString() + ' kcal' }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: { callback: (v) => v.toLocaleString() + ' kcal' }
            }
        }
    }
});

// ------- Barcode lookup via Open Food Facts API -------------------------
const lookupBtn = document.getElementById('lookup-btn');
const barcodeInp = document.getElementById('barcode');
const statusEl  = document.getElementById('lookup-status');
const mealInp   = document.getElementById('meal_name');
const calsInp   = document.getElementById('calories');

async function doLookup() {
    const code = barcodeInp.value.trim();
    if (!code) {
        statusEl.textContent = 'Please type a barcode first.';
        statusEl.className = 'lookup-status error-text';
        return;
    }

    statusEl.textContent = 'Looking up…';
    statusEl.className = 'lookup-status muted';

    try {
        const resp = await fetch(
            `https://world.openfoodfacts.org/api/v2/product/${encodeURIComponent(code)}.json`
        );
        const data = await resp.json();

        if (data.status !== 1 || !data.product) {
            statusEl.textContent = 'Product not found. Type the meal manually below.';
            statusEl.className = 'lookup-status error-text';
            return;
        }

        const p = data.product;
        const name = p.product_name || p.generic_name || 'Unknown product';
        const brand = p.brands ? ' (' + p.brands + ')' : '';
        // Open Food Facts stores nutrients per 100g.
        const kcalPer100 = p.nutriments && p.nutriments['energy-kcal_100g'];

        mealInp.value = name + brand;

        if (kcalPer100) {
            calsInp.value = Math.round(kcalPer100);
            statusEl.innerHTML =
                'Found: <strong>' + name + brand + '</strong> — ' +
                Math.round(kcalPer100) + ' kcal per 100g. ' +
                'Adjust the calorie number to match your actual portion size.';
            statusEl.className = 'lookup-status success-text';
        } else {
            statusEl.textContent =
                'Found "' + name + '" but no calorie data on file. Enter calories manually.';
            statusEl.className = 'lookup-status error-text';
        }
    } catch (err) {
        statusEl.textContent = 'Lookup failed — are you online? Enter meal manually.';
        statusEl.className = 'lookup-status error-text';
    }
}

lookupBtn.addEventListener('click', doLookup);
// Also trigger lookup when pressing Enter inside the barcode field.
barcodeInp.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') { e.preventDefault(); doLookup(); }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

/* ---------- Barcode lookup (calorie tracker) ---------- */
.barcode-row {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
    align-items: center;
}
.barcode-row input[type="text"] {
    flex: 1;
    min-width: 200px;
    padding: 0.55rem 0.7rem;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    font: inherit;
}
.lookup-status {
    margin-top: 0.6rem;
    font-size: 0.92rem;
    min-height: 1.25rem;
}
.lookup-status.muted      { color: #6b7280; }
.lookup-status.error-text { color: #991b1b; }
.lookup-status.success-text {
    color: #166534;
    background: #dcfce7;
    padding: 0.5rem 0.7rem;
    border-radius: 8px;
}

.help-text {
    color: #6b7280;
    font-size: 0.85rem;
    margin-top: 1rem;
}