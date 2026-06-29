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


// Fetch Woodcraft products — approved sellers, in-stock only
$sort = $_GET['sort'] ?? 'featured';
$order_sql = "ORDER BY p.created_at DESC";
if ($sort === 'price_low') {
    $order_sql = "ORDER BY p.price ASC";
} elseif ($sort === 'price_high') {
    $order_sql = "ORDER BY p.price DESC";
} elseif ($sort === 'new') {
    $order_sql = "ORDER BY p.created_at DESC";
}

// Check if approval_status column exists using SHOW COLUMNS (works on InfinityFree)
$has_approval_col = false;
$appr_col_res = $conn->query("SHOW COLUMNS FROM artisans LIKE 'approval_status'");
if ($appr_col_res && $appr_col_res->num_rows > 0) {
    $has_approval_col = true;
}

// Filter parameters from URL
$selected_subs = $_GET['subcategories'] ?? [];
$selected_techs = $_GET['techniques'] ?? [];
$max_price = isset($_GET['max_price']) ? floatval($_GET['max_price']) : 10000;
$selected_colors = $_GET['colors'] ?? [];
$filter_color = $_GET['color'] ?? '';
if (empty($selected_colors) && !empty($filter_color)) {
    $selected_colors = [$filter_color];
}

$filter_query = trim($_GET['q'] ?? '');
$filter_material = trim($_GET['material'] ?? '');
$filter_pattern = trim($_GET['pattern'] ?? '');

// Build dynamic WHERE clause
$where_clauses = ["p.category = 'Woodcraft'", "p.stock > 0", "p.price <= ?"];
$params = [$max_price];
$types = "d";

if ($has_approval_col) {
    $where_clauses[] = "a.approval_status = 'approved'";
}

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

if (!empty($selected_subs)) {
    $sub_clauses = [];
    foreach ($selected_subs as $s) {
        if ($s === "Furniture") {
            $sub_clauses[] = "(p.subcategory = 'Furniture' OR p.subcategory LIKE '%Furniture%' OR p.title LIKE '%Furniture%' OR p.title LIKE '%Table%' OR p.title LIKE '%Stool%' OR p.title LIKE '%Chair%' OR p.title LIKE '%Bench%' OR p.title LIKE '%Couch%' OR p.title LIKE '%Cabinet%' OR p.title LIKE '%Desk%')";
        } elseif ($s === "Home Decor") {
            $sub_clauses[] = "(p.subcategory = 'Home Decor' OR p.subcategory LIKE '%Decor%' OR p.title LIKE '%Decor%' OR p.title LIKE '%Plaque%' OR p.title LIKE '%Sculpt%' OR p.title LIKE '%Art%' OR p.title LIKE '%Frame%' OR p.title LIKE '%Mirror%' OR p.title LIKE '%Vase%' OR p.title LIKE '%Clock%')";
        } elseif ($s === "Kitchenware") {
            $sub_clauses[] = "(p.subcategory = 'Kitchenware' OR p.subcategory LIKE '%Kitchen%' OR p.title LIKE '%Kitchen%' OR p.title LIKE '%Plate%' OR p.title LIKE '%Bowl%' OR p.title LIKE '%Tray%' OR p.title LIKE '%Spoon%' OR p.title LIKE '%Fork%' OR p.title LIKE '%Utensil%' OR p.title LIKE '%Board%' OR p.title LIKE '%Coaster%')";
        } elseif ($s === "Traditional Carving") {
            $sub_clauses[] = "(p.subcategory = 'Traditional Carving' OR p.subcategory LIKE '%Carving%' OR p.title LIKE '%Carving%' OR p.title LIKE '%Carved%' OR p.title LIKE '%Relief%' OR p.title LIKE '%Malay%')";
        } elseif ($s === "Souvenirs") {
            $sub_clauses[] = "(p.subcategory = 'Souvenirs' OR p.subcategory LIKE '%Souvenir%' OR p.title LIKE '%Souvenir%' OR p.title LIKE '%Gift%' OR p.title LIKE '%Box%' OR p.title LIKE '%Keychain%')";
        } else {
            $sub_clauses[] = "p.subcategory = ?";
            $params[] = $s;
            $types .= "s";
        }
    }
    if (!empty($sub_clauses)) {
        $where_clauses[] = "(" . implode(" OR ", $sub_clauses) . ")";
    }
}

if (!empty($selected_techs)) {
    $placeholders = implode(',', array_fill(0, count($selected_techs), '?'));
    $where_clauses[] = "p.technique IN ($placeholders)";
    foreach ($selected_techs as $t) {
        $params[] = $t;
        $types .= "s";
    }
}

