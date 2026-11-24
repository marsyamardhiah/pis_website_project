<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

/*
 * Station Information (Member 3)
 *
 * Two views on one page:
 *   stations.php                 -> directory of every station
 *   stations.php?station_id=N    -> one station: platforms, notices, direct trains
 *
 * Schedules repeat daily, so "today" means time-of-day only — the same
 * reasoning as pis_effective_status() and the home page.
 */

// "To X" / "From X" / "X → Y" for a train calling at $stationId.
// Returns HTML-safe text.
function station_working_label(array $row, int $stationId): string
{
    $origin = htmlspecialchars($row['origin_name']);
    $dest   = htmlspecialchars($row['destination_name']);

    if ((int) $row['origin_station_id'] === $stationId) {
        return 'To ' . $dest;
    }
    if ((int) $row['destination_station_id'] === $stationId) {
        return 'From ' . $origin;
    }
    return $origin . ' &rarr; ' . $dest;
}

$selectedId = isset($_GET['station_id']) ? (int) $_GET['station_id'] : 0;
$station    = null;

// Same grace windows the home page uses: a departure stays "next" for 5
// minutes after its time; a train-specific alert expires 3 minutes after.
$nextCutoff  = date('H:i:s', strtotime('-5 minutes'));
$alertCutoff = date('H:i:s', strtotime('-3 minutes'));

