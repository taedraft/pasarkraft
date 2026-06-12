<?php
session_start();
require __DIR__ . '/db_connect.php';

// Action: Cancel and reset flow
if (isset($_GET['action']) && $_GET['action'] === 'cancel') {
    unset($_SESSION['reset_step']);
    unset($_SESSION['reset_email']);
    unset($_SESSION['reset_otp']);
    unset($_SESSION['otp_sent_alert']);
    unset($_SESSION['reset_error']);
    header("Location: buyer/login_buyer.php");
    exit();
}

// Ensure reset step is initialized
if (!isset($_SESSION['reset_step'])) {
    $_SESSION['reset_step'] = 1;
}

$error_message = '';
if (isset($_SESSION['reset_error'])) {
    $error_message = $_SESSION['reset_error'];
    unset($_SESSION['reset_error']);
}

function smtp_read_response($socket)
{
    $lines = [];
    while (($line = fgets($socket, 1024)) !== false) {
        $lines[] = rtrim($line, "\r\n");
        if (strlen($line) >= 4 && $line[3] === ' ') {
            break;
        }
    }

    $code = 0;
    if (!empty($lines) && preg_match('/^(\d{3})/', $lines[0], $match)) {
        $code = intval($match[1]);
    }

    return [$code, implode("\n", $lines)];
}

function smtp_send_command($socket, $command, array $expected_codes)
{
    fwrite($socket, $command . "\r\n");
    list($code, $response) = smtp_read_response($socket);
    return [in_array($code, $expected_codes, true), $code, $response];
}

// Real SMTP mailer using Gmail-compatible settings from environment variables.
function send_otp_via_email($to_email, $otp_code)
{
    $smtp_host = pk_env('PK_SMTP_HOST', 'smtp.gmail.com');
    $smtp_port = intval(pk_env('PK_SMTP_PORT', '465'));
    $smtp_user = pk_env('PK_SMTP_USER', '');
    $smtp_pass = pk_env('PK_SMTP_PASS', '');
    $from_email = pk_env('PK_SMTP_FROM', $smtp_user);
    $from_name = pk_env('PK_SMTP_FROM_NAME', 'PasarKraft');

    if (empty($smtp_user) || empty($smtp_pass) || empty($from_email)) {
        return false;
    }

    $socket = @stream_socket_client("ssl://{$smtp_host}:{$smtp_port}", $errno, $errstr, 30, STREAM_CLIENT_CONNECT);
    if (!$socket) {
        return false;
    }

    stream_set_timeout($socket, 30);
    list($code, ) = smtp_read_response($socket);
    if ($code !== 220) {
        fclose($socket);
        return false;
    }

    list($ok, ) = smtp_send_command($socket, 'EHLO ' . $smtp_host, [250]);
    if (!$ok) {
        fclose($socket);
        return false;
    }

    list($ok, ) = smtp_send_command($socket, 'AUTH LOGIN', [334]);
    if (!$ok) {
        fclose($socket);
        return false;
    }

    list($ok, ) = smtp_send_command($socket, base64_encode($smtp_user), [334]);
    if (!$ok) {
        fclose($socket);
        return false;
    }

    list($ok, ) = smtp_send_command($socket, base64_encode($smtp_pass), [235]);
    if (!$ok) {
        fclose($socket);
        return false;
    }

    list($ok, ) = smtp_send_command($socket, 'MAIL FROM:<' . $from_email . '>', [250]);
    if (!$ok) {
        fclose($socket);
        return false;
    }

    list($ok, ) = smtp_send_command($socket, 'RCPT TO:<' . $to_email . '>', [250, 251]);
    if (!$ok) {
        fclose($socket);
        return false;
    }

    list($ok, ) = smtp_send_command($socket, 'DATA', [354]);
    if (!$ok) {
        fclose($socket);
        return false;
    }

    $subject = 'PasarKraft Password Reset OTP';
    $body = "Hello,\r\n\r\nYour PasarKraft verification code is: {$otp_code}\r\n\r\nThis code expires in 15 minutes. If you did not request this reset, please ignore this email.\r\n";
    $headers = [];
    $headers[] = 'From: ' . $from_name . ' <' . $from_email . '>';
    $headers[] = 'To: <' . $to_email . '>';
    $headers[] = 'Subject: ' . $subject;
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'Content-Type: text/plain; charset=UTF-8';

    fwrite($socket, implode("\r\n", $headers) . "\r\n\r\n" . $body . "\r\n.\r\n");
    list($code, ) = smtp_read_response($socket);
    fwrite($socket, "QUIT\r\n");
    fclose($socket);

    return $code === 250;
}

