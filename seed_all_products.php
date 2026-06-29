<?php
/**
 * PasarKraft Database Seeding Engine - 5 New Unique Sellers & 49 Products
 * Run this script once to populate the database on InfinityFree.
 */
require_once 'db_connect.php';

// Enable error output for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h2>Starting Database Seeding Process...</h2>";

// 1. Define the 5 new unique sellers
$sellers = [
    [
        'shopname' => 'Cengal Klasik',
        'email' => 'cengalklasik@gmail.com',
        'username' => 'cengalklasik',
        'phone' => '+60123456001',
        'ssm' => '202601009001',
        'password' => 'CengalKlasik123!',
        'description' => 'Premium Malaysian woodcrafts and custom live-edge furniture.',
        'location' => 'Terengganu, Malaysia'
    ],
    [
        'shopname' => 'Sanggar Arca',
        'email' => 'sanggararca@gmail.com',
        'username' => 'sanggararca',
        'phone' => '+60123456002',
        'ssm' => '202601009002',
        'password' => 'SanggarArca123!',
        'description' => 'Artistic wood sculptures, traditional Malay carvings, and luxury home decor.',
        'location' => 'Kelantan, Malaysia'
    ],
    [
        'shopname' => 'Batik Anggun',
        'email' => 'batikanggun@gmail.com',
        'username' => 'batikanggun',
        'phone' => '+60123456003',
        'ssm' => '202601009003',
        'password' => 'BatikAnggun123!',
        'description' => 'Elegant and modern hand-drawn and block-printed Batik apparel.',
        'location' => 'Terengganu, Malaysia'
    ],
    [
        'shopname' => 'Tenun Kencana',
        'email' => 'tenunkencana@gmail.com',
        'username' => 'tenunkencana',
        'phone' => '+60123456004',
        'ssm' => '202601009004',
        'password' => 'TenunKencana123!',
        'description' => 'Traditional textiles, unstitched silk batik, and handcrafted accessories.',
        'location' => 'Pahang, Malaysia'
    ],
    [
        'shopname' => 'Nusantara Bags',
        'email' => 'nusantarabags@gmail.com',
        'username' => 'nusantarabags',
        'phone' => '+60123456005',
        'ssm' => '202601009005',
        'password' => 'NusantaraBags123!',
        'description' => 'Handcrafted bags, pouches, and wooden lifestyle souvenirs.',
        'location' => 'Selangor, Malaysia'
    ]
];

$seller_ids = [];

