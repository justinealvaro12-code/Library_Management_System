<?php
require_once 'includes/config.php';
if (isLoggedIn()) {
    logActivity($conn, $_SESSION['user_id'], 'logout', 'User logged out');
    session_destroy();
}
redirect(SITE_URL . '/index.php');
?>