if ($selectedId > 0) {
    // ---------------------------------------------------------------
    // DETAIL VIEW
    // ---------------------------------------------------------------
    $stmt = $pdo->prepare('SELECT station_id, station_name, station_code, platform_count FROM stations WHERE station_id = ?');
    $stmt->execute([$selectedId]);
    $station = $stmt->fetch() ?: null;

    if (!$station) {
        http_response_code(404);
        $page_title = 'Station not found';
    } else {
        $page_title = $station['station_name'] . ' station';

        // Every train calling here today, in time order
        $stmt = $pdo->prepare("
            SELECT t.train_no, t.line_name, t.origin_station_id, t.destination_station_id,
                   os.station_name AS origin_name, ds.station_name AS destination_name,
                   sch.platform_no, sch.arrival_time, sch.departure_time, sch.status, sch.delay_minutes
            FROM schedules sch
            JOIN trains t   ON t.train_id = sch.train_id
            JOIN stations os ON os.station_id = t.origin_station_id
            JOIN stations ds ON ds.station_id = t.destination_station_id
            WHERE sch.station_id = :station_id
            ORDER BY TIME(COALESCE(sch.departure_time, sch.arrival_time)) ASC
        ");
        $stmt->execute(['station_id' => $selectedId]);
        $calls = $stmt->fetchAll();

        // Lines served, derived from the trains that call here
        $lines = array_values(array_unique(array_column($calls, 'line_name')));
        sort($lines);

        // Group calls by platform. Platforms 1..platform_count always get a
        // panel (even if empty); anything without a valid platform number
        // goes into a "to be confirmed" panel so no train is ever hidden.
        $platforms = [];
        for ($p = 1; $p <= (int) $station['platform_count']; $p++) {
            $platforms[$p] = [];
        }
        $unassigned = [];
        foreach ($calls as $row) {
            $key = trim((string) ($row['platform_no'] ?? ''));
            if ($key !== '' && ctype_digit($key) && isset($platforms[(int) $key])) {
                $platforms[(int) $key][] = $row;
            } else {
                $unassigned[] = $row;
            }
        }

        // Next departure from this station
        $nextDeparture = null;
        foreach ($calls as $row) {
            if ($row['departure_time'] !== null && date('H:i:s', strtotime($row['departure_time'])) >= $nextCutoff) {
                $nextDeparture = $row;
                break;
            }
        }

        // Active notices for this station (same expiry rule as the home page)
        $stmt = $pdo->prepare("
            SELECT a.message, a.severity, a.created_at
            FROM alerts a
            LEFT JOIN schedules sch ON sch.schedule_id = a.schedule_id
            WHERE a.station_id = :station_id
              AND (a.schedule_id IS NULL
                   OR TIME(COALESCE(sch.departure_time, sch.arrival_time)) >= :cutoff)
            ORDER BY FIELD(a.severity, 'critical', 'warning', 'info'), a.created_at DESC
        ");
        $stmt->execute(['station_id' => $selectedId, 'cutoff' => $alertCutoff]);
        $alerts = $stmt->fetchAll();

        // Stations reachable on a direct train from here. Uses the same
        // rule as search.php's route search, so every chip below links to
        // a search that actually returns results.
        $stmt = $pdo->prepare("
            SELECT DISTINCT s2.station_id, s2.station_name, s2.station_code
            FROM schedules a
            JOIN schedules b ON b.train_id = a.train_id AND b.station_id <> a.station_id
            JOIN stations s2 ON s2.station_id = b.station_id
            WHERE a.station_id = :station_id
              AND a.departure_time IS NOT NULL
              AND b.arrival_time IS NOT NULL
              AND a.departure_time < b.arrival_time
            ORDER BY s2.station_name
        ");
        $stmt->execute(['station_id' => $selectedId]);
        $connections = $stmt->fetchAll();

        $trainCount = count(array_unique(array_column($calls, 'train_no')));
    }
} else {
    // ---------------------------------------------------------------
    // DIRECTORY VIEW
    // ---------------------------------------------------------------
    $page_title = 'Stations';

    // GROUP BY lists every selected column so this also runs under
    // MariaDB's ONLY_FULL_GROUP_BY mode. ("lines" is a reserved word in
    // MySQL, hence line_list.)
    $directory = $pdo->query("
        SELECT s.station_id, s.station_name, s.station_code, s.platform_count,
               COUNT(DISTINCT sch.train_id) AS train_count,
               GROUP_CONCAT(DISTINCT t.line_name ORDER BY t.line_name SEPARATOR '||') AS line_list
        FROM stations s
        LEFT JOIN schedules sch ON sch.station_id = s.station_id
        LEFT JOIN trains t      ON t.train_id = sch.train_id
        GROUP BY s.station_id, s.station_name, s.station_code, s.platform_count
        ORDER BY s.station_name
    ")->fetchAll();

    // Next departure at every station, in one query: walk the upcoming
    // departures in time order and keep the first one seen per station.
    $stmt = $pdo->prepare("
        SELECT sch.station_id, t.train_no, ds.station_name AS destination_name,
               sch.departure_time, sch.status, sch.delay_minutes
        FROM schedules sch
        JOIN trains t    ON t.train_id = sch.train_id
        JOIN stations ds ON ds.station_id = t.destination_station_id
        WHERE sch.departure_time IS NOT NULL AND TIME(sch.departure_time) >= :cutoff
        ORDER BY TIME(sch.departure_time) ASC
    ");
    $stmt->execute(['cutoff' => $nextCutoff]);
    $nextByStation = [];
    foreach ($stmt->fetchAll() as $row) {
        $nextByStation[(int) $row['station_id']] ??= $row;
    }

    // Network totals for the stat strip
    $totals = $pdo->query("
        SELECT (SELECT COUNT(*) FROM stations)                 AS stations,
               (SELECT COALESCE(SUM(platform_count), 0) FROM stations) AS platforms,
               (SELECT COUNT(DISTINCT line_name) FROM trains)  AS line_count,
               (SELECT COUNT(*) FROM schedules)                AS stops
    ")->fetch();
}

require_once __DIR__ . '/../includes/header.php';
?>

<?php if ($selectedId > 0 && !$station): ?>
<!-- ================================================================
     Station not found
     ================================================================ -->
<div class="pis-hero">
    <div class="pis-hero-copy">
        <div class="pis-hero-eyebrow"><span class="pis-dot"></span> Station information &mdash; <span class="pis-live-clock" data-pis-clock></span></div>
        <h1>Station not found</h1>
        <p>There's no station with that ID on the network.</p>
    </div>
</div>

<div class="pis-empty-state" style="margin-top: 1.75rem;">
    <strong>Nothing to show</strong>
    The station may have been removed or the link is out of date.
    <a href="stations.php">See all stations</a>.
</div>

<?php elseif ($station): ?>
<!-- ================================================================
     Detail view
     ================================================================ -->
<div class="pis-hero">
    <div class="pis-hero-copy">
        <div class="pis-hero-eyebrow"><span class="pis-dot"></span> Station information &mdash; <span class="pis-live-clock" data-pis-clock></span></div>
        <h1><?= htmlspecialchars($station['station_name']) ?></h1>
        <p>
            Station code <strong><?= htmlspecialchars($station['station_code']) ?></strong>
            &middot; <?= (int) $station['platform_count'] ?> platform<?= (int) $station['platform_count'] === 1 ? '' : 's' ?>
            <?php if ($lines): ?>
                &middot; served by <?= htmlspecialchars(implode(', ', $lines)) ?>
            <?php endif; ?>
        </p>
    </div>
    <div class="pis-hero-code" aria-hidden="true"><?= htmlspecialchars($station['station_code']) ?></div>
</div>

<div class="pis-stat-strip">
    <div class="pis-stat">
        <div class="pis-stat-value"><?= (int) $station['platform_count'] ?></div>
        <span class="pis-stat-label">Platforms</span>
    </div>
    <div class="pis-stat">
        <div class="pis-stat-value"><?= $trainCount ?></div>
        <span class="pis-stat-label">Trains today</span>
    </div>
    <div class="pis-stat">
        <div class="pis-stat-value"><?= $nextDeparture ? htmlspecialchars(date('H:i', strtotime($nextDeparture['departure_time']))) : '&mdash;' ?></div>
        <span class="pis-stat-label">Next departure</span>
    </div>
    <div class="pis-stat">
        <div class="pis-stat-value<?= $alerts ? ' is-danger' : '' ?>"><?= count($alerts) ?></div>
        <span class="pis-stat-label">Active notices</span>
    </div>
</div>

<div class="pis-action-row">
    <a class="btn-signal" href="departures.php?station_id=<?= (int) $station['station_id'] ?>">Live departure board</a>
    <a class="btn-ghost" href="search.php?mode=route&amp;origin_id=<?= (int) $station['station_id'] ?>">Plan a journey from here</a>
    <a class="btn-ghost" href="stations.php">&larr; All stations</a>
</div>

<?php if ($alerts): ?>
    <div class="pis-board-heading">
        <h2>Service notices</h2>
        <span class="pis-board-clock"><?= count($alerts) ?> active</span>
    </div>
    <ul class="pis-alert-list">
        <?php foreach ($alerts as $a): ?>
            <li class="pis-alert-item is-<?= htmlspecialchars($a['severity']) ?>">
                <span class="pis-alert-level"><?= htmlspecialchars($a['severity']) ?></span>
                <div>
                    <p class="pis-alert-msg"><?= htmlspecialchars($a['message']) ?></p>
                    <span class="pis-alert-time">Posted <?= htmlspecialchars(date('j M, H:i', strtotime($a['created_at']))) ?></span>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

<div class="pis-board-heading">
    <h2>Platforms</h2>
    <span class="pis-board-clock"><?= count($calls) ?> call<?= count($calls) === 1 ? '' : 's' ?> today</span>
</div>

<?php
// One platform panel. Kept as a closure so the numbered platforms and the
// "to be confirmed" panel render identically.
$renderPlatform = function (string $label, array $rows) use ($selectedId): void { ?>
    <section class="pis-platform">
        <header class="pis-platform-head">
            <span><?= $label ?></span>
            <span class="pis-platform-count"><?= count($rows) ?> train<?= count($rows) === 1 ? '' : 's' ?></span>
        </header>
        <?php if (!$rows): ?>
            <p class="pis-platform-empty">No trains booked to this platform today.</p>
        <?php else: ?>
            <ul class="pis-platform-list">
                <?php foreach ($rows as $row):
                    $isTerminating = $row['departure_time'] === null;
                    $mainTime      = $isTerminating ? $row['arrival_time'] : $row['departure_time'];
                    if ($isTerminating) {
                        $timeNote = 'arr';
                    } elseif ($row['arrival_time'] !== null) {
                        $timeNote = 'arr ' . date('H:i', strtotime($row['arrival_time']));
                    } else {
                        $timeNote = 'dep';
                    }
                    [$effStatus, $effDelay] = pis_effective_status($row['status'], (int) $row['delay_minutes'], $mainTime);
                ?>
                    <li>
                        <span class="pis-platform-time">
                            <?= htmlspecialchars(date('H:i', strtotime($mainTime))) ?>
                            <small><?= htmlspecialchars($timeNote) ?></small>
                        </span>
                        <span class="pis-platform-train">
                            <?= htmlspecialchars($row['train_no']) ?>
                            <span class="pis-platform-working"><?= station_working_label($row, $selectedId) ?></span>
                        </span>
                        <span class="status-<?= htmlspecialchars($effStatus) ?>">
                            <?= htmlspecialchars(pis_status_label($effStatus)) ?><?= $effDelay > 0 ? " (+{$effDelay}m)" : '' ?>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
<?php };
?>

<div class="pis-platform-grid">
    <?php foreach ($platforms as $number => $rows): ?>
        <?php $renderPlatform('Platform <span class="pis-platform-no">' . (int) $number . '</span>', $rows); ?>
    <?php endforeach; ?>
    <?php if ($unassigned): ?>
        <?php $renderPlatform('Platform to be confirmed', $unassigned); ?>
    <?php endif; ?>
</div>

<div class="pis-board-heading">
    <h2>Direct trains from here</h2>
    <span class="pis-board-clock"><?= count($connections) ?> destination<?= count($connections) === 1 ? '' : 's' ?></span>
</div>

<?php if ($connections): ?>
    <div class="pis-station-chips" style="margin-top: 0;">
        <?php foreach ($connections as $c): ?>
            <a class="pis-station-chip"
               href="search.php?mode=route&amp;origin_id=<?= (int) $station['station_id'] ?>&amp;destination_id=<?= (int) $c['station_id'] ?>">
                <?= htmlspecialchars($c['station_name']) ?>
                <span class="pis-station-code"><?= htmlspecialchars($c['station_code']) ?></span>
            </a>
        <?php endforeach; ?>
    </div>
    <p class="pis-muted" style="font-size: 0.88rem; margin: 0.6rem 0 0;">Pick a destination to see the trains, times and platforms for that journey.</p>
<?php else: ?>
    <div class="pis-empty-state">
        <strong>No direct departures</strong>
        No train leaving this station calls at another station later in its journey today.
    </div>
<?php endif; ?>

<?php else: ?>
<!-- ================================================================
     Directory view
     ================================================================ -->
<div class="pis-hero">
    <div class="pis-hero-copy">
        <div class="pis-hero-eyebrow"><span class="pis-dot"></span> Station directory &mdash; <span class="pis-live-clock" data-pis-clock></span></div>
        <h1>Stations</h1>
        <p>Every station on the network, with its platforms, the lines that serve it, and the next train out.</p>
    </div>
</div>

<div class="pis-stat-strip">
    <div class="pis-stat">
        <div class="pis-stat-value"><?= (int) $totals['stations'] ?></div>
        <span class="pis-stat-label">Stations</span>
    </div>
    <div class="pis-stat">
        <div class="pis-stat-value"><?= (int) $totals['platforms'] ?></div>
        <span class="pis-stat-label">Platforms</span>
    </div>
    <div class="pis-stat">
        <div class="pis-stat-value"><?= (int) $totals['line_count'] ?></div>
        <span class="pis-stat-label">Lines</span>
    </div>
    <div class="pis-stat">
        <div class="pis-stat-value"><?= (int) $totals['stops'] ?></div>
        <span class="pis-stat-label">Station stops today</span>
    </div>
</div>

<?php if (!$directory): ?>
    <div class="pis-empty-state" style="margin-top: 1.75rem;">
        <strong>No stations yet</strong>
        Stations added by staff will appear here.
    </div>
<?php else: ?>
    <div class="pis-filter-bar">
        <label class="pis-filter-label" for="stationFilter">Find a station</label>
        <input type="search" id="stationFilter" class="pis-input pis-filter-input"
               placeholder="Name, code or line" autocomplete="off"
               data-pis-filter="#stationGrid"
               data-pis-filter-empty="#stationEmpty"
               data-pis-filter-count="#stationCount">
        <span class="pis-filter-count" id="stationCount" aria-live="polite"><?= count($directory) ?> station<?= count($directory) === 1 ? '' : 's' ?></span>
    </div>

    <div class="pis-station-grid" id="stationGrid">
        <?php foreach ($directory as $s):
            $sid        = (int) $s['station_id'];
            $stationLines = $s['line_list'] !== null ? explode('||', $s['line_list']) : [];
            $next       = $nextByStation[$sid] ?? null;
            $filterText = $s['station_name'] . ' ' . $s['station_code'] . ' ' . implode(' ', $stationLines);
        ?>
            <article class="pis-station-card" data-filter-text="<?= htmlspecialchars($filterText) ?>">
                <header class="pis-station-card-head">
                    <span class="pis-code-badge"><?= htmlspecialchars($s['station_code']) ?></span>
                    <h2><?= htmlspecialchars($s['station_name']) ?></h2>
                </header>

                <div class="pis-station-card-body">
                    <div class="pis-station-figures">
                        <div class="pis-station-figure">
                            <?= (int) $s['platform_count'] ?>
                            <span>Platform<?= (int) $s['platform_count'] === 1 ? '' : 's' ?></span>
                        </div>
                        <div class="pis-station-figure">
                            <?= (int) $s['train_count'] ?>
                            <span>Train<?= (int) $s['train_count'] === 1 ? '' : 's' ?> today</span>
                        </div>
                    </div>
                    <p class="pis-station-lines">
                        <?= $stationLines ? htmlspecialchars(implode(' · ', $stationLines)) : 'No lines calling today' ?>
                    </p>
                </div>

                <?php if ($next):
                    [$effStatus, $effDelay] = pis_effective_status($next['status'], (int) $next['delay_minutes'], $next['departure_time']);
                ?>
                    <div class="pis-next-strip">
                        <span class="pis-next-label">Next</span>
                        <span><?= htmlspecialchars(date('H:i', strtotime($next['departure_time']))) ?>
                            <?= htmlspecialchars($next['train_no']) ?> &rarr; <?= htmlspecialchars($next['destination_name']) ?></span>
                        <span class="status-<?= htmlspecialchars($effStatus) ?>"><?= htmlspecialchars(pis_status_label($effStatus)) ?><?= $effDelay > 0 ? " (+{$effDelay}m)" : '' ?></span>
                    </div>
                <?php else: ?>
                    <div class="pis-next-strip is-none">
                        <span class="pis-next-label">Next</span>
                        <span>No more departures today</span>
                    </div>
                <?php endif; ?>

                <nav class="pis-station-card-links" aria-label="<?= htmlspecialchars($s['station_name']) ?>">
                    <a href="stations.php?station_id=<?= $sid ?>">Station info</a>
                    <a href="departures.php?station_id=<?= $sid ?>">Live board</a>
                </nav>
            </article>
        <?php endforeach; ?>
    </div>

    <div class="pis-empty-state" id="stationEmpty" hidden>
        <strong>No matching stations</strong>
        Try part of the station name, its code (such as KLS), or a line name.
    </div>
<?php endif; ?>

<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
