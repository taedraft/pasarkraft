<?php
session_start();
header("Cache-Control: no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

require 'db_connect.php';

// Fetch top 4 trending tags from active products
$trending_tags = [];
$tags_query = $conn->query("SELECT tags FROM products WHERE stock > 0");
if ($tags_query) {
    $tag_counts = [];
    while ($t_row = $tags_query->fetch_assoc()) {
        if (!empty($t_row['tags'])) {
            $individual_tags = explode(',', $t_row['tags']);
            foreach ($individual_tags as $tag) {
                $trimmed = trim($tag);
                if ($trimmed !== '') {
                    $tag_counts[$trimmed] = ($tag_counts[$trimmed] ?? 0) + 1;
                }
            }
        }
    }
    arsort($tag_counts);
    $trending_tags = array_slice(array_keys($tag_counts), 0, 4);
}
if (empty($trending_tags)) {
    $trending_tags = ["Handmade", "Batik", "Woodcraft", "Gift"];
}


$unread_count = 0;
if (isset($_SESSION['user_id']) && isset($_SESSION['role']) && $_SESSION['role'] === 'buyer') {
    $unread_stmt = $conn->prepare("SELECT COUNT(DISTINCT sender_id) as unread_count FROM messages WHERE receiver_id = ? AND is_read = 0");
    $unread_stmt->bind_param("i", $_SESSION['user_id']);
    $unread_stmt->execute();
    $unread_res = $unread_stmt->get_result();
    if ($unread_row = $unread_res->fetch_assoc()) {
        $unread_count = $unread_row['unread_count'];
    }
    $unread_stmt->close();
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'buyer') {
    $_SESSION['login_error'] = "Log in to view your wishlist.";
    header("Location: buyer/login_buyer.php");
    exit();
}

$user_id = $_SESSION['user_id'];

$filter_query = trim($_GET['q'] ?? '');
$filter_material = trim($_GET['material'] ?? '');
$filter_pattern = trim($_GET['pattern'] ?? '');
$filter_color = trim($_GET['color'] ?? '');

$where_clauses = ["w.buyer_id = ?"];
$params = [$user_id];
$types = "i";

if ($filter_query !== '') {
    $like = '%' . $filter_query . '%';
    $where_clauses[] = "(p.title LIKE ? OR p.description LIKE ? OR p.tags LIKE ?)";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types .= 'sss';
}

if ($filter_material !== '') {
    $where_clauses[] = "p.material LIKE ?";
    $params[] = '%' . $filter_material . '%';
    $types .= 's';
}

if ($filter_pattern !== '') {
    $like_pattern = '%' . $filter_pattern . '%';
    $where_clauses[] = "(p.tags LIKE ? OR p.technique LIKE ? OR p.style LIKE ? OR p.subcategory LIKE ?)";
    $params[] = $like_pattern;
    $params[] = $like_pattern;
    $params[] = $like_pattern;
    $params[] = $like_pattern;
    $types .= 'ssss';
}

if ($filter_color !== '') {
    $where_clauses[] = "p.color LIKE ?";
    $params[] = '%' . $filter_color . '%';
    $types .= 's';
}

$sql = "SELECT p.id, p.seller_id, p.title, p.category, p.price, p.image_path 
        FROM wishlist w
        JOIN products p ON w.product_id = p.id";
if (!empty($where_clauses)) {
    $sql .= " WHERE " . implode(" AND ", $where_clauses);
}

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$wishlist_items = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $wishlist_items[] = $row;
    }
}
$stmt->close();

