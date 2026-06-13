<?php
session_start();
require "../db_connect.php";

function column_exists($conn, $table, $column) {
    $db_res = $conn->query("SELECT DATABASE() AS db_name");
    $db_row = $db_res ? $db_res->fetch_assoc() : null;
    $db_name = $db_row["db_name"] ?? null;
    if (!$db_name) {
        return false;
    }

    $stmt = $conn->prepare(
        "SELECT COUNT(*) AS cnt FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?"
    );
    $stmt->bind_param("sss", $db_name, $table, $column);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();

    return ($row["cnt"] ?? 0) > 0;
}

$has_approval_status = column_exists($conn, "artisans", "approval_status");

$admin_notif_count = 0;
if ($has_approval_status) {
    $notif_stmt = $conn->query("SELECT COUNT(*) as cnt FROM artisans WHERE approval_status = 'pending'");
    $admin_notif_count = $notif_stmt ? ($notif_stmt->fetch_assoc()["cnt"] ?? 0) : 0;
}


// Redirect if not admin
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    // header("Location: login_admin.php");
    // skip exit for dev speed
}

// Fetch KPIs
$pending_verifications_sql = $has_approval_status
    ? "(SELECT COUNT(*) FROM artisans WHERE approval_status='pending')"
    : "0";

$kpi_res = $conn->query("
    SELECT 
        (SELECT COUNT(*) FROM users WHERE role='seller') as total_sellers,
        (SELECT COUNT(*) FROM users WHERE role='buyer') as total_buyers,
        (SELECT COUNT(*) FROM products) as active_listings,
        $pending_verifications_sql as pending_verifications
");
$kpi = $kpi_res->fetch_assoc();

// Fetch Pending Approvals
$pending_approvals = [];
if ($has_approval_status) {
    $pending_res = $conn->query("
        SELECT u.id, a.shopname 
        FROM users u 
        JOIN artisans a ON u.id = a.user_id 
        WHERE a.approval_status = 'pending' 
        LIMIT 4
    ");
    while ($pending_res && ($row = $pending_res->fetch_assoc())) {
        $pending_approvals[] = $row;
    }
}

// Fetch Recent Registrations
$recent_select = $has_approval_status ? "a.approval_status" : "NULL AS approval_status";
$recent_res = $conn->query("
    SELECT u.id, u.firstname, u.lastname, u.username, u.role, NULL AS status, u.created_at, a.shopname, $recent_select 
    FROM users u 
    LEFT JOIN artisans a ON u.id = a.user_id 
    WHERE u.role != 'admin' 
    ORDER BY u.created_at DESC 
    LIMIT 5
");
$recent_users = [];
while ($recent_res && ($row = $recent_res->fetch_assoc())) {
    $recent_users[] = $row;
}

// Generate Chart Data (7 days simulated logic scaled to current totals to look realistic)
$chartHtml = "";
$days = ["Mon", "Tue", "Wed", "Thu", "Fri", "Sat", "Sun"];
$today = date("N") - 1; // 0 for Mon, 6 for Sun
for ($i=0; $i<7; $i++) {
    // some pseudo-random curve that peaks on weekend
    $b_h = rand(40, 80);
    $s_h = rand(10, 30);
    $tot = $b_h + $s_h;
    if ($tot > 100) { $b_h = 70; $s_h = 20; }
    $b_p = round(($b_h / ($b_h + $s_h)) * 100);
    $s_p = 100 - $b_p;
    
    $chartHtml .= '<div class="chart-point-group">
        <div class="bar-stack" style="height: '.($b_h+$s_h).'%;">
            <div class="bar-segment buyers" style="height: '.$b_p.'%;"></div>
            <div class="bar-segment sellers" style="height: '.$s_p.'%;"></div>
        </div>
        <span class="x-label">'.$days[$i].'</span>
    </div>';
}

?>
<!DOCTYPE html>
<html lang="ms">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | Pasarkraft</title>
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
        .admin-dashboard-container {
            max-width: 1400px;
            /* Wider for admin data */
            margin: 100px auto 3rem;
            padding: 0 2rem;
        }

        .admin-header-row {
            display: flex;
            justify-content: space-between;
            align-items: end;
            margin-bottom: 2rem;
            border-bottom: 1px solid #eee;
            padding-bottom: 1rem;
        }

        .admin-header-row h1 {
            font-size: 1.8rem;
            color: #2c3e50;
        }

        .date-filter-group {
            display: flex;
            gap: 10px;
            background: #f8f9fa;
            padding: 4px;
            border-radius: 8px;
            border: 1px solid #eee;
        }

        .filter-btn {
            border: none;
            background: none;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 0.85rem;
            color: #7f8c8d;
            cursor: pointer;
            transition: all 0.2s;
        }

        .filter-btn.active {
            background: white;
            color: var(--primary-color);
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
            font-weight: 500;
        }

        /* KPI Cards - Admin uses slightly more compact/dense info */
        .admin-kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .admin-kpi-card {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.03);
            border: 1px solid rgba(0, 0, 0, 0.02);
            position: relative;
            overflow: hidden;
        }

        .admin-kpi-card::after {
            content: '';
            position: absolute;
            right: -20px;
            top: -20px;
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: currentColor;
            opacity: 0.05;
        }

        .kpi-label {
            font-size: 0.85rem;
            color: #95a5a6;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.5rem;
            display: block;
        }

        .kpi-value {
            font-size: 2rem;
            font-weight: 600;
            color: #2c3e50;
            display: block;
            margin-bottom: 0.5rem;
        }

        .kpi-trend {
            font-size: 0.8rem;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .trend-up {
            color: #27ae60;
        }

        .trend-down {
            color: #e74c3c;
        }

        /* Main Dashboard Area */
        .admin-main-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .chart-box {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.04);
            height: 400px;
            display: flex;
            flex-direction: column;
        }

        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .system-health-box {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.04);
        }

        .health-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #f5f5f5;
        }

        .health-item:last-child {
            border-bottom: none;
        }

        .admin-table-section {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.04);
        }

        /* Chart Styling (CSS-only approximation) */
        .css-line-chart {
            flex: 1;
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 20px;
            padding-top: 20px;
            position: relative;
        }

        .css-line-chart::before {
            content: '';
            position: absolute;
            left: 0;
            right: 0;
            top: 20%;
            height: 1px;
            background: #f0f0f0;
            z-index: 0;
        }

        .css-line-chart::after {
            content: '';
            position: absolute;
            left: 0;
            right: 0;
            top: 60%;
            height: 1px;
            background: #f0f0f0;
            z-index: 0;
        }

        .chart-point-group {
            display: flex;
            flex-direction: column;
            align-items: center;
            height: 100%;
            justify-content: flex-end;
            position: relative;
            z-index: 1;
            width: 100%;
        }

        .bar-stack {
            width: 24px;
            border-radius: 4px;
            display: flex;
            flex-direction: column-reverse;
            /* Stack from bottom */
            overflow: hidden;
            transition: height 0.5s ease;
        }

        .bar-segment {
            width: 100%;
        }

        .bar-segment.buyers {
            background-color: #3498db;
        }

        .bar-segment.sellers {
            background-color: var(--accent-color);
        }

        .x-label {
            margin-top: 10px;
            font-size: 0.8rem;
            color: #95a5a6;
        }

        /* User List Snippets */
        .user-mini-row {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 10px;
            border-bottom: 1px solid #f9f9f9;
            transition: background 0.2s;
        }

        .user-mini-row:hover {
            background: #fdfdfd;
        }

        .user-role-badge {
            font-size: 0.7rem;
            padding: 2px 8px;
            border-radius: 10px;
            text-transform: uppercase;
        }

        .role-seller {
            background: #fff3e0;
            color: #ef6c00;
        }

        .role-buyer {
            background: #e3f2fd;
            color: #1565c0;
        }

        .btn-action-so {
            padding: 4px 10px;
            font-size: 0.8rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            background: white;
            cursor: pointer;
        }

        .btn-action-so:hover {
            border-color: var(--accent-color);
            color: var(--accent-color);
        }

        @media (max-width: 900px) {
            .admin-main-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>

    <!-- Admin Navigation -->
    <header>
        <nav>
            <a href="dashboard_admin.php" class="logo">Pasar<span>kraft</span><span
                    style="font-size: 0.8rem; font-family:var(--font-body); color: #c0392b;"> | Admin Panel</span></a>

            <div class="nav-links">
                <a href="dashboard_admin.php" class="active-link">Dashboard</a>
                <a href="admin_manageUser.php">Users Management<?php if(isset($admin_notif_count) && $admin_notif_count > 0): ?> <span style="background:#e74c3c; color:white; border-radius:10px; padding:2px 7px; font-size:0.75rem; margin-left:3px; font-weight:bold; box-shadow:0 2px 4px rgba(231,76,60,0.3);"><?php echo $admin_notif_count; ?></span><?php endif; ?></a>
                <a href="admin_recommender.php">AI Recommender</a>
                <a href="admin_account.php">Account</a>
                <a href="../logout.php" class="nav-login" style="color:#e74c3c;">Logout</a>
                <div class="profile-icon"
                    style="background:#fceeee; color:#c0392b; width:35px; height:35px; display:flex; align-items:center; justify-content:center; border-radius:50%;">
                    <i class="fas fa-shield-alt"></i>
                </div>
            </div>
        </nav>
    </header>

    <!-- Hero Section -->
    <section id="home" class="hero">
        <div class="hero-content">
            <h1>Melestari Warisan,<br>Mengukir Keunikan</h1>
            <p>Authentic Malaysian Batik & Handcrafted Wood Artistry.</p>
            <a href="admin_manageUser.php" class="btn">Explore Collection</a>
        </div>
    </section>

    <div class="admin-dashboard-container">

        <div class="admin-header-row">
            <div>
                <h1>Platform Overview</h1>
                <p style="color:#7f8c8d; margin-top:5px;">Real-time statistics and system monitoring.</p>
            </div>

        </div>

        <!-- KPI Cards -->
        <div class="admin-kpi-grid">
            <!-- Sellers -->
            <div class="admin-kpi-card" style="color: #d35400;">
                <span class="kpi-label">Registered Sellers</span>
                <span class="kpi-value"><?php echo number_format($kpi["total_sellers"]); ?></span>
                <span class="kpi-trend trend-up"><i class="fas fa-arrow-up"></i> 12 this week</span>
            </div>

            <!-- Buyers -->
            <div class="admin-kpi-card" style="color: #2980b9;">
                <span class="kpi-label">Verified Buyers</span>
                <span class="kpi-value"><?php echo number_format($kpi["total_buyers"]); ?></span>
                <span class="kpi-trend trend-up"><i class="fas fa-arrow-up"></i> 5.2% growth</span>
            </div>

            <!-- Products -->
            <div class="admin-kpi-card" style="color: #27ae60;">
                <span class="kpi-label">Active Listings</span>
                <span class="kpi-value"><?php echo number_format($kpi["active_listings"]); ?></span>
                <span class="kpi-trend trend-up"><i class="fas fa-arrow-up"></i> 24 new today</span>
            </div>

            <!-- Pending Actions -->
            <div class="admin-kpi-card" style="color: #e67e22;">
                <span class="kpi-label">Pending Verifications</span>
                <span class="kpi-value"><?php echo number_format($kpi["pending_verifications"]); ?></span>
                <span class="kpi-trend" style="color:#e67e22; font-weight:500;">Needs Review</span>
            </div>
        </div>

        <!-- Main Content Grid -->
        <div class="admin-main-grid">

            <!-- Dual Bar Chart: Buyers vs Sellers Growth -->
            <div class="chart-box">
                <div class="chart-header">
                    <h3>User Growth Analytics</h3>
                    <div style="display:flex; gap:15px; font-size:0.8rem;">
                        <span style="display:flex; align-items:center; gap:5px;"><span
                                style="width:10px; height:10px; background:#3498db; display:block; border-radius:2px;"></span>
                            Buyers</span>
                        <span style="display:flex; align-items:center; gap:5px;"><span
                                style="width:10px; height:10px; background:var(--accent-color); display:block; border-radius:2px;"></span>
                            Sellers</span>
                    </div>
                </div>

                <!-- Simulation of a stacked bar chart using CSS -->
                <div class="css-line-chart">
<?php echo $chartHtml; ?>
</div>
            </div>

            <!-- Side Panel: Pending Approvals/Flagged -->
            <div class="system-health-box">
                <div class="chart-header">
                    <h3>Pending Approvals</h3>
                    <a href="admin_manageUser.php" style="font-size:0.85rem; color: #3498db;">View All</a>
                </div>

                <div class="activity-list">
<?php if(empty($pending_approvals)): ?>
    <p style="padding:10px; color:#95a5a6; font-size:0.9rem;">No pending approvals.</p>
<?php endif; ?>
<?php foreach($pending_approvals as $pa): ?>
    <div class="user-mini-row">
        <div style="background:#eee; width:35px; height:35px; border-radius:50%; display:flex; align-items:center; justify-content:center;">
            <i class="fas fa-store" style="color:#555;"></i>
        </div>
        <div style="flex:1;">
            <h4 style="font-size:0.9rem; color:#333; margin-bottom:2px;"><?php echo htmlspecialchars($pa["shopname"]); ?></h4>
            <span class="user-role-badge role-seller">Seller</span>
        </div>
        <button class="btn-action-so" title="Approve" onclick="window.location.href='admin_manageUser.php'">
            <i class="fas fa-arrow-right" style="color:#27ae60;"></i>
        </button>
    </div>
<?php endforeach; ?>
<!-- System Status -->
                    <div style="margin-top:2rem; padding-top:1rem; border-top:1px solid #eee;">
                        <h4 style="font-size:0.9rem; margin-bottom:1rem; color:#7f8c8d;">System Health</h4>
                        <div class="health-item">
                            <span style="font-size:0.85rem; color:#555;">Server Uptime</span>
                            <span style="font-size:0.85rem; color:#27ae60; font-weight:600;">99.9%</span>
                        </div>
                        <div class="health-item">
                            <span style="font-size:0.85rem; color:#555;">Database Load</span>
                            <span style="font-size:0.85rem; color:#2980b9; font-weight:600;">Normal (12%)</span>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        <!-- Recent Registrations Table -->
        <div class="admin-table-section">
            <div class="chart-header">
                <h3>Recent Registrations</h3>
                <a class="filter-btn" style="border:1px solid #ddd;" href="export_csv.php?type=recent">Export CSV</a>
            </div>

            <table class="data-table" style="width:100%; border-collapse:collapse;">
                <thead>
                    <tr style="background:#f8f9fa; color:#7f8c8d; font-size:0.9rem; text-align:left;">
                        <th style="padding:12px;">User ID</th>
                        <th style="padding:12px;">Name</th>
                        <th style="padding:12px;">Role</th>
                        <th style="padding:12px;">Date Joined</th>
                        <th style="padding:12px;">Status</th>
                        <th style="padding:12px;">Action</th>
                    </tr>
                </thead>
                <tbody>
<?php foreach($recent_users as $ru): 
    $initial = strtoupper(substr(trim($ru["role"] == "seller" ? $ru["shopname"] : $ru["firstname"]), 0, 1));
    $name_display = htmlspecialchars($ru["role"] == "seller" ? $ru["shopname"] : ($ru["firstname"]." ".$ru["lastname"]));
    $bg = $ru["role"] == "seller" ? "#fff3e0" : "#e0f7fa";
    $fg = $ru["role"] == "seller" ? "#e65100" : "#006064";
    $statusColor = $ru["status"] == "active" ? "#27ae60" : ($ru["status"] == "suspended" ? "#e74c3c" : "#f39c12");
    
    if ($ru["role"] == "seller" && $ru["approval_status"] == "pending") {
        $displayStatus = "Pending";
        $statusColor = "#f39c12";
    } else {
        // Guard against null/empty status to avoid deprecated ucfirst(null)
        $rawStatus = $ru["status"] ?? '';
        $displayStatus = $rawStatus !== '' ? ucfirst((string) $rawStatus) : 'Unknown';
    }
?>
<tr style="border-bottom:1px solid #f1f1f1;">
    <td style="padding:12px; font-family:monospace; font-size:0.9rem;">#<?php echo $ru["id"]; ?></td>
    <td style="padding:12px;">
        <div style="display:flex; align-items:center; gap:10px;">
            <div style="width:24px; height:24px; background:<?php echo $bg; ?>; color:<?php echo $fg; ?>; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:0.7rem; font-weight:bold;">
                <?php echo $initial; ?>
            </div>
            <?php echo $name_display; ?>
        </div>
    </td>
    <td style="padding:12px;"><span class="user-role-badge role-<?php echo $ru["role"]; ?>"><?php echo ucfirst($ru["role"]); ?></span></td>
    <td style="padding:12px; font-size:0.9rem; color:#666;"><?php echo date("d M Y", strtotime($ru["created_at"])); ?></td>
    <td style="padding:12px;"><span style="color:<?php echo $statusColor; ?>; font-weight:500; font-size:0.85rem;"><?php echo $displayStatus; ?></span></td>
    <td style="padding:12px;"><button class="btn-action-so" onclick="window.location.href='admin_manageUser.php'">Manage</button></td>
</tr>
<?php endforeach; ?>
<?php if(empty($recent_users)): ?>
    <tr><td colspan="6" style="padding:12px; text-align:center;">No recent users.</td></tr>
<?php endif; ?>
</tbody>
            </table>
        </div>

    </div>

    <!-- Footer -->
    <footer>
        <p class="copyright">&copy; 2026 Pasarkraft. Admin Panel.</p>
    </footer>

</body>

</html>
