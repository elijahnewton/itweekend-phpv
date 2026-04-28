<?php
session_start();
require_once __DIR__ . '/../functions.php';
require_admin();

$pdo    = get_db();
$action = $_GET['action'] ?? 'list';
$id     = (int)($_GET['id'] ?? 0);
$error  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $pa = $_POST['action'] ?? '';

    if ($pa === 'delete_quiz' && (int)$_POST['id'] > 0) {
        $pdo->prepare('DELETE FROM quizzes WHERE id = ?')->execute([(int)$_POST['id']]);
        flash('Quiz deleted.');
        redirect('/admin/quizzes.php');
    }

    if ($pa === 'delete_question' && (int)$_POST['id'] > 0) {
        $pdo->prepare('DELETE FROM quiz_questions WHERE id = ?')->execute([(int)$_POST['id']]);
        flash('Question deleted.');
        redirect('/admin/quizzes.php?action=edit&id=' . (int)$_POST['quiz_id']);
    }

    if ($pa === 'save_quiz') {
        $lesson_id = (int)($_POST['lesson_id'] ?? 0) ?: null;
        $lang_id   = (int)($_POST['language_id'] ?? 0) ?: null;
        $title     = trim($_POST['title'] ?? '');
        $edit_id   = (int)($_POST['edit_id'] ?? 0);

        if ($title === '') {
            $error = 'Title is required.';
        } else {
            if ($edit_id) {
                $pdo->prepare('UPDATE quizzes SET lesson_id=?,language_id=?,title=? WHERE id=?')
                    ->execute([$lesson_id, $lang_id, $title, $edit_id]);
                flash('Quiz updated.');
                redirect('/admin/quizzes.php?action=edit&id=' . $edit_id);
            } else {
                $pdo->prepare('INSERT INTO quizzes (lesson_id,language_id,title) VALUES (?,?,?)')
                    ->execute([$lesson_id, $lang_id, $title]);
                flash('Quiz created.');
                redirect('/admin/quizzes.php?action=edit&id=' . $pdo->lastInsertId());
            }
        }
    }

    if ($pa === 'add_question') {
        $quiz_id   = (int)$_POST['quiz_id'];
        $prompt    = trim($_POST['prompt'] ?? '');
        $expl      = trim($_POST['explanation'] ?? '');
        $choices   = $_POST['choices'] ?? [];
        $correct   = (int)($_POST['correct_index'] ?? 0);

        if ($prompt === '' || count($choices) < 2) {
            $error = 'Prompt and at least 2 choices are required.';
        } else {
            $pdo->prepare('INSERT INTO quiz_questions (quiz_id, prompt, explanation, order_index) VALUES (?, ?, ?, ?)')
                ->execute([$quiz_id, $prompt, $expl,
                    (int)$pdo->query("SELECT COUNT(*) FROM quiz_questions WHERE quiz_id=$quiz_id")->fetchColumn()
                ]);
            $qqid = (int)$pdo->lastInsertId();
            foreach ($choices as $ci => $ct) {
                if (trim($ct) === '') continue;
                $pdo->prepare('INSERT INTO quiz_choices (question_id, choice_text, is_correct) VALUES (?, ?, ?)')
                    ->execute([$qqid, trim($ct), $ci === $correct ? 1 : 0]);
            }
            flash('Question added.');
            redirect('/admin/quizzes.php?action=edit&id=' . $quiz_id);
        }
    }
}

// Editing a specific quiz
$editing = null;
$quiz_questions = [];
if ($action === 'edit' && $id) {
    $s = $pdo->prepare('SELECT * FROM quizzes WHERE id = ?');
    $s->execute([$id]);
    $editing = $s->fetch();
    if ($editing) {
        $qs = $pdo->prepare(
            'SELECT qq.*, GROUP_CONCAT(qc.choice_text, "||") as choices_text,
             GROUP_CONCAT(qc.is_correct, "||") as choices_correct
             FROM quiz_questions qq
             LEFT JOIN quiz_choices qc ON qc.question_id = qq.id
             WHERE qq.quiz_id = ?
             GROUP BY qq.id ORDER BY qq.order_index'
        );
        $qs->execute([$id]);
        $quiz_questions = $qs->fetchAll();
    }
}

