<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_login();

$error = '';

// --- CREATE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    $train_no = trim($_POST['train_no'] ?? '');
    $line_name = trim($_POST['line_name'] ?? '');
    $origin = (int)($_POST['origin_station_id'] ?? 0);
    $destination = (int)($_POST['destination_station_id'] ?? 0);

    if ($train_no === '' || $line_name === '' || !$origin || !$destination) {
        $error = 'All fields are required.';
    } else {
        $stmt = $pdo->prepare(
            'INSERT INTO trains (train_no, line_name, origin_station_id, destination_station_id) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$train_no, $line_name, $origin, $destination]);
        header('Location: manage_trains.php');
        exit;
    }
}

// --- DELETE ---
if (isset($_GET['delete'])) {
    $stmt = $pdo->prepare('DELETE FROM trains WHERE train_id = ?');
    $stmt->execute([(int)$_GET['delete']]);
    header('Location: manage_trains.php');
    exit;
}

// --- READ (list + station dropdown data) ---
$trains = $pdo->query("
    SELECT t.train_id, t.train_no, t.line_name,
           so.station_name AS origin_name, sd.station_name AS destination_name
    FROM trains t
    JOIN stations so ON so.station_id = t.origin_station_id
    JOIN stations sd ON sd.station_id = t.destination_station_id
    ORDER BY t.train_id DESC
")->fetchAll();

$stations = $pdo->query('SELECT station_id, station_name FROM stations ORDER BY station_name')->fetchAll();

$page_title = 'Manage Trains';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3">Manage Trains</h1>
    <a href="dashboard.php" class="btn btn-outline-secondary btn-sm">&larr; Dashboard</a>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="card mb-4">
    <div class="card-body">
        <h2 class="h5 mb-3">Add New Train</h2>
        <form method="post" class="row g-2">
            <input type="hidden" name="action" value="create">
            <div class="col-md-3">
                <input type="text" name="train_no" class="form-control" placeholder="Train No. (e.g. ETS-307)" required>
            </div>
            <div class="col-md-3">
                <input type="text" name="line_name" class="form-control" placeholder="Line Name" required>
            </div>
            <div class="col-md-2">
                <select name="origin_station_id" class="form-select" required>
                    <option value="">Origin</option>
                    <?php foreach ($stations as $s): ?>
                        <option value="<?= $s['station_id'] ?>"><?= htmlspecialchars($s['station_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select name="destination_station_id" class="form-select" required>
                    <option value="">Destination</option>
                    <?php foreach ($stations as $s): ?>
                        <option value="<?= $s['station_id'] ?>"><?= htmlspecialchars($s['station_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-success w-100">Add Train</button>
            </div>
        </form>
    </div>
</div>

<table class="table table-striped bg-white">
    <thead>
        <tr>
            <th>#</th>
            <th>Train No.</th>
            <th>Line</th>
            <th>Origin</th>
            <th>Destination</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($trains as $t): ?>
        <tr>
            <td><?= $t['train_id'] ?></td>
            <td><?= htmlspecialchars($t['train_no']) ?></td>
            <td><?= htmlspecialchars($t['line_name']) ?></td>
            <td><?= htmlspecialchars($t['origin_name']) ?></td>
            <td><?= htmlspecialchars($t['destination_name']) ?></td>
            <td>
                <a href="edit_train.php?id=<?= $t['train_id'] ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                <a href="manage_trains.php?delete=<?= $t['train_id'] ?>"
                   class="btn btn-sm btn-outline-danger"
                   onclick="return confirm('Delete this train?');">Delete</a>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
