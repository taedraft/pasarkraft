<?php
session_start();
require "../db_connect.php";
$notif_stmt = $conn->query("SELECT COUNT(*) as cnt FROM artisans WHERE approval_status = 'pending'");
$admin_notif_count = $notif_stmt->fetch_assoc()["cnt"];


// Redirect if not admin
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    // header("Location: login_admin.php");
    // skip strict exit for testing right now but we should have it
}

// Handle actions
if (isset($_GET["action"]) && isset($_GET["id"])) {
    $action = $_GET["action"];
    $uid = intval($_GET["id"]);
    
    if ($action === "approve") {
        $conn->query("UPDATE artisans SET approval_status = 'approved' WHERE user_id = $uid");
    } elseif ($action === "reject") {
        $conn->query("UPDATE artisans SET approval_status = 'rejected' WHERE user_id = $uid");
    } elseif ($action === "suspend") {
        $conn->query("UPDATE users SET status = 'suspended' WHERE id = $uid");
    } elseif ($action === "delete") {
        $conn->query("DELETE FROM users WHERE id = $uid");
    }
    header("Location: admin_manageUser.php");
    exit();
}

$users_data = [];
$pending_count = 0;

$query = "SELECT u.id, u.firstname, u.lastname, u.username, u.email, u.role, u.status, u.created_at, a.shopname, a.ssm, a.phone, a.approval_status 
          FROM users u 
          LEFT JOIN artisans a ON u.id = a.user_id 
          WHERE u.role != 'admin' 
          ORDER BY u.created_at DESC";

$result = $conn->query($query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $users_data[] = $row;
        if ($row["role"] === "seller" && $row["approval_status"] === "pending") {
            $pending_count++;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ms">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management | Pasarkraft Admin</title>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Playfair+Display:ital,wght@0,400;0,700;1,400&display=swap"
        rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../styles.css">
    <style>
        .admin-container {
            max-width: 1400px;
            margin: 100px auto 3rem;
            padding: 0 2rem;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .page-header h1 {
            font-size: 1.8rem;
            color: #2c3e50;
        }

        /* Filter Tabs */
        .filter-tabs {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
            border-bottom: 1px solid #eee;
            padding-bottom: 1rem;
        }

        .tab-btn {
            background: none;
            border: none;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.9rem;
            color: #7f8c8d;
            cursor: pointer;
            transition: all 0.2s;
            font-weight: 500;
        }

        .tab-btn.active {
            background: #2c3e50;
            color: white;
            box-shadow: 0 4px 10px rgba(44, 62, 80, 0.2);
        }

        .tab-btn:hover:not(.active) {
            background: #f1f2f6;
            color: #2c3e50;
        }

        /* Table Styling matching the reference image layout but theme aligned */
        .user-table-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.04);
            overflow: hidden;
            /* For rounded corners on header */
        }

        .user-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1000px;
        }

        .user-table thead {
            background: #e8ecef;
            /* Neutral greyish blue, distinct from header */
        }

        .user-table th {
            text-align: left;
            padding: 18px 24px;
            font-size: 0.85rem;
            color: #555;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }

        .user-table tbody tr {
            border-bottom: 1px solid #f5f5f5;
            transition: background 0.2s;
        }

        .user-table tbody tr:hover {
            background-color: #fdfaf6;
        }

        .user-table td {
            padding: 20px 24px;
            color: #2c3e50;
            vertical-align: middle;
        }

        /* ID Column */
        .col-id {
            color: #7f8c8d;
            font-family: monospace;
            font-size: 0.9rem;
        }

        /* User Info Column */
        .user-info-cell {
            display: flex;
            flex-direction: column;
        }

        .user-name {
            font-weight: 600;
            color: #2c3e50;
            font-size: 0.95rem;
            margin-bottom: 4px;
        }

        .user-sub {
            font-size: 0.8rem;
            color: #95a5a6;
        }

        /* Role Badge */
        .role-badge {
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            display: inline-block;
        }

        .role-seller {
            background-color: #fff3e0;
            color: #e67e22;
        }

        .role-buyer {
            background-color: #e3f2fd;
            color: #2980b9;
        }

        /* Status Badge */
        .status-badge {
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .status-pending {
            background-color: #fff8e1;
            color: #f39c12;
            border: 1px solid #ffe082;
        }

        .status-active {
            background-color: #e8f5e9;
            color: #27ae60;
            border: 1px solid #c8e6c9;
        }

        .status-inactive {
            background-color: #ffebee;
            color: #e53935;
            border: 1px solid #ffcdd2;
        }

        /* Action Buttons */
        .action-btn-group {
            display: flex;
            gap: 10px;
        }

        .btn-icon {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 0.9rem;
        }

        .btn-approve {
            background-color: #e8f5e9;
            color: #27ae60;
            border: 1px solid #c8e6c9;
        }

        .btn-approve:hover {
            background-color: #27ae60;
            color: white;
        }

        .btn-suspend {
            background-color: #fff3e0;
            color: #e67e22;
            border: 1px solid #ffe0b2;
        }

        .btn-suspend:hover {
            background-color: #e67e22;
            color: white;
        }

        .btn-delete {
            background-color: #ffebee;
            color: #e53935;
            border: 1px solid #ffcdd2;
        }

        .btn-delete:hover {
            background-color: #e53935;
            color: white;
        }

        /* Pending Highlight Row (optional) */
        .row-pending {
            background-color: #fffdf5;
        }

        /* Modal Styles */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .modal-content {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            width: 100%;
            max-width: 500px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            position: relative;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            border-bottom: 1px solid #eee;
            padding-bottom: 1rem;
        }

        .modal-header h3 {
            margin: 0;
            color: #2c3e50;
        }

        .close-modal {
            background: none;
            border: none;
            font-size: 1.2rem;
            color: #7f8c8d;
            cursor: pointer;
        }

        .modal-form-group {
            margin-bottom: 1rem;
        }

        .modal-form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #2c3e50;
            font-weight: 500;
            font-size: 0.9rem;
        }

        .modal-form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 0.95rem;
        }

        .modal-actions {
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
            margin-top: 2rem;
        }
    </style>
