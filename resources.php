<?php
session_start();
require_once __DIR__ . '/functions.php';

$resources = [
    [
        'category' => 'C Programming',
        'slug'     => 'c-programming',
        'items'    => [
            ['title' => 'The C Programming Language (K&R)', 'url' => 'https://en.wikipedia.org/wiki/The_C_Programming_Language', 'type' => 'Book'],
            ['title' => 'cppreference.com — C reference',   'url' => 'https://en.cppreference.com/w/c',                         'type' => 'Reference'],
            ['title' => 'CS50 — Introduction to C',         'url' => 'https://cs50.harvard.edu/',                                'type' => 'Course'],
            ['title' => 'Beej\'s Guide to C Programming',   'url' => 'https://beej.us/guide/bgc/',                               'type' => 'Guide'],
        ],
    ],
    [
        'category' => 'Web Development',
        'slug'     => 'web-development',
        'items'    => [
            ['title' => 'MDN Web Docs',             'url' => 'https://developer.mozilla.org/',         'type' => 'Reference'],
            ['title' => 'CSS-Tricks',               'url' => 'https://css-tricks.com/',                'type' => 'Blog'],
            ['title' => 'JavaScript.info',          'url' => 'https://javascript.info/',               'type' => 'Tutorial'],
            ['title' => 'The Odin Project',         'url' => 'https://www.theodinproject.com/',        'type' => 'Course'],
            ['title' => 'freeCodeCamp',             'url' => 'https://www.freecodecamp.org/',          'type' => 'Course'],
        ],
    ],
    [
        'category' => 'Cyber Security',
        'slug'     => 'cyber-security',
        'items'    => [
            ['title' => 'TryHackMe',                     'url' => 'https://tryhackme.com/',                  'type' => 'Platform'],
            ['title' => 'Hack The Box',                  'url' => 'https://www.hackthebox.com/',             'type' => 'Platform'],
            ['title' => 'OWASP Top 10',                  'url' => 'https://owasp.org/www-project-top-ten/', 'type' => 'Reference'],
            ['title' => 'Cybrary Free Courses',          'url' => 'https://www.cybrary.it/',                'type' => 'Course'],
            ['title' => 'PortSwigger Web Security',      'url' => 'https://portswigger.net/web-security',   'type' => 'Course'],
        ],
    ],
    [
        'category' => 'General',
        'slug'     => 'general',
        'items'    => [
            ['title' => 'Stack Overflow',      'url' => 'https://stackoverflow.com/',        'type' => 'Community'],
            ['title' => 'GitHub',              'url' => 'https://github.com/',               'type' => 'Platform'],
            ['title' => 'GeeksforGeeks',       'url' => 'https://www.geeksforgeeks.org/',    'type' => 'Tutorial'],
            ['title' => 'LeetCode',            'url' => 'https://leetcode.com/',             'type' => 'Practice'],
        ],
    ],
];

$type_colors = [
    'Book' => 'bg-warning text-dark',
    'Reference' => 'bg-info text-dark',
    'Course' => 'bg-primary',
    'Tutorial' => 'bg-success',
    'Guide' => 'bg-secondary',
    'Blog' => 'bg-danger',
    'Platform' => 'bg-dark',
    'Community' => 'bg-indigo',
    'Practice' => 'bg-teal',
];

$page_title = 'Resources';
require __DIR__ . '/templates/header.php';
?>

<h1 class="fw-bold mb-4"><i class="bi bi-link-45deg text-primary me-2"></i>Learning Resources</h1>
<p class="lead text-muted mb-5">Handpicked books, tutorials, and tools to complement your learning journey.</p>

<div class="row g-4">
    <?php foreach ($resources as $group): ?>
    <div class="col-md-6 col-lg-3">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-header bg-primary text-white rounded-top-4 py-3 px-4">
                <h5 class="mb-0 fw-semibold"><?= h($group['category']) ?></h5>
            </div>
            <div class="list-group list-group-flush">
                <?php foreach ($group['items'] as $item): ?>
                <a href="<?= h($item['url']) ?>" target="_blank" rel="noopener noreferrer"
                   class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-3">
                    <span class="small fw-medium"><?= h($item['title']) ?></span>
                    <span class="badge <?= $type_colors[$item['type']] ?? 'bg-secondary' ?> ms-2 flex-shrink-0">
                        <?= h($item['type']) ?>
                    </span>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php require __DIR__ . '/templates/footer.php'; ?>
