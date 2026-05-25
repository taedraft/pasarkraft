<?php
session_start();
header("Cache-Control: no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

require 'db_connect.php';

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
    $_SESSION['login_error'] = "Log in to view your wishlist.";
    header("Location: buyer/login_buyer.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$sql = "SELECT p.id, p.seller_id, p.title, p.category, p.price, p.image_path 
        FROM wishlist w
        JOIN products p ON w.product_id = p.id
        WHERE w.buyer_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$wishlist_items = [];
if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $wishlist_items[] = $row;
    }
}
$stmt->close();
?>
<!DOCTYPE html>
<html lang="ms">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Wishlist | Pasarkraft</title>
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
    <style>
        .wishlist-page-container {
            max-width: 1200px;
            margin: 100px auto 3rem;
            padding: 2rem;
            min-height: 60vh;
        }

        .wishlist-title-section {
            text-align: center;
            margin-bottom: 4rem;
        }

        .wishlist-title-section h1 {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
            color: var(--primary-color);
        }

        .breadcrumb {
            color: #888;
            font-size: 0.9rem;
        }

        .breadcrumb a {
            color: #888;
            transition: color 0.3s;
        }

        .breadcrumb a:hover {
            color: var(--accent-color);
        }

        /* Wishlist Item Badge Style like in reference */
        .badge-new-arrival {
            position: absolute;
            top: 10px;
            left: 10px;
            background: transparent;
            color: #333;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            z-index: 2;
        }

        /* Remove Button Overlay */
        .btn-remove-wishlist {
            position: absolute;
            top: 10px;
            right: 10px;
            background: white;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            color: #e74c3c;
            cursor: pointer;
            transition: all 0.3s;
            border: none;
            z-index: 2;
        }

        .btn-remove-wishlist:hover {
            transform: scale(1.1);
            background: #e74c3c;
            color: white;
        }

        /* Empty State */
        .empty-wishlist {
            text-align: center;
            padding: 4rem;
            color: #999;
        }

        .empty-wishlist i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.3;
        }
    </style>
</head>

