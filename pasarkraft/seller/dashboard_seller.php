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
                <a href="chat_history_seller.php" <?php if (basename($_SERVER['PHP_SELF']) == 'chat_history_seller.php')
                    echo 'class="active-link" style="color: var(--accent-color);"'; ?>>Customer Chats <?php if(isset($unread_count) && $unread_count > 0) echo '<span style="background: red; color: white; border-radius: 50%; padding: 2px 6px; font-size: 0.75rem; margin-left: 5px;">'.$unread_count.'</span>'; ?></a>
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
            <p>Authentic Malaysian Batik & Handcrafted Wood Artistry.</p>
            <a href="myshop.php" class="btn">Add Products</a>
        </div>
    </section>

    <div class="dashboard-container">

        <div class="dashboard-header">
            <h1>Overview</h1>
            <p>Welcome back, <strong><?php echo $shopname; ?></strong>. Here is what is happening with your shop.</p>
        </div>

        <!-- KPI Cards -->
        <div class="kpi-grid">
            <div class="kpi-card">
                <div class="kpi-info">
                    <h3>RM 4,280</h3>
                    <p>Est. Revenue</p>
                </div>
                <div class="kpi-icon" style="background:#e8f5e9; color:#2e7d32;">
                    <i class="fas fa-coins"></i>
                </div>
            </div>
            <div class="kpi-card">
                <div class="kpi-info">
                    <h3>8</h3>
                    <p>Active Inquiries</p>
                </div>
                <div class="kpi-icon" style="background:#e3f2fd; color:#1565c0;">
                    <i class="fas fa-comments"></i>
                </div>
            </div>
            <div class="kpi-card">
                <div class="kpi-info">
                    <h3>12</h3>
                    <p>Products Listed</p>
                </div>
                <div class="kpi-icon" style="background:#fff3e0; color:#ef6c00;">
                    <i class="fas fa-box"></i>
                </div>
            </div>
            <div class="kpi-card">
                <div class="kpi-info">
                    <h3>4.8</h3>
                    <p>Average Rating</p>
                </div>
                <div class="kpi-icon" style="background:#fff8e1; color:#fbc02d;">
                    <i class="fas fa-star"></i>
                </div>
            </div>
        </div>

        <div class="dashboard-grid-2">
            <!-- Left: Revenue Grid (Using existing styles from homepage_seller if possible, or new) -->
            <!-- I'll use the .revenue-chart-card class I defined in styles.css -->
            <div class="revenue-chart-card" style="box-shadow: 0 4px 6px rgba(0,0,0,0.02);">
                <div class="chart-header">
                    <h3>Revenue Analytics</h3>
                    <div class="chart-legend">
                        <div class="legend-item"><span class="legend-color"
                                style="background:var(--accent-color)"></span> Sales</div>
                    </div>
                </div>

                <!-- CSS Bar Chart Component -->
                <div class="css-bar-chart">
                    <div class="chart-bar-group">
                        <div class="chart-bar fill" style="height: 40%;" data-value="RM 15,200"></div>
                        <span class="chart-label">2021</span>
                    </div>
                    <div class="chart-bar-group">
                        <div class="chart-bar fill" style="height: 55%;" data-value="RM 21,500"></div>
                        <span class="chart-label">2022</span>
                    </div>
                    <div class="chart-bar-group">
                        <div class="chart-bar fill" style="height: 35%;" data-value="RM 14,800"></div>
                        <span class="chart-label">2023</span>
                    </div>
                    <div class="chart-bar-group">
                        <div class="chart-bar fill" style="height: 60%;" data-value="RM 24,100"></div>
                        <span class="chart-label">2024</span>
                    </div>
                    <div class="chart-bar-group">
                        <div class="chart-bar fill" style="height: 45%;" data-value="RM 18,900"></div>
                        <span class="chart-label">2025</span>
                    </div>
                    <div class="chart-bar-group">
                        <div class="chart-bar fill" style="height: 64%;" data-value="RM 25,600"></div>
                        <span class="chart-label" style="font-weight:700; color:#333;">2026</span>
                    </div>
                </div>
            </div>

            <!-- Right: Recent Reviews -->
            <div class="activity-card">
                <h3 style="margin-bottom:1.2rem; color:#2c3e50;">Recent Reviews</h3>

                <div class="review-item">
                    <div class="user-avatar" style="background:#3498db;">AM</div>
                    <div class="review-content">
                        <h4>Ahmad M.</h4>
                        <div class="star-rating"><i class="fas fa-star"></i><i class="fas fa-star"></i><i
                                class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i></div>
                        <p>Beautiful woodwork! Exactly as described.</p>
                    </div>
                </div>

                <div class="review-item">
                    <div class="user-avatar" style="background:#e74c3c;">SL</div>
                    <div class="review-content">
                        <h4>Sarah L.</h4>
                        <div class="star-rating"><i class="fas fa-star"></i><i class="fas fa-star"></i><i
                                class="fas fa-star"></i><i class="fas fa-star"></i><i class="far fa-star"></i></div>
                        <p>Fabric is nice but delivery was slightly delayed.</p>
                    </div>
                </div>

                <div class="review-item" style="border:none;">
                    <div class="user-avatar" style="background:#9b59b6;">D</div>
                    <div class="review-content">
                        <h4>David</h4>
                        <div class="star-rating"><i class="fas fa-star"></i><i class="fas fa-star"></i><i
                                class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i></div>
                        <p>The batik shirt fits perfectly. Will buy again!</p>
                    </div>
                </div>

            </div>
        </div>

        <br>

        <!-- Recent Orders Table -->
        <div class="orders-section">
            <div class="section-header">
                <h2>Recent Inquiries & Deals</h2>
                <a href="chat_history_seller.php" style="color:var(--accent-color); font-size:0.9rem;">View
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
                                            <?php echo $buyer_initial; ?></div>
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
                                        onclick="window.location.href='chat_history_seller.php?inquiry_id=<?php echo $inq['id']; ?>'">
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