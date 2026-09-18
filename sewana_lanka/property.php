<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

$propertyId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$propertyId) {
    http_response_code(404);
    exit('Property not found.');
}

$stmt = $conn->prepare('SELECT * FROM properties WHERE id = :id LIMIT 1');
$stmt->execute(['id' => $propertyId]);
$property = $stmt->fetch();

if (!$property) {
    http_response_code(404);
    exit('Property not found.');
}

$mediaStmt = $conn->prepare(
    "SELECT file_name, media_type, sort_order
     FROM property_media
     WHERE property_id = :property_id
     ORDER BY media_type = 'video', sort_order, id"
);
$mediaStmt->execute(['property_id' => $propertyId]);
$media = $mediaStmt->fetchAll();

if (!$media && !empty($property['image'])) {
    $media[] = [
        'file_name' => $property['image'],
        'media_type' => 'image',
        'sort_order' => 1,
    ];
}

$gallery = [];
foreach ($media as $item) {
    $gallery[] = [
        'type' => $item['media_type'] === 'video' ? 'video' : 'image',
        'src' => uploadUrl($item['file_name']),
    ];
}

$isSold = $property['status'] === 'sold';
$whatsAppMessage = rawurlencode('Hello, I am interested in: ' . $property['title']);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= e($property['title']) ?> | Sewana Lanka</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<header class="site-header">
    <a class="brand" href="index.php">
        <img src="assets/sewana-logo.jpg" alt="Sewana Lanka logo">
        <span>Sewana Lanka</span>
    </a>
    <nav class="main-nav detail-nav">
        <a href="index.php#properties">← All Properties</a>
        <a href="index.php#contact">Contact</a>
    </nav>
</header>

<main class="property-detail-page">
    <div class="detail-heading">
        <div>
            <span class="district-badge"><?= e($property['district']) ?> District</span>
            <span class="status-badge <?= $isSold ? 'sold' : 'available' ?>">
                <?= e(propertyStatusLabel($property['status'])) ?>
            </span>
            <h1><?= e($property['title']) ?></h1>
            <p class="detail-price"><?= e($property['price']) ?></p>
            <p class="detail-type"><?= e(ucfirst($property['property_type'])) ?> property</p>
        </div>

        <?php if (!$isSold): ?>
            <a
                class="button primary"
                href="https://wa.me/94772986984?text=<?= $whatsAppMessage ?>"
                target="_blank"
                rel="noopener"
            >Enquire on WhatsApp</a>
        <?php else: ?>
            <div class="sold-callout">This property is sold out.</div>
        <?php endif; ?>
    </div>

    <section class="gallery-layout">
        <div class="main-viewer" id="mainViewer"></div>

        <div class="thumbnail-list" id="thumbnailList">
            <?php foreach ($gallery as $index => $item): ?>
                <button
                    class="media-thumbnail <?= $index === 0 ? 'active' : '' ?>"
                    type="button"
                    data-index="<?= $index ?>"
                    aria-label="View <?= $item['type'] ?> <?= $index + 1 ?>"
                >
                    <?php if ($item['type'] === 'video'): ?>
                        <video muted preload="metadata">
                            <source src="<?= e($item['src']) ?>">
                        </video>
                        <span class="video-label">▶ Video</span>
                    <?php else: ?>
                        <img src="<?= e($item['src']) ?>" alt="Property photo <?= $index + 1 ?>" loading="lazy">
                    <?php endif; ?>
                </button>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="detail-information">
        <h2>Property Details</h2>
        <p><?= nl2br(e($property['details'])) ?></p>
    </section>
</main>

<footer id="contact">
    <div class="footer-grid">
        <div>
            <img class="footer-brand" src="assets/sewana-logo.jpg" alt="Sewana Lanka logo">
            <p>Connecting land, homes and commercial ventures across Sri Lanka.</p>
        </div>
        <div class="contact-list">
            <h3>Contact</h3>
            <a href="tel:+94751227606"><?= contactIcon('call') ?><span>+94 75 122 7606</span></a>
            <a href="mailto:Sewanalanka.info@gmail.com"><?= contactIcon('email') ?><span>Sewanalanka.info@gmail.com</span></a>
            <a href="https://www.facebook.com/share/18CM1Nfgk7/" target="_blank" rel="noopener"><?= contactIcon('facebook') ?><span>Facebook</span></a>
            <a href="https://wa.me/94751227606?text=<?= $whatsAppMessage ?>" target="_blank" rel="noopener"><?= contactIcon('whatsapp') ?><span>WhatsApp</span></a>
        </div>
        <div class="powered">
            <img src="assets/dark-forest-logo.png" alt="Dark Forest logo">
            <p>Powered by <strong>PrimeWeb Studio</strong></p>
        </div>
    </div>
    <div class="copyright">© <?= date('Y') ?> Sewana Lanka. All rights reserved.</div>
</footer>

<script>
const gallery = <?= json_encode($gallery, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
const viewer = document.getElementById('mainViewer');
const thumbnails = document.querySelectorAll('.media-thumbnail');

function showMedia(index) {
    if (!gallery[index]) return;

    viewer.innerHTML = '';
    const item = gallery[index];

    if (item.type === 'video') {
        const video = document.createElement('video');
        video.controls = true;
        video.playsInline = true;
        video.preload = 'metadata';
        video.src = item.src;
        viewer.appendChild(video);
    } else {
        const image = document.createElement('img');
        image.src = item.src;
        image.alt = <?= json_encode($property['title'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        viewer.appendChild(image);
    }

    thumbnails.forEach(button => button.classList.remove('active'));
    if (thumbnails[index]) thumbnails[index].classList.add('active');
}

thumbnails.forEach(button => {
    button.addEventListener('click', () => showMedia(Number(button.dataset.index)));
});

showMedia(0);
</script>
</body>
</html>
