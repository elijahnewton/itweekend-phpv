<?php
session_start();
require_once __DIR__ . '/functions.php';
require_login();

$pdo  = get_db();
$user = current_user();

// Language progress
$languages = $pdo->query('SELECT * FROM languages ORDER BY order_index')->fetchAll();
$lang_progress = [];
foreach ($languages as $lang) {
    $prog = get_language_progress($user['id'], $lang['id']);
    if ($prog['total'] === 0) continue;
    $lang_progress[] = [
        'language'          => $lang,
        'total_lessons'     => $prog['total'],
        'completed_lessons' => $prog['completed'],
        'percent'           => $prog['pct'],
        'certificate_earned'=> $prog['pct'] === 100,
    ];
}

// Last accessed lesson
$last_stmt = $pdo->prepare(
    'SELECT up.*, l.title as lesson_title, l.slug as lesson_slug,
     t.name as topic_name, t.slug as topic_slug,
     lg.name as lang_name, lg.slug as lang_slug
     FROM user_progress up
     JOIN lessons l ON up.lesson_id = l.id
     JOIN topics t ON l.topic_id = t.id
     JOIN languages lg ON t.language_id = lg.id
     WHERE up.user_id = ?
     ORDER BY up.last_accessed_at DESC
     LIMIT 1'
);
$last_stmt->execute([$user['id']]);
$last = $last_stmt->fetch();

// Quiz stats
$quiz_stmt = $pdo->prepare(
    'SELECT COUNT(*) as attempts, MAX(quiz_best_score) as best, AVG(quiz_last_score) as avg_score
     FROM user_progress WHERE user_id = ? AND quiz_attempts > 0'
);
$quiz_stmt->execute([$user['id']]);
$quiz_stats = $quiz_stmt->fetch();

// Handle profile update
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $name = trim($_POST['name'] ?? '');
        if ($name === '') {
            $error = 'Name cannot be empty.';
        } else {
            $pdo->prepare('UPDATE users SET name = ? WHERE id = ?')->execute([$name, $user['id']]);
            // clear cache
            unset($_SESSION['user_id']); $_SESSION['user_id'] = $user['id'];
            flash('Profile updated.');
            redirect('/profile.php');
        }
    } elseif ($action === 'change_password') {
        $current  = $_POST['current_password'] ?? '';
        $new      = $_POST['new_password'] ?? '';
        $confirm  = $_POST['confirm_password'] ?? '';
        if (!password_verify($current, $user['password'])) {
            $error = 'Current password is incorrect.';
        } elseif (strlen($new) < 8) {
            $error = 'New password must be at least 8 characters.';
        } elseif ($new !== $confirm) {
            $error = 'Passwords do not match.';
        } else {
            $hash = password_hash($new, PASSWORD_DEFAULT);
            $pdo->prepare('UPDATE users SET password = ? WHERE id = ?')->execute([$hash, $user['id']]);
            flash('Password updated successfully.');
            redirect('/profile.php');
        }
    }
}

$page_title = 'My Profile';
require __DIR__ . '/templates/header.php';
?>

