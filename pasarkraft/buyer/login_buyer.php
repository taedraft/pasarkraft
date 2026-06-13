<?php session_start(); ?>
<!DOCTYPE html>
<html lang="ms">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Pasarkraft</title>
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
    <style>
        /* Specific page adjustments */
        body {
            /* Fallback */
            background-color: #fdfaf6;
            background:
                linear-gradient(rgba(0, 0, 0, 0.1), rgba(0, 0, 0, 0.2)),
                url('../png/batik_woodcraft_bg.png');
            background-size: cover;
            background-position: center;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* Centering the login container on its own page */
        .login-page-wrapper {
            flex: 1;
            display: flex;
            align-items: flex-start;
            /* Removed center to allow custom top spacing */
            justify-content: center;
            padding: 2rem;
            padding-top: 150px;
            /* Moves the container down */
        }

        /* Re-use .login-container styles but ensure they look good here */
        .login-container {
            margin: 0;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        header {
            background-color: transparent !important;
            box-shadow: none !important;
            backdrop-filter: none !important;
            position: fixed !important;
            /* Changed from absolute to fixed */
            top: 0;
            width: 100%;
            z-index: 1000;
        }

        .logo {
            color: var(--primary-color) !important;
        }

        /* Depending on bg brightness, white might be better but let's check */
        /* Actually with the dark overlay I added, White logo is better */
        .logo {
            color: #fff !important;
        }

        .logo span {
            color: #d35400 !important;
        }
    </style>
</head>

<body>

    <!-- Navigation -->
    <header>
        <nav>
            <a href="../homepage.php" class="logo">Pasar<span>kraft</span>.</a>

        </nav>
    </header>

    <!-- Main Content -->
    <div class="login-page-wrapper">
        <div class="login-container">
            <div class="login-content">
                <h2>Welcome Back</h2>
                <p>Login to access your wishlist and chat history.</p>

                <?php if (isset($_GET['from']) && $_GET['from'] === 'chat'): ?>
                    <div style="background:#eff6ff; border-left:4px solid #3b82f6; color:#1d4ed8; padding:12px 16px; margin-bottom:1.2rem; border-radius:6px; display:flex; align-items:center; gap:10px; font-size:0.9rem;">
                        <i class="fas fa-comment-dots" style="font-size:1.1rem;"></i>
                        <span>Log in to view your Chat History.</span>
                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['login_error'])): ?>
                    <div
                        style="background-color: #fee2e2; border-left: 4px solid #ef4444; color: #b91c1c; padding: 12px 16px; margin-bottom: 1.5rem; font-size: 0.9rem; border-radius: 4px; display: flex; align-items: center; gap: 8px;">
                        <i class="fas fa-exclamation-circle"></i>
                        <span><?php echo htmlspecialchars($_SESSION['login_error']); ?></span>
                    </div>
                    <?php unset($_SESSION['login_error']); ?>
                <?php endif; ?>

                <form class="login-form" action="../login.php" method="POST">
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email" placeholder="Enter your email" required>
                    </div>
                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" placeholder="Enter your password" required>
                    </div>
                    <button type="submit" class="btn btn-full">Sign In</button>
                    <div class="form-footer">
                        <a href="../forgot_password.php">Forgot Password?</a>
                        <span>|</span>
                        <a href="signup_buyer.php">Create Account</a>
                    </div>
                </form>

                <div class="role-switch">
                    <p>Not a buyer?</p>
                    <a href="../seller/login_seller.php">Log in as Seller</a>
                    <a href="../admin/login_admin.php">Admin</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer>
        <p class="copyright">&copy; 2026 Pasarkraft. Keeping Traditions Alive.</p>
    </footer>

</body>

</html>