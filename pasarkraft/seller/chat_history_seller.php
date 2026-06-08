<?php
session_start();
header("Cache-Control: no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");
require '../db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'seller') {
    $_SESSION['login_error'] = "Log in as a seller to view messages.";
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
$active_inquiry_id = isset($_GET['inquiry_id']) ? intval($_GET['inquiry_id']) : 0;

$shopname = "Artisan";
$seller_email = "seller@pasarkraft.com";
$stmt = $conn->prepare("SELECT a.shopname, u.email FROM artisans a JOIN users u ON a.user_id = u.id WHERE u.id = ?");
$stmt->bind_param("i", $seller_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $shopname = htmlspecialchars($row['shopname']);
    $seller_email = htmlspecialchars($row['email']);
}
$stmt->close();

// Handle form submissions for messages and offers
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $active_inquiry_id > 0) {
    if (isset($_POST['message'])) {
        $msg = trim($_POST['message']);
        if (!empty($msg)) {
            // Find buyer_id for this inquiry
            $b_stmt = $conn->prepare("SELECT buyer_id FROM inquiries WHERE id = ? AND seller_id = ?");
            $b_stmt->bind_param("ii", $active_inquiry_id, $seller_id);
            $b_stmt->execute();
            $b_res = $b_stmt->get_result();
            if ($b_row = $b_res->fetch_assoc()) {
                $buyer_id = $b_row['buyer_id'];
                $i_stmt = $conn->prepare("INSERT INTO messages (inquiry_id, sender_id, receiver_id, message) VALUES (?, ?, ?, ?)");
                $i_stmt->bind_param("iiis", $active_inquiry_id, $seller_id, $buyer_id, $msg);
                $i_stmt->execute();
                
                // Update inquiry updated_at
                $u_stmt = $conn->prepare("UPDATE inquiries SET updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                $u_stmt->bind_param("i", $active_inquiry_id);
                $u_stmt->execute();
            }
        }
    }
    if (isset($_POST['update_status'])) {
        $new_status = $_POST['update_status'];
        $u_stmt = $conn->prepare("UPDATE inquiries SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND seller_id = ?");
        $u_stmt->bind_param("sii", $new_status, $active_inquiry_id, $seller_id);
        if ($u_stmt->execute() && ($new_status === 'Deal Agreed' || $new_status === 'Sold')) {
            // Fetch buyer_id and product_id to log purchase
            $inq_info = $conn->prepare("SELECT buyer_id, product_id FROM inquiries WHERE id = ?");
            $inq_info->bind_param("i", $active_inquiry_id);
            if ($inq_info->execute()) {
                $res_info = $inq_info->get_result()->fetch_assoc();
                if ($res_info) {
                    $b_id = intval($res_info['buyer_id']);
                    $p_id = intval($res_info['product_id']);
                    
                    // Log purchase interaction (value = 5.0) if not already logged
                    $pur_chk = $conn->prepare("SELECT id FROM user_interactions WHERE user_id = ? AND product_id = ? AND interaction_type = 'purchase'");
                    $pur_chk->bind_param("ii", $b_id, $p_id);
                    $pur_chk->execute();
                    if ($pur_chk->get_result()->num_rows == 0) {
                        $pur_ins = $conn->prepare("INSERT INTO user_interactions (user_id, product_id, interaction_type, interaction_value) VALUES (?, ?, 'purchase', 5.0)");
                        $pur_ins->bind_param("ii", $b_id, $p_id);
                        $pur_ins->execute();
                        $pur_ins->close();
                    }
                    $pur_chk->close();
                }
            }
            $inq_info->close();
        }
    }
    header("Location: chat_history_seller.php?inquiry_id=$active_inquiry_id");
    exit();
}

// Fetch all inquiries for this seller
$inquiries = [];
$stmt = $conn->prepare("SELECT i.*, u.firstname, u.lastname, u.username, p.title as product_title, p.image_path FROM inquiries i JOIN users u ON i.buyer_id = u.id JOIN products p ON i.product_id = p.id WHERE i.seller_id = ? ORDER BY i.updated_at DESC");
$stmt->bind_param("i", $seller_id);
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
        while ($m = $msg_res->fetch_assoc()) {
            $messages[] = $m;
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
    <title>Customer Inquiries | Pasarkraft Seller</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
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
        .contact-avatar { width: 40px; height: 40px; border-radius: 50%; background: #34495e; color: white; display: flex; align-items: center; justify-content: center; font-weight: bold; flex-shrink: 0;}
        .contact-info h4 { margin: 0 0 4px 0; font-size: 0.95rem; color: #2c3e50; }
        .contact-info p { margin: 0; font-size: 0.8rem; color: #7f8c8d; text-overflow: ellipsis; overflow: hidden; white-space: nowrap; max-width: 200px;}
        .status-badge { display: inline-block; padding: 2px 6px; border-radius: 10px; font-size: 0.65rem; font-weight: 600; margin-left: 5px; }
        .status-In-Discussion { background: #fff3cd; color: #856404; }
        .status-Deal-Agreed { background: #d1ecf1; color: #0c5460; }
        .status-Sold { background: #d4edda; color: #155724; }
        .status-No-Deal { background: #f8d7da; color: #721c24; }

        .chat-main { flex: 1; display: flex; flex-direction: column; background: #fafafa; }
        .chat-header { padding: 15px 20px; background: #fff; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; }
        .chat-header .header-left { display: flex; align-items: center; gap: 15px; }
        .chat-header h3 { margin: 0; color: #2c3e50; font-size: 1.1rem; }
        .chat-header p { margin: 2px 0 0 0; font-size: 0.85rem; color: #7f8c8d; }
        .chat-header .header-actions { display: flex; gap: 10px; align-items: center; }

        .chat-messages { flex: 1; padding: 20px; overflow-y: auto; display: flex; flex-direction: column; gap: 15px; }
        .message { max-width: 70%; padding: 12px 16px; border-radius: 16px; position: relative; font-size: 0.95rem; line-height: 1.4; }
        .message.sent { background: #27ae60; color: white; align-self: flex-end; border-bottom-right-radius: 4px; }
        .message.received { background: #e2e8f0; color: #2d3748; align-self: flex-start; border-bottom-left-radius: 4px; }
        .msg-time { font-size: 0.7rem; opacity: 0.7; display: block; margin-top: 5px; text-align: right; }
        
        .chat-input-area { padding: 20px; background: #fff; border-top: 1px solid #eee; }
        .chat-form { display: flex; gap: 10px; }
        .chat-input { flex: 1; padding: 12px 15px; border: 1px solid #ddd; border-radius: 20px; outline: none; transition: border 0.3s; }
        .chat-input:focus { border-color: #27ae60; }
        .btn-send { background: #27ae60; color: white; border: none; width: 45px; height: 45px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: transform 0.2s; }
        .btn-send:hover { transform: scale(1.05); }
        .empty-chat { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; color: #95a5a6; }
        .empty-chat i { font-size: 3rem; margin-bottom: 15px; opacity: 0.5; }

        .btn-status { padding: 6px 12px; border: 1px solid #ddd; border-radius: 6px; background: #fff; color: #555; cursor: pointer; font-size: 0.8rem; font-weight: 500; }
        .btn-status.agree { color: #0c5460; background: #d1ecf1; border-color: #bee5eb; }
        .btn-status.sold { color: #155724; background: #d4edda; border-color: #c3e6cb; }
        .btn-status.nodeal { color: #721c24; background: #f8d7da; border-color: #f5c6cb; }

        .ai-helper-panel {
            margin: 0 20px 10px;
            padding: 14px 16px;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            background: linear-gradient(180deg, #ffffff, #fafafa);
        }

        .ai-helper-panel h4 {
            margin: 0 0 6px 0;
            color: #2c3e50;
        }

        .ai-helper-panel p {
            margin: 0 0 12px 0;
            font-size: 0.85rem;
            color: #64748b;
        }

        .ai-helper-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 12px;
        }

        .ai-helper-actions button {
            padding: 8px 12px;
            border: 1px solid #d1d5db;
            border-radius: 999px;
            background: #fff;
            color: #334155;
            cursor: pointer;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .ai-helper-actions button:hover {
            border-color: var(--accent-color);
            color: var(--accent-color);
        }

        .ai-helper-output {
            min-height: 72px;
            padding: 12px;
            border-radius: 10px;
            background: #f8fafc;
            color: #334155;
            font-size: 0.9rem;
            line-height: 1.5;
            white-space: pre-wrap;
        }
    </style>
</head>
<body>
    <header>
        <nav>
            <a href="dashboard_seller.php" class="logo">Pasar<span>kraft</span><span style="font-size: 0.8rem; font-family:var(--font-body); color: #555;"> | Seller Centre</span></a>
            <div class="nav-links">
                <a href="dashboard_seller.php" <?php if(basename($_SERVER['PHP_SELF']) == 'dashboard_seller.php' || basename($_SERVER['PHP_SELF']) == 'homepage_seller.php') echo 'class="active-link" style="color: var(--accent-color);"'; ?>>Dashboard</a>
                <a href="myshop.php" <?php if(basename($_SERVER['PHP_SELF']) == 'myshop.php') echo 'class="active-link" style="color: var(--accent-color);"'; ?>>Products</a>
                <a href="chat_history_seller.php" <?php if(basename($_SERVER['PHP_SELF']) == 'chat_history_seller.php') echo 'class="active-link" style="color: var(--accent-color);"'; ?>>Customer Chats <?php if(isset($unread_count) && $unread_count > 0) echo '<span style="background: red; color: white; border-radius: 50%; padding: 2px 6px; font-size: 0.75rem; margin-left: 5px;">'.$unread_count.'</span>'; ?></a>
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

    <div class="chat-container">
        <!-- Sidebar Contacts / Inquiries -->
        <div class="chat-sidebar">
            <div class="chat-sidebar-header">
                <h3>Customer Inquiries</h3>
            </div>
            <div class="chat-contacts">
                <?php if (empty($inquiries)): ?>
                    <p style="padding: 20px; color: #999; text-align: center;">No inquiries yet.</p>
                <?php else: ?>
                    <?php foreach ($inquiries as $inq): ?>
                        <a href="chat_history_seller.php?inquiry_id=<?php echo $inq['id']; ?>" class="contact-item <?php echo ($active_inquiry_id == $inq['id']) ? 'active' : ''; ?>">
                            <?php 
                                $name = trim($inq['firstname'] . ' ' . $inq['lastname']); 
                                $display = $name ?: $inq['username']; 
                                $statusClassFormat = str_replace(' ', '-', $inq['status']);
                            ?>
                            <div class="contact-avatar" style="background:#3498db;"><?php echo strtoupper(substr($display, 0, 1)); ?></div>
                            <div class="contact-info">
                                <h4><?php echo htmlspecialchars($display); ?> <span class="status-badge status-<?php echo $statusClassFormat; ?>"><?php echo htmlspecialchars($inq['status']); ?></span></h4>
                                <p><?php echo htmlspecialchars($inq['product_title']); ?></p>
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
                <?php 
                    $dispName = trim($active_inquiry['firstname'] . ' ' . $active_inquiry['lastname']) ?: $active_inquiry['username']; 
                ?>
                <div class="chat-header">
                    <div class="header-left">
                        <div class="contact-avatar" style="width: 40px; height: 40px; background:#3498db;"><?php echo strtoupper(substr($dispName, 0, 1)); ?></div>
                        <div>
                            <h3><?php echo htmlspecialchars($dispName); ?></h3>
                            <p>Interest: <strong><?php echo htmlspecialchars($active_inquiry['product_title']); ?></strong></p>
                        </div>
                    </div>
                    <div class="header-actions">
                        <form method="POST" style="display:flex; gap:5px;">
                            <?php if($active_inquiry['status'] == 'In Discussion'): ?>
                                <button type="submit" name="update_status" value="Deal Agreed" class="btn-status agree">Accept Deal</button>
                                <button type="submit" name="update_status" value="No Deal" class="btn-status nodeal">No Deal</button>
                            <?php elseif($active_inquiry['status'] == 'Deal Agreed'): ?>
                                <button type="submit" name="update_status" value="Sold" class="btn-status sold">Mark as Sold</button>
                                <button type="submit" name="update_status" value="In Discussion" class="btn-status">Cancel Deal</button>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
                
                <div class="chat-messages" id="chatMessages">
                    <?php if (empty($messages)): ?>
                        <div style="text-align:center; color:#999; margin:auto;">Send a message to start the thread.</div>
                    <?php else: ?>
                        <?php foreach($messages as $msg): ?>
                            <?php $is_mine = ($msg['sender_id'] == $seller_id); ?>
                            <!-- Offer Message -->
                            <?php if ($msg['is_offer']): ?>
                                <div class="message <?php echo $is_mine ? 'sent' : 'received'; ?>" style="border: 2px solid <?php echo $is_mine ? '#2ecc71' : '#f1c40f'; ?>;">
                                    <strong><i class="fas fa-hand-holding-usd"></i> New Offer Made: RM <?php echo number_format($msg['offer_amount'], 2); ?></strong><br>
                                    <?php echo htmlspecialchars($msg['message']); ?>
                                    <span class="msg-time"><?php echo date('H:i', strtotime($msg['created_at'])); ?></span>
                                </div>
                            <?php else: ?>
                                <div class="message <?php echo $is_mine ? 'sent' : 'received'; ?>">
                                    <?php echo htmlspecialchars($msg['message']); ?>
                                    <span class="msg-time"><?php echo date('H:i', strtotime($msg['created_at'])); ?></span>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <div class="ai-helper-panel">
                    <h4><i class="fas fa-robot"></i> AI Assistant</h4>
                    <p>Summarize long chats or draft quick replies for the buyer.</p>
                    <div class="ai-helper-actions">
                        <button type="button" id="aiSummarizeBtn">Summarize Conversation</button>
                        <button type="button" id="aiReplyBtn">Suggest Quick Replies</button>
                    </div>
                    <div class="ai-helper-output" id="aiHelperOutput">AI summaries will appear here.</div>
                </div>

                <div class="chat-input-area">
                    <form class="chat-form" method="POST">
                        <input type="text" name="message" class="chat-input" placeholder="Type a message..." required autocomplete="off" <?php echo ($active_inquiry['status'] == 'Sold' || $active_inquiry['status'] == 'No Deal') ? 'disabled' : ''; ?>>
                        <button type="submit" class="btn-send" <?php echo ($active_inquiry['status'] == 'Sold' || $active_inquiry['status'] == 'No Deal') ? 'disabled' : ''; ?>><i class="fas fa-paper-plane"></i></button>
                    </form>
                </div>
                <!-- Auto scroll to bottom -->
                <script>
                    const pkConversationMessages = <?php echo json_encode($messages, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
                    const pkSellerId = <?php echo (int) $seller_id; ?>;
                    const pkBuyerName = <?php echo json_encode($active_inquiry ? (trim($active_inquiry['firstname'] . ' ' . $active_inquiry['lastname']) ?: $active_inquiry['username']) : 'Buyer', JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
                    const pkAiOutput = document.getElementById('aiHelperOutput');
                    const pkSummarizeBtn = document.getElementById('aiSummarizeBtn');
                    const pkReplyBtn = document.getElementById('aiReplyBtn');

                    var cm = document.getElementById('chatMessages');
                    if(cm) cm.scrollTop = cm.scrollHeight;

                    function buildTranscript() {
                        return pkConversationMessages.slice(-20).map(function (msg) {
                            const speaker = parseInt(msg.sender_id, 10) === pkSellerId ? 'Seller' : pkBuyerName;
                            return speaker + ': ' + (msg.message || '');
                        }).join('\n');
                    }

                    function requestAiHelp(mode) {
                        if (!pkAiOutput) return;
                        pkAiOutput.textContent = 'Generating AI response...';
                        const transcript = buildTranscript();
                        const prompt = mode === 'summary'
                            ? 'Summarize this seller-buyer conversation in 4 concise bullet points, then list the next action item for the seller. Finally suggest 3 short replies the artisan can send.\n\nConversation:\n' + transcript
                            : 'Suggest 3 short, polite quick replies the artisan can send next, based on the following conversation. Keep each reply under 18 words.\n\nConversation:\n' + transcript;

                        fetch('../api/chatbot.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ message: prompt, history: [] })
                        })
                        .then(function (res) { return res.json(); })
                        .then(function (data) {
                            if (data.status === 'success') {
                                pkAiOutput.textContent = data.reply;
                            } else {
                                pkAiOutput.textContent = 'AI helper error: ' + (data.message || 'Unknown error');
                            }
                        })
                        .catch(function () {
                            pkAiOutput.textContent = 'AI helper is unavailable right now.';
                        });
                    }

                    if (pkSummarizeBtn) {
                        pkSummarizeBtn.addEventListener('click', function () {
                            requestAiHelp('summary');
                        });
                    }

                    if (pkReplyBtn) {
                        pkReplyBtn.addEventListener('click', function () {
                            requestAiHelp('reply');
                        });
                    }
                </script>
            <?php else: ?>
                <div class="empty-chat">
                    <i class="far fa-comments"></i>
                    <h2>Select an inquiry</h2>
                    <p>Choose an inquiry from the left to view the thread and negotiation.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