// All quizzes
$quizzes = $pdo->query(
    'SELECT q.*, l.title as lesson_title, lg.name as lang_name,
     COUNT(qq.id) as question_count
     FROM quizzes q
     LEFT JOIN lessons l ON q.lesson_id = l.id
     LEFT JOIN languages lg ON q.language_id = lg.id
     LEFT JOIN quiz_questions qq ON qq.quiz_id = q.id
     GROUP BY q.id ORDER BY q.id DESC'
)->fetchAll();

$lessons_list = $pdo->query(
    'SELECT l.id, l.title, t.name as topic_name, lg.name as lang_name
     FROM lessons l JOIN topics t ON l.topic_id = t.id JOIN languages lg ON t.language_id = lg.id
     ORDER BY lg.order_index, t.order_index, l.order_index'
)->fetchAll();

$languages_list = $pdo->query('SELECT * FROM languages ORDER BY order_index')->fetchAll();

$page_title = 'Manage Quizzes';
require __DIR__ . '/../templates/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="fw-bold mb-0"><i class="bi bi-question-circle text-primary me-2"></i>Quizzes</h1>
    <div class="d-flex gap-2">
        <a href="?action=create" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>New Quiz</a>
        <a href="/admin/index.php" class="btn btn-outline-secondary btn-sm">← Dashboard</a>
    </div>
</div>

