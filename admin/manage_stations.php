<?php
/**
 * Manage Stations / Platforms (admin)
 * Full CRUD for the `stations` table: add, list, edit and delete stations.
 * Every query that touches user input uses a prepared statement.
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_login();

$error = '';

// Values that refill the form (blank for "add", the row's values for "edit")
$form = ['station_id' => 0, 'station_name' => '', 'station_code' => '', 'platform_count' => 1];

// --- CREATE / UPDATE / DELETE (all sent by POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        try {
            $stmt = $pdo->prepare('DELETE FROM stations WHERE station_id = ?');
            $stmt->execute([(int)($_POST['station_id'] ?? 0)]);
            $_SESSION['flash'] = 'Station deleted.';
            header('Location: manage_stations.php');
            exit;
        } catch (PDOException $e) {
            // MySQL error 1451 = row is still referenced by another table
            if (($e->errorInfo[1] ?? 0) == 1451) {
                $error = 'This station cannot be deleted because trains or schedules still use it. Remove or change those first.';
            } else {
                $error = 'Could not delete the station.';
            }
        }
    }

    if ($action === 'create' || $action === 'update') {
        $form = [
            'station_id'     => (int)($_POST['station_id'] ?? 0),
            'station_name'   => trim($_POST['station_name'] ?? ''),
            'station_code'   => strtoupper(trim($_POST['station_code'] ?? '')),
            'platform_count' => (int)($_POST['platform_count'] ?? 0),
        ];

        if ($form['station_name'] === '' || $form['station_code'] === '') {
            $error = 'Station name and code are required.';
        } elseif (mb_strlen($form['station_name']) > 100) {
            $error = 'Station name must be 100 characters or less.';
        } elseif (!preg_match('/^[A-Z0-9]{2,10}$/', $form['station_code'])) {
            $error = 'Station code must be 2–10 letters or numbers (e.g. KLS).';
        } elseif ($form['platform_count'] < 1 || $form['platform_count'] > 30) {
            $error = 'Platform count must be between 1 and 30.';
        } else {
            try {
                if ($action === 'create') {
                    $stmt = $pdo->prepare(
                        'INSERT INTO stations (station_name, station_code, platform_count) VALUES (?, ?, ?)'
                    );
                    $stmt->execute([$form['station_name'], $form['station_code'], $form['platform_count']]);
                    $_SESSION['flash'] = 'Station added.';
                } else {
                    $stmt = $pdo->prepare(
                        'UPDATE stations SET station_name = ?, station_code = ?, platform_count = ? WHERE station_id = ?'
                    );
                    $stmt->execute([$form['station_name'], $form['station_code'], $form['platform_count'], $form['station_id']]);
                    $_SESSION['flash'] = 'Station updated.';
                }
                header('Location: manage_stations.php');
                exit;
            } catch (PDOException $e) {
                // MySQL error 1062 = duplicate value in a UNIQUE column (station_code)
                if (($e->errorInfo[1] ?? 0) == 1062) {
                    $error = 'Station code "' . $form['station_code'] . '" is already used by another station.';
                } else {
                    $error = 'Could not save the station.';
                }
            }
        }
    }
}

// --- Load a row into the form when "Edit" was clicked ---
if (isset($_GET['edit']) && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $stmt = $pdo->prepare('SELECT station_id, station_name, station_code, platform_count FROM stations WHERE station_id = ?');
    $stmt->execute([(int)$_GET['edit']]);
    $row = $stmt->fetch();
    if ($row) {
        $form = $row;
    }
}
$editing = $form['station_id'] > 0;

// --- READ (list, with how many trains/stops use each station) ---
$stations = $pdo->query("
    SELECT s.station_id, s.station_name, s.station_code, s.platform_count,
           (SELECT COUNT(*) FROM trains t
             WHERE t.origin_station_id = s.station_id OR t.destination_station_id = s.station_id) AS train_count,
           (SELECT COUNT(*) FROM schedules sc WHERE sc.station_id = s.station_id) AS stop_count
    FROM stations s
    ORDER BY s.station_name
")->fetchAll();

$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);

$page_title = 'Manage Stations';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="pis-board-heading">
    <h2>Manage Stations &amp; Platforms</h2>
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
        <div class="pis-search-panel-head"><?= $editing ? 'EDIT STATION #' . (int)$form['station_id'] : 'ADD NEW STATION' ?></div>
        <form method="post" class="pis-search-form">
            <input type="hidden" name="action" value="<?= $editing ? 'update' : 'create' ?>">
            <input type="hidden" name="station_id" value="<?= (int)$form['station_id'] ?>">

            <div class="pis-field" style="flex-grow: 2;">
                <label for="station_name">Station Name</label>
                <input type="text" id="station_name" name="station_name" class="pis-input" maxlength="100" required
                       placeholder="e.g. Kajang" value="<?= htmlspecialchars($form['station_name']) ?>">
            </div>

            <div class="pis-field">
                <label for="station_code">Station Code</label>
                <input type="text" id="station_code" name="station_code" class="pis-input" maxlength="10" required
                       placeholder="e.g. KJG" style="text-transform: uppercase;"
                       value="<?= htmlspecialchars($form['station_code']) ?>">
            </div>

            <div class="pis-field">
                <label for="platform_count">Platforms</label>
                <input type="number" id="platform_count" name="platform_count" class="pis-input" min="1" max="30" required
                       value="<?= (int)$form['platform_count'] ?>">
            </div>

            <button type="submit" class="btn-signal"><?= $editing ? 'Save Changes' : 'Add Station' ?></button>
            <?php if ($editing): ?>
                <a href="manage_stations.php" class="btn-ghost">Cancel</a>
            <?php endif; ?>
        </form>
    </div>
</section>

<section class="pis-section">
    <h2 class="pis-section-title">All Stations (<?= count($stations) ?>)</h2>

    <?php if (!$stations): ?>
        <div class="pis-empty-state">
            <strong>No stations yet</strong>
            Use the form above to add the first station.
        </div>
    <?php else: ?>
        <div class="departure-board">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Code</th>
                        <th>Station</th>
                        <th>Platforms</th>
                        <th>Trains</th>
                        <th>Stops</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($stations as $s): ?>
                    <tr>
                        <td><?= (int)$s['station_id'] ?></td>
                        <td><?= htmlspecialchars($s['station_code']) ?></td>
                        <td style="color:var(--pis-ink); font-family:var(--font-body); font-weight:600;"><?= htmlspecialchars($s['station_name']) ?></td>
                        <td><?= (int)$s['platform_count'] ?></td>
                        <td><?= (int)$s['train_count'] ?></td>
                        <td><?= (int)$s['stop_count'] ?></td>
                        <td style="white-space:nowrap;">
                            <a href="manage_stations.php?edit=<?= (int)$s['station_id'] ?>" class="btn-ghost" style="padding:0.25rem 0.7rem;">Edit</a>
                            <form method="post" style="display:inline;" onsubmit="return confirm('Delete this station? Any alerts for it will also be removed.');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="station_id" value="<?= (int)$s['station_id'] ?>">
                                <button type="submit" class="btn-ghost" style="padding:0.25rem 0.7rem; color:var(--pis-red); border-color:var(--pis-red); cursor:pointer;">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="pis-muted" style="font-size:0.85rem; margin-top:0.6rem;">
            A station that is still used by trains or schedules can't be deleted — edit or remove those first.
        </p>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
