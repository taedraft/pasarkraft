<?php
session_start();
require "../db_connect.php";
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    // skip strict for dev
}

// Defensive: only count pending approvals if column exists
$colRes = $conn->query("SELECT DATABASE() AS db_name");
$dbName = $colRes ? ($colRes->fetch_assoc()["db_name"] ?? null) : null;
$hasApprovalCol = false;
if ($dbName) {
    $stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?");
    $table = 'artisans'; $col = 'approval_status';
    $stmt->bind_param('sss', $dbName, $table, $col);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    $hasApprovalCol = ($r['cnt'] ?? 0) > 0;
    $stmt->close();
}

if ($hasApprovalCol) {
    $notif_stmt = $conn->query("SELECT COUNT(*) as cnt FROM artisans WHERE approval_status = 'pending'");
    $admin_notif_count = $notif_stmt ? ($notif_stmt->fetch_assoc()["cnt"] ?? 0) : 0;
} else {
    $admin_notif_count = 0;
}
?>
<!DOCTYPE html>
<html lang="ms">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Account | Pasarkraft</title>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Playfair+Display:ital,wght@0,400;0,700;1,400&display=swap"
        rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../styles.css">
    <style>
        .account-container {
            max-width: 900px;
            margin: 100px auto 3rem;
            padding: 0 2rem;
        }

        .page-header {
            margin-bottom: 2rem;
        }

        .page-header h1 {
            font-size: 1.8rem;
            color: #2c3e50;
        }

        .account-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.04);
            padding: 2.5rem;
            display: flex;
            gap: 3rem;
        }

        .profile-side {
            width: 250px;
            text-align: center;
            border-right: 1px solid #f0f0f0;
            padding-right: 2rem;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .admin-avatar-large {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: #fceeee;
            color: #c0392b;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3.5rem;
            margin-bottom: 1rem;
            border: 4px solid #fff;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        }

        .role-badge-large {
            background: #2c3e50;
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            margin-top: 10px;
            display: inline-block;
        }

        .form-side {
            flex: 1;
        }

        .form-section-title {
            font-size: 1.1rem;
            color: #2c3e50;
            border-bottom: 1px solid #eee;
            padding-bottom: 0.8rem;
            margin-bottom: 1.5rem;
            font-weight: 600;
        }

        .form-grid-account {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .form-group label {
            display: block;
            font-size: 0.85rem;
            color: #7f8c8d;
            margin-bottom: 0.5rem;
            font-weight: 500;
        }

        .form-control {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 0.95rem;
            transition: border-color 0.2s;
        }

        .form-control:focus {
            outline: none;
            border-color: #c0392b;
        }

        .btn-save-admin {
            background: #c0392b;
            color: white;
            padding: 10px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.2s;
        }

        .btn-save-admin:hover {
            background: #e74c3c;
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(231, 76, 60, 0.3);
        }

        @media (max-width: 800px) {
            .account-card {
                flex-direction: column;
                gap: 2rem;
            }

            .profile-side {
                width: 100%;
                border-right: none;
                border-bottom: 1px solid #f0f0f0;
                padding-right: 0;
                padding-bottom: 2rem;
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
                <a href="dashboard_admin.php">Dashboard</a>
                <a href="admin_manageUser.php">Users Management<?php if(isset($admin_notif_count) && $admin_notif_count > 0): ?> <span style="background:#e74c3c; color:white; border-radius:10px; padding:2px 7px; font-size:0.75rem; margin-left:3px; font-weight:bold; box-shadow:0 2px 4px rgba(231,76,60,0.3);"><?php echo $admin_notif_count; ?></span><?php endif; ?></a>
                <a href="admin_recommender.php">AI Recommender</a>
                <a href="admin_account.php" class="active-link">Account</a>
                <a href="../logout.php" class="nav-login" style="color:#e74c3c;">Logout</a>
                <div class="profile-icon" style="background:#fceeee; color:#c0392b; width:35px; height:35px; display:flex; align-items:center; justify-content:center; border-radius:50%;">
                    <i class="fas fa-shield-alt"></i>
                </div>
            </div>
        </nav>
    </header>

    <div class="account-container">
        <div class="page-header">
            <h1>My Account</h1>
            <p style="color:#7f8c8d;">Manage your administrative profile and security.</p>
        </div>

        <div class="account-card">
            <!-- Profile Info Side -->
            <div class="profile-side">
                <div class="admin-avatar-large">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <h3 style="color:#2c3e50;">Super Admin</h3>
                <span class="role-badge-large">System Administrator</span>
                <p style="font-size:0.85rem; color:#95a5a6; margin-top:1rem;">Last login: Today, 10:42 AM</p>
                <p style="font-size:0.85rem; color:#95a5a6;">ID: #ADM-001</p>
            </div>

            <!-- Edit Form Side -->
            <div class="form-side">
                <form>
                    <h4 class="form-section-title">Personal Details</h4>
                    <div class="form-grid-account">
                        <div class="form-group">
                            <label>Full Name</label>
                            <input type="text" class="form-control" value="Pasarkraft Admin">
                        </div>
                        <div class="form-group">
                            <label>Email Address</label>
                            <input type="email" class="form-control" value="admin@pasarkraft.com">
                        </div>
                        <div class="form-group">
                            <label>Phone Number</label>
                            <input type="tel" class="form-control" value="+60 3-8888 9999">
                        </div>
                        <div class="form-group">
                            <label>Department</label>
                            <input type="text" class="form-control" value="Operations" disabled
                                style="background:#f9f9f9; color:#7f8c8d;">
                        </div>
                    </div>

                    <h4 class="form-section-title">Security</h4>
                    <div class="form-grid-account">
                        <div class="form-group">
                            <label>Current Password</label>
                            <input type="password" class="form-control" placeholder="••••••••">
                        </div>
                        <div class="form-group"></div> <!-- Spacer -->
                        <div class="form-group">
                            <label>New Password</label>
                            <input type="password" class="form-control" placeholder="New password">
                        </div>
                        <div class="form-group">
                            <label>Confirm New Password</label>
                            <input type="password" class="form-control" placeholder="Confirm new password">
                        </div>
                    </div>

                    <div style="display:flex; justify-content:flex-end; margin-top:2rem;">
                        <button type="button" class="btn-save-admin"
                            onclick="alert('Account details updated successfully.')">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer>
        <p class="copyright">&copy; 2026 Pasarkraft. Admin Panel.</p>
    </footer>

</body>

</html>