<div class="row g-5">
    <div class="col-lg-4">
        <!-- Profile card -->
        <div class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-body p-4 text-center">
                <div class="bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3"
                     style="width:80px;height:80px;font-size:2rem">
                    <?= strtoupper(mb_substr($user['name'] ?: $user['email'], 0, 1)) ?>
                </div>
                <h4 class="fw-bold mb-1"><?= h($user['name'] ?: '(no name)') ?></h4>
                <p class="text-muted small mb-2"><?= h($user['email']) ?></p>
                <span class="badge <?= $user['role'] === ROLE_ADMIN ? 'bg-warning text-dark' : 'bg-primary' ?>">
                    <?= h(ucfirst($user['role'])) ?>
                </span>
                <div class="text-muted small mt-2">
                    Member since <?= h(substr($user['created_at'], 0, 10)) ?>
                </div>
            </div>
        </div>

        <!-- Quick stats -->
        <div class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-body p-4">
                <h6 class="fw-semibold mb-3 text-muted text-uppercase small">Stats</h6>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted small">Tracks started</span>
                    <strong><?= count($lang_progress) ?></strong>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted small">Lessons completed</span>
                    <strong><?= array_sum(array_column($lang_progress, 'completed_lessons')) ?></strong>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted small">Quiz attempts</span>
                    <strong><?= (int)($quiz_stats['attempts'] ?? 0) ?></strong>
                </div>
                <?php if (($quiz_stats['best'] ?? 0) > 0): ?>
                <div class="d-flex justify-content-between">
                    <span class="text-muted small">Best quiz score</span>
                    <strong><?= (int)$quiz_stats['best'] ?>%</strong>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($last): ?>
        <!-- Resume learning -->
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body p-4">
                <h6 class="fw-semibold mb-2">Resume Learning</h6>
                <p class="text-muted small mb-1"><?= h($last['lang_name']) ?> &rarr; <?= h($last['topic_name']) ?></p>
                <p class="fw-medium mb-3"><?= h($last['lesson_title']) ?></p>
                <a href="/lesson.php?lang=<?= urlencode($last['lang_slug']) ?>&topic=<?= urlencode($last['topic_slug']) ?>&slug=<?= urlencode($last['lesson_slug']) ?>"
                   class="btn btn-primary btn-sm w-100">
                    <i class="bi bi-play-fill me-2"></i>Continue
                </a>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div class="col-lg-8">
        <?php if ($error): ?>
        <div class="alert alert-danger"><?= h($error) ?></div>
        <?php endif; ?>

        <!-- Update profile form -->
        <div class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-header bg-transparent border-0 pt-4 pb-0 px-4">
                <h5 class="fw-semibold mb-0">Edit Profile</h5>
            </div>
            <div class="card-body p-4">
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                    <input type="hidden" name="action" value="update_profile">
                    <div class="mb-3">
                        <label class="form-label fw-semibold" for="name">Full Name</label>
                        <input type="text" class="form-control" id="name" name="name"
                               value="<?= h($user['name']) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Email</label>
                        <input type="email" class="form-control" value="<?= h($user['email']) ?>" disabled>
                    </div>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </form>
            </div>
        </div>

        <!-- Change password -->
        <div class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-header bg-transparent border-0 pt-4 pb-0 px-4">
                <h5 class="fw-semibold mb-0">Change Password</h5>
            </div>
            <div class="card-body p-4">
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                    <input type="hidden" name="action" value="change_password">
                    <div class="mb-3">
                        <label class="form-label fw-semibold" for="current_password">Current Password</label>
                        <input type="password" class="form-control" id="current_password" name="current_password" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold" for="new_password">New Password</label>
                        <input type="password" class="form-control" id="new_password" name="new_password" minlength="8" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold" for="confirm_password">Confirm Password</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                    </div>
                    <button type="submit" class="btn btn-warning">Update Password</button>
                </form>
            </div>
        </div>

        <!-- Progress overview -->
        <?php if (!empty($lang_progress)): ?>
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-header bg-transparent border-0 pt-4 pb-0 px-4">
                <h5 class="fw-semibold mb-0">Progress Overview</h5>
            </div>
            <div class="card-body p-4">
                <?php foreach ($lang_progress as $lp): ?>
                <div class="mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="fw-semibold">
                            <?= h($lp['language']['icon'] ?? '') ?> <?= h($lp['language']['name']) ?>
                        </span>
                        <span class="text-muted small">
                            <?= $lp['completed_lessons'] ?>/<?= $lp['total_lessons'] ?> lessons
                            <?php if ($lp['certificate_earned']): ?>
                            <span class="badge bg-success ms-1"><i class="bi bi-trophy-fill me-1"></i>Complete</span>
                            <?php endif; ?>
                        </span>
                    </div>
                    <div class="progress" style="height:8px">
                        <div class="progress-bar <?= $lp['certificate_earned'] ? 'bg-success' : 'bg-primary' ?>"
                             style="width:<?= $lp['percent'] ?>%"></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/templates/footer.php'; ?>
