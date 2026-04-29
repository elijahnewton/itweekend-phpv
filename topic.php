<?php
session_start();
require_once __DIR__ . '/functions.php';

$pdo  = get_db();
$user = current_user();

$lang_slug  = $_GET['lang'] ?? '';
$topic_slug = $_GET['slug'] ?? '';

$lang_row = $pdo->prepare('SELECT * FROM languages WHERE slug = ?');
$lang_row->execute([$lang_slug]);
$lang = $lang_row->fetch();
if (!$lang) { flash('Not found.', 'danger'); redirect('/learn.php'); }

$topic_row = $pdo->prepare('SELECT * FROM topics WHERE language_id = ? AND slug = ?');
$topic_row->execute([$lang['id'], $topic_slug]);
$topic = $topic_row->fetch();
if (!$topic) { flash('Topic not found.', 'danger'); redirect('/language.php?slug=' . urlencode($lang_slug)); }

$lessons = $pdo->prepare(
    'SELECT * FROM lessons WHERE topic_id = ? ORDER BY order_index, id'
);
$lessons->execute([$topic['id']]);
$lessons = $lessons->fetchAll();

// Fetch completed lesson IDs for this user in this topic
$completed_ids = [];
if ($user) {
    $s = $pdo->prepare(
        'SELECT lesson_id FROM user_progress
         WHERE user_id = ? AND lesson_id IN (
             SELECT id FROM lessons WHERE topic_id = ?
         ) AND completed_at IS NOT NULL'
    );
    $s->execute([$user['id'], $topic['id']]);
    $completed_ids = array_column($s->fetchAll(), 'lesson_id');
}

$topic_prog = $user ? get_topic_progress($user['id'], $topic['id']) : null;

$page_title = h($topic['name']) . ' — ' . h($lang['name']);
require __DIR__ . '/templates/header.php';
?>

<nav aria-label="breadcrumb" class="mb-4">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="/learn.php">Learn</a></li>
        <li class="breadcrumb-item"><a href="/language.php?slug=<?= urlencode($lang['slug']) ?>"><?= h($lang['name']) ?></a></li>
        <li class="breadcrumb-item active"><?= h($topic['name']) ?></li>
    </ol>
</nav>

<div class="row g-5">
    <div class="col-lg-8">
        <h1 class="fw-bold mb-2"><?= h($topic['name']) ?></h1>
        <?php if ($topic['description']): ?>
        <p class="lead text-muted mb-4"><?= h($topic['description']) ?></p>
        <?php endif; ?>

        <?php if ($topic_prog && $topic_prog['total'] > 0): ?>
        <div class="mb-4">
            <div class="d-flex justify-content-between small mb-1">
                <span class="fw-semibold">Topic Progress</span>
                <span><?= $topic_prog['pct'] ?>% &mdash; <?= $topic_prog['completed'] ?>/<?= $topic_prog['total'] ?></span>
            </div>
            <div class="progress" style="height:10px">
                <div class="progress-bar bg-success" style="width:<?= $topic_prog['pct'] ?>%"></div>
            </div>
        </div>
        <?php endif; ?>

        <h3 class="fw-semibold mb-3">Lessons</h3>
        <?php if (empty($lessons)): ?>
        <p class="text-muted">No lessons in this topic yet.</p>
        <?php else: ?>
        <div class="list-group lesson-sidebar">
            <?php foreach ($lessons as $i => $lesson):
                $done = in_array((int)$lesson['id'], array_map('intval', $completed_ids), true);
            ?>
            <a href="/lesson.php?lang=<?= urlencode($lang['slug']) ?>&topic=<?= urlencode($topic['slug']) ?>&slug=<?= urlencode($lesson['slug']) ?>"
               class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-3
                      <?= $done ? 'completed' : '' ?>">
                <div>
                    <span class="text-muted me-2 small"><?= $i + 1 ?>.</span>
                    <span class="fw-semibold"><?= h($lesson['title']) ?></span>
                    <?php if ($lesson['estimated_minutes']): ?>
                    <span class="text-muted small ms-2">
                        <i class="bi bi-clock me-1"></i><?= (int)$lesson['estimated_minutes'] ?> min
                    </span>
                    <?php endif; ?>
                </div>
                <?php if ($done): ?>
                <i class="bi bi-check-circle-fill text-success fs-5"></i>
                <?php else: ?>
                <i class="bi bi-chevron-right text-muted"></i>
                <?php endif; ?>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <div class="col-lg-4">
        <div class="card border-0 shadow-sm rounded-4 sticky-top" style="top:1rem">
            <div class="card-body p-4">
                <h6 class="fw-semibold mb-3 text-muted text-uppercase small">In this track</h6>
                <div class="fw-semibold mb-1"><?= h($lang['name']) ?></div>
                <div class="text-muted small mb-3"><?= h($topic['name']) ?></div>
                <hr>
                <ul class="list-unstyled small mb-3">
                    <li class="mb-1"><i class="bi bi-file-text text-primary me-2"></i><strong><?= count($lessons) ?></strong> lessons</li>
                </ul>
                <?php if (!empty($lessons)):
                    // Find first incomplete lesson
                    $next = null;
                    foreach ($lessons as $l) {
                        if (!in_array((int)$l['id'], array_map('intval', $completed_ids), true)) {
                            $next = $l; break;
                        }
                    }
                    $next = $next ?? $lessons[0];
                ?>
                <a href="/lesson.php?lang=<?= urlencode($lang['slug']) ?>&topic=<?= urlencode($topic['slug']) ?>&slug=<?= urlencode($next['slug']) ?>"
                   class="btn btn-primary w-100 mb-2">
                    <i class="bi bi-play-fill me-2"></i><?= $topic_prog && $topic_prog['completed'] > 0 ? 'Continue' : 'Start' ?>
                </a>
                <?php endif; ?>
                <a href="/language.php?slug=<?= urlencode($lang['slug']) ?>" class="btn btn-outline-secondary w-100 btn-sm">
                    <i class="bi bi-arrow-left me-1"></i>Back to <?= h($lang['name']) ?>
                </a>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/templates/footer.php'; ?>
