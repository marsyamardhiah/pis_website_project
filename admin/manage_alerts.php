<?php
/**
 * Manage Delays & Alerts (admin)
 * Full CRUD for the `alerts` table: add, list, edit and delete the
 * announcements that appear on public/alerts.php.
 * Every query that touches user input uses a prepared statement.
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_login();

$severities = ['info' => 'Info', 'warning' => 'Warning', 'critical' => 'Critical'];
$error = '';

// Values that refill the form (blank for "add", the row's values for "edit")
$form = ['alert_id' => 0, 'message' => '', 'severity' => 'info', 'station_id' => '', 'schedule_id' => ''];

// --- CREATE / UPDATE / DELETE (all sent by POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $stmt = $pdo->prepare('DELETE FROM alerts WHERE alert_id = ?');
        $stmt->execute([(int)($_POST['alert_id'] ?? 0)]);
        $_SESSION['flash'] = 'Alert deleted.';
        header('Location: manage_alerts.php');
        exit;
    }

    if ($action === 'create' || $action === 'update') {
        $form = [
            'alert_id'    => (int)($_POST['alert_id'] ?? 0),
            'message'     => trim($_POST['message'] ?? ''),
            'severity'    => $_POST['severity'] ?? '',
            'station_id'  => $_POST['station_id'] ?? '',
            'schedule_id' => $_POST['schedule_id'] ?? '',
        ];

        // Station and schedule are optional: an empty choice is stored as NULL
        $station_id  = $form['station_id']  === '' ? null : (int)$form['station_id'];
        $schedule_id = $form['schedule_id'] === '' ? null : (int)$form['schedule_id'];

        if ($form['message'] === '') {
            $error = 'Message is required.';
        } elseif (mb_strlen($form['message']) > 255) {
            $error = 'Message must be 255 characters or less.';
        } elseif (!isset($severities[$form['severity']])) {
            $error = 'Please choose a valid severity.';
        } else {
            try {
                if ($action === 'create') {
                    $stmt = $pdo->prepare(
                        'INSERT INTO alerts (message, severity, station_id, schedule_id) VALUES (?, ?, ?, ?)'
                    );
                    $stmt->execute([$form['message'], $form['severity'], $station_id, $schedule_id]);
                    $_SESSION['flash'] = 'Alert added.';
                } else {
                    $stmt = $pdo->prepare(
                        'UPDATE alerts SET message = ?, severity = ?, station_id = ?, schedule_id = ? WHERE alert_id = ?'
                    );
                    $stmt->execute([$form['message'], $form['severity'], $station_id, $schedule_id, $form['alert_id']]);
                    $_SESSION['flash'] = 'Alert updated.';
                }
                header('Location: manage_alerts.php');
                exit;
            } catch (PDOException $e) {
                // Most likely the chosen station/schedule was deleted meanwhile
                $error = 'Could not save the alert. Please check the station and schedule you picked.';
            }
        }
    }
}

// --- Load a row into the form when "Edit" was clicked ---
if (isset($_GET['edit']) && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $stmt = $pdo->prepare('SELECT alert_id, message, severity, station_id, schedule_id FROM alerts WHERE alert_id = ?');
    $stmt->execute([(int)$_GET['edit']]);
    $row = $stmt->fetch();
    if ($row) {
        $form = $row;
    }
}
$editing = $form['alert_id'] > 0;

// --- READ (list + dropdown data) ---
$alerts = $pdo->query("
    SELECT a.alert_id, a.message, a.severity, a.created_at,
           s.station_name, t.train_no
    FROM alerts a
    LEFT JOIN stations  s  ON s.station_id   = a.station_id
    LEFT JOIN schedules sc ON sc.schedule_id = a.schedule_id
    LEFT JOIN trains    t  ON t.train_id     = sc.train_id
    ORDER BY a.created_at DESC, a.alert_id DESC
")->fetchAll();

$stations = $pdo->query('SELECT station_id, station_name FROM stations ORDER BY station_name')->fetchAll();

$schedules = $pdo->query("
    SELECT sc.schedule_id, t.train_no, s.station_name,
           COALESCE(sc.departure_time, sc.arrival_time) AS stop_time
    FROM schedules sc
    JOIN trains   t ON t.train_id   = sc.train_id
    JOIN stations s ON s.station_id = sc.station_id
    ORDER BY t.train_no, stop_time
")->fetchAll();

$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);

$page_title = 'Manage Alerts';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="pis-board-heading">
    <h2>Manage Delays &amp; Alerts</h2>
    <a href="dashboard.php" class="btn-ghost">&larr; Dashboard</a>
</div>

<?php if ($flash): ?>
    <div class="pis-alert-list pis-section"><div class="pis-alert-item">
        <span class="pis-alert-level">Done</span>
        <p class="pis-alert-msg"><?= htmlspecialchars($flash) ?></p>
    </div></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="pis-alert-list pis-section"><div class="pis-alert-item is-critical">
        <span class="pis-alert-level">Error</span>
        <p class="pis-alert-msg"><?= htmlspecialchars($error) ?></p>
    </div></div>
<?php endif; ?>

<section class="pis-section">
    <div class="pis-search-panel">
        <div class="pis-search-panel-head"><?= $editing ? 'EDIT ALERT #' . (int)$form['alert_id'] : 'ADD NEW ALERT' ?></div>
        <form method="post" class="pis-search-form">
            <input type="hidden" name="action" value="<?= $editing ? 'update' : 'create' ?>">
            <input type="hidden" name="alert_id" value="<?= (int)$form['alert_id'] ?>">

            <div class="pis-field" style="flex-basis: 100%;">
                <label for="message">Message</label>
                <input type="text" id="message" name="message" class="pis-input" maxlength="255" required
                       placeholder="e.g. ETS-301 delayed 20 minutes due to track inspection"
                       value="<?= htmlspecialchars($form['message']) ?>">
            </div>

            <div class="pis-field">
                <label for="severity">Severity</label>
                <select id="severity" name="severity" class="pis-select" required>
                    <?php foreach ($severities as $value => $label): ?>
                        <option value="<?= $value ?>" <?= $form['severity'] === $value ? 'selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="pis-field">
                <label for="station_id">Station (optional)</label>
                <select id="station_id" name="station_id" class="pis-select">
                    <option value="">— All / network-wide —</option>
                    <?php foreach ($stations as $s): ?>
                        <option value="<?= $s['station_id'] ?>" <?= (string)$form['station_id'] === (string)$s['station_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($s['station_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="pis-field">
                <label for="schedule_id">Related train stop (optional)</label>
                <select id="schedule_id" name="schedule_id" class="pis-select">
                    <option value="">— None —</option>
                    <?php foreach ($schedules as $sc): ?>
                        <option value="<?= $sc['schedule_id'] ?>" <?= (string)$form['schedule_id'] === (string)$sc['schedule_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($sc['train_no'] . ' · ' . $sc['station_name'] . ' · ' . ($sc['stop_time'] ? date('H:i', strtotime($sc['stop_time'])) : '--:--')) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button type="submit" class="btn-signal"><?= $editing ? 'Save Changes' : 'Add Alert' ?></button>
            <?php if ($editing): ?>
                <a href="manage_alerts.php" class="btn-ghost">Cancel</a>
            <?php endif; ?>
        </form>
    </div>
</section>

<section class="pis-section">
    <h2 class="pis-section-title">All Alerts (<?= count($alerts) ?>)</h2>

    <?php if (!$alerts): ?>
        <div class="pis-empty-state">
            <strong>No alerts yet</strong>
            Use the form above to post the first announcement.
        </div>
    <?php else: ?>
        <div class="departure-board">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Severity</th>
                        <th>Message</th>
                        <th>Station</th>
                        <th>Train</th>
                        <th>Posted</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($alerts as $a): ?>
                    <tr>
                        <td><?= (int)$a['alert_id'] ?></td>
                        <td class="<?= $a['severity'] === 'critical' ? 'status-cancelled' : ($a['severity'] === 'warning' ? 'status-delayed' : 'status-boarding') ?>">
                            <?= htmlspecialchars(strtoupper($a['severity'])) ?>
                        </td>
                        <td style="color:var(--pis-ink); font-family:var(--font-body); font-weight:600;"><?= htmlspecialchars($a['message']) ?></td>
                        <td><?= htmlspecialchars($a['station_name'] ?? 'All') ?></td>
                        <td><?= htmlspecialchars($a['train_no'] ?? '—') ?></td>
                        <td><?= htmlspecialchars(date('d M H:i', strtotime($a['created_at']))) ?></td>
                        <td style="white-space:nowrap;">
                            <a href="manage_alerts.php?edit=<?= (int)$a['alert_id'] ?>" class="btn-ghost" style="padding:0.25rem 0.7rem;">Edit</a>
                            <form method="post" style="display:inline;" onsubmit="return confirm('Delete this alert?');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="alert_id" value="<?= (int)$a['alert_id'] ?>">
                                <button type="submit" class="btn-ghost" style="padding:0.25rem 0.7rem; color:var(--pis-red); border-color:var(--pis-red); cursor:pointer;">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
