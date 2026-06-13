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

    $stmt = $conn->prepare("SELECT id, firstname, lastname, password, role FROM users WHERE email = ? OR username = ?");
    $stmt->bind_param("ss", $email, $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['firstname'] = $user['firstname'];
            $_SESSION['role'] = $user['role'];

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