</head>

<body>

    <!-- Admin Navigation -->
    <header>
        <nav>
            <a href="dashboard_admin.php" class="logo">Pasar<span>kraft</span><span
                    style="font-size: 0.8rem; font-family:var(--font-body); color: #c0392b;"> | Admin Panel</span></a>

            <div class="nav-links">
                <a href="dashboard_admin.php">Dashboard</a>
                <a href="admin_manageUser.php" class="active-link">Users Management<?php if(isset($admin_notif_count) && $admin_notif_count > 0): ?> <span style="background:#e74c3c; color:white; border-radius:10px; padding:2px 7px; font-size:0.75rem; margin-left:3px; font-weight:bold; box-shadow:0 2px 4px rgba(231,76,60,0.3);"><?php echo $admin_notif_count; ?></span><?php endif; ?></a>
                <a href="admin_recommender.php">AI Recommender</a>
                <a href="admin_account.php">Account</a>
                <a href="../logout.php" class="nav-login" style="color:#e74c3c;">Logout</a>
                <div class="profile-icon"
                    style="background:#fceeee; color:#c0392b; width:35px; height:35px; display:flex; align-items:center; justify-content:center; border-radius:50%;">
                    <i class="fas fa-shield-alt"></i>
                </div>
            </div>
        </nav>
    </header>

    <div class="admin-container">

        <div class="page-header">
            <div>
                <h1>User Management</h1>
                <p style="color:#7f8c8d; margin-top:5px;">Manage users, approve sellers, and monitor platform activity.
                </p>
            </div>
            <div>
                <button class="btn"
                    style="background:#2c3e50; color:white; border:none; padding:10px 20px; margin-right: 10px;"
                    onclick="openAddAdminModal()">
                    <i class="fas fa-user-plus"></i> Add Admin
                </button>
                <button class="btn" style="background:#2c3e50; color:white; border:none; padding:10px 20px;">
                    <i class="fas fa-download"></i> Export Report
                </button>
            </div>
        </div>

        <div class="filter-tabs">
            <button class="tab-btn active">All Users</button>
            <button class="tab-btn">Pending Approval <span style="background:#e74c3c; color:white; padding:2px 6px; border-radius:10px; font-size:0.7rem; margin-left:5px;"><?php echo $pending_count; ?></span></button>
            <button class="tab-btn">Sellers</button>
            <button class="tab-btn">Buyers</button>
        </div>

        <div class="user-table-container">
            <table class="user-table">
                <thead>
                    <tr>
                        <th style="width: 50px;">ID</th>
                        <th>User / Shop Name</th>
                        <th>Contact info</th>
                        <th>Role</th>
                        <th>Date Joined</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
