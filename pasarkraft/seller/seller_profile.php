<?php
session_start();
require '../db_connect.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'seller') {
    $_SESSION['login_error'] = "Please log in to access your profile.";
    header("Location: login_seller.php");
    exit();
}

$seller_id = $_SESSION['user_id']; $unread_count = 0;
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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Logo upload handling
    if (isset($_FILES['shop_logo']) && $_FILES['shop_logo']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/logos/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_info = pathinfo($_FILES['shop_logo']['name']);
        $ext = strtolower($file_info['extension']);
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
            $new_filename = 'logo_' . $seller_id . '_' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES['shop_logo']['tmp_name'], $upload_dir . $new_filename)) {
                $path = $upload_dir . $new_filename;
                
                // Delete explicitly old logo off disk
                $old_stmt = $conn->prepare("SELECT logo_path FROM artisans WHERE user_id = ?");
                $old_stmt->bind_param("i", $seller_id);
                $old_stmt->execute();
                $res_old = $old_stmt->get_result();
                if ($res_old->num_rows > 0) {
                    $old_data = $res_old->fetch_assoc();
                    if (!empty($old_data['logo_path']) && file_exists($old_data['logo_path'])) {
                        unlink($old_data['logo_path']);
                    }
                }
                $old_stmt->close();
                
                $stmt = $conn->prepare("UPDATE artisans SET logo_path = ? WHERE user_id = ?");
                $stmt->bind_param("si", $path, $seller_id);
                $stmt->execute();
                $_SESSION['profile_success'] = "Logo updated successfully!";
            } else {
                $_SESSION['profile_error'] = "Failed to save logo file.";
            }
        } else {
            $_SESSION['profile_error'] = "Invalid file type. Only JPG, PNG, WEBP allowed.";
        }
        header("Location: seller_profile.php");
        exit();
    }

    $new_shopname = trim($_POST['shopname'] ?? '');
    $new_phone = trim($_POST['phone'] ?? '');
    $current_pass = $_POST['current_password'] ?? '';
    $new_pass = $_POST['new_password'] ?? '';
    $confirm_pass = $_POST['confirm_password'] ?? '';

    $has_error = false;

    // Password change logic
    if (!empty($current_pass) || !empty($new_pass) || !empty($confirm_pass)) {
        if (empty($current_pass) || empty($new_pass) || empty($confirm_pass)) {
            $_SESSION['profile_error'] = "All password fields are required to change your password.";
            $has_error = true;
        } else if ($new_pass !== $confirm_pass) {
            $_SESSION['profile_error'] = "New passwords do not match.";
            $has_error = true;
        } else if (strlen($new_pass) < 5) {
            $_SESSION['profile_error'] = "New password must be at least 5 characters long.";
            $has_error = true;
        } else {
            // Verify current password
            $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->bind_param("i", $seller_id);
            $stmt->execute();
            $res = $stmt->get_result();
            $user_data = $res->fetch_assoc();
            $stmt->close();

            if ($user_data && password_verify($current_pass, $user_data['password'])) {
                $hashed_new = password_hash($new_pass, PASSWORD_BCRYPT);
                $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->bind_param("si", $hashed_new, $seller_id);
                $stmt->execute();
                $stmt->close();
            } else {
                $_SESSION['profile_error'] = "Current password is incorrect.";
                $has_error = true;
            }
        }
    }

    // Update profile info if no errors
    if (!$has_error && !empty($new_shopname)) {
        $stmt = $conn->prepare("UPDATE artisans SET shopname = ?, phone = ? WHERE user_id = ?");
        $stmt->bind_param("ssi", $new_shopname, $new_phone, $seller_id);
        if ($stmt->execute()) {
            if (!isset($_SESSION['profile_error'])) {
                $_SESSION['profile_success'] = "Profile updated successfully!";
            }
        } else {
            $_SESSION['profile_error'] = "Error updating profile.";
        }
        $stmt->close();
    }
    
    // Redirect to clear POST data
    header("Location: seller_profile.php");
    exit();
}

