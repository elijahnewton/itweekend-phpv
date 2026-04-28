<?php
session_start();
require_once __DIR__ . '/../functions.php';
require_admin();

$pdo = get_db();
$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $post_action = $_POST['action'] ?? '';

    if ($post_action === 'delete' && (int)$_POST['id'] > 0) {
        $pdo->prepare('DELETE FROM users WHERE id = ? AND role != ?')->execute([(int)$_POST['id'], ROLE_ADMIN]);
        flash('User deleted.');
        redirect('/admin/users.php');
    }

    if ($post_action === 'update_role' && (int)$_POST['id'] > 0) {
        $role = in_array($_POST['role'], [ROLE_ADMIN, ROLE_STUDENT]) ? $_POST['role'] : ROLE_STUDENT;
        $pdo->prepare('UPDATE users SET role = ? WHERE id = ?')->execute([$role, (int)$_POST['id']]);
        flash('Role updated.');
        redirect('/admin/users.php');
    }

    redirect('/admin/users.php');
}

$search = trim($_GET['q'] ?? '');
$where = '';
$params = [];
if ($search) {
    $where = 'WHERE email LIKE ? OR name LIKE ?';
    $params = ["%$search%", "%$search%"];
}

$users = $pdo->prepare("SELECT * FROM users $where ORDER BY created_at DESC");
$users->execute($params);
$users = $users->fetchAll();

$page_title = 'Manage Users';
require __DIR__ . '/../templates/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="fw-bold mb-0"><i class="bi bi-people text-primary me-2"></i>Manage Users</h1>
    <a href="/admin/index.php" class="btn btn-outline-secondary btn-sm">← Dashboard</a>
</div>

<form method="get" class="row g-2 mb-4">
    <div class="col">
        <input type="text" class="form-control" name="q" placeholder="Search by email or name…"
               value="<?= h($search) ?>">
    </div>
    <div class="col-auto">
        <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i></button>
        <?php if ($search): ?><a href="/admin/users.php" class="btn btn-outline-secondary">Clear</a><?php endif; ?>
    </div>
</form>

<div class="card border-0 shadow-sm rounded-4">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr>
                <th>#</th><th>Name</th><th>Email</th><th>Role</th><th>Joined</th><th>Actions</th>
            </tr></thead>
            <tbody>
            <?php foreach ($users as $u): ?>
            <tr>
                <td class="text-muted small"><?= $u['id'] ?></td>
                <td><?= h($u['name'] ?: '—') ?></td>
                <td><?= h($u['email']) ?></td>
                <td>
                    <form method="post" class="d-inline">
                        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                        <input type="hidden" name="action" value="update_role">
                        <input type="hidden" name="id" value="<?= $u['id'] ?>">
                        <select name="role" class="form-select form-select-sm d-inline w-auto"
                                onchange="this.form.submit()">
                            <option value="student" <?= $u['role'] === ROLE_STUDENT ? 'selected' : '' ?>>Student</option>
                            <option value="admin"   <?= $u['role'] === ROLE_ADMIN   ? 'selected' : '' ?>>Admin</option>
                        </select>
                    </form>
                </td>
                <td class="text-muted small"><?= h(substr($u['created_at'], 0, 10)) ?></td>
                <td>
                    <?php if ($u['role'] !== ROLE_ADMIN): ?>
                    <form method="post" class="d-inline">
                        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= $u['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger"
                                data-confirm="Delete user <?= h($u['email']) ?>?">
                            <i class="bi bi-trash"></i>
                        </button>
                    </form>
                    <?php else: ?>
                    <span class="text-muted small">—</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($users)): ?>
            <tr><td colspan="6" class="text-center text-muted py-4">No users found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/../templates/footer.php'; ?>
