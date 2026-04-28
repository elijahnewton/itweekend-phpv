<?php
session_start();
require_once __DIR__ . '/functions.php';

$pdo  = get_db();
$user = current_user();

$languages = $pdo->query(
    'SELECT l.*, COUNT(DISTINCT t.id) as topic_count, COUNT(DISTINCT ls.id) as lesson_count
     FROM languages l
     LEFT JOIN topics t ON t.language_id = l.id
     LEFT JOIN lessons ls ON ls.topic_id = t.id
     GROUP BY l.id
     ORDER BY l.order_index'
)->fetchAll();

$page_title = 'Learn';
require __DIR__ . '/templates/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="fw-bold mb-1"><i class="bi bi-book text-primary me-2"></i>Learn</h1>
        <p class="text-muted mb-0">Choose a track to start learning</p>
    </div>
    <?php if ($user): ?>
    <a href="/progress.php" class="btn btn-outline-primary btn-sm">
        <i class="bi bi-bar-chart me-1"></i>My Progress
    </a>
    <?php endif; ?>
</div>

<?php if (empty($languages)): ?>
<div class="alert alert-info">No learning tracks available yet.</div>
<?php else: ?>
<div class="row g-4">
    <?php
    $gradients = [
        'from-indigo-500 to-violet-600' => 'linear-gradient(135deg,#6366f1,#7c3aed)',
        'from-blue-500 to-cyan-500'     => 'linear-gradient(135deg,#3b82f6,#06b6d4)',
        'from-violet-500 to-purple-600' => 'linear-gradient(135deg,#8b5cf6,#9333ea)',
    ];
    foreach ($languages as $lang):
        $gradient = $gradients[$lang['color'] ?? ''] ?? 'linear-gradient(135deg,#6366f1,#7c3aed)';
        $prog = $user ? get_language_progress($user['id'], $lang['id']) : null;
    ?>
    <div class="col-md-4">
        <a href="/language.php?slug=<?= urlencode($lang['slug']) ?>" class="text-decoration-none">
            <div class="card border-0 shadow-sm rounded-4 h-100 course-card">
                <div class="course-thumb-placeholder rounded-top-4"
                     style="background:<?= $gradient ?>; height:140px; display:flex; align-items:center; justify-content:center; font-size:3rem;">
                    <?= h($lang['icon'] ?? '📚') ?>
                </div>
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <h4 class="fw-bold mb-0 text-dark"><?= h($lang['name']) ?></h4>
                        <span class="badge bg-light text-dark border"><?= h($lang['level'] ?? '') ?></span>
                    </div>
                    <p class="text-muted small mb-3"><?= h($lang['description'] ?? '') ?></p>
                    <div class="d-flex gap-3 text-muted small mb-3">
                        <span><i class="bi bi-folder me-1"></i><?= (int)$lang['topic_count'] ?> topics</span>
                        <span><i class="bi bi-file-text me-1"></i><?= (int)$lang['lesson_count'] ?> lessons</span>
                        <?php if ($lang['estimated_hours']): ?>
                        <span><i class="bi bi-clock me-1"></i>~<?= (int)$lang['estimated_hours'] ?>h</span>
                        <?php endif; ?>
                    </div>
                    <?php if ($prog && $prog['total'] > 0): ?>
                    <div>
                        <div class="d-flex justify-content-between small text-muted mb-1">
                            <span>Progress</span>
                            <span><?= $prog['pct'] ?>%</span>
                        </div>
                        <div class="progress" style="height:6px">
                            <div class="progress-bar bg-success" style="width:<?= $prog['pct'] ?>%"></div>
                        </div>
                    </div>
                    <?php else: ?>
                    <span class="btn btn-primary btn-sm w-100">Start Learning &rarr;</span>
                    <?php endif; ?>
                </div>
            </div>
        </a>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php require __DIR__ . '/templates/footer.php'; ?>