// POST processing logic
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $step = intval($_POST['step']);

    if ($step === 1) {
        // Step 1: Validate Email
        $email = isset($_POST['email']) ? trim($_POST['email']) : '';

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['reset_error'] = "Invalid email, enter VALID email address.";
            $_SESSION['reset_step'] = 1;
            header("Location: forgot_password.php");
            exit();
        }

        // Check if email exists in DB
        $stmt = $conn->prepare("SELECT id, username FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($res->num_rows === 0) {
            $_SESSION['reset_error'] = "Invalid email, enter VALID email address.";
            $_SESSION['reset_step'] = 1;
            $stmt->close();
            header("Location: forgot_password.php");
            exit();
        }

        $user_data = $res->fetch_assoc();
        $stmt->close();

        // Email exists! Generate 6-digit OTP
        $otp = strval(rand(100000, 999999));
        $expiry = date('Y-m-d H:i:s', strtotime('+15 minutes'));

        // Save OTP to user record
        $update_stmt = $conn->prepare("UPDATE users SET reset_token = ?, reset_token_expires_at = ? WHERE email = ?");
        $update_stmt->bind_param("sss", $otp, $expiry, $email);
        $update_stmt->execute();
        $update_stmt->close();

        // Send actual verification email via SMTP.
        if (!send_otp_via_email($email, $otp)) {
            $_SESSION['reset_error'] = "Unable to send verification email. Please check SMTP credentials or try again later.";
            $_SESSION['reset_step'] = 1;
            header("Location: forgot_password.php");
            exit();
        }

        // Set session variables
        $_SESSION['reset_email'] = $email;
        $_SESSION['reset_otp'] = $otp;
        $_SESSION['reset_step'] = 2;
        $_SESSION['otp_sent_alert'] = true; // Flag to show popup modal

        header("Location: forgot_password.php");
        exit();

    } elseif ($step === 2) {
        // Step 2: Validate OTP
        $otp_entered = isset($_POST['otp_code']) ? trim($_POST['otp_code']) : '';
        $email = isset($_SESSION['reset_email']) ? $_SESSION['reset_email'] : '';

        if (empty($otp_entered) || empty($email)) {
            $_SESSION['reset_error'] = "Invalid OTP code. Please try again.";
            $_SESSION['reset_step'] = 2;
            header("Location: forgot_password.php");
            exit();
        }

        // Verify in database with time check
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND reset_token = ? AND reset_token_expires_at >= NOW()");
        $stmt->bind_param("ss", $email, $otp_entered);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($res->num_rows === 0) {
            $_SESSION['reset_error'] = "Invalid OTP code. Please try again.";
            $_SESSION['reset_step'] = 2;
            $stmt->close();
            header("Location: forgot_password.php");
            exit();
        }

        $stmt->close();

        // OTP Validated! Move to Step 3
        $_SESSION['reset_step'] = 3;
        header("Location: forgot_password.php");
        exit();

    } elseif ($step === 3) {
        // Step 3: Enter and Update Password
        $new_password = isset($_POST['new_password']) ? $_POST['new_password'] : '';
        $confirm_password = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';
        $email = isset($_SESSION['reset_email']) ? $_SESSION['reset_email'] : '';

        if (empty($email)) {
            $_SESSION['reset_error'] = "Session expired. Please restart the process.";
            $_SESSION['reset_step'] = 1;
            header("Location: forgot_password.php");
            exit();
        }

        if ($new_password !== $confirm_password) {
            $_SESSION['reset_error'] = "Password not matched !";
            $_SESSION['reset_step'] = 3;
            header("Location: forgot_password.php");
            exit();
        }

        // Passwords match. Hash and update DB
        $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);

        $stmt = $conn->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_token_expires_at = NULL WHERE email = ?");
        $stmt->bind_param("ss", $hashed_password, $email);

        if ($stmt->execute()) {
            $stmt->close();
            // Clear password reset session state
            unset($_SESSION['reset_step']);
            unset($_SESSION['reset_email']);
            unset($_SESSION['reset_otp']);
            unset($_SESSION['otp_sent_alert']);

            // Echo a beautiful alert and redirect using JS as requested
            echo "<script>
                alert('Password reset successful! Please log in with your new password.');
                window.location.href = 'buyer/login_buyer.php';
            </script>";
            exit();
        } else {
            $_SESSION['reset_error'] = "Database error. Please try again.";
            $_SESSION['reset_step'] = 3;
            $stmt->close();
            header("Location: forgot_password.php");
            exit();
        }
    }
}

