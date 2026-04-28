<?php
session_start();
require_once __DIR__ . '/functions.php';

$page_title = 'Master IT Skills on Your Schedule';
require __DIR__ . '/templates/header.php';
$user = current_user();
?>

<!-- Hero -->
<div class="hero-section rounded-4 mb-5 p-5 text-white"
     style="background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);">
    <div class="row align-items-center g-4">
        <div class="col-lg-7">
            <span class="badge bg-white text-primary mb-3 px-3 py-2">🎓 Short lessons, real skills</span>
            <h1 class="display-5 fw-bold mb-3">
                Master IT skills you can&rsquo;t find <span style="text-decoration:underline wavy #a5b4fc">anywhere</span>
            </h1>
            <p class="lead opacity-90 mb-4">
                Follow structured tutorials and build real projects in C Programming, Web Development, and Cyber Security.
                Weekend-sized lessons designed for busy learners.
            </p>
            <div class="d-flex flex-wrap gap-3">
                <a href="/learn.php" class="btn btn-light btn-lg fw-semibold">
                    <?= $user ? 'Continue Learning' : 'Start Learning Free' ?>
                </a>
                <?php if (!$user): ?>
                <a href="/register.php" class="btn btn-outline-light btn-lg">Create Free Account</a>
                <?php else: ?>
                <a href="/progress.php" class="btn btn-outline-light btn-lg">View My Progress</a>
                <?php endif; ?>
            </div>
        </div>
        <div class="col-lg-5 text-center d-none d-lg-block">
            <svg viewBox="0 0 400 320" xmlns="http://www.w3.org/2000/svg" class="w-100" style="max-width:360px" aria-hidden="true">
                <rect x="60" y="60" width="280" height="180" rx="12" fill="white" opacity="0.12"/>
                <rect x="60" y="60" width="280" height="32" rx="12" fill="white" opacity="0.25"/>
                <circle cx="82" cy="76" r="5" fill="white" opacity="0.5"/>
                <circle cx="98" cy="76" r="5" fill="white" opacity="0.5"/>
                <circle cx="114" cy="76" r="5" fill="white" opacity="0.5"/>
                <rect x="80" y="112" width="60" height="8" rx="4" fill="white" opacity="0.3"/>
                <rect x="150" y="112" width="100" height="8" rx="4" fill="white" opacity="0.5"/>
                <rect x="80" y="130" width="80" height="8" rx="4" fill="white" opacity="0.25"/>
                <rect x="170" y="130" width="60" height="8" rx="4" fill="white" opacity="0.35"/>
                <rect x="95" y="148" width="100" height="8" rx="4" fill="white" opacity="0.3"/>
                <rect x="205" y="148" width="50" height="8" rx="4" fill="white" opacity="0.4"/>
                <rect x="80" y="166" width="50" height="8" rx="4" fill="white" opacity="0.25"/>
                <rect x="140" y="166" width="80" height="8" rx="4" fill="white" opacity="0.35"/>
                <rect x="60" y="240" width="280" height="14" rx="4" fill="white" opacity="0.15"/>
            </svg>
        </div>
    </div>
</div>

<!-- Why IT Weekend? -->
<div class="card border-0 shadow-sm rounded-4 mb-5">
    <div class="card-body p-4 p-md-5">
        <h2 class="fw-bold mb-4">Why IT Weekend?</h2>
        <div class="row g-3">
            <?php foreach ([
                ['📅', 'Weekend-sized', 'Lessons designed to fit in a single session'],
                ['⚡', 'Bite-sized', 'No fluff — just the concepts that matter'],
                ['🛠️', 'Practical', 'Real code examples you can use immediately'],
                ['🎯', 'Focused tracks', 'Three clear paths from zero to job-ready'],
            ] as [$emoji, $title, $text]): ?>
            <div class="col-sm-6 col-lg-3">
                <div class="d-flex align-items-start gap-3 p-3 bg-light rounded-3">
                    <span class="fs-3"><?= $emoji ?></span>
                    <div>
                        <div class="fw-semibold"><?= h($title) ?></div>
                        <div class="text-muted small"><?= h($text) ?></div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Three Tracks -->
<h2 class="fw-bold mb-4">Three Core Tracks</h2>
<div class="row g-4 mb-5">
    <?php
    $tracks = [
        ['/language.php?slug=c-programming',   '⚙️', 'C Programming',  'Fundamentals',  'bg-primary',   'From basic syntax to pointers and memory management — build strong foundations in systems programming.'],
        ['/language.php?slug=web-development',  '🌐', 'Web Development','Practical',     'bg-info',      'HTML, CSS, JavaScript, and modern frameworks. Build real-world websites from scratch.'],
        ['/language.php?slug=cyber-security',   '🔒', 'Cyber Security', 'In-Demand',     'bg-purple',    'Learn ethical hacking, network security, and how to protect systems from modern threats.'],
    ];
    foreach ($tracks as [$href, $icon, $title, $badge, $color, $desc]):
    ?>
    <div class="col-md-4">
        <a href="<?= h($href) ?>" class="card border-0 shadow-sm rounded-4 h-100 text-decoration-none hover-lift">
            <div class="card-body p-4">
                <div class="d-flex align-items-center gap-3 mb-3">
                    <div class="track-icon <?= $color ?> text-white rounded-3 d-flex align-items-center justify-content-center"
                         style="width:52px;height:52px;font-size:1.6rem">
                        <?= $icon ?>
                    </div>
                    <div>
                        <div class="fw-bold fs-5 text-dark"><?= h($title) ?></div>
                        <span class="badge bg-secondary"><?= h($badge) ?></span>
                    </div>
                </div>
                <p class="text-muted small mb-3"><?= h($desc) ?></p>
                <span class="btn btn-primary btn-sm">Explore &rarr;</span>
            </div>
        </a>
    </div>
    <?php endforeach; ?>
</div>

<?php require __DIR__ . '/templates/footer.php'; ?>
