<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_login();

// Only admins may manage accounts (staff accounts get a friendly block page)
if (($_SESSION['admin_role'] ?? '') !== 'admin') {
    http_response_code(403);
    $page_title = 'Access Denied';
    require_once __DIR__ . '/../includes/header.php';
    ?>
    <div class="pis-empty-state">
        <strong>Access denied</strong>
        Only administrators can manage staff accounts.
        <p><a href="dashboard.php" class="btn-ghost">&larr; Dashboard</a></p>
    </div>
    <?php
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

$error = '';
$my_id = (int)$_SESSION['admin_id'];

// Values that refill the form (blank for "add", the row's values for "edit")
$form = ['user_id' => 0, 'username' => '', 'full_name' => '', 'role' => 'staff'];

// Helper: how many admin accounts exist, not counting $exclude_id
function other_admin_count(PDO $pdo, int $exclude_id): int {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM admin_users WHERE role = 'admin' AND user_id <> ?");
    $stmt->execute([$exclude_id]);
    return (int)$stmt->fetchColumn();
}

// --- CREATE / UPDATE / DELETE (all sent by POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $target = (int)($_POST['user_id'] ?? 0);

        $stmt = $pdo->prepare('SELECT role FROM admin_users WHERE user_id = ?');
        $stmt->execute([$target]);
        $row = $stmt->fetch();

        if (!$row) {
            $error = 'That account no longer exists.';
        } elseif ($target === $my_id) {
            $error = 'You cannot delete the account you are logged in with.';
        } elseif ($row['role'] === 'admin' && other_admin_count($pdo, $target) === 0) {
            $error = 'You cannot delete the last administrator account.';
        } else {
            $stmt = $pdo->prepare('DELETE FROM admin_users WHERE user_id = ?');
            $stmt->execute([$target]);
            $_SESSION['flash'] = 'Account deleted.';
            header('Location: manage_users.php');
            exit;
        }
    }

    if ($action === 'create' || $action === 'update') {
        $password = $_POST['password'] ?? '';
        $form = [
            'user_id'   => (int)($_POST['user_id'] ?? 0),
            'username'  => trim($_POST['username'] ?? ''),
            'full_name' => trim($_POST['full_name'] ?? ''),
            'role'      => ($_POST['role'] ?? '') === 'admin' ? 'admin' : 'staff',
        ];

        if ($form['username'] === '' || $form['full_name'] === '') {
            $error = 'Username and full name are required.';
        } elseif (!preg_match('/^[A-Za-z0-9_.]{3,50}$/', $form['username'])) {
            $error = 'Username must be 3–50 characters: letters, numbers, dot or underscore only.';
        } elseif (mb_strlen($form['full_name']) > 100) {
            $error = 'Full name must be 100 characters or less.';
        } elseif ($action === 'create' && $password === '') {
            $error = 'A password is required for a new account.';
        } elseif ($password !== '' && strlen($password) < 8) {
            $error = 'Password must be at least 8 characters.';
        } else {
            try {
                if ($action === 'create') {
                    $stmt = $pdo->prepare(
                        'INSERT INTO admin_users (username, password_hash, full_name, role) VALUES (?, ?, ?, ?)'
                    );
                    $stmt->execute([
                        $form['username'],
                        password_hash($password, PASSWORD_DEFAULT),
                        $form['full_name'],
                        $form['role'],
                    ]);
                    $_SESSION['flash'] = 'Account created.';
                    header('Location: manage_users.php');
                    exit;
                }

                // --- UPDATE ---
                $stmt = $pdo->prepare('SELECT role FROM admin_users WHERE user_id = ?');
                $stmt->execute([$form['user_id']]);
                $current = $stmt->fetch();

                if (!$current) {
                    $error = 'That account no longer exists.';
                } elseif ($current['role'] === 'admin' && $form['role'] === 'staff'
                          && other_admin_count($pdo, $form['user_id']) === 0) {
                    $error = 'You cannot remove the admin role from the last administrator.';
                } else {
                    if ($password !== '') {
                        $stmt = $pdo->prepare(
                            'UPDATE admin_users SET username = ?, full_name = ?, role = ?, password_hash = ? WHERE user_id = ?'
                        );
                        $stmt->execute([
                            $form['username'], $form['full_name'], $form['role'],
                            password_hash($password, PASSWORD_DEFAULT), $form['user_id'],
                        ]);
                    } else {
                        $stmt = $pdo->prepare(
                            'UPDATE admin_users SET username = ?, full_name = ?, role = ? WHERE user_id = ?'
                        );
                        $stmt->execute([$form['username'], $form['full_name'], $form['role'], $form['user_id']]);
                    }

                    // Keep the session in step if the person edited their own account
                    if ($form['user_id'] === $my_id) {
                        $_SESSION['admin_name'] = $form['full_name'];
                        $_SESSION['admin_role'] = $form['role'];
                    }

                    $_SESSION['flash'] = 'Account updated.';
                    header('Location: manage_users.php');
                    exit;
                }
            } catch (PDOException $e) {
                // MySQL error 1062 = duplicate value in a UNIQUE column (username)
                if (($e->errorInfo[1] ?? 0) == 1062) {
                    $error = 'The username "' . $form['username'] . '" is already taken.';
                } else {
                    $error = 'Could not save the account.';
                }
            }
        }
    }
}

