<?php
session_start();
header("Cache-Control: no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");
require 'db_connect.php';

$unread_count = 0;
if (isset($_SESSION['user_id']) && isset($_SESSION['role']) && $_SESSION['role'] === 'buyer') {
    $unread_stmt = $conn->prepare("SELECT COUNT(*) as unread_count FROM messages WHERE receiver_id = ? AND is_read = 0");
    $unread_stmt->bind_param("i", $_SESSION['user_id']);
    $unread_stmt->execute();
    $unread_res = $unread_stmt->get_result();
    if ($unread_row = $unread_res->fetch_assoc()) {
        $unread_count = $unread_row['unread_count'];
    }
    $unread_stmt->close();
}

$user_wishlist = [];
if (isset($_SESSION['user_id']) && isset($_SESSION['role']) && $_SESSION['role'] === 'buyer') {
    $w_stmt = $conn->prepare("SELECT product_id FROM wishlist WHERE buyer_id = ?");
    $w_stmt->bind_param("i", $_SESSION['user_id']);
    $w_stmt->execute();
    $w_res = $w_stmt->get_result();
    while ($w_row = $w_res->fetch_assoc()) {
        $user_wishlist[] = $w_row['product_id'];
    }
    $w_stmt->close();
}


$products = [];
$stmt = $conn->prepare("SELECT p.id, p.seller_id, p.title, p.category, p.price, p.image_path, a.shopname FROM products p LEFT JOIN artisans a ON p.seller_id = a.user_id ORDER BY p.created_at DESC LIMIT 8");
$stmt->execute();
$res = $stmt->get_result();
if ($res && $res->num_rows > 0) {
    while($row = $res->fetch_assoc()) {
        $products[] = $row;
    }
}
$stmt->close();

