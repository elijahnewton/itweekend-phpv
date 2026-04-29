<?php
/**
 * api/quiz.php — Quiz API endpoint
 *
 * GET  ?lesson_id=<id>   Fetch quiz questions for a lesson (JSON)
 * POST action=submit     Submit quiz answers and record score
 */
session_start();
require_once __DIR__ . '/../functions.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

// ── GET: fetch quiz for a lesson ─────────────────────────────────────────────
if ($method === 'GET') {
    $lesson_id = (int)($_GET['lesson_id'] ?? 0);
    if ($lesson_id <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'lesson_id required']);
        exit;
    }

    $pdo = get_db();

    // Verify lesson exists
    $s = $pdo->prepare('SELECT id FROM lessons WHERE id = ?');
    $s->execute([$lesson_id]);
    if (!$s->fetch()) {
        http_response_code(404);
        echo json_encode(['error' => 'Lesson not found']);
        exit;
    }

    $quiz = $pdo->prepare('SELECT * FROM quizzes WHERE lesson_id = ? LIMIT 1');
    $quiz->execute([$lesson_id]);
    $quiz = $quiz->fetch();

    if (!$quiz) {
        echo json_encode(['quiz' => null]);
        exit;
    }

    $qs = $pdo->prepare(
        'SELECT qq.id, qq.prompt, qq.explanation, qq.order_index,
                GROUP_CONCAT(qc.choice_text ORDER BY qc.id, "||") as choices_text,
                GROUP_CONCAT(qc.is_correct  ORDER BY qc.id, "||") as choices_correct
         FROM quiz_questions qq
         JOIN quiz_choices qc ON qc.question_id = qq.id
         WHERE qq.quiz_id = ?
         GROUP BY qq.id
         ORDER BY qq.order_index, qq.id'
    );
    $qs->execute([$quiz['id']]);
    $raw = $qs->fetchAll();

    $questions = array_map(function ($q) {
        $choices  = explode('||', $q['choices_text'] ?? '');
        $corrects = explode('||', $q['choices_correct'] ?? '');
        $found = array_search('1', $corrects, true);
        $correct_idx = $found !== false ? (int)$found : 0;
        return [
            'id'           => (int)$q['id'],
            'prompt'       => $q['prompt'],
            'explanation'  => $q['explanation'],
            'options'      => $choices,
            'correctIndex' => $correct_idx,
        ];
    }, $raw);

    echo json_encode([
        'quiz' => [
            'id'        => (int)$quiz['id'],
            'title'     => $quiz['title'],
            'questions' => $questions,
        ],
    ]);
    exit;
}

// ── POST: submit quiz answers ────────────────────────────────────────────────
if ($method === 'POST') {
    if (!is_logged_in()) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }

    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals(csrf_token(), $token)) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid CSRF token']);
        exit;
    }

    $action    = $_POST['action'] ?? '';
    $lesson_id = (int)($_POST['lesson_id'] ?? 0);
    $pdo       = get_db();
    $user      = current_user();

    if ($action === 'submit') {
        if ($lesson_id <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'lesson_id required']);
            exit;
        }

        $score = (int)($_POST['score'] ?? 0);
        $total = (int)($_POST['total'] ?? 0);

        if ($total <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'total must be > 0']);
            exit;
        }

        $pct = (int)round((float)$score / (float)$total * 100);

        $existing = get_lesson_progress($user['id'], $lesson_id);
        if ($existing) {
            $best = max((int)$existing['quiz_best_score'], $pct);
            $pdo->prepare(
                'UPDATE user_progress
                 SET quiz_last_score = ?, quiz_best_score = ?, quiz_attempts = quiz_attempts + 1
                 WHERE user_id = ? AND lesson_id = ?'
            )->execute([$pct, $best, $user['id'], $lesson_id]);
        } else {
            $pdo->prepare(
                'INSERT INTO user_progress (user_id, lesson_id, quiz_last_score, quiz_best_score, quiz_attempts)
                 VALUES (?, ?, ?, ?, 1)'
            )->execute([$user['id'], $lesson_id, $pct, $pct]);
        }

        echo json_encode(['ok' => true, 'pct' => $pct, 'passed' => $pct >= 70]);
        exit;
    }

    http_response_code(400);
    echo json_encode(['error' => 'Unknown action']);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