// --- Load a row into the form when "Edit" was clicked ---
if (isset($_GET['edit']) && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $stmt = $pdo->prepare('SELECT user_id, username, full_name, role FROM admin_users WHERE user_id = ?');
    $stmt->execute([(int)$_GET['edit']]);
    $row = $stmt->fetch();
    if ($row) {
        $form = $row;
    }
}
$editing = $form['user_id'] > 0;

// --- READ ---
$users = $pdo->query(
    'SELECT user_id, username, full_name, role, created_at FROM admin_users ORDER BY role, username'
)->fetchAll();

$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);

$page_title = 'Manage Staff Accounts';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="pis-board-heading">
    <h2>Manage Staff Accounts</h2>
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
        <div class="pis-search-panel-head"><?= $editing ? 'EDIT ACCOUNT #' . (int)$form['user_id'] : 'ADD NEW ACCOUNT' ?></div>
        <form method="post" class="pis-search-form" autocomplete="off">
            <input type="hidden" name="action" value="<?= $editing ? 'update' : 'create' ?>">
            <input type="hidden" name="user_id" value="<?= (int)$form['user_id'] ?>">

            <div class="pis-field">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" class="pis-input" maxlength="50" required
                       placeholder="e.g. siti.aminah" value="<?= htmlspecialchars($form['username']) ?>">
            </div>

            <div class="pis-field" style="flex-grow: 2;">
                <label for="full_name">Full Name</label>
                <input type="text" id="full_name" name="full_name" class="pis-input" maxlength="100" required
                       placeholder="e.g. Siti Aminah" value="<?= htmlspecialchars($form['full_name']) ?>">
            </div>

            <div class="pis-field">
                <label for="password"><?= $editing ? 'New Password (optional)' : 'Password' ?></label>
                <input type="password" id="password" name="password" class="pis-input" minlength="8"
                       autocomplete="new-password" <?= $editing ? '' : 'required' ?>
                       placeholder="<?= $editing ? 'Leave blank to keep' : 'Min. 8 characters' ?>">
            </div>

            <div class="pis-field">
                <label for="role">Role</label>
                <select id="role" name="role" class="pis-input">
                    <option value="staff" <?= $form['role'] === 'staff' ? 'selected' : '' ?>>Staff</option>
                    <option value="admin" <?= $form['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                </select>
            </div>

            <button type="submit" class="btn-signal"><?= $editing ? 'Save Changes' : 'Add Account' ?></button>
            <?php if ($editing): ?>
                <a href="manage_users.php" class="btn-ghost">Cancel</a>
            <?php endif; ?>
        </form>
    </div>
</section>

<section class="pis-section">
    <h2 class="pis-section-title">All Accounts (<?= count($users) ?>)</h2>

    <div class="departure-board">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Username</th>
                    <th>Full Name</th>
                    <th>Role</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($users as $u): ?>
                <tr>
                    <td><?= (int)$u['user_id'] ?></td>
                    <td><?= htmlspecialchars($u['username']) ?><?= (int)$u['user_id'] === $my_id ? ' (you)' : '' ?></td>
                    <td style="color:var(--pis-ink); font-family:var(--font-body); font-weight:600;"><?= htmlspecialchars($u['full_name'] ?? '') ?></td>
                    <td><?= htmlspecialchars(ucfirst($u['role'])) ?></td>
                    <td><?= htmlspecialchars(date('d M Y', strtotime($u['created_at']))) ?></td>
                    <td style="white-space:nowrap;">
                        <a href="manage_users.php?edit=<?= (int)$u['user_id'] ?>" class="btn-ghost" style="padding:0.25rem 0.7rem;">Edit</a>
                        <?php if ((int)$u['user_id'] !== $my_id): ?>
                            <form method="post" style="display:inline;" onsubmit="return confirm('Delete this account?');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="user_id" value="<?= (int)$u['user_id'] ?>">
                                <button type="submit" class="btn-ghost" style="padding:0.25rem 0.7rem; color:var(--pis-red); border-color:var(--pis-red); cursor:pointer;">Delete</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>