$recommended_products = [];
// Fetch cached recommendations for the buyer
$rec_stmt = $conn->prepare("
    SELECT p.id, p.seller_id, p.title, p.category, p.price, p.image_path, a.shopname, r.score, r.recommendation_type
    FROM recommendations r
    JOIN products p ON r.product_id = p.id
    LEFT JOIN artisans a ON p.seller_id = a.user_id
    WHERE r.user_id = ? AND p.stock > 0
    ORDER BY r.score DESC
    LIMIT 8
");
$rec_stmt->bind_param("i", $user_id);
$rec_stmt->execute();
$rec_res = $rec_stmt->get_result();
while ($row = $rec_res->fetch_assoc()) {
    $recommended_products[] = $row;
}
$rec_stmt->close();

// If no recommendations are cached, generate them dynamically in real-time.
if (empty($recommended_products)) {
    $python_bin = getenv('PK_PYTHON_BIN') ?: 'python';
    $script_path = __DIR__ . DIRECTORY_SEPARATOR . 'recommender.py';
    $exit_code = 1;

    if (function_exists('exec') && file_exists($script_path)) {
        $cmd = escapeshellarg($python_bin) . ' ' . escapeshellarg($script_path)
            . ' --host ' . escapeshellarg($servername)
            . ' --user ' . escapeshellarg($username)
            . ' --password ' . escapeshellarg($password)
            . ' --database ' . escapeshellarg($dbname)
            . ' --user-id ' . escapeshellarg((string) $user_id)
            . ' --limit 8 --write-db';
        $output = [];
        @exec($cmd . ' 2>&1', $output, $exit_code);
    }

    if ($exit_code === 0) {
        $rec_stmt = $conn->prepare("
            SELECT p.id, p.seller_id, p.title, p.category, p.price, p.image_path, a.shopname, r.score, r.recommendation_type
            FROM recommendations r
            JOIN products p ON r.product_id = p.id
            LEFT JOIN artisans a ON p.seller_id = a.user_id
            WHERE r.user_id = ? AND p.stock > 0
            ORDER BY r.score DESC
            LIMIT 8
        ");
        $rec_stmt->bind_param("i", $user_id);
        $rec_stmt->execute();
        $rec_res = $rec_stmt->get_result();
        while ($row = $rec_res->fetch_assoc()) {
            $recommended_products[] = $row;
        }
        $rec_stmt->close();
    }

    if (empty($recommended_products)) {
        require_once 'recommender.php';
        $recEngine = new PasarKraftRecommender($conn);
        $recEngine->trainTfidf();
        $recEngine->trainSVD();
        $recs = $recEngine->getRecommendationsForUser($user_id, 8);

        if (!empty($recs)) {
            $ins_stmt = $conn->prepare("INSERT INTO recommendations (user_id, product_id, score, recommendation_type) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE score = VALUES(score)");
            foreach ($recs as $pid => $data) {
                $ins_stmt->bind_param("iids", $user_id, $pid, $data['score'], $data['type']);
                $ins_stmt->execute();
            }
            $ins_stmt->close();

            $rec_stmt = $conn->prepare("
                SELECT p.id, p.seller_id, p.title, p.category, p.price, p.image_path, a.shopname, r.score, r.recommendation_type
                FROM recommendations r
                JOIN products p ON r.product_id = p.id
                LEFT JOIN artisans a ON p.seller_id = a.user_id
                WHERE r.user_id = ? AND p.stock > 0
                ORDER BY r.score DESC
                LIMIT 8
            ");
            $rec_stmt->bind_param("i", $user_id);
            $rec_stmt->execute();
            $rec_res = $rec_stmt->get_result();
            while ($row = $rec_res->fetch_assoc()) {
                $recommended_products[] = $row;
            }
            $rec_stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ms">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Wishlist | Pasarkraft</title>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Playfair+Display:ital,wght@0,400;0,700;1,400&display=swap"
        rel="stylesheet">
    <!-- Font Awesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="styles.css">
    <style>
        .wishlist-page-container {
            max-width: 1200px;
            margin: 100px auto 3rem;
            padding: 2rem;
            min-height: 60vh;
        }

        .wishlist-title-section {
            text-align: center;
            margin-bottom: 4rem;
        }

        .wishlist-title-section h1 {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
            color: var(--primary-color);
        }

        .breadcrumb {
            color: #888;
            font-size: 0.9rem;
        }

        .breadcrumb a {
            color: #888;
            transition: color 0.3s;
        }

        .breadcrumb a:hover {
            color: var(--accent-color);
        }

        /* Wishlist Item Badge Style like in reference */
        .badge-new-arrival {
            position: absolute;
            top: 10px;
            left: 10px;
            background: transparent;
            color: #333;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            z-index: 2;
        }

        /* Remove Button Overlay */
        .btn-remove-wishlist {
            position: absolute;
            top: 10px;
            right: 10px;
            background: white;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            color: #e74c3c;
            cursor: pointer;
            transition: all 0.3s;
            border: none;
            z-index: 2;
        }

        .btn-remove-wishlist:hover {
            transform: scale(1.1);
            background: #e74c3c;
            color: white;
        }

        /* Empty State */
        .empty-wishlist {
            text-align: center;
            padding: 4rem;
            color: #999;
        }

        .empty-wishlist i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.3;
        }
    </style>
</head>

<body>

    <!-- Navigation -->
    <header>
        <nav>
            <a href="homepage.php" class="logo">Pasar<span>kraft</span>.</a>
            <form class="header-search" method="GET" action="wishlist_page.php">
                <input type="text" name="q" placeholder="Search wishlisted products..." value="<?php echo htmlspecialchars($filter_query); ?>">
                <div class="search-controls">
                    <button class="search-btn" type="submit"><i class="fas fa-search"></i></button>
                </div>
                <!-- Dropdown Menu -->
                <div class="search-dropdown">
                    <div class="dropdown-section">
                        <h4>Collections</h4>
                        <div class="collection-list">
                            <a href="batik_page.php?subcategories[]=Men%27s+Wear&subcategories[]=Women%27s+Wear"><i class="fas fa-tshirt"></i> Batik Fashion</a>
                            <a href="woodcraft_page.php?subcategories[]=Furniture"><i class="fas fa-couch"></i> Wood Furniture</a>
                            <a href="woodcraft_page.php?subcategories[]=Traditional+Carving&subcategories[]=Home+Decor"><i class="fas fa-tree"></i> Handcrafted wood</a>
                            <a href="batik_page.php?subcategories[]=Batik+Textile"><i class="fas fa-scroll"></i> Batik Textile</a>
                        </div>
                    </div>
                    <div class="dropdown-section">
                        <h4>Trending Tags</h4>
                        <div class="tags">
                            <?php foreach ($trending_tags as $t): ?>
                                <a href="homepage.php?q=<?php echo urlencode($t); ?>" style="text-decoration:none; color:inherit;"><span>#<?php echo htmlspecialchars($t); ?></span></a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </form>
            <div class="nav-links">
                <a href="batik_page.php">Batik</a>
                <a href="woodcraft_page.php">Woodcraft</a>
                <a href="homepage.php#about">About</a>
                <a href="buyer/inquiry_messages.php">Chat history
                    <?php if (isset($unread_count) && $unread_count > 0)
                        echo '<span style="background: red; color: white; border-radius: 50%; padding: 2px 6px; font-size: 0.75rem; margin-left: 5px;">' . $unread_count . '</span>'; ?>
                </a>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="logout.php" class="nav-login"
                        onclick="return confirm('Are you sure you want to log out?');">Logout</a>
                <?php else: ?>
                    <a href="buyer/login_buyer.php" class="nav-login">Login</a>
                <?php endif; ?>
                <a href="wishlist_page.php" class="wishlist-icon" style="color: var(--accent-color);">
                    <i class="fas fa-heart"></i>
                    <span class="tooltip">Wishlist</span>
                </a>
                <a href="buyer/buyer_profile.php" class="profile-icon">
                    <i class="far fa-user-circle"></i>
                    <span class="tooltip">Account</span>
                </a>
            </div>
        </nav>
    </header>

    <!-- Wishlist Layout -->
    <div class="wishlist-page-container">

        <div class="wishlist-title-section">
            <h1>Wishlist</h1>
            <div class="breadcrumb">
                <a href="homepage.php">Home</a> &nbsp;&gt;&nbsp; <span>Wishlist</span>
            </div>
        </div>

        <!-- Product Grid -->
        <div class="product-grid" id="wishlistGrid">
            <?php if (empty($wishlist_items)): ?>
                <div
                    style="grid-column: 1 / -1; text-align: center; padding: 60px; color: #7f8c8d; background: #fff; border-radius: 8px; border: 1px dashed #ccc;">
                    <i class="fas fa-heart-broken" style="font-size: 3rem; color: #bdc3c7; margin-bottom: 15px;"></i>
                    <h3>Your Wishlist is Empty</h3>
                    <p>Discover our beautiful collections and save your favorite Malaysian crafts here!</p>
                    <a href="homepage.php#products" class="btn" style="margin-top: 15px; display: inline-block;">Explore
                        Now</a>
                </div>
            <?php else: ?>
                <?php foreach ($wishlist_items as $item): ?>
                    <article class="product-card">
                        <button class="btn-remove-wishlist" title="Remove from wishlist"
                            onclick="removeFromWishlist(this, <?php echo $item['id']; ?>)">
                            <i class="fas fa-times"></i>
                        </button>
                        <a href="product_detail.php?id=<?php echo $item['id']; ?>" class="product-card-link" style="text-decoration: none; color: inherit; display: block;">
                            <div class="product-image">
                                <img src="<?php echo htmlspecialchars($item['image_path'] ? $item['image_path'] : 'png/batik_shirt.png'); ?>"
                                    alt="<?php echo htmlspecialchars($item['title']); ?>">
                            </div>
                        </a>
                        <div class="product-info">
                            <div class="product-meta">
                                <span class="product-category">
                                    <?php echo htmlspecialchars($item['category']); ?>
                                </span>
                                <button class="wishlist-btn active" title="Added to Wishlist"
                                    onclick="removeFromWishlist(this, <?php echo $item['id']; ?>)"><i class="fas fa-heart"
                                        style="color:#e74c3c;"></i></button>
                            </div>
                            <h3 class="product-title">
                                <a href="product_detail.php?id=<?php echo $item['id']; ?>" style="text-decoration: none; color: inherit;">
                                    <?php echo htmlspecialchars($item['title']); ?>
                                </a>
                            </h3>
                            <span class="product-price">RM
                                <?php echo number_format($item['price'], 2); ?>
                            </span>
                            <a href="buyer/inquiry_messages.php?seller_id=<?php echo urlencode($item['seller_id']); ?>"
                                class="btn-chat">Chat with Seller</a>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!empty($recommended_products)): ?>
        <section id="ai-recommendations" class="products"
            style="background: #faf8f5; padding: 4rem 2rem; border-top: 1px solid #f1ece4; border-bottom: 1px solid #f1ece4; margin-top: 3rem;">
            <div style="max-width: 1200px; margin: 0 auto;">
                <div class="section-header" style="text-align: center; margin-bottom: 3rem;">
                    <h2
                        style="font-size: 2rem; color: var(--primary-color); font-family: var(--font-heading); margin-bottom: 0.5rem;">
                        Recommended for You</h2>
                    <p style="color: #7f8c8d; font-size: 0.95rem;"><i class="fas fa-magic"
                            style="color: #d35400; margin-right: 5px;"></i> AI Personalized matches based on your interests.
                    </p>
                </div>

                <div class="product-grid animate-fade-in">
                    <?php foreach ($recommended_products as $prod):
                        $img_src = htmlspecialchars($prod['image_path'] ?? '');
                        if (empty($img_src)) {
                            $img_src = 'png/batik_shirt.png';
                        } elseif (strpos($img_src, '/') === false) {
                            $img_src = 'png/' . $img_src;
                        }

                        $match_percentage = round($prod['score'] * 100);
                        if ($match_percentage < 40)
                            $match_percentage += 45;
                        if ($match_percentage > 99)
                            $match_percentage = 99;
                        ?>
                        <article class="product-card">
                            <a href="product_detail.php?id=<?php echo $prod['id']; ?>" class="product-card-link" style="text-decoration: none; color: inherit; display: block;">
                                <div class="product-image" style="position: relative;">
                                    <img src="<?php echo $img_src; ?>" alt="<?php echo htmlspecialchars($prod['title']); ?>">
                                    <span class="ai-match-badge"
                                        style="position: absolute; top: 15px; left: 15px; background: rgba(44, 62, 80, 0.95); color: white; padding: 6px 12px; font-size: 0.75rem; font-weight: 600; border-radius: 20px; box-shadow: 0 4px 8px rgba(0,0,0,0.15); display: flex; align-items: center; gap: 5px; backdrop-filter: blur(5px); border: 1px solid rgba(255,255,255,0.1); z-index: 10;">
                                        <i class="fas fa-brain" style="color: #e67e22;"></i>
                                        <?php echo $match_percentage; ?>% Match
                                    </span>
                                </div>
                            </a>
                            <div class="product-info">
                                <div class="product-meta">
                                    <span class="product-category">
                                        <?php echo htmlspecialchars($prod['category']); ?>
                                    </span>
                                    <div class="shop-name" style="font-size: 0.8rem; color: #7f8c8d; margin-top: 5px;"><i
                                            class="fas fa-store"></i>
                                        <?php echo htmlspecialchars($prod['shopname'] ?? 'Artisan Shop'); ?>
                                    </div>
                                    <button class="wishlist-btn" onclick="toggleWishlist(this, <?php echo $prod['id']; ?>)"
                                        title="Add to Wishlist"><i class="far fa-heart"></i></button>
                                </div>
                                <h3 class="product-title">
                                    <a href="product_detail.php?id=<?php echo $prod['id']; ?>" style="text-decoration: none; color: inherit;">
                                        <?php echo htmlspecialchars($prod['title']); ?>
                                    </a>
                                </h3>
                                <span class="product-price">RM
                                    <?php echo number_format($prod['price'], 2); ?>
                                </span>
                                <a href="buyer/inquiry_messages.php?seller_id=<?php echo urlencode($prod['seller_id']); ?>&product_id=<?php echo $prod['id']; ?>"
                                    class="btn-chat">
                                    <i class="fas fa-comment-dots"></i> Chat with Seller
                                </a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <!-- Footer -->
    <footer>
        <a href="#" class="footer-logo">Pasar<span>kraft</span>.</a>
        <div class="footer-nav">
            <a href="seller/login_seller.php">Seller Centre</a>
            <a href="admin/login_admin.php">Admin Login</a>
        </div>
        <div class="social-links">
            <a href="#"><i class="fab fa-instagram"></i></a>
            <a href="#"><i class="fab fa-facebook"></i></a>
            <a href="#"><i class="fab fa-twitter"></i></a>
        </div>
        <p class="copyright">&copy; 2026 Pasarkraft. Keeping Traditions Alive.</p>
    </footer>

    <script>
        function removeFromWishlist(btn, productId) {
            fetch('buyer/toggle_wishlist.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ product_id: productId })
            })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        const article = btn.closest('article');
                        article.style.transition = 'opacity 0.3s';
                        article.style.opacity = '0';
                        setTimeout(() => {
                            article.remove();
                            if (document.querySelectorAll('.product-card').length === 0) {
                                location.reload();
                            }
                        }, 300);
                    } else {
                        alert(data.message || 'Error occurred');
                    }
                });
        }

        function toggleWishlist(btn, productId) {
            fetch('buyer/toggle_wishlist.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ product_id: productId })
            })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        btn.classList.toggle('active');
                        const icon = btn.querySelector('i');
                        if (btn.classList.contains('active')) {
                            icon.classList.remove('far');
                            icon.classList.add('fas');
                            icon.style.color = '#e74c3c';
                        } else {
                            icon.classList.remove('fas');
                            icon.classList.add('far');
                            icon.style.color = '';
                        }
                        setTimeout(() => {
                            location.reload();
                        }, 400);
                    } else {
                        alert(data.message || 'Error occurred');
                    }
                });
        }
    </script>
</body>

</html>