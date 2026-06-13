<?php
/**
 * PasarKraft AI Recommender Engine
 * Implements:
 * 1. TF-IDF feature extraction on product metadata.
 * 2. Cosine Similarity between TF-IDF product vectors.
 * 3. Matrix Factorization (SVD) with Stochastic Gradient Descent (SGD) for Collaborative Filtering.
 * 4. Hybrid Recommendation Fusion.
 * 5. Recommendations caching into the database.
 */

class PasarKraftRecommender {
    private $conn;
    
    // TF-IDF configuration
    private $vocabulary = [];
    private $idf = [];
    private $tfidfVectors = []; // [product_id => [term => value]]
    
    // SVD configuration & trained models
    private $latentFactors = 4; // K
    private $lr = 0.05; // Learning rate
    private $reg = 0.02; // Regularization
    private $epochs = 50;
    
    private $userMap = []; // [user_id => index]
    private $userMapRev = [];
    private $productMap = []; // [product_id => index]
    private $productMapRev = [];
    
    private $globalMean = 3.0;
    private $userBiases = [];
    private $productBiases = [];
    private $P = []; // User latent factors matrix
    private $Q = []; // Item latent factors matrix
    private $trainingMetrics = [];
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    // ==========================================
    // 1. TF-IDF & CONTENT-BASED FILTERING
    // ==========================================
    
    /**
     * Clean and tokenize a text string.
     */
    private function tokenize($text) {
        $text = strtolower($text);
        // Remove HTML and special characters
        $text = strip_tags($text);
        $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
        $words = preg_split('/[\s-]+/', $text);
        
        // Stopwords (English and Malay)
        $stopwords = [
            'the', 'and', 'with', 'for', 'this', 'that', 'from', 'your', 'made', 'with', 'fabric', 'design',
            'dan', 'dengan', 'untuk', 'yang', 'ini', 'itu', 'dari', 'pada', 'adalah', 'sebagai', 'oleh',
            'a', 'an', 'of', 'in', 'on', 'at', 'or', 'but', 'is', 'are', 'was', 'were', 'be', 'been', 'to'
        ];
        
        $filteredWords = [];
        foreach ($words as $w) {
            $w = trim($w);
            if (strlen($w) > 2 && !in_array($w, $stopwords)) {
                $filteredWords[] = $w;
            }
        }
        return $filteredWords;
    }
    
    /**
     * Extract TF-IDF vectors for all active products.
     */
    public function trainTfidf() {
        $this->vocabulary = [];
        $this->idf = [];
        $this->tfidfVectors = [];
        
        // Fetch all products
        $res = $this->conn->query("SELECT id, title, description, category, subcategory, technique, color, material, style, tags FROM products");
        $documents = [];
        $docTermCounts = [];
        
        while ($row = $res->fetch_assoc()) {
            $pid = intval($row['id']);
            // Concatenate all metadata into a single text corpus
            $corpus = implode(' ', [
                $row['title'],
                $row['description'],
                $row['category'],
                $row['subcategory'],
                $row['technique'],
                $row['color'],
                $row['material'],
                $row['style'],
                $row['tags']
            ]);
            
            $tokens = $this->tokenize($corpus);
            $documents[$pid] = $tokens;
            
            // Term count in this document for TF
            $counts = array_count_values($tokens);
            $docTermCounts[$pid] = $counts;
            
            // Build vocabulary
            foreach (array_keys($counts) as $term) {
                $this->vocabulary[$term] = true;
            }
        }
        
        $totalDocs = count($documents);
        if ($totalDocs === 0) return;
        
        // Calculate DF (Document Frequency) for IDF
        $df = [];
        foreach ($this->vocabulary as $term => $_) {
            $df[$term] = 0;
            foreach ($documents as $pid => $tokens) {
                if (isset($docTermCounts[$pid][$term])) {
                    $df[$term]++;
                }
            }
            // IDF = ln(Total Docs / (DF + 1)) + 1
            $this->idf[$term] = log($totalDocs / ($df[$term] + 1)) + 1;
        }
        
        // Calculate TF-IDF vectors
        foreach ($documents as $pid => $tokens) {
            $vector = [];
            $totalTerms = count($tokens);
            if ($totalTerms === 0) continue;
            
            foreach ($docTermCounts[$pid] as $term => $count) {
                $tf = $count / $totalTerms;
                $vector[$term] = $tf * $this->idf[$term];
            }
            
            // L2 normalization of vectors
            $sumSq = 0;
            foreach ($vector as $val) {
                $sumSq += $val * $val;
            }
            $norm = sqrt($sumSq);
            
            if ($norm > 0) {
                foreach ($vector as $term => $val) {
                    $vector[$term] = $val / $norm;
                }
            }
            
            $this->tfidfVectors[$pid] = $vector;
        }
    }
    
