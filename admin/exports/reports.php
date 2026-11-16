<?php
require_once '../../includes/config.php';
require_once '../../includes/export.php';
requireRole('admin');

logActivity($conn, $_SESSION['user_id'], 'export_csv', 'Exported reports CSV');
$out = csvStart('reports_' . date('Y-m-d') . '.csv', ['Section','Label','Value1','Value2']);
$statsRes = $conn->query("SELECT COUNT(*) c, COALESCE(SUM(total_copies),0) t FROM books");
$stats = $statsRes->fetch_assoc();
$statsRes->free();
csvRow($out, ['summary','unique_titles',$stats['c'] ?? 0,'']);
csvRow($out, ['summary','total_copies',$stats['t'] ?? 0,'']);
$fineRes = $conn->query("SELECT COALESCE(SUM(CASE WHEN status='returned' AND fine_amount > 0 AND fine_paid = 'no' THEN fine_amount ELSE 0 END),0) uncollected, COALESCE(SUM(CASE WHEN status='returned' AND fine_amount > 0 AND fine_paid = 'yes' THEN fine_amount ELSE 0 END),0) collected FROM borrow_records");
$fineStats = $fineRes->fetch_assoc();
$fineRes->free();
csvRow($out, ['summary','uncollected_fines',number_format((float)($fineStats['uncollected']??0),2,'.',''),'']);
csvRow($out, ['summary','collected_fines',number_format((float)($fineStats['collected']??0),2,'.',''),'']);
$res = $conn->query("SELECT b.title,b.author,COUNT(*) times FROM borrow_records br JOIN books b ON br.book_id=b.id GROUP BY b.id,b.title,b.author ORDER BY times DESC LIMIT 10", MYSQLI_USE_RESULT);
while ($r = $res->fetch_assoc()) { csvRow($out, ['top_books',$r['title'],$r['author'],$r['times']]); }
$res->free();
$res = $conn->query("SELECT br.id,u.full_name,u.student_id,b.title,br.due_date,br.fine_amount FROM borrow_records br JOIN users u ON br.user_id=u.id JOIN books b ON br.book_id=b.id WHERE br.status='borrowed' AND br.due_date < CURDATE() ORDER BY br.due_date ASC", MYSQLI_USE_RESULT);
while ($r = $res->fetch_assoc()) { csvRow($out, ['overdue',$r['full_name'],$r['title'],$r['due_date']]); }
$res->free();
fclose($out);
exit();
?>
