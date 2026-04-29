<?php
session_start();
require_once __DIR__ . '/../functions.php';
require_admin();

$pdo    = get_db();
$action = $_GET['action'] ?? 'list';
$id     = (int)($_GET['id'] ?? 0);
$error  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $pa = $_POST['action'] ?? '';

    if ($pa === 'delete' && (int)$_POST['id'] > 0) {
        $pdo->prepare('DELETE FROM lessons WHERE id = ?')->execute([(int)$_POST['id']]);
        flash('Lesson deleted.');
        redirect('/admin/lessons.php');
    }

    if ($pa === 'save') {
        $topic_id = (int)($_POST['topic_id'] ?? 0);
        $title    = trim($_POST['title'] ?? '');
        $slug     = slugify(trim($_POST['slug'] ?? $title));
        $content  = trim($_POST['content_html'] ?? '');
        $code     = trim($_POST['code_example'] ?? '');
        $codelang = trim($_POST['code_language'] ?? 'plaintext');
        $order    = (int)($_POST['order_index'] ?? 0);
        $mins     = (int)($_POST['estimated_minutes'] ?? 0);
        $edit_id  = (int)($_POST['edit_id'] ?? 0);

        if ($topic_id <= 0 || $title === '') {
            $error = 'Topic and title are required.';
        } else {
            if ($edit_id) {
                $pdo->prepare(
                    'UPDATE lessons SET topic_id=?,title=?,slug=?,content_html=?,code_example=?,code_language=?,order_index=?,estimated_minutes=? WHERE id=?'
                )->execute([$topic_id, $title, $slug, $content, $code, $codelang, $order, $mins ?: null, $edit_id]);
                flash('Lesson updated.');
            } else {
                $pdo->prepare(
                    'INSERT INTO lessons (topic_id,title,slug,content_html,code_example,code_language,order_index,estimated_minutes) VALUES (?,?,?,?,?,?,?,?)'
                )->execute([$topic_id, $title, $slug, $content, $code, $codelang, $order, $mins ?: null]);
                flash('Lesson created.');
            }
            redirect('/admin/lessons.php');
        }
    }
}

$editing = null;
if ($action === 'edit' && $id) {
    $s = $pdo->prepare('SELECT * FROM lessons WHERE id = ?');
    $s->execute([$id]);
    $editing = $s->fetch();
}

// Grouped topics for dropdown
$topics_grouped = [];
foreach ($pdo->query(
    'SELECT t.id, t.name as topic_name, l.name as lang_name, l.id as lang_id
     FROM topics t JOIN languages l ON t.language_id = l.id ORDER BY l.order_index, t.order_index'
)->fetchAll() as $row) {
    $topics_grouped[$row['lang_name']][] = $row;
}

$topic_filter = (int)($_GET['topic_id'] ?? 0);
$where = '';
$params = [];
if ($topic_filter) {
    $where = 'WHERE l.topic_id = ?';
    $params[] = $topic_filter;
}

$lessons = $pdo->prepare(
    "SELECT l.*, t.name as topic_name, lg.name as lang_name
     FROM lessons l JOIN topics t ON l.topic_id = t.id JOIN languages lg ON t.language_id = lg.id
     $where ORDER BY lg.order_index, t.order_index, l.order_index"
);
$lessons->execute($params);
$lessons = $lessons->fetchAll();

$page_title = 'Manage Lessons';
require __DIR__ . '/../templates/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="fw-bold mb-0"><i class="bi bi-file-text text-primary me-2"></i>Lessons</h1>
    <div class="d-flex gap-2">
        <a href="?action=create" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>Add Lesson</a>
        <a href="/admin/index.php" class="btn btn-outline-secondary btn-sm">← Dashboard</a>
    </div>
</div>

<?php if ($action === 'create' || ($action === 'edit' && $editing)): ?>
<div class="card border-0 shadow-sm rounded-4 mb-5">
    <div class="card-body p-4">
        <h4 class="fw-semibold mb-4"><?= $editing ? 'Edit Lesson' : 'New Lesson' ?></h4>
        <?php if ($error): ?><div class="alert alert-danger"><?= h($error) ?></div><?php endif; ?>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="edit_id" value="<?= $editing ? $editing['id'] : '' ?>">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Topic *</label>
                    <select class="form-select" name="topic_id" required>
                        <option value="">— Choose —</option>
                        <?php foreach ($topics_grouped as $lang_name => $tlist): ?>
                        <optgroup label="<?= h($lang_name) ?>">
                            <?php foreach ($tlist as $t): ?>
                            <option value="<?= $t['id'] ?>"
                                <?= (int)($editing['topic_id'] ?? 0) === (int)$t['id'] ? 'selected' : '' ?>>
                                <?= h($t['topic_name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </optgroup>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Title *</label>
                    <input type="text" class="form-control" name="title"
                           value="<?= h($editing['title'] ?? '') ?>" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-semibold">Slug</label>
                    <input type="text" class="form-control" name="slug"
                           value="<?= h($editing['slug'] ?? '') ?>">
                </div>
                <div class="col-md-1">
                    <label class="form-label fw-semibold">Order</label>
                    <input type="number" class="form-control" name="order_index"
                           value="<?= (int)($editing['order_index'] ?? 0) ?>">
                </div>
                <div class="col-md-1">
                    <label class="form-label fw-semibold">Minutes</label>
                    <input type="number" class="form-control" name="estimated_minutes"
                           value="<?= (int)($editing['estimated_minutes'] ?? 0) ?>">
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold">Content (HTML allowed)</label>
                    <textarea class="form-control font-monospace" name="content_html"
                              rows="6"><?= h($editing['content_html'] ?? '') ?></textarea>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-semibold">Code Language</label>
                    <select class="form-select" name="code_language">
                        <?php foreach (['plaintext','c','cpp','python','javascript','html','css','bash','sql','php'] as $cl): ?>
                        <option value="<?= $cl ?>" <?= ($editing['code_language'] ?? '') === $cl ? 'selected' : '' ?>>
                            <?= $cl ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-10">
                    <label class="form-label fw-semibold">Code Example</label>
                    <textarea class="form-control font-monospace" name="code_example"
                              rows="6"><?= h($editing['code_example'] ?? '') ?></textarea>
                </div>
            </div>
            <div class="mt-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary">Save</button>
                <a href="/admin/lessons.php" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<div class="card border-0 shadow-sm rounded-4">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Track</th><th>Topic</th><th>Title</th><th>Slug</th><th>Order</th><th>Mins</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($lessons as $l): ?>
            <tr>
                <td><span class="badge bg-primary"><?= h($l['lang_name']) ?></span></td>
                <td><?= h($l['topic_name']) ?></td>
                <td><?= h($l['title']) ?></td>
                <td class="text-muted small"><code><?= h($l['slug']) ?></code></td>
                <td><?= $l['order_index'] ?></td>
                <td><?= $l['estimated_minutes'] ?? '—' ?></td>
                <td class="d-flex gap-1">
                    <a href="?action=edit&id=<?= $l['id'] ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= $l['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger"
                                data-confirm="Delete '<?= h($l['title']) ?>'?">
                            <i class="bi bi-trash"></i>
                        </button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($lessons)): ?>
            <tr><td colspan="7" class="text-center text-muted py-4">No lessons.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/../templates/footer.php'; ?>
