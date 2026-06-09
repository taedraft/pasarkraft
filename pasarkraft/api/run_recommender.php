<?php
/**
 * Local API endpoint: runs recommender.py for the logged-in buyer and returns JSON.
 */
session_start();
require '../db_connect.php';
require '../recommender_bridge.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'buyer') {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Login required']);
    exit();
}

$limit = intval($_GET['limit'] ?? $_POST['limit'] ?? 8);
if ($limit < 1) {
    $limit = 8;
}
if ($limit > 20) {
    $limit = 20;
}

$user_id = intval($_SESSION['user_id']);
$recommendations = pk_run_python_recommender($user_id, $limit, [
    'host' => $servername,
    'user' => $username,
    'password' => $password,
    'database' => $dbname,
]);

if (empty($recommendations)) {
    http_response_code(503);
    echo json_encode([
        'status' => 'error',
        'message' => 'Python recommender unavailable. Ensure PK_PYTHON_BIN, dependencies, and DB/API credentials are configured.',
        'recommendations' => [],
    ]);
    exit();
}

$products = pk_fetch_recommended_products_by_ids($conn, $recommendations);

echo json_encode([
    'status' => 'success',
    'source' => 'hybrid_py',
    'user_id' => $user_id,
    'recommendations' => $recommendations,
    'products' => $products,
]);
