<?php
require_once __DIR__ . '/db.php';

function flash(string $msg, string $type = 'success'): void {
    $_SESSION['flash'] = ['msg' => $msg, 'type' => $type];
}

function get_flash(): ?array {
    if (isset($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $f;
    }
    return null;
}

function current_user(): ?array {
    if (!isset($_SESSION['user_id'])) return null;
    static $cache = [];
    $uid = (int)$_SESSION['user_id'];
    if (!isset($cache[$uid])) {
        $stmt = get_db()->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$uid]);
        $cache[$uid] = $stmt->fetch() ?: null;
    }
    return $cache[$uid];
}

function is_logged_in(): bool {
    return isset($_SESSION['user_id']);
}

function require_login(): void {
    if (!is_logged_in()) {
        flash('Please log in to continue.', 'info');
        header('Location: /login.php?next=' . urlencode($_SERVER['REQUEST_URI']));
        exit;
    }
}

function require_admin(): void {
    require_login();
    $user = current_user();
    if (!$user || $user['role'] !== ROLE_ADMIN) {
        flash('Admin access required.', 'danger');
        header('Location: /learn.php');
        exit;
    }
}

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $url): void {
    header('Location: ' . $url);
    exit;
}

function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf(): void {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals(csrf_token(), $token)) {
        flash('Invalid request token.', 'danger');
        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/learn.php'));
        exit;
    }
}

/**
 * Get a user's progress for a specific lesson.
 */
function get_lesson_progress(int $user_id, int $lesson_id): ?array {
    $stmt = get_db()->prepare('SELECT * FROM user_progress WHERE user_id = ? AND lesson_id = ?');
    $stmt->execute([$user_id, $lesson_id]);
    return $stmt->fetch() ?: null;
}

/**
 * Get completion stats for all lessons in a language.
 */
function get_language_progress(int $user_id, int $language_id): array {
    $pdo = get_db();
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM lessons l JOIN topics t ON l.topic_id = t.id WHERE t.language_id = ?'
    );
    $stmt->execute([$language_id]);
    $total = (int)$stmt->fetchColumn();
    if ($total === 0) return ['total' => 0, 'completed' => 0, 'pct' => 0];

    $stmt2 = $pdo->prepare(
        'SELECT COUNT(*) FROM user_progress up
         JOIN lessons l ON up.lesson_id = l.id
         JOIN topics t ON l.topic_id = t.id
         WHERE up.user_id = ? AND t.language_id = ? AND up.completed_at IS NOT NULL'
    );
    $stmt2->execute([$user_id, $language_id]);
    $completed = (int)$stmt2->fetchColumn();
    return [
        'total' => $total,
        'completed' => $completed,
        'pct' => (int)round($completed / $total * 100),
    ];
}

/**
 * Get completion stats for lessons within a topic.
 */
function get_topic_progress(int $user_id, int $topic_id): array {
    $pdo = get_db();
    $s1 = $pdo->prepare('SELECT COUNT(*) FROM lessons WHERE topic_id = ?');
    $s1->execute([$topic_id]);
    $total = (int)$s1->fetchColumn();
    if ($total === 0) return ['total' => 0, 'completed' => 0, 'pct' => 0];

    $s2 = $pdo->prepare(
        'SELECT COUNT(*) FROM user_progress up
         JOIN lessons l ON up.lesson_id = l.id
         WHERE up.user_id = ? AND l.topic_id = ? AND up.completed_at IS NOT NULL'
    );
    $s2->execute([$user_id, $topic_id]);
    $completed = (int)$s2->fetchColumn();
    return [
        'total' => $total,
        'completed' => $completed,
        'pct' => (int)round($completed / $total * 100),
    ];
}

/**
 * Mark a lesson as accessed (creates or updates a progress row).
 */
function touch_lesson_progress(int $user_id, int $lesson_id): void {
    $pdo = get_db();
    $existing = get_lesson_progress($user_id, $lesson_id);
    if ($existing) {
        $pdo->prepare(
            "UPDATE user_progress SET last_accessed_at = datetime('now') WHERE user_id = ? AND lesson_id = ?"
        )->execute([$user_id, $lesson_id]);
    } else {
        $pdo->prepare(
            'INSERT INTO user_progress (user_id, lesson_id) VALUES (?, ?)'
        )->execute([$user_id, $lesson_id]);
    }
}

/**
 * Simple slug generator.
 */
function slugify(string $text): string {
    $text = mb_strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    return trim($text, '-');
}

