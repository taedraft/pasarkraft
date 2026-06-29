<?php
session_start();
require 'db_connect.php';

// Fetch product ID from URL
$product_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($product_id <= 0) {
    header("Location: homepage.php");
    exit();
}

// 1. Fetch product details along with shop details
$stmt = $conn->prepare("
    SELECT p.*, a.shopname, a.logo_path, a.user_id as seller_user_id
    FROM products p
    JOIN artisans a ON p.seller_id = a.user_id
    WHERE p.id = ?
");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // Product not found
    header("Location: homepage.php");
    exit();
}

$product = $result->fetch_assoc();
$stmt->close();

// Log a 'view' interaction for the AI recommender if logged in as buyer
if (isset($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'buyer') {
    $user_id = intval($_SESSION['user_id']);
    
    // Check if view interaction already exists
    $view_check = $conn->prepare("SELECT id FROM user_interactions WHERE user_id = ? AND product_id = ? AND interaction_type = 'view'");
    $view_check->bind_param("ii", $user_id, $product_id);
    $view_check->execute();
    $view_res = $view_check->get_result();
    
    if ($view_res->num_rows === 0) {
        // Insert a new view interaction (value = 1.0)
        $view_ins = $conn->prepare("INSERT INTO user_interactions (user_id, product_id, interaction_type, interaction_value) VALUES (?, ?, 'view', 1.0)");
        $view_ins->bind_param("ii", $user_id, $product_id);
        $view_ins->execute();
        $view_ins->close();
    }
    $view_check->close();
}

// 2. Fetch unread message count for logged-in user
$unread_count = 0;
if (isset($_SESSION['user_id'])) {
    $unread_stmt = $conn->prepare("SELECT COUNT(DISTINCT sender_id) as unread_count FROM messages WHERE receiver_id = ? AND is_read = 0");
    $unread_stmt->bind_param("i", $_SESSION['user_id']);
    $unread_stmt->execute();
    $unread_res = $unread_stmt->get_result();
    if ($unread_row = $unread_res->fetch_assoc()) {
        $unread_count = $unread_row['unread_count'];
    }
    $unread_stmt->close();
}

// 3. Check if this product is in the buyer's wishlist
$user_wishlist = [];
if (isset($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'buyer') {
    $wish_stmt = $conn->prepare("SELECT product_id FROM wishlist WHERE buyer_id = ?");
    $wish_stmt->bind_param("i", $_SESSION['user_id']);
    $wish_stmt->execute();
    $wish_res = $wish_stmt->get_result();
    while ($w_row = $wish_res->fetch_assoc()) {
        $user_wishlist[] = intval($w_row['product_id']);
    }
    $wish_stmt->close();
}
$is_wished = in_array($product['id'], $user_wishlist);
$wishlist_class = $is_wished ? 'active' : '';
$heart_icon = $is_wished ? 'fas fa-heart' : 'far fa-heart';
$heart_color = $is_wished ? 'color: #e74c3c;' : '';

// 4. Resolve product image path
$img_raw = $product['image_path'] ?? '';
if (empty($img_raw)) {
    $img_src = ($product['category'] === 'Woodcraft') ? 'png/wood_chair.png' : 'png/batik_shirt.png';
} elseif (strpos($img_raw, '/') === false) {
    $img_src = 'png/' . htmlspecialchars($img_raw);
} else {
    $img_src = htmlspecialchars($img_raw);
}

// 5. Resolve shop logo path
$logo_raw = $product['logo_path'] ?? '';
if (empty($logo_raw)) {
    $logo_src = 'png/hero_bg.png'; // placeholder logo
} else {
    $logo_src = htmlspecialchars($logo_raw);
}
?>
<!DOCTYPE html>
<html lang="ms">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($product['title']); ?> | Pasarkraft</title>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Playfair+Display:ital,wght@0,400;0,700;1,400&display=swap" rel="stylesheet">
    <!-- Font Awesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="styles.css">
    <style>
        /* Specific page styles for Product Details */
        .product-detail-container {
            max-width: 1200px;
            margin: 120px auto 4rem;
            padding: 0 2rem;
        }

        /* Breadcrumbs */
        .breadcrumbs {
            margin-bottom: 1.5rem;
            font-size: 0.85rem;
            color: #7f8c8d;
        }

        .breadcrumbs a {
            color: #7f8c8d;
            text-decoration: none;
            transition: color 0.2s;
        }

        .breadcrumbs a:hover {
            color: var(--primary-color);
        }

        .breadcrumbs span {
            margin: 0 8px;
        }

        /* Detail Layout */
        .detail-layout {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 3rem;
            background: #fff;
            padding: 2.5rem;
            border-radius: 12px;
            box-shadow: 0 5px 25px rgba(0, 0, 0, 0.05);
            border: 1px solid #f0edf8;
        }

        /* Left Column: Image */
        .detail-image-panel {
            position: relative;
            background: #fdfaf6;
            border-radius: 8px;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid #f5ebe0;
            min-height: 450px;
            max-height: 550px;
        }

        .detail-image-panel img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }

        .detail-image-panel img:hover {
            transform: scale(1.03);
        }

        /* Right Column: Info */
        .detail-info-panel {
            display: flex;
            flex-direction: column;
        }

        .detail-category-tag {
            align-self: flex-start;
            background: #f7ede2;
            color: #d35400;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 1rem;
        }

        .detail-title {
            font-family: 'Playfair Display', Georgia, serif;
            font-size: 2.25rem;
            color: #2c3e50;
            margin: 0 0 0.5rem;
            line-height: 1.2;
        }

        .detail-price {
            font-size: 1.8rem;
            color: #2980b9;
            font-weight: 700;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .stock-badge {
            font-size: 0.8rem;
            padding: 4px 10px;
            border-radius: 4px;
            font-weight: 600;
            font-family: 'Inter', sans-serif;
        }

        .stock-badge.in-stock {
            background: #d1fae5;
            color: #065f46;
        }

        .stock-badge.low-stock {
            background: #fef3c7;
            color: #92400e;
        }

        .stock-badge.out-of-stock {
            background: #fee2e2;
            color: #991b1b;
        }

        /* Divider */
        .detail-divider {
            height: 1px;
            background: #f1f1f1;
            margin: 1.5rem 0;
        }

        /* Description */
        .detail-desc-title {
            font-size: 1.05rem;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 0.5rem;
        }

        .detail-desc-content {
            color: #555;
            font-size: 0.95rem;
            line-height: 1.6;
            margin-bottom: 1.5rem;
        }

        /* Attributes Grid */
        .detail-specs-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 2rem;
            background: #fdfaf6;
            padding: 1.2rem;
            border-radius: 8px;
            border: 1px solid #f7ede2;
        }

        .spec-item {
            display: flex;
            flex-direction: column;
            font-size: 0.85rem;
        }

        .spec-label {
            color: #7f8c8d;
            margin-bottom: 2px;
            font-weight: 500;
        }

        .spec-value {
            color: #2c3e50;
            font-weight: 600;
        }

        /* Shop Owner Card */
        .artisan-card {
            display: flex;
            align-items: center;
            gap: 12px;
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 2rem;
        }

        .artisan-logo {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #fff;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .artisan-info {
            display: flex;
            flex-direction: column;
        }

        .artisan-info span {
            font-size: 0.75rem;
            color: #7f8c8d;
        }

        .artisan-name {
            font-weight: 600;
            color: #2c3e50;
            font-size: 0.95rem;
        }

        /* Actions block */
        .detail-actions {
            display: flex;
            gap: 1rem;
            margin-top: auto;
        }

        .btn-detail-chat {
            flex: 1;
            background: #d35400;
            color: #fff;
            border: none;
            padding: 14px 20px;
            border-radius: 8px;
            font-weight: 600;
            text-align: center;
            text-decoration: none;
            transition: background 0.2s, transform 0.1s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            box-shadow: 0 4px 12px rgba(211, 84, 0, 0.2);
            cursor: pointer;
        }

        .btn-detail-chat:hover {
            background: #b84500;
        }

        .btn-detail-chat:active {
            transform: scale(0.98);
        }

        .btn-detail-wishlist {
            background: #fff;
            border: 1px solid #e2e8f0;
            color: #4a5568;
            padding: 14px;
            width: 54px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: border-color 0.2s, background 0.2s;
            font-size: 1.2rem;
        }

        .btn-detail-wishlist:hover {
            border-color: #cbd5e0;
            background: #f8fafc;
        }

        .btn-detail-wishlist.active i {
            color: #e74c3c;
        }

        /* Responsive Design */
        @media (max-width: 900px) {
            .detail-layout {
                grid-template-columns: 1fr;
                gap: 2rem;
                padding: 1.5rem;
            }
            .detail-image-panel {
                min-height: 350px;
            }
            .product-detail-container {
                margin: 100px auto 2rem;
                padding: 0 1rem;
            }
        }
    </style>
</head>

<body>

    <!-- Navigation -->
    <header>
        <nav>
            <a href="homepage.php" class="logo">Pasar<span>kraft</span>.</a>
            <div class="header-search" style="visibility: hidden;">
                <!-- Dummy Search to Maintain Navbar Spacing -->
            </div>
            <div class="nav-links">
                <a href="batik_page.php" class="<?php echo ($product['category'] === 'Batik') ? 'active-link' : ''; ?>">Batik</a>
                <a href="woodcraft_page.php" class="<?php echo ($product['category'] === 'Woodcraft') ? 'active-link' : ''; ?>">Woodcraft</a>
                <a href="homepage.php#about">About</a>
                <a href="buyer/inquiry_messages.php">Chat history <?php if(isset($unread_count) && $unread_count > 0) echo '<span style="background: red; color: white; border-radius: 50%; padding: 2px 6px; font-size: 0.75rem; margin-left: 5px;">'.$unread_count.'</span>'; ?></a>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="logout.php" class="nav-login"
                        onclick="return confirm('Are you sure you want to log out?');">Logout</a>
                <?php else: ?>
                    <a href="buyer/login_buyer.php" class="nav-login">Login</a>
                <?php endif; ?>
                <a href="wishlist_page.php" class="wishlist-icon">
                    <i class="<?php echo $heart_icon; ?>" style="<?php echo $heart_color; ?>"></i>
                    <span class="tooltip">Wishlist</span>
                </a>
                <a href="buyer/buyer_profile.php" class="profile-icon">
                    <i class="far fa-user-circle"></i>
                    <span class="tooltip">Account</span>
                </a>
            </div>
        </nav>
    </header>

    <!-- Main Content -->
    <main class="product-detail-container">
        
        <!-- Breadcrumbs -->
        <nav class="breadcrumbs">
            <a href="homepage.php">Home</a>
            <span>/</span>
            <a href="<?php echo ($product['category'] === 'Woodcraft') ? 'woodcraft_page.php' : 'batik_page.php'; ?>">
                <?php echo htmlspecialchars($product['category']); ?>
            </a>
            <span>/</span>
            <span><?php echo htmlspecialchars($product['title']); ?></span>
        </nav>

        <!-- Details Box -->
        <div class="detail-layout">
            
            <!-- Left Column: Product Image -->
            <div class="detail-image-panel">
                <img src="<?php echo $img_src; ?>" alt="<?php echo htmlspecialchars($product['title']); ?>">
            </div>

            <!-- Right Column: Product Information -->
            <div class="detail-info-panel">
                
                <span class="detail-category-tag"><?php echo htmlspecialchars($product['category']); ?></span>
                <h1 class="detail-title"><?php echo htmlspecialchars($product['title']); ?></h1>
                
                <div class="detail-price">
                    RM <?php echo number_format($product['price'], 2); ?>
                    
                    <!-- Stock Status Badge -->
                    <?php if ($product['stock'] > 10): ?>
                        <span class="stock-badge in-stock"><i class="fas fa-check"></i> In Stock (<?php echo $product['stock']; ?>)</span>
                    <?php elseif ($product['stock'] > 0): ?>
                        <span class="stock-badge low-stock"><i class="fas fa-exclamation-triangle"></i> Low Stock (<?php echo $product['stock']; ?>)</span>
                    <?php else: ?>
                        <span class="stock-badge out-of-stock"><i class="fas fa-times"></i> Out of Stock</span>
                    <?php endif; ?>
                </div>

                <div class="detail-divider"></div>

                <!-- Product Specifications -->
                <div class="detail-specs-grid">
                    <?php if (!empty($product['technique'])): ?>
                        <div class="spec-item">
                            <span class="spec-label">Technique</span>
                            <span class="spec-value"><?php echo htmlspecialchars($product['technique']); ?></span>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($product['material'])): ?>
                        <div class="spec-item">
                            <span class="spec-label">Material</span>
                            <span class="spec-value"><?php echo htmlspecialchars($product['material']); ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($product['color'])): ?>
                        <div class="spec-item">
                            <span class="spec-label">Color / Tone</span>
                            <span class="spec-value"><?php echo htmlspecialchars($product['color']); ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($product['style'])): ?>
                        <div class="spec-item">
                            <span class="spec-label">Style</span>
                            <span class="spec-value"><?php echo htmlspecialchars($product['style']); ?></span>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Description -->
                <h3 class="detail-desc-title">About this craft</h3>
                <p class="detail-desc-content">
                    <?php echo nl2br(htmlspecialchars($product['description'] ? $product['description'] : 'No description provided. This is a unique handcrafted piece made by our expert artisans. Contact the seller directly to customize this item or learn more.')); ?>
                </p>

                <!-- Artisan Shop Card -->
                <div class="artisan-card">
                    <img src="<?php echo $logo_src; ?>" class="artisan-logo" alt="<?php echo htmlspecialchars($product['shopname']); ?>">
                    <div class="artisan-info">
                        <span>Crafted By</span>
                        <span class="artisan-name"><?php echo htmlspecialchars($product['shopname']); ?></span>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="detail-actions">
                    <a href="buyer/inquiry_messages.php?seller_id=<?php echo urlencode($product['seller_user_id']); ?>&product_id=<?php echo $product['id']; ?>" class="btn-detail-chat">
                        <i class="far fa-comment-dots"></i> Chat with Seller
                    </a>
                    
                    <button class="btn-detail-wishlist <?php echo $wishlist_class; ?>" onclick="toggleWishlist(this, <?php echo $product['id']; ?>)" title="Add to Wishlist">
                        <i class="<?php echo $heart_icon; ?>"></i>
                    </button>
                </div>

            </div>
        </div>

    </main>

    <!-- Footer -->
    <footer>
        <p class="copyright">&copy; 2026 Pasarkraft. Keeping Traditions Alive.</p>
    </footer>

    <!-- Wishlist JavaScript Handler -->
    <script>
        function toggleWishlist(button, productId) {
            fetch('buyer/toggle_wishlist.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ product_id: productId })
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    const icon = button.querySelector('i');
                    if (data.action === 'added') {
                        button.classList.add('active');
                        icon.className = 'fas fa-heart';
                        icon.style.color = '#e74c3c';
                        
                        // Log wishlist interaction for recommender
                        if (typeof pkLogInteraction === 'function') {
                            pkLogInteraction(productId, 'wishlist', 3.0);
                        }
                    } else {
                        button.classList.remove('active');
                        icon.className = 'far fa-heart';
                        icon.style.color = '';
                    }
                } else {
                    alert(data.message || 'Please log in as a buyer to save items.');
                }
            })
            .catch(err => {
                console.error('Error toggling wishlist:', err);
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
    </script>

</body>

</html>
