<?php
require '../db_connect.php';

$api_key = $_GET['key'] ?? ($_SERVER['HTTP_X_API_KEY'] ?? '');
$expected_key = 'pk_4R9q8mW7vT2xK5nH1sL6cQ3yB8dZ0eU';

if ($expected_key !== '' && $api_key !== $expected_key) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

header('Content-Type: application/json; charset=utf-8');

$products = [];
$product_sql = "SELECT id, title, description, category, subcategory, technique, color, material, style, tags, stock FROM products";
if ($prod_res = $conn->query($product_sql)) {
    while ($row = $prod_res->fetch_assoc()) {
        $products[] = $row;
    }
}

$interactions = [];
$inter_sql = "SELECT user_id, product_id, interaction_value FROM user_interactions";
if ($int_res = $conn->query($inter_sql)) {
    while ($row = $int_res->fetch_assoc()) {
        $interactions[] = $row;
    }
}

$buyers = [];
$buyer_sql = "SELECT id FROM users WHERE role = 'buyer'";
if ($buyer_res = $conn->query($buyer_sql)) {
    while ($row = $buyer_res->fetch_assoc()) {
        $buyers[] = (int) $row['id'];
    }
}

echo json_encode([
    'products' => $products,
    'interactions' => $interactions,
    'buyers' => $buyers
]);
