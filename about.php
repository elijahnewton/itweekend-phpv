<?php
session_start();
require_once __DIR__ . '/functions.php';

$page_title = 'About';
require __DIR__ . '/templates/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <h1 class="fw-bold mb-4"><i class="bi bi-info-circle text-primary me-2"></i>About IT Weekend</h1>
        <p class="lead mb-4">
            IT Weekend is a structured learning platform built for busy students and professionals
            who want to master technology skills on their own schedule.
        </p>
        <div class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-body p-4">
                <h4 class="fw-semibold mb-3">Our Mission</h4>
                <p>
                    We believe great technical education should be accessible, practical, and efficient.
                    Our lessons are designed to be completed in short weekend sessions, giving you real,
                    usable skills — not just theory.
                </p>
            </div>
        </div>
        <div class="row g-4 mb-4">
            <?php foreach ([
                ['⚙️', 'C Programming',   'Master systems programming fundamentals from syntax to memory management.'],
                ['🌐', 'Web Development', 'Build modern websites with HTML, CSS, and JavaScript.'],
                ['🔒', 'Cyber Security',  'Learn how to think like an attacker and defend real systems.'],
            ] as [$icon, $name, $desc]): ?>
            <div class="col-md-4">
                <div class="card border-0 bg-light rounded-3 h-100">
                    <div class="card-body p-3 text-center">
                        <div class="fs-1 mb-2"><?= $icon ?></div>
                        <h6 class="fw-bold"><?= h($name) ?></h6>
                        <p class="text-muted small mb-0"><?= h($desc) ?></p>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <a href="/learn.php" class="btn btn-primary btn-lg">Start Learning Free &rarr;</a>
    </div>
</div>

<?php require __DIR__ . '/templates/footer.php'; ?>
