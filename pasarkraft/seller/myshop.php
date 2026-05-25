<?php
session_start();
require '../db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'seller') {
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
?>

// Get shop info for the header
$shopname = "My Shop";
$seller_email = "";
$stmt = $conn->prepare("SELECT a.shopname, u.email FROM artisans a JOIN users u ON a.user_id = u.id WHERE a.user_id = ?");
$stmt->bind_param("i", $seller_id);
if ($stmt->execute()) {
    $res = $stmt->get_result();
    if ($res->num_rows > 0) {
        $row = $res->fetch_assoc();
        $shopname = htmlspecialchars($row['shopname']);
        $seller_email = htmlspecialchars($row['email']);
    }
}
$stmt->close();

// Handle AJAX requests for edit/delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'edit_product') {
        $product_id = intval($_POST['product_id']);
        $price = floatval($_POST['price']);
        $stock = intval($_POST['stock']);

        $stmt_check = $conn->prepare("SELECT id FROM products WHERE id = ? AND seller_id = ?");
        $stmt_check->bind_param("ii", $product_id, $seller_id);
        $stmt_check->execute();
        if ($stmt_check->get_result()->num_rows > 0) {
            $stmt_update = $conn->prepare("UPDATE products SET price = ?, stock = ? WHERE id = ?");
            $stmt_update->bind_param("dii", $price, $stock, $product_id);
            if ($stmt_update->execute()) {
                echo json_encode(['success' => true]);
                exit();
            }
        }
        echo json_encode(['success' => false]);
        exit();
    }

    if ($_POST['action'] === 'delete_product') {
        $product_id = intval($_POST['product_id']);

        $stmt_check = $conn->prepare("SELECT id FROM products WHERE id = ? AND seller_id = ?");
        $stmt_check->bind_param("ii", $product_id, $seller_id);
        $stmt_check->execute();
        if ($stmt_check->get_result()->num_rows > 0) {
            $stmt_delete = $conn->prepare("DELETE FROM products WHERE id = ?");
            $stmt_delete->bind_param("i", $product_id);
            if ($stmt_delete->execute()) {
                echo json_encode(['success' => true]);
                exit();
            }
        }
        echo json_encode(['success' => false]);
        exit();
    }
}

// Handle form submission to add new product
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_product') {
    $title = $_POST['title'] ?? '';
    $category = $_POST['category'] ?? '';
    $subcategory = $_POST['subcategory'] ?? '';
    $technique = $_POST['technique'] ?? '';
    $color = $_POST['color'] ?? '';
    $price = floatval($_POST['price'] ?? 0);
    $stock = intval($_POST['stock'] ?? 0);
    $db_path = '';

    if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['image/jpeg', 'image/png', 'image/gif'];
        if (in_array($_FILES['product_image']['type'], $allowed)) {
            $upload_dir = '../uploads/';
            if (!is_dir($upload_dir))
                mkdir($upload_dir, 0777, true);
            $filename = time() . '_' . basename($_FILES['product_image']['name']);
            if (move_uploaded_file($_FILES['product_image']['tmp_name'], $upload_dir . $filename)) {
                $db_path = 'uploads/' . $filename;
            }
        }
    }

    $stmt_insert = $conn->prepare("INSERT INTO products (seller_id, title, category, subcategory, technique, color, price, stock, image_path) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt_insert->bind_param("isssssdis", $seller_id, $title, $category, $subcategory, $technique, $color, $price, $stock, $db_path);
    if ($stmt_insert->execute()) {
        $_SESSION['success_msg'] = "Product added successfully!";
    }
    $stmt_insert->close();
    header("Location: myshop.php");
    exit();
}

