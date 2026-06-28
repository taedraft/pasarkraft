<?php
session_start();
require "../db_connect.php";
require "../recommender.php";

// Redirect if not admin
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("Location: login_admin.php");
    exit();
}

$recommender = new PasarKraftRecommender($conn);

$msg = "";
$msg_type = "";
$metrics = null;

// Handle Training Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'train') {
        try {
            $metrics = $recommender->generateAndCacheAllRecommendations();
            $_SESSION['training_metrics'] = $metrics;
            $_SESSION['training_metrics']['trained_at'] = date('d M Y, H:i');
            $msg = "AI Recommender pipeline trained successfully! Recommendations updated for {$metrics['users_updated']} buyers.";
            $msg_type = "success";
        } catch (Exception $e) {
            $msg = "Error during training: " . $e->getMessage();
            $msg_type = "error";
        }
    } elseif ($_POST['action'] === 'clear') {
        $conn->query("DELETE FROM recommendations");
        $conn->query("DELETE FROM user_interactions"); // clear interaction logs if requested
        $msg = "Recommender caching and interaction logs cleared.";
        $msg_type = "success";
    } elseif ($_POST['action'] === 'seed_mock') {
        // Safe Seeder Trigger in case they have a completely clean DB and want to see the recommender immediately
        // Insert Buyers
        $pass = password_hash('password123', PASSWORD_BCRYPT);
        $conn->query("INSERT INTO users (firstname, lastname, username, email, password, role) 
                      SELECT 'John', 'Doe', 'buyer1', 'buyer1@kraft.com', '$pass', 'buyer' 
                      WHERE NOT EXISTS (SELECT 1 FROM users WHERE username = 'buyer1')");
        $conn->query("INSERT INTO users (firstname, lastname, username, email, password, role) 
                      SELECT 'Jane', 'Smith', 'buyer2', 'buyer2@kraft.com', '$pass', 'buyer' 
                      WHERE NOT EXISTS (SELECT 1 FROM users WHERE username = 'buyer2')");
        $conn->query("INSERT INTO users (firstname, lastname, username, email, password, role) 
                      SELECT 'Ahmad', 'Kassim', 'buyer3', 'buyer3@kraft.com', '$pass', 'buyer' 
                      WHERE NOT EXISTS (SELECT 1 FROM users WHERE username = 'buyer3')");
        $conn->query("INSERT INTO users (firstname, lastname, username, email, password, role) 
                      SELECT 'Siti', 'Aminah', 'buyer4', 'buyer4@kraft.com', '$pass', 'buyer' 
                      WHERE NOT EXISTS (SELECT 1 FROM users WHERE username = 'buyer4')");
                      
        // Get buyer IDs
        $buyer_ids = [];
        $res = $conn->query("SELECT id, username FROM users WHERE role = 'buyer'");
        while($r = $res->fetch_assoc()) $buyer_ids[$r['username']] = $r['id'];

        // Find existing products or insert standard ones
        $prod_res = $conn->query("SELECT id, title FROM products LIMIT 8");
        $prods = [];
        while($r = $prod_res->fetch_assoc()) $prods[$r['title']] = $r['id'];
        
        if (count($prods) >= 4) {
            $titles = array_keys($prods);
            $interactions = [
                ['buyer1', $titles[0], 'purchase', 5.0],
                ['buyer1', $titles[1], 'wishlist', 3.0],
                ['buyer1', $titles[2], 'click', 2.0],
                ['buyer2', $titles[2], 'purchase', 5.0],
                ['buyer2', $titles[3], 'wishlist', 3.0],
                ['buyer3', $titles[0], 'wishlist', 3.0],
                ['buyer3', $titles[1], 'click', 2.0],
                ['buyer4', $titles[3], 'purchase', 5.0],
            ];
            
            $conn->query("DELETE FROM user_interactions");
            foreach ($interactions as $i) {
                if (isset($buyer_ids[$i[0]]) && isset($prods[$i[1]])) {
                    $uid = $buyer_ids[$i[0]];
                    $pid = $prods[$i[1]];
                    $conn->query("INSERT INTO user_interactions (user_id, product_id, interaction_type, interaction_value) VALUES ($uid, $pid, '{$i[2]}', {$i[3]})");
                }
            }
            $msg = "Seeded 4 buyers and interaction log metrics successfully!";
            $msg_type = "success";
        } else {
            $msg = "Please add at least 4 products in your database first before seeding mock interactions.";
            $msg_type = "error";
        }
    }
}

// Fetch active metrics from session if exists
if (isset($_SESSION['training_metrics'])) {
    $metrics = $_SESSION['training_metrics'];
}

// PERF FIX: trainTfidf() has been removed from page load.
// It ran expensive O(V×D) IDF computation + O(N²) cosine similarity on every request,
// causing "Page Unresponsive". TF-IDF data is now loaded lazily via AJAX
// (get_cosine_matrix.php) only when the user clicks the TF-IDF tab.
$svd = $recommender->getSVDMatrices();
$userMap = $recommender->getUserMap();
$productMap = $recommender->getProductMap();

// Lightweight KPI data — direct DB queries only, no heavy in-memory computation
$productCountRes = $conn->query("SELECT COUNT(*) as cnt FROM products");
$productCount = intval($productCountRes->fetch_assoc()['cnt']);
// Vocabulary size is persisted in session after each "Train AI Pipeline" run
$cachedVocabSize = isset($_SESSION['training_metrics']['vocabulary_size']) ? intval($_SESSION['training_metrics']['vocabulary_size']) : null;

// Fetch Users for interaction matrix
$usersRes = $conn->query("SELECT id, firstname, lastname, username FROM users WHERE role = 'buyer'");
$users = [];
while ($row = $usersRes->fetch_assoc()) $users[] = $row;

// Fetch Products for matrix
$productsRes = $conn->query("SELECT id, title, category FROM products");
$products = [];
while ($row = $productsRes->fetch_assoc()) $products[] = $row;

// Fetch raw interaction log
$interactRes = $conn->query("SELECT * FROM user_interactions");
$rawInteractions = [];
while ($row = $interactRes->fetch_assoc()) {
    $rawInteractions[$row['user_id']][$row['product_id']] = floatval($row['interaction_value']);
}

// Fetch cached recommendations for viewing
$recViewRes = $conn->query("
    SELECT r.score, r.recommendation_type, r.generated_at, u.username as buyer_name, p.title as product_title 
    FROM recommendations r
    JOIN users u ON r.user_id = u.id
    JOIN products p ON r.product_id = p.id
    ORDER BY r.user_id, r.score DESC
");
$cachedRecs = [];
while ($row = $recViewRes->fetch_assoc()) $cachedRecs[] = $row;

?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Recommender Diagnostics | Pasarkraft Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../styles.css">
    <style>
        body { background: #f8fafc; font-family: 'Inter', sans-serif; }
        .admin-dashboard-container { max-width: 1400px; margin: 100px auto 3rem; padding: 0 2rem; }
        .page-header-row { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e2e8f0; padding-bottom: 1.5rem; margin-bottom: 2rem; }
        .page-header-row h1 { font-size: 2.2rem; font-family: 'Playfair Display', serif; color: #1e293b; margin: 0; }
        
        .alert { padding: 1rem 1.5rem; border-radius: 8px; margin-bottom: 2rem; font-weight: 500; display: flex; align-items: center; gap: 10px; }
        .alert-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
        
        /* KPI Cards Grid */
        .recommender-kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }
        .kpi-card { background: white; padding: 1.5rem; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: space-between; }
        .kpi-info h4 { margin: 0 0 5px 0; color: #64748b; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px; }
        .kpi-info span { font-size: 1.8rem; font-weight: 700; color: #0f172a; }
        .kpi-icon { width: 50px; height: 50px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; }
        .bg-blue { background: #eff6ff; color: #2563eb; }
        .bg-orange { background: #fff7ed; color: #ea580c; }
        .bg-green { background: #f0fdf4; color: #16a34a; }
        .bg-purple { background: #faf5ff; color: #9333ea; }
        
        /* Action Buttons Box */
        .actions-box { background: white; padding: 1.5rem; border-radius: 12px; border: 1px solid #e2e8f0; margin-bottom: 2rem; display: flex; justify-content: space-between; align-items: center; gap: 1rem; flex-wrap: wrap; }
        .btn-group { display: flex; gap: 10px; }
        .btn-train { background: #2563eb; color: white; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 8px; transition: background 0.2s; }
        .btn-train:hover { background: #1d4ed8; }
        .btn-seed { background: #10b981; color: white; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 8px; transition: background 0.2s; }
        .btn-seed:hover { background: #059669; }
        .btn-clear { background: #ef4444; color: white; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 8px; transition: background 0.2s; }
        .btn-clear:hover { background: #dc2626; }
        
        /* Tabs System */
        .tabs-header { display: flex; border-bottom: 2px solid #e2e8f0; margin-bottom: 2rem; gap: 1.5rem; }
        .tab-link { padding: 10px 5px; color: #64748b; text-decoration: none; font-weight: 600; border-bottom: 2px solid transparent; margin-bottom: -2px; cursor: pointer; display: flex; align-items: center; gap: 8px; }
        .tab-link.active { color: #2563eb; border-bottom: 2px solid #2563eb; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        
        /* Grid Tables / Matrices */
        .card-panel { background: white; border-radius: 12px; border: 1px solid #e2e8f0; padding: 2rem; margin-bottom: 2rem; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02); }
        .card-panel h3 { font-size: 1.25rem; color: #1e293b; margin-top: 0; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 8px; border-bottom: 1px solid #f1f5f9; padding-bottom: 0.75rem; }
        
        .matrix-table-container { overflow-x: auto; margin-bottom: 1.5rem; max-width: 100%; -webkit-overflow-scrolling: touch; }
        .matrix-table { width: auto; min-width: 100%; border-collapse: collapse; font-size: 0.85rem; }
        .matrix-table th, .matrix-table td { padding: 10px; border: 1px solid #e2e8f0; text-align: center; white-space: nowrap; }
        .matrix-table th { background: #f8fafc; color: #475569; font-weight: 600; }
        .matrix-cell-active { background: #dbeafe; color: #1e40af; font-weight: bold; }
        .matrix-cell-empty { color: #cbd5e1; }
        
        .latent-tag { display: inline-block; padding: 2px 8px; border-radius: 12px; font-family: monospace; font-size: 0.75rem; font-weight: 600; }
        .latent-pos { background: #f0fdf4; color: #16a34a; }
        .latent-neg { background: #fef2f2; color: #dc2626; }
        
        /* Simulator Styles */
        .simulator-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; }
        .simulator-results { background: #f8fafc; border-radius: 8px; border: 1px solid #e2e8f0; padding: 1.5rem; }
        
        .math-box { font-family: 'Courier New', Courier, monospace; background: #0f172a; color: #38bdf8; padding: 1.5rem; border-radius: 8px; overflow-x: auto; margin-top: 1rem; }
        .math-box p { margin: 5px 0; }
        
        @media (max-width: 900px) {
            .simulator-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

    <!-- Admin Navigation Header -->
    <header>
        <nav>
            <a href="dashboard_admin.php" class="logo">Pasar<span>kraft</span><span style="font-size: 0.8rem; font-family:var(--font-body); color: #c0392b;"> | Admin Panel</span></a>
            <div class="nav-links">
                <a href="dashboard_admin.php">Dashboard</a>
                <a href="admin_manageUser.php">Users Management</a>
                <a href="admin_recommender.php" class="active-link" style="color: var(--accent-color);">AI Recommender</a>
                <a href="../logout.php" class="nav-login" style="color:#e74c3c;">Logout</a>
            </div>
        </nav>
    </header>

    <div class="admin-dashboard-container">
        
        <div class="page-header-row">
            <div>
                <h1>AI Recommender Diagnostics</h1>
                <p style="color: #64748b; margin-top: 5px;">Visualizing SVD Matrix Factorisation, Cosine Similarity, and TF-IDF Feature Extraction.</p>
            </div>
        </div>

        <?php if (!empty($msg)): ?>
            <div class="alert alert-<?php echo $msg_type; ?>">
                <i class="fas <?php echo $msg_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                <?php echo $msg; ?>
            </div>
        <?php endif; ?>

        <!-- KPIs -->
        <div class="recommender-kpi-grid">
            <div class="kpi-card">
                <div class="kpi-info">
                    <h4>Total Vocabulary Size</h4>
                    <span><?php echo $cachedVocabSize !== null ? $cachedVocabSize : '—'; ?></span>
                    <?php if ($cachedVocabSize === null): ?>
                        <small style="color:#94a3b8;font-size:0.7rem;">Train pipeline to compute</small>
                    <?php endif; ?>
                </div>
                <div class="kpi-icon bg-blue"><i class="fas fa-spell-check"></i></div>
            </div>
            
            <div class="kpi-card">
                <div class="kpi-info">
                    <h4>Active Products Indexed</h4>
                    <span><?php echo $productCount; ?></span>
                </div>
                <div class="kpi-icon bg-orange"><i class="fas fa-box-open"></i></div>
            </div>
            
            <div class="kpi-card">
                <div class="kpi-info">
                    <h4>Interaction Log Entries</h4>
                    <span>
                        <?php 
                            $log_cnt = 0;
                            foreach($rawInteractions as $u_ints) $log_cnt += count($u_ints);
                            echo $log_cnt;
                        ?>
                    </span>
                </div>
                <div class="kpi-icon bg-green"><i class="fas fa-history"></i></div>
            </div>
            
            <div class="kpi-card">
                <div class="kpi-info">
                    <h4>Cached Recs Stored</h4>
                    <span><?php echo count($cachedRecs); ?></span>
                </div>
                <div class="kpi-icon bg-purple"><i class="fas fa-database"></i></div>
            </div>

            <div class="kpi-card">
                <div class="kpi-info">
                    <h4>Last Trained At</h4>
                    <?php $trainedAt = $_SESSION['training_metrics']['trained_at'] ?? null; ?>
                    <span style="font-size:1rem;"><?php echo $trainedAt ? htmlspecialchars($trainedAt) : '—'; ?></span>
                    <?php if (!$trainedAt): ?>
                        <small style="color:#94a3b8;font-size:0.7rem;">Never trained yet</small>
                    <?php else: ?>
                        <small style="color:#16a34a;font-size:0.7rem;">Recommendations are fresh</small>
                    <?php endif; ?>
                </div>
                <div class="kpi-icon bg-green"><i class="fas fa-clock"></i></div>
            </div>
        </div>

        <!-- Action Panel -->
        <div class="actions-box">
            <div>
                <h3 style="margin: 0 0 5px 0; color: #0f172a; font-size: 1.1rem;">AI Pipeline Controller</h3>
                <p style="margin: 0; color: #64748b; font-size: 0.85rem;">Run, clear, or seed the recommender mathematical model.</p>
            </div>
            <div class="btn-group">
                <form method="POST" style="display: flex; gap: 10px;" id="aiPipelineForm">
                    <input type="hidden" name="action" id="pipelineAction" value="">
                    <button type="button" class="btn-train" id="btnTrain" onclick="submitAction('train')">
                        <i class="fas fa-cogs"></i> Train AI Pipeline
                    </button>
                    <button type="button" class="btn-seed" onclick="submitAction('seed_mock')" title="Seed 4 buyers and interaction log metrics for immediate demonstration">
                        <i class="fas fa-seedling"></i> Seed Interaction Data
                    </button>
                    <button type="button" class="btn-clear" onclick="if(confirm('Clear recommendations cache and raw user interactions?')) submitAction('clear')">
                        <i class="fas fa-trash-alt"></i> Clear Data
                    </button>
                </form>
            </div>
        </div>

        <!-- Model Training Metrics Box -->
        <?php if ($metrics): ?>
        <div class="card-panel" style="border-left: 5px solid #2563eb;">
            <h3><i class="fas fa-chart-line" style="color: #2563eb;"></i> SVD Model Training Results</h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem;">
                <div>
                    <strong style="color: #64748b; font-size: 0.85rem; display:block; margin-bottom:5px;">SVD Epochs Completed</strong>
                    <span style="font-size: 1.5rem; font-weight: 700; color: #0f172a;"><?php echo $metrics['epochs']; ?></span>
                </div>
                <div>
                    <strong style="color: #64748b; font-size: 0.85rem; display:block; margin-bottom:5px;">Final Model Training RMSE</strong>
                    <span style="font-size: 1.5rem; font-weight: 700; color: #ea580c;"><?php echo number_format($metrics['final_rmse'], 4); ?></span>
                </div>
                <div>
                    <strong style="color: #64748b; font-size: 0.85rem; display:block; margin-bottom:5px;">Convergence Status</strong>
                    <span class="latent-tag latent-pos" style="font-size:0.9rem; padding: 4px 12px; margin-top:5px;"><i class="fas fa-check-circle"></i> Converged</span>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Tabs Navigation -->
        <div class="tabs-header">
            <div class="tab-link active" onclick="switchTab('svd-tab')"><i class="fas fa-th"></i> Matrix Factorisation (SVD)</div>
            <div class="tab-link" onclick="switchTab('tfidf-tab')"><i class="fas fa-signature"></i> TF-IDF & Cosine Similarity</div>
            <div class="tab-link" onclick="switchTab('cached-tab')"><i class="fas fa-server"></i> Cached Recommendations</div>
            <div class="tab-link" onclick="switchTab('simulator-tab')"><i class="fas fa-calculator"></i> AI Match Simulator</div>
        </div>

        <!-- Tab 1: SVD Matrix Factorisation -->
        <div id="svd-tab" class="tab-content active animate-fade-in">
            
            <!-- User-Item Interaction Matrix -->
            <div class="card-panel">
                <h3><i class="fas fa-border-all" style="color: #3b82f6;"></i> 1. User-Item Interaction Matrix</h3>
                <p style="color: #64748b; margin-top: -10px; margin-bottom: 1.5rem; font-size: 0.9rem;">
                    The raw training data representing historical buyer interactions. Ratings are mapped: View = 1, Click = 2, Wishlist = 3, Accepted Deal = 5.
                </p>
                <div class="matrix-table-container">
                    <?php if (empty($users) || empty($products)): ?>
                        <div style="text-align:center; padding: 3rem 2rem; color:#94a3b8;">
                            <i class="fas fa-table" style="font-size:2.5rem; display:block; margin-bottom:1rem; opacity:0.4;"></i>
                            <h4 style="margin:0 0 8px; color:#64748b;">No data to display yet</h4>
                            <p style="font-size:0.85rem; margin:0;">Add products and buyers, then click <strong>"Train AI Pipeline"</strong> to populate this matrix.</p>
                        </div>
                    <?php else: ?>
                    <table class="matrix-table">
                        <thead>
                            <tr>
                                <th>Buyer / Product</th>
                                <?php foreach ($products as $p): ?>
                                    <th title="<?php echo htmlspecialchars($p['title']); ?>"><?php echo htmlspecialchars(strlen($p['title']) > 15 ? substr($p['title'],0,15).'...' : $p['title']); ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($users)): ?>
                                <tr><td colspan="<?php echo count($products)+1; ?>" style="padding:20px; color:#94a3b8;">No buyers registered yet.</td></tr>
                            <?php else: ?>
                                <?php foreach ($users as $u): ?>
                                    <tr>
                                        <td style="text-align: left; font-weight: 600; color: #1e293b;"><?php echo htmlspecialchars($u['firstname'] . ' ' . $u['lastname']); ?> (<?php echo htmlspecialchars($u['username']); ?>)</td>
                                        <?php foreach ($products as $p): ?>
                                            <?php 
                                                $score = isset($rawInteractions[$u['id']][$p['id']]) ? $rawInteractions[$u['id']][$p['id']] : null;
                                                $class = $score ? 'matrix-cell-active' : 'matrix-cell-empty';
                                            ?>
                                            <td class="<?php echo $class; ?>"><?php echo $score ? number_format($score, 1) : '-'; ?></td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Latent Factors Decompositions -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-bottom: 2rem;">
                
                <!-- User Latent Factors Matrix (P) -->
                <div class="card-panel">
                    <h3><i class="fas fa-user-circle" style="color: #8b5cf6;"></i> Latent User Factors Matrix ($P$)</h3>
                    <p style="color: #64748b; margin-top: -10px; margin-bottom: 1.5rem; font-size: 0.85rem;">
                        How much each user aligns with different latent taste profiles (dimension: $U \times K$).
                    </p>
                    <div class="matrix-table-container">
                        <table class="matrix-table">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <?php for($f=0;$f<4;$f++): ?>
                                        <th>Factor <?php echo $f+1; ?></th>
                                    <?php endfor; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $u): ?>
                                    <?php if (isset($userMap[$u['id']])): ?>
                                        <?php $idx = $userMap[$u['id']]; ?>
                                        <tr>
                                            <td style="text-align:left; font-weight: 500;"><?php echo htmlspecialchars($u['username']); ?></td>
                                            <?php for($f=0;$f<4;$f++): ?>
                                                <?php 
                                                    $val = isset($svd['P'][$idx][$f]) ? $svd['P'][$idx][$f] : 0.0; 
                                                    $tag = $val >= 0 ? 'latent-pos' : 'latent-neg';
                                                ?>
                                                <td><span class="latent-tag <?php echo $tag; ?>"><?php echo number_format($val, 4); ?></span></td>
                                            <?php endfor; ?>
                                        </tr>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Item Latent Factors Matrix (Q) -->
                <div class="card-panel">
                    <h3><i class="fas fa-gem" style="color: #eab308;"></i> Latent Product Factors Matrix ($Q$)</h3>
                    <p style="color: #64748b; margin-top: -10px; margin-bottom: 1.5rem; font-size: 0.85rem;">
                        How heavily each product maps onto different latent themes (dimension: $I \times K$).
                    </p>
                    <div class="matrix-table-container">
                        <table class="matrix-table">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <?php for($f=0;$f<4;$f++): ?>
                                        <th>Factor <?php echo $f+1; ?></th>
                                    <?php endfor; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($products as $p): ?>
                                    <?php if (isset($productMap[$p['id']])): ?>
                                        <?php $idx = $productMap[$p['id']]; ?>
                                        <tr>
                                            <td style="text-align:left; font-weight: 500;" title="<?php echo htmlspecialchars($p['title']); ?>"><?php echo htmlspecialchars(strlen($p['title']) > 15 ? substr($p['title'],0,15).'...' : $p['title']); ?></td>
                                            <?php for($f=0;$f<4;$f++): ?>
                                                <?php 
                                                    $val = isset($svd['Q'][$idx][$f]) ? $svd['Q'][$idx][$f] : 0.0; 
                                                    $tag = $val >= 0 ? 'latent-pos' : 'latent-neg';
                                                ?>
                                                <td><span class="latent-tag <?php echo $tag; ?>"><?php echo number_format($val, 4); ?></span></td>
                                            <?php endfor; ?>
                                        </tr>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tab 2: TF-IDF Content-Based Filtering -->
        <!-- PERF FIX: Content is loaded lazily via AJAX on first tab click (get_cosine_matrix.php). -->
        <!-- Previously this tab rendered O(N²) cosine similarity computations server-side on page load. -->
        <div id="tfidf-tab" class="tab-content animate-fade-in">

            <!-- Loading state shown while AJAX request is in flight -->
            <div id="tfidf-loader" style="display:none; text-align:center; padding: 4rem 2rem; color: #64748b;">
                <i class="fas fa-spinner fa-spin" style="font-size: 2.5rem; color: #2563eb; display:block; margin-bottom: 1rem;"></i>
                <p style="font-weight:600; font-size:1rem; margin:0 0 8px;">Computing TF-IDF &amp; Cosine Similarity Matrix...</p>
                <p style="font-size:0.85rem; color:#94a3b8; margin:0;">Computed on-demand to keep the page fast. This may take a few seconds.</p>
            </div>

            <!-- Error state -->
            <div id="tfidf-error" style="display:none; text-align:center; padding: 3rem 2rem; color: #ef4444;">
                <i class="fas fa-exclamation-triangle" style="font-size: 2rem; display:block; margin-bottom: 1rem;"></i>
                <p id="tfidf-error-msg" style="font-weight:600;">Failed to load TF-IDF data.</p>
            </div>

            <!-- Actual content, rendered by JS after AJAX response -->
            <div id="tfidf-content" style="display:none;">
                <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 2rem;">

                    <!-- Vocabulary list -->
                    <div class="card-panel" style="max-height: 550px; overflow-y: auto;">
                        <h3><i class="fas fa-book" style="color: #10b981;"></i> TF-IDF Vocabulary</h3>
                        <p style="color: #64748b; font-size: 0.85rem; margin-top: -10px; margin-bottom: 1rem;">
                            Unique keywords extracted and vectorized across all product descriptions and metadata tags.
                        </p>
                        <div id="vocab-container" style="display: flex; flex-wrap: wrap; gap: 6px;"></div>
                    </div>

                    <!-- Cosine Similarity Grid -->
                    <div class="card-panel">
                        <h3><i class="fas fa-compress-arrows-alt" style="color: #f59e0b;"></i> Product Cosine Similarity Matrix</h3>
                        <p style="color: #64748b; font-size: 0.85rem; margin-top: -10px; margin-bottom: 1.5rem;">
                            Cosine similarities between product TF-IDF vectors.
                            <span id="matrix-note" style="color:#94a3b8;"></span>
                        </p>
                        <div class="matrix-table-container" id="cosine-matrix-container"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tab 3: Stored Recommendations Cache -->
        <div id="cached-tab" class="tab-content animate-fade-in">
            <div class="card-panel">
                <h3><i class="fas fa-server" style="color: #6366f1;"></i> Stored/Cached Personalized Recommendations</h3>
                <p style="color: #64748b; font-size: 0.9rem; margin-top: -10px; margin-bottom: 1.5rem;">
                    Currently stored recommendations in the <strong>`recommendations`</strong> table, directly read by the homepage.
                </p>
                <div class="matrix-table-container">
                    <table class="matrix-table" style="text-align: left;">
                        <thead>
                            <tr style="background:#f8fafc;">
                                <th style="text-align:left; padding: 12px;">Buyer Name</th>
                                <th style="text-align:left; padding: 12px;">Recommended Product</th>
                                <th style="text-align:center; padding: 12px;">Recommendation Score</th>
                                <th style="text-align:center; padding: 12px;">Recommendation Type</th>
                                <th style="text-align:center; padding: 12px;">Cached Timestamp</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($cachedRecs)): ?>
                                <tr>
                                    <td colspan="5" style="text-align: center; padding: 3rem; color: #94a3b8;">
                                        <i class="fas fa-folder-open" style="font-size: 3rem; margin-bottom: 10px; opacity: 0.5;"></i>
                                        <h4>No Cached Recommendations</h4>
                                        <p style="font-size:0.85rem;">Click "Train AI Pipeline" at the top to compute and cache recommendations.</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($cachedRecs as $cr): ?>
                                    <tr style="border-bottom: 1px solid #f1f5f9;">
                                        <td style="padding: 12px; font-weight: 500;"><?php echo htmlspecialchars($cr['buyer_name']); ?></td>
                                        <td style="padding: 12px;"><?php echo htmlspecialchars($cr['product_title']); ?></td>
                                        <td style="padding: 12px; text-align: center; font-weight: 600; color:#2563eb;"><?php echo number_format($cr['score'], 4); ?></td>
                                        <td style="padding: 12px; text-align: center;">
                                            <span class="latent-tag <?php echo $cr['recommendation_type'] === 'hybrid' ? 'latent-pos' : ($cr['recommendation_type'] === 'popularity' ? 'latent-neg' : 'latent-pos'); ?>" style="text-transform: uppercase;">
                                                <?php echo htmlspecialchars($cr['recommendation_type']); ?>
                                            </span>
                                        </td>
                                        <td style="padding: 12px; text-align: center; color: #64748b; font-size: 0.8rem;"><?php echo date("d M Y H:i", strtotime($cr['generated_at'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Tab 4: Recommendation Simulator -->
        <div id="simulator-tab" class="tab-content animate-fade-in">
            <div class="card-panel">
                <h3><i class="fas fa-calculator" style="color: #ec4899;"></i> AI Recommendation Match Simulator</h3>
                <p style="color: #64748b; font-size: 0.9rem; margin-top: -10px; margin-bottom: 2rem;">
                    Select any buyer and product to see a step-by-step mathematical breakdown of the unified hybrid score!
                </p>
                
                <div class="simulator-grid">
                    <div>
                        <div class="form-group" style="margin-bottom: 1.5rem;">
                            <label style="display:block; font-weight:600; margin-bottom:8px; color:#475569;">1. Select Buyer</label>
                            <select id="sim-user" class="form-control-sm" style="width:100%; padding:10px; border-radius:6px; border:1px solid #cbd5e1;">
                                <?php foreach($users as $u): ?>
                                    <option value="<?php echo $u['id']; ?>"><?php echo htmlspecialchars($u['firstname'] . ' ' . $u['lastname'] . ' ('.$u['username'].')'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group" style="margin-bottom: 2rem;">
                            <label style="display:block; font-weight:600; margin-bottom:8px; color:#475569;">2. Select Target Product</label>
                            <select id="sim-product" class="form-control-sm" style="width:100%; padding:10px; border-radius:6px; border:1px solid #cbd5e1;">
                                <?php foreach($products as $p): ?>
                                    <option value="<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['title'] . ' ['.$p['category'].']'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <button type="button" class="btn-train" style="width:100%; justify-content:center; padding:12px;" onclick="runSimulation()">
                            <i class="fas fa-play"></i> Compute Recommendation Math
                        </button>
                    </div>
                    
                    <div class="simulator-results" id="simResultBox" style="display: none;">
                        <h4 style="margin: 0 0 1rem 0; color: #1e293b;"><i class="fas fa-brain" style="color: #e67e22;"></i> Mathematical Score Analysis</h4>
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem;">
                            <div style="background: white; padding: 10px; border-radius: 6px; border: 1px solid #e2e8f0; text-align: center;">
                                <span style="font-size: 0.75rem; color:#64748b; font-weight:600; display:block;">Collaborative SVD</span>
                                <span id="svdResVal" style="font-size: 1.25rem; font-weight:700; color:#2563eb;">-</span>
                            </div>
                            <div style="background: white; padding: 10px; border-radius: 6px; border: 1px solid #e2e8f0; text-align: center;">
                                <span style="font-size: 0.75rem; color:#64748b; font-weight:600; display:block;">Content Similarity</span>
                                <span id="contentResVal" style="font-size: 1.25rem; font-weight:700; color:#10b981;">-</span>
                            </div>
                            <div style="background: white; padding: 10px; border-radius: 6px; border: 1px solid #e2e8f0; text-align: center; border-color: #ea580c; background: #fff7ed;">
                                <span style="font-size: 0.75rem; color:#ea580c; font-weight:700; display:block;">Unified Match Score</span>
                                <span id="hybridResVal" style="font-size: 1.25rem; font-weight:800; color:#ea580c;">-</span>
                            </div>
                        </div>
                        
                        <h5 style="margin:0 0 5px 0; color:#475569;">Step-by-Step Calculation Formula</h5>
                        <div class="math-box" id="mathDetailsBox">
                            <!-- Populated by JS -->
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- Admin Footer -->
    <footer style="margin-top: 5rem;">
        <p class="copyright">&copy; 2026 Pasarkraft. Admin Panel | AI Recommender Control Center.</p>
    </footer>

    <script>
        // Submit pipeline action via hidden input — avoids the browser dropping
        // the button value when the button is disabled before form submit.
        function submitAction(action) {
            if (action === 'train') {
                var btn = document.getElementById('btnTrain');
                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Training... (this may take a few seconds)';
                btn.style.opacity = '0.7';
                // Auto-reset after 60s in case of timeout/failure
                setTimeout(function() {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-cogs"></i> Train AI Pipeline';
                    btn.style.opacity = '1';
                }, 60000);
            }
            document.getElementById('pipelineAction').value = action;
            document.getElementById('aiPipelineForm').submit();
        }

        // Flag: true once TF-IDF data has been fetched from the server
        let tfidfLoaded = false;

        function switchTab(tabId) {
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(tc => tc.classList.remove('active'));
            // Remove active style from tab links
            document.querySelectorAll('.tab-link').forEach(tl => tl.classList.remove('active'));

            // Show target
            document.getElementById(tabId).classList.add('active');
            event.currentTarget.classList.add('active');

            // PERF FIX: Lazy-load TF-IDF tab only when first clicked.
            // Previously this was computed server-side (O(N²)) on every page load.
            if (tabId === 'tfidf-tab' && !tfidfLoaded) {
                loadTfidfData();
            }
        }

        function loadTfidfData() {
            document.getElementById('tfidf-loader').style.display = 'block';
            document.getElementById('tfidf-content').style.display = 'none';
            document.getElementById('tfidf-error').style.display = 'none';

            fetch('get_cosine_matrix.php')
                .then(r => {
                    if (!r.ok) throw new Error('Server error ' + r.status);
                    return r.json();
                })
                .then(data => {
                    tfidfLoaded = true;

                    // --- Render Vocabulary ---
                    const vocabContainer = document.getElementById('vocab-container');
                    let vocabHtml = '';
                    data.vocab.forEach(term => {
                        const escaped = term.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
                        vocabHtml += `<span style="background:#f1f5f9;color:#475569;padding:4px 10px;border-radius:4px;font-size:0.8rem;font-family:monospace;border:1px solid #e2e8f0;">${escaped}</span>`;
                    });
                    if (data.vocab_total > data.vocab.length) {
                        vocabHtml += `<span style="color:#94a3b8;font-size:0.8rem;padding:4px 0;">...and ${data.vocab_total - data.vocab.length} more terms</span>`;
                    }
                    vocabContainer.innerHTML = vocabHtml;

                    // --- Show note if product list was capped ---
                    if (data.total_products > data.shown_products) {
                        document.getElementById('matrix-note').textContent =
                            ` (Showing first ${data.shown_products} of ${data.total_products} products to prevent timeout)`;
                    }

                    // --- Render Cosine Similarity Table ---
                    const products = data.products;
                    let tableHtml = `<table class="matrix-table" style="font-size:0.75rem;"><thead><tr><th>Product</th>`;
                    products.forEach(p => {
                        const label = p.title.length > 10 ? p.title.substring(0, 10) + '...' : p.title;
                        tableHtml += `<th title="${p.title}">${label}</th>`;
                    });
                    tableHtml += `</tr></thead><tbody>`;

                    data.matrix.forEach((row, i) => {
                        const p1 = products[i];
                        const rowLabel = p1.title.length > 15 ? p1.title.substring(0, 15) + '...' : p1.title;
                        tableHtml += `<tr><td style="text-align:left;font-weight:500;font-size:0.8rem;" title="${p1.title}">${rowLabel}</td>`;
                        row.forEach(sim => {
                            let style;
                            if (sim >= 0.9999) style = 'background:#eff6ff;color:#2563eb;font-weight:bold;';
                            else if (sim > 0.6)  style = 'background:#ecfdf5;color:#10b981;';
                            else if (sim > 0.2)  style = 'background:#fffbeb;color:#d97706;';
                            else                 style = 'background:#ffffff;color:#cbd5e1;';
                            tableHtml += `<td style="${style}">${sim.toFixed(2)}</td>`;
                        });
                        tableHtml += `</tr>`;
                    });
                    tableHtml += `</tbody></table>`;

                    document.getElementById('cosine-matrix-container').innerHTML = tableHtml;
                    document.getElementById('tfidf-loader').style.display = 'none';
                    document.getElementById('tfidf-content').style.display = 'block';
                })
                .catch(err => {
                    document.getElementById('tfidf-loader').style.display = 'none';
                    document.getElementById('tfidf-error').style.display = 'block';
                    document.getElementById('tfidf-error-msg').textContent = 'Failed to load TF-IDF data: ' + err.message;
                });
        }

        function runSimulation() {
            const userId = document.getElementById('sim-user').value;
            const productId = document.getElementById('sim-product').value;
            
            // Perform simulated math in PHP via AJAX or direct dynamic computation
            fetch(`sim_recommendation.php?user_id=${userId}&product_id=${productId}`)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('simResultBox').style.display = 'block';
                    
                    document.getElementById('svdResVal').textContent = parseFloat(data.svd_rating).toFixed(2);
                    document.getElementById('contentResVal').textContent = (parseFloat(data.cosine_similarity) * 100).toFixed(0) + '%';
                    document.getElementById('hybridResVal').textContent = (parseFloat(data.hybrid_score) * 100).toFixed(0) + '% Match';
                    
                    // Render step by step math box
                    const mathHtml = `
<p>// 1. COLLABORATIVE FILTERING (SVD MODEL)</p>
<p>Predicted Rating = Mean (${parseFloat(data.svd_mean).toFixed(2)}) + Bias_u (${parseFloat(data.svd_user_bias).toFixed(2)}) + Bias_i (${parseFloat(data.svd_product_bias).toFixed(2)}) + (P_u . Q_i)</p>
<p>Predicted SVD Rating = <strong>${parseFloat(data.svd_rating).toFixed(4)} / 5.0</strong></p>
<p>Normalized SVD Rating (0..1 scale) = (Rating - 1.0) / 4.0 = <strong>${parseFloat(data.svd_normalized).toFixed(4)}</strong></p>
<br>
<p>// 2. CONTENT-BASED FILTERING (TF-IDF &amp; COSINE SIMILARITY)</p>
<p>User Profile Vector compiled from ${data.user_items_count} historical interactions.</p>
<p>Cosine Similarity = Dot Product (UserProfile, ProductTFIDF) = <strong>${parseFloat(data.cosine_similarity).toFixed(4)}</strong></p>
<br>
<p>// 3. HYBRID FUSION SYSTEM</p>
<p>Model weight (Alpha = ${parseFloat(data.alpha).toFixed(2)}) chosen based on interaction count (${data.user_interactions_count}).</p>
<p>Formula: Score = (Alpha * SVD) + ((1.0 - Alpha) * Content)</p>
<p>Score = (${parseFloat(data.alpha).toFixed(2)} * ${parseFloat(data.svd_normalized).toFixed(4)}) + (${(1.0 - parseFloat(data.alpha)).toFixed(2)} * ${parseFloat(data.cosine_similarity).toFixed(4)})</p>
<p>Unified Score = <strong>${parseFloat(data.hybrid_score).toFixed(6)}</strong></p>
<p>Match Percentage = <strong>${(parseFloat(data.hybrid_score) * 100).toFixed(2)}% Match</strong></p>
                    `;
                    document.getElementById('mathDetailsBox').innerHTML = mathHtml;
                })
                .catch(err => {
                    console.error("Simulation failed:", err);
                    alert("Error running simulation. Please train the AI pipeline first!");
                });
        }
    </script>
</body>
</html>
