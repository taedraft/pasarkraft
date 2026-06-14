<?php
session_start();
require '../db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo "Forbidden - login as admin to generate export.";
    exit();
}

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

$payload = [
    'products' => $products,
    'interactions' => $interactions,
    'buyers' => $buyers,
    'generated_at' => date('c')
];

$filename = __DIR__ . '/../recommender_dump.json';
file_put_contents($filename, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

header('Content-Type: application/json; charset=utf-8');
header('Content-Disposition: attachment; filename="recommender_dump.json"');
echo json_encode(['status'=>'ok','file'=>basename($filename),'rows_products'=>count($products),'rows_interactions'=>count($interactions)]);

?>