$current_step = $_SESSION['reset_step'];
$reset_email = isset($_SESSION['reset_email']) ? $_SESSION['reset_email'] : '';
$reset_otp = isset($_SESSION['reset_otp']) ? $_SESSION['reset_otp'] : '';

// Flag to show popup modal on page load
$show_popup = false;
if (isset($_SESSION['otp_sent_alert']) && $_SESSION['otp_sent_alert'] === true) {
    $show_popup = true;
    unset($_SESSION['otp_sent_alert']); // Show once only
}
?>
<!DOCTYPE html>
<html lang="ms">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password | Pasarkraft</title>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Playfair+Display:ital,wght@0,400;0,700;1,400&display=swap"
        rel="stylesheet">
    <!-- Font Awesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="styles.css">
    <style>
        /* Specific page adjustments */
        body {
            background-color: #fdfaf6;
            background:
                linear-gradient(rgba(0, 0, 0, 0.15), rgba(0, 0, 0, 0.25)),
                url('png/batik_woodcraft_bg.png');
            background-size: cover;
            background-position: center;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            position: relative;
        }

        .login-page-wrapper {
            flex: 1;
            display: flex;
            align-items: flex-start;
            justify-content: center;
            padding: 2rem;
            padding-top: 130px;
        }

        .login-container {
            margin: 0;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(12px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.25);
            width: 100%;
            max-width: 480px;
            border-radius: 12px;
            overflow: hidden;
            animation: slideUp 0.5s ease-out;
        }

        header {
            background-color: transparent !important;
            box-shadow: none !important;
            backdrop-filter: none !important;
            position: fixed !important;
            top: 0;
            width: 100%;
            z-index: 1000;
        }

        .logo {
            color: #fff !important;
        }

        .logo span {
            color: #d35400 !important;
        }

        /* Progress Steps styling */
        .step-progress-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.5rem 2rem 0;
            position: relative;
        }

        .step-progress-bar::before {
            content: '';
            position: absolute;
            top: 40px;
            left: 15%;
            right: 15%;
            height: 2px;
            background: #e2e8f0;
            z-index: 1;
        }

        .step-node {
            display: flex;
            flex-direction: column;
            align-items: center;
            z-index: 2;
            flex: 1;
        }

        .step-circle {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: #e2e8f0;
            color: #64748b;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 0.9rem;
            border: 2px solid #fff;
            transition: all 0.3s ease;
        }

        .step-label {
            font-size: 0.75rem;
            font-weight: 500;
            color: #64748b;
            margin-top: 6px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
        }

        .step-node.active .step-circle {
            background: var(--accent-color);
            color: #fff;
            box-shadow: 0 0 0 4px rgba(211, 84, 0, 0.2);
        }

        .step-node.active .step-label {
            color: var(--accent-color);
            font-weight: 600;
        }

        .step-node.completed .step-circle {
            background: #22c55e;
            color: #fff;
        }

        .step-node.completed .step-label {
            color: #22c55e;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Modern Custom Alert Overlay Modal */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .modal-overlay.show {
            display: flex;
            opacity: 1;
        }

        .modal-card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.3);
            padding: 2rem;
            width: 90%;
            max-width: 450px;
            text-align: center;
            transform: scale(0.9);
            transition: transform 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            border-top: 4px solid #22c55e;
        }

        .modal-overlay.show .modal-card {
            transform: scale(1);
        }

        .modal-icon {
            font-size: 3rem;
            color: #22c55e;
            margin-bottom: 1rem;
            animation: bounceIn 0.6s ease;
        }

        .modal-card h3 {
            font-family: var(--font-heading);
            font-size: 1.5rem;
            margin-bottom: 0.8rem;
            color: var(--primary-color);
        }

        .modal-card p {
            color: #4a5568;
            font-size: 0.95rem;
            line-height: 1.5;
            margin-bottom: 1.5rem;
        }

        .modal-btn {
            background: linear-gradient(135deg, var(--accent-color), #e67e22);
            color: #fff;
            border: none;
            padding: 0.8rem 2.5rem;
            border-radius: 50px;
            font-weight: 600;
            cursor: pointer;
            box-shadow: 0 4px 10px rgba(211, 84, 0, 0.2);
            transition: all 0.2s ease;
        }

        .modal-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(211, 84, 0, 0.3);
        }

        @keyframes bounceIn {
            0% {
                transform: scale(0.3);
                opacity: 0;
            }

            50% {
                transform: scale(1.05);
                opacity: 0.8;
            }

            70% {
                transform: scale(0.9);
                opacity: 0.9;
            }

            100% {
                transform: scale(1);
                opacity: 1;
            }
        }

        .helper-text {
            display: block;
            margin-top: 5px;
            font-size: 0.8rem;
            color: #64748b;
        }
    </style>
