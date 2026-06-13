<?php
/**
 * Shared PHP bridge that executes recommender.py and returns decoded recommendations.
 */
require_once __DIR__ . '/db_connect.php';

function pk_parse_recommender_output(array $output)
{
    if (empty($output)) {
        return [];
    }

    $json = trim(implode("\n", $output));
    $payload = json_decode($json, true);
    if (is_array($payload) && !empty($payload['recommendations'])) {
        return $payload['recommendations'];
    }

    $start = strrpos($json, '{');
    if ($start !== false) {
        $payload = json_decode(substr($json, $start), true);
        if (is_array($payload) && !empty($payload['recommendations'])) {
            return $payload['recommendations'];
        }
    }

    return [];
}

function pk_run_python_recommender($userId, $limit, array $dbConfig = [])
{
    $python_bin = pk_env('PK_PYTHON_BIN', 'python');
    $script_path = __DIR__ . DIRECTORY_SEPARATOR . 'recommender.py';

    if (!function_exists('exec') || !file_exists($script_path)) {
        return [];
    }

    $host = $dbConfig['host'] ?? pk_env('PK_DB_HOST', '');
    $user = $dbConfig['user'] ?? pk_env('PK_DB_USER', '');
    $password = $dbConfig['password'] ?? pk_env('PK_DB_PASSWORD', '');
    $database = $dbConfig['database'] ?? pk_env('PK_DB_NAME', '');
    $api_base = trim((string) pk_env('PK_API_BASE', ''));
    $api_key = trim((string) pk_env('PK_API_KEY', ''));

    $cmd = escapeshellarg($python_bin) . ' ' . escapeshellarg($script_path)
        . ' --user-id ' . escapeshellarg((string) $userId)
        . ' --limit ' . escapeshellarg((string) $limit)
        . ' --write-db';

    if ($api_base !== '') {
        $cmd .= ' --api-base ' . escapeshellarg($api_base);
        if ($api_key !== '') {
            $cmd .= ' --api-key ' . escapeshellarg($api_key);
        }
    } else {
        if ($host === '' || $user === '' || $database === '') {
            return [];
        }
        $cmd .= ' --host ' . escapeshellarg($host)
            . ' --user ' . escapeshellarg($user)
            . ' --password ' . escapeshellarg($password)
            . ' --database ' . escapeshellarg($database);
    }

    $output = [];
    $exit_code = 1;
    @exec($cmd . ' 2>&1', $output, $exit_code);
    if ($exit_code !== 0) {
        return [];
    }

    return pk_parse_recommender_output($output);
}

function pk_fetch_recommended_products_by_ids($conn, $recommendations)
{
    if (empty($recommendations)) {
        return [];
    }

    $productIds = [];
    $scoresById = [];
    foreach ($recommendations as $item) {
        $pid = intval($item['product_id'] ?? 0);
        if ($pid <= 0) {
            continue;
        }
        $productIds[] = $pid;
        $scoresById[$pid] = floatval($item['score'] ?? 0);
    }

    if (empty($productIds)) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($productIds), '?'));
    $types = str_repeat('i', count($productIds));

    $sql = "SELECT p.id, p.seller_id, p.title, p.category, p.price, p.image_path, a.shopname
            FROM products p
            LEFT JOIN artisans a ON p.seller_id = a.user_id
            WHERE p.id IN ($placeholders) AND p.stock > 0";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }

    $stmt->bind_param($types, ...$productIds);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($row = $res->fetch_assoc()) {
        $pid = intval($row['id']);
        $row['score'] = $scoresById[$pid] ?? 0;
        $row['recommendation_type'] = 'hybrid_py';
        $rows[$pid] = $row;
    }
    $stmt->close();

    $ordered = [];
    foreach ($productIds as $pid) {
        if (isset($rows[$pid])) {
            $ordered[] = $rows[$pid];
        }
    }

    return $ordered;
}
