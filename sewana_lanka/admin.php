<?php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if (empty($_SESSION['admin_logged'])) {
    // Redirect unauthenticated visitors to the private direct admin URL.
    header('Location: sewana-admin-access.php');
    exit;
}

const MAX_IMAGES = 10;
const MAX_IMAGE_BYTES = 50 * 1024 * 1024;
const MAX_VIDEO_BYTES = 1024 * 1024 * 1024;

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

$uploadDir = __DIR__ . '/uploads/';
if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
    exit('Unable to create the uploads directory.');
}

$message = $_SESSION['flash'] ?? '';
$messageType = $_SESSION['flash_type'] ?? 'success';
unset($_SESSION['flash'], $_SESSION['flash_type']);

function redirectWithMessage(string $message, string $type = 'success'): void
{
    $_SESSION['flash'] = $message;
    $_SESSION['flash_type'] = $type;
    header('Location: admin.php');
    exit;
}

function validCsrf(): bool
{
    $submitted = $_POST['csrf'] ?? '';
    return is_string($submitted)
        && isset($_SESSION['csrf'])
        && hash_equals($_SESSION['csrf'], $submitted);
}

function phpSizeToBytes(string $value): int
{
    $value = trim($value);
    if ($value === '') {
        return 0;
    }

    $unit = strtolower(substr($value, -1));
    $number = (float) $value;
    return match ($unit) {
        'g' => (int) ($number * 1024 * 1024 * 1024),
        'm' => (int) ($number * 1024 * 1024),
        'k' => (int) ($number * 1024),
        default => (int) $number,
    };
}

function normalizeMultipleFiles(array $files): array
{
    $result = [];
    if (!isset($files['name']) || !is_array($files['name'])) {
        return $result;
    }

    foreach ($files['name'] as $index => $name) {
        $error = $files['error'][$index] ?? UPLOAD_ERR_NO_FILE;
        if ($error === UPLOAD_ERR_NO_FILE) {
            continue;
        }

        $result[] = [
            'name' => (string) $name,
            'tmp_name' => (string) ($files['tmp_name'][$index] ?? ''),
            'error' => (int) $error,
            'size' => (int) ($files['size'][$index] ?? 0),
        ];
    }

    return $result;
}

