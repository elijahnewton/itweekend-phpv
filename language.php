<?php
session_start();
require_once __DIR__ . '/functions.php';

$pdo  = get_db();
$user = current_user();
$slug = $_GET['slug'] ?? '';

$lang = $pdo->prepare('SELECT * FROM languages WHERE slug = ?');
$lang->execute([$slug]);
$lang = $lang->fetch();

if (!$lang) {
    flash('Language not found.', 'danger');
    redirect('/learn.php');
}

$topics = $pdo->prepare(
    'SELECT t.*, COUNT(l.id) as lesson_count
     FROM topics t
     LEFT JOIN lessons l ON l.topic_id = t.id
     WHERE t.language_id = ?
     GROUP BY t.id
     ORDER BY t.order_index, t.id'
);
$topics->execute([$lang['id']]);
$topics = $topics->fetchAll();

$page_title = h($lang['name']);
require __DIR__ . '/templates/header.php';
?>

<nav aria-label="breadcrumb" class="mb-4">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="/learn.php">Learn</a></li>
        <li class="breadcrumb-item active"><?= h($lang['name']) ?></li>
    </ol>
</nav>

<div class="row g-5 mb-5">
    <div class="col-lg-8">
        <div class="d-flex align-items-center gap-3 mb-3">
            <div class="fs-1"><?= h($lang['icon'] ?? '📚') ?></div>
            <div>
                <h1 class="fw-bold mb-1"><?= h($lang['name']) ?></h1>
                <div class="d-flex gap-2">
                    <span class="badge bg-secondary"><?= h($lang['level'] ?? '') ?></span>
                    <?php if ($lang['estimated_hours']): ?>
                    <span class="badge bg-light text-dark border">
                        <i class="bi bi-clock me-1"></i>~<?= (int)$lang['estimated_hours'] ?>h
                    </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <p class="lead text-muted mb-4"><?= h($lang['description'] ?? '') ?></p>

        <?php if ($user):
            $lang_prog = get_language_progress($user['id'], $lang['id']);
            if ($lang_prog['total'] > 0): ?>
        <div class="mb-4">
            <div class="d-flex justify-content-between small mb-1">
                <span class="fw-semibold">Overall Progress</span>
                <span><?= $lang_prog['pct'] ?>% &mdash; <?= $lang_prog['completed'] ?>/<?= $lang_prog['total'] ?> lessons</span>
            </div>
            <div class="progress" style="height:10px">
                <div class="progress-bar bg-success" style="width:<?= $lang_prog['pct'] ?>%"></div>
            </div>
        </div>
        <?php endif; endif; ?>

        <h3 class="fw-semibold mb-3">Topics</h3>
        <?php if (empty($topics)): ?>
        <p class="text-muted">No topics available yet.</p>
        <?php else: ?>
        <div class="row g-3">
            <?php foreach ($topics as $i => $topic):
                $tprog = $user ? get_topic_progress($user['id'], $topic['id']) : null;
            ?>
            <div class="col-md-6">
                <a href="/topic.php?lang=<?= urlencode($lang['slug']) ?>&slug=<?= urlencode($topic['slug']) ?>"
                   class="card border-0 shadow-sm rounded-3 h-100 text-decoration-none hover-lift">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <h5 class="fw-semibold mb-0 text-dark"><?= h($topic['name']) ?></h5>
                            <span class="badge bg-primary rounded-pill"><?= (int)$topic['lesson_count'] ?></span>
                        </div>
                        <?php if ($topic['description']): ?>
                        <p class="text-muted small mb-2"><?= h($topic['description']) ?></p>
                        <?php endif; ?>
                        <?php if ($tprog && $tprog['total'] > 0): ?>
                        <div class="progress mt-2" style="height:4px">
                            <div class="progress-bar bg-success" style="width:<?= $tprog['pct'] ?>%"></div>
                        </div>
                        <div class="text-muted small mt-1"><?= $tprog['completed'] ?>/<?= $tprog['total'] ?></div>
                        <?php endif; ?>
                    </div>
                </a>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <div class="col-lg-4">
        <div class="card border-0 shadow-sm rounded-4 sticky-top" style="top:1rem">
            <div class="card-body p-4">
                <h5 class="fw-semibold mb-3">Track Info</h5>
                <ul class="list-unstyled small mb-3">
                    <li class="mb-2"><i class="bi bi-folder text-primary me-2"></i><strong><?= count($topics) ?></strong> topics</li>
                    <li class="mb-2">
                        <i class="bi bi-file-text text-primary me-2"></i>
                        <strong><?= array_sum(array_column($topics, 'lesson_count')) ?></strong> lessons
                    </li>
                    <?php if ($lang['estimated_hours']): ?>
                    <li class="mb-2"><i class="bi bi-clock text-primary me-2"></i>~<strong><?= (int)$lang['estimated_hours'] ?></strong> hours</li>
                    <?php endif; ?>
                    <li><i class="bi bi-bar-chart text-primary me-2"></i><strong><?= h($lang['level'] ?? 'All levels') ?></strong></li>
                </ul>
                <?php if (!empty($topics)): ?>
                <a href="/topic.php?lang=<?= urlencode($lang['slug']) ?>&slug=<?= urlencode($topics[0]['slug']) ?>"
                   class="btn btn-primary w-100">
                    <i class="bi bi-play-fill me-2"></i>Start First Topic
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/templates/footer.php'; ?>
