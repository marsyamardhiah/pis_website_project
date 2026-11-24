<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_login(); // <-- every admin page must call this before any HTML output

$page_title = 'Admin Dashboard';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3">Welcome, <?= htmlspecialchars(current_admin_name()) ?></h1>
    <a href="logout.php" class="btn btn-outline-danger btn-sm">Log Out</a>
</div>

<div class="row g-3">
    <div class="col-md-3">
        <div class="card text-center p-3">
            <h2 class="h5">Trains</h2>
            <a href="manage_trains.php" class="btn btn-primary btn-sm mt-2">Manage</a>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center p-3">
            <h2 class="h5">Delays &amp; Alerts</h2>
            <a href="manage_alerts.php" class="btn btn-primary btn-sm mt-2">Manage</a>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center p-3">
            <h2 class="h5">Stations</h2>
            <a href="manage_stations.php" class="btn btn-primary btn-sm mt-2">Manage</a>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center p-3">
            <h2 class="h5">Staff Accounts</h2>
            <a href="manage_users.php" class="btn btn-primary btn-sm mt-2">Manage</a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
