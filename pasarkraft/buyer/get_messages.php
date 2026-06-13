<?php
session_start();
header('Content-Type: application/json');
require '../db_connect.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'unauthorized', 'messages' => [], 'unread_count' => 0]);
    exit;
}

$user_id    = intval($_SESSION['user_id']);
$inquiry_id = intval($_GET['inquiry_id'] ?? 0);
$after_id   = intval($_GET['after_id']   ?? 0);

if ($inquiry_id <= 0) {
    echo json_encode(['messages' => [], 'unread_count' => 0]);
    exit;
}

// Verify caller is buyer OR seller of this inquiry
$chk = $conn->prepare("SELECT id FROM inquiries WHERE id = ? AND (buyer_id = ? OR seller_id = ?)");
$chk->bind_param("iii", $inquiry_id, $user_id, $user_id);
$chk->execute();
if ($chk->get_result()->num_rows === 0) {
    echo json_encode(['error' => 'forbidden', 'messages' => [], 'unread_count' => 0]);
    exit;
}
$chk->close();

// Mark newly arrived messages addressed to this user as read
$upd = $conn->prepare("UPDATE messages SET is_read = 1 WHERE inquiry_id = ? AND receiver_id = ? AND is_read = 0");
$upd->bind_param("ii", $inquiry_id, $user_id);
$upd->execute();
$upd->close();

// Fetch only new messages (id > after_id)
$stmt = $conn->prepare(
    "SELECT m.id, m.sender_id, m.message, m.is_offer, m.offer_amount, m.created_at,
            u.firstname, u.lastname
     FROM messages m
     JOIN users u ON m.sender_id = u.id
     WHERE m.inquiry_id = ? AND m.id > ?
     ORDER BY m.created_at ASC"
);
$stmt->bind_param("ii", $inquiry_id, $after_id);
$stmt->execute();
$res = $stmt->get_result();
$new_messages = [];
while ($row = $res->fetch_assoc()) {
    $new_messages[] = $row;
}
$stmt->close();

// Total unread count across ALL inquiries (for the nav badge)
$unread_stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM messages WHERE receiver_id = ? AND is_read = 0");
$unread_stmt->bind_param("i", $user_id);
$unread_stmt->execute();
$unread_row   = $unread_stmt->get_result()->fetch_assoc();
$unread_count = intval($unread_row['cnt'] ?? 0);
$unread_stmt->close();

echo json_encode([
    'messages'     => $new_messages,
    'unread_count' => $unread_count,
]);
