<?php
// ============================================
// DATABASE CONFIGURATION
// ============================================
define('DB_HOST', 'localhost');
define('DB_USER', 'root');       // Default XAMPP username
define('DB_PASS', '');           // Default XAMPP password (empty)
define('DB_NAME', 'lms_rmc');

define('SITE_NAME', 'RMC Library Management System');
define('SITE_URL', 'http://localhost:8080/lms');
define('FINE_PER_DAY', 5.00);
define('LOAN_DAYS', 7);
define('MAX_BORROW', 3);

// ============================================
// DATABASE CONNECTION (MySQLi)
// ============================================
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    die("
    <div style='font-family:sans-serif;max-width:500px;margin:80px auto;padding:30px;border:2px solid #e74c3c;border-radius:8px;background:#fff5f5;'>
        <h2 style='color:#e74c3c;'>⚠️ Database Connection Failed</h2>
        <p><strong>Error:</strong> " . $conn->connect_error . "</p>
        <hr>
        <p>Make sure XAMPP is running and the database <code>lms_rmc</code> has been imported.</p>
        <p>See the setup guide for instructions.</p>
    </div>");
}

$conn->set_charset("utf8mb4");

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ============================================
// HELPER FUNCTIONS
// ============================================

function redirect($url) {
    header("Location: $url");
    exit();
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        redirect(SITE_URL . '/index.php');
    }
}

function requireRole($role) {
    requireLogin();
    if ($_SESSION['user_role'] !== $role && $_SESSION['user_role'] !== 'admin') {
        redirect(SITE_URL . '/index.php?error=unauthorized');
    }
}

function sanitize($conn, $data) {
    return $conn->real_escape_string(htmlspecialchars(strip_tags(trim($data))));
}

function getUser($conn, $id) {
    $id = (int)$id;
    $result = $conn->query("SELECT * FROM users WHERE id = $id");
    return $result ? $result->fetch_assoc() : null;
}

function logActivity($conn, $user_id, $action, $details = '') {
    $user_id = (int)$user_id;
    $action = $conn->real_escape_string($action);
    $details = $conn->real_escape_string($details);
    $ip = $conn->real_escape_string($_SERVER['REMOTE_ADDR'] ?? '');
    $conn->query("INSERT INTO activity_logs (user_id, action, details, ip_address) VALUES ($user_id, '$action', '$details', '$ip')");
}

function calculateFine($due_date) {
    $today = new DateTime();
    $due = new DateTime($due_date);
    if ($today > $due) {
        $diff = $today->diff($due);
        return $diff->days * FINE_PER_DAY;
    }
    return 0;
}

function timeAgo($datetime) {
    $time = strtotime($datetime);
    $diff = time() - $time;
    if ($diff < 60) return "just now";
    if ($diff < 3600) return floor($diff/60) . " min ago";
    if ($diff < 86400) return floor($diff/3600) . " hr ago";
    return date('M d, Y', $time);
}
?>
