<?php
$servername = "localhost";
$username = "root";
$password = "";

// Create connection
$conn = new mysqli($servername, $username, $password);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create database (bypassing the old corrupted one)
$sql = "CREATE DATABASE IF NOT EXISTS pasarkraft_db";
if ($conn->query($sql) === TRUE) {
    echo "Database pasarkraft_db created successfully.<br>";
} else {
    echo "Error creating database: " . $conn->error . "<br>";
}

// Select database
$conn->select_db("pasarkraft_db");

// Table: Users
$sql_users = "CREATE TABLE IF NOT EXISTS users (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    firstname VARCHAR(50) NOT NULL,
    lastname VARCHAR(50) NOT NULL,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('buyer', 'seller', 'admin') DEFAULT 'buyer',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB"; // <-- Explicitly set Engine

if ($conn->query($sql_users) === TRUE) {
    echo "Table users created successfully.<br>";
} else {
    echo "Error creating table users: " . $conn->error . "<br>";
}

// Table: Artisans (Sellers Information)
$sql_artisans = "CREATE TABLE IF NOT EXISTS artisans (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    user_id INT(11) NOT NULL UNIQUE,
    shopname VARCHAR(100) NOT NULL,
    ssm VARCHAR(50) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    logo_path VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB";

if ($conn->query($sql_artisans) === TRUE) {
    echo "Table artisans checked/created successfully.<br>";
    
    // Patch legacy databases
    $check_logo = $conn->query("SHOW COLUMNS FROM artisans LIKE 'logo_path'");
    if ($check_logo->num_rows == 0) {
        if ($conn->query("ALTER TABLE artisans ADD COLUMN logo_path VARCHAR(255) DEFAULT NULL")) {
            echo "Successfully patched legacy artisans table with logo_path.<br>";
        }
    }
} else {
    echo "Error creating table artisans: " . $conn->error . "<br>";
}

// Table: Products
$sql_products = "CREATE TABLE IF NOT EXISTS products (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    seller_id INT(11) NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    category VARCHAR(50),
    subcategory VARCHAR(100),
    technique VARCHAR(100),
    color VARCHAR(50),
    material VARCHAR(100) DEFAULT NULL,
    style VARCHAR(100) DEFAULT NULL,
    tags TEXT DEFAULT NULL,
    price DECIMAL(10,2) NOT NULL,
    stock INT(11) DEFAULT 0,
    image_path VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB"; // <-- Explicitly set Engine

if ($conn->query($sql_products) === TRUE) {
    echo "Table products checked/created successfully.<br>";
    
    // Patch legacy databases
    $check_cols = $conn->query("SHOW COLUMNS FROM products LIKE 'subcategory'");
    if ($check_cols->num_rows == 0) {
        if ($conn->query("ALTER TABLE products ADD COLUMN subcategory VARCHAR(100) DEFAULT NULL, ADD COLUMN technique VARCHAR(100) DEFAULT NULL")) {
            echo "Successfully patched legacy products table with subcategory and technique.<br>";
        }
    }

    $check_cols2 = $conn->query("SHOW COLUMNS FROM products LIKE 'color'");
    if ($check_cols2->num_rows == 0) {
        if ($conn->query("ALTER TABLE products ADD COLUMN color VARCHAR(50) DEFAULT NULL")) {
            echo "Successfully patched legacy products table with color.<br>";
        }
    }

    // Patch for recommender columns
    $check_mat = $conn->query("SHOW COLUMNS FROM products LIKE 'material'");
    if ($check_mat->num_rows == 0) {
        if ($conn->query("ALTER TABLE products ADD COLUMN material VARCHAR(100) DEFAULT NULL, ADD COLUMN style VARCHAR(100) DEFAULT NULL, ADD COLUMN tags TEXT DEFAULT NULL")) {
            echo "Successfully patched legacy products table with material, style, and tags.<br>";
        }
    }
} else {
    echo "Error creating table products: " . $conn->error . "<br>";
}

