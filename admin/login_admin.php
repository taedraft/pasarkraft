<!DOCTYPE html>
<html lang="ms">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login | Pasarkraft</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Playfair+Display:ital,wght@0,400;0,700;1,400&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../styles.css">
    <style>
        body {
            background-color: #2c3e50;
            /* Darker base for Admin */
            background: linear-gradient(rgba(0, 0, 0, 0.4), rgba(0, 0, 0, 0.6)), url('../png/batik_woodcraft_bg.png');
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
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-top: 4px solid var(--accent-color);
            /* Admin highlight */
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
                <h2>Admin Panel</h2>
                <p>Authorized personnel only.</p>
                <form class="login-form" action="../login.php" method="POST">
                    <div class="form-group">
                        <label for="email">Admin ID</label>
                        <input type="text" id="email" name="email" placeholder="Enter your admin ID (username)" required>
                    </div>
                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" placeholder="Enter your password" required>
                    </div>
                    <button type="submit" class="btn btn-full">Access Dashboard</button>
                </form>
                <div class="role-switch">
                    <p>Back to public site?</p>
                    <a href="../buyer/login_buyer.php">Buyer Login</a>
                    <a href="../seller/login_seller.php">Seller Login</a>
                </div>
            </div>
        </div>
    </div>

    <footer>
        <p class="copyright">&copy; 2026 Pasarkraft. Admin Access.</p>
    </footer>
</body>

</html>
