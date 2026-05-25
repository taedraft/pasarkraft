<?php
session_start();
header("Cache-Control: no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");
require '../db_connect.php';

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

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'buyer') {
    $_SESSION['login_error'] = "Log in into pasarkraft for further experience";
    header("Location: login_buyer.php");
    exit();
}

$user_id = $_SESSION['user_id'];

$check_col = $conn->query("SHOW COLUMNS FROM users LIKE 'profile_path'");
if ($check_col && $check_col->num_rows == 0) {
    // Add the column dynamically if it's missing from the legacy database
    $conn->query("ALTER TABLE users ADD COLUMN profile_path VARCHAR(255) DEFAULT NULL");
}

$stmt = $conn->prepare("SELECT firstname, lastname, username, email, created_at, profile_path FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();
    $fullname = htmlspecialchars($user['firstname'] . ' ' . $user['lastname']);
    $email = htmlspecialchars($user['email']);
    $username = htmlspecialchars($user['username']);
    $created_at = date('d M Y', strtotime($user['created_at']));
} else {
    $fullname = "Unknown User";
    $email = "";
    $username = "unknown";
    $created_at = "Unknown";
}
$stmt->close();

$error_msg = "";
$success_msg = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $updated_fullname = $_POST['fullname'] ?? '';

    // Handle profile picture upload
    if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['image/jpeg', 'image/png', 'image/gif'];
        if (in_array($_FILES['profile_pic']['type'], $allowed)) {
            $upload_dir = '../uploads/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            $filename = time() . '_' . basename($_FILES['profile_pic']['name']);
            $target_file = $upload_dir . $filename;
            
            if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], $target_file)) {
                $db_path = 'uploads/' . $filename;
                $stmt_upd = $conn->prepare("UPDATE users SET profile_path = ? WHERE id = ?");
                $stmt_upd->bind_param("si", $db_path, $user_id);
                $stmt_upd->execute();
                $stmt_upd->close();
                $success_msg = "Profile picture updated successfully.";
                $user['profile_path'] = $db_path; // update local context
            } else {
                $error_msg = "Failed to upload image.";
            }
        } else {
            $error_msg = "Only JPG, PNG and GIF files are allowed for your profile picture.";
        }
    }

    // Handle password change if filled
    if (!empty($current_password) || !empty($new_password)) {
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $error_msg = "Please fill in all password fields to change your password.";
        } else {
            // Verify old password
            $stmt_pwd = $conn->prepare("SELECT password FROM users WHERE id = ?");
            $stmt_pwd->bind_param("i", $user_id);
            $stmt_pwd->execute();
            $pwd_res = $stmt_pwd->get_result();
            if ($pwd_res->num_rows > 0) {
                $user_data = $pwd_res->fetch_assoc();
                if (password_verify($current_password, $user_data['password'])) {
                    if ($new_password === $confirm_password) {
                        $new_hash = password_hash($new_password, PASSWORD_BCRYPT);
                        $update_pwd = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                        $update_pwd->bind_param("si", $new_hash, $user_id);
                        $update_pwd->execute();
                        
                        // Force logout and prompt relogin
                        session_unset();
                        session_destroy();
                        session_start();
                        $_SESSION['login_error'] = "Password changed. Please log in again to confirm it's you.";
                        echo "<script>alert('Password changed successfully.'); window.location.href='login_buyer.php';</script>";
                        exit();
                    } else {
                        $error_msg = "New password and confirm password do not match.";
                    }
                } else {
                    $error_msg = "Current password is incorrect.";
                }
            }
            $stmt_pwd->close();
        }
    } 
    // Handle profile info update
    elseif (!empty($updated_fullname) && $updated_fullname !== $fullname) {
        $parts = explode(" ", trim($updated_fullname), 2);
        $fn = $parts[0];
        $ln = isset($parts[1]) ? $parts[1] : '';
        
        $upd = $conn->prepare("UPDATE users SET firstname = ?, lastname = ? WHERE id = ?");
        $upd->bind_param("ssi", $fn, $ln, $user_id);
        if ($upd->execute()) {
            $fullname = htmlspecialchars($updated_fullname);
            $success_msg = "Profile updated successfully.";
        } else {
            $error_msg = "Failed to update profile.";
        }
        $upd->close();
    }
}
?>
<!DOCTYPE html>
<html lang="ms">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile | Pasarkraft</title>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Playfair+Display:ital,wght@0,400;0,700;1,400&display=swap"
        rel="stylesheet">
    <!-- Font Awesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../styles.css">
</head>

