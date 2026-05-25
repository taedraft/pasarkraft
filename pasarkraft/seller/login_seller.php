<?php session_start(); ?>
<!DOCTYPE html>
<html lang="ms">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seller Login | Pasarkraft</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Playfair+Display:ital,wght@0,400;0,700;1,400&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../styles.css">
    <style>
        body {
            background-color: #fdfaf6;
            background: linear-gradient(rgba(0, 0, 0, 0.2), rgba(0, 0, 0, 0.4)), url('../png/batik_woodcraft_bg.png');
            background-size: cover;
            background-position: center;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .login-page-wrapper {
            flex: 1;
            display: flex;
            align-items: flex-start;
            justify-content: center;
            padding: 2rem;
            padding-top: 150px;
        }

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
            top: 0;
            width: 100%;
            z-index: 1000;
        }

        .logo {
            color: #fff !important;
        }

        .logo span {
            color: #d35400 !important;
        }
    </style>
</head>

<body>
    <header>
        <nav>
            <a href="../homepage.php" class="logo">Pasar<span>kraft</span>.</a>
        </nav>
    </header>

    <div class="login-page-wrapper">
        <div class="login-container">
            <div class="login-content">
                <h2>Seller Portal</h2>
                <p>Manage your artisan products and orders.</p>

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
                        <label for="email">Seller Email</label>
                        <input type="email" id="email" name="email" placeholder="Enter your seller email" required>
                    </div>
                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" placeholder="Enter your password" required>
                    </div>
                    <button type="submit" class="btn btn-full">Login to Dashboard</button>
                    <div class="form-footer">
                        <a href="../forgot_password.php">Forgot Password?</a>
                        <span>|</span>
                        <a href="signup_seller.php">Register Shop</a>
                    </div>
                </form>
                <div class="role-switch">
                    <p>Not a seller?</p>
                    <a href="../buyer/login_buyer.php">Login as Buyer</a>
                    <a href="../admin/login_admin.php">Admin</a>
                </div>
            </div>
        </div>
    </div>

    <footer>
        <p class="copyright">&copy; 2026 Pasarkraft. Keeping Traditions Alive.</p>
    </footer>
</body>

</html>