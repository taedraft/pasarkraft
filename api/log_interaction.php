<?php
session_start();
require '../db_connect.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'buyer') {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Login required']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = $_POST;
}

$product_id = intval($input['product_id'] ?? 0);
$interaction_type = trim((string) ($input['interaction_type'] ?? ''));
$interaction_value = isset($input['interaction_value']) ? floatval($input['interaction_value']) : 0.0;

$default_values = [
    'view' => 1.0,
    'click' => 2.0,
    'wishlist' => 3.0,
    'purchase' => 5.0,
];

if ($product_id <= 0 || !isset($default_values[$interaction_type])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid interaction payload']);
    exit();
}

if ($interaction_value <= 0) {
    $interaction_value = $default_values[$interaction_type];
}

$stmt = $conn->prepare("INSERT INTO user_interactions (user_id, product_id, interaction_type, interaction_value) VALUES (?, ?, ?, ?)");
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Failed to prepare log statement']);
    exit();
}

$user_id = intval($_SESSION['user_id']);
$stmt->bind_param('iisd', $user_id, $product_id, $interaction_type, $interaction_value);

if (!$stmt->execute()) {
    $stmt->close();
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Failed to save interaction']);
    exit();
}

$stmt->close();

echo json_encode(['status' => 'success']);