<?php
session_start();
require 'db_connect.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $firstname = isset($_POST['firstname']) ? $_POST['firstname'] : '';
    $lastname = isset($_POST['lastname']) ? $_POST['lastname'] : '';
    $username = isset($_POST['username']) ? $_POST['username'] : '';
    $email = isset($_POST['email']) ? $_POST['email'] : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $confirm_password = isset($_POST['confirm-password']) ? $_POST['confirm-password'] : '';
    $role = isset($_POST['role']) ? $_POST['role'] : 'buyer'; // default to buyer

    // Seller fields
    $shopname = isset($_POST['shopname']) ? $_POST['shopname'] : null;
    $ssm = isset($_POST['ssm']) ? $_POST['ssm'] : null;
    $phone = isset($_POST['phone']) ? $_POST['phone'] : null;

    if ($role === 'seller') {
        if (empty($firstname)) $firstname = $shopname;
        if (empty($lastname)) $lastname = 'Artisan';
    }

    // Basic validation
    if (empty($firstname) || empty($lastname) || empty($username) || empty($email) || empty($password)) {
        $_SESSION['register_error'] = "Please fill all required fields.";
        header("Location: " . $_SERVER['HTTP_REFERER']);
        exit();
    }

    if ($password !== $confirm_password) {
        $_SESSION['register_error'] = "Passwords do not match.";
        header("Location: " . $_SERVER['HTTP_REFERER']);
        exit();
    }

    if (strlen($password) < 5) {
        $_SESSION['register_error'] = "Password must be at least 5 characters long.";
        header("Location: " . $_SERVER['HTTP_REFERER']);
        exit();
    }

    // Check for duplicate username or email
    $check_stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
    $check_stmt->bind_param("ss", $username, $email);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();

    if ($check_result->num_rows > 0) {
        $_SESSION['register_error'] = "Username or Email is already taken. Please choose another one.";
        header("Location: " . $_SERVER['HTTP_REFERER']);
        $check_stmt->close();
        exit();
    }
    $check_stmt->close();

    // Check for duplicate shopname or ssm if role is seller
    if ($role == 'seller') {
        $art_check_stmt = $conn->prepare("SELECT id FROM artisans WHERE shopname = ? OR ssm = ?");
        $art_check_stmt->bind_param("ss", $shopname, $ssm);
        $art_check_stmt->execute();
        $art_check_result = $art_check_stmt->get_result();
        
        if ($art_check_result->num_rows > 0) {
            $_SESSION['register_error'] = "Shop Name or Registration Number (SSM) is already registered.";
            header("Location: " . $_SERVER['HTTP_REFERER']);
            $art_check_stmt->close();
            exit();
        }
        $art_check_stmt->close();
    }

    // Hash password
    $hashed_password = password_hash($password, PASSWORD_BCRYPT);

    // Prepare and execute statement
    $stmt = $conn->prepare("INSERT INTO users (firstname, lastname, username, email, password, role) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssss", $firstname, $lastname, $username, $email, $hashed_password, $role);

    if ($stmt->execute()) {
        $new_user_id = $conn->insert_id;

        if ($role == 'seller') {
            // Insert into artisans table — explicitly set pending so admin must approve before seller can add products
            $art_stmt = $conn->prepare("INSERT INTO artisans (user_id, shopname, ssm, phone, approval_status) VALUES (?, ?, ?, ?, 'pending')");
            $art_stmt->bind_param("isss", $new_user_id, $shopname, $ssm, $phone);
            $art_stmt->execute();
            $art_stmt->close();

            $_SESSION['register_success'] = "Registration successful! Your store application is now under review. Admin will approve your account before you can start selling.";
        } else {
            $_SESSION['register_success'] = "Registration successful!";
        }
        header("Location: " . $_SERVER['HTTP_REFERER']);
        exit();
    } else {
        $_SESSION['register_error'] = "Error: " . $stmt->error;
        header("Location: " . $_SERVER['HTTP_REFERER']);
        exit();
    }

    $stmt->close();
    $conn->close();
}
?>