<body>

    <!-- Navigation -->
    <header>
        <nav>
            <a href="homepage.php" class="logo">Pasar<span>kraft</span>.</a>
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
                            <a href="batik_page.php"><i class="fas fa-tshirt"></i> Batik Fashion</a>
                            <a href="woodcraft_page.php"><i class="fas fa-couch"></i> Wood Furniture</a>
                            <a href="woodcraft_page.php"><i class="fas fa-tree"></i> Handcrafted wood</a>
                            <a href="batik_page.php"><i class="fas fa-scroll"></i> Batik Textile</a>
                        </div>
                    </div>
                </div>
                <!-- Filter Dropdown -->
                <div class="filter-dropdown" id="filterDropdown">
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

                        if (!filterDropdown.contains(event.target) && !filterBtn.contains(event.target)) {
                            filterDropdown.classList.remove('show-filter');
                            if (headerSearch) headerSearch.classList.remove('filter-active');
                        }
                    });
                </script>
            </div>
            <div class="nav-links">
                <a href="batik_page.php">Batik</a>
                <a href="woodcraft_page.php">Woodcraft</a>
                <a href="homepage.php#about">About</a>
                <a href="buyer/chat_history.php">Chat history <?php if(isset($unread_count) && $unread_count > 0) echo '<span style="background: red; color: white; border-radius: 50%; padding: 2px 6px; font-size: 0.75rem; margin-left: 5px;">'.$unread_count.'</span>'; ?></a>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="logout.php" class="nav-login" onclick="return confirm('Are you sure you want to log out?');">Logout</a>
                <?php else: ?>
                    <a href="buyer/login_buyer.php" class="nav-login">Login</a>
                <?php endif; ?>
                <a href="wishlist_page.php" class="wishlist-icon" style="color: var(--accent-color);">
                    <i class="fas fa-heart"></i>
                    <span class="tooltip">Wishlist</span>
                </a>
                <a href="buyer/buyer_profile.php" class="profile-icon">
                    <i class="far fa-user-circle"></i>
                    <span class="tooltip">Account</span>
                </a>
            </div>
        </nav>
    </header>

    <!-- Wishlist Layout -->
    <div class="wishlist-page-container">

        <div class="wishlist-title-section">
            <h1>Wishlist</h1>
            <div class="breadcrumb">
                <a href="homepage.php">Home</a> &nbsp;&gt;&nbsp; <span>Wishlist</span>
            </div>
        </div>

        <!-- Product Grid -->
        <div class="product-grid" id="wishlistGrid">
            <?php if (empty($wishlist_items)): ?>
                <div style="grid-column: 1 / -1; text-align: center; padding: 60px; color: #7f8c8d; background: #fff; border-radius: 8px; border: 1px dashed #ccc;">
                    <i class="fas fa-heart-broken" style="font-size: 3rem; color: #bdc3c7; margin-bottom: 15px;"></i>
                    <h3>Your Wishlist is Empty</h3>
                    <p>Discover our beautiful collections and save your favorite Malaysian crafts here!</p>
                    <a href="homepage.php#products" class="btn" style="margin-top: 15px; display: inline-block;">Explore Now</a>
                </div>
            <?php else: ?>
                <?php foreach ($wishlist_items as $item): ?>
                    <article class="product-card">
                        <button class="btn-remove-wishlist" title="Remove from wishlist" onclick="removeFromWishlist(this, <?php echo $item['id']; ?>)">
                            <i class="fas fa-times"></i>
                        </button>
                        <div class="product-image">
                            <img src="<?php echo htmlspecialchars($item['image_path'] ? $item['image_path'] : 'png/batik_shirt.png'); ?>" alt="<?php echo htmlspecialchars($item['title']); ?>">
                        </div>
                        <div class="product-info">
                            <div class="product-meta">
                                <span class="product-category"><?php echo htmlspecialchars($item['category']); ?></span>
                                <button class="wishlist-btn active" title="Added to Wishlist" onclick="removeFromWishlist(this, <?php echo $item['id']; ?>)"><i class="fas fa-heart" style="color:#e74c3c;"></i></button>
                            </div>
                            <h3 class="product-title"><?php echo htmlspecialchars($item['title']); ?></h3>
                            <span class="product-price">RM <?php echo number_format($item['price'], 2); ?></span>
                            <a href="buyer/chat_history.php?chat_with=<?php echo urlencode($item['seller_id']); ?>" class="btn-chat">Chat with Seller</a>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Footer -->
    <footer>
        <a href="#" class="footer-logo">Pasar<span>kraft</span>.</a>
        <div class="footer-nav">
            <a href="seller/login_seller.php">Seller Centre</a>
            <a href="admin/login_admin.php">Admin Login</a>
        </div>
        <div class="social-links">
            <a href="#"><i class="fab fa-instagram"></i></a>
            <a href="#"><i class="fab fa-facebook"></i></a>
            <a href="#"><i class="fab fa-twitter"></i></a>
        </div>
        <p class="copyright">&copy; 2026 Pasarkraft. Keeping Traditions Alive.</p>
    </footer>

<script>
    function removeFromWishlist(btn, productId) {
        fetch('buyer/toggle_wishlist.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ product_id: productId })
        })
        .then(response => response.json())
        .then(data => {
            if(data.status === 'success') {
                const article = btn.closest('article');
                article.style.transition = 'opacity 0.3s';
                article.style.opacity = '0';
                setTimeout(() => {
                    article.remove();
                    if(document.querySelectorAll('.product-card').length === 0) {
                        location.reload();
                    }
                }, 300);
            } else {
                alert(data.message || 'Error occurred');
            }
        });
    }
</script>
</body>

</html>
