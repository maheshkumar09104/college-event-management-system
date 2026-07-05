<?php
define('DB_HOST', getenv('DB_HOST') ?: 'mysql-37ee7a06-mahesh09104-4ff1.i.aivencloud.com');
define('DB_USER', getenv('DB_USER') ?: 'avnadmin');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_NAME', getenv('DB_NAME') ?: 'defaultdb');
define('DB_PORT', getenv('DB_PORT') ?: 27725);
define('SITE_NAME', 'CampusEvents');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$conn = mysqli_init();
mysqli_ssl_set($conn, NULL, NULL, __DIR__ . '/ca.pem', NULL, NULL);
$success = mysqli_real_connect(
    $conn,
    DB_HOST,
    DB_USER,
    DB_PASS,
    DB_NAME,
    DB_PORT,
    NULL,
    MYSQLI_CLIENT_SSL
);

if (!$success) {
    die("DB Error: " . mysqli_connect_error());
}

$conn->set_charset("utf8");

function isLoggedIn() { return isset($_SESSION['user_id']); }
function isAdmin() { return isset($_SESSION['role']) && $_SESSION['role'] === 'admin'; }
function requireLogin() { if (!isLoggedIn()) { header("Location: login.php"); exit(); } }
function requireAdmin() { if (!isAdmin()) { header("Location: dashboard.php"); exit(); } }
function clean($data) { global $conn; return htmlspecialchars(strip_tags(trim($conn->real_escape_string($data)))); }
?>