<body>

    <!-- Navigation -->
    <header>
        <nav>
            <a href="../homepage.php" class="logo">Pasar<span>kraft</span>.</a>
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
                            <a href="../woodcraft_page.php"><i class="fas fa-couch"></i> Wood Furniture</a>
                            <a href="../woodcraft_page.php"><i class="fas fa-tree"></i> Handcrafted wood</a>
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
                <a href="../batik_page.php">Batik</a>
                <a href="../woodcraft_page.php">Woodcraft</a>
                <a href="../homepage.php#about">About</a>
                <a href="chat_history.php">Chat history <?php if(isset($unread_count) && $unread_count > 0) echo '<span style="background: red; color: white; border-radius: 50%; padding: 2px 6px; font-size: 0.75rem; margin-left: 5px;">'.$unread_count.'</span>'; ?></a>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="../logout.php" class="nav-login" onclick="return confirm('Are you sure you want to log out?');">Logout</a>
                <?php else: ?>
                    <a href="login_buyer.php" class="nav-login">Login</a>
                <?php endif; ?>
                <a href="../wishlist_page.php" class="wishlist-icon">
                    <i class="far fa-heart"></i>
                    <span class="tooltip">Wishlist</span>
                </a>
                <a href="buyer_profile.php" class="profile-icon active">
                    <i class="fas fa-user-circle"></i>
                    <span class="tooltip">Account</span>
                </a>
            </div>
        </nav>
    </header>

    <!-- Profile Content -->
    <div class="profile-page-wrapper">
        <div class="profile-container">
            <div class="profile-header-section">
                <form action="buyer_profile.php" method="POST" enctype="multipart/form-data" class="profile-avatar-form">
                    <div class="profile-avatar-wrapper">
                        <?php 
                            $avatar_url = !empty($user['profile_path']) ? '../' . $user['profile_path'] : 'https://ui-avatars.com/api/?name='.urlencode($fullname).'&background=random';
                        ?>
                        <img src="<?php echo htmlspecialchars($avatar_url); ?>" alt="Profile Picture">
                        <label for="profile_pic" class="edit-avatar-btn" style="cursor: pointer;"><i class="fas fa-camera"></i></label>
                        <input type="file" id="profile_pic" name="profile_pic" accept="image/*" style="display:none;" onchange="this.form.submit()">
                    </div>
                </form>
                <div class="profile-title-info">
                    <h2><?php echo $fullname; ?></h2>
                    <span class="account-badge">Buyer Account</span>
                </div>
            </div>

            <?php if (!empty($error_msg)): ?>
                <div style="background-color: #fee2e2; border-left: 4px solid #ef4444; color: #b91c1c; padding: 12px 16px; margin: 1rem 0; font-size: 0.9rem; border-radius: 4px; display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?php echo htmlspecialchars($error_msg); ?></span>
                </div>
            <?php endif; ?>
            <?php if (!empty($success_msg)): ?>
                <div style="background-color: #dcfce7; border-left: 4px solid #22c55e; color: #166534; padding: 12px 16px; margin: 1rem 0; font-size: 0.9rem; border-radius: 4px; display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-check-circle"></i>
                    <span><?php echo htmlspecialchars($success_msg); ?></span>
                </div>
            <?php endif; ?>

            <form class="profile-form" action="buyer_profile.php" method="POST">
                <div class="profile-section-title">Personal Information</div>
                <div class="profile-form-grid">
                    <div class="form-group">
                        <label for="email">Email Address <span class="read-only-badge">Locked</span></label>
                        <input type="email" id="email" value="<?php echo $email; ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label for="username">Username <span class="read-only-badge">Locked</span></label>
                        <input type="text" id="username" value="@<?php echo $username; ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label for="fullname">Full Name</label>
                        <input type="text" id="fullname" name="fullname" value="<?php echo $fullname; ?>">
                    </div>

                    <div class="form-group">
                        <label for="member-since">Member Since <span class="read-only-badge">Locked</span></label>
                        <input type="text" id="member-since" value="<?php echo $created_at; ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label for="account-status">Account Status</label>
                        <input type="text" id="account-status" value="Active - Verified" readonly
                               style="color: #2ecc71; font-weight: 600;">
                    </div>
                </div>

                <div class="profile-section-title" style="margin-top: 1.5rem;">Security</div>
                <div class="profile-form-grid">
                    <div class="form-group full-width">
                        <label>Current Password</label>
                        <input type="password" name="current_password" placeholder="••••••••"
                               style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px;">
                    </div>
                </div>
                <div class="profile-form-grid">
                    <div class="form-group">
                        <label>New Password</label>
                        <input type="password" name="new_password" placeholder="New password"
                               style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px;">
                    </div>
                    <div class="form-group">
                        <label>Confirm New Password</label>
                        <input type="password" name="confirm_password" placeholder="Confirm new password"
                               style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px;">
                    </div>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn-cancel">Cancel</button>
                    <button type="submit" class="btn btn-save">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Footer -->
    <footer>
        <a href="#" class="footer-logo">Pasar<span>kraft</span>.</a>
        <div class="footer-nav">
            <a href="../seller/login_seller.php">Seller Centre</a>
            <a href="../admin/login_admin.php">Admin Login</a>
        </div>
        <div class="social-links">
            <a href="#"><i class="fab fa-instagram"></i></a>
            <a href="#"><i class="fab fa-facebook"></i></a>
            <a href="#"><i class="fab fa-twitter"></i></a>
        </div>
        <p class="copyright">&copy; 2026 Pasarkraft. Keeping Traditions Alive.</p>
    </footer>

</body>

</html>