// Table: Orders
$sql_orders = "CREATE TABLE IF NOT EXISTS orders (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    buyer_id INT(11) NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    status ENUM('pending', 'completed', 'cancelled') DEFAULT 'pending',
    shipping_address TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (buyer_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB"; // <-- Explicitly set Engine

if ($conn->query($sql_orders) === TRUE) {
    echo "Table orders created successfully.<br>";
} else {
    echo "Error creating table orders: " . $conn->error . "<br>";
}

// Table: Order Items
$sql_order_items = "CREATE TABLE IF NOT EXISTS order_items (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    order_id INT(11) NOT NULL,
    product_id INT(11) NOT NULL,
    quantity INT(11) NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB"; // <-- Explicitly set Engine

if ($conn->query($sql_order_items) === TRUE) {
    echo "Table order_items created successfully.<br>";
} else {
    echo "Error creating table order_items: " . $conn->error . "<br>";
}

// Table: Inquiries
$sql_inquiries = "CREATE TABLE IF NOT EXISTS inquiries (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    buyer_id INT(11) NOT NULL,
    seller_id INT(11) NOT NULL,
    product_id INT(11) NOT NULL,
    current_offer DECIMAL(10,2) DEFAULT NULL,
    status ENUM('In Discussion', 'Deal Agreed', 'No Deal', 'Sold') DEFAULT 'In Discussion',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (buyer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB";

if ($conn->query($sql_inquiries) === TRUE) {
    echo "Table inquiries created successfully.<br>";
} else {
    echo "Error creating table inquiries: " . $conn->error . "<br>";
}

// Table: Messages (for Chat Feature)
$sql_messages = "CREATE TABLE IF NOT EXISTS messages (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    sender_id INT(11) NOT NULL,
    receiver_id INT(11) NOT NULL,
    product_id INT(11) DEFAULT NULL,
    inquiry_id INT(11) DEFAULT NULL,
    message TEXT NOT NULL,
    is_offer BOOLEAN DEFAULT FALSE,
    offer_amount DECIMAL(10,2) DEFAULT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL,
    FOREIGN KEY (inquiry_id) REFERENCES inquiries(id) ON DELETE CASCADE
) ENGINE=InnoDB"; // <-- Explicitly set Engine

if ($conn->query($sql_messages) === TRUE) {
    echo "Table messages created successfully.<br>";
    
    // Patch legacy databases
    $check_msg = $conn->query("SHOW COLUMNS FROM messages LIKE 'inquiry_id'");
    if ($check_msg->num_rows == 0) {
        $conn->query("ALTER TABLE messages ADD COLUMN inquiry_id INT(11) DEFAULT NULL, ADD COLUMN is_offer BOOLEAN DEFAULT FALSE, ADD COLUMN offer_amount DECIMAL(10,2) DEFAULT NULL");
        $conn->query("ALTER TABLE messages ADD FOREIGN KEY (inquiry_id) REFERENCES inquiries(id) ON DELETE CASCADE");
        echo "Successfully patched legacy messages table.<br>";
    }
} else {
    echo "Error creating table messages: " . $conn->error . "<br>";
}

// Table: Wishlist
$sql_wishlist = "CREATE TABLE IF NOT EXISTS wishlist (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    buyer_id INT(11) NOT NULL,
    product_id INT(11) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (buyer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    UNIQUE KEY unique_wishlist (buyer_id, product_id)
) ENGINE=InnoDB";

if ($conn->query($sql_wishlist) === TRUE) {
    echo "Table wishlist created successfully.<br>";
} else {
    echo "Error creating table wishlist: " . $conn->error . "<br>";
}

// Table: User Interactions
$sql_interactions = "CREATE TABLE IF NOT EXISTS user_interactions (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    user_id INT(11) NOT NULL,
    product_id INT(11) NOT NULL,
    interaction_type VARCHAR(50) NOT NULL,
    interaction_value DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB";

if ($conn->query($sql_interactions) === TRUE) {
    echo "Table user_interactions created successfully.<br>";
} else {
    echo "Error creating table user_interactions: " . $conn->error . "<br>";
}

// Table: Recommendations
$sql_recommendations = "CREATE TABLE IF NOT EXISTS recommendations (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    user_id INT(11) NOT NULL,
    product_id INT(11) NOT NULL,
    score DECIMAL(10,4) NOT NULL,
    recommendation_type VARCHAR(50) NOT NULL,
    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    UNIQUE KEY unique_recommendation (user_id, product_id, recommendation_type)
) ENGINE=InnoDB";

if ($conn->query($sql_recommendations) === TRUE) {
    echo "Table recommendations created successfully.<br>";
} else {
    echo "Error creating table recommendations: " . $conn->error . "<br>";
}

// Insert admin account if not exists
$admin_pass = password_hash('admin123', PASSWORD_BCRYPT);
$sql_admin = "INSERT INTO users (firstname, lastname, username, email, password, role) 
              SELECT 'Admin', 'User', 'admin', 'admin@pasarkraft.com', '$admin_pass', 'admin' 
              WHERE NOT EXISTS (SELECT 1 FROM users WHERE username = 'admin')";

