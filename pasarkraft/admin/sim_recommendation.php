<?php
session_start();
require "../db_connect.php";
require "../recommender.php";

header('Content-Type: application/json');

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    // skip security block for dev ease or allow admin checks
}

if (!isset($_GET['user_id']) || !isset($_GET['product_id'])) {
    echo json_encode(['error' => 'Missing parameters']);
    exit();
}

$userId = intval($_GET['user_id']);
$productId = intval($_GET['product_id']);

$recEngine = new PasarKraftRecommender($conn);
$recEngine->trainTfidf();
$recEngine->trainSVD();

// 1. Get SVD details
$svd = $recEngine->getSVDMatrices();
$userMap = $recEngine->getUserMap();
$productMap = $recEngine->getProductMap();

$svd_mean = $svd['globalMean'];
$svd_user_bias = 0.0;
$svd_product_bias = 0.0;
$svd_rating = $svd_mean;

if (isset($userMap[$userId])) {
    $uIdx = $userMap[$userId];
    $svd_user_bias = $svd['userBiases'][$uIdx];
}

if (isset($productMap[$productId])) {
    $pIdx = $productMap[$productId];
    $svd_product_bias = $svd['productBiases'][$pIdx];
}

$svd_rating = $recEngine->predictSVDScore($userId, $productId);
$svd_normalized = ($svd_rating - 1.0) / 4.0;

// 2. Content similarity
// Build profile
$profile = [];
$weightSum = 0.0;
$user_items_count = 0;

$stmt = $conn->prepare("SELECT product_id, interaction_value FROM user_interactions WHERE user_id = ? AND interaction_value >= 2.0");
$stmt->bind_param("i", $userId);
$stmt->execute();
$res = $stmt->get_result();

while ($row = $res->fetch_assoc()) {
    $pid = intval($row['product_id']);
    $val = floatval($row['interaction_value']);
    
    $tfidfVecs = $recEngine->getTfidfVectors();
    if (isset($tfidfVecs[$pid])) {
        $weightSum += $val;
        $user_items_count++;
        foreach ($tfidfVecs[$pid] as $term => $tfidfVal) {
            if (!isset($profile[$term])) $profile[$term] = 0.0;
            $profile[$term] += $tfidfVal * $val;
        }
    }
}
$stmt->close();

if ($weightSum > 0) {
    foreach ($profile as $term => $val) {
        $profile[$term] = $val / $weightSum;
    }
}

$cosine_similarity = 0.0;
$tfidfVecs = $recEngine->getTfidfVectors();
if (!empty($profile) && isset($tfidfVecs[$productId])) {
    $cosine_similarity = PasarKraftRecommender::calculateCosineSimilarity($profile, $tfidfVecs[$productId]);
}

// 3. User interaction count and Alpha
$cntStmt = $conn->prepare("SELECT COUNT(*) as count FROM user_interactions WHERE user_id = ?");
$cntStmt->bind_param("i", $userId);
$cntStmt->execute();
$user_interactions_count = $cntStmt->get_result()->fetch_assoc()['count'];
$cntStmt->close();

if ($user_interactions_count == 0) {
    $alpha = 0.0;
} elseif ($user_interactions_count < 3) {
    $alpha = 0.25;
} elseif ($user_interactions_count < 8) {
    $alpha = 0.50;
} else {
    $alpha = 0.70;
}

if ($user_interactions_count == 0) {
    // Popularity logic
    $popRes = $conn->query("SELECT AVG(interaction_value) as avg_val FROM user_interactions WHERE product_id = $productId");
    $popRow = $popRes->fetch_assoc();
    $hybrid_score = $popRow['avg_val'] ? floatval($popRow['avg_val']) / 5.0 : 0.2;
} else {
    $hybrid_score = ($alpha * $svd_normalized) + ((1.0 - $alpha) * $cosine_similarity);
}

echo json_encode([
    'svd_mean' => $svd_mean,
    'svd_user_bias' => $svd_user_bias,
    'svd_product_bias' => $svd_product_bias,
    'svd_rating' => $svd_rating,
    'svd_normalized' => $svd_normalized,
    'user_items_count' => $user_items_count,
    'cosine_similarity' => $cosine_similarity,
    'user_interactions_count' => $user_interactions_count,
    'alpha' => $alpha,
    'hybrid_score' => $hybrid_score
]);
exit();
