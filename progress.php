<?php
session_start();
require_once __DIR__ . '/functions.php';
require_login();

$pdo  = get_db();
$user = current_user();

// Language-level progress
$languages = $pdo->query('SELECT * FROM languages ORDER BY order_index')->fetchAll();
$lang_progress = [];
foreach ($languages as $lang) {
    $prog = get_language_progress($user['id'], $lang['id']);
    if ($prog['total'] === 0) continue;
    $lang_progress[] = [
        'language'           => $lang,
        'total_lessons'      => $prog['total'],
        'completed_lessons'  => $prog['completed'],
        'percent'            => $prog['pct'],
        'certificate_earned' => $prog['pct'] === 100,
    ];
}

// Recently accessed
$recent_stmt = $pdo->prepare(
    'SELECT up.*, l.title as lesson_title, l.slug as lesson_slug,
     t.name as topic_name, t.slug as topic_slug,
     lg.name as lang_name, lg.slug as lang_slug
     FROM user_progress up
     JOIN lessons l ON up.lesson_id = l.id
     JOIN topics t ON l.topic_id = t.id
     JOIN languages lg ON t.language_id = lg.id
     WHERE up.user_id = ?
     ORDER BY up.last_accessed_at DESC
     LIMIT 5'
);
$recent_stmt->execute([$user['id']]);
$recent = $recent_stmt->fetchAll();

$page_title = 'My Progress';
require __DIR__ . '/templates/header.php';
?>

<h1 class="fw-bold mb-4"><i class="bi bi-bar-chart text-primary me-2"></i>My Progress</h1>

<?php if (empty($lang_progress)): ?>
<div class="alert alert-info">
    You haven&rsquo;t started any lessons yet.
    <a href="/learn.php">Browse the learning tracks</a> to get started!
</div>
<?php else: ?>
<div class="row g-4 mb-5">
    <?php foreach ($lang_progress as $lp): ?>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-body p-4">
                <div class="d-flex align-items-center gap-2 mb-3">
                    <span class="fs-2"><?= h($lp['language']['icon'] ?? '📚') ?></span>
                    <div>
                        <h5 class="fw-bold mb-0"><?= h($lp['language']['name']) ?></h5>
                        <span class="text-muted small"><?= $lp['completed_lessons'] ?>/<?= $lp['total_lessons'] ?> lessons</span>
                    </div>
                </div>
                <div class="progress mb-2" style="height:10px">
                    <div class="progress-bar <?= $lp['certificate_earned'] ? 'bg-success' : 'bg-primary' ?>"
                         style="width:<?= $lp['percent'] ?>%"></div>
                </div>
                <div class="d-flex justify-content-between align-items-center">
                    <span class="fw-semibold"><?= $lp['percent'] ?>%</span>
                    <?php if ($lp['certificate_earned']): ?>
                    <span class="badge bg-success"><i class="bi bi-trophy-fill me-1"></i>Completed!</span>
                    <?php endif; ?>
                </div>
                <a href="/language.php?slug=<?= urlencode($lp['language']['slug']) ?>"
                   class="btn btn-outline-primary btn-sm w-100 mt-3">Continue &rarr;</a>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if (!empty($recent)): ?>
<h3 class="fw-semibold mb-3">Recently Accessed</h3>
<div class="table-responsive">
    <table class="table table-hover align-middle">
        <thead><tr>
            <th>Lesson</th><th>Track</th><th>Topic</th><th>Status</th><th>Last Accessed</th>
        </tr></thead>
        <tbody>
        <?php foreach ($recent as $r): ?>
        <tr>
            <td>
                <a href="/lesson.php?lang=<?= urlencode($r['lang_slug']) ?>&topic=<?= urlencode($r['topic_slug']) ?>&slug=<?= urlencode($r['lesson_slug']) ?>">
                    <?= h($r['lesson_title']) ?>
                </a>
            </td>
            <td><a href="/language.php?slug=<?= urlencode($r['lang_slug']) ?>"><?= h($r['lang_name']) ?></a></td>
            <td><?= h($r['topic_name']) ?></td>
            <td>
                <?php if ($r['completed_at']): ?>
                <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Complete</span>
                <?php else: ?>
                <span class="badge bg-warning text-dark"><i class="bi bi-hourglass me-1"></i>In Progress</span>
                <?php endif; ?>
            </td>
            <td class="text-muted small"><?= h(substr($r['last_accessed_at'], 0, 16)) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php require __DIR__ . '/templates/footer.php'; ?>
