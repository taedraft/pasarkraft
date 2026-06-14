<?php session_start(); ?>
<!DOCTYPE html>
<html lang="ms">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register Shop | Pasarkraft</title>
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
            padding-top: 100px;
            /* Slightly reduced top padding for taller form */
        }

        /* Re-use .login-container styles but ensure they look good here */
        .login-container {
            margin: 0;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.2);
            width: 100%;
            max-width: 500px;
            /* Slightly wider for the form */
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

        .form-row {
            display: flex;
            gap: 1rem;
        }

        .form-row .form-group {
            flex: 1;
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
                <h2>Register Shop</h2>
                <p>Start selling your unique batik and woodcraft creations today.</p>

                <?php if (isset($_SESSION['register_success'])): ?>
                    <div
                        style="background-color: #dcfce7; border-left: 4px solid #22c55e; color: #166534; padding: 12px 16px; margin-bottom: 1.5rem; font-size: 0.9rem; border-radius: 4px; display: flex; align-items: center; gap: 8px;">
                        <i class="fas fa-check-circle"></i>
                        <span><?php echo $_SESSION['register_success']; ?></span>
                    </div>
                    <?php unset($_SESSION['register_success']); ?>
                <?php endif; ?>

                <?php if (isset($_SESSION['register_error'])): ?>
                    <div
                        style="background-color: #fee2e2; border-left: 4px solid #ef4444; color: #b91c1c; padding: 12px 16px; margin-bottom: 1.5rem; font-size: 0.9rem; border-radius: 4px; display: flex; align-items: center; gap: 8px;">
                        <i class="fas fa-exclamation-circle"></i>
                        <span><?php echo htmlspecialchars($_SESSION['register_error']); ?></span>
                    </div>
                    <?php unset($_SESSION['register_error']); ?>
                <?php endif; ?>

                <form class="login-form" action="../register.php" method="POST">
                    <input type="hidden" name="role" value="seller">
                    <input type="hidden" name="username" value="" id="hidden-username">

                    <div class="form-group">
                        <label for="shopname">Shop Name <span
                                style="color: red; font-size: 0.9em; margin-left:2px;">*</span></label>
                        <input type="text" id="shopname" name="shopname" placeholder="Enter your shop name" required>
                    </div>

                    <div class="form-group">
                        <label for="ssm">Registration Number (SSM) <span
                                style="color: red; font-size: 0.9em; margin-left:2px;">*</span></label>
                        <input type="text" id="ssm" name="ssm" placeholder="e.g. 202401001234 (123456-A)" required>
                    </div>



                    <div class="form-group">
                        <label for="email">Email Address <span
                                style="color: red; font-size: 0.9em; margin-left:2px;">*</span></label>
                        <input type="email" id="email" name="email" placeholder="Enter your business email" required>
                    </div>

                    <div class="form-group">
                        <label for="phone">Phone Number <span
                                style="color: red; font-size: 0.9em; margin-left:2px;">*</span></label>
                        <input type="tel" id="phone" name="phone" placeholder="e.g. +60123456789" required>
                    </div>

                    <div class="form-group">
                        <label for="password">Password <span
                                style="color: red; font-size: 0.9em; margin-left:2px;">*</span></label>
                        <input type="password" id="password" name="password" placeholder="Create a password" required>
                    </div>

                    <div class="form-group">
                        <label for="confirm-password">Confirm Password <span
                                style="color: red; font-size: 0.9em; margin-left:2px;">*</span></label>
                        <input type="password" id="confirm-password" name="confirm-password"
                            placeholder="Confirm your password" required>
                    </div>

                    <button type="submit" class="btn btn-full">Register Shop</button>

                    <div class="form-footer">
                        <span>Already have a shop?</span>
                        <a href="login_seller.php">Sign In</a>
                    </div>
                </form>

                <div class="role-switch">
                    <p>Looking to buy?</p>
                    <a href="../buyer/signup_buyer.php">Register as Buyer</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer>
        <p class="copyright">&copy; 2026 Pasarkraft. Keeping Traditions Alive.</p>
    </footer>


    </footer>

    <script>
        // Set a default username from email since seller form doesn't have username field
        document.querySelector('.login-form').addEventListener('submit', function (e) {
            const email = document.getElementById('email').value;
            const username = email.split('@')[0] + Math.floor(Math.random() * 1000);
            document.getElementById('hidden-username').value = username;
        });
    </script>
</body>

</html>