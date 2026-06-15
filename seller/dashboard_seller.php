<?php
session_start();
require '../db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'seller') {
    $_SESSION['login_error'] = "Please log in to access the seller dashboard.";
    header("Location: login_seller.php");
    exit();
}

$seller_id = $_SESSION['user_id'];
$unread_count = 0;
if (isset($_SESSION['user_id'])) {
    $unread_stmt = $conn->prepare("SELECT COUNT(*) as unread_count FROM messages WHERE receiver_id = ? AND is_read = 0");
    $unread_stmt->bind_param("i", $_SESSION['user_id']);
    $unread_stmt->execute();
    $unread_res = $unread_stmt->get_result();
    if ($unread_row = $unread_res->fetch_assoc()) {
        $unread_count = $unread_row['unread_count'];
    }
    $unread_stmt->close();
}

$shopname = "Artisan";
$seller_email = "seller@pasarkraft.com";

$stmt = $conn->prepare("SELECT a.shopname, u.email FROM artisans a JOIN users u ON a.user_id = u.id WHERE u.id = ?");
$stmt->bind_param("i", $seller_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $shopname = htmlspecialchars($row['shopname']);
    $seller_email = htmlspecialchars($row['email']);
}
$stmt->close();

// Fetch and refresh seller approval status (keeps session current after admin acts)
$appr_stmt = $conn->prepare("SELECT COALESCE(approval_status, 'approved') AS approval_status FROM artisans WHERE user_id = ?");
$appr_stmt->bind_param("i", $seller_id);
$appr_stmt->execute();
$appr_res = $appr_stmt->get_result();
$approval_status = 'approved'; // default safe value
if ($appr_row = $appr_res->fetch_assoc()) {
    $approval_status = $appr_row['approval_status'];
}
$_SESSION['approval_status'] = $approval_status; // keep session up to date
$appr_stmt->close();
$total_inquiries_count = 0;
$active_inquiries_count = 0;
$products_listed_count = 0;
$wishlist_adds_count = 0;
$product_views_count = 0;
$product_clicks_count = 0;
$ctr_rate = 0.0;

$stmt_total_inquiries = $conn->prepare("SELECT COUNT(DISTINCT buyer_id) AS cnt FROM inquiries WHERE seller_id = ?");
$stmt_total_inquiries->bind_param("i", $seller_id);
$stmt_total_inquiries->execute();
$total_res = $stmt_total_inquiries->get_result();
if ($total_row = $total_res->fetch_assoc()) {
    $total_inquiries_count = (int) $total_row['cnt'];
}
$stmt_total_inquiries->close();

$stmt_active = $conn->prepare("SELECT COUNT(*) AS cnt FROM inquiries WHERE seller_id = ? AND status IN ('In Discussion', 'Deal Agreed')");
$stmt_active->bind_param("i", $seller_id);
$stmt_active->execute();
$active_res = $stmt_active->get_result();
if ($active_row = $active_res->fetch_assoc()) {
    $active_inquiries_count = (int) $active_row['cnt'];
}
$stmt_active->close();

$stmt_products = $conn->prepare("SELECT COUNT(*) AS cnt FROM products WHERE seller_id = ?");
$stmt_products->bind_param("i", $seller_id);
$stmt_products->execute();
$prod_res = $stmt_products->get_result();
if ($prod_row = $prod_res->fetch_assoc()) {
    $products_listed_count = (int) $prod_row['cnt'];
}
$stmt_products->close();

$stmt_wishlist = $conn->prepare("SELECT COUNT(*) AS cnt FROM wishlist w JOIN products p ON w.product_id = p.id WHERE p.seller_id = ?");
$stmt_wishlist->bind_param("i", $seller_id);
$stmt_wishlist->execute();
$wish_res = $stmt_wishlist->get_result();
if ($wish_row = $wish_res->fetch_assoc()) {
    $wishlist_adds_count = (int) $wish_row['cnt'];
}
$stmt_wishlist->close();

$stmt_views = $conn->prepare("SELECT COUNT(*) AS cnt FROM user_interactions ui JOIN products p ON ui.product_id = p.id WHERE p.seller_id = ? AND ui.interaction_type = 'view'");
$stmt_views->bind_param("i", $seller_id);
$stmt_views->execute();
$views_res = $stmt_views->get_result();
if ($views_row = $views_res->fetch_assoc()) {
    $product_views_count = (int) $views_row['cnt'];
}
$stmt_views->close();

