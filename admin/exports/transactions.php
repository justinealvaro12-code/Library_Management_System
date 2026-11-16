<?php
require_once '../../includes/config.php';
require_once '../../includes/export.php';
requireRole('admin');

$filters = exportAllowedFilters(['filter']);
$filter = isset($filters['filter']) ? trim((string)$filters['filter']) : '';
if (!in_array($filter, ['','overdue','borrowed','returned'], true)) { $filter = ''; }
$where = '';
if ($filter === 'overdue') { $where = "WHERE br.status='borrowed' AND br.due_date < CURDATE()"; }
elseif ($filter === 'borrowed') { $where = "WHERE br.status='borrowed'"; }
elseif ($filter === 'returned') { $where = "WHERE br.status='returned'"; }

logActivity($conn, $_SESSION['user_id'], 'export_csv', 'Exported transactions CSV' . ($filter ? " (filter=$filter)" : ''));
$out = csvStart('transactions_' . date('Y-m-d') . '.csv', ['ID','Student','Student ID','Book','Author','Issue Date','Due Date','Return Date','Fine Amount','Fine Paid','Status']);
$sql = "SELECT br.id,u.full_name,u.student_id,b.title,b.author,br.issue_date,br.due_date,br.return_date,br.fine_amount,br.fine_paid,br.status
        FROM borrow_records br JOIN users u ON br.user_id=u.id JOIN books b ON br.book_id=b.id $where ORDER BY br.created_at DESC";
$res = $conn->query($sql, MYSQLI_USE_RESULT);
while ($r = $res->fetch_assoc()) {
    csvRow($out, [$r['id'],$r['full_name'],$r['student_id'],$r['title'],$r['author'],$r['issue_date'],$r['due_date'],$r['return_date'],$r['fine_amount'],$r['fine_paid'],$r['status']]);
}
$res->free();
fclose($out);
exit();
?>
