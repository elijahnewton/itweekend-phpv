<?php
session_start();
require_once __DIR__ . '/functions.php';

if (is_logged_in()) {
    redirect('/learn.php');
}

$error = '';
$values = ['name' => '', 'email' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name     = trim($_POST['name'] ?? '');
    $email    = trim(strtolower($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm'] ?? '';

    $values = compact('name', 'email');

    if ($name === '' || $email === '' || $password === '') {
        $error = 'All fields are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    }

    if ($error === '') {
        $pdo   = get_db();
        $check = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $check->execute([$email]);
        if ($check->fetch()) {
            $error = 'An account with that email already exists.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $pdo->prepare('INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)')
                ->execute([$name, $email, $hash, ROLE_STUDENT]);
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int)$pdo->lastInsertId();
            flash('Account created! Welcome, ' . $name . '.');
            redirect('/learn.php');
        }
    }
}

$page_title = 'Create Account';
require __DIR__ . '/templates/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
        <div class="card shadow-sm border-0 rounded-4 mt-4">
            <div class="card-body p-4">
                <h2 class="card-title text-center mb-4 fw-bold">
                    <i class="bi bi-person-plus text-primary me-2"></i>Create Account
                </h2>
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= h($error) ?></div>
                <?php endif; ?>
                <form method="post" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                    <div class="mb-3">
                        <label class="form-label fw-semibold" for="name">Full Name</label>
                        <input type="text" class="form-control" id="name" name="name"
                               value="<?= h($values['name']) ?>" required autofocus>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold" for="email">Email address</label>
                        <input type="email" class="form-control" id="email" name="email"
                               value="<?= h($values['email']) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold" for="password">Password</label>
                        <input type="password" class="form-control" id="password" name="password"
                               minlength="8" required>
                        <div class="form-text">At least 8 characters</div>
                    </div>
                    <div class="mb-4">
                        <label class="form-label fw-semibold" for="confirm">Confirm Password</label>
                        <input type="password" class="form-control" id="confirm" name="confirm" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 py-2">Create Account</button>
                </form>
                <p class="mt-3 text-center text-muted small">
                    Already have an account? <a href="/login.php">Sign in</a>
                </p>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/templates/footer.php'; ?>