if (!empty($selected_colors)) {
    $color_conditions = [];
    foreach ($selected_colors as $color_family) {
        if ($color_family === 'Teak / Brown' || $color_family === 'Dark Teak' || $color_family === 'Medium Oak') {
            $color_conditions[] = "(p.color LIKE ? OR p.color LIKE '%Teak%' OR p.color LIKE '%Oak%' OR p.color LIKE '%Brown%' OR p.color LIKE '%Walnut%' OR p.color LIKE '%Natural%')";
            $params[] = "%Teak%";
        } elseif ($color_family === 'Mahogany / Red Tone') {
            $color_conditions[] = "(p.color LIKE ? OR p.color LIKE '%Mahogany%' OR p.color LIKE '%Red%' OR p.color LIKE '%Cherry%' OR p.color LIKE '%Rosewood%')";
            $params[] = "%Mahogany%";
        } elseif ($color_family === 'Pine / Light Tone' || $color_family === 'Light Pine') {
            $color_conditions[] = "(p.color LIKE ? OR p.color LIKE '%Pine%' OR p.color LIKE '%Light%' OR p.color LIKE '%Maple%' OR p.color LIKE '%Birch%' OR p.color LIKE '%White%' OR p.color LIKE '%Cream%')";
            $params[] = "%Pine%";
        } elseif ($color_family === 'Ebony / Black' || $color_family === 'Ebony') {
            $color_conditions[] = "(p.color LIKE ? OR p.color LIKE '%Ebony%' OR p.color LIKE '%Black%' OR p.color LIKE '%Charcoal%' OR p.color LIKE '%Dark%')";
            $params[] = "%Ebony%";
        } elseif ($color_family === 'Driftwood / Grey' || $color_family === 'Driftwood') {
            $color_conditions[] = "(p.color LIKE ? OR p.color LIKE '%Driftwood%' OR p.color LIKE '%Grey%' OR p.color LIKE '%Gray%' OR p.color LIKE '%Ash%')";
            $params[] = "%Driftwood%";
        } else {
            $color_conditions[] = "(p.color LIKE ? OR p.color LIKE ?)";
            $params[] = "%" . $color_family . "%";
            $params[] = "%" . str_replace(" ", "%", $color_family) . "%";
            $types .= "s";
        }
        $types .= "s";
    }
    if (!empty($color_conditions)) {
        $where_clauses[] = "(" . implode(' OR ', $color_conditions) . ")";
    }
}

$where_sql = implode(' AND ', $where_clauses);

// 1. Count total matching items
$count_sql = "SELECT COUNT(*) as total FROM products p LEFT JOIN artisans a ON p.seller_id = a.user_id WHERE $where_sql";
$count_stmt = $conn->prepare($count_sql);
if (!empty($params)) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$count_res = $count_stmt->get_result()->fetch_assoc();
$total_items = $count_res['total'] ?? 0;
$count_stmt->close();

$limit = 12;
$total_pages = ceil($total_items / $limit);
$page = isset($_GET['page']) ? max(1, min($total_pages, intval($_GET['page']))) : 1;
if ($total_pages < 1) $page = 1;
$offset = ($page - 1) * $limit;

// 2. Fetch paginated products
$sql = "SELECT p.*, a.shopname FROM products p LEFT JOIN artisans a ON p.seller_id = a.user_id WHERE $where_sql $order_sql LIMIT ? OFFSET ?";

$paginated_params = $params;
$paginated_params[] = $limit;
$paginated_params[] = $offset;
$paginated_types = $types . "ii";

$stmt = $conn->prepare($sql);
if (!empty($paginated_params)) {
    $stmt->bind_param($paginated_types, ...$paginated_params);
}
$stmt->execute();
$result = $stmt->get_result();

$products = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
}
$stmt->close();
?>
<!DOCTYPE html>
<html lang="ms">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Woodcraft Collection | Pasarkraft</title>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Playfair+Display:ital,wght@0,400;0,700;1,400&display=swap"
        rel="stylesheet">
    <!-- Font Awesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="styles.css">
    <style>
        .color-swatch.selected {
            transform: scale(1.15);
            box-shadow: 0 0 0 3px #8d6e63 !important;
        }
        .color-circle { width: 30px; height: 30px; border-radius: 50%; display: inline-block; cursor: pointer; border: none; }
    </style>
</head>

