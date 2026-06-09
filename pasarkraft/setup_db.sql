-- PasarKraft schema and seed data
-- NOTE: Replace ADMIN_PASSWORD_HASH and USER_PASSWORD_HASH with bcrypt hashes from:
--   php -r "echo password_hash('admin123', PASSWORD_BCRYPT);"
--   php -r "echo password_hash('password123', PASSWORD_BCRYPT);"

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS users (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    firstname VARCHAR(50) NOT NULL,
    lastname VARCHAR(50) NOT NULL,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('buyer', 'seller', 'admin') DEFAULT 'buyer',
    reset_token VARCHAR(10) DEFAULT NULL,
    reset_token_expiry DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS artisans (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    user_id INT(11) NOT NULL UNIQUE,
    shopname VARCHAR(100) NOT NULL,
    ssm VARCHAR(50) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    logo_path VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS products (
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
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS orders (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    buyer_id INT(11) NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    status ENUM('pending', 'completed', 'cancelled') DEFAULT 'pending',
    shipping_address TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (buyer_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS order_items (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    order_id INT(11) NOT NULL,
    product_id INT(11) NOT NULL,
    quantity INT(11) NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS inquiries (
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
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS messages (
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
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS wishlist (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    buyer_id INT(11) NOT NULL,
    product_id INT(11) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (buyer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    UNIQUE KEY unique_wishlist (buyer_id, product_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS user_interactions (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    user_id INT(11) NOT NULL,
    product_id INT(11) NOT NULL,
    interaction_type VARCHAR(50) NOT NULL,
    interaction_value DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS recommendations (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    user_id INT(11) NOT NULL,
    product_id INT(11) NOT NULL,
    score DECIMAL(10,4) NOT NULL,
    recommendation_type VARCHAR(50) NOT NULL,
    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    UNIQUE KEY unique_recommendation (user_id, product_id, recommendation_type)
) ENGINE=InnoDB;

-- Admin account
INSERT INTO users (firstname, lastname, username, email, password, role)
SELECT 'Admin', 'User', 'admin', 'admin@pasarkraft.com', '$2y$10$mCK/xOcOLYNQviAH/XZzcOZTkRw2sOQHxRTa4G43rkK9pYwfhhfmK', 'admin'
WHERE NOT EXISTS (SELECT 1 FROM users WHERE username = 'admin');

-- Seed users (ignore duplicates by username/email)
INSERT IGNORE INTO users (firstname, lastname, username, email, password, role) VALUES
('Amanah', 'Batik', 'amanah_batik', 'amanah@batik.com', '$2y$10$hRZkDGRCgcRDZq11eS5zsOUTDxH3QwWpbH2OIF.BQIPjhrWHKEWXe', 'seller'),
('Warisan', 'Kayu', 'warisan_kayu', 'warisan@kayu.com', '$2y$10$hRZkDGRCgcRDZq11eS5zsOUTDxH3QwWpbH2OIF.BQIPjhrWHKEWXe', 'seller'),
('John', 'Doe', 'buyer1', 'buyer1@kraft.com', '$2y$10$hRZkDGRCgcRDZq11eS5zsOUTDxH3QwWpbH2OIF.BQIPjhrWHKEWXe', 'buyer'),
('Jane', 'Smith', 'buyer2', 'buyer2@kraft.com', '$2y$10$hRZkDGRCgcRDZq11eS5zsOUTDxH3QwWpbH2OIF.BQIPjhrWHKEWXe', 'buyer'),
('Ahmad', 'Kassim', 'buyer3', 'buyer3@kraft.com', '$2y$10$hRZkDGRCgcRDZq11eS5zsOUTDxH3QwWpbH2OIF.BQIPjhrWHKEWXe', 'buyer'),
('Siti', 'Aminah', 'buyer4', 'buyer4@kraft.com', '$2y$10$hRZkDGRCgcRDZq11eS5zsOUTDxH3QwWpbH2OIF.BQIPjhrWHKEWXe', 'buyer'),
('Ali', 'Abu', 'buyer5', 'buyer5@kraft.com', '$2y$10$hRZkDGRCgcRDZq11eS5zsOUTDxH3QwWpbH2OIF.BQIPjhrWHKEWXe', 'buyer');

-- Artisans
INSERT IGNORE INTO artisans (user_id, shopname, ssm, phone)
SELECT id, 'Amanah Batik Shop', 'SSM12345', '0123456789' FROM users WHERE username = 'amanah_batik';
INSERT IGNORE INTO artisans (user_id, shopname, ssm, phone)
SELECT id, 'Warisan Kayu', 'SSM54321', '0198765432' FROM users WHERE username = 'warisan_kayu';

-- Products
INSERT IGNORE INTO products (seller_id, title, description, category, subcategory, technique, color, material, style, tags, price, stock, image_path)
SELECT u.id, p.title, p.description, p.category, p.subcategory, p.technique, p.color, p.material, p.style, p.tags, p.price, p.stock, p.image_path
FROM (
    SELECT 'amanah_batik' AS seller_username, 'Premium Silk Batik Shirt' AS title,
           'Hand-drawn floral batik shirt using high-grade Chinese silk fabric. Exquisite canting craftsmanship.' AS description,
           'Batik' AS category, 'Men''s Wear' AS subcategory, 'Hand-drawn (Canting)' AS technique,
           'Indigo Blue' AS color, 'Silk' AS material, 'Modern' AS style,
           'silk, luxury, formal, fashion, floral' AS tags, 249.90 AS price, 15 AS stock, 'batik_shirt.png' AS image_path
    UNION ALL
    SELECT 'amanah_batik', 'Floral Cotton Batik Sarong',
           'Traditional block printed floral batik sarong with organic cotton cloth. Soft and comfortable.',
           'Batik', 'Batik Textile', 'Block Print (Cap)',
           'Terracotta Red', 'Cotton', 'Traditional',
           'floral, cotton, daily, sarong, classic', 89.00, 30, 'batik_scarf.png'
    UNION ALL
    SELECT 'warisan_kayu', 'Teak Wood Dining Table',
           'Elegant and highly durable dining table handcrafted from solid Indonesian teak wood. Modern clean cut styling.',
           'Woodcraft', 'Furniture', 'Lathe Turned',
           'Natural Brown', 'Teak Wood', 'Modern',
           'luxury, dining, heavy-duty, teak, table', 1899.00, 3, 'wood_table.png'
    UNION ALL
    SELECT 'warisan_kayu', 'Mahogany Hand-Carved Vase',
           'Rustic mahogany wood vase meticulously hand-carved with traditional floral relief motif.',
           'Woodcraft', 'Home Decor', 'Hand-Carved',
           'Earth Brown', 'Mahogany Wood', 'Traditional',
           'floral, vase, carving, rustic, decorative', 120.00, 8, 'wood_vase.png'
    UNION ALL
    SELECT 'amanah_batik', 'Modern Geometric Scarf',
           'Lightweight geometric pattern chiffon scarf. Screen-printed and ideal for casual styling.',
           'Batik', 'Accessories', 'Screen Print',
           'Golden Yellow', 'Chiffon', 'Modern',
           'geometric, styling, scarf, lightweight, casual', 45.00, 50, 'batik_scarf.png'
    UNION ALL
    SELECT 'warisan_kayu', 'Relief Carved Wall Art',
           'Spectacular spiritual wall panel hand carved in meranti wood featuring abstract organic floral details.',
           'Woodcraft', 'Home Decor', 'Relief Carving',
           'Dark Mahogany', 'Meranti Wood', 'Traditional',
           'wall-decor, carving, traditional, relief, elegant', 450.00, 5, 'wood_art.png'
    UNION ALL
    SELECT 'amanah_batik', 'Batik Cotton Table Runner',
           'Authentic block print batik table runner made from unbleached cotton, featuring elegant local patterns.',
           'Batik', 'Handcrafted Items', 'Block Print (Cap)',
           'Leaf Green', 'Cotton', 'Traditional',
           'floral, runner, table, cotton, handmade', 65.00, 20, 'batik_scarf.png'
    UNION ALL
    SELECT 'warisan_kayu', 'Bamboo Weaved Hanging Lamp',
           'Stunning modern eco-friendly hanging pendant lamp crafted from premium split bamboo inlay.',
           'Woodcraft', 'Home Decor', 'Inlay Work',
           'Light Wood', 'Bamboo', 'Modern',
           'lighting, bamboo, eco-friendly, hanging, modern', 180.00, 12, 'wood_lamp.png'
) AS p
JOIN users u ON u.username = p.seller_username;

-- User interactions
INSERT IGNORE INTO user_interactions (user_id, product_id, interaction_type, interaction_value)
SELECT u.id, pr.id, i.interaction_type, i.interaction_value
FROM (
    SELECT 'buyer1' AS username, 'Premium Silk Batik Shirt' AS title, 'purchase' AS interaction_type, 5.0 AS interaction_value
    UNION ALL SELECT 'buyer1', 'Batik Cotton Table Runner', 'wishlist', 3.0
    UNION ALL SELECT 'buyer1', 'Floral Cotton Batik Sarong', 'click', 2.0
    UNION ALL SELECT 'buyer1', 'Modern Geometric Scarf', 'view', 1.0
    UNION ALL SELECT 'buyer2', 'Teak Wood Dining Table', 'purchase', 5.0
    UNION ALL SELECT 'buyer2', 'Mahogany Hand-Carved Vase', 'wishlist', 3.0
    UNION ALL SELECT 'buyer2', 'Bamboo Weaved Hanging Lamp', 'click', 2.0
    UNION ALL SELECT 'buyer2', 'Relief Carved Wall Art', 'view', 1.0
    UNION ALL SELECT 'buyer3', 'Modern Geometric Scarf', 'purchase', 5.0
    UNION ALL SELECT 'buyer3', 'Premium Silk Batik Shirt', 'wishlist', 3.0
    UNION ALL SELECT 'buyer3', 'Floral Cotton Batik Sarong', 'click', 2.0
    UNION ALL SELECT 'buyer4', 'Relief Carved Wall Art', 'purchase', 5.0
    UNION ALL SELECT 'buyer4', 'Bamboo Weaved Hanging Lamp', 'wishlist', 3.0
    UNION ALL SELECT 'buyer4', 'Mahogany Hand-Carved Vase', 'view', 1.0
    UNION ALL SELECT 'buyer5', 'Premium Silk Batik Shirt', 'click', 2.0
    UNION ALL SELECT 'buyer5', 'Floral Cotton Batik Sarong', 'click', 2.0
    UNION ALL SELECT 'buyer5', 'Modern Geometric Scarf', 'wishlist', 3.0
    UNION ALL SELECT 'buyer5', 'Bamboo Weaved Hanging Lamp', 'view', 1.0
) AS i
JOIN users u ON u.username = i.username
JOIN products pr ON pr.title = i.title;
