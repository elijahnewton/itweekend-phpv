<?php
session_start();
require_once __DIR__ . '/functions.php';

$pdo  = get_db();
$user = current_user();

$lang_slug   = $_GET['lang'] ?? '';
$topic_slug  = $_GET['topic'] ?? '';

$lang_filter  = null;
$topic_filter = null;
$where        = '';
$params       = [];

if ($lang_slug) {
    $s = $pdo->prepare('SELECT * FROM languages WHERE slug = ?');
    $s->execute([$lang_slug]);
    $lang_filter = $s->fetch();
}
if ($topic_slug) {
    $s = $pdo->prepare('SELECT * FROM topics WHERE slug = ?');
    $s->execute([$topic_slug]);
    $topic_filter = $s->fetch();
}

$sql = 'SELECT v.*, l.name as language_name, l.slug as language_slug, t.name as topic_name, t.slug as topic_slug
        FROM videos v
        LEFT JOIN languages l ON v.language_id = l.id
        LEFT JOIN topics t ON v.topic_id = t.id';
$conditions = [];
if ($lang_filter) {
    $conditions[] = 'v.language_id = ?';
    $params[] = $lang_filter['id'];
}
if ($topic_filter) {
    $conditions[] = 'v.topic_id = ?';
    $params[] = $topic_filter['id'];
}
if ($conditions) {
    $sql .= ' WHERE ' . implode(' AND ', $conditions);
}
$sql .= ' ORDER BY v.created_at DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$videos = $stmt->fetchAll();

$languages = $pdo->query('SELECT * FROM languages ORDER BY order_index')->fetchAll();

$page_title = 'Videos';
require __DIR__ . '/templates/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="fw-bold mb-0"><i class="bi bi-play-circle text-primary me-2"></i>Videos</h1>
</div>

<!-- Filters -->
<div class="d-flex flex-wrap gap-2 mb-4">
    <a href="/videos.php" class="btn btn-sm <?= !$lang_slug ? 'btn-primary' : 'btn-outline-secondary' ?>">All</a>
    <?php foreach ($languages as $l): ?>
    <a href="/videos.php?lang=<?= urlencode($l['slug']) ?>"
       class="btn btn-sm <?= $lang_slug === $l['slug'] ? 'btn-primary' : 'btn-outline-secondary' ?>">
        <?= h($l['icon'] ?? '') ?> <?= h($l['name']) ?>
    </a>
    <?php endforeach; ?>
</div>

<?php if (empty($videos)): ?>
<div class="alert alert-info">No videos available yet.</div>
<?php else: ?>
<div class="row g-4">
    <?php foreach ($videos as $v): ?>
    <div class="col-md-4 col-lg-3">
        <div class="card border-0 shadow-sm rounded-3 h-100">
            <?php
            // Embed YouTube/Vimeo or plain link
            $embed_url = '';
            if (preg_match('/youtube\.com\/watch\?v=([^&]+)/', $v['url'], $m)) {
                $embed_url = 'https://www.youtube.com/embed/' . $m[1];
            } elseif (preg_match('/youtu\.be\/([^?]+)/', $v['url'], $m)) {
                $embed_url = 'https://www.youtube.com/embed/' . $m[1];
            } elseif (preg_match('/vimeo\.com\/(\d+)/', $v['url'], $m)) {
                $embed_url = 'https://player.vimeo.com/video/' . $m[1];
            }
            ?>
            <?php if ($embed_url): ?>
            <div class="ratio ratio-16x9">
                <iframe src="<?= h($embed_url) ?>" title="<?= h($v['title']) ?>"
                        allowfullscreen loading="lazy" class="rounded-top-3"></iframe>
            </div>
            <?php else: ?>
            <div class="d-flex align-items-center justify-content-center bg-light rounded-top-3"
                 style="height:160px">
                <a href="<?= h($v['url']) ?>" target="_blank" rel="noopener noreferrer"
                   class="btn btn-outline-primary">
                    <i class="bi bi-play-circle-fill me-2 fs-4"></i>Watch Video
                </a>
            </div>
            <?php endif; ?>
            <div class="card-body p-3">
                <h6 class="fw-semibold mb-1"><?= h($v['title']) ?></h6>
                <?php if ($v['description']): ?>
                <p class="text-muted small mb-2"><?= h(mb_substr($v['description'], 0, 80)) ?><?= strlen($v['description']) > 80 ? '…' : '' ?></p>
                <?php endif; ?>
                <div class="d-flex gap-2 flex-wrap">
                    <?php if ($v['language_name']): ?>
                    <a href="/videos.php?lang=<?= urlencode($v['language_slug']) ?>"
                       class="badge bg-primary text-decoration-none"><?= h($v['language_name']) ?></a>
                    <?php endif; ?>
                    <?php if ($v['topic_name']): ?>
                    <span class="badge bg-secondary"><?= h($v['topic_name']) ?></span>
                    <?php endif; ?>
                    <?php if ($v['duration_seconds']): ?>
                    <span class="badge bg-light text-dark border">
                        <i class="bi bi-clock me-1"></i><?php
                        $m = (int)floor($v['duration_seconds'] / 60);
                        $s2 = $v['duration_seconds'] % 60;
                        echo $m . ':' . str_pad($s2, 2, '0', STR_PAD_LEFT);
                        ?>
                    </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php require __DIR__ . '/templates/footer.php'; ?>
