<?php
// ============================================
// DATABASE CONFIGURATION
// ============================================
date_default_timezone_set('Asia/Manila');

define('DB_HOST', 'localhost');
define('DB_USER', 'root');       // Default XAMPP username
define('DB_PASS', '');           // Default XAMPP password (empty)
define('DB_NAME', 'lms_rmc');

define('SITE_NAME', 'RMC Library Management System');
define('SITE_URL', 'http://localhost/lms');

// ============================================
// DATABASE CONNECTION (MySQLi)
// ============================================
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    $conn->set_charset("utf8mb4");
    $conn->query("SET time_zone = '+08:00'");
} catch (mysqli_sql_exception $e) {
    die("
    <div style='font-family:sans-serif;max-width:500px;margin:80px auto;padding:30px;border:2px solid #e74c3c;border-radius:8px;background:#fff5f5;'>
        <h2 style='color:#e74c3c;'>Database Connection Failed</h2>
        <p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>
        <hr>
        <p>Make sure XAMPP is running and the database <code>lms_rmc</code> has been imported.</p>
        <p>See the setup guide for instructions.</p>
    </div>");
}

// Load system settings (fall back to defaults if unavailable)
$settings = [];
try {
    $settingsResult = $conn->query("SELECT setting_key, setting_value FROM settings");
    while ($settingRow = $settingsResult->fetch_assoc()) {
        $settings[$settingRow['setting_key']] = $settingRow['setting_value'];
    }
} catch (mysqli_sql_exception $e) {
    $settings = [];
}

define('FINE_PER_DAY', isset($settings['fine_per_day']) ? (float)$settings['fine_per_day'] : 5.00);
define('LOAN_DAYS', isset($settings['loan_period_days']) ? (int)$settings['loan_period_days'] : 7);
define('MAX_BORROW', isset($settings['max_books_per_student']) ? (int)$settings['max_books_per_student'] : 3);
define('LIBRARY_NAME', !empty($settings['library_name']) ? $settings['library_name'] : 'RMC Library');

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
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

    global $conn;
    $uid = (int)$_SESSION['user_id'];
    $result = $conn->query("SELECT status, role FROM users WHERE id = $uid");
    $row = $result ? $result->fetch_assoc() : null;

    if (!$row || $row['status'] !== 'active') {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
        redirect(SITE_URL . '/index.php?error=disabled');
    }

    $_SESSION['user_role'] = $row['role'];
}

function requireRole($role) {
    requireLogin();
    if ($_SESSION['user_role'] !== $role && $_SESSION['user_role'] !== 'admin') {
        redirect(SITE_URL . '/index.php?error=unauthorized');
    }
}

function sanitize($conn, $data) {
    return $conn->real_escape_string(trim($data));
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

function daysUntilDue($due) {
    return (int)(new DateTime('today'))->diff(new DateTime($due))->format('%r%a');
}

function daysOverdue($due) {
    return max(0, -daysUntilDue($due));
}

function calculateFine($due) {
    return daysOverdue($due) * FINE_PER_DAY;
}

function initial($name) {
    $name = trim((string)$name);
    if ($name === '' || !preg_match('/^./us', $name, $matches)) {
        return '?';
    }
    return strtoupper($matches[0]);
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