function saveUploadedFile(
    array $file,
    array $allowedMimeTypes,
    int $maximumBytes,
    string $uploadDir,
    string $prefix
): string {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('A selected file could not be uploaded. Upload error code: ' . ($file['error'] ?? 'unknown'));
    }

    $size = (int) ($file['size'] ?? 0);
    if ($size < 1 || $size > $maximumBytes) {
        throw new RuntimeException('A selected file is empty or larger than the allowed size.');
    }

    $temporaryName = (string) ($file['tmp_name'] ?? '');
    if (!is_uploaded_file($temporaryName)) {
        throw new RuntimeException('An invalid uploaded file was detected.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($temporaryName);
    if (!isset($allowedMimeTypes[$mimeType])) {
        throw new RuntimeException('Unsupported file type: ' . $mimeType);
    }

    $fileName = $prefix . '_' . bin2hex(random_bytes(16)) . '.' . $allowedMimeTypes[$mimeType];
    if (!move_uploaded_file($temporaryName, $uploadDir . $fileName)) {
        throw new RuntimeException('A file could not be saved in the uploads folder.');
    }

    return $fileName;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // PHP discards both POST and FILES when the complete request is larger
    // than post_max_size. Detect that case before checking the CSRF token.
    $requestBytes = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
    $postLimit = phpSizeToBytes((string) ini_get('post_max_size'));
    if ($requestBytes > 0 && $postLimit > 0 && $requestBytes > $postLimit) {
        redirectWithMessage(
            'The complete upload is larger than the PHP post_max_size limit ('
            . ini_get('post_max_size')
            . '). Increase post_max_size and upload_max_filesize in XAMPP php.ini, restart Apache, and try again.',
            'error'
        );
    }

    if (!validCsrf()) {
        redirectWithMessage('Security validation failed. Refresh the page and try again.', 'error');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $title = trim($_POST['title'] ?? '');
        $priceInput = trim($_POST['price'] ?? '');
        $priceAmount = filter_var($priceInput, FILTER_VALIDATE_FLOAT);
        $propertyType = trim($_POST['property_type'] ?? '');
        $district = trim($_POST['district'] ?? '');
        $status = $_POST['status'] ?? 'available';
        $details = trim($_POST['details'] ?? '');
        $images = normalizeMultipleFiles($_FILES['images'] ?? []);
        $video = $_FILES['video'] ?? null;
        $savedFiles = [];

        try {
            if (!is_writable($uploadDir)) {
                throw new RuntimeException('The uploads folder is not writable. Allow Apache to write to the uploads folder.');
            }
            if ($title === '' || $priceInput === '' || $propertyType === '' || $district === '' || $details === '') {
                throw new RuntimeException('Please complete every required property field.');
            }
            if ($priceAmount === false || $priceAmount < 0) {
                throw new RuntimeException('Please enter a valid numeric property price.');
            }
            if (!in_array($propertyType, ['house', 'land', 'commercial', 'apartment'], true)) {
                throw new RuntimeException('Please select a valid property type.');
            }
            if (!in_array($district, districts(), true)) {
                throw new RuntimeException('Please select a valid Sri Lankan district.');
            }
            if (!in_array($status, ['available', 'sold'], true)) {
                throw new RuntimeException('Invalid property status.');
            }
            if (count($images) < 1) {
                throw new RuntimeException('Select at least one property photo.');
            }
            if (count($images) > MAX_IMAGES) {
                throw new RuntimeException('Only 10 photos are allowed for one property post.');
            }

            $imageTypes = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
            ];
            $videoTypes = [
                'video/mp4' => 'mp4',
                'video/webm' => 'webm',
                'video/ogg' => 'ogv',
            ];

            $imageNames = [];
            foreach ($images as $image) {
                $savedName = saveUploadedFile(
                    $image,
                    $imageTypes,
                    MAX_IMAGE_BYTES,
                    $uploadDir,
                    'property_photo'
                );
                $imageNames[] = $savedName;
                $savedFiles[] = $savedName;
            }

            $videoName = null;
            if (is_array($video) && ($video['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $videoName = saveUploadedFile(
                    $video,
                    $videoTypes,
                    MAX_VIDEO_BYTES,
                    $uploadDir,
                    'property_video'
                );
                $savedFiles[] = $videoName;
            }

            $conn->beginTransaction();

            $propertyStmt = $conn->prepare(
                'INSERT INTO properties
                    (title, price, price_amount, property_type, district, status, image, details)
                 VALUES
                    (:title, :price, :price_amount, :property_type, :district, :status, :image, :details)'
            );
            $propertyStmt->execute([
                'title' => $title,
                'price' => 'Rs. ' . number_format((float) $priceAmount, 0),
                'price_amount' => $priceAmount,
                'property_type' => $propertyType,
                'district' => $district,
                'status' => $status,
                'image' => $imageNames[0],
                'details' => $details,
            ]);

            $propertyId = (int) $conn->lastInsertId();
            $mediaStmt = $conn->prepare(
                'INSERT INTO property_media
                    (property_id, file_name, media_type, sort_order)
                 VALUES
                    (:property_id, :file_name, :media_type, :sort_order)'
            );

            foreach ($imageNames as $position => $imageName) {
                $mediaStmt->execute([
                    'property_id' => $propertyId,
                    'file_name' => $imageName,
                    'media_type' => 'image',
                    'sort_order' => $position + 1,
                ]);
            }

            if ($videoName !== null) {
                $mediaStmt->execute([
                    'property_id' => $propertyId,
                    'file_name' => $videoName,
                    'media_type' => 'video',
                    'sort_order' => 1,
                ]);
            }

            $conn->commit();
            redirectWithMessage(
                'Property published with ' . count($imageNames) . ' photo(s)'
                . ($videoName ? ' and one video.' : '.')
            );
        } catch (Throwable $exception) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }

            foreach ($savedFiles as $savedFile) {
                $path = $uploadDir . basename($savedFile);
                if (is_file($path)) {
                    @unlink($path);
                }
            }

            redirectWithMessage($exception->getMessage(), 'error');
        }
    }

    if ($action === 'update_meta') {
        $propertyId = filter_input(INPUT_POST, 'property_id', FILTER_VALIDATE_INT);
        $district = trim($_POST['district'] ?? '');
        $propertyType = trim($_POST['property_type'] ?? '');
        $status = $_POST['status'] ?? '';

        if (
            !$propertyId
            || !in_array($district, districts(), true)
            || !in_array($propertyType, ['house', 'land', 'commercial', 'apartment'], true)
            || !in_array($status, ['available', 'sold'], true)
        ) {
            redirectWithMessage('Invalid district or property status.', 'error');
        }

        $stmt = $conn->prepare(
            'UPDATE properties
             SET district = :district, property_type = :property_type, status = :status
             WHERE id = :id'
        );
        $stmt->execute([
            'district' => $district,
            'property_type' => $propertyType,
            'status' => $status,
            'id' => $propertyId,
        ]);

        redirectWithMessage('Property type, district and availability were updated.');
    }

    if ($action === 'delete') {
        $propertyId = filter_input(INPUT_POST, 'property_id', FILTER_VALIDATE_INT);
        if (!$propertyId) {
            redirectWithMessage('Invalid property ID.', 'error');
        }

        try {
            $mediaStmt = $conn->prepare(
                'SELECT file_name FROM property_media WHERE property_id = :property_id'
            );
            $mediaStmt->execute(['property_id' => $propertyId]);
            $files = array_column($mediaStmt->fetchAll(), 'file_name');

            $legacyStmt = $conn->prepare('SELECT image FROM properties WHERE id = :id');
            $legacyStmt->execute(['id' => $propertyId]);
            $legacyImage = $legacyStmt->fetchColumn();
            if ($legacyImage) {
                $files[] = $legacyImage;
            }

            $conn->beginTransaction();
            $deleteStmt = $conn->prepare('DELETE FROM properties WHERE id = :id');
            $deleteStmt->execute(['id' => $propertyId]);
            $conn->commit();

            foreach (array_unique($files) as $fileName) {
                $path = $uploadDir . basename((string) $fileName);
                if (is_file($path)) {
                    @unlink($path);
                }
            }

            redirectWithMessage('Property and all uploaded media were deleted.');
        } catch (Throwable $exception) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            redirectWithMessage('The property could not be deleted.', 'error');
        }
    }
}

