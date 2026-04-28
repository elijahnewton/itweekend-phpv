<?php
session_start();
require_once __DIR__ . '/functions.php';
require_login();

$pdo  = get_db();
$user = current_user();

// Stats
if ($user['role'] === ROLE_ADMIN) {
    $stats = [
        'users'    => (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn(),
        'courses'  => (int)$pdo->query('SELECT COUNT(*) FROM courses')->fetchColumn(),
        'enrollments' => (int)$pdo->query('SELECT COUNT(*) FROM enrollments')->fetchColumn(),
        'lessons'  => (int)$pdo->query('SELECT COUNT(*) FROM lessons')->fetchColumn(),
    ];
    $recent_courses = $pdo->query(
        'SELECT c.*, u.name as instructor_name FROM courses c
         JOIN users u ON c.instructor_id = u.id
         ORDER BY c.created_at DESC LIMIT 5'
    )->fetchAll();

} elseif ($user['role'] === ROLE_INSTRUCTOR) {
    $stmt = $pdo->prepare(
        'SELECT c.*, COUNT(DISTINCT e.user_id) as student_count, COUNT(DISTINCT l.id) as lesson_count
         FROM courses c
         LEFT JOIN enrollments e ON e.course_id = c.id
         LEFT JOIN lessons l ON l.course_id = c.id
         WHERE c.instructor_id = ?
         GROUP BY c.id
         ORDER BY c.created_at DESC LIMIT 5'
    );
    $stmt->execute([$user['id']]);
    $my_courses = $stmt->fetchAll();

    $stats = [
        'courses'     => (int)$pdo->prepare('SELECT COUNT(*) FROM courses WHERE instructor_id = ?')
            ->execute([$user['id']]) ? 0 : 0,
        'students'    => 0,
        'lessons'     => 0,
    ];
    $s2 = $pdo->prepare('SELECT COUNT(*) FROM courses WHERE instructor_id = ?');
    $s2->execute([$user['id']]);
    $stats['courses'] = (int)$s2->fetchColumn();

    $s3 = $pdo->prepare(
        'SELECT COUNT(DISTINCT e.user_id) FROM enrollments e
         JOIN courses c ON e.course_id = c.id WHERE c.instructor_id = ?'
    );
    $s3->execute([$user['id']]);
    $stats['students'] = (int)$s3->fetchColumn();

    $s4 = $pdo->prepare(
        'SELECT COUNT(*) FROM lessons l JOIN courses c ON l.course_id = c.id WHERE c.instructor_id = ?'
    );
    $s4->execute([$user['id']]);
    $stats['lessons'] = (int)$s4->fetchColumn();

} else {
    // Student
    $stmt = $pdo->prepare(
        'SELECT c.*, u.name as instructor_name, e.enrolled_at
         FROM enrollments e
         JOIN courses c ON e.course_id = c.id
         JOIN users u ON c.instructor_id = u.id
         WHERE e.user_id = ?
         ORDER BY e.enrolled_at DESC LIMIT 5'
    );
    $stmt->execute([$user['id']]);
    $enrolled_courses = $stmt->fetchAll();

    $s1 = $pdo->prepare('SELECT COUNT(*) FROM enrollments WHERE user_id = ?');
    $s1->execute([$user['id']]);
    $stats = ['enrolled' => (int)$s1->fetchColumn()];

    $s2 = $pdo->prepare(
        'SELECT COUNT(*) FROM lesson_progress lp
         JOIN lessons l ON lp.lesson_id = l.id
         JOIN courses c ON l.course_id = c.id
         JOIN enrollments e ON e.course_id = c.id AND e.user_id = lp.user_id
         WHERE lp.user_id = ?'
    );
    $s2->execute([$user['id']]);
    $stats['lessons_done'] = (int)$s2->fetchColumn();
}

$page_title = 'Dashboard';
require __DIR__ . '/templates/header.php';
?>

<div class="hero mb-5">
    <h1 class="display-6 fw-bold mb-1">
        <i class="bi bi-hand-wave me-2"></i>Welcome back, <?= h($user['name']) ?>!
    </h1>
    <p class="mb-0 opacity-75">
        <?php if ($user['role'] === ROLE_ADMIN): ?>
            You&rsquo;re logged in as <strong>Administrator</strong>. Manage the platform below.
        <?php elseif ($user['role'] === ROLE_INSTRUCTOR): ?>
            You&rsquo;re logged in as <strong>Instructor</strong>. Create and manage your courses.
        <?php else: ?>
            Keep learning! Browse courses and track your progress below.
        <?php endif; ?>
    </p>
</div>

<?php if ($user['role'] === ROLE_ADMIN): ?>
<!-- ADMIN DASHBOARD -->
<div class="row g-4 mb-5">
    <?php foreach ([
        ['label' => 'Total Users',    'value' => $stats['users'],       'icon' => 'bi-people-fill',     'color' => 'text-primary'],
        ['label' => 'Total Courses',  'value' => $stats['courses'],     'icon' => 'bi-collection-fill', 'color' => 'text-success'],
        ['label' => 'Enrollments',    'value' => $stats['enrollments'], 'icon' => 'bi-person-check-fill','color' => 'text-warning'],
        ['label' => 'Total Lessons',  'value' => $stats['lessons'],     'icon' => 'bi-book-fill',        'color' => 'text-info'],
    ] as $s): ?>
    <div class="col-6 col-md-3">
        <div class="card stat-card shadow-sm">
            <div class="stat-icon <?= $s['color'] ?>">
                <i class="bi <?= $s['icon'] ?>"></i>
            </div>
            <div class="stat-number"><?= $s['value'] ?></div>
            <div class="text-muted small"><?= $s['label'] ?></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<h4 class="mb-3 fw-semibold">Recent Courses</h4>
<div class="table-responsive">
    <table class="table table-hover align-middle">
        <thead><tr>
            <th>Title</th><th>Instructor</th><th>Status</th><th>Created</th><th></th>
        </tr></thead>
        <tbody>
        <?php foreach ($recent_courses as $rc): ?>
        <tr>
            <td><a href="/course.php?id=<?= $rc['id'] ?>"><?= h($rc['title']) ?></a></td>
            <td><?= h($rc['instructor_name']) ?></td>
            <td><?= $rc['is_published']
                ? '<span class="badge bg-success">Published</span>'
                : '<span class="badge bg-secondary">Draft</span>' ?></td>
            <td><?= h(substr($rc['created_at'], 0, 10)) ?></td>
            <td><a href="/admin/courses.php" class="btn btn-sm btn-outline-secondary">Manage</a></td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($recent_courses)): ?>
        <tr><td colspan="5" class="text-center text-muted">No courses yet.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
