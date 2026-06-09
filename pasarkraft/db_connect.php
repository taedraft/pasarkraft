<?php
require_once __DIR__ . '/env_loader.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$servername = pk_env('PK_DB_HOST', 'sql204.infinityfree.com');
$username = pk_env('PK_DB_USER', 'if0_42022884');
$password = pk_env('PK_DB_PASSWORD', 'PWp1pzwdti3jW');
$dbname = pk_env('PK_DB_NAME', 'if0_42022884_pasarkraft_db');

if ($password === '') {
    die(
        'Database password is missing. Upload pk_config.env or .env into htdocs with PK_DB_PASSWORD set. ' .
        'InfinityFree may hide dotfiles, so pk_config.env is recommended.'
    );
}

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}