if ($conn->query($sql_admin) === TRUE) {
    echo "Admin account checked/created.<br>";
} else {
    echo "Error inserting admin account: " . $conn->error . "<br>";
}

// --- SEEDER ROUTINE ---
// Check if we need to seed users
$user_check = $conn->query("SELECT COUNT(*) as count FROM users WHERE role != 'admin'");
$user_count = $user_check->fetch_assoc()['count'];

if ($user_count == 0) {
    echo "Seeding user and product data for AI Recommender...<br>";
    $pass = password_hash('password123', PASSWORD_BCRYPT);
    
    // 1. Insert Sellers
    $sellers = [
        ['Amanah', 'Batik', 'amanah_batik', 'amanah@batik.com', 'Amanah Batik Shop', 'SSM12345', '0123456789'],
        ['Warisan', 'Kayu', 'warisan_kayu', 'warisan@kayu.com', 'Warisan Kayu', 'SSM54321', '0198765432']
    ];
    
    $seller_ids = [];
    foreach ($sellers as $s) {
        $conn->query("INSERT INTO users (firstname, lastname, username, email, password, role) VALUES ('{$s[0]}', '{$s[1]}', '{$s[2]}', '{$s[3]}', '$pass', 'seller')");
        $uid = $conn->insert_id;
        $seller_ids[$s[2]] = $uid;
        
        $conn->query("INSERT INTO artisans (user_id, shopname, ssm, phone) VALUES ($uid, '{$s[4]}', '{$s[5]}', '{$s[6]}')");
    }
    
    // 2. Insert Buyers
    $buyers = [
        ['John', 'Doe', 'buyer1', 'buyer1@kraft.com'],
        ['Jane', 'Smith', 'buyer2', 'buyer2@kraft.com'],
        ['Ahmad', 'Kassim', 'buyer3', 'buyer3@kraft.com'],
        ['Siti', 'Aminah', 'buyer4', 'buyer4@kraft.com'],
        ['Ali', 'Abu', 'buyer5', 'buyer5@kraft.com']
    ];
    
    $buyer_ids = [];
    foreach ($buyers as $b) {
        $conn->query("INSERT INTO users (firstname, lastname, username, email, password, role) VALUES ('{$b[0]}', '{$b[1]}', '{$b[2]}', '{$b[3]}', '$pass', 'buyer')");
        $buyer_ids[$b[2]] = $conn->insert_id;
    }
    
    // 3. Insert Products
    $products = [
        ['amanah_batik', 'Premium Silk Batik Shirt', 'Hand-drawn floral batik shirt using high-grade Chinese silk fabric. Exquisite canting craftsmanship.', 'Batik', 'Men\'s Wear', 'Hand-drawn (Canting)', 'Indigo Blue', 'Silk', 'Modern', 'silk, luxury, formal, fashion, floral', 249.90, 15, 'batik_shirt.png'],
        ['amanah_batik', 'Floral Cotton Batik Sarong', 'Traditional block printed floral batik sarong with organic cotton cloth. Soft and comfortable.', 'Batik', 'Batik Textile', 'Block Print (Cap)', 'Terracotta Red', 'Cotton', 'Traditional', 'floral, cotton, daily, sarong, classic', 89.00, 30, 'batik_scarf.png'],
        ['warisan_kayu', 'Teak Wood Dining Table', 'Elegant and highly durable dining table handcrafted from solid Indonesian teak wood. Modern clean cut styling.', 'Woodcraft', 'Furniture', 'Lathe Turned', 'Natural Brown', 'Teak Wood', 'Modern', 'luxury, dining, heavy-duty, teak, table', 1899.00, 3, 'wood_table.png'],
        ['warisan_kayu', 'Mahogany Hand-Carved Vase', 'Rustic mahogany wood vase meticulously hand-carved with traditional floral relief motif.', 'Woodcraft', 'Home Decor', 'Hand-Carved', 'Earth Brown', 'Mahogany Wood', 'Traditional', 'floral, vase, carving, rustic, decorative', 120.00, 8, 'wood_vase.png'],
        ['amanah_batik', 'Modern Geometric Scarf', 'Lightweight geometric pattern chiffon scarf. Screen-printed and ideal for casual styling.', 'Batik', 'Accessories', 'Screen Print', 'Golden Yellow', 'Chiffon', 'Modern', 'geometric, styling, scarf, lightweight, casual', 45.00, 50, 'batik_scarf.png'],
        ['warisan_kayu', 'Relief Carved Wall Art', 'Spectacular spiritual wall panel hand carved in meranti wood featuring abstract organic floral details.', 'Woodcraft', 'Home Decor', 'Relief Carving', 'Dark Mahogany', 'Meranti Wood', 'Traditional', 'wall-decor, carving, traditional, relief, elegant', 450.00, 5, 'wood_art.png'],
        ['amanah_batik', 'Batik Cotton Table Runner', 'Authentic block print batik table runner made from unbleached cotton, featuring elegant local patterns.', 'Batik', 'Handcrafted Items', 'Block Print (Cap)', 'Leaf Green', 'Cotton', 'Traditional', 'floral, runner, table, cotton, handmade', 65.00, 20, 'batik_scarf.png'],
        ['warisan_kayu', 'Bamboo Weaved Hanging Lamp', 'Stunning modern eco-friendly hanging pendant lamp crafted from premium split bamboo inlay.', 'Woodcraft', 'Home Decor', 'Inlay Work', 'Light Wood', 'Bamboo', 'Modern', 'lighting, bamboo, eco-friendly, hanging, modern', 180.00, 12, 'wood_lamp.png']
    ];
    
    $product_ids = [];
    foreach ($products as $p) {
        $sid = $seller_ids[$p[0]];
        $conn->query("INSERT INTO products (seller_id, title, description, category, subcategory, technique, color, material, style, tags, price, stock, image_path) VALUES ($sid, '{$p[1]}', '{$p[2]}', '{$p[3]}', '{$p[4]}', '{$p[5]}', '{$p[6]}', '{$p[7]}', '{$p[8]}', '{$p[9]}', {$p[10]}, {$p[11]}, '{$p[12]}')");
        $pid = $conn->insert_id;
        $product_ids[$p[1]] = $pid;
    }
    
    // 4. Insert User Interactions (representing user profile vectors)
    // Ratings/Values: View=1, Click=2, Wishlist=3, Add to cart=4, Purchase=5
    $interactions = [
        // buyer1: Loves Batik (garments and runner)
        ['buyer1', 'Premium Silk Batik Shirt', 'purchase', 5.0],
        ['buyer1', 'Batik Cotton Table Runner', 'wishlist', 3.0],
        ['buyer1', 'Floral Cotton Batik Sarong', 'click', 2.0],
        ['buyer1', 'Modern Geometric Scarf', 'view', 1.0],
        
        // buyer2: Loves Woodcraft (furniture and home decor)
        ['buyer2', 'Teak Wood Dining Table', 'purchase', 5.0],
        ['buyer2', 'Mahogany Hand-Carved Vase', 'wishlist', 3.0],
        ['buyer2', 'Bamboo Weaved Hanging Lamp', 'click', 2.0],
        ['buyer2', 'Relief Carved Wall Art', 'view', 1.0],
        
        // buyer3: Fashion & Silk/Chiffon Batik
        ['buyer3', 'Modern Geometric Scarf', 'purchase', 5.0],
        ['buyer3', 'Premium Silk Batik Shirt', 'wishlist', 3.0],
        ['buyer3', 'Floral Cotton Batik Sarong', 'click', 2.0],
        
        // buyer4: Woodcraft Home Decor and Art
        ['buyer4', 'Relief Carved Wall Art', 'purchase', 5.0],
        ['buyer4', 'Bamboo Weaved Hanging Lamp', 'wishlist', 3.0],
        ['buyer4', 'Mahogany Hand-Carved Vase', 'view', 1.0],
        
        // buyer5: Mixed behavior (clicks multiple silk/cotton batik products, view a light lamp)
        ['buyer5', 'Premium Silk Batik Shirt', 'click', 2.0],
        ['buyer5', 'Floral Cotton Batik Sarong', 'click', 2.0],
        ['buyer5', 'Modern Geometric Scarf', 'wishlist', 3.0],
        ['buyer5', 'Bamboo Weaved Hanging Lamp', 'view', 1.0],
    ];
    
    foreach ($interactions as $i) {
        $uid = $buyer_ids[$i[0]];
        $pid = $product_ids[$i[1]];
        $conn->query("INSERT INTO user_interactions (user_id, product_id, interaction_type, interaction_value) VALUES ($uid, $pid, '{$i[2]}', {$i[3]})");
    }
    echo "Successfully seeded user and product recommender data!<br>";
}

$conn->close();
echo "<h3>Database setup complete! You can now use the application.</h3>";
?>