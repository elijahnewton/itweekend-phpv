<?php
session_start();
require_once __DIR__ . '/../functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

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
$user      = current_user();
$pdo       = get_db();
$lesson_id = (int)($_POST['lesson_id'] ?? 0);

if ($action === 'complete_lesson') {
    if ($lesson_id <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid lesson']);
        exit;
    }
    // Verify lesson exists
    $stmt = $pdo->prepare('SELECT id FROM lessons WHERE id = ?');
    $stmt->execute([$lesson_id]);
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['error' => 'Lesson not found']);
        exit;
    }

    $existing = get_lesson_progress($user['id'], $lesson_id);
    if ($existing) {
        $pdo->prepare(
            "UPDATE user_progress SET completed_at = datetime('now'), completion_percent = 100 WHERE user_id = ? AND lesson_id = ?"
        )->execute([$user['id'], $lesson_id]);
    } else {
        $pdo->prepare(
            "INSERT INTO user_progress (user_id, lesson_id, completed_at, completion_percent) VALUES (?, ?, datetime('now'), 100)"
        )->execute([$user['id'], $lesson_id]);
    }
    echo json_encode(['ok' => true]);

} elseif ($action === 'uncomplete_lesson') {
    if ($lesson_id <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid lesson']);
        exit;
    }
    $pdo->prepare(
        'UPDATE user_progress SET completed_at = NULL, completion_percent = 0 WHERE user_id = ? AND lesson_id = ?'
    )->execute([$user['id'], $lesson_id]);
    echo json_encode(['ok' => true]);

} elseif ($action === 'quiz_score') {
    $score      = (int)($_POST['score'] ?? 0);
    $total      = (int)($_POST['total'] ?? 0);
    $lesson_id2 = (int)($_POST['lesson_id'] ?? 0);
    $pct        = ($total > 0) ? (int)round($score / $total * 100) : 0;

    if ($lesson_id2 <= 0) {
        echo json_encode(['ok' => true]);
        exit;
    }

    $existing = get_lesson_progress($user['id'], $lesson_id2);
    if ($existing) {
        $best = max((int)$existing['quiz_best_score'], $pct);
        $pdo->prepare(
            'UPDATE user_progress SET quiz_last_score = ?, quiz_best_score = ?, quiz_attempts = quiz_attempts + 1 WHERE user_id = ? AND lesson_id = ?'
        )->execute([$pct, $best, $user['id'], $lesson_id2]);
    } else {
        $pdo->prepare(
            'INSERT INTO user_progress (user_id, lesson_id, quiz_last_score, quiz_best_score, quiz_attempts) VALUES (?, ?, ?, ?, 1)'
        )->execute([$user['id'], $lesson_id2, $pct, $pct]);
    }
    echo json_encode(['ok' => true, 'pct' => $pct]);

} else {
    http_response_code(400);
    echo json_encode(['error' => 'Unknown action']);
}
