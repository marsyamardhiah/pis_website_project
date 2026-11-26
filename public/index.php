<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
$page_title = 'Home';

// Embedded (internal) style sheet — only this page needs it, so it goes
// through header.php's $extra_head slot rather than into the shared
// external style.css. This is deliberately the 3rd stylesheet type on
// this page: style.css (external) + the style="" below (inline) + this.
$extra_head = '<style>
    /* Embedded style sheet: scoped to index.php only */
    .pis-alert-btn {
        background: var(--pis-red);
        color: #fff;
    }
    .pis-alert-btn:hover {
        background: #a90d26;
    }
</style>';

// Fetch the next few genuinely-upcoming departures (a 5-minute grace window
// keeps a train that just started boarding from vanishing mid-board).
// Compared by TIME-of-day only, same reasoning as pis_effective_status():
// schedules repeat daily, so the calendar date stored on each row doesn't
// matter and can't go stale.
$stmt = $pdo->prepare("
    SELECT t.train_no, t.line_name, s.station_name, sch.platform_no,
           sch.departure_time, sch.status, sch.delay_minutes
    FROM schedules sch
    JOIN trains t ON t.train_id = sch.train_id
    JOIN stations s ON s.station_id = sch.station_id
    WHERE sch.departure_time IS NOT NULL AND TIME(sch.departure_time) >= :cutoff
    ORDER BY TIME(sch.departure_time) ASC
    LIMIT 5
");
$stmt->execute(['cutoff' => date('H:i:s', strtotime('-5 minutes'))]);
$upcoming = $stmt->fetchAll();

// Quick stats for the summary strip
$stationCount = (int) $pdo->query("SELECT COUNT(*) FROM stations")->fetchColumn();
$trainCount   = (int) $pdo->query("SELECT COUNT(*) FROM trains")->fetchColumn();

// "Active alerts" — an alert tied to a specific schedule (schedule_id is
// set) can self-expire the same way "Delayed now" does, by checking that
// schedule's own time. A standalone alert (schedule_id is NULL, e.g. a
// general platform notice) has no time of its own stored anywhere, so
// there's nothing to compare against — it's always counted until an
// admin deletes it or the table gains a real expires_at/resolved field.
$alertsStmt = $pdo->prepare("
    SELECT COUNT(*) FROM alerts a
    LEFT JOIN schedules sch ON sch.schedule_id = a.schedule_id
    WHERE a.schedule_id IS NULL
       OR TIME(COALESCE(sch.departure_time, sch.arrival_time)) >= :cutoff
");
$alertsStmt->execute(['cutoff' => date('H:i:s', strtotime('-3 minutes'))]);
$activeAlerts = (int) $alertsStmt->fetchColumn();

// "Delayed now" — only count a delayed train while its own time hasn't
// clearly passed yet (same 3-minute grace pis_effective_status() uses
// before calling a train "departed"), so it drops off on its own instead
// of staying flagged forever until an admin manually clears it.
$delayedStmt = $pdo->prepare("
    SELECT COUNT(*) FROM schedules
    WHERE status = 'delayed'
      AND TIME(COALESCE(departure_time, arrival_time)) >= :cutoff
");
$delayedStmt->execute(['cutoff' => date('H:i:s', strtotime('-3 minutes'))]);
$delayedCount = (int) $delayedStmt->fetchColumn();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="pis-hero">
    <div class="pis-hero-art">
        <img src="<?= $pis_base ?>/assets/img/hero-train.svg" alt="" width="470" height="200">
    </div>
    <div class="pis-hero-copy">
        <div class="pis-hero-eyebrow"><span class="pis-dot"></span> Network status &mdash; live, <span class="pis-live-clock" data-pis-clock></span></div>
        <h1>Passenger Information System</h1>
        <p>Departures, arrivals and service alerts for every station on the network, updated as they happen.</p>
        <a href="departures.php" class="btn-signal">View full departure board</a>

        <div class="pis-next" id="pisNext">
            <span class="pis-next-label">Next departure</span>
            <?php if (!empty($upcoming)): $nx = $upcoming[0]; ?>
                <strong data-next-title><?= htmlspecialchars($nx['train_no']) ?> from <?= htmlspecialchars($nx['station_name']) ?></strong>
                <span class="pis-next-detail" data-next-detail>Platform <?= htmlspecialchars($nx['platform_no'] ?? '-') ?> &middot; <?= htmlspecialchars(date('H:i', strtotime($nx['departure_time']))) ?></span>
            <?php else: ?>
                <strong data-next-title>No more departures today</strong>
                <span class="pis-next-detail" data-next-detail>Check the board again tomorrow.</span>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="pis-stat-strip">
    <div class="pis-stat">
        <div class="pis-stat-value" data-stat="stations"><?= $stationCount ?></div>
        <span class="pis-stat-label">Stations</span>
    </div>
    <div class="pis-stat">
        <div class="pis-stat-value" data-stat="trains"><?= $trainCount ?></div>
        <span class="pis-stat-label">Trains in service</span>
    </div>
    <div class="pis-stat">
        <div class="pis-stat-value is-warning" data-stat="delayed"><?= $delayedCount ?></div>
        <span class="pis-stat-label">Delayed now</span>
    </div>
    <div class="pis-stat">
        <div class="pis-stat-value is-danger" data-stat="alerts"><?= $activeAlerts ?></div>
        <span class="pis-stat-label">Active alerts</span>
    </div>
</div>

<section class="pis-section" id="pisFavs" hidden aria-labelledby="favs-title">
    <h2 class="pis-section-title" id="favs-title">Your stations</h2>
    <div class="pis-station-chips" id="pisFavList"></div>
</section>

<div class="pis-board-heading">
    <h2>Upcoming departures</h2>
    <span class="pis-board-clock">Network-wide &middot; <span class="pis-live-clock" data-pis-clock></span></span>
</div>

<div class="departure-board" data-pis-live
     data-endpoint="api_board.php?mode=upcoming"
     data-columns="train,line,station,platform,departs,status"
     data-empty="No upcoming departures.">
    <table>
        <caption class="pis-sr-only">The next five departures across the network, with station, platform, departure time and status</caption>
        <thead>
            <tr>
                <th scope="col">Train</th>
                <th scope="col">Line</th>
                <th scope="col">Station</th>
                <th scope="col">Platform</th>
                <th scope="col">Departure</th>
                <th scope="col">Status</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($upcoming)): ?>
            <tr><td colspan="6">No upcoming departures.</td></tr>
        <?php else: foreach ($upcoming as $row):
            [$effStatus, $effDelay] = pis_effective_status($row['status'], (int) $row['delay_minutes'], $row['departure_time']);
        ?>
            <tr>
                <td><?= htmlspecialchars($row['train_no']) ?></td>
                <td><?= htmlspecialchars($row['line_name']) ?></td>
                <td><?= htmlspecialchars($row['station_name']) ?></td>
                <td><?= htmlspecialchars($row['platform_no'] ?? '-') ?></td>
                <td><?= htmlspecialchars(date('H:i', strtotime($row['departure_time']))) ?></td>
                <td class="status-<?= htmlspecialchars($effStatus) ?>">
                    <?= htmlspecialchars(pis_status_label($effStatus)) ?>
                    <?= $effDelay > 0 ? " (+{$effDelay}m)" : '' ?>
                </td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<p style="margin: 0.75rem 0 0;">
    <button type="button" class="pis-refresh-btn" data-pis-refresh="30" aria-pressed="false">Pause auto-refresh</button>
    <span id="pisLiveMsg" class="pis-muted" role="status" aria-live="polite"></span>
</p>

<button class="pis-pa-button"
        onclick="announce('Welcome to the Passenger Information System. Please check the departure board for live updates.')">
    🔊 Play welcome announcement
</button>

<!-- Inline style sheet: only this one button gets this nudge, so the
     declaration sits directly on the tag instead of in style.css -->
<button class="pis-pa-button pis-alert-btn" style="margin-left: 0.6rem;"
        onclick="alert(document.querySelector('[data-stat=delayed]').textContent + ' train(s) delayed and ' + document.querySelector('[data-stat=alerts]').textContent + ' active alert(s) right now.')">
    ⚠️ Show active alerts
</button>

<section class="pis-section" style="margin-top: 2.5rem;" aria-labelledby="tips-title">
    <h2 class="pis-section-title" id="tips-title">Travelling made easier</h2>
    <div class="pis-feature-grid">
        <article class="pis-feature">
            <img src="<?= $pis_base ?>/assets/img/platform-board.svg" alt="A train waiting at a platform beneath a hanging departure sign that lists destinations and their status." width="480" height="300">
            <div class="pis-feature-body">
                <h3>Find your platform</h3>
                <p>Every departure shows its platform number, so you know where to go before you reach the station. <a href="departures.php">Open the departure board</a>.</p>
            </div>
        </article>
        <article class="pis-feature">
            <img src="<?= $pis_base ?>/assets/img/live-clock.svg" alt="An analogue clock with a small digital display reading 10:42." width="480" height="300">
            <div class="pis-feature-body">
                <h3>Times you can trust</h3>
                <p>The clock and every status follow the real time, so a train that has left no longer shows as waiting.</p>
            </div>
        </article>
        <article class="pis-feature">
            <img src="<?= $pis_base ?>/assets/img/announcement.svg" alt="A loudspeaker with sound waves coming out of it." width="480" height="300">
            <div class="pis-feature-body">
                <h3>Hear it, don't just read it</h3>
                <p>Press &ldquo;Play welcome announcement&rdquo; above and your browser reads the message aloud. This helps when small print is hard to see.</p>
            </div>
        </article>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
