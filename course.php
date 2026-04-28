<?php
session_start();
require_once __DIR__ . '/functions.php';
require_login();

$pdo  = get_db();
$user = current_user();
$id   = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare(
    'SELECT c.*, u.name as instructor_name FROM courses c
     JOIN users u ON c.instructor_id = u.id WHERE c.id = ?'
);
$stmt->execute([$id]);
$course = $stmt->fetch();

if (!$course) {
    flash('Course not found.', 'danger');
    redirect('/courses.php');
}

// Only show unpublished to instructor or admin
if (!$course['is_published'] &&
    $user['role'] !== ROLE_ADMIN &&
    (int)$course['instructor_id'] !== (int)$user['id']) {
    flash('Course not available.', 'danger');
    redirect('/courses.php');
}

// Check enrollment
$enroll_stmt = $pdo->prepare('SELECT id FROM enrollments WHERE user_id = ? AND course_id = ?');
$enroll_stmt->execute([$user['id'], $id]);
$is_enrolled = (bool)$enroll_stmt->fetch();

// Fetch lessons
$lessons_stmt = $pdo->prepare('SELECT * FROM lessons WHERE course_id = ? ORDER BY sort_order, id');
$lessons_stmt->execute([$id]);
$lessons = $lessons_stmt->fetchAll();

// Fetch completed lessons for this user
$prog_stmt = $pdo->prepare(
    'SELECT lesson_id FROM lesson_progress lp
     JOIN lessons l ON lp.lesson_id = l.id
     WHERE lp.user_id = ? AND l.course_id = ?'
);
$prog_stmt->execute([$user['id'], $id]);
$completed_ids = array_column($prog_stmt->fetchAll(), 'lesson_id');

$progress = get_course_progress($user['id'], $id);

// Stats
$s = $pdo->prepare('SELECT COUNT(*) FROM enrollments WHERE course_id = ?');
$s->execute([$id]);
$student_count = (int)$s->fetchColumn();

$page_title = $course['title'];
require __DIR__ . '/templates/header.php';
?>

<nav aria-label="breadcrumb" class="mb-4">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="/courses.php">Courses</a></li>
        <li class="breadcrumb-item active"><?= h($course['title']) ?></li>
    </ol>
</nav>

