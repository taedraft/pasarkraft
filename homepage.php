<?php
session_start();
header("Cache-Control: no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");
require 'db_connect.php';
require 'recommender_bridge.php';

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

$is_buyer = isset($_SESSION['user_id']) && isset($_SESSION['role']) && $_SESSION['role'] === 'buyer';

$filter_query = trim($_GET['q'] ?? '');
$filter_category = trim($_GET['category'] ?? '');
$filter_material = trim($_GET['material'] ?? '');
$filter_pattern = trim($_GET['pattern'] ?? '');
$filter_color = trim($_GET['color'] ?? '');

$products = [];
$where = [];
$params = [];
$types = '';

// Check if approval_status column exists using SHOW COLUMNS (works on InfinityFree)
$has_approval_col = false;
$appr_col_res = $conn->query("SHOW COLUMNS FROM artisans LIKE 'approval_status'");
if ($appr_col_res && $appr_col_res->num_rows > 0) {
    $has_approval_col = true;
}

// Base filters always applied: in-stock only + approved sellers only
$base_where = ["p.stock > 0"];
if ($has_approval_col) {
    $base_where[] = "a.approval_status = 'approved'";
}

if ($filter_query !== '') {
    $like = '%' . $filter_query . '%';
    $where[] = "(p.title LIKE ? OR p.description LIKE ? OR p.tags LIKE ?)";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types .= 'sss';
}

if ($filter_category !== '') {
    $where[] = "p.category = ?";
    $params[] = $filter_category;
    $types .= 's';
}

if ($filter_material !== '') {
    $where[] = "p.material LIKE ?";
    $params[] = '%' . $filter_material . '%';
    $types .= 's';
}

if ($filter_pattern !== '') {
    $like_pattern = '%' . $filter_pattern . '%';
    $where[] = "(p.tags LIKE ? OR p.technique LIKE ? OR p.style LIKE ? OR p.subcategory LIKE ?)";
    $params[] = $like_pattern;
    $params[] = $like_pattern;
    $params[] = $like_pattern;
    $params[] = $like_pattern;
    $types .= 'ssss';
}

if ($filter_color !== '') {
    $where[] = "p.color LIKE ?";
    $params[] = '%' . $filter_color . '%';
    $types .= 's';
}

$sql = "SELECT p.id, p.seller_id, p.title, p.category, p.price, p.image_path, a.shopname FROM products p LEFT JOIN artisans a ON p.seller_id = a.user_id";

// Merge base filters (stock, approval) with any search/filter conditions
$all_where = array_merge($base_where, $where);
$sql .= " WHERE " . implode(" AND ", $all_where);
$sql .= " ORDER BY p.created_at DESC LIMIT 8";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$prod_res = $stmt->get_result();
while ($row = $prod_res->fetch_assoc()) {
    $products[] = $row;
}
$stmt->close();

