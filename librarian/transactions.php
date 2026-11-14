<?php
require_once '../includes/config.php';
requireRole('librarian');

$pageTitle  = 'Transactions';
$activePage = 'transactions';

$filter = trim($_GET['filter'] ?? '');
if (!in_array($filter, ['', 'overdue', 'borrowed', 'returned'], true)) {
    $filter = '';
}
$where  = match($filter) {
    'overdue'  => "WHERE br.status='borrowed' AND br.due_date < CURDATE()",
    'borrowed' => "WHERE br.status='borrowed'",
    'returned' => "WHERE br.status='returned'",
    default    => ''
};

$records = $conn->query("
    SELECT br.*, u.full_name, u.student_id, b.title
    FROM borrow_records br
    JOIN users u ON br.user_id=u.id
    JOIN books b ON br.book_id=b.id
    $where
    ORDER BY br.created_at DESC LIMIT 100
");

require_once '../includes/header.php';
?>

<div class="page-header">
  <h1>Transactions</h1>
  <p>View borrow and return records.</p>
</div>

<div style="display:flex;gap:10px;margin-bottom:20px">
  <a href="transactions.php" class="btn btn-outline <?= !$filter?'btn-primary':'' ?>">All</a>
  <a href="?filter=borrowed" class="btn btn-outline <?= $filter==='borrowed'?'btn-primary':'' ?>">Active</a>
  <a href="?filter=returned" class="btn btn-outline <?= $filter==='returned'?'btn-primary':'' ?>">Returned</a>
  <a href="?filter=overdue" class="btn btn-outline" style="<?= $filter==='overdue'?'background:#e74c3c;color:white;border-color:#e74c3c':'' ?>">⚠️ Overdue</a>
</div>

<div class="card">
  <div class="table-wrap">
    <table>
      <thead><tr><th>#</th><th>Student</th><th>Book</th><th>Issue</th><th>Due</th><th>Return</th><th>Fine</th><th>Status</th></tr></thead>
      <tbody>
      <?php while ($r = $records->fetch_assoc()):
        $isOD = $r['status']==='borrowed' && $r['due_date'] < date('Y-m-d');
        $fine = $isOD ? calculateFine($r['due_date']) : $r['fine_amount'];
        $status = $isOD ? 'overdue' : $r['status'];
        $sc = match($status){ 'returned'=>'badge-success','overdue'=>'badge-danger',default=>'badge-info'};
      ?>
        <tr>
          <td style="color:#aaa;font-size:12px"><?= $r['id'] ?></td>
          <td><?= htmlspecialchars($r['full_name']) ?><br><span style="font-size:11px;color:#aaa"><?= htmlspecialchars($r['student_id'] ?? '') ?></span></td>
          <td style="font-size:13px"><?= htmlspecialchars(substr($r['title'],0,35)) ?></td>
          <td style="font-size:12px"><?= $r['issue_date'] ?></td>
          <td style="font-size:12px;color:<?= $isOD?'#e74c3c':'inherit' ?>"><?= $r['due_date'] ?></td>
          <td style="font-size:12px;color:#888"><?= $r['return_date'] ?? '—' ?></td>
          <td><?= $fine > 0 ? '<span style="color:#e74c3c;font-weight:600">₱'.number_format($fine,2).'</span>' : '<span style="color:#aaa">—</span>' ?></td>
          <td><span class="badge <?= $sc ?>"><?= ucfirst($status) ?></span></td>
        </tr>
      <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once '../includes/footer.php'; ?>