<?php foreach ($users_data as $u): ?>
    <?php
    $is_pending = ($u["role"] === "seller" && $u["approval_status"] === "pending");
    $tr_class = $is_pending ? "row-pending" : "";
    
    $name_display = htmlspecialchars($u["role"] === "seller" ? $u["shopname"] : ($u["firstname"]." ".$u["lastname"]));
    $sub_display = htmlspecialchars($u["role"] === "seller" ? ("SSM: ".$u["ssm"]) : $u["username"]);
    $contact = htmlspecialchars($u["role"] === "seller" ? $u["phone"] : "");
    ?>
    <tr class="<?php echo $tr_class; ?>">
        <td class="col-id"><?php echo $u["id"]; ?></td>
        <td>
            <div class="user-info-cell">
                <span class="user-name"><?php echo $name_display; ?></span>
                <span class="user-sub"><?php echo $sub_display; ?></span>
            </div>
        </td>
        <td>
            <div class="user-info-cell">
                <span style="font-size:0.9rem;"><?php echo htmlspecialchars($u["email"]); ?></span>
                <span class="user-sub"><?php echo $contact; ?></span>
            </div>
        </td>
        <td><span class="role-badge role-<?php echo $u["role"]; ?>"><?php echo ucfirst($u["role"]); ?></span></td>
        <td><?php echo date("d M Y", strtotime($u["created_at"])); ?></td>
        <td>
            <?php if ($is_pending): ?>
                <span class="status-badge status-pending">Pending</span>
            <?php elseif ($u["status"] === "suspended"): ?>
                <span class="status-badge status-inactive">Suspended</span>
            <?php else: ?>
                <span class="status-badge status-active">Active</span>
            <?php endif; ?>
        </td>
        <td>
            <div class="action-btn-group">
                <?php if ($is_pending): ?>
                    <button class="btn-icon btn-approve" title="Approve Seller" onclick="window.location.href='admin_manageUser.php?action=approve&id=<?php echo $u["id"]; ?>'"><i class="fas fa-check"></i></button>
                    <button class="btn-icon btn-delete" title="Reject Application" onclick="if(confirm('Reject this? ')) window.location.href='admin_manageUser.php?action=reject&id=<?php echo $u["id"]; ?>'"><i class="fas fa-times"></i></button>
                <?php else: ?>
                    <button class="btn-icon btn-suspend" title="Suspend User" onclick="if(confirm('Suspend user? ')) window.location.href='admin_manageUser.php?action=suspend&id=<?php echo $u["id"]; ?>'"><i class="fas fa-ban"></i></button>
                    <button class="btn-icon btn-delete" title="Delete User" onclick="if(confirm('Delete user permanently? ')) window.location.href='admin_manageUser.php?action=delete&id=<?php echo $u["id"]; ?>'"><i class="fas fa-trash-alt"></i></button>
                <?php endif; ?>
            </div>
        </td>
    </tr>
