<?php
session_start();
require_once __DIR__ . '/functions.php';

$pdo  = get_db();
$user = current_user();

$lang_slug  = $_GET['lang']  ?? '';
$topic_slug = $_GET['topic'] ?? '';
$lesson_slug = $_GET['slug'] ?? '';

$lang = $pdo->prepare('SELECT * FROM languages WHERE slug = ?');
$lang->execute([$lang_slug]);
$lang = $lang->fetch();
if (!$lang) { flash('Not found.', 'danger'); redirect('/learn.php'); }

$topic = $pdo->prepare('SELECT * FROM topics WHERE language_id = ? AND slug = ?');
$topic->execute([$lang['id'], $topic_slug]);
$topic = $topic->fetch();
if (!$topic) { flash('Topic not found.', 'danger'); redirect('/language.php?slug=' . urlencode($lang_slug)); }

$lesson = $pdo->prepare('SELECT * FROM lessons WHERE topic_id = ? AND slug = ?');
$lesson->execute([$topic['id'], $lesson_slug]);
$lesson = $lesson->fetch();
if (!$lesson) { flash('Lesson not found.', 'danger'); redirect('/topic.php?lang=' . urlencode($lang_slug) . '&slug=' . urlencode($topic_slug)); }

// Track access
if ($user) {
    touch_lesson_progress($user['id'], $lesson['id']);
}

// Sibling lessons for prev/next
$all_lessons = $pdo->prepare('SELECT * FROM lessons WHERE topic_id = ? ORDER BY order_index, id');
$all_lessons->execute([$topic['id']]);
$all_lessons = $all_lessons->fetchAll();
$lesson_idx  = array_search($lesson['id'], array_column($all_lessons, 'id'));
$prev_lesson = $lesson_idx > 0 ? $all_lessons[$lesson_idx - 1] : null;
$next_lesson = $lesson_idx < count($all_lessons) - 1 ? $all_lessons[$lesson_idx + 1] : null;

// Progress for sidebar
$completed_ids = [];
if ($user) {
    $s = $pdo->prepare(
        'SELECT lesson_id FROM user_progress
         WHERE user_id = ? AND lesson_id IN (SELECT id FROM lessons WHERE topic_id = ?)
         AND completed_at IS NOT NULL'
    );
    $s->execute([$user['id'], $topic['id']]);
    $completed_ids = array_column($s->fetchAll(), 'lesson_id');
}

$my_prog = $user ? get_lesson_progress($user['id'], $lesson['id']) : null;
$is_completed = $my_prog && $my_prog['completed_at'] !== null;

// Load quiz for this lesson
$quiz = $pdo->prepare('SELECT * FROM quizzes WHERE lesson_id = ? LIMIT 1');
$quiz->execute([$lesson['id']]);
$quiz = $quiz->fetch();
$quiz_data = null;
if ($quiz) {
    $questions = $pdo->prepare(
        'SELECT qq.*, GROUP_CONCAT(qc.choice_text, "||") as choices_text,
         GROUP_CONCAT(qc.is_correct, "||") as choices_correct,
         GROUP_CONCAT(qc.id, "||") as choices_ids
         FROM quiz_questions qq
         JOIN quiz_choices qc ON qc.question_id = qq.id
         WHERE qq.quiz_id = ?
         GROUP BY qq.id
         ORDER BY qq.order_index, qq.id'
    );
    $questions->execute([$quiz['id']]);
    $raw_questions = $questions->fetchAll();

    $quiz_data = [
        'id'        => $quiz['id'],
        'title'     => $quiz['title'],
        'questions' => array_map(function($q) {
            $choices   = explode('||', $q['choices_text']);
            $corrects  = explode('||', $q['choices_correct']);
            $correct_idx = array_search('1', $corrects);
            return [
                'id'           => $q['id'],
                'prompt'       => $q['prompt'],
                'explanation'  => $q['explanation'],
                'options'      => $choices,
                'correctIndex' => (int)$correct_idx,
            ];
        }, $raw_questions),
    ];
}

$best_score = $my_prog ? (int)$my_prog['quiz_best_score'] : 0;

$page_title = h($lesson['title']) . ' — ' . h($lang['name']);
require __DIR__ . '/templates/header.php';
?>

<nav aria-label="breadcrumb" class="mb-4">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="/learn.php">Learn</a></li>
        <li class="breadcrumb-item"><a href="/language.php?slug=<?= urlencode($lang['slug']) ?>"><?= h($lang['name']) ?></a></li>
        <li class="breadcrumb-item"><a href="/topic.php?lang=<?= urlencode($lang['slug']) ?>&slug=<?= urlencode($topic['slug']) ?>"><?= h($topic['name']) ?></a></li>
        <li class="breadcrumb-item active"><?= h($lesson['title']) ?></li>
    </ol>
</nav>