// Fetch products
$products = [];
$stmt = $conn->prepare("SELECT id, title, description, category, subcategory, technique, color, price, stock, image_path, created_at FROM products WHERE seller_id = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $seller_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $products[] = $row;
}
$stmt->close();
?>
<!DOCTYPE html>
<html lang="ms">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Shop Management | Pasarkraft</title>
    <meta name="description" content="Manage your Pasarkraft inventory.">
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Playfair+Display:ital,wght@0,400;0,700;1,400&display=swap"
        rel="stylesheet">
    <!-- Font Awesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../styles.css?v=<?php echo time(); ?>">
    <style>
        .shop-management-container {
            max-width: 1200px;
            margin: 120px auto 3rem;
            /* 120px top margin for fixed header */
            padding: 0 2rem;
        }

        .page-header-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .page-title h1 {
            font-size: 2rem;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }

        .page-title p {
            color: #7f8c8d;
        }

        /* Inventory List Styles */
        .inventory-list {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .inventory-item {
            background: white;
            border: 1px solid #eee;
            border-radius: 12px;
            padding: 1.5rem;
            display: grid;
            grid-template-columns: 100px 2fr 1fr 1fr 1fr auto;
            gap: 2rem;
            align-items: center;
            transition: all 0.3s ease;
        }

        .inventory-item:hover {
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            border-color: #ddd;
        }

        .item-image {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 8px;
            overflow: hidden;
        }

        .item-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .item-details h3 {
            font-size: 1.1rem;
            color: #2c3e50;
            margin-bottom: 0.3rem;
        }

        .item-details .category {
            font-size: 0.85rem;
            color: #95a5a6;
            background: #fdfaf6;
            padding: 2px 8px;
            border-radius: 4px;
            border: 1px solid #eee;
        }

        .form-group-small {
            margin-bottom: 0;
        }

        .form-group-small label {
            display: block;
            font-size: 0.75rem;
            color: #95a5a6;
            margin-bottom: 4px;
            text-transform: uppercase;
        }

        .form-control-sm {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 0.9rem;
            font-family: var(--font-body);
        }

        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .status-active {
            background: #d4edda;
            color: #155724;
        }

        .status-sold {
            background: #f8d7da;
            color: #721c24;
        }

        .status-low {
            background: #fff3cd;
            color: #856404;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
        }

        .btn-icon-action {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-edit {
            background: #eef2f3;
            color: #2c3e50;
        }

        .btn-edit:hover {
            background: #dfe6e9;
        }

        .btn-delete {
            background: #fff5f5;
            color: #e74c3c;
        }

        .btn-delete:hover {
            background: #ffebeb;
        }

        .btn-save {
            background: var(--success-color, #27ae60);
            color: white;
            display: none;
        }

        /* Add Item Modal (Simple simulation via overlay) */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 2000;
            backdrop-filter: blur(5px);
        }

        .modal-content {
            background: white;
            padding: 2rem;
            border-radius: 16px;
            width: 100%;
            max-width: 500px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        /* Mobile Responsiveness */
        @media (max-width: 900px) {
            .inventory-item {
                grid-template-columns: 80px 1fr;
                gap: 1rem;
            }

            .item-stock,
            .item-price,
            .item-status {
                grid-column: span 2;
            }

            .action-buttons {
                grid-column: span 2;
                justify-content: flex-end;
            }
        }

        /* Status Border Colors */
        .status-border-green {
            border: 2px solid #27ae60 !important;
            color: #27ae60;
            font-weight: 600;
        }

        .status-border-yellow {
            border: 2px solid #f1c40f !important;
            color: #f39c12;
            font-weight: 600;
        }

        .status-border-red {
            border: 2px solid #e74c3c !important;
            color: #c0392b;
            font-weight: 600;
        }
    </style>
</head>

<body>

    <!-- Navigation -->
    <header>
        <nav>
            <a href="dashboard_seller.php" class="logo">Pasar<span>kraft</span><span
                    style="font-size: 0.8rem; font-family:var(--font-body); color: #555;"> | Seller Centre</span></a>
            <div class="nav-links">
                <a href="dashboard_seller.php">Dashboard</a>
                <a href="myshop.php" class="active-link" style="color: var(--accent-color);">Products</a>
                <a href="chat_history_seller.php">Customer Chats <?php if(isset($unread_count) && $unread_count > 0) echo '<span style="background: red; color: white; border-radius: 50%; padding: 2px 6px; font-size: 0.75rem; margin-left: 5px;">'.$unread_count.'</span>'; ?></a>
                <div class="profile-dropdown-container">
                    <div class="profile-icon"><i class="far fa-user-circle"></i></div>
                    <div class="profile-dropdown-menu">
                        <?php if (isset($shopname) && isset($seller_email)): ?>
                            <div class="profile-header-info">
                                <h4><?php echo htmlspecialchars($shopname); ?></h4>
                                <span><?php echo htmlspecialchars($seller_email); ?></span>
                            </div>
                        <?php endif; ?>
                        <a href="seller_profile.php" class="profile-menu-item"><i class="fas fa-user"></i> My
                            Profile</a>
                        <div class="profile-menu-separator"></div>
                        <a href="../logout.php" class="profile-menu-item logout"
                            onclick="return confirm('Are you sure you want to log out?');"><i
                                class="fas fa-sign-out-alt"></i> Logout</a>
                    </div>
                </div>
            </div>
        </nav>
    </header>

    <div class="shop-management-container">
        <!-- Header Row -->
        <div class="page-header-row">
            <div class="page-title">
                <h1>My Shop</h1>
                <p>Manage your products, stock, and status.</p>
            </div>
            <button class="btn" onclick="openAddModal()"><i class="fas fa-plus"></i> Add New Product</button>
        </div>

        <!-- Inventory List -->
        <div class="inventory-list" id="inventoryList">
            <?php if (empty($products)): ?>
                <div
                    style="text-align: center; padding: 4rem 2rem; background: white; border-radius: 12px; border: 1px dashed #ccc;">
                    <i class="fas fa-box-open" style="font-size: 4rem; color: #ddd; margin-bottom: 1rem;"></i>
                    <h3 style="color: #2c3e50; margin-bottom: 0.5rem;">No Products Listed Yet</h3>
                    <p style="color: #7f8c8d; margin-bottom: 1.5rem;">You haven't uploaded any products to your shop. Get
                        started by adding your first item!</p>
                    <button class="btn" onclick="openAddModal()"><i class="fas fa-plus"></i> Add New Product</button>
                </div>
            <?php else: ?>
                <?php foreach ($products as $item): ?>
                    <?php
                    $status_class = "status-border-green";
                    $status_text = "Available";
                    if ($item['stock'] == 0) {
                        $status_class = "status-border-red";
                        $status_text = "Sold Out";
                    } elseif ($item['stock'] < 10) {
                        $status_class = "status-border-yellow";
                        $status_text = "Low Stock";
                    }
                    ?>
                    <div class="inventory-item" id="item-<?php echo $item['id']; ?>">
                        <div class="item-image">
                            <?php if (!empty($item['image_path'])):
                                $img_src = $item['image_path'];
                                if (strpos($img_src, '/') === false) {
                                    $img_src = '../png/' . $img_src;
                                } else {
                                    $img_src = '../' . $img_src;
                                }
                                ?>
                                <img src="<?php echo htmlspecialchars($img_src); ?>" alt="Product">
                            <?php else: ?>
                                <div
                                    style="width:100%; height:100%; background:#f5f5f5; border-radius:8px; display:flex; align-items:center; justify-content:center; color:#ccc;">
                                    <i class="fas fa-image"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="item-details">
                            <h3><?php echo htmlspecialchars($item['title']); ?></h3>
                            <div style="display:flex; flex-wrap:wrap; gap:4px; margin-top:4px;">
                                <span class="category"><?php echo htmlspecialchars($item['category']); ?></span>
                                <?php if (!empty($item['subcategory'])): ?>
                                    <span class="category" style="background:#f0f8ff;"><i class="fas fa-tag"
                                            style="font-size:0.8em; margin-right:3px;"></i><?php echo htmlspecialchars($item['subcategory']); ?></span>
                                <?php endif; ?>
                                <?php if (!empty($item['technique'])): ?>
                                    <span class="category" style="background:#f9f2f4;"><i class="fas fa-paint-brush"
                                            style="font-size:0.8em; margin-right:3px;"></i><?php echo htmlspecialchars($item['technique']); ?></span>
                                <?php endif; ?>
                                <?php if (!empty($item['color'])): ?>
                                    <span class="category" style="background:#fdf6e3;"><i class="fas fa-palette"
                                            style="font-size:0.8em; margin-right:3px;"></i><?php echo htmlspecialchars($item['color']); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="form-group-small">
                            <label>Price (RM)</label>
                            <input type="number" class="form-control-sm item-price-input"
                                value="<?php echo htmlspecialchars($item['price']); ?>" disabled>
                        </div>
                        <div class="form-group-small">
                            <label>Stock</label>
                            <input type="number" class="form-control-sm item-stock-input"
                                value="<?php echo htmlspecialchars($item['stock']); ?>" disabled>
                        </div>
                        <div class="form-group-small">
                            <label>Status</label>
                            <input type="text" class="form-control-sm item-status-input <?php echo $status_class; ?>"
                                value="<?php echo $status_text; ?>" readonly>
                        </div>
                        <div class="action-buttons">
                            <button class="btn-icon-action btn-edit" onclick="enableEdit('item-<?php echo $item['id']; ?>')"
                                title="Edit"><i class="fas fa-pen"></i></button>
                            <button class="btn-icon-action btn-save" onclick="saveEdit('item-<?php echo $item['id']; ?>')"
                                title="Save"><i class="fas fa-check"></i></button>
                            <button class="btn-icon-action btn-delete" onclick="deleteItem('item-<?php echo $item['id']; ?>')"
                                title="Delete"><i class="fas fa-trash"></i></button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Add Item Modal -->
    <div class="modal-overlay" id="addModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Add New Product</h2>
                <button class="btn-icon-action" onclick="closeAddModal()"><i class="fas fa-times"></i></button>
            </div>
            <?php if (isset($_SESSION['success_msg'])): ?>
                <div
                    style="background-color: #dcfce7; color: #166534; padding: 12px; margin-bottom: 1rem; border-radius: 4px;">
                    <?php echo htmlspecialchars($_SESSION['success_msg']);
                    unset($_SESSION['success_msg']); ?>
                </div>
            <?php endif; ?>

            <form action="myshop.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="add_product">
                <div class="form-group">
                    <label>Product Name</label>
                    <input type="text" name="title" class="form-control-sm" required>
                </div>
                <div class="form-group">
                    <label>Category</label>
                    <select name="category" id="categorySelect" class="form-control-sm" onchange="updateSubAndTech()"
                        required>
                        <option value="">Select Category...</option>
                        <option value="Batik">Batik</option>
                        <option value="Woodcraft">Woodcraft</option>
                    </select>
                </div>
                <div class="form-group" id="subcategoryGroup" style="display:none;">
                    <label>Sub-category</label>
                    <select name="subcategory" id="subcategorySelect" class="form-control-sm">
                    </select>
                </div>
                <div class="form-group" id="techniqueGroup" style="display:none;">
                    <label>Technique</label>
                    <select name="technique" id="techniqueSelect" class="form-control-sm">
                    </select>
                </div>
                <div class="form-group" id="colorGroup" style="display:none;">
                    <label id="colorLabel">Color</label>
                    <input type="text" name="color" id="colorInput" class="form-control-sm" placeholder="e.g. Red, Blue, Wood Tone">
                </div>
                <div class="row" style="display:flex; gap:1rem;">
                    <div class="form-group" style="flex:1;">
                        <label>Price (RM)</label>
                        <input type="number" step="0.01" name="price" class="form-control-sm" required>
                    </div>
                    <div class="form-group" style="flex:1;">
                        <label>Stock</label>
                        <input type="number" name="stock" class="form-control-sm" required>
                    </div>
                </div>
                <div class="form-group">
                    <label>Product Image</label>
                    <input type="file" name="product_image" accept="image/*" class="form-control-sm"
                        style="padding-top: 5px;">
                </div>
                <button type="submit" class="btn btn-full">Create Listing</button>
            </form>
        </div>
    </div>

    <!-- Footer -->
    <footer>
        <p class="copyright">&copy; 2026 Pasarkraft. Seller Centre.</p>
    </footer>

    <script>
        function openAddModal() {
            document.getElementById('addModal').style.display = 'flex';
        }

        function closeAddModal() {
            document.getElementById('addModal').style.display = 'none';
        }

        function updateSubAndTech() {
            const category = document.getElementById('categorySelect').value;
            const subcategoryGroup = document.getElementById('subcategoryGroup');
            const subcategorySelect = document.getElementById('subcategorySelect');
            const techniqueGroup = document.getElementById('techniqueGroup');
            const techniqueSelect = document.getElementById('techniqueSelect');
            const colorGroup = document.getElementById('colorGroup');
            const colorInput = document.getElementById('colorInput');
            const colorLabel = document.getElementById('colorLabel');

            subcategorySelect.innerHTML = '';
            techniqueSelect.innerHTML = '';
            colorInput.value = '';

            if (category === 'Batik') {
                subcategoryGroup.style.display = 'block';
                techniqueGroup.style.display = 'block';
                colorGroup.style.display = 'block';
                colorLabel.textContent = 'Color';

                const subcategories = ["Batik Textile", "Men's Wear", "Women's Wear", "Handcrafted Items", "Accessories"];
                const techniques = ["Hand-drawn (Canting)", "Block Print (Cap)", "Screen Print"];

                subcategories.forEach(sub => subcategorySelect.innerHTML += `<option value="${sub}">${sub}</option>`);
                techniques.forEach(tech => techniqueSelect.innerHTML += `<option value="${tech}">${tech}</option>`);

            } else if (category === 'Woodcraft') {
                subcategoryGroup.style.display = 'block';
                techniqueGroup.style.display = 'block';
                colorGroup.style.display = 'block';
                colorLabel.textContent = 'Wood Tone';

                const subcategories = ["Furniture", "Home Decor", "Kitchenware", "Traditional Carving", "Souvenirs"];
                const techniques = ["Hand-Carved", "Lathe Turned", "Relief Carving", "Inlay Work"];

                subcategories.forEach(sub => subcategorySelect.innerHTML += `<option value="${sub}">${sub}</option>`);
                techniques.forEach(tech => techniqueSelect.innerHTML += `<option value="${tech}">${tech}</option>`);
            } else {
                subcategoryGroup.style.display = 'none';
                techniqueGroup.style.display = 'none';
                colorGroup.style.display = 'none';
                subcategorySelect.innerHTML = '';
                techniqueSelect.innerHTML = '';
                colorInput.value = '';
            }
        }

        function enableEdit(itemId) {
            const item = document.getElementById(itemId);
            const inputs = item.querySelectorAll('input, select');
            const editBtn = item.querySelector('.btn-edit');
            const saveBtn = item.querySelector('.btn-save');

            inputs.forEach(input => {
                if (!input.classList.contains('item-status-input')) {
                    input.disabled = false;
                    input.style.borderColor = 'var(--accent-color)';
                }
            });

            editBtn.style.display = 'none';
            saveBtn.style.display = 'flex';
        }

        function saveEdit(itemId) {
            const item = document.getElementById(itemId);
            const productId = itemId.replace('item-', '');
            const inputs = item.querySelectorAll('input, select');
            const editBtn = item.querySelector('.btn-edit');
            const saveBtn = item.querySelector('.btn-save');

            const stockInput = item.querySelector('.item-stock-input');
            const priceInput = item.querySelector('.item-price-input');
            const statusInput = item.querySelector('.item-status-input');

            const newPrice = priceInput.value;
            const newStock = stockInput.value;

            fetch('myshop.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=edit_product&product_id=${productId}&price=${newPrice}&stock=${newStock}`
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        inputs.forEach(input => {
                            input.disabled = true;
                            input.style.borderColor = '#ddd';
                        });

                        editBtn.style.display = 'flex';
                        saveBtn.style.display = 'none';

                        alert('Product updated successfully!');

                        statusInput.className = 'form-control-sm item-status-input';

                        if (parseInt(stockInput.value) <= 0) {
                            statusInput.value = 'Sold Out';
                            statusInput.classList.add('status-border-red');
                        } else if (parseInt(stockInput.value) < 10) {
                            statusInput.value = 'Low Stock';
                            statusInput.classList.add('status-border-yellow');
                        } else {
                            statusInput.value = 'Available';
                            statusInput.classList.add('status-border-green');
                        }
                    } else {
                        alert('Failed to update product in database.');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while saving.');
                });
        }

        function deleteItem(itemId) {
            if (confirm('Are you sure to delete this product?')) {
                const productId = itemId.replace('item-', '');

                fetch('myshop.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=delete_product&product_id=${productId}`
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            const item = document.getElementById(itemId);
                            item.style.opacity = '0';
                            setTimeout(() => {
                                item.remove();
                            }, 300);
                        } else {
                            alert('Failed to delete product from database.');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('An error occurred while deleting.');
                    });
            }
        }

        function addItem(e) {
            // Function no longer needed, handled by PHP completely
        }
    </script>
</body>

</html>