<?php if ($action === 'create' || ($action === 'edit' && $editing)): ?>
<!-- Quiz form -->
<div class="card border-0 shadow-sm rounded-4 mb-4">
    <div class="card-body p-4">
        <h4 class="fw-semibold mb-3"><?= $editing ? 'Edit Quiz: ' . h($editing['title']) : 'New Quiz' ?></h4>
        <?php if ($error): ?><div class="alert alert-danger"><?= h($error) ?></div><?php endif; ?>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="action" value="save_quiz">
            <input type="hidden" name="edit_id" value="<?= $editing ? $editing['id'] : '' ?>">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Title *</label>
                    <input type="text" class="form-control" name="title"
                           value="<?= h($editing['title'] ?? '') ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Attach to Lesson</label>
                    <select class="form-select" name="lesson_id">
                        <option value="">— None —</option>
                        <?php foreach ($lessons_list as $l): ?>
                        <option value="<?= $l['id'] ?>"
                            <?= (int)($editing['lesson_id'] ?? 0) === (int)$l['id'] ? 'selected' : '' ?>>
                            [<?= h($l['lang_name']) ?>] <?= h($l['topic_name']) ?> → <?= h($l['title']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Attach to Language</label>
                    <select class="form-select" name="language_id">
                        <option value="">— None —</option>
                        <?php foreach ($languages_list as $l): ?>
                        <option value="<?= $l['id'] ?>"
                            <?= (int)($editing['language_id'] ?? 0) === (int)$l['id'] ? 'selected' : '' ?>>
                            <?= h($l['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="mt-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary">Save Quiz</button>
                <a href="/admin/quizzes.php" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php if ($editing): ?>
<!-- Existing questions -->
<?php if (!empty($quiz_questions)): ?>
<div class="card border-0 shadow-sm rounded-4 mb-4">
    <div class="card-header bg-transparent border-0 pt-4 px-4">
        <h5 class="fw-semibold">Questions (<?= count($quiz_questions) ?>)</h5>
    </div>
    <div class="list-group list-group-flush">
        <?php foreach ($quiz_questions as $qi => $q): ?>
        <div class="list-group-item px-4 py-3">
            <div class="d-flex justify-content-between">
                <strong><?= $qi + 1 ?>. <?= h($q['prompt']) ?></strong>
                <form method="post" class="d-inline">
                    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                    <input type="hidden" name="action" value="delete_question">
                    <input type="hidden" name="id" value="<?= $q['id'] ?>">
                    <input type="hidden" name="quiz_id" value="<?= $editing['id'] ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger"
                            data-confirm="Delete this question?">
                        <i class="bi bi-trash"></i>
                    </button>
                </form>
            </div>
            <?php
            $choices = explode('||', $q['choices_text'] ?? '');
            $corrects = explode('||', $q['choices_correct'] ?? '');
            ?>
            <ul class="list-unstyled mt-2 mb-0">
                <?php foreach ($choices as $ci => $ct): ?>
                <li class="small <?= ($corrects[$ci] ?? '0') === '1' ? 'text-success fw-semibold' : 'text-muted' ?>">
                    <?= ($corrects[$ci] ?? '0') === '1' ? '✓' : '○' ?> <?= h($ct) ?>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php if ($q['explanation']): ?>
            <div class="text-muted small mt-1"><em>Explanation: <?= h($q['explanation']) ?></em></div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- Add question form -->
<div class="card border-0 shadow-sm rounded-4 mb-5">
    <div class="card-header bg-transparent border-0 pt-4 px-4">
        <h5 class="fw-semibold">Add Question</h5>
    </div>
    <div class="card-body p-4">
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="action" value="add_question">
            <input type="hidden" name="quiz_id" value="<?= $editing['id'] ?>">
            <div class="mb-3">
                <label class="form-label fw-semibold">Question *</label>
                <input type="text" class="form-control" name="prompt" required>
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold">Explanation (shown after answering)</label>
                <input type="text" class="form-control" name="explanation">
            </div>
            <div class="mb-2 fw-semibold">Answer Choices *</div>
            <div class="row g-2 mb-3">
                <?php for ($i = 0; $i < 4; $i++): ?>
                <div class="col-md-3">
                    <div class="input-group">
                        <div class="input-group-text">
                            <input class="form-check-input mt-0" type="radio" name="correct_index"
                                   value="<?= $i ?>" <?= $i === 0 ? 'checked' : '' ?>>
                        </div>
                        <input type="text" class="form-control" name="choices[]"
                               placeholder="Choice <?= $i + 1 ?>">
                    </div>
                </div>
                <?php endfor; ?>
            </div>
            <div class="text-muted small mb-3">Select the radio button next to the correct answer.</div>
            <button type="submit" class="btn btn-success">Add Question</button>
        </form>
    </div>
</div>
<?php endif; ?>
<?php endif; ?>

<!-- List quizzes -->
<div class="card border-0 shadow-sm rounded-4">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Title</th><th>Attached To</th><th>Questions</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($quizzes as $qz): ?>
            <tr>
                <td><?= h($qz['title']) ?></td>
                <td class="text-muted small">
                    <?= $qz['lesson_title'] ? 'Lesson: ' . h($qz['lesson_title']) : '' ?>
                    <?= $qz['lang_name'] ? 'Language: ' . h($qz['lang_name']) : '' ?>
                    <?= !$qz['lesson_title'] && !$qz['lang_name'] ? '—' : '' ?>
                </td>
                <td><?= (int)$qz['question_count'] ?></td>
                <td class="d-flex gap-1">
                    <a href="?action=edit&id=<?= $qz['id'] ?>" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-pencil"></i>
                    </a>
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                        <input type="hidden" name="action" value="delete_quiz">
                        <input type="hidden" name="id" value="<?= $qz['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger"
                                data-confirm="Delete quiz '<?= h($qz['title']) ?>'?">
                            <i class="bi bi-trash"></i>
                        </button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($quizzes)): ?>
            <tr><td colspan="4" class="text-center text-muted py-4">No quizzes yet.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/../templates/footer.php'; ?>