<div class="row g-4">
    <!-- Sidebar: lesson list -->
    <div class="col-lg-3 order-lg-2">
        <div class="card border-0 shadow-sm rounded-3 sticky-top lesson-sidebar" style="top:1rem; max-height:80vh; overflow-y:auto;">
            <div class="card-header bg-primary text-white py-2 px-3">
                <div class="small fw-semibold"><?= h($topic['name']) ?></div>
            </div>
            <div class="list-group list-group-flush">
                <?php foreach ($all_lessons as $i => $sl):
                    $sl_done = in_array((int)$sl['id'], array_map('intval', $completed_ids), true);
                    $is_current = $sl['id'] == $lesson['id'];
                ?>
                <a href="/lesson.php?lang=<?= urlencode($lang['slug']) ?>&topic=<?= urlencode($topic['slug']) ?>&slug=<?= urlencode($sl['slug']) ?>"
                   class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-2 px-3
                          <?= $is_current ? 'active' : '' ?>
                          <?= $sl_done && !$is_current ? 'completed' : '' ?>">
                    <span class="small">
                        <span class="me-1 opacity-50"><?= $i + 1 ?>.</span><?= h($sl['title']) ?>
                    </span>
                    <?php if ($sl_done): ?>
                    <i class="bi bi-check-circle-fill text-success ms-1 flex-shrink-0 <?= $is_current ? 'text-white' : '' ?>"></i>
                    <?php endif; ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Main content -->
    <div class="col-lg-9 order-lg-1">
        <div class="d-flex justify-content-between align-items-start mb-3">
            <h1 class="fw-bold mb-0"><?= h($lesson['title']) ?></h1>
            <?php if ($lesson['estimated_minutes']): ?>
            <span class="badge bg-light text-dark border ms-3 flex-shrink-0">
                <i class="bi bi-clock me-1"></i><?= (int)$lesson['estimated_minutes'] ?> min
            </span>
            <?php endif; ?>
        </div>

        <?php if ($is_completed): ?>
        <div class="alert alert-success d-flex align-items-center gap-2 mb-4">
            <i class="bi bi-check-circle-fill fs-5"></i>
            You&rsquo;ve completed this lesson!
            <?php if ($best_score > 0): ?>
            &mdash; Quiz best score: <strong><?= $best_score ?>%</strong>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Lesson content -->
        <div class="lesson-content mb-5">
            <?php if ($lesson['content_html']): ?>
            <div class="mb-4 lh-lg"><?= $lesson['content_html'] ?></div>
            <?php endif; ?>

            <?php if ($lesson['code_example']): ?>
            <h4 class="fw-semibold mt-4 mb-2">
                <i class="bi bi-code-slash text-primary me-2"></i>Code Example
            </h4>
            <pre><code class="language-<?= h($lesson['code_language'] ?: 'plaintext') ?>"><?= h($lesson['code_example']) ?></code></pre>
            <?php endif; ?>
        </div>

        <!-- Mark complete / Quiz -->
        <?php if ($user): ?>
        <div class="card border-0 shadow-sm rounded-4 mb-5 p-4" id="mark-complete-card">
            <?php if (!$is_completed): ?>
            <form method="post" action="/api/progress.php" id="complete-form">
                <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                <input type="hidden" name="action" value="complete_lesson">
                <input type="hidden" name="lesson_id" value="<?= (int)$lesson['id'] ?>">
                <button type="submit" class="btn btn-success px-4">
                    <i class="bi bi-check-lg me-2"></i>Mark as Complete
                </button>
                <span class="text-muted small ms-3">Marks this lesson done and records your progress.</span>
            </form>
            <?php else: ?>
            <form method="post" action="/api/progress.php" id="uncomplete-form">
                <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                <input type="hidden" name="action" value="uncomplete_lesson">
                <input type="hidden" name="lesson_id" value="<?= (int)$lesson['id'] ?>">
                <button type="submit" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-counterclockwise me-1"></i>Mark as Incomplete
                </button>
            </form>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <div class="alert alert-info mb-5">
            <a href="/login.php">Sign in</a> to track your progress and take quizzes.
        </div>
        <?php endif; ?>

        <!-- Quiz -->
        <?php if ($quiz_data && !empty($quiz_data['questions'])): ?>
        <div class="card border-0 shadow-sm rounded-4 mb-5" id="quiz-section">
            <div class="card-header bg-light border-0 py-3 px-4 rounded-top-4">
                <h4 class="mb-0 fw-semibold"><i class="bi bi-question-circle text-primary me-2"></i><?= h($quiz_data['title']) ?></h4>
                <?php if ($best_score > 0): ?>
                <span class="text-muted small">Best score: <strong><?= $best_score ?>%</strong></span>
                <?php endif; ?>
            </div>
            <div class="card-body p-4">
                <div id="quiz-container" data-quiz='<?= json_encode($quiz_data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP) ?>'
                     data-lesson-id="<?= (int)$lesson['id'] ?>"
                     data-csrf="<?= h(csrf_token()) ?>">
                    <!-- Rendered by app.js -->
                    <p class="text-muted"><em>Loading quiz…</em></p>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Prev / Next navigation -->
        <div class="d-flex justify-content-between gap-3 mt-4">
            <?php if ($prev_lesson): ?>
            <a href="/lesson.php?lang=<?= urlencode($lang['slug']) ?>&topic=<?= urlencode($topic['slug']) ?>&slug=<?= urlencode($prev_lesson['slug']) ?>"
               class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-2"></i><?= h($prev_lesson['title']) ?>
            </a>
            <?php else: ?>
            <a href="/topic.php?lang=<?= urlencode($lang['slug']) ?>&slug=<?= urlencode($topic['slug']) ?>" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-2"></i>Back to Topic
            </a>
            <?php endif; ?>

            <?php if ($next_lesson): ?>
            <a href="/lesson.php?lang=<?= urlencode($lang['slug']) ?>&topic=<?= urlencode($topic['slug']) ?>&slug=<?= urlencode($next_lesson['slug']) ?>"
               class="btn btn-primary ms-auto">
                <?= h($next_lesson['title']) ?><i class="bi bi-arrow-right ms-2"></i>
            </a>
            <?php else: ?>
            <a href="/topic.php?lang=<?= urlencode($lang['slug']) ?>&slug=<?= urlencode($topic['slug']) ?>" class="btn btn-success ms-auto">
                <i class="bi bi-check-circle me-2"></i>Finish Topic
            </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require __DIR__ . '/templates/footer.php'; ?>
