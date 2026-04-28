<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($page_title ?? APP_NAME) ?> &mdash; <?= h(APP_NAME) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="/assets/css/app.css" rel="stylesheet">
</head>
<body>
<?php
$_user = current_user();
$_flash = get_flash();
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container">
        <a class="navbar-brand fw-bold" href="/dashboard.php">
            <i class="bi bi-mortarboard-fill me-2"></i><?= h(APP_NAME) ?>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMain">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navMain">
            <ul class="navbar-nav me-auto">
                <?php if ($_user): ?>
                <li class="nav-item">
                    <a class="nav-link" href="/dashboard.php"><i class="bi bi-speedometer2 me-1"></i>Dashboard</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="/courses.php"><i class="bi bi-collection me-1"></i>Courses</a>
                </li>
                <?php if ($_user['role'] === ROLE_INSTRUCTOR || $_user['role'] === ROLE_ADMIN): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                        <i class="bi bi-person-workspace me-1"></i>Instructor
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="/instructor/courses.php">My Courses</a></li>
                        <li><a class="dropdown-item" href="/instructor/create_course.php">Create Course</a></li>
                    </ul>
                </li>
                <?php endif; ?>
                <?php if ($_user['role'] === ROLE_ADMIN): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                        <i class="bi bi-shield-lock me-1"></i>Admin
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="/admin/index.php">Dashboard</a></li>
                        <li><a class="dropdown-item" href="/admin/users.php">Manage Users</a></li>
                        <li><a class="dropdown-item" href="/admin/courses.php">Manage Courses</a></li>
                    </ul>
                </li>
                <?php endif; ?>
                <?php endif; ?>
            </ul>
            <ul class="navbar-nav ms-auto">
                <?php if ($_user): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                        <i class="bi bi-person-circle me-1"></i><?= h($_user['name']) ?>
                        <span class="badge bg-secondary ms-1"><?= h($_user['role']) ?></span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="/profile.php"><i class="bi bi-person me-2"></i>Profile</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                    </ul>
                </li>
                <?php else: ?>
                <li class="nav-item"><a class="nav-link" href="/login.php">Login</a></li>
                <li class="nav-item"><a class="btn btn-light btn-sm ms-2" href="/register.php">Register</a></li>
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