$recommended_products = [];
$recommendation_source = '';
if (isset($_SESSION['user_id']) && isset($_SESSION['role']) && $_SESSION['role'] === 'buyer') {
    $uid = $_SESSION['user_id'];

    $python_recommendations = pk_run_python_recommender($uid, 8, [
        'host' => $servername,
        'user' => $username,
        'password' => $password,
        'database' => $dbname,
    ]);
    $recommended_products = pk_fetch_recommended_products_by_ids($conn, $python_recommendations);
    if (!empty($recommended_products)) {
        $recommendation_source = 'hybrid_py';
    }

    if (empty($recommended_products)) {
        $rec_stmt = $conn->prepare("
            SELECT p.id, p.seller_id, p.title, p.category, p.price, p.image_path, a.shopname, r.score, r.recommendation_type
            FROM recommendations r
            JOIN products p ON r.product_id = p.id
            LEFT JOIN artisans a ON p.seller_id = a.user_id
            WHERE r.user_id = ? AND p.stock > 0 AND r.recommendation_type IN ('hybrid_py_daily', 'hybrid_py', 'hybrid')
            ORDER BY CASE r.recommendation_type
                WHEN 'hybrid_py_daily' THEN 1
                WHEN 'hybrid_py' THEN 2
                WHEN 'hybrid' THEN 3
                WHEN 'hybrid' THEN 4
            END,
            r.generated_at DESC,
            r.score DESC
            LIMIT 8
        ");
        $rec_stmt->bind_param("i", $uid);
        $rec_stmt->execute();
        $rec_res = $rec_stmt->get_result();
        while ($row = $rec_res->fetch_assoc()) {
            $recommended_products[] = $row;
            if ($recommendation_source === '') {
                $recommendation_source = $row['recommendation_type'] ?? 'cached';
            }
        }
        $rec_stmt->close();
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
            <form class="header-search" method="GET" action="homepage.php">
                <input type="text" name="q" placeholder="Search by product name, color, pattern..."
                    value="<?php echo htmlspecialchars($filter_query); ?>">
                <div class="search-controls">
                    <button class="filter-btn" type="button" onclick="toggleFilter()">
                        <i class="fas fa-sliders-h"></i>
                        <span>Filter</span>
                    </button>
                    <button class="search-btn" type="submit"><i class="fas fa-search"></i></button>
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
                        <h4>Category</h4>
                        <select name="category" class="filter-input">
                            <option value="">All</option>
                            <option value="Batik" <?php echo $filter_category === 'Batik' ? 'selected' : ''; ?>>Batik
                            </option>
                            <option value="Woodcraft" <?php echo $filter_category === 'Woodcraft' ? 'selected' : ''; ?>>
                                Woodcraft</option>
                        </select>
                    </div>
                    <div class="filter-section">
                        <h4>Type & Pattern</h4>
                        <input type="text" name="pattern" placeholder="e.g. Batik Flora, Jati Wood..."
                            class="filter-input" value="<?php echo htmlspecialchars($filter_pattern); ?>">
                        <div class="tags" style="margin-top: 10px;">
                            <span class="tag-option">Abstract</span>
                            <span class="tag-option">Floral</span>
                            <span class="tag-option">Geometric</span>
                            <span class="tag-option">Mahogany</span>
                            <span class="tag-option">Teak</span>
                        </div>
                    </div>
                    <div class="filter-section">
                        <h4>Material</h4>
                        <input type="text" name="material" placeholder="e.g. Cotton, Teak Wood" class="filter-input"
                            value="<?php echo htmlspecialchars($filter_material); ?>">
                    </div>
                    <div class="filter-section">
                        <h4>Color Palette</h4>
                        <div class="color-options">
                            <button type="button" class="color-circle" style="background:#2c3e50;" title="Dark Blue"
                                onclick="setFilterColor('Dark Blue')"></button>
                            <button type="button" class="color-circle" style="background:#d35400;" title="Terracotta"
                                onclick="setFilterColor('Terracotta')"></button>
                            <button type="button" class="color-circle" style="background:#27ae60;" title="Green"
                                onclick="setFilterColor('Green')"></button>
                            <button type="button" class="color-circle" style="background:#8e44ad;" title="Purple"
                                onclick="setFilterColor('Purple')"></button>
                            <button type="button" class="color-circle" style="background:#000000;" title="Black"
                                onclick="setFilterColor('Black')"></button>
                            <button type="button" class="color-circle"
                                style="background:#ffffff; border:1px solid #ddd;" title="White"
                                onclick="setFilterColor('White')"></button>
                        </div>
                        <input type="hidden" name="color" id="filterColorInput"
                            value="<?php echo htmlspecialchars($filter_color); ?>">
                        <div style="margin-top: 8px; font-size: 0.8rem; color: #7f8c8d;">Selected: <span
                                id="filterColorLabel"><?php echo $filter_color !== '' ? htmlspecialchars($filter_color) : 'Any'; ?></span>
                        </div>
                    </div>
                    <div class="filter-section">
                        <button class="btn-apply-filter" type="submit" onclick="toggleFilter()">Apply Filters</button>
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
                    function setFilterColor(color) {
                        const input = document.getElementById('filterColorInput');
                        const label = document.getElementById('filterColorLabel');
                        input.value = color;
                        if (label) label.textContent = color;
                    }
                </script>
            </form>
            <div class="nav-links">

                <a href="batik_page.php">Batik</a>
                <a href="woodcraft_page.php">Woodcraft</a>
                <a href="#about">About</a>
                <?php
                $chat_href = (isset($_SESSION['user_id']) && isset($_SESSION['role']) && $_SESSION['role'] === 'buyer')
                    ? 'buyer/inquiry_messages.php'
                    : 'buyer/login_buyer.php?from=inquiries';
                ?>
                <a href="<?php echo $chat_href; ?>">Chat History
                    <?php if (isset($unread_count) && $unread_count > 0)
                        echo '<span style="background: red; color: white; border-radius: 50%; padding: 2px 6px; font-size: 0.75rem; margin-left: 5px;">' . $unread_count . '</span>'; ?></a>
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
        <section id="ai-recommendations" class="products"
            style="background: #faf8f5; padding: 4rem 2rem; border-bottom: 1px solid #f1ece4;">
            <div class="section-header">
                <h2>Recommended for You</h2>
                <span></span>
                <p><i class="fas fa-magic" style="color: #d35400; margin-right: 5px;"></i> AI Personalized matches based on
                    your interests.<?php if ($recommendation_source === 'hybrid_py'): ?> <span
                            style="font-size:0.8rem;color:#64748b;">(TF-IDF + Matrix Factorization via
                            Python)</span><?php endif; ?></p>
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
                        $match_percentage += 45; // Clamp for attractive visualization
                    if ($match_percentage > 99)
                        $match_percentage = 99;
                    ?>
                    <article class="product-card" data-product-id="<?php echo $prod['id']; ?>">
                        <div class="product-image" style="position: relative;">
                            <img src="<?php echo $img_src; ?>" alt="<?php echo htmlspecialchars($prod['title']); ?>">
                            <!-- AI Match Tag -->
                            <span class="ai-match-badge"
                                style="position: absolute; top: 15px; left: 15px; background: rgba(44, 62, 80, 0.95); color: white; padding: 6px 12px; font-size: 0.75rem; font-weight: 600; border-radius: 20px; box-shadow: 0 4px 8px rgba(0,0,0,0.15); display: flex; align-items: center; gap: 5px; backdrop-filter: blur(5px); border: 1px solid rgba(255,255,255,0.1); z-index: 10;">
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
                                <div class="shop-name" style="font-size: 0.8rem; color: #7f8c8d; margin-top: 5px;"><i
                                        class="fas fa-store"></i>
                                    <?php echo htmlspecialchars($prod['shopname'] ?? 'Artisan Shop'); ?></div>
                                <button class="wishlist-btn <?php echo $wishlist_class; ?>"
                                    onclick="toggleWishlist(this, <?php echo $prod['id']; ?>)" title="Add to Wishlist"><i
                                        class="<?php echo $heart_icon; ?>" style="<?php echo $heart_color; ?>"></i></button>
                            </div>
                            <h3 class="product-title"><?php echo htmlspecialchars($prod['title']); ?></h3>
                            <span class="product-price">RM <?php echo number_format($prod['price'], 2); ?></span>
                            <a href="<?php echo $is_buyer ? 'buyer/inquiry_messages.php?seller_id='.urlencode($prod['seller_id']).'&product_id='.$prod['id'] : 'buyer/login_buyer.php?from=inquiries'; ?>"
                                class="btn-chat" data-product-id="<?php echo $prod['id']; ?>">
                                <i class="fas fa-comment-dots"></i> Chat with Seller
                            </a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <!-- Products Section -->
    <section id="products" class="products">
        <div class="section-header">
            <h2>Featured Masterpieces</h2>
            <span></span>
            <p>Direct from the artisan's workshop to you.</p>
        </div>

        <?php if (!empty($products)): ?>
            <div class="product-grid">
                <?php foreach ($products as $prod):
                    $img_raw = $prod['image_path'] ?? '';
                    if (empty($img_raw)) {
                        // No image uploaded — use category-based placeholder
                        $img_src = ($prod['category'] === 'Woodcraft') ? 'png/wood_art.png' : 'png/batik_shirt.png';
                    } elseif (strpos($img_raw, '/') === false) {
                        // Legacy format: just a filename, look in png/
                        $img_src = 'png/' . htmlspecialchars($img_raw);
                    } else {
                        // Modern format: relative path like uploads/filename.jpg
                        $img_src = htmlspecialchars($img_raw);
                    }
                    ?>
                    <article class="product-card" data-product-id="<?php echo $prod['id']; ?>">
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
                                <div class="shop-name" style="font-size: 0.8rem; color: #7f8c8d; margin-top: 5px;"><i
                                        class="fas fa-store"></i>
                                    <?php echo htmlspecialchars($prod['shopname'] ?? 'Artisan Shop'); ?></div>
                                <button class="wishlist-btn <?php echo $wishlist_class; ?>"
                                    onclick="toggleWishlist(this, <?php echo $prod['id']; ?>)" title="Add to Wishlist"><i
                                        class="<?php echo $heart_icon; ?>" style="<?php echo $heart_color; ?>"></i></button>
                            </div>
                            <h3 class="product-title"><?php echo htmlspecialchars($prod['title']); ?></h3>
                            <span class="product-price">RM <?php echo number_format($prod['price'], 2); ?></span>
                            <a href="<?php echo $is_buyer ? 'buyer/inquiry_messages.php?seller_id='.urlencode($prod['seller_id']).'&product_id='.$prod['id'] : 'buyer/login_buyer.php?from=inquiries'; ?>"
                                class="btn-chat" data-product-id="<?php echo $prod['id']; ?>">
                                <i class="fas fa-comment-dots"></i> Chat with Seller
                            </a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="no-products-found"
                style="text-align: center; padding: 4rem 2rem; background: #fff; border-radius: 12px; border: 1px dashed #ddd; max-width: 600px; margin: 0 auto 3rem;">
                <i class="fas fa-search" style="font-size: 3rem; color: #bdc3c7; margin-bottom: 1rem; display: block;"></i>
                <h3
                    style="font-size: 1.5rem; color: var(--primary-color); margin-bottom: 0.5rem; font-family: var(--font-heading);">
                    No Products Found</h3>
                <p style="color: #7f8c8d; margin-bottom: 1.5rem; font-size: 0.95rem;">We couldn't find any products matching
                    your search or filters. Try adjusting your selections or clearing the filters.</p>
                <a href="homepage.php" class="btn"
                    style="display: inline-block; padding: 10px 24px; text-decoration: none; border-radius: 6px; font-weight: 500;">Clear
                    Filters</a>
            </div>
        <?php endif; ?>
    </section>

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

    <div id="pk-chatbot" class="pk-chatbot">
        <button type="button" id="pk-chatbot-toggle" class="pk-chatbot-toggle" aria-expanded="false">
            <i class="fas fa-comment-dots"></i>
            <span>Ask PasarKraft</span>
        </button>
        <div class="pk-chatbot-panel" hidden>
            <div class="pk-chatbot-header">
                <strong>PasarKraft Guide</strong>
                <button type="button" id="pk-chatbot-close" aria-label="Close chatbot">&times;</button>
            </div>
            <div class="pk-chatbot-body" id="pk-chatbot-body">
                <div class="pk-chatbot-message bot">Hi! I am the PasarKraft AI Guide. Ask me anything about Batik,
                    Woodcraft, or how to contact our artisans!</div>
            </div>
            <div class="pk-chatbot-actions">
                <button type="button" data-topic="batik">What is Batik?</button>
                <button type="button" data-topic="woodcraft">What is Woodcraft?</button>
                <button type="button" data-topic="contact">How to contact a carver?</button>
            </div>
            <div class="pk-chatbot-input-area"
                style="display: flex; border-top: 1px solid #eee; padding: 8px 12px; gap: 8px; background: #fff;">
                <input type="text" id="pk-chatbot-input" placeholder="Type a message..."
                    style="flex: 1; border: 1px solid #ddd; padding: 6px 12px; border-radius: 20px; font-size: 0.85rem; outline: none; font-family: var(--font-body);">
                <button type="button" id="pk-chatbot-send"
                    style="background: #2c3e50; color: #fff; border: none; width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: background 0.2s;"><i
                        class="fas fa-paper-plane" style="font-size: 0.8rem;"></i></button>
            </div>
        </div>
    </div>



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
                } else {
                    alert(data.message || 'Error occurred');
                }
            });
    }

    function pkLogInteraction(productId, interactionType, interactionValue) {
        fetch('api/log_interaction.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                product_id: productId,
                interaction_type: interactionType,
                interaction_value: interactionValue || 0
            })
        }).catch(function () { });
    }

    document.addEventListener('DOMContentLoaded', function () {
        const cards = document.querySelectorAll('.product-card[data-product-id]');
        if (!('IntersectionObserver' in window)) {
            cards.forEach(function (card) {
                pkLogInteraction(card.getAttribute('data-product-id'), 'view', 1.0);
            });
        } else {

            const seenKey = 'pk_viewed_products';
            const seenProducts = new Set(JSON.parse(localStorage.getItem(seenKey) || '[]'));
            const observer = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (!entry.isIntersecting) return;
                    const productId = entry.target.getAttribute('data-product-id');
                    if (!productId || seenProducts.has(productId)) return;
                    seenProducts.add(productId);
                    localStorage.setItem(seenKey, JSON.stringify(Array.from(seenProducts)));
                    pkLogInteraction(productId, 'view', 1.0);
                    observer.unobserve(entry.target);
                });
            }, { threshold: 0.55 });

            cards.forEach(function (card) {
                observer.observe(card);
            });
        }

        document.querySelectorAll('.btn-chat[data-product-id]').forEach(function (link) {
            link.addEventListener('click', function () {
                pkLogInteraction(link.getAttribute('data-product-id'), 'click', 2.0);
            });
        });
    });
