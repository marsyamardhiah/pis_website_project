<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
$page_title = 'Departures';

// Station picker — defaults to the first station alphabetically
$stations = $pdo->query("SELECT station_id, station_name, station_code FROM stations ORDER BY station_name")->fetchAll();

$selectedId = isset($_GET['station_id']) ? (int) $_GET['station_id'] : 0;
$validIds   = array_column($stations, 'station_id');
if (!in_array($selectedId, $validIds, true)) {
    $selectedId = $validIds[0] ?? 0;
}

$stmt = $pdo->prepare("
    SELECT t.train_no, t.line_name,
           os.station_name AS origin_name, ds.station_name AS destination_name,
           sch.platform_no, sch.arrival_time, sch.departure_time, sch.status, sch.delay_minutes
    FROM schedules sch
    JOIN trains t ON t.train_id = sch.train_id
    JOIN stations os ON os.station_id = t.origin_station_id
    JOIN stations ds ON ds.station_id = t.destination_station_id
    WHERE sch.station_id = :station_id
    ORDER BY COALESCE(sch.departure_time, sch.arrival_time) ASC
");
$stmt->execute(['station_id' => $selectedId]);
$board = $stmt->fetchAll();

$selectedStation = null;
foreach ($stations as $s) {
    if ((int) $s['station_id'] === $selectedId) { $selectedStation = $s; break; }
}

// The rows refresh themselves every 30 seconds with fetch() + JSON (script.js,
// api_board.php), without reloading the page, and the button below pauses it.
// Only if JavaScript is switched off do we fall back to a plain page refresh.
$extra_head = '<noscript><meta http-equiv="refresh" content="30"></noscript>';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="pis-hero">
    <div class="pis-hero-copy">
        <div class="pis-hero-eyebrow"><span class="pis-dot"></span> Live board, <span class="pis-live-clock" data-pis-clock></span> &mdash; refreshes every 30s</div>
        <h1>Departures &amp; arrivals</h1>
        <p>Every train calling at a station, in the order it arrives or leaves &mdash; pick a station below to see its board.</p>
    </div>
</div>

<div class="pis-chip-panel">
    <div class="pis-station-chips" style="margin: 0; padding: 1rem 1.15rem;">
        <?php foreach ($stations as $s): ?>
            <a class="pis-station-chip<?= (int) $s['station_id'] === $selectedId ? ' is-active' : '' ?>"
               href="departures.php?station_id=<?= (int) $s['station_id'] ?>">
                <?= htmlspecialchars($s['station_name']) ?>
                <span class="pis-station-code"><?= htmlspecialchars($s['station_code']) ?></span>
            </a>
        <?php endforeach; ?>
    </div>
</div>

<div class="pis-board-heading" style="margin-top: 1.75rem;">
    <h2><?= htmlspecialchars($selectedStation['station_name'] ?? 'Station') ?> board</h2>
    <span class="pis-board-clock"><?= htmlspecialchars($selectedStation['station_code'] ?? '') ?> &middot; <?= $board ? count($board) : 0 ?> train<?= count($board) === 1 ? '' : 's' ?> today</span>
</div>

<div class="pis-toolbar">
    <label for="boardSearch" class="pis-sr-only">Filter trains by number, line or destination</label>
    <input type="search" id="boardSearch" class="pis-input" placeholder="Filter by train, line or destination" autocomplete="off">
    <div class="pis-filter-chips" role="group" aria-label="Filter by status">
        <button type="button" class="pis-chip-btn" data-status-filter="all" aria-pressed="true">All</button>
        <button type="button" class="pis-chip-btn" data-status-filter="on_time" aria-pressed="false">On time</button>
        <button type="button" class="pis-chip-btn" data-status-filter="boarding" aria-pressed="false">Boarding</button>
        <button type="button" class="pis-chip-btn" data-status-filter="delayed" aria-pressed="false">Delayed</button>
        <button type="button" class="pis-chip-btn" data-status-filter="cancelled" aria-pressed="false">Cancelled</button>
        <button type="button" class="pis-chip-btn" data-status-filter="departed" aria-pressed="false">Departed</button>
    </div>
    <button type="button" class="pis-star" id="pisStarBtn" aria-pressed="false"
            data-station-id="<?= (int) $selectedId ?>"
            data-station-name="<?= htmlspecialchars($selectedStation['station_name'] ?? '') ?>">&#9734; Save station</button>
    <span class="pis-count" id="pisCount" aria-live="polite"></span>
</div>

<div class="departure-board" data-pis-live
     data-endpoint="api_board.php?mode=station&amp;station_id=<?= (int) $selectedId ?>"
     data-columns="train,line,working,platform,arrives,departs,status"
     data-empty="No trains are booked through this station today.">
    <table>
        <caption class="pis-sr-only">Trains calling at <?= htmlspecialchars($selectedStation['station_name'] ?? 'this station') ?>, with platform, arrival time, departure time and status</caption>
        <thead>
            <tr>
                <th scope="col">Train</th>
                <th scope="col">Line</th>
                <th scope="col">Working</th>
                <th scope="col">Platform</th>
                <th scope="col">Arrives</th>
                <th scope="col">Departs</th>
                <th scope="col">Status</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($board)): ?>
            <tr><td colspan="7">No trains are booked through this station today.</td></tr>
        <?php else: foreach ($board as $row):
            $isOrigin   = $row['origin_name'] === $selectedStation['station_name'];
            $isDest     = $row['destination_name'] === $selectedStation['station_name'];
            $originSafe = htmlspecialchars($row['origin_name']);
            $destSafe   = htmlspecialchars($row['destination_name']);
            if ($isOrigin) {
                $working = 'To ' . $destSafe;
            } elseif ($isDest) {
                $working = 'From ' . $originSafe;
            } else {
                $working = $originSafe . ' &rarr; ' . $destSafe;
            }
            $referenceTime = $row['departure_time'] ?? $row['arrival_time'];
            [$effStatus, $effDelay] = pis_effective_status($row['status'], (int) $row['delay_minutes'], $referenceTime);
        ?>
            <tr>
                <td><?= htmlspecialchars($row['train_no']) ?></td>
                <td><?= htmlspecialchars($row['line_name']) ?></td>
                <td><?= $working ?></td>
                <td><?= htmlspecialchars($row['platform_no'] ?? '-') ?></td>
                <td><?= $row['arrival_time'] ? htmlspecialchars(date('H:i', strtotime($row['arrival_time']))) : '&mdash;' ?></td>
                <td><?= $row['departure_time'] ? htmlspecialchars(date('H:i', strtotime($row['departure_time']))) : '&mdash;' ?></td>
                <td class="status-<?= htmlspecialchars($effStatus) ?>">
                    <?= htmlspecialchars(pis_status_label($effStatus)) ?>
                    <?= $effDelay > 0 ? " (+{$effDelay}m)" : '' ?>
                </td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<div class="pis-board-meta">
    <span>Last updated <span class="pis-fresh-stamp" data-pis-updated><?= date('H:i:s') ?></span></span>
    <span>Status updates automatically as each scheduled time passes &mdash; a human overrides it only for a real delay or cancellation.</span>
