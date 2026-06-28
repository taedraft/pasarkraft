<?php
session_start();
require 'db_connect.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];
    $password = $_POST['password'];

    if (empty($email) || empty($password)) {
        $_SESSION['login_error'] = "Please enter email and password.";
        header("Location: " . $_SERVER['HTTP_REFERER']);
        exit();
    }

    $stmt = $conn->prepare("SELECT u.id, u.firstname, u.lastname, u.password, u.role,
        COALESCE(u.status, 'active') AS status,
        COALESCE(a.approval_status, 'approved') AS approval_status
        FROM users u
        LEFT JOIN artisans a ON u.id = a.user_id
        WHERE u.email = ? OR u.username = ?");
    $stmt->bind_param("ss", $email, $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {

            // Check if the user's role matches the expected portal role
            $expected_role = $_POST['expected_role'] ?? '';
            if (!empty($expected_role) && $user['role'] !== $expected_role) {
                $portalName = ($expected_role === 'admin') ? 'Admin' : ucfirst($expected_role) . 's';
                $_SESSION['login_error'] = "Access denied. This login portal is only for " . $portalName . ".";
                header("Location: " . $_SERVER['HTTP_REFERER']);
                exit();
            }

            // Block suspended accounts
            if ($user['status'] === 'suspended') {
                $_SESSION['login_error'] = "Your account has been suspended. Please contact admin at admin@pasarkraft.com.";
                header("Location: " . $_SERVER['HTTP_REFERER']);
                exit();
            }

            $_SESSION['user_id']          = $user['id'];
            $_SESSION['firstname']        = $user['firstname'];
            $_SESSION['role']             = $user['role'];
            // Store approval status in session so seller pages can check without extra DB queries
            $_SESSION['approval_status']  = $user['approval_status'];

            if ($user['role'] == 'admin') {
                header("Location: admin/dashboard_admin.php");
            } elseif ($user['role'] == 'seller') {
                header("Location: seller/dashboard_seller.php");
            } else {
                header("Location: homepage.php");
            }
            exit();
        } else {
            $_SESSION['login_error'] = "password incorrect";
            header("Location: " . $_SERVER['HTTP_REFERER']);
            exit();
        }
    } else {
        if (strpos($_SERVER['HTTP_REFERER'], 'login_seller.php') !== false) {
            // Provide exact string user requested for unregistered seller
            $_SESSION['login_error'] = "user not found, please register first. Email is not found !";
        } else {
            $_SESSION['login_error'] = "Email is not found ! User not found, please register first.";
        }
        header("Location: " . $_SERVER['HTTP_REFERER']);
        exit();
    }

    $stmt->close();
    $conn->close();
}
?>
