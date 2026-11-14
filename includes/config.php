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
// CSRF PROTECTION
// ============================================
function csrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfField() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrfToken()) . '">';
}

// Verify the token on every POST request before any action runs.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!is_string($csrfToken) || !hash_equals(csrfToken(), $csrfToken)) {
        http_response_code(403);
        exit('Invalid or expired security token. Please go back and try again.');
    }
}

// ============================================
// HELPER FUNCTIONS
// ============================================

/**
 * Run a prepared SELECT and return the resulting statement.
 * $params values are bound as-is (never pre-escaped).
 */
function dbSelect($conn, $sql, $types = '', $params = []) {
    $stmt = $conn->prepare($sql);
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    return $stmt->get_result();
}

/**
 * Run a prepared INSERT/UPDATE/DELETE and return the statement.
 */
function dbExecute($conn, $sql, $types = '', $params = []) {
    $stmt = $conn->prepare($sql);
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    return $stmt;
}

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
    $result = dbSelect($conn, "SELECT status, role FROM users WHERE id = ?", 'i', [$uid]);
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

function getUser($conn, $id) {
    $result = dbSelect($conn, "SELECT * FROM users WHERE id = ?", 'i', [(int)$id]);
    return $result ? $result->fetch_assoc() : null;
}

function logActivity($conn, $user_id, $action, $details = '') {
    dbExecute(
        $conn,
        "INSERT INTO activity_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)",
        'isss',
        [(int)$user_id, (string)$action, (string)$details, $_SERVER['REMOTE_ADDR'] ?? '']
    );
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
