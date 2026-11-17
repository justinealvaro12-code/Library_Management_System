<?php
require_once '../../includes/config.php';
require_once '../../includes/export.php';
require_once '../../includes/overdue_queue.php';
requireRole('librarian');
$rows = overdueRows($conn);
logActivity($conn, (int)$_SESSION['user_id'], 'export_overdue', 'Exported overdue loans CSV');
$out = csvStart('librarian_overdue_' . date('Y-m-d') . '.csv', ['Student Name', 'Student ID', 'Book', 'Due Date', 'Days Overdue', 'Computed Fine']);
while ($row = $rows->fetch_assoc()) {
    $details = overdueDetails($row);
    csvRow($out, [$row['full_name'], $row['student_id'], $row['title'], $row['due_date'], $details['days'], number_format($details['fine'], 2, '.', '')]);
}
fclose($out);
exit;
?>