</script>

<style>
    .pk-chatbot {
        position: fixed;
        right: 24px;
        bottom: 24px;
        z-index: 50;
        font-family: var(--font-body);
    }

    .pk-chatbot-toggle {
        display: flex;
        align-items: center;
        gap: 8px;
        background: #2c3e50;
        color: #fff;
        border: none;
        padding: 12px 16px;
        border-radius: 999px;
        cursor: pointer;
        box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
    }

    .pk-chatbot-panel {
        width: 300px;
        background: #fff;
        border: 1px solid #eee;
        border-radius: 12px;
        box-shadow: 0 12px 24px rgba(0, 0, 0, 0.12);
        margin-top: 12px;
        overflow: hidden;
    }

    .pk-chatbot-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 12px 14px;
        background: #f8f4ef;
    }

    .pk-chatbot-header button {
        background: none;
        border: none;
        font-size: 1.2rem;
        cursor: pointer;
    }

    .pk-chatbot-body {
        padding: 12px 14px;
        height: 240px;
        overflow-y: auto;
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .pk-chatbot-message {
        padding: 8px 12px;
        border-radius: 12px;
        font-size: 0.85rem;
        line-height: 1.4;
        word-break: break-word;
    }

    .pk-chatbot-message.bot {
        background: #f1f5f9;
        color: #2c3e50;
        align-self: flex-start;
    }

    .pk-chatbot-message.user {
        background: #2c3e50;
        color: #fff;
        align-self: flex-end;
    }

    .pk-chatbot-message.typing {
        font-style: italic;
        color: #7f8c8d;
        background: #f1f5f9;
        align-self: flex-start;
    }

    .pk-chatbot-actions {
        display: grid;
        gap: 8px;
        padding: 12px 14px;
        border-top: 1px solid #eee;
        background: #faf9f6;
    }

    .pk-chatbot-actions button {
        padding: 8px;
        border-radius: 8px;
        border: 1px solid #ddd;
        background: #fff;
        cursor: pointer;
        font-size: 0.8rem;
        text-align: left;
        transition: all 0.2s;
    }

    .pk-chatbot-actions button:hover {
        border-color: #2c3e50;
        background: #fdfaf6;
    }

    .pk-chatbot-input-area button:hover {
        background: var(--accent-color, #d35400) !important;
    }

    @media (max-width: 640px) {
        .pk-chatbot-panel {
            width: 90vw;
        }
    }
</style>

<script>
    (function () {
        const toggle = document.getElementById('pk-chatbot-toggle');
        const closeBtn = document.getElementById('pk-chatbot-close');
        const panel = document.querySelector('.pk-chatbot-panel');
        const body = document.getElementById('pk-chatbot-body');
        const actions = document.querySelectorAll('.pk-chatbot-actions button');
        const inputEl = document.getElementById('pk-chatbot-input');
        const sendBtn = document.getElementById('pk-chatbot-send');

        let history = [];

        function addMessage(text, isUser) {
            const div = document.createElement('div');
            div.className = 'pk-chatbot-message ' + (isUser ? 'user' : 'bot');
            div.textContent = text;
            body.appendChild(div);
            body.scrollTop = body.scrollHeight;
        }

        function sendMessage(text) {
            if (!text || !text.trim()) return;
            const messageText = text.trim();
            addMessage(messageText, true);

            // Add typing indicator
            const typingDiv = document.createElement('div');
            typingDiv.className = 'pk-chatbot-message bot typing';
            typingDiv.textContent = 'PasarKraft Guide is typing...';
            body.appendChild(typingDiv);
            body.scrollTop = body.scrollHeight;

            // Prepare URL-encoded form data parameters to bypass InfinityFree AES firewall
            const formData = new URLSearchParams();
            formData.append('message', messageText);
            formData.append('history', JSON.stringify(history));

            // Call backend API matching traditional form formatting
            fetch('api/ai_guide.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: formData.toString()
            })
                .then(res => res.json())
                .then(data => {
                    typingDiv.remove();
                    if (data.status === 'success') {
                        addMessage(data.reply, false);
                        history.push({ role: 'user', text: messageText });
                        history.push({ role: 'model', text: data.reply });
                        if (history.length > 20) {
                            history.shift();
                            history.shift();
                        }
                    } else {
                        addMessage('Sorry, I encountered an error: ' + (data.message || 'Unknown error'), false);
                    }
                })
                .catch(err => {
                    typingDiv.remove();
                    addMessage('Sorry, I cannot connect to the chatbot server right now.', false);
                });
        }

        function togglePanel(show) {
            const isOpen = show !== undefined ? show : panel.hasAttribute('hidden');
            if (isOpen) {
                panel.removeAttribute('hidden');
                toggle.setAttribute('aria-expanded', 'true');
                body.scrollTop = body.scrollHeight;
            } else {
                panel.setAttribute('hidden', 'hidden');
                toggle.setAttribute('aria-expanded', 'false');
            }
        }

        toggle.addEventListener('click', function () {
            togglePanel();
        });

        closeBtn.addEventListener('click', function () {
            togglePanel(false);
        });

        actions.forEach(function (btn) {
            btn.addEventListener('click', function () {
                const topicText = btn.textContent;
                sendMessage(topicText);
            });
        });

        sendBtn.addEventListener('click', function () {
            const text = inputEl.value;
            inputEl.value = '';
            sendMessage(text);
        });

        inputEl.addEventListener('keypress', function (e) {
            if (e.key === 'Enter') {
                const text = inputEl.value;
                inputEl.value = '';
                sendMessage(text);
            }
        });
    })();
</script>

</html>