    /**
     * Compute cosine similarity between two product vectors.
     */
    public static function calculateCosineSimilarity($vecA, $vecB) {
        if (empty($vecA) || empty($vecB)) return 0.0;
        
        $dotProduct = 0.0;
        $normASq = 0.0;
        $normBSq = 0.0;
        
        // Compute norms
        foreach ($vecA as $val) $normASq += $val * $val;
        foreach ($vecB as $val) $normBSq += $val * $val;
        
        if ($normASq == 0 || $normBSq == 0) return 0.0;
        
        // Compute dot product
        foreach ($vecA as $term => $valA) {
            if (isset($vecB[$term])) {
                $dotProduct += $valA * $vecB[$term];
            }
        }
        
        return $dotProduct / (sqrt($normASq) * sqrt($normBSq));
    }
    
    /**
     * Get similar products using TF-IDF and Cosine Similarity.
     */
    public function getSimilarProducts($productId, $limit = 5) {
        if (!isset($this->tfidfVectors[$productId])) {
            $this->trainTfidf(); // Ensure trained
        }
        
        if (!isset($this->tfidfVectors[$productId])) return [];
        
        $targetVec = $this->tfidfVectors[$productId];
        $similarities = [];
        
        foreach ($this->tfidfVectors as $pid => $vec) {
            if ($pid === $productId) continue;
            $sim = self::calculateCosineSimilarity($targetVec, $vec);
            if ($sim > 0.05) {
                $similarities[$pid] = $sim;
            }
        }
        
        arsort($similarities);
        return array_slice($similarities, 0, $limit, true);
    }
    
    // ==========================================
    // 2. COLLABORATIVE FILTERING (SVD MODEL)
    // ==========================================
    
    /**
     * Train SVD Matrix Factorization on user interactions.
     */
    public function trainSVD() {
        $this->userMap = [];
        $this->userMapRev = [];
        $this->productMap = [];
        $this->productMapRev = [];
        $this->trainingMetrics = [];
        
        // Pull all user interactions
        $interactionsRes = $this->conn->query("SELECT user_id, product_id, interaction_value FROM user_interactions");
        $interactions = [];
        $ratingsSum = 0;
        
        while ($row = $interactionsRes->fetch_assoc()) {
            $uid = intval($row['user_id']);
            $pid = intval($row['product_id']);
            $val = floatval($row['interaction_value']);
            
            // Map IDs to indexes
            if (!isset($this->userMap[$uid])) {
                $idx = count($this->userMap);
                $this->userMap[$uid] = $idx;
                $this->userMapRev[$idx] = $uid;
            }
            if (!isset($this->productMap[$pid])) {
                $idx = count($this->productMap);
                $this->productMap[$pid] = $idx;
                $this->productMapRev[$idx] = $pid;
            }
            
            $interactions[] = [
                'u' => $this->userMap[$uid],
                'i' => $this->productMap[$pid],
                'rating' => $val
            ];
            $ratingsSum += $val;
        }
        
        $numInteractions = count($interactions);
        if ($numInteractions === 0) {
            $this->globalMean = 3.0;
            return false;
        }
        
        $this->globalMean = $ratingsSum / $numInteractions;
        $numUsers = count($this->userMap);
        $numProducts = count($this->productMap);
        
        // Initialize user/product biases and P/Q matrices
        $this->userBiases = array_fill(0, $numUsers, 0.0);
        $this->productBiases = array_fill(0, $numProducts, 0.0);
        
        $this->P = [];
        $this->Q = [];
        
        $initRange = sqrt($this->globalMean / $this->latentFactors);
        
        for ($u = 0; $u < $numUsers; $u++) {
            $this->P[$u] = [];
            for ($f = 0; $f < $this->latentFactors; $f++) {
                // Initialize randomly around 0.1
                $this->P[$u][$f] = (mt_rand() / mt_getrandmax()) * $initRange * 0.5;
            }
        }
        
        for ($i = 0; $i < $numProducts; $i++) {
            $this->Q[$i] = [];
            for ($f = 0; $f < $this->latentFactors; $f++) {
                $this->Q[$i][$f] = (mt_rand() / mt_getrandmax()) * $initRange * 0.5;
            }
        }
        
        // Stochastic Gradient Descent Loop
        for ($epoch = 1; $epoch <= $this->epochs; $epoch++) {
            shuffle($interactions);
            $se = 0.0; // Sum of squared errors
            
            foreach ($interactions as $inter) {
                $u = $inter['u'];
                $i = $inter['i'];
                $rating = $inter['rating'];
                
                // Prediction = Mean + b_u + b_i + P_u . Q_i
                $pred = $this->globalMean + $this->userBiases[$u] + $this->productBiases[$i];
                for ($f = 0; $f < $this->latentFactors; $f++) {
                    $pred += $this->P[$u][$f] * $this->Q[$i][$f];
                }
                
                $err = $rating - $pred;
                $se += $err * $err;
                
                // Update biases
                $this->userBiases[$u] += $this->lr * ($err - $this->reg * $this->userBiases[$u]);
                $this->productBiases[$i] += $this->lr * ($err - $this->reg * $this->productBiases[$i]);
                
                // Update latent factors P and Q
                for ($f = 0; $f < $this->latentFactors; $f++) {
                    $p_old = $this->P[$u][$f];
                    $q_old = $this->Q[$i][$f];
                    
                    $this->P[$u][$f] += $this->lr * ($err * $q_old - $this->reg * $p_old);
                    $this->Q[$i][$f] += $this->lr * ($err * $p_old - $this->reg * $q_old);
                }
            }
            
            $rmse = sqrt($se / $numInteractions);
            $this->trainingMetrics[$epoch] = $rmse;
        }
        return true;
    }
    