$stmt_clicks = $conn->prepare("SELECT COUNT(*) AS cnt FROM user_interactions ui JOIN products p ON ui.product_id = p.id WHERE p.seller_id = ? AND ui.interaction_type = 'click'");
$stmt_clicks->bind_param("i", $seller_id);
$stmt_clicks->execute();
$clicks_res = $stmt_clicks->get_result();
if ($clicks_row = $clicks_res->fetch_assoc()) {
    $product_clicks_count = (int) $clicks_row['cnt'];
}
$stmt_clicks->close();

if ($product_views_count > 0) {
    $ctr_rate = round(($product_clicks_count / $product_views_count) * 100, 2);
}

$engagement_products = [];
$stmt_engagement = $conn->prepare("
    SELECT p.id, p.title,
        SUM(CASE WHEN ui.interaction_type = 'view' THEN 1 ELSE 0 END) AS views,
        SUM(CASE WHEN ui.interaction_type = 'click' THEN 1 ELSE 0 END) AS clicks
    FROM products p
    LEFT JOIN user_interactions ui ON ui.product_id = p.id
    WHERE p.seller_id = ?
    GROUP BY p.id, p.title
    ORDER BY views DESC, clicks DESC, p.created_at DESC
    LIMIT 5
");
$stmt_engagement->bind_param("i", $seller_id);
$stmt_engagement->execute();
$engagement_res = $stmt_engagement->get_result();
while ($row = $engagement_res->fetch_assoc()) {
    $row['views'] = (int) $row['views'];
    $row['clicks'] = (int) $row['clicks'];
    $row['ctr'] = $row['views'] > 0 ? round(($row['clicks'] / $row['views']) * 100, 2) : 0;
    $engagement_products[] = $row;
}
$stmt_engagement->close();

// Fetch inquiries
$inquiries = [];
$stmt_inq = $conn->prepare("SELECT i.id, i.current_offer, i.status, i.updated_at, u.firstname as buyer_name, p.title as product_title FROM inquiries i JOIN users u ON i.buyer_id = u.id JOIN products p ON i.product_id = p.id WHERE i.seller_id = ? ORDER BY i.updated_at DESC LIMIT 5");
$stmt_inq->bind_param("i", $seller_id);
$stmt_inq->execute();
$res_inq = $stmt_inq->get_result();
while ($row = $res_inq->fetch_assoc()) {
    $inquiries[] = $row;
}
$stmt_inq->close();
// Top wishlisted products for this seller (real data)
$top_wishlist = [];
$stmt_top_wish = $conn->prepare("
    SELECT p.id, p.title, p.price, p.image_path, COUNT(w.id) AS wish_count
    FROM products p
    JOIN wishlist w ON w.product_id = p.id
    WHERE p.seller_id = ?
    GROUP BY p.id, p.title, p.price, p.image_path
    ORDER BY wish_count DESC
    LIMIT 3
");
$stmt_top_wish->bind_param("i", $seller_id);
$stmt_top_wish->execute();
$res_top_wish = $stmt_top_wish->get_result();
while ($row = $res_top_wish->fetch_assoc()) {
    $top_wishlist[] = $row;
}
$stmt_top_wish->close();
?>
<!DOCTYPE html>
<html lang="ms">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seller Dashboard | Pasarkraft</title>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Playfair+Display:ital,wght@0,400;0,700;1,400&display=swap"
        rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../styles.css?v=<?php echo time(); ?>">
    <style>
        .dashboard-container {
            max-width: 1200px;
            margin: 100px auto 3rem;
            padding: 0 2rem;
        }

        .dashboard-header {
            margin-bottom: 2rem;
        }

        .dashboard-header h1 {
            font-size: 2rem;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }

        /* Stats Cards - Expanded */
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .kpi-card {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.02);
            border: 1px solid rgba(0, 0, 0, 0.03);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .kpi-info h3 {
            font-size: 2rem;
            color: #2c3e50;
            margin-bottom: 0;
            font-family: var(--font-heading);
        }

        .kpi-info p {
            color: #95a5a6;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .kpi-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }

        /* Order Table Section */
        .orders-section {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.02);
            border: 1px solid rgba(0, 0, 0, 0.03);
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .section-header h2 {
            font-size: 1.25rem;
            color: #2c3e50;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
        }

        .data-table th {
            text-align: left;
            padding: 1rem;
            background: #fdfaf6;
            color: #7f8c8d;
            font-weight: 500;
            font-size: 0.9rem;
        }

        .data-table td {
            padding: 1rem;
            border-bottom: 1px solid #eee;
            color: #2c3e50;
        }

        .data-table tr:last-child td {
            border-bottom: none;
        }

        .user-cell {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .user-avatar {
            width: 32px;
            height: 32px;
            background: #ddd;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 0.8rem;
        }

        .order-status {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-shipped {
            background: #d1ecf1;
            color: #0c5460;
        }

        .status-delivered {
            background: #d4edda;
            color: #155724;
        }

        .status-cancelled {
            background: #f8d7da;
            color: #721c24;
        }

        .btn-view {
            padding: 6px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            background: transparent;
            color: #555;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 0.85rem;
        }

        .btn-view:hover {
            background: #f5f5f5;
            color: var(--primary-color);
        }

        /* Two Column Layout for Graph + Reviews */
        .dashboard-grid-2 {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 1.5rem;
        }

        .activity-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            border: 1px solid #eee;
        }

        .review-item {
            display: flex;
            gap: 1rem;
            padding-bottom: 1rem;
            margin-bottom: 1rem;
            border-bottom: 1px solid #f5f5f5;
        }

        .review-content h4 {
            font-size: 0.95rem;
            margin-bottom: 0.2rem;
            color: #2c3e50;
        }

        .review-content p {
            font-size: 0.85rem;
            color: #7f8c8d;
            line-height: 1.4;
        }

        .star-rating {
            color: #f1c40f;
            font-size: 0.8rem;
            margin-bottom: 0.3rem;
        }

        .engagement-bars {
            display: grid;
            gap: 1rem;
        }

        .engagement-row {
            display: grid;
            gap: 0.4rem;
        }

        .engagement-row .row-label {
            display: flex;
            justify-content: space-between;
            gap: 1rem;
            font-size: 0.88rem;
            color: #334155;
        }

        .engagement-track {
            width: 100%;
            height: 12px;
            border-radius: 999px;
            background: #edf2f7;
            overflow: hidden;
        }

        .engagement-fill {
            height: 100%;
            border-radius: 999px;
            background: linear-gradient(90deg, #f59e0b, #ef4444);
        }

        .engagement-meta {
            display: flex;
            justify-content: space-between;
            gap: 1rem;
            font-size: 0.8rem;
            color: #64748b;
        }

        @media (max-width: 900px) {
            .dashboard-grid-2 {
                grid-template-columns: 1fr;
            }

            .data-table {
                display: block;
                overflow-x: auto;
            }
        }
    </style>
</head>

<body>

    <!-- Navigation -->
    <header>
        <nav>
            <a href="dashboard_seller.php" class="logo">Pasar<span>kraft</span><span
                    style="font-size: 0.8rem; font-family:var(--font-body); color: #555;"> | Seller Centre</span></a>
            <div class="nav-links">
                <a href="dashboard_seller.php" <?php if (basename($_SERVER['PHP_SELF']) == 'dashboard_seller.php' || basename($_SERVER['PHP_SELF']) == 'homepage_seller.php')
                    echo 'class="active-link" style="color: var(--accent-color);"'; ?>>Dashboard</a>
                <a href="myshop.php" <?php if (basename($_SERVER['PHP_SELF']) == 'myshop.php')
                    echo 'class="active-link" style="color: var(--accent-color);"'; ?>>Products</a>
                <a href="customer_inquiries.php" <?php if (basename($_SERVER['PHP_SELF']) == 'customer_inquiries.php')
                    echo 'class="active-link" style="color: var(--accent-color);"'; ?>>Customer Chats
                    <?php if (isset($unread_count) && $unread_count > 0)
                        echo '<span style="background: red; color: white; border-radius: 50%; padding: 2px 6px; font-size: 0.75rem; margin-left: 5px;">' . $unread_count . '</span>'; ?></a>
                <div class="profile-dropdown-container">
                    <div class="profile-icon"><i class="far fa-user-circle"></i></div>
                    <div class="profile-dropdown-menu">
                        <?php if (isset($shopname) && isset($seller_email)): ?>
                            <div class="profile-header-info">
                                <h4><?php echo htmlspecialchars($shopname); ?></h4>
                                <span><?php echo htmlspecialchars($seller_email); ?></span>
                            </div>
                        <?php endif; ?>
                        <a href="seller_profile.php" class="profile-menu-item"><i class="fas fa-user"></i> My
                            Profile</a>
                        <div class="profile-menu-separator"></div>
                        <a href="logout.php" class="profile-menu-item logout"
                            onclick="return confirm('Are you sure you want to log out?');"><i
                                class="fas fa-sign-out-alt"></i> Logout</a>
                    </div>
                </div>
            </div>
        </nav>
    </header>

    <!-- Hero Section -->
    <section id="home" class="hero">
        <div class="hero-content">
            <h1>Melestari Warisan,<br>Mengukir Keunikan</h1>
            <p>Authentic Malaysian Batik &amp; Handcrafted Wood Artistry.</p>
            <?php if ($approval_status === 'approved'): ?>
                <a href="myshop.php" class="btn">Add Products</a>
            <?php elseif ($approval_status === 'pending'): ?>
                <a href="#approval-notice" class="btn" style="background:#f59e0b;">⏳ Pending Approval</a>
            <?php else: ?>
                <a href="#approval-notice" class="btn" style="background:#ef4444;">🔒 Store Rejected</a>
            <?php endif; ?>
        </div>
    </section>

    <div class="dashboard-container">

        <!-- Approval Status Banner -->
        <?php if ($approval_status === 'pending'): ?>
        <div id="approval-notice" style="background:#fffbeb; border:1.5px solid #f59e0b; border-radius:12px; padding:1.2rem 1.5rem; margin-bottom:1.5rem; display:flex; align-items:flex-start; gap:1rem;">
            <i class="fas fa-clock" style="color:#f59e0b; font-size:1.4rem; margin-top:2px;"></i>
            <div>
                <strong style="color:#92400e; font-size:1rem;">Your store is pending approval</strong>
                <p style="margin:4px 0 0; color:#78350f; font-size:0.88rem;">Our admin team is reviewing your store application. You can browse your dashboard, but you cannot add new products until your store is approved. We will review as soon as possible.</p>
            </div>
        </div>
        <?php elseif ($approval_status === 'rejected'): ?>
        <div id="approval-notice" style="background:#fef2f2; border:1.5px solid #ef4444; border-radius:12px; padding:1.2rem 1.5rem; margin-bottom:1.5rem; display:flex; align-items:flex-start; gap:1rem;">
            <i class="fas fa-ban" style="color:#ef4444; font-size:1.4rem; margin-top:2px;"></i>
            <div>
                <strong style="color:#991b1b; font-size:1rem;">Your store application was rejected</strong>
                <p style="margin:4px 0 0; color:#7f1d1d; font-size:0.88rem;">Unfortunately your store application was not approved. You cannot add products. If you believe this is a mistake, please contact admin at <a href="mailto:admin@pasarkraft.com" style="color:#dc2626; font-weight:600;">admin@pasarkraft.com</a> to appeal.</p>
            </div>
        </div>
        <?php endif; ?>

        <div class="dashboard-header">
            <h1>Overview</h1>
            <p>Welcome back, <strong><?php echo $shopname; ?></strong>. Here is what is happening with your shop.</p>
        </div>

        <!-- KPI Cards -->
        <div class="kpi-grid">
            <div class="kpi-card">
                <div class="kpi-info">
                    <h3><?php echo number_format($total_inquiries_count); ?></h3>
                    <p>Unique Buyer Inquiries</p>
                </div>
                <div class="kpi-icon" style="background:#e3f2fd; color:#1565c0;">
                    <i class="fas fa-comments"></i>
                </div>
            </div>
            <div class="kpi-card">
                <div class="kpi-info">
                    <h3><?php echo number_format($wishlist_adds_count); ?></h3>
                    <p>Wishlist Adds</p>
                </div>
                <div class="kpi-icon" style="background:#fff8e1; color:#fbc02d;">
                    <i class="fas fa-heart"></i>
                </div>
            </div>
            <div class="kpi-card">
                <div class="kpi-info">
                    <h3><?php echo number_format($products_listed_count); ?></h3>
                    <p>Products Listed</p>
                </div>
                <div class="kpi-icon" style="background:#fff3e0; color:#ef6c00;">
                    <i class="fas fa-box"></i>
                </div>
            </div>
            <div class="kpi-card">
                <div class="kpi-info">
                    <h3><?php echo number_format($active_inquiries_count); ?></h3>
                    <p>Active Inquiries</p>
                </div>
                <div class="kpi-icon" style="background:#e8f5e9; color:#2e7d32;">
                    <i class="fas fa-comment-dots"></i>
                </div>
            </div>
            <div class="kpi-card">
                <div class="kpi-info">
                    <h3><?php echo number_format($product_views_count); ?></h3>
                    <p>Product Views</p>
                </div>
                <div class="kpi-icon" style="background:#eff6ff; color:#2563eb;">
                    <i class="fas fa-eye"></i>
                </div>
            </div>
            <div class="kpi-card">
                <div class="kpi-info">
                    <h3><?php echo number_format($ctr_rate, 2); ?>%</h3>
                    <p>Click-Through Rate</p>
                </div>
                <div class="kpi-icon" style="background:#fdf2f8; color:#db2777;">
                    <i class="fas fa-bullseye"></i>
                </div>
            </div>
        </div>

        <div class="dashboard-grid-2">
            <!-- Left: Real Engagement Chart -->
            <div class="revenue-chart-card" style="box-shadow: 0 4px 6px rgba(0,0,0,0.02);">
                <div class="chart-header">
                    <h3>Product Views & CTR</h3>
                    <div class="chart-legend">
                        <div class="legend-item"><span class="legend-color"
                                style="background:var(--accent-color)"></span> Views</div>
                    </div>
                </div>

                <div class="engagement-bars">
                    <?php if (empty($engagement_products)): ?>
                        <p style="color:#94a3b8; margin:0;">No engagement logs yet. Product views and click-throughs will
                            appear here once buyers browse your listings.</p>
                    <?php else: ?>
                        <?php
                        $maxViews = 0;
                        foreach ($engagement_products as $eng) {
                            if ($eng['views'] > $maxViews)
                                $maxViews = $eng['views'];
                        }
                        $maxViews = max(1, $maxViews);
                        ?>
                        <?php foreach ($engagement_products as $eng): ?>
                            <?php $width = round(($eng['views'] / $maxViews) * 100); ?>
                            <div class="engagement-row">
                                <div class="row-label">
                                    <span><?php echo htmlspecialchars(strlen($eng['title']) > 28 ? substr($eng['title'], 0, 28) . '...' : $eng['title']); ?></span>
                                    <span><?php echo number_format($eng['views']); ?> views</span>
                                </div>
                                <div class="engagement-track">
                                    <div class="engagement-fill" style="width: <?php echo $width; ?>%;"></div>
                                </div>
                                <div class="engagement-meta">
                                    <span><?php echo number_format($eng['clicks']); ?> clicks</span>
                                    <span>CTR <?php echo number_format($eng['ctr'], 2); ?>%</span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right: Top Wishlisted Products (real wishlist data) -->
            <div class="activity-card">
                <h3 style="margin-bottom:1.2rem; color:#2c3e50; display:flex; align-items:center; gap:8px;">
                    <i class="fas fa-heart" style="color:#e74c3c; font-size:1rem;"></i> Top Wishlisted
                </h3>

                <?php if (empty($top_wishlist)): ?>
                    <div style="text-align:center; padding:2rem 0; color:#94a3b8;">
                        <i class="fas fa-heart-broken" style="font-size:2.5rem; margin-bottom:0.8rem; display:block; color:#e2e8f0;"></i>
                        <p style="font-size:0.88rem; line-height:1.6;">No wishlist data yet.<br>When buyers save your products, they'll appear here.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($top_wishlist as $i => $wp): ?>
                        <div class="review-item" style="<?php echo ($i === count($top_wishlist) - 1) ? 'border:none; margin-bottom:0; padding-bottom:0;' : ''; ?>">
                            <!-- Product thumbnail or icon -->
                            <div style="width:44px; height:44px; border-radius:10px; overflow:hidden; flex-shrink:0; background:#f1f5f9; display:flex; align-items:center; justify-content:center;">
                                <?php if (!empty($wp['image_path'])): ?>
                                    <img src="../<?php echo htmlspecialchars($wp['image_path']); ?>" alt="" style="width:44px; height:44px; object-fit:cover;">
                                <?php else: ?>
                                    <i class="fas fa-box" style="color:#94a3b8; font-size:1.1rem;"></i>
                                <?php endif; ?>
                            </div>
                            <div class="review-content" style="flex:1; min-width:0;">
                                <h4 style="white-space:nowrap; overflow:hidden; text-overflow:ellipsis; font-size:0.9rem;">
                                    <?php echo htmlspecialchars(mb_strlen($wp['title']) > 22 ? mb_substr($wp['title'], 0, 22) . '...' : $wp['title']); ?>
                                </h4>
                                <p style="font-size:0.82rem; color:#64748b; margin:2px 0;">RM <?php echo number_format((float)$wp['price'], 2); ?></p>
                                <div style="display:flex; align-items:center; gap:4px; margin-top:4px;">
                                    <i class="fas fa-heart" style="color:#e74c3c; font-size:0.72rem;"></i>
                                    <span style="font-size:0.82rem; color:#e74c3c; font-weight:600;">
                                        <?php echo $wp['wish_count']; ?> <?php echo (int)$wp['wish_count'] === 1 ? 'save' : 'saves'; ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <br>

        <!-- Recent Orders Table -->
        <div class="orders-section">
            <div class="section-header">
                <h2>Recent Inquiries & Deals</h2>
                <a href="customer_inquiries.php" style="color:var(--accent-color); font-size:0.9rem;">View
                    Messages</a>
            </div>

            <table class="data-table">
                <thead>
                    <tr>
                        <th>Chat ID</th>
                        <th>Customer</th>
                        <th>Interest</th>
                        <th>Date</th>
                        <th>Offer Price</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($inquiries)): ?>
                        <tr>
                            <td colspan="7" style="text-align:center; color:#95a5a6; padding: 2rem;">No recent inquiries
                                yet.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($inquiries as $inq): ?>
                            <?php
                            $status_class = "status-pending"; // In Discussion
                            if ($inq['status'] === 'Deal Agreed')
                                $status_class = "status-shipped";
                            elseif ($inq['status'] === 'Sold')
                                $status_class = "status-delivered";
                            elseif ($inq['status'] === 'No Deal')
                                $status_class = "status-cancelled";

                            $buyer_initial = strtoupper(substr($inq['buyer_name'], 0, 1));
                            $colors = ['#1abc9c', '#3498db', '#9b59b6', '#f1c40f', '#e67e22', '#e74c3c'];
                            $avatar_bg = $colors[abs(crc32($inq['buyer_name'])) % count($colors)];
                            ?>
                            <tr>
                                <td>#CHT-<?php echo str_pad($inq['id'], 4, '0', STR_PAD_LEFT); ?></td>
                                <td>
                                    <div class="user-cell">
                                        <div class="user-avatar" style="background:<?php echo $avatar_bg; ?>;">
                                            <?php echo $buyer_initial; ?>
                                        </div>
                                        <span><?php echo htmlspecialchars($inq['buyer_name']); ?></span>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars(strlen($inq['product_title']) > 20 ? substr($inq['product_title'], 0, 20) . '...' : $inq['product_title']); ?>
                                </td>
                                <td><?php echo date('d M Y', strtotime($inq['updated_at'])); ?></td>
                                <td><?php echo $inq['current_offer'] ? 'RM ' . number_format((float) $inq['current_offer'], 2) : '-'; ?>
                                </td>
                                <td><span
                                        class="order-status <?php echo $status_class; ?>"><?php echo htmlspecialchars($inq['status']); ?></span>
                                </td>
                                <td>
                                    <button class="btn-view"
                                        onclick="window.location.href='customer_inquiries.php?inquiry_id=<?php echo $inq['id']; ?>'">
                                        <?php echo ($inq['status'] === 'In Discussion') ? 'Reply' : 'Details'; ?>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div>

    <!-- Footer -->
    <footer>
        <p class="copyright">&copy; 2026 Pasarkraft. Seller Centre.</p>
    </footer>

</body>

</html>