$properties = $conn->query(
    "SELECT p.*,
        SUM(CASE WHEN pm.media_type = 'image' THEN 1 ELSE 0 END) AS image_count,
        SUM(CASE WHEN pm.media_type = 'video' THEN 1 ELSE 0 END) AS video_count
     FROM properties p
     LEFT JOIN property_media pm ON pm.property_id = p.id
     GROUP BY p.id
     ORDER BY p.created_at DESC, p.id DESC"
)->fetchAll();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Admin Dashboard | Sewana Lanka</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body class="admin-page">
<header class="admin-header">
    <div>
        <small>Administrator Dashboard</small>
        <h1>Sewana Lanka</h1>
    </div>
    <nav>
        <a href="index.php" target="_blank" rel="noopener">View Website</a>
        <a class="logout" href="logout.php">Logout</a>
    </nav>
</header>

<main class="admin-container">
    <?php if ($message): ?>
        <div class="alert <?= $messageType === 'error' ? 'error' : 'success' ?>">
            <?= e($message) ?>
        </div>
    <?php endif; ?>

    <section class="admin-card">
        <div class="admin-title">
            <div>
                <span>Admin-only upload</span>
                <h2>Publish a New Property</h2>
            </div>
            <strong><?= count($properties) ?> listings</strong>
        </div>

        <form class="property-form" method="post" enctype="multipart/form-data" id="propertyForm">
            <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
            <input type="hidden" name="action" value="add">

            <label>
                Property title
                <input name="title" maxlength="255" placeholder="Modern house in Kandy" required>
            </label>

            <label>
                Price
                <input type="number" name="price" min="0" step="1000" placeholder="25000000" required>
            </label>

            <label>
                Property type
                <select name="property_type" required>
                    <option value="">Select type</option>
                    <option value="house">House</option>
                    <option value="land">Land</option>
                    <option value="commercial">Commercial</option>
                    <option value="apartment">Apartment</option>
                </select>
            </label>

            <label>
                District
                <select name="district" required>
                    <option value="">Select district</option>
                    <?php foreach (districts() as $district): ?>
                        <option value="<?= e($district) ?>"><?= e($district) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>
                Initial status
                <select name="status" required>
                    <option value="available">Available</option>
                    <option value="sold">Sold Out</option>
                </select>
            </label>

            <label class="wide">
                Property details
                <textarea name="details" rows="6" placeholder="Location, bedrooms, bathrooms, land size and other details" required></textarea>
            </label>

            <label class="wide">
                Property photos
                <input
                    type="file"
                    name="images[]"
                    id="images"
                    accept="image/jpeg,image/png,image/webp"
                    multiple
                    required
                >
                <small>
                    Upload 1–10 JPG, PNG or WEBP photos. Maximum 50 MB per photo.
                    Selected: <strong id="imageCount">0</strong>/10
                </small>
            </label>

            <label class="wide">
                Property video
                <input
                    type="file"
                    name="video"
                    id="video"
                    accept="video/mp4,video/webm,video/ogg"
                >
                <small>Optional: maximum one MP4, WEBM or OGG video, up to 1 GB.</small>
            </label>

            <button class="button primary" type="submit">Save & Publish</button>
        </form>
    </section>

    <section class="admin-card">
        <div class="admin-title">
            <div>
                <span>Manage content</span>
                <h2>Current Listings</h2>
            </div>
        </div>

        <?php if (!$properties): ?>
            <div class="empty-state">
                <h3>No properties found</h3>
                <p>Use the form above to publish your first listing.</p>
            </div>
        <?php else: ?>
            <div class="admin-list">
                <?php foreach ($properties as $property): ?>
                    <article>
                        <img src="<?= e(uploadUrl($property['image'])) ?>" alt="">
                        <div class="admin-property-info">
                            <div class="admin-badges">
                                <span class="district-badge"><?= e($property['district']) ?></span>
                                <span class="status-badge <?= $property['status'] === 'sold' ? 'sold' : 'available' ?>">
                                    <?= e(propertyStatusLabel($property['status'])) ?>
                                </span>
                            </div>
                            <h3><?= e($property['title']) ?></h3>
                            <strong><?= e($property['price']) ?></strong>
                            <p>
                                <?= (int) $property['image_count'] ?> photo(s)
                                · <?= (int) $property['video_count'] ?> video
                            </p>
                        </div>

                        <div class="admin-actions">
                            <a class="small-button view" href="property.php?id=<?= (int) $property['id'] ?>" target="_blank" rel="noopener">View Gallery</a>

                            <form method="post" class="metadata-form">
                                <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
                                <input type="hidden" name="action" value="update_meta">
                                <input type="hidden" name="property_id" value="<?= (int) $property['id'] ?>">

                                <select name="district" aria-label="Property district" required>
                                    <?php foreach (districts() as $district): ?>
                                        <option value="<?= e($district) ?>" <?= $property['district'] === $district ? 'selected' : '' ?>>
                                            <?= e($district) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>

                                <select name="property_type" aria-label="Property type" required>
                                    <?php foreach (['house', 'land', 'commercial', 'apartment'] as $type): ?>
                                        <option value="<?= e($type) ?>" <?= $property['property_type'] === $type ? 'selected' : '' ?>><?= e(ucfirst($type)) ?></option>
                                    <?php endforeach; ?>
                                </select>

                                <select name="status" aria-label="Property status" required>
                                    <option value="available" <?= $property['status'] === 'available' ? 'selected' : '' ?>>Available</option>
                                    <option value="sold" <?= $property['status'] === 'sold' ? 'selected' : '' ?>>Sold Out</option>
                                </select>

                                <button class="small-button status" type="submit">Update</button>
                            </form>

                            <form method="post" onsubmit="return confirm('Delete this property and every uploaded photo/video?')">
                                <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="property_id" value="<?= (int) $property['id'] ?>">
                                <button class="small-button delete" type="submit">Delete</button>
                            </form>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</main>

