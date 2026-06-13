<?php
session_start();
require '../db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'buyer') {
    echo json_encode(['status' => 'error', 'message' => 'Please login as a buyer to save items.']);
    exit();
}

$buyer_id = $_SESSION['user_id'];
$data = json_decode(file_get_contents('php://input'), true);

if (isset($data['product_id'])) {
    $product_id = intval($data['product_id']);
    
    // Check if already in wishlist
    $check = $conn->prepare("SELECT id FROM wishlist WHERE buyer_id = ? AND product_id = ?");
    $check->bind_param("ii", $buyer_id, $product_id);
    $check->execute();
    $result = $check->get_result();
    
    if ($result->num_rows > 0) {
        $del = $conn->prepare("DELETE FROM wishlist WHERE buyer_id = ? AND product_id = ?");
        $del->bind_param("ii", $buyer_id, $product_id);
        if($del->execute()){
            // Remove from user_interactions as well
            $del_int = $conn->prepare("DELETE FROM user_interactions WHERE user_id = ? AND product_id = ? AND interaction_type = 'wishlist'");
            $del_int->bind_param("ii", $buyer_id, $product_id);
            $del_int->execute();
            $del_int->close();
            
            echo json_encode(['status' => 'success', 'action' => 'removed']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Database error']);
        }
    } else {
        $add = $conn->prepare("INSERT INTO wishlist (buyer_id, product_id) VALUES (?, ?)");
        $add->bind_param("ii", $buyer_id, $product_id);
        if($add->execute()){
            // Add to user_interactions (Wishlist value = 3.0)
            $add_int = $conn->prepare("INSERT INTO user_interactions (user_id, product_id, interaction_type, interaction_value) VALUES (?, ?, 'wishlist', 3.0)");
            $add_int->bind_param("ii", $buyer_id, $product_id);
            $add_int->execute();
            $add_int->close();
            
            echo json_encode(['status' => 'success', 'action' => 'added']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Database error']);
        }
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid product.']);
}
?>
