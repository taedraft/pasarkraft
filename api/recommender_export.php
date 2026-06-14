<?php
require '../db_connect.php';

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

echo json_encode([
    'products' => $products,
    'interactions' => $interactions
]);
