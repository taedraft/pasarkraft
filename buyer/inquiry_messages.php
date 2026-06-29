<?php
session_start();
header("Cache-Control: no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");
require '../db_connect.php';

$unread_count = 0;
if (isset($_SESSION['user_id']) && isset($_SESSION['role']) && $_SESSION['role'] === 'buyer') {
    $unread_stmt = $conn->prepare("SELECT COUNT(DISTINCT sender_id) as unread_count FROM messages WHERE receiver_id = ? AND is_read = 0");
    $unread_stmt->bind_param("i", $_SESSION['user_id']);
    $unread_stmt->execute();
    $unread_res = $unread_stmt->get_result();
    if ($unread_row = $unread_res->fetch_assoc()) {
        $unread_count = $unread_row['unread_count'];
    }
    $unread_stmt->close();
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'buyer') {
    $_SESSION['login_error'] = "Log in to access your chat history.";
    header("Location: login_buyer.php");
    exit();
}

$buyer_id = $_SESSION['user_id'];
$active_inquiry_id = isset($_GET['inquiry_id']) ? intval($_GET['inquiry_id']) : 0;

if (isset($_GET['product_id']) && isset($_GET['seller_id'])) {
    $p_id = intval($_GET['product_id']);
    $s_id = intval($_GET['seller_id']);
    
    // Log click interaction (value = 2.0) if not already logged
    $click_chk = $conn->prepare("SELECT id FROM user_interactions WHERE user_id = ? AND product_id = ? AND interaction_type = 'click'");
    $click_chk->bind_param("ii", $buyer_id, $p_id);
    $click_chk->execute();
    if ($click_chk->get_result()->num_rows == 0) {
        $click_ins = $conn->prepare("INSERT INTO user_interactions (user_id, product_id, interaction_type, interaction_value) VALUES (?, ?, 'click', 2.0)");
        $click_ins->bind_param("ii", $buyer_id, $p_id);
        $click_ins->execute();
        $click_ins->close();
    }
    $click_chk->close();
    
    $chk_stmt = $conn->prepare("SELECT id FROM inquiries WHERE buyer_id = ? AND product_id = ?");
    $chk_stmt->bind_param("ii", $buyer_id, $p_id);
    $chk_stmt->execute();
    $chk_res = $chk_stmt->get_result();
    
    if ($chk_row = $chk_res->fetch_assoc()) {
        $active_inquiry_id = $chk_row['id'];
        header("Location: inquiry_messages.php?inquiry_id=" . $active_inquiry_id);
        exit();
    } else {
        $ins = $conn->prepare("INSERT INTO inquiries (buyer_id, seller_id, product_id, status) VALUES (?, ?, ?, 'In Discussion')");
        $ins->bind_param("iii", $buyer_id, $s_id, $p_id);
        $ins->execute();
        $active_inquiry_id = $conn->insert_id;
        
        header("Location: inquiry_messages.php?inquiry_id=" . $active_inquiry_id);
        exit();
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $active_inquiry_id > 0) {
    if (isset($_POST['message'])) {
        $msg = trim($_POST['message']);
        $offer_amount = isset($_POST['offer_amount']) ? floatval($_POST['offer_amount']) : 0;
        $is_offer = ($offer_amount > 0) ? 1 : 0;

        if (!empty($msg) || $is_offer) {
            // Find seller_id
            $s_stmt = $conn->prepare("SELECT seller_id, status FROM inquiries WHERE id = ? AND buyer_id = ?");
            $s_stmt->bind_param("ii", $active_inquiry_id, $buyer_id);
            $s_stmt->execute();
            $s_res = $s_stmt->get_result();
            if ($s_row = $s_res->fetch_assoc()) {
                if ($s_row['status'] == 'Sold' || $s_row['status'] == 'No Deal') {
                    // Closed
                } else {
                    $seller_id = $s_row['seller_id'];
                    $msg_text = $msg ?: "Sent an offer.";
                    $i_stmt = $conn->prepare("INSERT INTO messages (inquiry_id, sender_id, receiver_id, message, is_offer, offer_amount) VALUES (?, ?, ?, ?, ?, ?)");
                    $i_stmt->bind_param("iiisid", $active_inquiry_id, $buyer_id, $seller_id, $msg_text, $is_offer, $offer_amount);
                    $i_stmt->execute();
                    
                    if ($is_offer) {
                        $u_stmt = $conn->prepare("UPDATE inquiries SET current_offer = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                        $u_stmt->bind_param("di", $offer_amount, $active_inquiry_id);
                        $u_stmt->execute();
                    } else {
                        $u_stmt = $conn->prepare("UPDATE inquiries SET updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                        $u_stmt->bind_param("i", $active_inquiry_id);
                        $u_stmt->execute();
                    }
                }
            }
        }
    }
    header("Location: inquiry_messages.php?inquiry_id=$active_inquiry_id");
    exit();
}

// Fetch inquiries
$inquiries = [];
$stmt = $conn->prepare("SELECT i.*, a.shopname as seller_name, p.title as product_title, p.price, p.image_path FROM inquiries i JOIN artisans a ON i.seller_id = a.user_id JOIN products p ON i.product_id = p.id WHERE i.buyer_id = ? ORDER BY i.updated_at DESC");
$stmt->bind_param("i", $buyer_id);
$stmt->execute();
$inq_res = $stmt->get_result();
while ($row = $inq_res->fetch_assoc()) {
    $inquiries[] = $row;
}
$stmt->close();

$messages = [];
$active_inquiry = null;

if ($active_inquiry_id > 0) {
    foreach ($inquiries as $i) {
        if ($i['id'] == $active_inquiry_id) {
            $active_inquiry = $i;
            break;
        }
    }
    if ($active_inquiry) {
        $upd_read = $conn->prepare("UPDATE messages SET is_read = 1 WHERE inquiry_id = ? AND receiver_id = ? AND is_read = 0");
        $upd_read->bind_param("ii", $active_inquiry_id, $_SESSION['user_id']);
        $upd_read->execute();
        
        $msg_sql = "SELECT * FROM messages WHERE inquiry_id = ? ORDER BY created_at ASC";
        $stmt = $conn->prepare($msg_sql);
        $stmt->bind_param("i", $active_inquiry_id);
        $stmt->execute();
        $msg_res = $stmt->get_result();
        while ($row = $msg_res->fetch_assoc()) {
            $messages[] = $row;
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="ms">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inquiry Messages | Pasarkraft</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Playfair+Display:ital,wght@0,400;0,700;1,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../styles.css">
    <style>
        body { background: #fdfaf6; }
        .chat-container { max-width: 1200px; margin: 100px auto 3rem; display: flex; height: 75vh; background: #fff; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); overflow: hidden; border: 1px solid #eee; }
        .chat-sidebar { width: 350px; border-right: 1px solid #eee; background: #fdfdfd; display: flex; flex-direction: column; }
        .chat-sidebar-header { padding: 20px; border-bottom: 1px solid #eee; background: #fff; }
        .chat-sidebar-header h3 { color: var(--primary-color); margin: 0; font-size: 1.2rem; }
        .chat-contacts { overflow-y: auto; flex: 1; }
        .contact-item { padding: 15px 20px; border-bottom: 1px solid #f5f5f5; cursor: pointer; transition: background 0.2s; display: flex; align-items: center; gap: 12px; text-decoration: none; color: inherit; }
        .contact-item:hover, .contact-item.active { background: #f0f7ff; }
        .contact-avatar { width: 40px; height: 40px; border-radius: 50%; background: var(--accent-color); color: white; display: flex; align-items: center; justify-content: center; font-weight: bold; flex-shrink: 0;}
        .contact-info h4 { margin: 0 0 4px 0; font-size: 0.95rem; color: #2c3e50; }
        .contact-info p { margin: 0; font-size: 0.8rem; color: #7f8c8d; text-overflow: ellipsis; overflow: hidden; white-space: nowrap; max-width: 200px;}
        .status-badge { display: inline-block; padding: 2px 6px; border-radius: 10px; font-size: 0.65rem; font-weight: 600; margin-left: 5px; }
        .status-In-Discussion { background: #fff3cd; color: #856404; }
        .status-Deal-Agreed { background: #d1ecf1; color: #0c5460; }
        .status-Sold { background: #d4edda; color: #155724; }
        .status-No-Deal { background: #f8d7da; color: #721c24; }

        .chat-main { flex: 1; display: flex; flex-direction: column; background: #fafafa; }
        .chat-header { padding: 15px 20px; background: #fff; border-bottom: 1px solid #eee; display: flex; align-items: center; gap: 15px; justify-content: space-between;}
        .chat-header .header-left { display: flex; align-items: center; gap: 15px; }
        .chat-header h3 { margin: 0; color: #2c3e50; font-size: 1.1rem; }
        .chat-header p { margin: 2px 0 0 0; font-size: 0.85rem; color: #7f8c8d; }
        
        .chat-messages { flex: 1; padding: 20px; overflow-y: auto; display: flex; flex-direction: column; gap: 15px; }
        .message { max-width: 70%; padding: 12px 16px; border-radius: 16px; position: relative; font-size: 0.95rem; line-height: 1.4; }
        
        .message.sent { background: var(--primary-color); color: white; align-self: flex-end; border-bottom-right-radius: 4px; }
        .message.received { background: #e2e8f0; color: #2d3748; align-self: flex-start; border-bottom-left-radius: 4px; }
        .msg-time { font-size: 0.7rem; opacity: 0.7; display: block; margin-top: 5px; text-align: right; }
        
        .chat-input-area { padding: 20px; background: #fff; border-top: 1px solid #eee; }
        .chat-form { display: flex; gap: 10px; flex-wrap: wrap; align-items: center;}
        .chat-input { flex: 1; padding: 12px 15px; border: 1px solid #ddd; border-radius: 20px; outline: none; transition: border 0.3s; min-width: 200px;}
        .chat-input:focus { border-color: var(--primary-color); }
        .offer-input { width: 100px; padding: 12px; border: 1px solid #ddd; border-radius: 20px; outline: none; transition: border 0.3s; }
        .offer-input:focus { border-color: var(--accent-color); }
        
        .btn-send { background: var(--accent-color); color: white; border: none; width: 45px; height: 45px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: transform 0.2s; flex-shrink: 0;}
        .btn-send:hover { transform: scale(1.05); }
        .empty-chat { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; color: #95a5a6; }
        .empty-chat i { font-size: 3rem; margin-bottom: 15px; opacity: 0.5; }
        /* Offer bubble (#6) */
        .message.offer-bubble { background: linear-gradient(135deg,#e67e22,#f39c12) !important; color: #fff; border: none !important; }
        .message.offer-bubble.received { background: linear-gradient(135deg,#16a085,#27ae60) !important; }
        .offer-amount-display { font-size: 1.05rem; font-weight: 700; display: block; margin-bottom: 4px; }
        /* System message pill (#7) */
        .message-system { align-self: center !important; max-width: 85%; background: #f0f4f8; color: #718096; padding: 5px 16px; border-radius: 20px; font-size: 0.78rem; text-align: center; font-style: italic; }
        /* Read receipt (#8) */
        .msg-read-inline { font-size: 0.65rem; opacity: 0.85; color: rgba(255,255,255,0.85); }
        /* Product thumbnail (#9) */
        .product-thumb { width: 18px; height: 18px; object-fit: cover; border-radius: 2px; vertical-align: middle; margin-right: 3px; }
    </style>
</head>
<body>
    <header>
        <nav>
            <a href="../homepage.php" class="logo">Pasar<span>kraft</span>.</a>
            <div class="nav-links">
                <a href="../batik_page.php">Batik</a>
                <a href="../woodcraft_page.php">Woodcraft</a>
                <a href="inquiry_messages.php" class="active-link" style="color: var(--accent-color);">Chat History <span id="pkUnreadBadge" style="background:red;color:white;border-radius:50%;padding:2px 6px;font-size:0.75rem;margin-left:5px;<?php echo ($unread_count > 0) ? '' : 'display:none;'; ?>"><?php echo $unread_count; ?></span></a>
                <a href="../logout.php" class="nav-login" onclick="return confirm('Are you sure you want to log out?');">Logout</a>
                <a href="../wishlist_page.php" class="wishlist-icon">
                    <i class="far fa-heart"></i>
                </a>
                <a href="buyer_profile.php" class="profile-icon">
                    <i class="far fa-user-circle"></i>
                </a>
            </div>
        </nav>
    </header>

    <div class="chat-container">
        <!-- Sidebar Contacts -->
        <div class="chat-sidebar">
            <div class="chat-sidebar-header">
                <h3>My Inquiries</h3>
            </div>
            <div class="chat-contacts">
                <?php if (empty($inquiries)): ?>
                    <p style="padding: 20px; color: #999; text-align: center;">No active inquiries yet.</p>
                <?php else: ?>
                    <?php foreach ($inquiries as $inq): ?>
                        <a href="inquiry_messages.php?inquiry_id=<?php echo $inq['id']; ?>" class="contact-item <?php echo ($active_inquiry_id == $inq['id']) ? 'active' : ''; ?>">
                            <?php $statusClassFormat = str_replace(' ', '-', $inq['status']); ?>
                            <div class="contact-avatar" style="background:#e67e22;"><?php echo strtoupper(substr($inq['seller_name'], 0, 1)); ?></div>
                            <div class="contact-info">
                                <h4><?php echo htmlspecialchars($inq['seller_name']); ?> <span class="status-badge status-<?php echo $statusClassFormat; ?>"><?php echo htmlspecialchars($inq['status']); ?></span></h4>
                                <p><?php if (!empty($inq['image_path'])): ?><img src="../<?php echo htmlspecialchars($inq['image_path']); ?>" class="product-thumb" onerror="this.style.display='none'"><?php endif; ?><?php echo htmlspecialchars($inq['product_title']); ?></p>
                                <?php if($inq['current_offer']): ?>
                                    <p style="color:#27ae60; font-weight:600;">Offer: RM <?php echo number_format($inq['current_offer'], 2); ?></p>
                                <?php endif; ?>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Main Chat Area -->
        <div class="chat-main">
            <?php if ($active_inquiry_id > 0 && $active_inquiry): ?>
                <div class="chat-header">
                    <div class="header-left">
                        <div class="contact-avatar" style="width: 40px; height: 40px; font-size: 0.9rem; background:#e67e22;"><?php echo strtoupper(substr($active_inquiry['seller_name'], 0, 1)); ?></div>
                        <div>
                            <h3><?php echo htmlspecialchars($active_inquiry['seller_name']); ?></h3>
                            <p>Interest: <strong><?php echo htmlspecialchars($active_inquiry['product_title']); ?></strong></p>
                        </div>
                    </div>
                </div>
                
                <div class="chat-messages" id="chatMessages">
                    <?php if (empty($messages)): ?>
                        <div data-placeholder="1" style="text-align:center; color:#999; margin:auto;">Send a message to start negotiating!</div>
                    <?php else: ?>
                        <?php foreach($messages as $msg): ?>
                            <?php $is_mine = ($msg['sender_id'] == $buyer_id); ?>
                            <?php $seen = ($is_mine && !empty($msg['is_read'])) ? ' &middot; <span class="msg-read-inline">&#10003; Seen</span>' : ''; ?>
                            <?php if (!empty($msg['is_system'])): ?>
                                <div class="message-system"><?php echo htmlspecialchars($msg['message']); ?></div>
                            <?php elseif ($msg['is_offer']): ?>
                                <div class="message <?php echo $is_mine ? 'sent' : 'received'; ?> offer-bubble">
                                    <span class="offer-amount-display"><i class="fas fa-tag"></i> RM <?php echo number_format($msg['offer_amount'], 2); ?></span>
                                    <?php $om = $msg['message']; if ($om && $om !== 'Sent an offer.' && $om !== 'Counter-offer sent.'): ?><span style="opacity:0.9;font-size:0.9rem;display:block;"><?php echo htmlspecialchars($om); ?></span><?php endif; ?>
                                    <span class="msg-time"><?php echo date('H:i', strtotime($msg['created_at'])); ?><?php echo $seen; ?></span>
                                </div>
                            <?php else: ?>
                                <div class="message <?php echo $is_mine ? 'sent' : 'received'; ?>">
                                    <?php echo htmlspecialchars($msg['message']); ?>
                                    <span class="msg-time"><?php echo date('H:i', strtotime($msg['created_at'])); ?><?php echo $seen; ?></span>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <div class="chat-input-area">
                    <form class="chat-form" method="POST" action="inquiry_messages.php?inquiry_id=<?php echo $active_inquiry_id; ?>" onsubmit="return validateChatForm(this)">
                        <input type="text" name="message" class="chat-input" placeholder="Type your message here..." autocomplete="off" <?php echo ($active_inquiry['status'] == 'Sold' || $active_inquiry['status'] == 'No Deal') ? 'disabled' : ''; ?>>
                        <input type="number" step="0.01" min="0.01" name="offer_amount" class="offer-input" placeholder="Offer RM" <?php echo ($active_inquiry['status'] == 'Sold' || $active_inquiry['status'] == 'No Deal') ? 'disabled' : ''; ?>>
                        <button type="submit" class="btn-send" <?php echo ($active_inquiry['status'] == 'Sold' || $active_inquiry['status'] == 'No Deal') ? 'disabled' : ''; ?>><i class="fas fa-paper-plane"></i></button>
                    </form>
                </div>
                <script>
                    // Scroll to bottom on load
                    var cm = document.getElementById('chatMessages');
                    if (cm) cm.scrollTop = cm.scrollHeight;

                    // Require message OR offer before submitting
                    function validateChatForm(f) {
                        var msg   = f.querySelector('[name="message"]').value.trim();
                        var offer = parseFloat(f.querySelector('[name="offer_amount"]').value || 0);
                        if (!msg && !(offer > 0)) {
                            alert('Please type a message or enter an offer amount.');
                            return false;
                        }
                        return true;
                    }

                    <?php if (!($active_inquiry['status'] === 'Sold' || $active_inquiry['status'] === 'No Deal')): ?>
                    // Real-time polling — check for new messages every 3 s
                    var lastMsgId = <?php echo !empty($messages) ? (int)end($messages)['id'] : 0; ?>;
                    var myUserId  = <?php echo (int)$buyer_id; ?>;
                    var inquiryId = <?php echo (int)$active_inquiry_id; ?>;

                    function escHtml(t) {
                        return String(t || '').replace(/[&<>"']/g, function(c) {
                            return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c];
                        });
                    }

                    function buildMsgHtml(msg) {
                        var mine = parseInt(msg.sender_id) === myUserId;
                        var cls  = mine ? 'sent' : 'received';
                        var d    = new Date(msg.created_at.replace(' ', 'T'));
                        var time = d.toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'});
                        var seen = (mine && parseInt(msg.is_read)) ? ' &middot; <span class="msg-read-inline">&#10003; Seen</span>' : '';
                        if (parseInt(msg.is_system)) {
                            return '<div class="message-system">' + escHtml(msg.message) + '</div>';
                        }
                        if (parseInt(msg.is_offer)) {
                            var om = msg.message;
                            var omHtml = (om && om !== 'Sent an offer.' && om !== 'Counter-offer sent.')
                                ? '<span style="opacity:0.9;font-size:0.9rem;display:block;">' + escHtml(om) + '</span>' : '';
                            return '<div class="message ' + cls + ' offer-bubble">' +
                                   '<span class="offer-amount-display"><i class="fas fa-tag"></i> RM ' +
                                   parseFloat(msg.offer_amount).toFixed(2) + '</span>' +
                                   omHtml +
                                   '<span class="msg-time">' + time + seen + '</span></div>';
                        }
                        return '<div class="message ' + cls + '">' + escHtml(msg.message) +
                               '<span class="msg-time">' + time + seen + '</span></div>';
                    }

                    function pollMessages() {
                        fetch('get_messages.php?inquiry_id=' + inquiryId + '&after_id=' + lastMsgId)
                            .then(function(r) { return r.json(); })
                            .then(function(data) {
                                if (data.messages && data.messages.length > 0) {
                                    var chatEl = document.getElementById('chatMessages');
                                    var ph = chatEl.querySelector('[data-placeholder]');
                                    if (ph) ph.remove();
                                    data.messages.forEach(function(msg) {
                                        chatEl.insertAdjacentHTML('beforeend', buildMsgHtml(msg));
                                        lastMsgId = Math.max(lastMsgId, parseInt(msg.id));
                                    });
                                    chatEl.scrollTop = chatEl.scrollHeight;
                                }
                                // Update nav unread badge live
                                var badge = document.getElementById('pkUnreadBadge');
                                if (badge) {
                                    badge.style.display = data.unread_count > 0 ? 'inline' : 'none';
                                    if (data.unread_count > 0) badge.textContent = data.unread_count;
                                }
                            }).catch(function() {});
                    }

                    setInterval(pollMessages, 3000);
                    <?php endif; ?>
                </script>
            <?php else: ?>
                <div class="empty-chat">
                    <i class="far fa-comments"></i>
                    <h2>Select an inquiry</h2>
                    <p>Choose an inquiry from the left to start chatting and negotiating.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