</head>

<body>

    <!-- Navigation -->
    <header>
        <nav>
            <a href="homepage.php" class="logo">Pasar<span>kraft</span>.</a>
        </nav>
    </header>

    <!-- Main Content Wrapper -->
    <div class="login-page-wrapper">
        <div class="login-container">

            <!-- Progress Tracker -->
            <div class="step-progress-bar">
                <div
                    class="step-node <?php echo ($current_step >= 1) ? 'active' : ''; ?> <?php echo ($current_step > 1) ? 'completed' : ''; ?>">
                    <div class="step-circle"><?php echo ($current_step > 1) ? '<i class="fas fa-check"></i>' : '1'; ?>
                    </div>
                    <div class="step-label">Email</div>
                </div>
                <div
                    class="step-node <?php echo ($current_step >= 2) ? 'active' : ''; ?> <?php echo ($current_step > 2) ? 'completed' : ''; ?>">
                    <div class="step-circle"><?php echo ($current_step > 2) ? '<i class="fas fa-check"></i>' : '2'; ?>
                    </div>
                    <div class="step-label">OTP Code</div>
                </div>
                <div
                    class="step-node <?php echo ($current_step >= 3) ? 'active' : ''; ?> <?php echo ($current_step > 3) ? 'completed' : ''; ?>">
                    <div class="step-circle">3</div>
                    <div class="step-label">Password</div>
                </div>
            </div>

            <div class="login-content" style="padding-top: 1rem;">

                <!-- Display Errors -->
                <?php if (!empty($error_message)): ?>
                    <div
                        style="background-color: #fee2e2; border-left: 4px solid #ef4444; color: #b91c1c; padding: 12px 16px; margin-bottom: 1.5rem; font-size: 0.9rem; border-radius: 4px; display: flex; align-items: center; gap: 8px;">
                        <i class="fas fa-exclamation-circle"></i>
                        <span><?php echo htmlspecialchars($error_message); ?></span>
                    </div>
                <?php endif; ?>

                <!-- Step forms -->
                <?php if ($current_step === 1): ?>
                    <h2>Forgot Password?</h2>
                    <p>Enter your registered email address to verify existence and send a password reset OTP code directly
                        to your Gmail.</p>

                    <form class="login-form" action="forgot_password.php" method="POST">
                        <input type="hidden" name="step" value="1">

                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <input type="email" id="email" name="email" placeholder="Enter your email address" required>
                        </div>

                        <button type="submit" class="btn btn-full">Send OTP to Email</button>

                        <div class="form-footer" style="margin-top: 1.5rem;">
                            <a href="buyer/login_buyer.php">Back to Buyer Login</a>
                        </div>
                    </form>

                <?php elseif ($current_step === 2): ?>
                    <h2>Enter Reset OTP</h2>
                    <p>We've sent a secure 6-digit verification code to your Gmail inbox at
                        <strong><?php echo htmlspecialchars($reset_email); ?></strong>. Please check your inbox (and spam
                        folder) and enter it below.
                    </p>

                    <form class="login-form" action="forgot_password.php" method="POST">
                        <input type="hidden" name="step" value="2">

                        <div class="form-group">
                            <label for="otp_code">OTP Code</label>
                            <input type="text" id="otp_code" name="otp_code" placeholder="Enter 6-digit OTP code" required
                                maxlength="6" pattern="\d{6}"
                                style="text-align: center; letter-spacing: 4px; font-size: 1.25rem; font-weight: 600;">
                            <span class="helper-text"><i class="fas fa-info-circle"></i> Code expires in 15 minutes.</span>
                        </div>

                        <button type="submit" class="btn btn-full">Verify Code</button>

                        <div class="form-footer" style="margin-top: 1.5rem; display: flex; justify-content: space-between;">
                            <a href="forgot_password.php?action=cancel" style="color: #64748b;"><i
                                    class="fas fa-arrow-left"></i> Cancel</a>
                            <a href="forgot_password.php?action=cancel" style="color: var(--accent-color);">Resend Email</a>
                        </div>
                    </form>

                <?php elseif ($current_step === 3): ?>
                    <h2>Create New Password</h2>
                    <p>Resetting password for <strong><?php echo htmlspecialchars($reset_email); ?></strong>. Enter and
                        confirm your new secure password.</p>

                    <form class="login-form" action="forgot_password.php" method="POST">
                        <input type="hidden" name="step" value="3">

                        <div class="form-group">
                            <label for="new_password">New Password</label>
                            <input type="password" id="new_password" name="new_password" placeholder="Enter new password"
                                required minlength="5">
                        </div>

                        <div class="form-group">
                            <label for="confirm_password">Confirm New Password</label>
                            <input type="password" id="confirm_password" name="confirm_password"
                                placeholder="Confirm new password" required minlength="5">
                        </div>

                        <button type="submit" class="btn btn-full">Reset Password</button>

                        <div class="form-footer" style="margin-top: 1.5rem;">
                            <a href="forgot_password.php?action=cancel" style="color: #64748b;">Cancel Reset</a>
                        </div>
                    </form>
                <?php endif; ?>

            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer>
        <p class="copyright">&copy; 2026 Pasarkraft. Keeping Traditions Alive.</p>
    </footer>

    <!-- Modern Premium Popup Modal Dialog -->
    <div id="otp-modal" class="modal-overlay <?php echo $show_popup ? 'show' : ''; ?>">
        <div class="modal-card">
            <div class="modal-icon">
                <i class="fas fa-paper-plane"></i>
            </div>
            <h3>OTP Code Sent!</h3>
            <p>We have sent a password reset OTP code to your email -
                <strong><?php echo htmlspecialchars($reset_email); ?></strong>
            </p>
            <button class="modal-btn" onclick="closeModal()">Got it</button>
        </div>
    </div>

    <script>
        function closeModal() {
            const modal = document.getElementById('otp-modal');
            modal.classList.remove('show');
            // Remove display style after transition
            setTimeout(() => {
                modal.style.display = 'none';
            }, 300);
        }
    </script>
</body>

</html>