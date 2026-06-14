<?php
/**
 * get_cosine_matrix.php — On-demand TF-IDF & Cosine Similarity AJAX Endpoint
 *
 * Called by admin_recommender.php via fetch() only when the user first clicks
 * the "TF-IDF & Cosine Similarity" tab. This prevents the O(N²) cosine matrix
 * computation from blocking the main page load (which was causing "Page Unresponsive").
 */
session_start();
require "../db_connect.php";
require "../recommender.php";

header('Content-Type: application/json; charset=utf-8');

// Only allow admin
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Cap products at 25 to prevent timeout on shared hosting.
// O(N²) cosine similarity: 25 products = 625 pairs (fast), 100 products = 10,000 pairs (slow).
$PRODUCT_LIMIT = 25;
$VOCAB_DISPLAY_LIMIT = 200; // Limit vocab tags displayed in UI

try {
    $recommender = new PasarKraftRecommender($conn);
    $recommender->trainTfidf();

    $tfidf = $recommender->getTfidfVectors();
    $vocab = $recommender->getVocabulary();

    // Fetch total product count for "showing X of Y" note
    $totalCountRes = $conn->query("SELECT COUNT(*) as cnt FROM products");
    $totalProducts = intval($totalCountRes->fetch_assoc()['cnt']);

    // Fetch the capped product list for the matrix
    $productsRes = $conn->query("SELECT id, title, category FROM products LIMIT $PRODUCT_LIMIT");
    $products = [];
    while ($row = $productsRes->fetch_assoc()) {
        $products[] = $row;
    }

    // Compute N×N cosine similarity matrix for the fetched products
    $matrix = [];
    foreach ($products as $p1) {
        $row = [];
        $v1 = isset($tfidf[$p1['id']]) ? $tfidf[$p1['id']] : [];
        foreach ($products as $p2) {
            if ($p1['id'] === $p2['id']) {
                $row[] = 1.0;
            } else {
                $v2 = isset($tfidf[$p2['id']]) ? $tfidf[$p2['id']] : [];
                $row[] = round(PasarKraftRecommender::calculateCosineSimilarity($v1, $v2), 4);
            }
        }
        $matrix[] = $row;
    }

    echo json_encode([
        'products'       => $products,
        'vocab'          => array_slice($vocab, 0, $VOCAB_DISPLAY_LIMIT),
        'vocab_total'    => count($vocab),
        'matrix'         => $matrix,
        'total_products' => $totalProducts,
        'shown_products' => count($products),
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