<body class="woodcraft-theme">

    <!-- Navigation -->
    <header>
        <nav>
            <a href="homepage.php" class="logo">Pasar<span>kraft</span>.</a>
            <form class="header-search" method="GET" action="woodcraft_page.php">
                <input type="text" name="q" placeholder="Search woodcraft products..." value="<?php echo htmlspecialchars($filter_query); ?>">
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
                <a href="woodcraft_page.php" class="active-link" style="color: #8d6e63;">Woodcraft</a>
                <a href="homepage.php#about">About</a>
                <a href="buyer/inquiry_messages.php">Chat history <?php if(isset($unread_count) && $unread_count > 0) echo '<span style="background: red; color: white; border-radius: 50%; padding: 2px 6px; font-size: 0.75rem; margin-left: 5px;">'.$unread_count.'</span>'; ?></a>
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
        </nav>
    </header>

    <!-- Page Header -->
    <section class="page-header woodcraft-header">
        <div class="page-header-content">
            <h1>Woodcraft Collection</h1>
            <p>Hand-carved elegance from Malaysia's finest artisans.</p>
        </div>
    </section>

    <!-- Main Content Area -->
    <div class="collection-layout">

        <!-- Sidebar Filter -->
        <aside class="collection-sidebar">
            <form id="filterForm" method="GET" action="">
                <!-- Keep sort parameter if set -->
                <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sort); ?>">

                <div class="filter-group">
                    <h3>Category</h3>
                    <ul>
                        <?php
                        $subcategories_list = ["Furniture", "Home Decor", "Kitchenware", "Traditional Carving", "Souvenirs"];
                        foreach ($subcategories_list as $sub):
                            $checked = in_array($sub, $selected_subs) ? 'checked' : '';
                        ?>
                            <li><label><input type="checkbox" name="subcategories[]" value="<?php echo htmlspecialchars($sub); ?>" <?php echo $checked; ?> onchange="this.form.submit()"> <?php echo htmlspecialchars($sub); ?></label></li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <div class="filter-group">
                    <h3>Price Range</h3>
                    <div class="price-slider-container">
                        <input type="range" name="max_price" class="price-range" min="0" max="10000" value="<?php echo htmlspecialchars($max_price); ?>" onchange="this.form.submit()" oninput="document.getElementById('priceVal').innerText = 'RM ' + this.value">
                        <div class="price-values">
                            <span>RM 0</span>
                            <span id="priceVal">RM <?php echo htmlspecialchars($max_price); ?></span>
                        </div>
                    </div>
                </div>


                <div class="filter-group">
                    <h3>Technique</h3>
                    <ul>
                        <?php
                        $techniques_list = ["Hand-Carved", "Lathe Turned", "Relief Carving", "Inlay Work"];
                        foreach ($techniques_list as $tech):
                            $checked = in_array($tech, $selected_techs) ? 'checked' : '';
                        ?>
                            <li><label><input type="checkbox" name="techniques[]" value="<?php echo htmlspecialchars($tech); ?>" <?php echo $checked; ?> onchange="this.form.submit()"> <?php echo htmlspecialchars($tech); ?></label></li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <div style="margin-top: 1.5rem; display: flex; gap: 0.5rem;">
                    <a href="?" class="btn-apply-filter" style="text-align: center; text-decoration: none; background: #e74c3c; flex: 1; padding: 8px 0;">Clear All</a>
                </div>
            </form>
        </aside>

        <!-- Product Grid -->
        <main class="collection-main">
            <div class="collection-toolbar">
                <span class="result-count">Showing <?php echo count($products); ?> products</span>
                <div class="sort-dropdown">
                    Sort by: <select onchange="changeSort(this.value)">
                        <option value="featured" <?php echo ($sort === 'featured') ? 'selected' : ''; ?>>Featured</option>
                        <option value="price_low" <?php echo ($sort === 'price_low') ? 'selected' : ''; ?>>Price: Low to High</option>
                        <option value="price_high" <?php echo ($sort === 'price_high') ? 'selected' : ''; ?>>Price: High to Low</option>
                        <option value="new" <?php echo ($sort === 'new') ? 'selected' : ''; ?>>New Arrivals</option>
                    </select>
                </div>
            </div>

            <div class="product-grid collection-grid">
                <?php if (empty($products)): ?>
                    <div
                        style="grid-column: 1 / -1; text-align: center; padding: 40px; color: #7f8c8d; background: #fff; border-radius: 8px; border: 1px dashed #ccc;">
                        <i class="fas fa-tree" style="font-size: 3rem; color: #bdc3c7; margin-bottom: 15px;"></i>
                        <h3>No Products Available</h3>
                        <p>Our artisans are currently crafting new pieces. Check back soon!</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($products as $item): ?>
                        <?php
                            $is_wished = in_array($item['id'], $user_wishlist);
                            $wishlist_class = $is_wished ? 'active' : '';
                            $heart_icon = $is_wished ? 'fas fa-heart' : 'far fa-heart';
                            $heart_color = $is_wished ? 'color: #e74c3c;' : '';

                            $img_raw = $item['image_path'] ?? '';
                            if (empty($img_raw)) {
                                $img_src = 'png/wood_chair.png';
                            } elseif (strpos($img_raw, '/') === false) {
                                $img_src = 'png/' . htmlspecialchars($img_raw);
                            } else {
                                $img_src = htmlspecialchars($img_raw);
                            }
                        ?>
                        <article class="product-card" data-product-id="<?php echo $item['id']; ?>">
                            <a href="product_detail.php?id=<?php echo $item['id']; ?>" class="product-card-link" style="text-decoration: none; color: inherit; display: block;">
                                <div class="product-image">
                                    <img src="<?php echo $img_src; ?>"
                                        alt="<?php echo htmlspecialchars($item['title']); ?>">
                                </div>
                            </a>
                            <div class="product-info">
                                <div class="product-meta">
                                    <span class="product-category"><?php echo htmlspecialchars($item['category']); ?></span>
                                    <div class="shop-name" style="font-size: 0.8rem; color: #7f8c8d; margin-top: 5px;"><i class="fas fa-store"></i> <?php echo htmlspecialchars($item['shopname'] ?? 'Artisan Shop'); ?></div>
                                    <button class="wishlist-btn <?php echo $wishlist_class; ?>" onclick="toggleWishlist(this, <?php echo $item['id']; ?>)" title="Add to Wishlist"><i
                                                class="<?php echo $heart_icon; ?>" style="<?php echo $heart_color; ?>"></i></button>
                                </div>
                                <h3 class="product-title">
                                    <a href="product_detail.php?id=<?php echo $item['id']; ?>" style="text-decoration: none; color: inherit;">
                                        <?php echo htmlspecialchars($item['title']); ?>
                                    </a>
                                </h3>
                                <span class="product-price">RM <?php echo number_format($item['price'], 2); ?></span>
                                <a href="buyer/inquiry_messages.php?seller_id=<?php echo urlencode($item['seller_id']); ?>&product_id=<?php echo $item['id']; ?>"
                                    class="btn-chat" data-product-id="<?php echo $item['id']; ?>">Chat with Seller</a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): 
                        $prev_query = $_GET;
                        $prev_query['page'] = $page - 1;
                    ?>
                        <a href="?<?php echo http_build_query($prev_query); ?>"><i class="fas fa-chevron-left"></i></a>
                    <?php endif; ?>

                    <?php for ($i = 1; $i <= $total_pages; $i++): 
                        $page_query = $_GET;
                        $page_query['page'] = $i;
                        $active_class = ($page == $i) ? 'class="active"' : '';
                    ?>
                        <a href="?<?php echo http_build_query($page_query); ?>" <?php echo $active_class; ?>><?php echo $i; ?></a>
                    <?php endfor; ?>

                    <?php if ($page < $total_pages): 
                        $next_query = $_GET;
                        $next_query['page'] = $page + 1;
                    ?>
                        <a href="?<?php echo http_build_query($next_query); ?>"><i class="fas fa-chevron-right"></i></a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </main>
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
    function selectColor(colorTitle) {
        const hiddenInput = document.getElementById('selectedColor');
        if (hiddenInput.value === colorTitle) {
            hiddenInput.value = '';
        } else {
            hiddenInput.value = colorTitle;
        }
        document.getElementById('filterForm').submit();
    }

    function changeSort(val) {
        const form = document.getElementById('filterForm');
        if (form) {
            let sortInput = form.querySelector('input[name="sort"]');
            if (!sortInput) {
                sortInput = document.createElement('input');
                sortInput.type = 'hidden';
                sortInput.name = 'sort';
                form.appendChild(sortInput);
            }
            sortInput.value = val;
            form.submit();
        } else {
            window.location.href = '?sort=' + encodeURIComponent(val);
        }
    }

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

    function pkLogInteraction(productId, interactionType, interactionValue) {
        fetch('api/log_interaction.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                product_id: productId,
                interaction_type: interactionType,
                interaction_value: interactionValue || 0
            })
        }).catch(function () {});
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

</html>