<div class="mt-3 d-flex gap-2">
    <a href="/admin/courses.php" class="btn btn-outline-primary">All Courses</a>
    <a href="/admin/users.php"   class="btn btn-outline-secondary">All Users</a>
</div>

<?php elseif ($user['role'] === ROLE_INSTRUCTOR): ?>
<!-- INSTRUCTOR DASHBOARD -->
<div class="row g-4 mb-5">
    <?php foreach ([
        ['label' => 'My Courses',    'value' => $stats['courses'],  'icon' => 'bi-collection-fill', 'color' => 'text-primary'],
        ['label' => 'My Students',   'value' => $stats['students'], 'icon' => 'bi-people-fill',     'color' => 'text-success'],
        ['label' => 'Total Lessons', 'value' => $stats['lessons'],  'icon' => 'bi-book-fill',       'color' => 'text-info'],
    ] as $s): ?>
    <div class="col-6 col-md-4">
        <div class="card stat-card shadow-sm">
            <div class="stat-icon <?= $s['color'] ?>">
                <i class="bi <?= $s['icon'] ?>"></i>
            </div>
            <div class="stat-number"><?= $s['value'] ?></div>
            <div class="text-muted small"><?= $s['label'] ?></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="fw-semibold mb-0">My Recent Courses</h4>
    <a href="/instructor/create_course.php" class="btn btn-primary btn-sm">
        <i class="bi bi-plus-lg me-1"></i>New Course
    </a>
