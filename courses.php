<?php
session_start();
require_once __DIR__ . '/functions.php';
require_login();

$pdo  = get_db();
$user = current_user();
$page = max(1, (int)($_GET['page'] ?? 1));
$search = trim($_GET['q'] ?? '');

$where = 'WHERE c.is_published = 1';
$params = [];
if ($search !== '') {
    $where .= ' AND (c.title LIKE ? OR c.description LIKE ?)';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}

$base_sql = "SELECT c.*, u.name as instructor_name,
    COUNT(DISTINCT e.user_id) as student_count,
    COUNT(DISTINCT l.id) as lesson_count
    FROM courses c
    JOIN users u ON c.instructor_id = u.id
    LEFT JOIN enrollments e ON e.course_id = c.id
    LEFT JOIN lessons l ON l.course_id = c.id
    $where
    GROUP BY c.id
    ORDER BY c.created_at DESC";

$result = paginate($pdo, $base_sql, $params, $page, 12);
$courses = $result['rows'];

// Check which courses the current user is enrolled in
$enrolled_ids = [];
if ($user['role'] === ROLE_STUDENT) {
    $stmt = $pdo->prepare('SELECT course_id FROM enrollments WHERE user_id = ?');
    $stmt->execute([$user['id']]);
    $enrolled_ids = array_column($stmt->fetchAll(), 'course_id');
}

$page_title = 'Browse Courses';
require __DIR__ . '/templates/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="fw-bold mb-0"><i class="bi bi-collection me-2 text-primary"></i>Browse Courses</h2>
</div>

<form method="get" class="row g-2 mb-4">
    <div class="col">
        <input type="text" class="form-control" name="q" placeholder="Search courses…"
               value="<?= h($search) ?>">
    </div>
    <div class="col-auto">
        <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i></button>
        <?php if ($search): ?>
        <a href="/courses.php" class="btn btn-outline-secondary">Clear</a>
        <?php endif; ?>
    </div>
</form>

<?php if (empty($courses)): ?>
<div class="alert alert-info">
    <?= $search ? 'No courses matched your search.' : 'No published courses available yet.' ?>
</div>
<?php else: ?>
<div class="row g-4">
    <?php foreach ($courses as $c):
        $is_enrolled = in_array((int)$c['id'], $enrolled_ids, true);
    ?>
    <div class="col-md-4 col-lg-3">
        <div class="card course-card shadow-sm h-100">
            <?php if ($c['thumbnail']): ?>
            <img src="<?= h($c['thumbnail']) ?>" class="card-img-top" alt="">
            <?php else: ?>
            <div class="course-thumb-placeholder">
                <i class="bi bi-collection"></i>
            </div>
            <?php endif; ?>
            <div class="card-body d-flex flex-column">
                <h5 class="card-title"><?= h($c['title']) ?></h5>
                <p class="card-text text-muted small flex-grow-1">
                    <?= h(mb_substr($c['description'], 0, 100)) ?><?= strlen($c['description']) > 100 ? '…' : '' ?>
                </p>
                <div class="d-flex gap-3 text-muted small mb-3">
                    <span><i class="bi bi-person me-1"></i><?= h($c['instructor_name']) ?></span>
                    <span><i class="bi bi-book me-1"></i><?= $c['lesson_count'] ?></span>
                    <span><i class="bi bi-people me-1"></i><?= $c['student_count'] ?></span>
                </div>
                <?php if ($is_enrolled): ?>
                <a href="/course.php?id=<?= $c['id'] ?>" class="btn btn-success btn-sm w-100">
                    <i class="bi bi-play-fill me-1"></i>Continue
                </a>
                <?php elseif ($user['role'] === ROLE_STUDENT): ?>
                <a href="/enroll.php?course_id=<?= $c['id'] ?>" class="btn btn-primary btn-sm w-100">
                    <i class="bi bi-plus-circle me-1"></i>Enroll
                </a>
                <?php else: ?>
                <a href="/course.php?id=<?= $c['id'] ?>" class="btn btn-outline-primary btn-sm w-100">
                    View Course
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php if ($result['pages'] > 1): ?>
<nav class="mt-4">
    <ul class="pagination justify-content-center">
        <?php for ($i = 1; $i <= $result['pages']; $i++): ?>
        <li class="page-item <?= $i === $result['current'] ? 'active' : '' ?>">
            <a class="page-link" href="?page=<?= $i ?><?= $search ? '&q=' . urlencode($search) : '' ?>">
                <?= $i ?>
            </a>
        </li>
        <?php endfor; ?>
    </ul>
</nav>
<?php endif; ?>
<?php endif; ?>

<?php require __DIR__ . '/templates/footer.php'; ?>