$recommended_products = [];
if (isset($_SESSION['user_id']) && isset($_SESSION['role']) && $_SESSION['role'] === 'buyer') {
    $uid = $_SESSION['user_id'];
    
    // Fetch cached recommendations
    $rec_stmt = $conn->prepare("
        SELECT p.id, p.seller_id, p.title, p.category, p.price, p.image_path, a.shopname, r.score, r.recommendation_type
        FROM recommendations r
        JOIN products p ON r.product_id = p.id
        LEFT JOIN artisans a ON p.seller_id = a.user_id
        WHERE r.user_id = ? AND p.stock > 0
        ORDER BY r.score DESC
        LIMIT 4
    ");
    $rec_stmt->bind_param("i", $uid);
    $rec_stmt->execute();
    $rec_res = $rec_stmt->get_result();
    while ($row = $rec_res->fetch_assoc()) {
        $recommended_products[] = $row;
    }
    $rec_stmt->close();
    
    // If no recommendations are cached, generate them dynamically in real-time!
    if (empty($recommended_products)) {
        require_once 'recommender.php';
        $recEngine = new PasarKraftRecommender($conn);
        $recEngine->trainTfidf();
        $recEngine->trainSVD();
        $recs = $recEngine->getRecommendationsForUser($uid, 4);
        
        if (!empty($recs)) {
            // Cache them
            $ins_stmt = $conn->prepare("INSERT INTO recommendations (user_id, product_id, score, recommendation_type) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE score = VALUES(score)");
            foreach ($recs as $pid => $data) {
                $ins_stmt->bind_param("iids", $uid, $pid, $data['score'], $data['type']);
                $ins_stmt->execute();
            }
            $ins_stmt->close();
            
            // Re-fetch
            $rec_stmt = $conn->prepare("
                SELECT p.id, p.seller_id, p.title, p.category, p.price, p.image_path, a.shopname, r.score, r.recommendation_type
                FROM recommendations r
                JOIN products p ON r.product_id = p.id
                LEFT JOIN artisans a ON p.seller_id = a.user_id
                WHERE r.user_id = ? AND p.stock > 0
                ORDER BY r.score DESC
                LIMIT 4
            ");
            $rec_stmt->bind_param("i", $uid);
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
    <title>Pasarkraft | Malaysian Batik & Woodcraft</title>
    <meta name="description"
        content="Discover premium Malaysian Batik and handcrafted woodcrafts. Connect directly with sellers at Pasarkraft.">
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Playfair+Display:ital,wght@0,400;0,700;1,400&display=swap"
        rel="stylesheet">
    <!-- Font Awesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="styles.css?v=<?php echo time(); ?>">
</head>

<body>

    <!-- Navigation -->
    <header>
        <nav>
            <a href="#" class="logo">Pasar<span>kraft</span>.</a>
            <div class="header-search">
                <input type="text" placeholder="Search by product name, color, pattern...">
                <div class="search-controls">
                    <button class="filter-btn" onclick="toggleFilter()">
                        <i class="fas fa-sliders-h"></i>
                        <span>Filter</span>
                    </button>
                    <button class="search-btn"><i class="fas fa-search"></i></button>
                </div>
                <!-- Dropdown Menu -->
                <div class="search-dropdown">
                    <div class="dropdown-section">
                        <h4>Collections</h4>
                        <div class="collection-list">
                            <a href="#"><i class="fas fa-tshirt"></i> Batik Fashion</a>
                            <a href="woodcraft_page.php"><i class="fas fa-couch"></i> Wood Furniture</a>
                            <a href="woodcraft_page.php"><i class="fas fa-tree"></i> Handcrafted wood</a>
                            <a href="#"><i class="fas fa-scroll"></i> Batik Textile</a>
                        </div>
                    </div>
                    <div class="dropdown-section">
                        <h4>Trending Tags</h4>
                        <div class="tags">
                            <span>#Handmade</span>
                            <span>#EcoFriendly</span>
                            <span>#Gifts</span>
                            <span>#Vintage</span>
                        </div>
                    </div>
                </div>
                <!-- Filter Dropdown -->
                <div class="filter-dropdown" id="filterDropdown">
                    <div class="filter-section">
                        <h4>Type & Pattern</h4>
                        <input type="text" placeholder="e.g. Batik Flora, Jati Wood..." class="filter-input">
                        <div class="tags" style="margin-top: 10px;">
                            <span class="tag-option">Abstract</span>
                            <span class="tag-option">Floral</span>
                            <span class="tag-option">Geometric</span>
                            <span class="tag-option">Mahogany</span>
                            <span class="tag-option">Teak</span>
                        </div>
                    </div>
                    <div class="filter-section">
                        <h4>Color Palette</h4>
                        <div class="color-options">
                            <span class="color-circle" style="background:#2c3e50;" title="Dark Blue"></span>
                            <span class="color-circle" style="background:#d35400;" title="Terracotta"></span>
                            <span class="color-circle" style="background:#27ae60;" title="Green"></span>
                            <span class="color-circle" style="background:#8e44ad;" title="Purple"></span>
                            <span class="color-circle" style="background:#000000;" title="Black"></span>
                            <span class="color-circle" style="background:#ffffff; border:1px solid #ddd;"
                                title="White"></span>
                        </div>
                    </div>
                    <div class="filter-section">
                        <button class="btn-apply-filter" onclick="toggleFilter()">Apply Filters</button>
                    </div>
                </div>

                <script>
                    function toggleFilter() {
                        const dropdown = document.getElementById('filterDropdown');
                        const headerSearch = document.querySelector('.header-search');

                        dropdown.classList.toggle('show-filter');
                        headerSearch.classList.toggle('filter-active');
                    }
                    // Optional: Close when clicking outside
                    document.addEventListener('click', function (event) {
                        const filterDropdown = document.getElementById('filterDropdown');
                        const filterBtn = document.querySelector('.filter-btn');
                        const headerSearch = document.querySelector('.header-search');

                        // Check if click is outside the dropdown AND the filter button
                        if (!filterDropdown.contains(event.target) && !filterBtn.contains(event.target)) {
                            filterDropdown.classList.remove('show-filter');
                            if (headerSearch) headerSearch.classList.remove('filter-active');
                        }
                    });
                </script>
            </div>
            <div class="nav-links">

                <a href="batik_page.php">Batik</a>
                <a href="woodcraft_page.php">Woodcraft</a>
                <a href="#about">About</a>
                <a href="buyer/chat_history.php">Chat history <?php if(isset($unread_count) && $unread_count > 0) echo '<span style="background: red; color: white; border-radius: 50%; padding: 2px 6px; font-size: 0.75rem; margin-left: 5px;">'.$unread_count.'</span>'; ?></a>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="logout.php" class="nav-login"
                        onclick="return confirm('Are you sure you want to log out?');">Logout</a>
                <?php else: ?>
                    <a href="buyer/login_buyer.php" class="nav-login">Login</a>
                <?php endif; ?>
                <a href="wishlist_page.php" class="wishlist-icon">
                    <i class="far fa-heart"></i>
                    <span class="tooltip">Wishlist</span>
                </a>
                <a href="buyer/buyer_profile.php" class="profile-icon">
                    <i class="far fa-user-circle"></i>
                    <span class="tooltip">Account</span>
                </a>
            </div>
            <!-- Mobile Menu Icon could go here -->
        </nav>
    </header>

    <!-- Hero Section -->
    <section id="home" class="hero">
        <div class="hero-content">
            <h1>Melestari Warisan,<br>Mengukir Keunikan</h1>
            <p>Authentic Malaysian Batik & Handcrafted Wood Artistry.</p>
            <a href="#products" class="btn">Explore Collection</a>
        </div>
    </section>

    <!-- About Section -->
    <section id="about" class="about">
        <h2>Curated Malaysian Craftsmanship</h2>
        <p>Pasarkraft is a marketplace dedicated to the preservation of Malaysia's finest traditional arts. We bridge
            the gap between local artisans and admirers of Batik and Woodcraft, bringing heritage directly through
            personal connection.</p>
    </section>

    <!-- Recommended for You Section (AI Personalized) -->
    <?php if (!empty($recommended_products)): ?>
    <section id="ai-recommendations" class="products" style="background: #faf8f5; padding: 4rem 2rem; border-bottom: 1px solid #f1ece4;">
        <div class="section-header">
            <h2>Recommended for You</h2>
            <span></span>
            <p><i class="fas fa-magic" style="color: #d35400; margin-right: 5px;"></i> AI Personalized matches based on your interests.</p>
        </div>

        <div class="product-grid animate-fade-in">
            <?php foreach($recommended_products as $prod): 
                $img_src = htmlspecialchars($prod['image_path'] ?? '');
                if (empty($img_src)) {
                    $img_src = 'png/batik_shirt.png';
                } elseif (strpos($img_src, '/') === false) {
                    $img_src = 'png/' . $img_src;
                }
                
                $match_percentage = round($prod['score'] * 100);
                if ($match_percentage < 40) $match_percentage += 45; // Clamp for attractive visualization
                if ($match_percentage > 99) $match_percentage = 99;
            ?>
            <article class="product-card">
                <div class="product-image" style="position: relative;">
                    <img src="<?php echo $img_src; ?>" alt="<?php echo htmlspecialchars($prod['title']); ?>">
                    <!-- AI Match Tag -->
                    <span class="ai-match-badge" style="position: absolute; top: 15px; left: 15px; background: rgba(44, 62, 80, 0.95); color: white; padding: 6px 12px; font-size: 0.75rem; font-weight: 600; border-radius: 20px; box-shadow: 0 4px 8px rgba(0,0,0,0.15); display: flex; align-items: center; gap: 5px; backdrop-filter: blur(5px); border: 1px solid rgba(255,255,255,0.1); z-index: 10;">
                        <i class="fas fa-brain" style="color: #e67e22;"></i> <?php echo $match_percentage; ?>% Match
                    </span>
                </div>
                <?php
                    $is_wished = in_array($prod['id'], $user_wishlist);
                    $wishlist_class = $is_wished ? 'active' : '';
                    $heart_icon = $is_wished ? 'fas fa-heart' : 'far fa-heart';
                    $heart_color = $is_wished ? 'color: #e74c3c;' : '';
                ?>
                <div class="product-info">
                    <div class="product-meta">
                        <span class="product-category"><?php echo htmlspecialchars($prod['category']); ?></span>
                        <div class="shop-name" style="font-size: 0.8rem; color: #7f8c8d; margin-top: 5px;"><i class="fas fa-store"></i> <?php echo htmlspecialchars($prod['shopname'] ?? 'Artisan Shop'); ?></div>
                        <button class="wishlist-btn <?php echo $wishlist_class; ?>" onclick="toggleWishlist(this, <?php echo $prod['id']; ?>)" title="Add to Wishlist"><i
                                class="<?php echo $heart_icon; ?>" style="<?php echo $heart_color; ?>"></i></button>
                    </div>
                    <h3 class="product-title"><?php echo htmlspecialchars($prod['title']); ?></h3>
                    <span class="product-price">RM <?php echo number_format($prod['price'], 2); ?></span>
                    <a href="buyer/chat_history.php?chat_with=<?php echo urlencode($prod['seller_id']); ?>&product_id=<?php echo $prod['id']; ?>" class="btn-chat">
                        <i class="fas fa-comment-dots"></i> Chat with Seller
                    </a>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- Products Section -->
    <?php if (!empty($products)): ?>
    <section id="products" class="products">
        <div class="section-header">
            <h2>Featured Masterpieces</h2>
            <span></span>
            <p>Direct from the artisan's workshop to you.</p>
        </div>

        <div class="product-grid">
            <?php foreach($products as $prod): 
                $img_src = htmlspecialchars($prod['image_path'] ?? '');
                if (empty($img_src)) {
                    $img_src = 'png/batik_shirt.png';
                } elseif (strpos($img_src, '/') === false) {
                    $img_src = 'png/' . $img_src;
                }
            ?>
            <article class="product-card">
                <div class="product-image">
                    <img src="<?php echo $img_src; ?>" alt="<?php echo htmlspecialchars($prod['title']); ?>">
                </div>
                <?php
                    $is_wished = in_array($prod['id'], $user_wishlist);
                    $wishlist_class = $is_wished ? 'active' : '';
                    $heart_icon = $is_wished ? 'fas fa-heart' : 'far fa-heart';
                    $heart_color = $is_wished ? 'color: #e74c3c;' : '';
                ?>
                <div class="product-info">
                    <div class="product-meta">
                        <span class="product-category"><?php echo htmlspecialchars($prod['category']); ?></span>
                        <div class="shop-name" style="font-size: 0.8rem; color: #7f8c8d; margin-top: 5px;"><i class="fas fa-store"></i> <?php echo htmlspecialchars($prod['shopname'] ?? 'Artisan Shop'); ?></div>
                        <button class="wishlist-btn <?php echo $wishlist_class; ?>" onclick="toggleWishlist(this, <?php echo $prod['id']; ?>)" title="Add to Wishlist"><i
                                class="<?php echo $heart_icon; ?>" style="<?php echo $heart_color; ?>"></i></button>
                    </div>
                    <h3 class="product-title"><?php echo htmlspecialchars($prod['title']); ?></h3>
                    <span class="product-price">RM <?php echo number_format($prod['price'], 2); ?></span>
                    <a href="buyer/chat_history.php?chat_with=<?php echo urlencode($prod['seller_id']); ?>&product_id=<?php echo $prod['id']; ?>" class="btn-chat">
                        <i class="fas fa-comment-dots"></i> Chat with Seller
                    </a>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- Qualities Section -->
    <section class="qualities">
        <div class="quality-grid">
            <div class="quality-item">
                <i class="fas fa-gem icon"></i>
                <h3>Authentic Quality</h3>
                <p>100% genuine materials sourced locally from Malaysian artisans.</p>
            </div>
            <div class="quality-item">
                <i class="fas fa-hand-holding-heart icon"></i>
                <h3>Handcrafted</h3>
                <p>Every piece tells a story, made with passion and heritage skills.</p>
            </div>
            <div class="quality-item">
                <i class="fas fa-comments icon"></i>
                <h3>Direct Connection</h3>
                <p>Chat directly with sellers to customize or inquire about your purchase.</p>
            </div>
        </div>
    </section>



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

</body>

<script>
    function toggleWishlist(btn, productId) {
        fetch('buyer/toggle_wishlist.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ product_id: productId })
        })
        .then(response => response.json())
        .then(data => {
            if(data.status === 'success') {
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
            } else {
                alert(data.message || 'Error occurred');
            }
        });
    }
</script>

</html>