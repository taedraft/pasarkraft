<?php
/**
 * PasarKraft Core Database Connection & Configuration Engine
 * Centralizes all credentials to circumvent environment loading limitations on InfinityFree.
 */

// Error-handling setup (Switch to 0 in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

/**
 * Global Environment Fallback Engine
 * Checks if a constant exists first, then tries local array parsing, and finally falls back to code defaults.
 */
function pk_env($key, $default = '')
{
    static $env = null;

    // Direct constant check first
    if (defined($key)) {
        return constant($key);
    }

    // Backup loader: parsing local .env if it actually exists/is readable
    if ($env === null) {
        $env = [];
        $envPath = __DIR__ . '/.env';

        if (file_exists($envPath)) {
            $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos(trim($line), '#') === 0)
                    continue;

                if (strpos($line, '=') !== false) {
                    list($name, $value) = explode('=', $line, 2);
                    $name = trim($name);
                    $value = trim($value, " \t\n\r\0\x0B\"'");
                    $env[$name] = $value;
                }
            }
        }
    }

    return isset($env[$key]) ? $env[$key] : $default;
}

// =========================================================================
//  CENTRAL CONFIGURATION REGISTRY (Your Secure Defaults)
// =========================================================================

// Database Settings
$servername = pk_env('PK_DB_HOST', 'sql204.infinityfree.com');
$username = pk_env('PK_DB_USER', 'if0_42022884');
$password = pk_env('PK_DB_PASSWORD', 'PWp1pzwdti3jW');
$dbname = pk_env('PK_DB_NAME', 'if0_42022884_pasarkraft_db');

// Recommender API Settings
define('PK_API_BASE', pk_env('PK_API_BASE', ''));
define('PK_API_KEY', pk_env('PK_API_KEY', 'pk_4R9q8mW7vT2xK5nH1sL6cQ3yB8dZ0eU'));
define('PK_PYTHON_BIN', pk_env('PK_PYTHON_BIN', 'python'));

// Gemini AI API Settings
define('GEMINI_API_KEY', pk_env('GEMINI_API_KEY', 'YOUR API KEY'));

// Gmail SMTP Mailer Settings
define('PK_SMTP_HOST', pk_env('PK_SMTP_HOST', 'smtp.gmail.com'));
define('PK_SMTP_PORT', intval(pk_env('PK_SMTP_PORT', 465)));
define('PK_SMTP_USER', pk_env('PK_SMTP_USER', 'af03.af30@gmail.com'));
define('PK_SMTP_PASS', pk_env('PK_SMTP_PASS', 'umay rpsa ajzt slia'));
define('PK_SMTP_FROM', pk_env('PK_SMTP_FROM', 'af03.af30@gmail.com'));
define('PK_SMTP_FROM_NAME', pk_env('PK_SMTP_FROM_NAME', 'PasarKraft'));

// =========================================================================
//  DATABASE INITIALIZATION
// =========================================================================

if ($password === '') {
    header('Content-Type: text/plain');
    die('Database password configuration mismatch. Verify configurations inside db_connect.php.');
}

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    header('Content-Type: text/plain');
    die('Connection failed: ' . $conn->connect_error);
}

// Set charset to avoid accent/malformed character mapping bugs
$conn->set_charset("utf8mb4");