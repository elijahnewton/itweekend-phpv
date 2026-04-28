<?php
session_start();
require_once __DIR__ . '/functions.php';

if (is_logged_in()) {
    redirect('/learn.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email    = trim(strtolower($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = 'Email and password are required.';
    } else {
        $pdo  = get_db();
        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            flash('Welcome back, ' . ($user['name'] ?: $user['email']) . '!');
            $next = $_GET['next'] ?? '/learn.php';
            // only allow relative URLs to prevent open redirect
            if (!str_starts_with($next, '/')) $next = '/learn.php';
            redirect($next);
        } else {
            $error = 'Invalid email or password.';
        }
    }
}

$page_title = 'Sign In';
require __DIR__ . '/templates/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-5 col-lg-4">
        <div class="card shadow-sm border-0 rounded-4 mt-4">
            <div class="card-body p-4">
                <h2 class="card-title text-center mb-4 fw-bold">
                    <i class="bi bi-box-arrow-in-right text-primary me-2"></i>Sign In
                </h2>
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= h($error) ?></div>
                <?php endif; ?>
                <form method="post" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                    <div class="mb-3">
                        <label class="form-label fw-semibold" for="email">Email address</label>
                        <input type="email" class="form-control" id="email" name="email"
                               value="<?= h($_POST['email'] ?? '') ?>" required autofocus>
                    </div>
                    <div class="mb-4">
                        <label class="form-label fw-semibold" for="password">Password</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 py-2">Sign In</button>
                </form>
                <p class="mt-3 text-center text-muted small">
                    Don&rsquo;t have an account? <a href="/register.php">Register here</a>
                </p>
            </div>
        </div>
        <p class="text-center text-muted small mt-3">
            <em>Default admin: admin@lms.local / admin123</em>
        </p>
    </div>
</div>

<?php require __DIR__ . '/templates/footer.php'; ?>
