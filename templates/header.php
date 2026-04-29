<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($page_title ?? APP_NAME) ?> &mdash; <?= h(APP_NAME) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/github-dark.min.css">
    <link href="/assets/css/app.css" rel="stylesheet">
</head>
<body>
<?php
$_user  = current_user();
$_flash = get_flash();
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container">
        <a class="navbar-brand fw-bold" href="/index.php">
            <i class="bi bi-mortarboard-fill me-2"></i><?= h(APP_NAME) ?>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMain">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navMain">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link" href="/learn.php"><i class="bi bi-book me-1"></i>Learn</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="/videos.php"><i class="bi bi-play-circle me-1"></i>Videos</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="/resources.php"><i class="bi bi-link-45deg me-1"></i>Resources</a>
                </li>
                <?php if ($_user): ?>
                <li class="nav-item">
                    <a class="nav-link" href="/progress.php"><i class="bi bi-bar-chart me-1"></i>Progress</a>
                </li>
                <?php endif; ?>
                <?php if ($_user && $_user['role'] === ROLE_ADMIN): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                        <i class="bi bi-shield-lock me-1"></i>Admin
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="/admin/index.php"><i class="bi bi-speedometer2 me-2"></i>Dashboard</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="/admin/languages.php"><i class="bi bi-collection me-2"></i>Languages</a></li>
                        <li><a class="dropdown-item" href="/admin/topics.php"><i class="bi bi-folder me-2"></i>Topics</a></li>
                        <li><a class="dropdown-item" href="/admin/lessons.php"><i class="bi bi-file-text me-2"></i>Lessons</a></li>
                        <li><a class="dropdown-item" href="/admin/quizzes.php"><i class="bi bi-question-circle me-2"></i>Quizzes</a></li>
                        <li><a class="dropdown-item" href="/admin/videos.php"><i class="bi bi-camera-video me-2"></i>Videos</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="/admin/users.php"><i class="bi bi-people me-2"></i>Users</a></li>
                    </ul>
                </li>
                <?php endif; ?>
            </ul>
            <ul class="navbar-nav ms-auto">
                <?php if ($_user): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                        <i class="bi bi-person-circle me-1"></i><?= h($_user['name'] ?: $_user['email']) ?>
                        <?php if ($_user['role'] === ROLE_ADMIN): ?>
                        <span class="badge bg-warning text-dark ms-1">Admin</span>
                        <?php endif; ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="/profile.php"><i class="bi bi-person me-2"></i>Profile</a></li>
                        <li><a class="dropdown-item" href="/progress.php"><i class="bi bi-bar-chart me-2"></i>My Progress</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <form method="post" action="/logout.php" class="px-3 py-1">
                                <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger w-100">
                                    <i class="bi bi-box-arrow-right me-1"></i>Logout
                                </button>
                            </form>
                        </li>
                    </ul>
                </li>
                <?php else: ?>
                <li class="nav-item"><a class="nav-link" href="/login.php">Sign In</a></li>
                <li class="nav-item"><a class="btn btn-light btn-sm ms-2 mt-1" href="/register.php">Sign Up</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<main class="container py-4">
<?php if ($_flash): ?>
<div class="alert alert-<?= h($_flash['type']) ?> alert-dismissible fade show" role="alert">
    <?= h($_flash['msg']) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

