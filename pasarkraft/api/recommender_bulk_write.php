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
if (!$payload || !isset($payload['runs']) || !is_array($payload['runs'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid payload']);
    exit();
}

$rec_type = $payload['recommendation_type'] ?? 'hybrid_py_daily';
$runs = $payload['runs'];

$conn->begin_transaction();

try {
    $del_stmt = $conn->prepare("DELETE FROM recommendations WHERE user_id = ? AND recommendation_type = ?");
    $ins_stmt = $conn->prepare("INSERT INTO recommendations (user_id, product_id, score, recommendation_type) VALUES (?, ?, ?, ?)");

    $total_rows = 0;
    $user_count = 0;

    foreach ($runs as $run) {
        $user_id = (int) ($run['user_id'] ?? 0);
        $recommendations = $run['recommendations'] ?? [];

        if ($user_id <= 0 || !is_array($recommendations)) {
            continue;
        }

        $del_stmt->bind_param('is', $user_id, $rec_type);
        $del_stmt->execute();

        foreach ($recommendations as $rec) {
            $pid = (int) ($rec['product_id'] ?? 0);
            $score = (float) ($rec['score'] ?? 0);
            if ($pid <= 0) {
                continue;
            }
            $ins_stmt->bind_param('iids', $user_id, $pid, $score, $rec_type);
            $ins_stmt->execute();
            $total_rows++;
        }

        $user_count++;
    }

    $del_stmt->close();
    $ins_stmt->close();

    $conn->commit();

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'status' => 'ok',
        'recommendation_type' => $rec_type,
        'users_updated' => $user_count,
        'rows_written' => $total_rows,
    ]);
} catch (Throwable $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode([
        'error' => 'Failed to write recommendations',
        'details' => $e->getMessage(),
    ]);
}
