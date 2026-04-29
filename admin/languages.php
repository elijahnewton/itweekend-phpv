<?php
session_start();
require_once __DIR__ . '/../functions.php';
require_admin();

$pdo    = get_db();
$action = $_GET['action'] ?? 'list';
$id     = (int)($_GET['id'] ?? 0);
$error  = '';

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $pa = $_POST['action'] ?? '';

    if ($pa === 'delete' && (int)$_POST['id'] > 0) {
        $pdo->prepare('DELETE FROM languages WHERE id = ?')->execute([(int)$_POST['id']]);
        flash('Language deleted.');
        redirect('/admin/languages.php');
    }

    if ($pa === 'save') {
        $name    = trim($_POST['name'] ?? '');
        $slug    = slugify(trim($_POST['slug'] ?? $name));
        $desc    = trim($_POST['description'] ?? '');
        $icon    = trim($_POST['icon'] ?? '');
        $level   = trim($_POST['level'] ?? '');
        $hours   = (int)($_POST['estimated_hours'] ?? 0);
        $order   = (int)($_POST['order_index'] ?? 0);
        $edit_id = (int)($_POST['edit_id'] ?? 0);

        if ($name === '' || $slug === '') {
            $error = 'Name and slug are required.';
        } else {
            if ($edit_id) {
                $pdo->prepare(
                    'UPDATE languages SET name=?,slug=?,description=?,icon=?,level=?,estimated_hours=?,order_index=? WHERE id=?'
                )->execute([$name, $slug, $desc, $icon, $level, $hours ?: null, $order, $edit_id]);
                flash('Language updated.');
            } else {
                $pdo->prepare(
                    'INSERT INTO languages (name,slug,description,icon,level,estimated_hours,order_index) VALUES (?,?,?,?,?,?,?)'
                )->execute([$name, $slug, $desc, $icon, $level, $hours ?: null, $order]);
                flash('Language created.');
            }
            redirect('/admin/languages.php');
        }
    }
}

$editing = null;
if (($action === 'edit' || $action === 'create') && $id) {
    $stmt = $pdo->prepare('SELECT * FROM languages WHERE id = ?');
    $stmt->execute([$id]);
    $editing = $stmt->fetch();
}

$languages = $pdo->query(
    'SELECT l.*, COUNT(DISTINCT t.id) as topic_count, COUNT(DISTINCT ls.id) as lesson_count
     FROM languages l
     LEFT JOIN topics t ON t.language_id = l.id
     LEFT JOIN lessons ls ON ls.topic_id = t.id
     GROUP BY l.id ORDER BY l.order_index'
)->fetchAll();

$page_title = 'Manage Languages';
require __DIR__ . '/../templates/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="fw-bold mb-0"><i class="bi bi-collection text-primary me-2"></i>Languages / Tracks</h1>
    <div class="d-flex gap-2">
        <a href="?action=create" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>Add Track</a>
        <a href="/admin/index.php" class="btn btn-outline-secondary btn-sm">← Dashboard</a>
    </div>
</div>

<?php if ($action === 'create' || ($action === 'edit' && $editing)): ?>
<div class="card border-0 shadow-sm rounded-4 mb-5">
    <div class="card-body p-4">
        <h4 class="fw-semibold mb-4"><?= $editing ? 'Edit: ' . h($editing['name']) : 'Create New Track' ?></h4>
        <?php if ($error): ?><div class="alert alert-danger"><?= h($error) ?></div><?php endif; ?>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="edit_id" value="<?= $editing ? $editing['id'] : '' ?>">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Name *</label>
                    <input type="text" class="form-control" name="name"
                           value="<?= h($editing['name'] ?? '') ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Slug *</label>
                    <input type="text" class="form-control" name="slug"
                           value="<?= h($editing['slug'] ?? '') ?>" placeholder="auto-generated">
                </div>
                <div class="col-md-1">
                    <label class="form-label fw-semibold">Icon</label>
                    <input type="text" class="form-control" name="icon"
                           value="<?= h($editing['icon'] ?? '') ?>" placeholder="⚙️">
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-semibold">Order</label>
                    <input type="number" class="form-control" name="order_index"
                           value="<?= (int)($editing['order_index'] ?? 0) ?>">
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold">Description</label>
                    <textarea class="form-control" name="description" rows="3"><?= h($editing['description'] ?? '') ?></textarea>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Level</label>
                    <select class="form-select" name="level">
                        <?php foreach (['', 'Beginner', 'Intermediate', 'Advanced'] as $lv): ?>
                        <option value="<?= $lv ?>" <?= ($editing['level'] ?? '') === $lv ? 'selected' : '' ?>>
                            <?= $lv ?: '— Select —' ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Estimated Hours</label>
                    <input type="number" class="form-control" name="estimated_hours"
                           value="<?= (int)($editing['estimated_hours'] ?? 0) ?>">
                </div>
            </div>
            <div class="mt-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary">Save</button>
                <a href="/admin/languages.php" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<div class="card border-0 shadow-sm rounded-4">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr>
                <th>Order</th><th>Icon</th><th>Name</th><th>Slug</th><th>Topics</th><th>Lessons</th><th>Level</th><th>Actions</th>
            </tr></thead>
            <tbody>
            <?php foreach ($languages as $l): ?>
            <tr>
                <td class="text-muted small"><?= $l['order_index'] ?></td>
                <td class="fs-5"><?= h($l['icon'] ?? '') ?></td>
                <td><a href="/language.php?slug=<?= urlencode($l['slug']) ?>"><?= h($l['name']) ?></a></td>
                <td class="text-muted small"><code><?= h($l['slug']) ?></code></td>
                <td><?= (int)$l['topic_count'] ?></td>
                <td><?= (int)$l['lesson_count'] ?></td>
                <td><?= h($l['level'] ?? '') ?></td>
                <td class="d-flex gap-1">
                    <a href="?action=edit&id=<?= $l['id'] ?>" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-pencil"></i>
                    </a>
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= $l['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger"
                                data-confirm="Delete '<?= h($l['name']) ?>' and all its content?">
                            <i class="bi bi-trash"></i>
                        </button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($languages)): ?>
            <tr><td colspan="8" class="text-center text-muted py-4">No languages yet.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/../templates/footer.php'; ?>
