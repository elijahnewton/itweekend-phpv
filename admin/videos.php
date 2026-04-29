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
        $pdo->prepare('DELETE FROM videos WHERE id = ?')->execute([(int)$_POST['id']]);
        flash('Video deleted.');
        redirect('/admin/videos.php');
    }

    if ($pa === 'save') {
        $title    = trim($_POST['title'] ?? '');
        $url      = trim($_POST['url'] ?? '');
        $desc     = trim($_POST['description'] ?? '');
        $lang_id  = (int)($_POST['language_id'] ?? 0) ?: null;
        $topic_id = (int)($_POST['topic_id'] ?? 0) ?: null;
        $duration = (int)($_POST['duration_seconds'] ?? 0) ?: null;
        $edit_id  = (int)($_POST['edit_id'] ?? 0);

        if ($title === '' || $url === '') {
            $error = 'Title and URL are required.';
        } else {
            if ($edit_id) {
                $pdo->prepare(
                    'UPDATE videos SET title=?,url=?,description=?,language_id=?,topic_id=?,duration_seconds=? WHERE id=?'
                )->execute([$title, $url, $desc, $lang_id, $topic_id, $duration, $edit_id]);
                flash('Video updated.');
            } else {
                $pdo->prepare(
                    'INSERT INTO videos (title,url,description,language_id,topic_id,duration_seconds) VALUES (?,?,?,?,?,?)'
                )->execute([$title, $url, $desc, $lang_id, $topic_id, $duration]);
                flash('Video added.');
            }
            redirect('/admin/videos.php');
        }
    }
}

$editing = null;
if ($action === 'edit' && $id) {
    $s = $pdo->prepare('SELECT * FROM videos WHERE id = ?');
    $s->execute([$id]);
    $editing = $s->fetch();
}

$videos = $pdo->query(
    'SELECT v.*, l.name as lang_name, t.name as topic_name
     FROM videos v
     LEFT JOIN languages l ON v.language_id = l.id
     LEFT JOIN topics t ON v.topic_id = t.id
     ORDER BY v.created_at DESC'
)->fetchAll();

$languages = $pdo->query('SELECT * FROM languages ORDER BY order_index')->fetchAll();
$topics    = $pdo->query(
    'SELECT t.*, l.name as lang_name FROM topics t JOIN languages l ON t.language_id = l.id ORDER BY l.order_index, t.order_index'
)->fetchAll();

$page_title = 'Manage Videos';
require __DIR__ . '/../templates/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="fw-bold mb-0"><i class="bi bi-camera-video text-primary me-2"></i>Videos</h1>
    <div class="d-flex gap-2">
        <a href="?action=create" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>Add Video</a>
        <a href="/admin/index.php" class="btn btn-outline-secondary btn-sm">← Dashboard</a>
    </div>
</div>

<?php if ($action === 'create' || ($action === 'edit' && $editing)): ?>
<div class="card border-0 shadow-sm rounded-4 mb-5">
    <div class="card-body p-4">
        <h4 class="fw-semibold mb-4"><?= $editing ? 'Edit Video' : 'Add Video' ?></h4>
        <?php if ($error): ?><div class="alert alert-danger"><?= h($error) ?></div><?php endif; ?>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="edit_id" value="<?= $editing ? $editing['id'] : '' ?>">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Title *</label>
                    <input type="text" class="form-control" name="title"
                           value="<?= h($editing['title'] ?? '') ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">URL * (YouTube/Vimeo/direct)</label>
                    <input type="url" class="form-control" name="url"
                           value="<?= h($editing['url'] ?? '') ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Language</label>
                    <select class="form-select" name="language_id">
                        <option value="">— None —</option>
                        <?php foreach ($languages as $l): ?>
                        <option value="<?= $l['id'] ?>"
                            <?= (int)($editing['language_id'] ?? 0) === (int)$l['id'] ? 'selected' : '' ?>>
                            <?= h($l['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Topic</label>
                    <select class="form-select" name="topic_id">
                        <option value="">— None —</option>
                        <?php foreach ($topics as $t): ?>
                        <option value="<?= $t['id'] ?>"
                            <?= (int)($editing['topic_id'] ?? 0) === (int)$t['id'] ? 'selected' : '' ?>>
                            [<?= h($t['lang_name']) ?>] <?= h($t['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Duration (seconds)</label>
                    <input type="number" class="form-control" name="duration_seconds"
                           value="<?= (int)($editing['duration_seconds'] ?? 0) ?: '' ?>">
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold">Description</label>
                    <textarea class="form-control" name="description" rows="2"><?= h($editing['description'] ?? '') ?></textarea>
                </div>
            </div>
            <div class="mt-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary">Save</button>
                <a href="/admin/videos.php" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<div class="card border-0 shadow-sm rounded-4">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Title</th><th>Language</th><th>Topic</th><th>Duration</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($videos as $v): ?>
            <tr>
                <td><a href="<?= h($v['url']) ?>" target="_blank" rel="noopener"><?= h($v['title']) ?></a></td>
                <td><?= $v['lang_name'] ? h($v['lang_name']) : '—' ?></td>
                <td><?= $v['topic_name'] ? h($v['topic_name']) : '—' ?></td>
                <td class="text-muted small">
                    <?php if ($v['duration_seconds']): ?>
                    <?= (int)floor($v['duration_seconds']/60) ?>:<?= str_pad($v['duration_seconds']%60,2,'0',STR_PAD_LEFT) ?>
                    <?php else: ?>—<?php endif; ?>
                </td>
                <td class="d-flex gap-1">
                    <a href="?action=edit&id=<?= $v['id'] ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= $v['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger"
                                data-confirm="Delete '<?= h($v['title']) ?>'?">
                            <i class="bi bi-trash"></i>
                        </button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($videos)): ?>
            <tr><td colspan="5" class="text-center text-muted py-4">No videos yet.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/../templates/footer.php'; ?>
