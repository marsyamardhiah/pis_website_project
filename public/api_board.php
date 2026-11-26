<?php
/**
 * Live data feed for the departure boards (returns JSON).
 *
 *   api_board.php?mode=station&station_id=1   -> every train calling at one station
 *   api_board.php?mode=upcoming               -> the next five departures network-wide + the four Home figures
 *
 * The browser (assets/js/script.js) calls this every 30 seconds with fetch()
 * and redraws the table rows in place, so the page never has to reload.
 * Same rules as the pages themselves: prepared statements for anything that
 * carries a value, status worked out by pis_effective_status(), time-of-day only.
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function pis_hm(?string $dateTime): ?string
{
    return $dateTime ? date('H:i', strtotime($dateTime)) : null;
}

function pis_json_error(int $code, string $message): void
{
    http_response_code($code);
    echo json_encode(['error' => $message]);
    exit;
}

$mode = $_GET['mode'] ?? 'station';
$out  = ['generated_at' => date('H:i:s'), 'rows' => []];

if ($mode === 'upcoming') {
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

    foreach ($stmt->fetchAll() as $row) {
        [$status, $delay] = pis_effective_status($row['status'], (int) $row['delay_minutes'], $row['departure_time']);
        $out['rows'][] = [
            'train_no'     => $row['train_no'],
            'line_name'    => $row['line_name'],
            'station_name' => $row['station_name'],
            'platform'     => $row['platform_no'] ?? '-',
            'departs'      => pis_hm($row['departure_time']),
            // minutes from now until the (daily) departure time, negative once it has gone
            'minutes'      => (int) round((strtotime(date('Y-m-d') . ' ' . date('H:i:s', strtotime($row['departure_time']))) - time()) / 60),
            'status'       => $status,
            'status_label' => pis_status_label($status),
            'delay'        => $delay,
        ];
    }

    // The four figures in the Home summary strip
    $grace = date('H:i:s', strtotime('-3 minutes'));

    $alerts = $pdo->prepare("
        SELECT COUNT(*) FROM alerts a
        LEFT JOIN schedules sch ON sch.schedule_id = a.schedule_id
        WHERE a.schedule_id IS NULL
           OR TIME(COALESCE(sch.departure_time, sch.arrival_time)) >= :cutoff
    ");
    $alerts->execute(['cutoff' => $grace]);

    $delayed = $pdo->prepare("
        SELECT COUNT(*) FROM schedules
        WHERE status = 'delayed'
          AND TIME(COALESCE(departure_time, arrival_time)) >= :cutoff
    ");
    $delayed->execute(['cutoff' => $grace]);

    $out['stats'] = [
        'stations' => (int) $pdo->query('SELECT COUNT(*) FROM stations')->fetchColumn(),
        'trains'   => (int) $pdo->query('SELECT COUNT(*) FROM trains')->fetchColumn(),
        'delayed'  => (int) $delayed->fetchColumn(),
        'alerts'   => (int) $alerts->fetchColumn(),
    ];
} elseif ($mode === 'station') {
    $stationId = (int) ($_GET['station_id'] ?? 0);

    $find = $pdo->prepare('SELECT station_name FROM stations WHERE station_id = ?');
    $find->execute([$stationId]);
    $stationName = $find->fetchColumn();
    if ($stationName === false) {
        pis_json_error(404, 'Unknown station');
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
    $stmt->execute(['station_id' => $stationId]);

    foreach ($stmt->fetchAll() as $row) {
        if ($row['origin_name'] === $stationName) {
            $working = 'To ' . $row['destination_name'];
        } elseif ($row['destination_name'] === $stationName) {
            $working = 'From ' . $row['origin_name'];
        } else {
            $working = $row['origin_name'] . ' → ' . $row['destination_name'];
        }
        $reference = $row['departure_time'] ?? $row['arrival_time'];
        [$status, $delay] = pis_effective_status($row['status'], (int) $row['delay_minutes'], $reference);

        $out['rows'][] = [
            'train_no'     => $row['train_no'],
            'line_name'    => $row['line_name'],
            'working'      => $working,
            'platform'     => $row['platform_no'] ?? '-',
            'arrives'      => pis_hm($row['arrival_time']) ?? '—',
            'departs'      => pis_hm($row['departure_time']) ?? '—',
            'status'       => $status,
            'status_label' => pis_status_label($status),
            'delay'        => $delay,
        ];
    }
    $out['station'] = $stationName;
} else {
    pis_json_error(400, 'Unknown mode');
}

echo json_encode($out, JSON_UNESCAPED_UNICODE);