</div>

<p style="margin-top: 0.75rem;">
    <button type="button" class="pis-refresh-btn" data-pis-refresh="30" aria-pressed="false">Pause auto-refresh</button>
    <span id="pisLiveMsg" class="pis-muted" role="status" aria-live="polite"></span>
</p>

<section class="pis-section" style="margin-top: 2.25rem;" aria-labelledby="legend-title">
    <h2 class="pis-section-title" id="legend-title">How to read the status column</h2>
    <div class="pis-split">
        <img src="<?= $pis_base ?>/assets/img/status-legend.svg" alt="Key to the status colours: green for On time, blue for Boarding, amber for Delayed with the number of minutes, red for Cancelled and grey for Departed." width="480" height="300">
        <ul>
            <li><strong>On time</strong> &mdash; the train is running to its timetable.</li>
            <li><strong>Boarding</strong> &mdash; passengers can get on now.</li>
            <li><strong>Delayed (+ minutes)</strong> &mdash; set by staff; the number shows how late.</li>
            <li><strong>Cancelled</strong> &mdash; the train will not run.</li>
            <li><strong>Departed</strong> &mdash; the train has already left.</li>
        </ul>
    </div>
    <p class="pis-muted" style="max-width: 70ch;">Every status is also written in words, so you never have to rely on colour to understand it.</p>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