<div class="row g-5">
    <div class="col-lg-8">
        <h1 class="fw-bold mb-2"><?= h($course['title']) ?></h1>
        <div class="d-flex flex-wrap gap-3 text-muted small mb-4">
            <span><i class="bi bi-person me-1"></i><?= h($course['instructor_name']) ?></span>
            <span><i class="bi bi-people me-1"></i><?= $student_count ?> students</span>
            <span><i class="bi bi-book me-1"></i><?= count($lessons) ?> lessons</span>
            <?= $course['is_published']
                ? '<span class="badge bg-success align-self-center">Published</span>'
                : '<span class="badge bg-secondary align-self-center">Draft</span>' ?>
        </div>

        <p class="lead mb-4"><?= nl2br(h($course['description'])) ?></p>

        <?php if ($is_enrolled): ?>
        <div class="mb-4">
            <div class="d-flex justify-content-between progress-label mb-1">
                <span>Your Progress</span><span><?= $progress['pct'] ?>%</span>
            </div>
            <div class="progress" style="height:10px">
                <div class="progress-bar bg-success" style="width:<?= $progress['pct'] ?>%"></div>
            </div>
            <div class="text-muted small mt-1">
                <?= $progress['completed'] ?>/<?= $progress['total'] ?> lessons completed
            </div>
        </div>
        <?php endif; ?>

        <h3 class="fw-semibold mb-3">Course Lessons</h3>
        <?php if (empty($lessons)): ?>
        <p class="text-muted">No lessons published yet.</p>
        <?php else: ?>
        <div class="list-group lesson-sidebar">
            <?php foreach ($lessons as $i => $lesson):
                $done = in_array((int)$lesson['id'], array_map('intval', $completed_ids), true);
                $can_access = $is_enrolled || $user['role'] === ROLE_ADMIN
                    || (int)$course['instructor_id'] === (int)$user['id'];
            ?>
            <a href="<?= $can_access ? '/lesson.php?id=' . $lesson['id'] : '#' ?>"
               class="list-group-item list-group-item-action d-flex justify-content-between align-items-center
                       <?= $done ? 'completed' : '' ?>
                       <?= !$can_access ? 'text-muted' : '' ?>">
                <span>
                    <span class="me-2 text-muted small"><?= $i + 1 ?>.</span>
                    <?= h($lesson['title']) ?>
                </span>
                <?php if ($done): ?>
                <i class="bi bi-check-circle-fill text-success"></i>
                <?php elseif (!$can_access): ?>
                <i class="bi bi-lock-fill text-muted"></i>
                <?php else: ?>
                <i class="bi bi-play-circle text-primary"></i>
                <?php endif; ?>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <div class="col-lg-4">
        <div class="card shadow-sm border-0 rounded-4 sticky-top" style="top:1rem">
            <?php if ($course['thumbnail']): ?>
            <img src="<?= h($course['thumbnail']) ?>" class="card-img-top rounded-top-4" alt="">
            <?php else: ?>
            <div class="course-thumb-placeholder rounded-top-4">
                <i class="bi bi-collection"></i>
            </div>
            <?php endif; ?>
            <div class="card-body p-4">
                <?php if ($is_enrolled): ?>
                    <?php
                    // Find first incomplete lesson
                    $next_lesson = null;
                    foreach ($lessons as $lesson) {
                        if (!in_array((int)$lesson['id'], array_map('intval', $completed_ids), true)) {
                            $next_lesson = $lesson;
                            break;
                        }
                    }
                    ?>
                    <?php if ($next_lesson): ?>
                    <a href="/lesson.php?id=<?= $next_lesson['id'] ?>" class="btn btn-success w-100 mb-2 py-2">
                        <i class="bi bi-play-fill me-2"></i>Continue Learning
                    </a>
                    <?php else: ?>
                    <div class="alert alert-success text-center mb-2">
                        <i class="bi bi-trophy-fill me-2"></i>Course Completed!
                    </div>
                    <?php endif; ?>
                <?php elseif ($user['role'] === ROLE_STUDENT): ?>
                    <a href="/enroll.php?course_id=<?= $course['id'] ?>" class="btn btn-primary w-100 mb-2 py-2">
                        <i class="bi bi-plus-circle me-2"></i>Enroll Now — Free
                    </a>
                <?php elseif ((int)$course['instructor_id'] === (int)$user['id'] || $user['role'] === ROLE_ADMIN): ?>
                    <a href="/instructor/edit_course.php?id=<?= $course['id'] ?>"
                       class="btn btn-outline-secondary w-100 mb-2">
                        <i class="bi bi-pencil me-2"></i>Edit Course
                    </a>
                    <a href="/instructor/create_lesson.php?course_id=<?= $course['id'] ?>"
                       class="btn btn-outline-primary w-100">
                        <i class="bi bi-plus me-2"></i>Add Lesson
                    </a>
                <?php endif; ?>

                <hr>
                <ul class="list-unstyled mb-0 small">
                    <li class="mb-2">
                        <i class="bi bi-people-fill text-primary me-2"></i>
                        <strong><?= $student_count ?></strong> students enrolled
                    </li>
                    <li class="mb-2">
                        <i class="bi bi-book-fill text-primary me-2"></i>
                        <strong><?= count($lessons) ?></strong> lessons
                    </li>
                    <li>
                        <i class="bi bi-calendar-fill text-primary me-2"></i>
                        Created <?= h(substr($course['created_at'], 0, 10)) ?>
                    </li>
                </ul>
            </div>
        </div>

        <?php if ((int)$course['instructor_id'] === (int)$user['id'] || $user['role'] === ROLE_ADMIN): ?>
        <div class="card mt-3 border-0 shadow-sm">
            <div class="card-body">
                <h6 class="fw-semibold mb-3">Instructor Tools</h6>
                <div class="d-grid gap-2">
                    <a href="/instructor/create_lesson.php?course_id=<?= $course['id'] ?>"
                       class="btn btn-primary btn-sm">
                        <i class="bi bi-plus-lg me-1"></i>Add Lesson
                    </a>
                    <a href="/instructor/edit_course.php?id=<?= $course['id'] ?>"
                       class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-pencil me-1"></i>Edit Course Details
                    </a>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/templates/footer.php'; ?>