    /**
     * Get predicted collaborative score for raw user and product IDs.
     */
    public function predictSVDScore($userId, $productId) {
        // Cold start checks
        if (!isset($this->userMap[$userId]) || !isset($this->productMap[$productId])) {
            return $this->globalMean;
        }
        
        $u = $this->userMap[$userId];
        $i = $this->productMap[$productId];
        
        $pred = $this->globalMean + $this->userBiases[$u] + $this->productBiases[$i];
        for ($f = 0; $f < $this->latentFactors; $f++) {
            $pred += $this->P[$u][$f] * $this->Q[$i][$f];
        }
        
        // Clamp prediction within interaction score range (1 to 5)
        return max(1.0, min(5.0, $pred));
    }
    
    // ==========================================
    // 3. HYBRID RECOMMENDATION GENERATION
    // ==========================================
    
    /**
     * Build User Content Profile Vector by averaging TF-IDF vectors of products
     * they interacted with (weighted by interaction values).
     */
    private function getUserContentProfile($userId) {
        $profile = [];
        $weightSum = 0.0;
        
        // Query user's interactions with a score >= 2 (click/wishlist/purchase)
        $stmt = $this->conn->prepare("SELECT product_id, interaction_value FROM user_interactions WHERE user_id = ? AND interaction_value >= 2.0");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        
        while ($row = $res->fetch_assoc()) {
            $pid = intval($row['product_id']);
            $val = floatval($row['interaction_value']);
            
            if (isset($this->tfidfVectors[$pid])) {
                $weightSum += $val;
                foreach ($this->tfidfVectors[$pid] as $term => $tfidfVal) {
                    if (!isset($profile[$term])) {
                        $profile[$term] = 0.0;
                    }
                    $profile[$term] += $tfidfVal * $val;
                }
            }
        }
        $stmt->close();
        
        // Normalize user profile vector
        if ($weightSum > 0) {
            foreach ($profile as $term => $val) {
                $profile[$term] = $val / $weightSum;
            }
        }
        
        return $profile;
    }
    
