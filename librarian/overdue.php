<?php
require_once '../includes/config.php';
require_once '../includes/pagination.php';
require_once '../includes/overdue_queue.php';
require_once '../includes/reservations.php';
requireRole('librarian');

$pageTitle = 'Overdue Loans';
$activePage = 'overdue';
$totalRecords = overdueCount($conn);
$pager = paginate($totalRecords, 20);
$records = overdueRows($conn, $pager['per_page'], $pager['offset']);
require_once '../includes/header.php';
?>
<div class="page-header"><h1>Overdue Loans</h1><p>Computed fines continue to accrue until each book is returned.</p></div>
<div style="margin-bottom:16px"><a class="btn btn-outline" href="exports/overdue.php">Export CSV</a></div>
<?php require '../includes/overdue_table.php'; ?>
<?php require_once '../includes/footer.php'; ?>