$shopname = "Shop Name";
$seller_email = "Email";
$username = "";
$ssm = "";
$phone = "";
$date_joined = "Recently";
$logo_path = "";

$stmt = $conn->prepare("SELECT u.username, u.email, u.created_at, a.shopname, a.ssm, a.phone, a.logo_path FROM users u JOIN artisans a ON u.id = a.user_id WHERE u.id = ?");
$stmt->bind_param("i", $seller_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $shopname = htmlspecialchars($row['shopname']);
    $seller_email = htmlspecialchars($row['email']);
    $username = htmlspecialchars($row['username']);
    $ssm = htmlspecialchars($row['ssm']);
    $phone = htmlspecialchars($row['phone']);
    $date_joined = date('M Y', strtotime($row['created_at']));
    $logo_path = $row['logo_path'];
}
$stmt->close();
?>
<!DOCTYPE html>
<html lang="ms">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seller Profile | Pasarkraft</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Playfair+Display:ital,wght@0,400;0,700;1,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="styles.css">
    <style>
        .profile-page-container { max-width: 1000px; margin: 120px auto 4rem; padding: 0 2rem; }
        .profile-header { margin-bottom: 2rem; display: flex; align-items: center; justify-content: space-between; }
        .profile-header h1 { font-size: 2rem; color: var(--primary-color); }
        .profile-content { display: grid; grid-template-columns: 300px 1fr; gap: 2rem; }
        
        .profile-card-side { background: white; border-radius: 12px; padding: 2rem; text-align: center; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05); height: fit-content; }
        .profile-avatar-large { width: 120px; height: 120px; border-radius: 50%; background: #eee; margin: 0 auto 1.5rem; position: relative; overflow: hidden; border: 4px solid white; box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08); }
        .change-photo-btn { margin-top: 1rem; font-size: 0.85rem; color: var(--accent-color); background: none; border: 1px solid var(--accent-color); padding: 6px 12px; border-radius: 20px; cursor: pointer; transition: all 0.2s; }
        .change-photo-btn:hover { background: var(--accent-color); color: white; }
        .shop-status-badge { display: inline-block; margin-top: 1rem; padding: 6px 16px; background: #e8f5e9; color: #2e7d32; border-radius: 20px; font-size: 0.85rem; font-weight: 600; }
        
        .profile-card-main { background: white; border-radius: 12px; padding: 2.5rem; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05); }
        .form-section-title { font-size: 1.1rem; color: #2c3e50; border-bottom: 1px solid #eee; padding-bottom: 0.8rem; margin-bottom: 1.5rem; font-family: var(--font-body); font-weight: 600; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 2rem; }
        .form-group { margin-bottom: 1rem; }
        .form-group.full-width { grid-column: span 2; }
        .form-group label { display: block; font-size: 0.85rem; color: #7f8c8d; margin-bottom: 0.5rem; font-weight: 500; }
        .form-control { width: 100%; padding: 10px 14px; border: 1px solid #ddd; border-radius: 8px; font-size: 0.95rem; font-family: var(--font-body); transition: border-color 0.2s; background: #fdfaf6; }
        .form-control:focus { outline: none; border-color: var(--accent-color); background: white; }
        .form-control:disabled { background: #eee; color: #555; cursor: not-allowed; border-color: #eee; }
        
        .action-buttons { display: flex; justify-content: flex-end; gap: 1rem; margin-top: 2rem; }
        .btn-cancel { padding: 10px 24px; border: 1px solid #ddd; background: transparent; border-radius: 8px; cursor: pointer; font-weight: 500; color: #555; }
        .btn-save { padding: 10px 24px; background: var(--accent-color); color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 500; box-shadow: 0 4px 10px rgba(211, 84, 0, 0.2); }
        
        @media (max-width: 800px) {
            .profile-content { grid-template-columns: 1fr; }
            .form-grid { grid-template-columns: 1fr; }
            .form-group.full-width { grid-column: span 1; }
        }
    </style>
</head>

<body>
    <!-- Standardized Seller Navigation -->
    <header>
        <nav>
            <a href="dashboard_seller.php" class="logo">Pasar<span>kraft</span><span style="font-size: 0.8rem; font-family:var(--font-body); color: #555;"> | Seller Centre</span></a>
            <div class="nav-links">
                <a href="dashboard_seller.php">Dashboard</a>
                <a href="myshop.php">Products</a>
                <a href="chat_history_seller.php">Customer Chats <?php if(isset($unread_count) && $unread_count > 0) echo '<span style="background: red; color: white; border-radius: 50%; padding: 2px 6px; font-size: 0.75rem; margin-left: 5px;">'.$unread_count.'</span>'; ?></a>
                <div class="profile-dropdown-container">
                    <div class="profile-icon"><i class="far fa-user-circle"></i></div>
                    <div class="profile-dropdown-menu">
                        <div class="profile-header-info">
                            <h4><?php echo $shopname; ?></h4>
                            <span><?php echo $seller_email; ?></span>
                        </div>
                        <a href="seller_profile.php" class="profile-menu-item"><i class="fas fa-user"></i> My Profile</a>
                        <div class="profile-menu-separator"></div>
                        <a href="logout.php" class="profile-menu-item logout" onclick="return confirm('Are you sure you want to log out?');"><i class="fas fa-sign-out-alt"></i> Logout</a>
                    </div>
                </div>
            </div>
        </nav>
    </header>

    <div class="profile-page-container">
        <div class="profile-header">
            <h1>Seller Profile</h1>
            <p style="color:#7f8c8d;">Manage your account details and shop information.</p>
        </div>

        <div class="profile-content">
            <!-- Left Sidebar -->
            <aside class="profile-card-side">
                <div class="profile-avatar-large">
                    <?php if (!empty($logo_path)): ?>
                        <img src="<?php echo htmlspecialchars($logo_path); ?>" alt="Shop Logo" style="width: 100%; height: 100%; object-fit: cover;">
                    <?php else: ?>
                        <i class="fas fa-store" style="font-size: 3rem; color: #ccc; line-height: 110px;"></i>
                    <?php endif; ?>
                </div>
                <h3><?php echo $shopname; ?></h3>
                <p style="color:#95a5a6; font-size:0.9rem;">Joined <?php echo $date_joined; ?></p>
                <div class="shop-status-badge">
                    <i class="fas fa-check-circle"></i> Verified Seller
                </div>
                
                <form id="logoUploadForm" method="POST" action="seller_profile.php" enctype="multipart/form-data">
                    <input type="file" id="shop_logo" name="shop_logo" accept="image/png, image/jpeg, image/webp" style="display: none;" onchange="document.getElementById('logoUploadForm').submit();">
                    <button type="button" class="change-photo-btn" onclick="document.getElementById('shop_logo').click();"><i class="fas fa-camera"></i> Upload Photo</button>
                </form>
            </aside>

            <!-- Main Form -->
            <main class="profile-card-main">
                
                <?php if (isset($_SESSION['profile_success'])): ?>
                    <div style="background-color: #dcfce7; border-left: 4px solid #22c55e; color: #166534; padding: 12px 16px; margin-bottom: 1.5rem; font-size: 0.9rem; border-radius: 4px; display: flex; align-items: center; gap: 8px;">
                        <i class="fas fa-check-circle"></i>
                        <span><?php echo $_SESSION['profile_success']; ?></span>
                    </div>
                    <?php unset($_SESSION['profile_success']); ?>
                <?php endif; ?>

                <?php if (isset($_SESSION['profile_error'])): ?>
                    <div style="background-color: #fee2e2; border-left: 4px solid #ef4444; color: #b91c1c; padding: 12px 16px; margin-bottom: 1.5rem; font-size: 0.9rem; border-radius: 4px; display: flex; align-items: center; gap: 8px;">
                        <i class="fas fa-exclamation-circle"></i>
                        <span><?php echo htmlspecialchars($_SESSION['profile_error']); ?></span>
                    </div>
                    <?php unset($_SESSION['profile_error']); ?>
                <?php endif; ?>

                <form method="POST" action="seller_profile.php">
                    <!-- Read-Only Section -->
                    <h4 class="form-section-title">Account Information <span style="font-size: 0.7rem; color: #999; font-weight: 400; margin-left: 10px;">(Non-editable)</span></h4>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Shop ID</label>
                            <input type="text" class="form-control" value="SHP-<?php echo str_pad($seller_id, 4, '0', STR_PAD_LEFT); ?>" disabled>
                        </div>
                        <div class="form-group">
                            <label>Username</label>
                            <input type="text" class="form-control" value="<?php echo $username; ?>" disabled>
                        </div>
                        <div class="form-group">
                            <label>Email Address</label>
                            <input type="email" class="form-control" value="<?php echo $seller_email; ?>" disabled>
                        </div>
                        <div class="form-group">
                            <label>Registration Number (SSM)</label>
                            <input type="text" class="form-control" value="<?php echo $ssm; ?>" disabled>
                        </div>
                    </div>

                    <!-- Editable Section -->
                    <h4 class="form-section-title" style="margin-top: 2rem;">Shop Details <span style="font-size: 0.7rem; color: var(--accent-color); font-weight: 400; margin-left: 10px;">(Editable)</span></h4>
                    <div class="form-grid">
                        <div class="form-group full-width">
                            <label>Shop Display Name</label>
                            <input type="text" name="shopname" class="form-control" value="<?php echo $shopname; ?>" required>
                        </div>
                        <div class="form-group full-width">
                            <label>Shop Description</label>
                            <textarea class="form-control" rows="4" style="resize:vertical;">Preserving the authentic art of Malaysian Batik. Hand-drawn chanting, silk, and cotton textiles directly from artisan workshops in Kelantan.</textarea>
                        </div>
                    </div>

                    <h4 class="form-section-title" style="margin-top: 1rem;">Contact Details</h4>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Business Phone / WhatsApp</label>
                            <input type="tel" name="phone" class="form-control" value="<?php echo $phone; ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Social Media (main)</label>
                            <input type="text" class="form-control" value="@pasarkraftseller">
                        </div>
                        <div class="form-group full-width">
                            <label>Workshop / Business Address</label>
                            <textarea class="form-control" rows="3">No. 15, Jalan Kraftangan 4/2, Kompleks Kraf Kuala Lumpur, 50450 Kuala Lumpur, Wilayah Persekutuan.</textarea>
                        </div>
                    </div>

                    <h4 class="form-section-title" style="margin-top: 1rem;">Security <span style="font-size: 0.7rem; color: #999; font-weight: 400; margin-left: 10px;">(Fill only if changing password)</span></h4>
                    <div class="form-group">
                        <label>Current Password <span style="color: red; font-size: 0.9em; margin-left:2px;">*</span></label>
                        <input type="password" name="current_password" class="form-control" placeholder="••••••••">
                    </div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>New Password <span style="color: red; font-size: 0.9em; margin-left:2px;">*</span></label>
                            <input type="password" name="new_password" class="form-control" placeholder="New password">
                        </div>
                        <div class="form-group">
                            <label>Confirm New Password <span style="color: red; font-size: 0.9em; margin-left:2px;">*</span></label>
                            <input type="password" name="confirm_password" class="form-control" placeholder="Confirm new password">
                        </div>
                    </div>

                    <div class="action-buttons">
                        <a href="seller_profile.php" class="btn-cancel" style="text-decoration:none; display:inline-block; line-height:20px;">Discard Changes</a>
                        <button type="submit" class="btn-save">Save Changes</button>
                    </div>
                </form>
            </main>
        </div>
    </div>

    <!-- Footer -->
    <footer>
        <p class="copyright">&copy; 2026 Pasarkraft. Seller Centre.</p>
    </footer>
</body>
</html>
