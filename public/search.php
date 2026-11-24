<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
$page_title = 'Search Schedule';

$stations = $pdo->query("SELECT station_id, station_name, station_code FROM stations ORDER BY station_name")->fetchAll();
$stationIds = array_column($stations, 'station_id');

$mode = $_GET['mode'] ?? 'route';
if (!in_array($mode, ['route', 'station', 'time'], true)) {
    $mode = 'route';
}

$timeBands = [
    'early'     => ['label' => 'Early morning', 'range' => '12am - 6am',     'start' => '00:00:00', 'end' => '05:59:59'],
    'morning'   => ['label' => 'Morning',       'range' => '6am - 12pm',     'start' => '06:00:00', 'end' => '11:59:59'],
    'afternoon' => ['label' => 'Afternoon',     'range' => '12pm - 6pm',     'start' => '12:00:00', 'end' => '17:59:59'],
    'evening'   => ['label' => 'Evening',       'range' => '6pm - midnight', 'start' => '18:00:00', 'end' => '23:59:59'],
];

$results   = [];
$searched  = false;
$errorMsg  = null;

// ---- Mode: Route (origin + destination) ----------------------------------
$originId = isset($_GET['origin_id']) ? (int) $_GET['origin_id'] : 0;
$destId   = isset($_GET['destination_id']) ? (int) $_GET['destination_id'] : 0;

