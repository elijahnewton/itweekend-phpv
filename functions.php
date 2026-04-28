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
    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch() ?: null;
}

function is_logged_in(): bool {
    return isset($_SESSION['user_id']);
}

function require_login(): void {
    if (!is_logged_in()) {
        header('Location: /login.php');
        exit;
    }
}

function require_role(string ...$roles): void {
    require_login();
    $user = current_user();
    if (!$user || !in_array($user['role'], $roles, true)) {
        flash('Access denied.', 'danger');
        header('Location: /dashboard.php');
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
        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/dashboard.php'));
        exit;
    }
}

function paginate(PDO $pdo, string $sql, array $params, int $page, int $per_page = 10): array {
    $count_sql = 'SELECT COUNT(*) FROM (' . $sql . ')';
    $total = (int)$pdo->prepare($count_sql)->execute($params) ? $pdo->prepare($count_sql)->execute($params) : 0;

    $stmt = $pdo->prepare($count_sql);
    $stmt->execute($params);
    $total = (int)$stmt->fetchColumn();

    $offset = ($page - 1) * $per_page;
    $stmt2 = $pdo->prepare($sql . " LIMIT $per_page OFFSET $offset");
    $stmt2->execute($params);
    $rows = $stmt2->fetchAll();

    return [
        'rows' => $rows,
        'total' => $total,
        'pages' => (int)ceil($total / $per_page),
        'current' => $page,
    ];
}

function get_course_progress(int $user_id, int $course_id): array {
    $pdo = get_db();
    $total = (int)$pdo->prepare('SELECT COUNT(*) FROM lessons WHERE course_id = ?')
        ->execute([$course_id]) ? 0 : 0;
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM lessons WHERE course_id = ?');
    $stmt->execute([$course_id]);
    $total = (int)$stmt->fetchColumn();

    if ($total === 0) return ['completed' => 0, 'total' => 0, 'pct' => 0];

    $stmt2 = $pdo->prepare(
        'SELECT COUNT(*) FROM lesson_progress lp
         JOIN lessons l ON lp.lesson_id = l.id
         WHERE lp.user_id = ? AND l.course_id = ?'
    );
    $stmt2->execute([$user_id, $course_id]);
    $completed = (int)$stmt2->fetchColumn();

    return [
        'completed' => $completed,
        'total' => $total,
        'pct' => (int)round($completed / $total * 100),
    ];
}
