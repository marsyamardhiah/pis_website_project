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

// Keeps the board current without the passenger lifting a finger
$extra_head = '<meta http-equiv="refresh" content="30">';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="pis-hero">
    <div class="pis-hero-copy">
        <div class="pis-hero-eyebrow"><span class="pis-dot"></span> Live board, <span class="pis-live-clock" data-pis-clock></span> &mdash; refreshes every 30s</div>
        <h1>Departures &amp; arrivals</h1>
        <p>Every train calling at a station, in the order it arrives or leaves &mdash; pick a station below to see its board.</p>
    </div>
</div>

<div class="pis-stat-strip" style="border-top: none;">
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

<div class="departure-board">
    <table>
        <thead>
            <tr>
                <th>Train</th>
                <th>Line</th>
                <th>Working</th>
                <th>Platform</th>
                <th>Arrives</th>
                <th>Departs</th>
                <th>Status</th>
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
    <span>Last updated <span class="pis-live-clock" data-pis-clock></span></span>
    <span>Status updates automatically as each scheduled time passes &mdash; a human overrides it only for a real delay or cancellation.</span>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
