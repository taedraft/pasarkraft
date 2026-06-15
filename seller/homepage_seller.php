<!DOCTYPE html>
<html lang="ms">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seller Dashboard | Pasarkraft</title>
    <meta name="description" content="Manage your shop, products, and orders on Pasarkraft.">
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Playfair+Display:ital,wght@0,400;0,700;1,400&display=swap"
        rel="stylesheet">
    <!-- Font Awesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="styles.css">
</head>

<body>

    <!-- Navigation -->
    <header>
                <nav>
            <a href="dashboard_seller.php" class="logo">Pasar<span>kraft</span><span style="font-size: 0.8rem; font-family:var(--font-body); color: #555;"> | Seller Centre</span></a>
            <div class="nav-links">
                <a href="dashboard_seller.php" <?php if(basename($_SERVER['PHP_SELF']) == 'dashboard_seller.php' || basename($_SERVER['PHP_SELF']) == 'homepage_seller.php') echo 'class="active-link" style="color: var(--accent-color);"'; ?>>Dashboard</a>
                <a href="myshop.php" <?php if(basename($_SERVER['PHP_SELF']) == 'myshop.php') echo 'class="active-link" style="color: var(--accent-color);"'; ?>>Products</a>
                <a href="customer_inquiries.php" <?php if(basename($_SERVER['PHP_SELF']) == 'customer_inquiries.php') echo 'class="active-link" style="color: var(--accent-color);"'; ?>>Customer Chats <?php if(isset($unread_count) && $unread_count > 0) echo '<span style="background: red; color: white; border-radius: 50%; padding: 2px 6px; font-size: 0.75rem; margin-left: 5px;">'.$unread_count.'</span>'; ?></a>
                <div class="profile-dropdown-container">
                    <div class="profile-icon"><i class="far fa-user-circle"></i></div>
                    <div class="profile-dropdown-menu">
                        <?php if (isset($shopname) && isset($seller_email)): ?>
                        <div class="profile-header-info">
                            <h4><?php echo htmlspecialchars($shopname); ?></h4>
                            <span><?php echo htmlspecialchars($seller_email); ?></span>
                        </div>
                        <?php endif; ?>
                        <a href="seller_profile.php" class="profile-menu-item"><i class="fas fa-user"></i> My Profile</a>
                        <div class="profile-menu-separator"></div>
                        <a href="logout.php" class="profile-menu-item logout" onclick="return confirm('Are you sure you want to log out?');"><i class="fas fa-sign-out-alt"></i> Logout</a>
                    </div>
                </div>
            </div>
        </nav>
    </header>

    <!-- Hero Section (Seller Context) -->
    <section id="home" class="hero"
        style="background-image: linear-gradient(rgba(0,0,0,0.5), rgba(0,0,0,0.5)), url('hero_bg.png');">
        <div class="hero-content">
            <h1>Welcome Back,<br>Artisan</h1>
            <p>Your creativity brings tradition to life. Manage your shop and connect with the world.</p>
            <div style="margin-top: 2rem;">
                <a href="#" class="btn" style="background-color: var(--accent-color); border:none;"><i
                        class="fas fa-plus"></i> Add New Product</a>
                <a href="#stats" class="btn"
                    style="background-color: transparent; border: 1px solid white; margin-left: 10px;">View
                    Analytics</a>
            </div>
        </div>
    </section>

    <!-- Dashboard/Stats Section (Replaces About) -->
    <!-- Dashboard/Stats Section (Replaces About) -->
    <section id="stats" class="seller-dashboard-stats">
        <div class="stats-container">
            <div class="stats-header">
                <h2>Shop Overview</h2>
                <p>Here's what's happening in your store today.</p>
            </div>

            <div class="stats-grid-layout">
                <!-- Left Stats Column -->
                <div class="key-stats-column">
                    <div class="stat-card-modern">
                        <div class="icon"><i class="fas fa-box-open"></i></div>
                        <span class="value">12</span>
                        <span class="label">Active Listings</span>
                    </div>

                    <!-- Stat Card 2 -->
                    <div class="stat-card-modern">
                        <div class="icon"><i class="fas fa-comments"></i></div>
                        <span class="value">5</span>
                        <span class="label">New Inquiries</span>
                    </div>

                    <div class="stat-card-modern">
                        <div class="icon"><i class="fas fa-coins"></i></div>
                        <span class="value">RM 1,280</span>
                        <span class="label">Total (Month)</span>
                    </div>
                </div>

                <!-- Right Chart Area -->
                <div class="revenue-chart-card">
                    <div class="chart-header">
                        <h3>Revenue Analytics</h3>
                        <div class="chart-legend">
                            <div class="legend-item"><span class="legend-color"
                                    style="background:var(--accent-color)"></span> Revenue (RM)</div>
                        </div>
                    </div>

                    <!-- CSS Bar Chart Component -->
                    <div class="css-bar-chart">
                        <!-- Jan -->
                        <div class="chart-bar-group">
                            <div class="chart-bar fill" style="height: 40%;" data-value="RM 800"></div>
                            <span class="chart-label">Jan</span>
                        </div>
                        <!-- Feb -->
                        <div class="chart-bar-group">
                            <div class="chart-bar fill" style="height: 55%;" data-value="RM 1,100"></div>
                            <span class="chart-label">Feb</span>
                        </div>
                        <!-- Mar -->
                        <div class="chart-bar-group">
                            <div class="chart-bar fill" style="height: 35%;" data-value="RM 700"></div>
                            <span class="chart-label">Mar</span>
                        </div>
                        <!-- Apr -->
                        <div class="chart-bar-group">
                            <div class="chart-bar fill" style="height: 60%;" data-value="RM 1,200"></div>
                            <span class="chart-label">Apr</span>
                        </div>
                        <!-- May -->
                        <div class="chart-bar-group">
                            <div class="chart-bar fill" style="height: 45%;" data-value="RM 900"></div>
                            <span class="chart-label">May</span>
                        </div>
                        <!-- Jun -->
                        <div class="chart-bar-group">
                            <div class="chart-bar fill" style="height: 64%;" data-value="RM 1,280"></div>
                            <span class="chart-label" style="font-weight:700; color:#333;">Jun</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Products Section (My Listings) -->
    <section id="products" class="products">
        <div class="section-header">
            <h2>Your Listings</h2>
            <span></span>
            <p>Manage your active products.</p>
        </div>

        <div class="product-grid">
            <!-- Product 1 -->
            <article class="product-card">
                <div class="product-image">
                    <img src="batik_shirt.png" alt="Modern Batik Shirt">
                    <span class="badge sale" style="background:var(--success-color, #27ae60);">Active</span>
                </div>
                <div class="product-info">
                    <div class="product-meta">
                        <span class="product-category">Batik Fashion</span>
                        <!-- Sellers don't wishlist their own items usually, maybe Edit button -->
                        <button class="wishlist-btn" title="Edit Product"><i class="fas fa-pen"></i></button>
                    </div>
                    <h3 class="product-title">Indigo Contemporary Shirt</h3>
                    <span class="product-price">RM 180.00</span>
                    <a href="#" class="btn-chat" style="background-color: #2c3e50; color: white; border:none;">
                        Manage Item
                    </a>
                </div>
            </article>

            <!-- Product 2 -->
            <article class="product-card">
                <div class="product-image">
                    <img src="wood_carving.png" alt="Traditional Wood Carving">
                    <span class="badge sale" style="background:var(--success-color, #27ae60);">Active</span>
                </div>
                <div class="product-info">
                    <div class="product-meta">
                        <span class="product-category">Woodcraft</span>
                        <button class="wishlist-btn" title="Edit Product"><i class="fas fa-pen"></i></button>
                    </div>
                    <h3 class="product-title">Floral Keris Stand</h3>
                    <span class="product-price">RM 350.00</span>
                    <a href="#" class="btn-chat" style="background-color: #2c3e50; color: white; border:none;">
                        Manage Item
                    </a>
                </div>
            </article>

            <!-- Product 3 -->
            <article class="product-card">
                <div class="product-image">
                    <img src="batik_fabric.png" alt="Hand-drawn Batik Silk">
                    <span class="badge sale" style="background:#e67e22;">Low Stock</span>
                </div>
                <div class="product-info">
                    <div class="product-meta">
                        <span class="product-category">Batik Textile</span>
                        <button class="wishlist-btn" title="Edit Product"><i class="fas fa-pen"></i></button>
                    </div>
                    <h3 class="product-title">Royal Silk Chantting</h3>
                    <span class="product-price">RM 420.00</span>
                    <a href="#" class="btn-chat" style="background-color: #2c3e50; color: white; border:none;">
                        Manage Item
                    </a>
                </div>
            </article>

            <!-- Product 4 -->
            <article class="product-card">
                <div class="product-image">
                    <img src="wood_bowl.png" alt="Wooden Serving Set">
                    <span class="badge sale" style="background:var(--success-color, #27ae60);">Active</span>
                </div>
                <div class="product-info">
                    <div class="product-meta">
                        <span class="product-category">Home Decor</span>
                        <button class="wishlist-btn" title="Edit Product"><i class="fas fa-pen"></i></button>
                    </div>
                    <h3 class="product-title">Jati Wood Serving Set</h3>
                    <span class="product-price">RM 120.00</span>
                    <a href="#" class="btn-chat" style="background-color: #2c3e50; color: white; border:none;">
                        Manage Item
                    </a>
                </div>
            </article>
        </div>
    </section>

    <!-- Footer -->
    <footer>
        <a href="#" class="footer-logo">Pasar<span>kraft</span>.</a>
        <div class="footer-nav">
            <a href="#">Help Center</a>
            <a href="#">Terms of Service</a>
            <a href="#">Privacy Policy</a>
        </div>
        <p class="copyright">&copy; 2026 Pasarkraft. Keeping Traditions Alive.</p>
    </footer>

</body>

</html>
