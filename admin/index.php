<?php
session_start();
require_once __DIR__ . '/../functions.php';
require_admin();

$pdo = get_db();

$stats = [
    'users'    => (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn(),
    'languages'=> (int)$pdo->query('SELECT COUNT(*) FROM languages')->fetchColumn(),
    'topics'   => (int)$pdo->query('SELECT COUNT(*) FROM topics')->fetchColumn(),
    'lessons'  => (int)$pdo->query('SELECT COUNT(*) FROM lessons')->fetchColumn(),
    'videos'   => (int)$pdo->query('SELECT COUNT(*) FROM videos')->fetchColumn(),
    'progress' => (int)$pdo->query('SELECT COUNT(*) FROM user_progress WHERE completed_at IS NOT NULL')->fetchColumn(),
];

$page_title = 'Admin Dashboard';
require __DIR__ . '/../templates/header.php';
?>

<h1 class="fw-bold mb-4"><i class="bi bi-speedometer2 text-primary me-2"></i>Admin Dashboard</h1>

<div class="row g-4 mb-5">
    <?php foreach ([
        ['users',    'Users',            'bi-people-fill',        'text-primary',  '/admin/users.php'],
        ['languages','Languages/Tracks', 'bi-collection-fill',    'text-success',  '/admin/languages.php'],
        ['topics',   'Topics',           'bi-folder-fill',        'text-warning',  '/admin/topics.php'],
        ['lessons',  'Lessons',          'bi-file-text-fill',     'text-info',     '/admin/lessons.php'],
        ['videos',   'Videos',           'bi-camera-video-fill',  'text-danger',   '/admin/videos.php'],
        ['progress', 'Completions',      'bi-check-circle-fill',  'text-secondary','/progress.php'],
    ] as [$key, $label, $icon, $color, $href]): ?>
    <div class="col-6 col-md-4 col-lg-2">
        <a href="<?= h($href) ?>" class="text-decoration-none">
            <div class="card border-0 shadow-sm rounded-4 text-center p-3 h-100 hover-lift">
                <div class="<?= $color ?> mb-2" style="font-size:2rem"><i class="bi <?= $icon ?>"></i></div>
                <div class="fw-bold fs-4"><?= $stats[$key] ?></div>
                <div class="text-muted small"><?= h($label) ?></div>
            </div>
        </a>
    </div>
    <?php endforeach; ?>
</div>

<div class="row g-4">
    <div class="col-md-6">
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-header bg-transparent border-0 pt-4 pb-0 px-4">
                <h5 class="fw-semibold">Quick Actions</h5>
            </div>
            <div class="card-body p-4">
                <div class="d-grid gap-2">
                    <a href="/admin/languages.php?action=create" class="btn btn-outline-primary">
                        <i class="bi bi-plus-lg me-2"></i>Add Language/Track
                    </a>
                    <a href="/admin/topics.php?action=create" class="btn btn-outline-success">
                        <i class="bi bi-plus-lg me-2"></i>Add Topic
                    </a>
                    <a href="/admin/lessons.php?action=create" class="btn btn-outline-info">
                        <i class="bi bi-plus-lg me-2"></i>Add Lesson
                    </a>
                    <a href="/admin/quizzes.php?action=create" class="btn btn-outline-warning">
                        <i class="bi bi-plus-lg me-2"></i>Add Quiz
                    </a>
                    <a href="/admin/videos.php?action=create" class="btn btn-outline-danger">
                        <i class="bi bi-plus-lg me-2"></i>Add Video
                    </a>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <?php
        $recent_users = $pdo->query('SELECT * FROM users ORDER BY created_at DESC LIMIT 5')->fetchAll();
        ?>
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-header bg-transparent border-0 pt-4 pb-0 px-4 d-flex justify-content-between">
                <h5 class="fw-semibold">Recent Users</h5>
                <a href="/admin/users.php" class="btn btn-outline-primary btn-sm">All</a>
            </div>
            <div class="list-group list-group-flush px-2 pb-2">
                <?php foreach ($recent_users as $u): ?>
                <div class="list-group-item border-0 py-2 d-flex justify-content-between align-items-center">
                    <div>
                        <div class="fw-medium"><?= h($u['name'] ?: $u['email']) ?></div>
                        <div class="text-muted small"><?= h($u['email']) ?></div>
                    </div>
                    <span class="badge <?= $u['role'] === ROLE_ADMIN ? 'bg-warning text-dark' : 'bg-secondary' ?>">
                        <?= h($u['role']) ?>
                    </span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../templates/footer.php'; ?>
