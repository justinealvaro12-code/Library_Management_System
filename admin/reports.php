<?php
require_once '../includes/config.php';
require_once '../includes/report_data.php';
requireRole('admin');

$pageTitle  = 'Reports';
$activePage = 'reports';

$range  = reportRange($_GET);
$report = reportData($conn, $range['from'], $range['to']);
$exportBase = SITE_URL . '/admin/exports/reports.php';

require_once '../includes/header.php';
require_once '../includes/report_dashboard.php';
require_once '../includes/footer.php';
