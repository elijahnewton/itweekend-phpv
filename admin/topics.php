<?php
session_start();
require_once __DIR__ . '/../functions.php';
require_admin();

$pdo    = get_db();
$action = $_GET['action'] ?? 'list';
$id     = (int)($_GET['id'] ?? 0);
$error  = '';
$lang_filter = (int)($_GET['language_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $pa = $_POST['action'] ?? '';

    if ($pa === 'delete' && (int)$_POST['id'] > 0) {
        $pdo->prepare('DELETE FROM topics WHERE id = ?')->execute([(int)$_POST['id']]);
        flash('Topic deleted.');
        redirect('/admin/topics.php');
    }

    if ($pa === 'save') {
        $lang_id = (int)($_POST['language_id'] ?? 0);
        $name    = trim($_POST['name'] ?? '');
        $slug    = slugify(trim($_POST['slug'] ?? $name));
        $desc    = trim($_POST['description'] ?? '');
        $order   = (int)($_POST['order_index'] ?? 0);
        $edit_id = (int)($_POST['edit_id'] ?? 0);

        if ($lang_id <= 0 || $name === '') {
            $error = 'Language and name are required.';
        } else {
            if ($edit_id) {
                $pdo->prepare('UPDATE topics SET language_id=?,name=?,slug=?,description=?,order_index=? WHERE id=?')
                    ->execute([$lang_id, $name, $slug, $desc, $order, $edit_id]);
                flash('Topic updated.');
            } else {
                $pdo->prepare('INSERT INTO topics (language_id,name,slug,description,order_index) VALUES (?,?,?,?,?)')
                    ->execute([$lang_id, $name, $slug, $desc, $order]);
                flash('Topic created.');
            }
            redirect('/admin/topics.php');
        }
    }
}

$editing = null;
if ($action === 'edit' && $id) {
    $s = $pdo->prepare('SELECT * FROM topics WHERE id = ?');
    $s->execute([$id]);
    $editing = $s->fetch();
}

$languages = $pdo->query('SELECT * FROM languages ORDER BY order_index')->fetchAll();

$where = '';
$params = [];
if ($lang_filter) {
    $where = 'WHERE t.language_id = ?';
    $params[] = $lang_filter;
}
$topics = $pdo->prepare(
    "SELECT t.*, l.name as lang_name, COUNT(ls.id) as lesson_count
     FROM topics t JOIN languages l ON t.language_id = l.id
     LEFT JOIN lessons ls ON ls.topic_id = t.id
     $where GROUP BY t.id ORDER BY l.order_index, t.order_index"
);
$topics->execute($params);
$topics = $topics->fetchAll();

$page_title = 'Manage Topics';
require __DIR__ . '/../templates/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="fw-bold mb-0"><i class="bi bi-folder text-primary me-2"></i>Topics</h1>
    <div class="d-flex gap-2">
        <a href="?action=create" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>Add Topic</a>
        <a href="/admin/index.php" class="btn btn-outline-secondary btn-sm">← Dashboard</a>
    </div>
</div>

<?php if ($action === 'create' || ($action === 'edit' && $editing)): ?>
<div class="card border-0 shadow-sm rounded-4 mb-5">
    <div class="card-body p-4">
        <h4 class="fw-semibold mb-4"><?= $editing ? 'Edit Topic' : 'New Topic' ?></h4>
        <?php if ($error): ?><div class="alert alert-danger"><?= h($error) ?></div><?php endif; ?>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="edit_id" value="<?= $editing ? $editing['id'] : '' ?>">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Language *</label>
                    <select class="form-select" name="language_id" required>
                        <option value="">— Choose —</option>
                        <?php foreach ($languages as $l): ?>
                        <option value="<?= $l['id'] ?>"
                            <?= (int)($editing['language_id'] ?? 0) === (int)$l['id'] ? 'selected' : '' ?>>
                            <?= h($l['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Name *</label>
                    <input type="text" class="form-control" name="name"
                           value="<?= h($editing['name'] ?? '') ?>" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-semibold">Slug</label>
                    <input type="text" class="form-control" name="slug"
                           value="<?= h($editing['slug'] ?? '') ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-semibold">Order</label>
                    <input type="number" class="form-control" name="order_index"
                           value="<?= (int)($editing['order_index'] ?? 0) ?>">
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold">Description</label>
                    <textarea class="form-control" name="description" rows="2"><?= h($editing['description'] ?? '') ?></textarea>
                </div>
            </div>
            <div class="mt-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary">Save</button>
                <a href="/admin/topics.php" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- Filter -->
<form method="get" class="row g-2 mb-4">
    <div class="col-auto">
        <select class="form-select form-select-sm" name="language_id" onchange="this.form.submit()">
            <option value="">All Languages</option>
            <?php foreach ($languages as $l): ?>
            <option value="<?= $l['id'] ?>" <?= $lang_filter === (int)$l['id'] ? 'selected' : '' ?>>
                <?= h($l['name']) ?>
            </option>
            <?php endforeach; ?>
        </select>
    </div>
</form>

<div class="card border-0 shadow-sm rounded-4">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Language</th><th>Name</th><th>Slug</th><th>Lessons</th><th>Order</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($topics as $t): ?>
            <tr>
                <td><span class="badge bg-primary"><?= h($t['lang_name']) ?></span></td>
                <td><?= h($t['name']) ?></td>
                <td class="text-muted small"><code><?= h($t['slug']) ?></code></td>
                <td><?= (int)$t['lesson_count'] ?></td>
                <td><?= $t['order_index'] ?></td>
                <td class="d-flex gap-1">
                    <a href="?action=edit&id=<?= $t['id'] ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= $t['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger"
                                data-confirm="Delete topic '<?= h($t['name']) ?>'?">
                            <i class="bi bi-trash"></i>
                        </button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($topics)): ?>
            <tr><td colspan="6" class="text-center text-muted py-4">No topics.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/../templates/footer.php'; ?>
