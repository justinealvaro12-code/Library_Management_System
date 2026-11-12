<?php
require_once '../includes/config.php';
requireRole('admin');

$pageTitle  = 'Transactions';
$activePage = 'transactions';

$filter = sanitize($conn, $_GET['filter'] ?? '');
$where  = '';
if ($filter === 'overdue') {
    $where = "WHERE br.status='borrowed' AND br.due_date < CURDATE()";
} elseif ($filter === 'borrowed') {
    $where = "WHERE br.status='borrowed'";
} elseif ($filter === 'returned') {
    $where = "WHERE br.status='returned'";
}

$records = $conn->query("
    SELECT br.*, u.full_name, u.student_id, b.title, b.author
    FROM borrow_records br
    JOIN users u ON br.user_id = u.id
    JOIN books b ON br.book_id = b.id
    $where
    ORDER BY br.created_at DESC
    LIMIT 100
");

require_once '../includes/header.php';
?>

<div class="page-header">
  <h1>Transactions</h1>
  <p>View all borrowing and return records.</p>
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
      <thead>
        <tr>
          <th>#</th>
          <th>Student</th>
          <th>Book</th>
          <th>Issue Date</th>
          <th>Due Date</th>
          <th>Return Date</th>
          <th>Fine</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
      <?php while ($r = $records->fetch_assoc()):
        $isOverdue = $r['status'] === 'borrowed' && $r['due_date'] < date('Y-m-d');
        $fine = $isOverdue ? calculateFine($r['due_date']) : $r['fine_amount'];
        $status = $isOverdue ? 'overdue' : $r['status'];
        $statusClass = match($status) {
          'returned' => 'badge-success',
          'overdue'  => 'badge-danger',
          default    => 'badge-info'
        };
      ?>
        <tr>
          <td style="color:#aaa;font-size:12px"><?= $r['id'] ?></td>
          <td>
            <strong><?= htmlspecialchars($r['full_name']) ?></strong><br>
            <span style="font-size:11px;color:#999"><?= htmlspecialchars($r['student_id'] ?? '') ?></span>
          </td>
          <td>
            <?= htmlspecialchars(substr($r['title'],0,35)) ?><?= strlen($r['title'])>35?'...':'' ?><br>
            <span style="font-size:11px;color:#aaa"><?= htmlspecialchars($r['author']) ?></span>
          </td>
          <td style="font-size:13px"><?= $r['issue_date'] ?></td>
          <td style="font-size:13px;color:<?= $isOverdue?'#e74c3c':'inherit' ?>"><?= $r['due_date'] ?></td>
          <td style="font-size:13px;color:#888"><?= $r['return_date'] ?? '—' ?></td>
          <td>
            <?php if ($fine > 0): ?>
              <span style="color:#e74c3c;font-weight:600">₱<?= number_format($fine, 2) ?></span>
            <?php else: ?>
              <span style="color:#aaa">—</span>
            <?php endif; ?>
          </td>
          <td><span class="badge <?= $statusClass ?>"><?= ucfirst($status) ?></span></td>
        </tr>
      <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once '../includes/footer.php'; ?>
