<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');

require_once dirname(__DIR__) . '/db.php';
require_once dirname(__DIR__) . '/functions.php';

try {
    $district = trim((string) ($_GET['district'] ?? ''));
    $propertyType = strtolower(trim((string) ($_GET['type'] ?? '')));
    $priceRange = trim((string) ($_GET['price_range'] ?? ''));
    $minPriceInput = trim((string) ($_GET['min_price'] ?? ''));
    $maxPriceInput = trim((string) ($_GET['max_price'] ?? ''));

    // The current UI sends one validated range. Keep min_price/max_price
    // support so saved links from previous versions continue to work.
    if ($priceRange !== '') {
        if (!preg_match('/^(\d+(?:\.\d+)?)-(\d*(?:\.\d+)?)$/', $priceRange, $matches)) {
            throw new InvalidArgumentException('Please choose a valid price range.');
        }
        $minPriceInput = $matches[1];
        $maxPriceInput = $matches[2];
    }
    $minPrice = $minPriceInput === '' ? null : filter_var($minPriceInput, FILTER_VALIDATE_FLOAT);
    $maxPrice = $maxPriceInput === '' ? null : filter_var($maxPriceInput, FILTER_VALIDATE_FLOAT);
    $allowedTypes = ['house', 'land', 'commercial', 'apartment'];
    if ($district !== '' && !in_array($district, districts(), true)) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'message' => 'Please choose a valid Sri Lankan district.']);
        exit;
    }
    if ($propertyType !== '' && !in_array($propertyType, $allowedTypes, true)) {
        throw new InvalidArgumentException('Please choose a valid property type.');
    }
    if ($minPrice === false || $maxPrice === false || ($minPrice !== null && $minPrice < 0) || ($maxPrice !== null && $maxPrice < 0)) {
        throw new InvalidArgumentException('Please enter valid non-negative price values.');
    }
    if ($minPrice !== null && $maxPrice !== null && $minPrice > $maxPrice) {
        throw new InvalidArgumentException('Minimum price cannot be higher than maximum price.');
    }

    // Detect the installed schema. Older project copies may not have the new
    // filter columns yet, so the API supplies safe fallback values instead of
    // failing and hiding every listing.
    $columnStatement = $conn->query('SHOW COLUMNS FROM properties');
    $availableColumns = array_column($columnStatement->fetchAll(), 'Field');
    $hasDistrict = in_array('district', $availableColumns, true);
    $hasStatus = in_array('status', $availableColumns, true);
    $hasPriceAmount = in_array('price_amount', $availableColumns, true);
    $hasPropertyType = in_array('property_type', $availableColumns, true);

    // Convert legacy values such as "Rs. 25,000,000" for price filtering.
    $legacyPriceSql = "CAST(REPLACE(REPLACE(REPLACE(REPLACE(LOWER(p.price), 'rs.', ''), 'rs', ''), ',', ''), ' ', '') AS DECIMAL(15,2))";
    $priceAmountSql = $hasPriceAmount ? "COALESCE(p.price_amount, {$legacyPriceSql})" : $legacyPriceSql;
    $districtSql = $hasDistrict ? 'p.district' : "'Not specified'";
    $statusSql = $hasStatus ? 'p.status' : "'available'";
    $propertyTypeSql = $hasPropertyType ? 'p.property_type' : "'house'";

    $mediaTableStatement = $conn->query("SHOW TABLES LIKE 'property_media'");
    $hasMediaTable = (bool) $mediaTableStatement->fetchColumn();
    $imageCountSql = $hasMediaTable
        ? "(SELECT COUNT(*) FROM property_media pm WHERE pm.property_id = p.id AND pm.media_type = 'image')"
        : '1';
    $videoCountSql = $hasMediaTable
        ? "(SELECT COUNT(*) FROM property_media pm WHERE pm.property_id = p.id AND pm.media_type = 'video')"
        : '0';

    $sql = "SELECT p.id, p.title, p.price,
            {$priceAmountSql} AS price_amount,
            {$propertyTypeSql} AS property_type,
            {$districtSql} AS district,
            {$statusSql} AS status,
            p.image, p.details, p.created_at,
            {$imageCountSql} AS image_count,
            {$videoCountSql} AS video_count
            FROM properties p";
    $params = [];

    $conditions = [];
    if ($district !== '' && $hasDistrict) {
        $conditions[] = 'p.district = :district';
        $params['district'] = $district;
    }
    if ($propertyType !== '' && $hasPropertyType) {
        $conditions[] = 'LOWER(TRIM(p.property_type)) = :property_type';
        $params['property_type'] = $propertyType;
    }
    if ($minPrice !== null) {
        $conditions[] = $priceAmountSql . ' >= :min_price';
        $params['min_price'] = $minPrice;
    }
    if ($maxPrice !== null) {
        $conditions[] = $priceAmountSql . ' <= :max_price';
        $params['max_price'] = $maxPrice;
    }
    if ($conditions) $sql .= ' WHERE ' . implode(' AND ', $conditions);
    $sql .= $hasStatus
        ? " ORDER BY (p.status = 'available') DESC, p.created_at DESC, p.id DESC"
        : ' ORDER BY p.created_at DESC, p.id DESC';

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $properties = array_map(static function (array $row): array {
        return [
            'id' => (int) $row['id'],
            'title' => $row['title'],
            'price' => $row['price'],
            'price_amount' => (float) $row['price_amount'],
            'property_type' => $row['property_type'],
            'property_type_label' => ucfirst($row['property_type']),
            'district' => $row['district'],
            'status' => $row['status'],
            'status_label' => propertyStatusLabel($row['status']),
            'image_url' => uploadUrl($row['image']),
            'details' => $row['details'],
            'image_count' => (int) $row['image_count'],
            'has_video' => (int) $row['video_count'] > 0,
            'detail_url' => 'property.php?id=' . (int) $row['id'],
            'enquiry_url' => 'https://wa.me/94751227606?text=' . rawurlencode('Hello, I am interested in: ' . $row['title']),
        ];
    }, $rows);

    echo json_encode([
        'ok' => true,
        'district' => $district,
        'property_type' => $propertyType,
        'min_price' => $minPrice,
        'max_price' => $maxPrice,
        'count' => count($properties),
        'properties' => $properties,
        // The site still works with an old table; importing update_database.sql
        // enables exact district/type metadata for existing records.
        'database_update_recommended' => !$hasDistrict || !$hasStatus || !$hasPriceAmount || !$hasPropertyType,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $exception) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => $exception->getMessage()]);
} catch (Throwable $exception) {
    http_response_code(500);
    error_log('Property API error: ' . $exception->getMessage());
    echo json_encode([
        'ok' => false,
        'code' => 'PROPERTY_API_ERROR',
        'message' => 'Could not connect to the property database. Confirm MySQL is running and db.php uses the correct database name.',
    ]);
}