foreach ($sellers as $s) {
    // Check if user already exists
    $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $check->bind_param("s", $s['email']);
    $check->execute();
    $res = $check->get_result();
    
    if ($res->num_rows > 0) {
        $row = $res->fetch_assoc();
        $user_id = $row['id'];
        echo "Seller '{$s['shopname']}' already exists (User ID: $user_id).<br>";
    } else {
        // Create user
        $hashed_pw = password_hash($s['password'], PASSWORD_DEFAULT);
        $role = 'seller';
        
        $ins_user = $conn->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
        $ins_user->bind_param("ssss", $s['username'], $s['email'], $hashed_pw, $role);
        $ins_user->execute();
        $user_id = $ins_user->insert_id;
        $ins_user->close();
        
        // Create artisan profile
        $status = 'approved';
        $ins_artisan = $conn->prepare("INSERT INTO artisans (user_id, shopname, description, ssm_no, phone_number, location, approval_status) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $ins_artisan->bind_param("issssss", $user_id, $s['shopname'], $s['description'], $s['ssm'], $s['phone'], $s['location'], $status);
        $ins_artisan->execute();
        $ins_artisan->close();
        
        echo "Successfully registered seller '{$s['shopname']}' (User ID: $user_id).<br>";
    }
    $seller_ids[$s['shopname']] = $user_id;
    $check->close();
}

// 2. Define the 49 products
$products = [
    // --- SELLER 1: Cengal Klasik ---
    [
        'shop' => 'Cengal Klasik',
        'title' => 'Solid Wood Natural Edge Dining Table',
        'desc' => 'A magnificent dining table crafted from a single solid wood slab with a rustic live-edge design. Features modern black steel legs.',
        'cat' => 'Woodcraft',
        'subcat' => 'Furniture',
        'tech' => 'Natural Form',
        'color' => 'Brown',
        'mat' => 'Solid Wood',
        'style' => 'Modern',
        'tags' => 'dining table, slab table, live edge, steel legs, luxury furniture',
        'price' => 2450.00,
        'stock' => 2,
        'img' => 'woodcraft_slab_dining_table.png'
    ],
    [
        'shop' => 'Cengal Klasik',
        'title' => 'Rustic Log Dining Table & Stool Set',
        'desc' => 'A complete rustic outdoor/indoor dining set featuring a thick slab table supported by sturdy log bases and matching log stools.',
        'cat' => 'Woodcraft',
        'subcat' => 'Furniture',
        'tech' => 'Carving',
        'color' => 'Brown',
        'mat' => 'Rustic Log Wood',
        'style' => 'Rustic',
        'tags' => 'log table, stool set, rustic dining, outdoor furniture',
        'price' => 1800.00,
        'stock' => 3,
        'img' => 'woodcraft_dining_table_logs.png'
    ],
    [
        'shop' => 'Cengal Klasik',
        'title' => 'Handcrafted Driftwood Swing Bench',
        'desc' => 'A beautiful hanging swing bench featuring traditional Malay wood carvings on the side panels. Perfect for gardens or patios.',
        'cat' => 'Woodcraft',
        'subcat' => 'Furniture',
        'tech' => 'Carving',
        'color' => 'Brown',
        'mat' => 'Merbau Wood',
        'style' => 'Traditional',
        'tags' => 'swing bench, garden swing, carved bench, merbau',
        'price' => 1150.00,
        'stock' => 2,
        'img' => 'woodcraft_swing_bench.png'
    ],
    [
        'shop' => 'Cengal Klasik',
        'title' => 'Glass-Top Log Base Coffee Table',
        'desc' => 'A contemporary coffee table featuring a thick tempered glass top resting on a cluster of natural teak logs.',
        'cat' => 'Woodcraft',
        'subcat' => 'Furniture',
        'tech' => 'Natural Form',
        'color' => 'Brown',
        'mat' => 'Teak & Glass',
        'style' => 'Modern',
        'tags' => 'coffee table, glass table, log base, teak wood, modern furniture',
        'price' => 1450.00,
        'stock' => 3,
        'img' => 'woodcraft_glass_table_logs.png'
    ],
    [
        'shop' => 'Cengal Klasik',
        'title' => 'Solid Wood Slab Coffee Table',
        'desc' => 'A beautiful low-profile coffee table cut from a single slab of seasoned teak wood, featuring sturdy matching wood legs.',
        'cat' => 'Woodcraft',
        'subcat' => 'Furniture',
        'tech' => 'Natural Form',
        'color' => 'Brown',
        'mat' => 'Teak Wood',
        'style' => 'Rustic',
        'tags' => 'coffee table, slab table, solid wood, rustic decor, teak furniture',
        'price' => 980.00,
        'stock' => 4,
        'img' => 'woodcraft_slab_coffee_table.png'
    ],
    [
        'shop' => 'Cengal Klasik',
        'title' => 'Hand-Carved Teak Sideboard Cabinet',
        'desc' => 'A spacious sideboard cabinet with three doors, featuring exquisite hand-carved floral patterns on the panels.',
        'cat' => 'Woodcraft',
        'subcat' => 'Furniture',
        'tech' => 'Carving',
        'color' => 'Brown',
        'mat' => 'Teak Wood',
        'style' => 'Traditional',
        'tags' => 'sideboard, cabinet, teak wood, hand-carved, traditional furniture',
        'price' => 2850.00,
        'stock' => 2,
        'img' => 'woodcraft_sideboard_cabinet.png'
    ],
    [
        'shop' => 'Cengal Klasik',
        'title' => 'Classic Teak Wood Garden Bench',
        'desc' => 'A durable and weather-resistant outdoor garden bench made of premium seasoned teak wood.',
        'cat' => 'Woodcraft',
        'subcat' => 'Furniture',
        'tech' => 'Lathe Turned',
        'color' => 'Brown',
        'mat' => 'Teak Wood',
        'style' => 'Traditional',
        'tags' => 'garden bench, outdoor seating, teak wood, classic bench',
        'price' => 750.00,
        'stock' => 5,
        'img' => 'woodcraft_wooden_bench.png'
    ],
    [
        'shop' => 'Cengal Klasik',
        'title' => 'Live Edge Slab Wood Bench',
        'desc' => 'A rustic bench crafted from a thick slab of solid wood, displaying the natural organic curves of the tree.',
        'cat' => 'Woodcraft',
        'subcat' => 'Furniture',
        'tech' => 'Natural Form',
        'color' => 'Brown',
        'mat' => 'Solid Wood',
        'style' => 'Rustic',
        'tags' => 'slab bench, live edge, rustic bench, solid wood seating',
        'price' => 850.00,
        'stock' => 3,
        'img' => 'woodcraft_slab_bench.png'
    ],
    [
        'shop' => 'Cengal Klasik',
        'title' => 'Minimalist Teak Console Table',
        'desc' => 'A sleek entryway console table with a single drawer, showcasing the beautiful natural grain of teak.',
        'cat' => 'Woodcraft',
        'subcat' => 'Furniture',
        'tech' => 'Natural Form',
        'color' => 'Brown',
        'mat' => 'Teak Wood',
        'style' => 'Modern',
        'tags' => 'console table, entryway table, minimalist furniture, teak wood',
        'price' => 920.00,
        'stock' => 3,
        'img' => 'woodcraft_console_table.png'
    ],

    // --- SELLER 2: Sanggar Arca ---
    [
        'shop' => 'Sanggar Arca',
        'title' => 'Flame of the Forest Wood Sculpture',
        'desc' => 'A vertical abstract wood carving representing flames, carved from a single piece of premium teak. Includes a sturdy display base.',
        'cat' => 'Woodcraft',
        'subcat' => 'Handcrafted Items',
        'tech' => 'Sculpting',
        'color' => 'Brown',
        'mat' => 'Teak Wood',
        'style' => 'Modern',
        'tags' => 'sculpture, abstract, teak art, home decor',
        'price' => 450.00,
        'stock' => 1,
        'img' => 'woodcraft_abstract_sculpture.jpg'
    ],
    [
        'shop' => 'Sanggar Arca',
        'title' => 'Mounted Driftwood Sculptural Art',
        'desc' => 'Beautifully aged organic driftwood mounted on metal stands over a polished wood base. A minimalist art piece for modern homes.',
        'cat' => 'Woodcraft',
        'subcat' => 'Handcrafted Items',
        'tech' => 'Natural Form',
        'color' => 'Brown',
        'mat' => 'Driftwood',
        'style' => 'Modern',
        'tags' => 'driftwood art, organic sculpture, minimalist decor',
        'price' => 195.00,
        'stock' => 4,
        'img' => 'woodcraft_driftwood_sculpture.png'
    ],
    [
        'shop' => 'Sanggar Arca',
        'title' => 'Traditional Carved Floral Wall Plaque',
        'desc' => 'Square wooden wall panel featuring hand-carved floral scrolls and geometric cutouts. Perfect for adding traditional charm to any wall.',
        'cat' => 'Woodcraft',
        'subcat' => 'Handcrafted Items',
        'tech' => 'Carving',
        'color' => 'Brown',
        'mat' => 'Solid Wood',
        'style' => 'Traditional',
        'tags' => 'wall plaque, floral carving, wood panel, wall art',
        'price' => 145.00,
        'stock' => 5,
        'img' => 'woodcraft_carved_panel.png'
    ],
    [
        'shop' => 'Sanggar Arca',
        'title' => 'Ornate Hand-Carved Wooden Doors',
        'desc' => 'A pair of magnificent entrance doors featuring traditional deep floral carvings on solid teak wood.',
        'cat' => 'Woodcraft',
        'subcat' => 'Traditional Carving',
        'tech' => 'Carving',
        'color' => 'Brown',
        'mat' => 'Teak Wood',
        'style' => 'Traditional',
        'tags' => 'carved doors, entrance doors, traditional carving, floral motif, luxury woodcraft',
        'price' => 4200.00,
        'stock' => 1,
        'img' => 'woodcraft_carved_doors.png'
    ],
    [
        'shop' => 'Sanggar Arca',
        'title' => 'Royal Keris Wooden Display Stand',
        'desc' => 'A traditional display stand designed to hold a royal Malay Keris, carved with intricate dragon and leaf motifs.',
        'cat' => 'Woodcraft',
        'subcat' => 'Traditional Carving',
        'tech' => 'Carving',
        'color' => 'Brown',
        'mat' => 'Mahogany Wood',
        'style' => 'Traditional',
        'tags' => 'keris stand, display stand, traditional carving, royal heritage, mahogany',
        'price' => 180.00,
        'stock' => 8,
        'img' => 'keris standee.png'
    ],
    [
        'shop' => 'Sanggar Arca',
        'title' => 'Carved Foldable Wooden Rehal',
        'desc' => 'A traditional foldable Quran book stand, hand-carved with Islamic geometric patterns from a single piece of teak.',
        'cat' => 'Woodcraft',
        'subcat' => 'Traditional Carving',
        'tech' => 'Carving',
        'color' => 'Brown',
        'mat' => 'Teak Wood',
        'style' => 'Traditional',
        'tags' => 'rehal, quran stand, book stand, foldable rehal, Islamic carving',
        'price' => 95.00,
        'stock' => 15,
        'img' => 'rehal.png'
    ],
    [
        'shop' => 'Sanggar Arca',
        'title' => 'Rustic Driftwood Accent Stool',
        'desc' => 'A unique decorative accent stool crafted from naturally weathered ocean driftwood, featuring a polished top.',
        'cat' => 'Woodcraft',
        'subcat' => 'Home Decor',
        'tech' => 'Natural Form',
        'color' => 'Brown',
        'mat' => 'Driftwood',
        'style' => 'Rustic',
        'tags' => 'driftwood stool, accent stool, rustic decor, organic furniture',
        'price' => 320.00,
        'stock' => 3,
        'img' => 'woodcraft_driftwood_stool.png'
    ],
    [
        'shop' => 'Sanggar Arca',
        'title' => 'Natural Driftwood Base Coffee Table',
        'desc' => 'A striking coffee table with a circular glass top supported by an artistic base of intertwined driftwood.',
        'cat' => 'Woodcraft',
        'subcat' => 'Furniture',
        'tech' => 'Natural Form',
        'color' => 'Brown',
        'mat' => 'Driftwood & Glass',
        'style' => 'Modern',
        'tags' => 'driftwood table, coffee table, glass top, organic art, home decor',
        'price' => 1650.00,
        'stock' => 2,
        'img' => 'woodcraft_driftwood_coffee_table.png'
    ],
    [
        'shop' => 'Sanggar Arca',
        'title' => 'Velvet-Lined Carved Jewelry Box',
        'desc' => 'A beautiful wooden keepsake and jewelry box featuring detailed floral carvings and a soft red velvet lining.',
        'cat' => 'Woodcraft',
        'subcat' => 'Home Decor',
        'tech' => 'Carving',
        'color' => 'Brown',
        'mat' => 'Mahogany Wood',
        'style' => 'Traditional',
        'tags' => 'jewelry box, keepsake box, carved wood, velvet lining, gift idea',
        'price' => 120.00,
        'stock' => 10,
        'img' => 'woodcraft_jewelry_box.jpg'
    ],
    [
        'shop' => 'Sanggar Arca',
        'title' => 'Hand-Carved Wooden Serving Tray',
        'desc' => 'A premium teak wood serving tray with integrated side handles, featuring a carved border.',
        'cat' => 'Woodcraft',
        'subcat' => 'Kitchenware',
        'tech' => 'Carving',
        'color' => 'Brown',
        'mat' => 'Teak Wood',
        'style' => 'Traditional',
        'tags' => 'serving tray, wood tray, kitchenware, teak tray, home hosting',
        'price' => 110.00,
        'stock' => 12,
        'img' => 'wood tray.png'
    ],
    [
        'shop' => 'Sanggar Arca',
        'title' => 'Wall-Mounted Wooden Spice Jar Rack',
        'desc' => 'A 2-tier wall-mounted spice organizer crafted from solid pine wood. Perfect for keeping kitchens neat.',
        'cat' => 'Woodcraft',
        'subcat' => 'Kitchenware',
        'tech' => 'Natural Form',
        'color' => 'Brown',
        'mat' => 'Pine Wood',
        'style' => 'Modern',
        'tags' => 'spice rack, jar holder, kitchenware, wall mount, pine wood',
        'price' => 85.00,
        'stock' => 15,
        'img' => 'woodcraft_spice_holder.png'
    ],

    // --- SELLER 3: Batik Anggun ---
    [
        'shop' => 'Batik Anggun',
        'title' => 'Royal Blue Men\'s Short-Sleeve Batik Shirt',
        'desc' => 'A classic short-sleeve men\'s batik shirt in royal blue, decorated with detailed white floral block patterns.',
        'cat' => 'Batik Textile',
        'subcat' => 'Men\'s Wear',
        'tech' => 'Block Print (Cap)',
        'color' => 'Blue',
        'mat' => 'Premium Cotton',
        'style' => 'Modern',
        'tags' => 'men\'s shirt, blue batik, block print, formal shirt',
        'price' => 125.00,
        'stock' => 15,
        'img' => 'batik_shirt_blue_men.png'
    ],
    [
        'shop' => 'Batik Anggun',
        'title' => 'Crimson Red Hand-Drawn Men\'s Batik Shirt',
        'desc' => 'An elegant crimson red/magenta batik shirt featuring premium hand-drawn floral designs. Crafted from high-quality silk cotton.',
        'cat' => 'Batik Textile',
        'subcat' => 'Men\'s Wear',
        'tech' => 'Hand-drawn (Canting)',
        'color' => 'Red',
        'mat' => 'Silk Cotton',
        'style' => 'Traditional',
        'tags' => 'red shirt, silk cotton, hand-drawn batik, premium shirt',
        'price' => 185.00,
        'stock' => 8,
        'img' => 'batik_shirt_red_men.png'
    ],
    [
        'shop' => 'Batik Anggun',
        'title' => 'Golden Yellow Floral Batik Caftan',
        'desc' => 'Flowy and comfortable mustard yellow caftan dress featuring elegant hand-screened white floral patterns.',
        'cat' => 'Batik Textile',
        'subcat' => 'Women\'s Wear',
        'tech' => 'Screen Print',
        'color' => 'Yellow',
        'mat' => 'Rayon',
        'style' => 'Casual',
        'tags' => 'caftan dress, yellow caftan, women\'s dress, floral batik',
        'price' => 89.00,
        'stock' => 20,
        'img' => 'batik_dress_yellow_floral.png'
    ],
    [
        'shop' => 'Batik Anggun',
        'title' => 'Elegant Purple-Pink Silk Batik Dress',
        'desc' => 'A stunning long-sleeve maxi dress in a flowing purple-pink silk batik pattern. Perfect for formal events.',
        'cat' => 'Batik Textile',
        'subcat' => 'Women\'s Wear',
        'tech' => 'Hand-drawn (Canting)',
        'color' => 'Purple',
        'mat' => 'Premium Silk',
        'style' => 'Modern',
        'tags' => 'batik dress, silk dress, purple pink, formal wear, maxi dress',
        'price' => 210.00,
        'stock' => 6,
        'img' => 'batik_dress_purple_pink.png'
    ],
    [
        'shop' => 'Batik Anggun',
        'title' => 'Black Floral Silk Batik Skirt',
        'desc' => 'A high-waisted A-line midi skirt with gold-embellished floral block prints on deep black silk.',
        'cat' => 'Batik Textile',
        'subcat' => 'Women\'s Wear',
        'tech' => 'Block Print (Cap)',
        'color' => 'Black',
        'mat' => 'Premium Silk',
        'style' => 'Modern',
        'tags' => 'batik skirt, silk skirt, black floral, A-line skirt, midi skirt',
        'price' => 95.00,
        'stock' => 12,
        'img' => 'batik_skirt_black_floral.png'
    ],
    [
        'shop' => 'Batik Anggun',
        'title' => 'Black-Orange Patterned Men\'s Batik Shirt',
        'desc' => 'A bold short-sleeve men\'s batik shirt featuring striking orange geometric motifs on a black cotton base.',
        'cat' => 'Batik Textile',
        'subcat' => 'Men\'s Wear',
        'tech' => 'Screen Print',
        'color' => 'Black',
        'mat' => 'Premium Cotton',
        'style' => 'Modern',
        'tags' => 'men\'s shirt, black orange, geometric batik, cotton shirt',
        'price' => 115.00,
        'stock' => 15,
        'img' => 'batik_shirt_black_orange_pattern.png'
    ],
    [
        'shop' => 'Batik Anggun',
        'title' => 'Modern Split-Pattern Men\'s Batik Shirt',
        'desc' => 'An eye-catching men\'s shirt featuring a split design with plain black on one side and orange batik on the other.',
        'cat' => 'Batik Textile',
        'subcat' => 'Men\'s Wear',
        'tech' => 'Screen Print',
        'color' => 'Black',
        'mat' => 'Premium Cotton',
        'style' => 'Modern',
        'tags' => 'split shirt, men\'s wear, orange black, modern batik, casual shirt',
        'price' => 115.00,
        'stock' => 14,
        'img' => 'batik_shirt_black_orange_split.png'
    ],
    [
        'shop' => 'Batik Anggun',
        'title' => 'Red-Blue Traditional Men\'s Batik Shirt',
        'desc' => 'A classic long-sleeve men\'s shirt decorated with traditional floral motifs in red and indigo blue block prints.',
        'cat' => 'Batik Textile',
        'subcat' => 'Men\'s Wear',
        'tech' => 'Block Print (Cap)',
        'color' => 'Red',
        'mat' => 'Premium Cotton',
        'style' => 'Traditional',
        'tags' => 'long-sleeve, men\'s shirt, red blue, traditional batik, formal wear',
        'price' => 120.00,
        'stock' => 10,
        'img' => 'batik_shirt_red_blue_pattern.png'
    ],

    // --- SELLER 4: Tenun Kencana ---
    [
        'shop' => 'Tenun Kencana',
        'title' => 'Royal Purple Floral Batik Sarong',
        'desc' => 'High-quality unstitched batik sarong fabric in purple and red hues, featuring a classic border and floral patterns.',
        'cat' => 'Batik Textile',
        'subcat' => 'Women\'s Wear',
        'tech' => 'Block Print (Cap)',
        'color' => 'Purple',
        'mat' => 'Cotton',
        'style' => 'Traditional',
        'tags' => 'sarong, purple fabric, floral sarong, traditional batik',
        'price' => 68.00,
        'stock' => 25,
        'img' => 'batik_sarong_purple.png'
    ],
    [
        'shop' => 'Tenun Kencana',
        'title' => 'Teal Hand-Painted Batik Folding Fan',
        'desc' => 'A beautiful folding fan made with hand-painted teal batik fabric and a natural bamboo frame. Light and compact.',
        'cat' => 'Batik Textile',
        'subcat' => 'Accessories',
        'tech' => 'Hand-drawn (Canting)',
        'color' => 'Blue (Teal)',
        'mat' => 'Bamboo & Cotton',
        'style' => 'Traditional',
        'tags' => 'folding fan, hand fan, teal batik, accessory',
        'price' => 28.00,
        'stock' => 30,
        'img' => 'batik_hand_fan_teal.png'
    ],
    [
        'shop' => 'Tenun Kencana',
        'title' => 'Premium Purple Silk Batik Scrunchie',
        'desc' => 'A soft, hair-friendly scrunchie crafted from purple batik silk remnants. Gentle on hair and looks great on the wrist.',
        'cat' => 'Batik Textile',
        'subcat' => 'Accessories',
        'tech' => 'Hand-drawn (Canting)',
        'color' => 'Purple',
        'mat' => 'Silk',
        'style' => 'Modern',
        'tags' => 'scrunchie, hair tie, purple silk, hair accessory',
        'price' => 12.00,
        'stock' => 50,
        'img' => 'batik_scrunchie_purple.png'
    ],
    [
        'shop' => 'Tenun Kencana',
        'title' => 'Draped Purple Silk Batik Fabric (4m)',
        'desc' => 'A 4-meter length of unstitched premium silk batik fabric with a rich purple floral design. Ideal for baju kurung or gowns.',
        'cat' => 'Batik Textile',
        'subcat' => 'Batik Textile',
        'tech' => 'Hand-drawn (Canting)',
        'color' => 'Purple',
        'mat' => 'Premium Silk',
        'style' => 'Traditional',
        'tags' => 'silk fabric, purple silk, unstitched fabric, dressmaking, luxury textile',
        'price' => 280.00,
        'stock' => 5,
        'img' => 'batik_fabric_purple_draped.png'
    ],
    [
        'shop' => 'Tenun Kencana',
        'title' => 'Leaf-Print Lightweight Cotton Scarf',
        'desc' => 'A soft, lightweight cotton-silk scarf featuring organic leaf prints hand-drawn using natural dyes.',
        'cat' => 'Batik Textile',
        'subcat' => 'Accessories',
        'tech' => 'Hand-drawn (Canting)',
        'color' => 'Green',
        'mat' => 'Cotton-Silk Blend',
        'style' => 'Modern',
        'tags' => 'batik scarf, leaf print, lightweight scarf, natural dye, green scarf',
        'price' => 45.00,
        'stock' => 20,
        'img' => 'batik_scarf_leaf_print.png'
    ],
    [
        'shop' => 'Tenun Kencana',
        'title' => 'Multicolor Silk Batik Scrunchies (Set of 2)',
        'desc' => 'A colorful set of two premium silk scrunchies made from batik fabric offcuts. Gentle on hair.',
        'cat' => 'Batik Textile',
        'subcat' => 'Accessories',
        'tech' => 'Hand-drawn (Canting)',
        'color' => 'Multi',
        'mat' => 'Premium Silk',
        'style' => 'Modern',
        'tags' => 'silk scrunchie, hair tie, multicolor, eco-friendly, hair accessory',
        'price' => 15.00,
        'stock' => 40,
        'img' => 'batik_scrunchie_multicolor.png'
    ],
    [
        'shop' => 'Tenun Kencana',
        'title' => 'Yellow Hardcover Batik Journal',
        'desc' => 'An A5 notebook featuring 120 pages of lined paper, bound in a bright mustard-yellow cotton batik cover.',
        'cat' => 'Batik Textile',
        'subcat' => 'Accessories',
        'tech' => 'Screen Print',
        'color' => 'Yellow',
        'mat' => 'Paper & Cotton',
        'style' => 'Modern',
        'tags' => 'batik notebook, hardcover journal, yellow stationery, gift idea',
        'price' => 35.00,
        'stock' => 25,
        'img' => 'batik_notebook_yellow.png'
    ],
    [
        'shop' => 'Tenun Kencana',
        'title' => 'Slim Zippered Batik Pencil Case',
        'desc' => 'A compact zippered pencil and stationery pouch made from durable cotton batik fabric with inner lining.',
        'cat' => 'Batik Textile',
        'subcat' => 'Accessories',
        'tech' => 'Screen Print',
        'color' => 'Blue',
        'mat' => 'Cotton with Lining',
        'style' => 'Casual',
        'tags' => 'pencil case, stationery pouch, zippered bag, blue batik',
        'price' => 22.00,
        'stock' => 30,
        'img' => 'batik_pencil_case.png'
    ],
    [
        'shop' => 'Tenun Kencana',
        'title' => 'Maroon Floral Zippered Travel Pouch',
        'desc' => 'A medium-sized zippered travel pouch in deep maroon, featuring hand-printed floral motifs. Great for cosmetics.',
        'cat' => 'Batik Textile',
        'subcat' => 'Accessories',
        'tech' => 'Block Print (Cap)',
        'color' => 'Red (Maroon)',
        'mat' => 'Cotton with Lining',
        'style' => 'Casual',
        'tags' => 'travel pouch, makeup bag, maroon pouch, floral print, zippered bag',
        'price' => 25.00,
        'stock' => 25,
        'img' => 'batik_pouch_floral_maroon.png'
    ],

    // --- SELLER 5: Nusantara Bags ---
    [
        'shop' => 'Nusantara Bags',
        'title' => 'Turquoise Tiger & Hibiscus Batik Tote',
        'desc' => 'A spacious, fully lined shoulder tote bag in bright turquoise cotton, featuring hand-printed tiger and hibiscus motifs.',
        'cat' => 'Batik Textile',
        'subcat' => 'Accessories',
        'tech' => 'Screen Print',
        'color' => 'Blue (Turquoise)',
        'mat' => 'Canvas Cotton',
        'style' => 'Modern',
        'tags' => 'tote bag, tiger print, turquoise bag, shoulder bag',
        'price' => 58.00,
        'stock' => 12,
        'img' => 'batik_tote_bag_tiger.png'
    ],
    [
        'shop' => 'Nusantara Bags',
        'title' => 'Scarlet Red Geometric Batik Pouch',
        'desc' => 'A compact zippered utility pouch in scarlet red with traditional geometric prints. Ideal for makeup, stationery, or coins.',
        'cat' => 'Batik Textile',
        'subcat' => 'Accessories',
        'tech' => 'Screen Print',
        'color' => 'Red',
        'mat' => 'Cotton with Lining',
        'style' => 'Casual',
        'tags' => 'zippered pouch, red pouch, makeup bag, coin purse',
        'price' => 18.00,
        'stock' => 30,
        'img' => 'batik_pouch_red.png'
    ],
    [
        'shop' => 'Nusantara Bags',
        'title' => 'Golden Spiral Black & Yellow Batik Pouch',
        'desc' => 'A stylish medium-sized zippered pouch featuring a contrasting golden-yellow spiral pattern over a deep black background.',
        'cat' => 'Batik Textile',
        'subcat' => 'Accessories',
        'tech' => 'Block Print (Cap)',
        'color' => 'Black',
        'mat' => 'Cotton',
        'style' => 'Modern',
        'tags' => 'black and yellow, batik pouch, travel pouch',
        'price' => 22.00,
        'stock' => 20,
        'img' => 'batik_pouch_black_yellow.png'
    ],
    [
        'shop' => 'Nusantara Bags',
        'title' => 'Quilted Blue Batik Handbag',
        'desc' => 'A premium quilted handbag featuring elegant floral batik designs on blue cotton, with comfortable leather straps.',
        'cat' => 'Batik Textile',
        'subcat' => 'Accessories',
        'tech' => 'Screen Print',
        'color' => 'Blue',
        'mat' => 'Cotton & Leather',
        'style' => 'Modern',
        'tags' => 'quilted handbag, blue bag, shoulder purse, leather straps, luxury bag',
        'price' => 135.00,
        'stock' => 8,
        'img' => 'batik_handbag_blue_quilted.png'
    ],
    [
        'shop' => 'Nusantara Bags',
        'title' => 'Vibrant Multicolor Batik Shoulder Bag',
        'desc' => 'A large, slouchy hobo-style shoulder bag made with colorful patchwork batik fabric. Very spacious.',
        'cat' => 'Batik Textile',
        'subcat' => 'Accessories',
        'tech' => 'Screen Print',
        'color' => 'Multi',
        'mat' => 'Cotton',
        'style' => 'Casual',
        'tags' => 'shoulder bag, hobo bag, multicolor bag, patchwork batik, casual bag',
        'price' => 65.00,
        'stock' => 15,
        'img' => 'batik_shoulder_bag_colorful.png'
    ],
    [
        'shop' => 'Nusantara Bags',
        'title' => 'Geometric Pattern Batik Shoulder Bag',
        'desc' => 'A structured shoulder bag featuring dark geometric batik prints. Includes an adjustable strap and inner pockets.',
        'cat' => 'Batik Textile',
        'subcat' => 'Accessories',
        'tech' => 'Block Print (Cap)',
        'color' => 'Black',
        'mat' => 'Cotton Canvas',
        'style' => 'Modern',
        'tags' => 'shoulder bag, geometric pattern, canvas bag, adjustable strap, black bag',
        'price' => 75.00,
        'stock' => 12,
        'img' => 'batik_shoulder_bag_geometric.png'
    ],
    [
        'shop' => 'Nusantara Bags',
        'title' => 'Blue-Green Batik Crossbody Sling Bag',
        'desc' => 'A compact crossbody sling bag in blue and green hues. Features secure zip closures for daily essentials.',
        'cat' => 'Batik Textile',
        'subcat' => 'Accessories',
        'tech' => 'Screen Print',
        'color' => 'Green',
        'mat' => 'Cotton',
        'style' => 'Casual',
        'tags' => 'sling bag, crossbody bag, blue green, compact bag, daily bag',
        'price' => 48.00,
        'stock' => 18,
        'img' => 'batik_sling_bag_blue_green.png'
    ],
    [
        'shop' => 'Nusantara Bags',
        'title' => 'Classic Maroon Cotton Batik Tote Bag',
        'desc' => 'A sturdy, everyday tote bag in maroon cotton featuring traditional floral prints. Large main compartment.',
        'cat' => 'Batik Textile',
        'subcat' => 'Accessories',
        'tech' => 'Block Print (Cap)',
        'color' => 'Red (Maroon)',
        'mat' => 'Cotton Canvas',
        'style' => 'Modern',
        'tags' => 'tote bag, maroon tote, canvas bag, shopping bag, floral print',
        'price' => 40.00,
        'stock' => 20,
        'img' => 'batik_tote_bag_brown_maroon.png'
    ],
    [
        'shop' => 'Nusantara Bags',
        'title' => 'Traditional Handcrafted Wooden Congkak Board',
        'desc' => 'A full-sized Congkak game board carved from solid teak wood. Includes a complete set of glass marbles.',
        'cat' => 'Woodcraft',
        'subcat' => 'Souvenirs',
        'tech' => 'Carving',
        'color' => 'Brown',
        'mat' => 'Teak Wood',
        'style' => 'Traditional',
        'tags' => 'congkak board, traditional game, wooden congkak, teak wood, souvenir',
        'price' => 145.00,
        'stock' => 5,
        'img' => 'congkak.png'
    ],
    [
        'shop' => 'Nusantara Bags',
        'title' => 'Teak Wood Desk Stationery Organizer',
        'desc' => 'A multi-compartment desktop organizer carved from solid teak, perfect for pens, cards, and letters.',
        'cat' => 'Woodcraft',
        'subcat' => 'Home Decor',
        'tech' => 'Natural Form',
        'color' => 'Brown',
        'mat' => 'Teak Wood',
        'style' => 'Modern',
        'tags' => 'desk organizer, pen holder, stationery stand, teak wood, office decor',
        'price' => 45.00,
        'stock' => 10,
        'img' => 'stationery holder.jpg'
    ],
    [
        'shop' => 'Nusantara Bags',
        'title' => 'Minimalist Wooden Tissue Box Cover',
        'desc' => 'A sleek, sliding-bottom wooden tissue box cover crafted from polished teak wood. Fits standard tissue boxes.',
        'cat' => 'Woodcraft',
        'subcat' => 'Home Decor',
        'tech' => 'Natural Form',
        'color' => 'Brown',
        'mat' => 'Teak Wood',
        'style' => 'Modern',
        'tags' => 'tissue cover, tissue box, home decor, teak wood, minimalist',
        'price' => 35.00,
        'stock' => 15,
        'img' => 'tissue holder.jpg'
    ],
    [
        'shop' => 'Nusantara Bags',
        'title' => 'Wooden Desktop Business Card Stand',
        'desc' => 'A compact, slanted desktop business card holder carved from a single piece of teak wood.',
        'cat' => 'Woodcraft',
        'subcat' => 'Home Decor',
        'tech' => 'Natural Form',
        'color' => 'Brown',
        'mat' => 'Teak Wood',
        'style' => 'Modern',
        'tags' => 'card stand, card holder, business card, desk accessory, teak wood',
        'price' => 20.00,
        'stock' => 25,
        'img' => 'business card holder.png'
    ]
];

// 3. Insert all products
foreach ($products as $p) {
    if (!isset($seller_ids[$p['shop']])) {
        echo "Error: Seller ID not found for '{$p['shop']}'. Skipping product.<br>";
        continue;
    }
    $seller_id = $seller_ids[$p['shop']];
    
    // Check if product already exists to prevent duplicate seeding
    $check_prod = $conn->prepare("SELECT id FROM products WHERE title = ? AND seller_id = ?");
    $check_prod->bind_param("si", $p['title'], $seller_id);
    $check_prod->execute();
    $prod_res = $check_prod->get_result();
    
    if ($prod_res->num_rows > 0) {
        echo "Product '{$p['title']}' already exists under '{$p['shop']}'.<br>";
    } else {
        $ins_prod = $conn->prepare("INSERT INTO products (seller_id, title, description, category, subcategory, technique, color, material, style, tags, price, stock, image_path) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $ins_prod->bind_param("isssssssssdis", 
            $seller_id, 
            $p['title'], 
            $p['desc'], 
            $p['cat'], 
            $p['subcat'], 
            $p['tech'], 
            $p['color'], 
            $p['mat'], 
            $p['style'], 
            $p['tags'], 
            $p['price'], 
            $p['stock'], 
            $p['img']
        );
        $ins_prod->execute();
        $ins_prod->close();
        echo "Successfully seeded product '{$p['title']}' for '{$p['shop']}'.<br>";
    }
    $check_prod->close();
}

echo "<h3>Database Seeding Completed Successfully!</h3>";
?>
