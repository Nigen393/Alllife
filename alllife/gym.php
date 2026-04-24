<?php
// gym.php — visible in the nav and dashboard for demonstration purposes.
// Full gym-tracker functionality is a planned future enhancement.

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
$pageTitle = 'Gym tracker';
require_once __DIR__ . '/includes/header.php';
?>

<h1>💪 Gym tracker</h1>
<p class="tagline">Log workouts, track your progress, and compare sessions over time.</p>

<div class="card">
    <h2>Planned feature</h2>
    <p>
        The gym tracker is part of AllLife's planned feature set. When fully built it will let you:
    </p>
    <ul>
        <li>Record a workout session (name, date, notes)</li>
        <li>Log the exercises you did — sets, reps, and weight for each</li>
        <li>See past sessions and your progress on each exercise over time</li>
        <li>Compare your current performance against previous attempts</li>
    </ul>
    <p>
        The database schema and navigation for this feature are already in place.
        The user-facing interface is the next step in the project roadmap.
    </p>

    <p>
        <a href="dashboard.php">← Back to dashboard</a>
    </p>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