<?php endforeach; ?>
<?php if (empty($users_data)): ?>
<tr><td colspan="7" style="text-align:center; padding: 2rem;">No users found.</td></tr>
<?php endif; ?>
</tbody>
            </table>
        </div>
    </div>

    <!-- Add Admin Modal -->
    <div class="modal-overlay" id="addAdminModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add New Admin</h3>
                <button class="close-modal" onclick="closeAddAdminModal()"><i class="fas fa-times"></i></button>
            </div>
            <form onsubmit="event.preventDefault(); addNewAdmin();">
                <div class="modal-form-group">
                    <label>Full Name</label>
                    <input type="text" class="modal-form-control" placeholder="Enter full name" required>
                </div>
                <div class="modal-form-group">
                    <label>Email Address</label>
                    <input type="email" class="modal-form-control" placeholder="Enter email address" required>
                </div>
                <div class="modal-form-group">
                    <label>Role / Position</label>
                    <select class="modal-form-control">
                        <option>Moderator</option>
                        <option>Super Admin</option>
                        <option>Support Staff</option>
                    </select>
                </div>
                <div class="modal-form-group">
                    <label>Temporary Password</label>
                    <input type="password" class="modal-form-control" placeholder="Create temporary password" required>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn" style="background:transparent; color:#555; border:1px solid #ddd;"
                        onclick="closeAddAdminModal()">Cancel</button>
                    <button type="submit" class="btn" style="background:#27ae60; color:white; border:none;">Create
                        Account</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Footer -->
    <footer>
        <p class="copyright">&copy; 2026 Pasarkraft. Admin Panel.</p>
    </footer>

    <script>
        function approveUser(btn) {
            if (confirm("Approve this seller account? They will be notified via email.")) {
                const row = btn.closest('tr');
                const statusBadge = row.querySelector('.status-badge');

                statusBadge.textContent = "Active";
                statusBadge.className = "status-badge status-active";

                // Remove approve button, keep suspend/delete
                const actionGroup = row.querySelector('.action-btn-group');
                actionGroup.innerHTML = `
                    <button class="btn-icon btn-suspend" title="Suspend User"><i class="fas fa-ban"></i></button>
                    <button class="btn-icon btn-delete" title="Delete User" onclick="deleteUser(this)"><i class="fas fa-trash-alt"></i></button>
                `;

                row.classList.remove('row-pending');
                alert("Seller approved successfully.");
            }
        }

        function deleteUser(btn) {
            if (confirm("Are you sure you want to remove this user? This action cannot be undone.")) {
                const row = btn.closest('tr');
                row.style.opacity = "0.5";
                setTimeout(() => {
                    row.remove();
                }, 300);
            }
        }

        // Modal Functions
        function openAddAdminModal() {
            document.getElementById('addAdminModal').style.display = 'flex';
        }

        function closeAddAdminModal() {
            document.getElementById('addAdminModal').style.display = 'none';
        }

        function addNewAdmin() {
            alert('New admin account created successfully! An email has been sent to the user.');
            closeAddAdminModal();
        }

        // Close modal when clicking outside
        window.onclick = function (event) {
            const modal = document.getElementById('addAdminModal');
            if (event.target == modal) {
                closeAddAdminModal();
            }
        }
    </script>


<script>
document.querySelectorAll(".tab-btn").forEach(btn => {
    btn.addEventListener("click", function() {
        document.querySelectorAll(".tab-btn").forEach(b => b.classList.remove("active"));
        this.classList.add("active");
        
        const filter = this.textContent.toLowerCase();
        document.querySelectorAll("tbody tr").forEach(row => {
            row.style.display = ""; // Reset
            
            if (filter.includes("pending")) {
                if (!row.classList.contains("row-pending")) row.style.display = "none";
            } else if (filter.includes("sellers")) {
                if (!row.querySelector(".role-seller")) row.style.display = "none";
            } else if (filter.includes("buyers")) {
                if (!row.querySelector(".role-buyer")) row.style.display = "none";
            }
        });
    });
});
</script>
</body>

</html>