if ($mode === 'route' && $originId && $destId) {
    $searched = true;
    if ($originId === $destId) {
        $errorMsg = 'Origin and destination are the same station. Pick two different stations to search a route.';
    } else {
        $stmt = $pdo->prepare("
            SELECT t.train_no, t.line_name,
                   so.departure_time AS dep_time, so.platform_no AS dep_platform, so.status AS dep_status, so.delay_minutes AS dep_delay,
                   sd.arrival_time AS arr_time, sd.platform_no AS arr_platform, sd.status AS arr_status
            FROM trains t
            JOIN schedules so ON so.train_id = t.train_id AND so.station_id = :origin AND so.departure_time IS NOT NULL
            JOIN schedules sd ON sd.train_id = t.train_id AND sd.station_id = :destination AND sd.arrival_time IS NOT NULL
            WHERE so.departure_time < sd.arrival_time
            ORDER BY so.departure_time ASC
        ");
        $stmt->execute(['origin' => $originId, 'destination' => $destId]);
        $results = $stmt->fetchAll();
    }
}

// ---- Mode: Station (single station board) ---------------------------------
$searchStationId = isset($_GET['station_id']) ? (int) $_GET['station_id'] : 0;

if ($mode === 'station' && $searchStationId) {
    $searched = true;
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
    $stmt->execute(['station_id' => $searchStationId]);
    $results = $stmt->fetchAll();
}

// ---- Mode: Time (time-of-day band, optional station) ----------------------
$band        = $_GET['band'] ?? '';
$timeStationId = isset($_GET['time_station_id']) ? (int) $_GET['time_station_id'] : 0;

if ($mode === 'time' && isset($timeBands[$band])) {
    $searched = true;
    $sql = "
        SELECT t.train_no, t.line_name, s.station_name,
               sch.platform_no, sch.arrival_time, sch.departure_time, sch.status, sch.delay_minutes
        FROM schedules sch
        JOIN trains t ON t.train_id = sch.train_id
        JOIN stations s ON s.station_id = sch.station_id
        WHERE TIME(COALESCE(sch.departure_time, sch.arrival_time)) BETWEEN :start AND :end
    ";
    $params = ['start' => $timeBands[$band]['start'], 'end' => $timeBands[$band]['end']];
    if ($timeStationId) {
        $sql .= " AND sch.station_id = :station_id";
        $params['station_id'] = $timeStationId;
    }
    $sql .= " ORDER BY COALESCE(sch.departure_time, sch.arrival_time) ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $results = $stmt->fetchAll();
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="pis-hero">
    <div class="pis-hero-copy">
        <div class="pis-hero-eyebrow"><span class="pis-dot"></span> Journey planning &mdash; <span class="pis-live-clock" data-pis-clock></span></div>
        <h1>Search the schedule</h1>
        <p>Find a train by the route you're taking, the station you're waiting at, or the time of day you want to travel.</p>
    </div>
</div>

<div class="pis-mode-tabs">
    <a class="pis-mode-tab<?= $mode === 'route' ? ' is-active' : '' ?>" href="search.php?mode=route">By route</a>
    <a class="pis-mode-tab<?= $mode === 'station' ? ' is-active' : '' ?>" href="search.php?mode=station">By station</a>
    <a class="pis-mode-tab<?= $mode === 'time' ? ' is-active' : '' ?>" href="search.php?mode=time">By time</a>
</div>

<div class="pis-search-panel">
    <div class="pis-search-panel-head">TRAIN ENQUIRY</div>

    <?php if ($mode === 'route'): ?>
        <form class="pis-search-form" method="get" action="search.php">
            <input type="hidden" name="mode" value="route">
            <div class="pis-field">
                <label for="origin_id">From</label>
                <select class="pis-select" id="origin_id" name="origin_id" required>
                    <option value="">Select station&hellip;</option>
                    <?php foreach ($stations as $s): ?>
                        <option value="<?= (int) $s['station_id'] ?>" <?= $originId === (int) $s['station_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($s['station_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="pis-field">
                <label for="destination_id">To</label>
                <select class="pis-select" id="destination_id" name="destination_id" required>
                    <option value="">Select station&hellip;</option>
                    <?php foreach ($stations as $s): ?>
                        <option value="<?= (int) $s['station_id'] ?>" <?= $destId === (int) $s['station_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($s['station_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn-signal">Find trains</button>
        </form>

    <?php elseif ($mode === 'station'): ?>
        <form class="pis-search-form" method="get" action="search.php">
            <input type="hidden" name="mode" value="station">
            <div class="pis-field">
                <label for="station_id">Station</label>
                <select class="pis-select" id="station_id" name="station_id" required>
                    <option value="">Select station&hellip;</option>
                    <?php foreach ($stations as $s): ?>
                        <option value="<?= (int) $s['station_id'] ?>" <?= $searchStationId === (int) $s['station_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($s['station_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn-signal">Show board</button>
        </form>

    <?php else: ?>
        <form class="pis-search-form" method="get" action="search.php">
            <input type="hidden" name="mode" value="time">
            <div class="pis-field">
                <label for="band">Time of day</label>
                <select class="pis-select" id="band" name="band" required>
                    <option value="">Select a window&hellip;</option>
                    <?php foreach ($timeBands as $key => $b): ?>
                        <option value="<?= htmlspecialchars($key) ?>" <?= $band === $key ? 'selected' : '' ?>>
                            <?= htmlspecialchars($b['label']) ?> (<?= htmlspecialchars($b['range']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="pis-field">
                <label for="time_station_id">Station (optional)</label>
                <select class="pis-select" id="time_station_id" name="time_station_id">
                    <option value="">Any station</option>
                    <?php foreach ($stations as $s): ?>
                        <option value="<?= (int) $s['station_id'] ?>" <?= $timeStationId === (int) $s['station_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($s['station_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn-signal">Find trains</button>
        </form>
    <?php endif; ?>
</div>

<?php if ($searched): ?>
    <div class="pis-board-heading" style="margin-top: 1.75rem;">
        <h2>Results</h2>
        <span class="pis-board-clock"><?= count($results) ?> match<?= count($results) === 1 ? '' : 'es' ?></span>
    </div>

    <?php if ($errorMsg): ?>
        <div class="pis-empty-state">
            <strong>Can't search that</strong>
            <?= htmlspecialchars($errorMsg) ?>
        </div>

    <?php elseif (empty($results)): ?>
        <div class="pis-empty-state">
            <strong>No trains found</strong>
            <?php if ($mode === 'route'): ?>
                No direct train runs between these two stations today. Try a different station pair, or search by station instead to see everything passing through one of them.
            <?php elseif ($mode === 'station'): ?>
                Nothing is booked through this station today.
            <?php else: ?>
                No trains depart or arrive in this window today. Try a different time of day.
            <?php endif; ?>
        </div>

    <?php elseif ($mode === 'route'): ?>
        <div class="departure-board">
            <table>
                <thead>
                    <tr>
                        <th>Train</th>
                        <th>Line</th>
                        <th>Departs</th>
                        <th>Platform</th>
                        <th>Arrives</th>
                        <th>Platform</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($results as $row):
                    [$effStatus, $effDelay] = pis_effective_status($row['dep_status'], (int) $row['dep_delay'], $row['dep_time']);
                ?>
                    <tr>
                        <td><?= htmlspecialchars($row['train_no']) ?></td>
                        <td><?= htmlspecialchars($row['line_name']) ?></td>
                        <td><?= htmlspecialchars(date('H:i', strtotime($row['dep_time']))) ?></td>
                        <td><?= htmlspecialchars($row['dep_platform'] ?? '-') ?></td>
                        <td><?= htmlspecialchars(date('H:i', strtotime($row['arr_time']))) ?></td>
                        <td><?= htmlspecialchars($row['arr_platform'] ?? '-') ?></td>
                        <td class="status-<?= htmlspecialchars($effStatus) ?>">
                            <?= htmlspecialchars(pis_status_label($effStatus)) ?>
                            <?= $effDelay > 0 ? " (+{$effDelay}m)" : '' ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    <?php elseif ($mode === 'station'):
        $currentStationName = '';
        foreach ($stations as $s) {
            if ((int) $s['station_id'] === $searchStationId) { $currentStationName = $s['station_name']; break; }
        }
    ?>
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
                <?php foreach ($results as $row):
                    $isOrigin   = $row['origin_name'] === $currentStationName;
                    $isDest     = $row['destination_name'] === $currentStationName;
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
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    <?php else: ?>
        <div class="departure-board">
            <table>
                <thead>
                    <tr>
                        <th>Train</th>
                        <th>Line</th>
                        <th>Station</th>
                        <th>Platform</th>
                        <th>Time</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($results as $row):
                    $referenceTime = $row['departure_time'] ?? $row['arrival_time'];
                    [$effStatus, $effDelay] = pis_effective_status($row['status'], (int) $row['delay_minutes'], $referenceTime);
                ?>
                    <tr>
                        <td><?= htmlspecialchars($row['train_no']) ?></td>
                        <td><?= htmlspecialchars($row['line_name']) ?></td>
                        <td><?= htmlspecialchars($row['station_name']) ?></td>
                        <td><?= htmlspecialchars($row['platform_no'] ?? '-') ?></td>
                        <td><?= htmlspecialchars(date('H:i', strtotime($referenceTime))) ?></td>
                        <td class="status-<?= htmlspecialchars($effStatus) ?>">
                            <?= htmlspecialchars(pis_status_label($effStatus)) ?>
                            <?= $effDelay > 0 ? " (+{$effDelay}m)" : '' ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