<script>
const imageInput = document.getElementById('images');
const imageCount = document.getElementById('imageCount');
const videoInput = document.getElementById('video');
const maxImageBytes = <?= MAX_IMAGE_BYTES ?>;
const maxVideoBytes = <?= MAX_VIDEO_BYTES ?>;

imageInput.addEventListener('change', () => {
    const count = imageInput.files.length;
    imageCount.textContent = count;

    if (count > 10) {
        alert('You can select a maximum of 10 photos.');
        imageInput.value = '';
        imageCount.textContent = '0';
        return;
    }

    const oversizedPhoto = Array.from(imageInput.files).find(file => file.size > maxImageBytes);
    if (oversizedPhoto) {
        alert(`Each photo must be 50 MB or smaller. "${oversizedPhoto.name}" is too large.`);
        imageInput.value = '';
        imageCount.textContent = '0';
    }
});

videoInput.addEventListener('change', () => {
    const video = videoInput.files[0];
    if (video && video.size > maxVideoBytes) {
        alert(`The video must be 1 GB or smaller. "${video.name}" is too large.`);
        videoInput.value = '';
    }
});

document.getElementById('propertyForm').addEventListener('submit', event => {
    const count = imageInput.files.length;
    if (count < 1 || count > 10) {
        event.preventDefault();
        alert('Select between 1 and 10 property photos.');
        return;
    }

    if (Array.from(imageInput.files).some(file => file.size > maxImageBytes)) {
        event.preventDefault();
        alert('Each property photo must be 50 MB or smaller.');
        return;
    }

    const video = videoInput.files[0];
    if (video && video.size > maxVideoBytes) {
        event.preventDefault();
        alert('The property video must be 1 GB or smaller.');
    }
});
</script>
</body>
</html>