</div>

<?php if (empty($my_courses)): ?>
<div class="alert alert-info">
    You haven&rsquo;t created any courses yet.
    <a href="/instructor/create_course.php">Create your first course</a>.
</div>
<?php else: ?>
<div class="row g-4">
    <?php foreach ($my_courses as $c): ?>
    <div class="col-md-4">
        <div class="card course-card shadow-sm h-100">
            <div class="course-thumb-placeholder">
                <i class="bi bi-collection"></i>
            </div>
            <div class="card-body">
                <h5 class="card-title"><?= h($c['title']) ?></h5>
                <p class="card-text text-muted small"><?= h(mb_substr($c['description'], 0, 80)) ?>…</p>
                <div class="d-flex gap-2 text-muted small mb-2">
                    <span><i class="bi bi-people me-1"></i><?= $c['student_count'] ?> students</span>
                    <span><i class="bi bi-book me-1"></i><?= $c['lesson_count'] ?> lessons</span>
                </div>
                <?= $c['is_published']
                    ? '<span class="badge bg-success mb-2">Published</span>'
                    : '<span class="badge bg-secondary mb-2">Draft</span>' ?>
            </div>
            <div class="card-footer bg-transparent d-flex gap-2">
                <a href="/course.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-outline-primary">View</a>
                <a href="/instructor/edit_course.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-outline-secondary">Edit</a>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<a href="/instructor/courses.php" class="btn btn-outline-primary mt-4">See All My Courses</a>
<?php endif; ?>

<?php else: ?>
<!-- STUDENT DASHBOARD -->
<div class="row g-4 mb-5">
    <div class="col-6 col-md-3">
        <div class="card stat-card shadow-sm">
            <div class="stat-icon text-primary"><i class="bi bi-bookmark-check-fill"></i></div>
            <div class="stat-number"><?= $stats['enrolled'] ?></div>
            <div class="text-muted small">Enrolled Courses</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card stat-card shadow-sm">
            <div class="stat-icon text-success"><i class="bi bi-check-circle-fill"></i></div>
            <div class="stat-number"><?= $stats['lessons_done'] ?></div>
            <div class="text-muted small">Lessons Completed</div>
        </div>
    </div>
</div>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="fw-semibold mb-0">My Enrolled Courses</h4>
    <a href="/courses.php" class="btn btn-primary btn-sm">
        <i class="bi bi-search me-1"></i>Browse Courses
    </a>
</div>

<?php if (empty($enrolled_courses)): ?>
<div class="alert alert-info">
    You&rsquo;re not enrolled in any courses yet.
    <a href="/courses.php">Browse available courses</a>.
</div>
<?php else: ?>
<div class="row g-4">
    <?php foreach ($enrolled_courses as $c):
        $prog = get_course_progress($user['id'], $c['id']); ?>
    <div class="col-md-4">
        <div class="card course-card shadow-sm h-100">
            <div class="course-thumb-placeholder">
                <i class="bi bi-collection"></i>
            </div>
            <div class="card-body">
                <h5 class="card-title"><?= h($c['title']) ?></h5>
                <p class="card-text text-muted small">
                    <i class="bi bi-person me-1"></i><?= h($c['instructor_name']) ?>
                </p>
                <div class="mb-1 d-flex justify-content-between progress-label">
                    <span>Progress</span><span><?= $prog['pct'] ?>%</span>
                </div>
                <div class="progress" style="height:8px">
                    <div class="progress-bar bg-success" style="width:<?= $prog['pct'] ?>%"></div>
                </div>
                <div class="text-muted small mt-1"><?= $prog['completed'] ?>/<?= $prog['total'] ?> lessons</div>
            </div>
            <div class="card-footer bg-transparent">
                <a href="/course.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-primary w-100">Continue</a>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
<?php endif; ?>

<?php require __DIR__ . '/templates/footer.php'; ?>