    /**
     * Generate dynamic Hybrid Recommendations for a single user.
     */
    public function getRecommendationsForUser($userId, $limit = 6) {
        // 1. Train models if empty
        if (empty($this->tfidfVectors)) {
            $this->trainTfidf();
        }
        if (empty($this->P)) {
            $this->trainSVD();
        }
        
        // 2. Fetch all active products
        $prodRes = $this->conn->query("SELECT id FROM products WHERE stock > 0");
        $productsList = [];
        while ($row = $prodRes->fetch_assoc()) {
            $productsList[] = intval($row['id']);
        }
        
        // 3. Fetch products user has already bought or wishes to skip
        $boughtRes = $this->conn->query("
            SELECT product_id FROM user_interactions WHERE user_id = $userId AND interaction_type = 'purchase'
            UNION
            SELECT product_id FROM wishlist WHERE buyer_id = $userId
        ");
        $excludeIds = [];
        while ($row = $boughtRes->fetch_assoc()) {
            $excludeIds[] = intval($row['product_id']);
        }
        
        // 4. Build user content preference profile
        $userProfile = $this->getUserContentProfile($userId);
        $hasHistory = !empty($userProfile);
        
        // Calculate count of interactions to adapt SVD weight (alpha)
        $cntStmt = $this->conn->prepare("SELECT COUNT(*) as count FROM user_interactions WHERE user_id = ?");
        $cntStmt->bind_param("i", $userId);
        $cntStmt->execute();
        $numInteractions = $cntStmt->get_result()->fetch_assoc()['count'];
        $cntStmt->close();
        
        // Dynamic Alpha: More interactions -> higher weight to SVD (collaborative)
        // Cold start -> alpha = 0 (pure content)
        if ($numInteractions == 0) {
            $alpha = 0.0;
        } elseif ($numInteractions < 3) {
            $alpha = 0.25;
        } elseif ($numInteractions < 8) {
            $alpha = 0.50;
        } else {
            $alpha = 0.70;
        }
        
        // PERF FIX: Pre-aggregate all product popularity scores in a SINGLE query.
        // Previously, cold-start users triggered one SELECT AVG() per product (N+1 pattern),
        // which could fire 50–100+ individual queries inside the loop below.
        $popularityMap = [];
        if (!$hasHistory) {
            $popRes = $this->conn->query(
                "SELECT product_id, AVG(interaction_value) as avg_val FROM user_interactions GROUP BY product_id"
            );
            while ($popRow = $popRes->fetch_assoc()) {
                $popularityMap[intval($popRow['product_id'])] = floatval($popRow['avg_val']) / 5.0;
            }
        }

        $scores = [];
        foreach ($productsList as $pid) {
            if (in_array($pid, $excludeIds)) continue; // Skip owned/wishlisted items
            
            // A. Content similarity (0 to 1)
            $contentScore = 0.0;
            if ($hasHistory && isset($this->tfidfVectors[$pid])) {
                $contentScore = self::calculateCosineSimilarity($userProfile, $this->tfidfVectors[$pid]);
            }
            
            // B. Collaborative SVD score (predicted rating 1..5)
            $svdPrediction = $this->predictSVDScore($userId, $pid);
            // Normalize SVD to a 0..1 scale
            $svdNormalized = ($svdPrediction - 1.0) / 4.0;
            
            // C. Hybrid calculation
            if (!$hasHistory) {
                // Cold start: use pre-fetched popularity map (single query above, not N queries)
                $popularity = isset($popularityMap[$pid]) ? $popularityMap[$pid] : 0.2;
                
                $hybridScore = $popularity;
                $type = 'popularity';
            } else {
                $hybridScore = ($alpha * $svdNormalized) + ((1.0 - $alpha) * $contentScore);
                $type = 'hybrid';
            }
            
            $scores[$pid] = [
                'score' => $hybridScore,
                'content_score' => $contentScore,
                'svd_score' => $svdPrediction,
                'type' => $type
            ];
        }
        
        // Sort descending
        uasort($scores, function($a, $b) {
            return $b['score'] <=> $a['score'];
        });
        
        return array_slice($scores, 0, $limit, true);
    }
    
    /**
     * Train models, generate recommendations for ALL active buyers, and write
     * directly into the caching tables.
     */
    public function generateAndCacheAllRecommendations() {
        // 1. Train the pipelines
        $this->trainTfidf();
        $this->trainSVD();
        
        // 2. Fetch all buyers
        $buyersRes = $this->conn->query("SELECT id FROM users WHERE role = 'buyer'");
        $numUpdated = 0;
        
        while ($row = $buyersRes->fetch_assoc()) {
            $uid = intval($row['id']);
            $recs = $this->getRecommendationsForUser($uid, 6);
            
            if (empty($recs)) continue;
            
            // Clear existing recommendations for this user
            $this->conn->query("DELETE FROM recommendations WHERE user_id = $uid");
            
            // Cache new ones
            $stmt = $this->conn->prepare("INSERT INTO recommendations (user_id, product_id, score, recommendation_type) VALUES (?, ?, ?, ?)");
            foreach ($recs as $pid => $data) {
                $stmt->bind_param("iids", $uid, $pid, $data['score'], $data['type']);
                $stmt->execute();
            }
            $stmt->close();
            $numUpdated++;
        }
        
        return [
            'users_updated' => $numUpdated,
            'vocabulary_size' => count($this->vocabulary),
            'final_rmse' => !empty($this->trainingMetrics) ? end($this->trainingMetrics) : 0.0,
            'epochs' => count($this->trainingMetrics)
        ];
    }
    
    // Getters for Admin diagnostics
    public function getVocabulary() { return array_keys($this->vocabulary); }
    public function getTfidfVectors() { return $this->tfidfVectors; }
    public function getUserMap() { return $this->userMap; }
    public function getProductMap() { return $this->productMap; }
    public function getSVDMatrices() {
        return [
            'P' => $this->P,
            'Q' => $this->Q,
            'userBiases' => $this->userBiases,
            'productBiases' => $this->productBiases,
            'globalMean' => $this->globalMean
        ];
    }
    public function getTrainingMetrics() { return $this->trainingMetrics; }
}
