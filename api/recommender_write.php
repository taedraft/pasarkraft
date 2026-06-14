<?php
require '../db_connect.php';

$api_key = $_GET['key'] ?? ($_SERVER['HTTP_X_API_KEY'] ?? '');
$expected_key = 'pk_4R9q8mW7vT2xK5nH1sL6cQ3yB8dZ0eU';

if ($expected_key !== '' && $api_key !== $expected_key) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

$payload = json_decode(file_get_contents('php://input'), true);
if (!$payload || !isset($payload['user_id']) || !isset($payload['recommendations'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid payload']);
    exit();
}

$user_id = (int) $payload['user_id'];
$recommendations = $payload['recommendations'];
$rec_type = $payload['recommendation_type'] ?? 'hybrid_py';

$del_stmt = $conn->prepare("DELETE FROM recommendations WHERE user_id = ? AND recommendation_type = ?");
$del_stmt->bind_param('is', $user_id, $rec_type);
$del_stmt->execute();
$del_stmt->close();

$ins_stmt = $conn->prepare("INSERT INTO recommendations (user_id, product_id, score, recommendation_type) VALUES (?, ?, ?, ?)");
foreach ($recommendations as $rec) {
    $pid = (int) ($rec['product_id'] ?? 0);
    $score = (float) ($rec['score'] ?? 0);
    if ($pid <= 0) {
        continue;
    }
    $ins_stmt->bind_param('iids', $user_id, $pid, $score, $rec_type);
    $ins_stmt->execute();
}
$ins_stmt->close();

header('Content-Type: application/json; charset=utf-8');
echo json_encode(['status' => 'ok', 'user_id' => $user_id, 'count' => count($recommendations)]);
