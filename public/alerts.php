
<?php
require_once __DIR__ . '/../config/db.php';

$page_title = 'Service Alerts';

$sql = "SELECT
            alerts.alert_id,
            alerts.message,
            alerts.severity,
            alerts.created_at,
            stations.station_name
        FROM alerts
        LEFT JOIN stations
        ON alerts.station_id = stations.station_id
        ORDER BY alerts.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute();
$alerts = $stmt->fetchAll();

$total_alerts = count($alerts);

require_once __DIR__ . '/../includes/header.php';
?>

<main class="pis-container pis-main">

    <!-- HERO SECTION -->
    <section class="pis-hero">
        <div class="pis-hero-copy">
            <span class="pis-hero-eyebrow">
                <span class="pis-dot"></span>
                PASSENGER INFORMATION SYSTEM
            </span>

            <h1>Service Alerts</h1>

            <p>
                Stay informed with the latest train announcements,
                delays and station updates.
            </p>
        </div>

        <div class="pis-hero-art">
            <span style="font-size: 5rem;">🔔</span>
        </div>
    </section>

    <!-- ALERT SUMMARY -->
    <section class="pis-stat-strip">
        <div class="pis-stat">
            <div class="pis-stat-value">
                <?php echo $total_alerts; ?>
            </div>
            <span class="pis-stat-label">Total Announcements</span>
        </div>

        <div class="pis-stat">
            <div class="pis-stat-value is-warning">
                <?php
                $warning_count = 0;
                foreach ($alerts as $alert) {
                    if ($alert['severity'] === 'warning') {
                        $warning_count++;
                    }
                }
                echo $warning_count;
                ?>
            </div>
            <span class="pis-stat-label">Warnings</span>
        </div>

        <div class="pis-stat">
            <div class="pis-stat-value is-danger">
                <?php
                $critical_count = 0;
                foreach ($alerts as $alert) {
                    if ($alert['severity'] === 'critical') {
                        $critical_count++;
                    }
                }
                echo $critical_count;
                ?>
            </div>
            <span class="pis-stat-label">Critical Alerts</span>
        </div>
    </section>

    <!-- ALERT LIST -->
    <section class="pis-section">

        <div class="pis-board-heading">
            <h2>Latest Notifications</h2>
            <span class="pis-muted">Latest first</span>
        </div>

        <?php if (!empty($alerts)): ?>

            <ul class="pis-alert-list">

                <?php foreach ($alerts as $alert): ?>

                    <?php
                    $severity = $alert['severity'];

                    $alert_class = in_array(
                        $severity,
                        ['warning', 'critical'],
                        true
                    ) ? 'is-' . $severity : '';
                    ?>

                    <li class="pis-alert-item <?php echo $alert_class; ?>">

                        <span class="pis-alert-level">
                            <?php echo htmlspecialchars($severity); ?>
                        </span>

                        <div style="flex: 1; min-width: 0;">

                            <p class="pis-alert-msg">
                                <?php echo htmlspecialchars($alert['message']); ?>
                            </p>

                            <div class="pis-tags" style="margin-top: 0.6rem;">

                                <span class="pis-muted">
                                    📍
                                    <?php echo htmlspecialchars(
                                        $alert['station_name'] ?? 'All Stations'
                                    ); ?>
                                </span>

                            </div>

                            <time class="pis-alert-time">
                                🕒
                                <?php echo htmlspecialchars($alert['created_at']); ?>
                            </time>

                        </div>

                    </li>

                <?php endforeach; ?>

            </ul>

        <?php else: ?>

            <div class="pis-empty-state">
                <strong>All Clear!</strong>
                There are currently no service alerts.
                Please check again later for updates.
            </div>

        <?php endif; ?>

    </section>

    <!-- FOOTNOTE -->
    <section class="pis-cta">
        <div>
            <h2>Plan Your Journey</h2>
            <p>Check the latest train departures before travelling.</p>
        </div>

        <a href="departures.php" class="btn-signal">
            View Departures →
        </a>
    </section>

</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
