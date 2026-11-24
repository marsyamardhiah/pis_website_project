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
    <div class="pis-hero-art" aria-hidden="true">
        <svg viewBox="0 0 220 120" xmlns="http://www.w3.org/2000/svg" role="img" aria-hidden="true">
            <!-- Signage-style ETS pictogram: line art only, inherits color via currentColor -->
            <g fill="none" stroke="currentColor" stroke-width="4" stroke-linecap="round" stroke-linejoin="round">
                <!-- pantograph -->
                <path d="M70 26 L82 10 L94 26" />
                <line x1="82" y1="26" x2="82" y2="34" />
                <!-- roof line -->
                <path d="M34 34 H196" />
                <!-- body + rounded nose -->
                <path d="M196 34 V78 H46 Q20 78 12 56 Q20 34 46 34 H196 Z" />
                <!-- cab window -->
                <rect x="24" y="44" width="22" height="18" rx="4" />
                <!-- passenger windows -->
                <rect x="64" y="44" width="30" height="18" rx="3" />
                <rect x="106" y="44" width="30" height="18" rx="3" />
                <rect x="148" y="44" width="30" height="18" rx="3" />
                <!-- livery stripe -->
                <line x1="20" y1="70" x2="196" y2="70" />
                <!-- wheels -->
                <circle cx="60" cy="90" r="10" />
                <circle cx="110" cy="90" r="10" />
                <circle cx="160" cy="90" r="10" />
                <!-- rail -->
                <path d="M4 104 H216" stroke-width="3" />
                <path d="M24 104 V112 M60 104 V112 M96 104 V112 M132 104 V112 M168 104 V112 M204 104 V112" stroke-width="3" />
            </g>
        </svg>
    </div>
    <div class="pis-hero-copy">
        <div class="pis-hero-eyebrow"><span class="pis-dot"></span> Network status &mdash; live, <span class="pis-live-clock" data-pis-clock></span></div>
        <h1>Passenger Information System</h1>
        <p>Departures, arrivals and service alerts for every station on the network, updated as they happen.</p>
        <a href="departures.php" class="btn-signal">View full departure board</a>
    </div>
</div>

<div class="pis-stat-strip">
    <div class="pis-stat">
        <div class="pis-stat-value"><?= $stationCount ?></div>
        <span class="pis-stat-label">Stations</span>
    </div>
    <div class="pis-stat">
        <div class="pis-stat-value"><?= $trainCount ?></div>
        <span class="pis-stat-label">Trains in service</span>
    </div>
    <div class="pis-stat">
        <div class="pis-stat-value is-warning"><?= $delayedCount ?></div>
        <span class="pis-stat-label">Delayed now</span>
    </div>
    <div class="pis-stat">
        <div class="pis-stat-value is-danger"><?= $activeAlerts ?></div>
        <span class="pis-stat-label">Active alerts</span>
    </div>
</div>

<div class="pis-board-heading">
    <h2>Upcoming departures</h2>
    <span class="pis-board-clock">Network-wide &middot; <span class="pis-live-clock" data-pis-clock></span></span>
</div>

<div class="departure-board">
    <table>
        <thead>
            <tr>
                <th>Train</th>
                <th>Line</th>
                <th>Station</th>
                <th>Platform</th>
                <th>Departure</th>
                <th>Status</th>
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

<button class="pis-pa-button"
        onclick="announce('Welcome to the Passenger Information System. Please check the departure board for live updates.')">
    🔊 Play welcome announcement
</button>

<!-- Inline style sheet: only this one button gets this nudge, so the
     declaration sits directly on the tag instead of in style.css -->
<button class="pis-pa-button pis-alert-btn" style="margin-left: 0.6rem;"
        onclick="alert('<?= addslashes($delayedCount) ?> train(s) delayed and <?= addslashes($activeAlerts) ?> active alert(s) right now.')">
    ⚠️ Show active alerts
</button>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
