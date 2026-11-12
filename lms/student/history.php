<?php
require_once '../includes/config.php';
requireRole('student');

$pageTitle  = 'Borrowing History';
$activePage = 'history';
$uid = $_SESSION['user_id'];

$history = $conn->query("
    SELECT br.*, b.title, b.author, b.category
    FROM borrow_records br JOIN books b ON br.book_id=b.id
    WHERE br.user_id=$uid
    ORDER BY br.created_at DESC
");

$total       = $conn->query("SELECT COUNT(*) c FROM borrow_records WHERE user_id=$uid")->fetch_assoc()['c'];
$totalFines  = $conn->query("SELECT COALESCE(SUM(fine_amount),0) s FROM borrow_records WHERE user_id=$uid")->fetch_assoc()['s'];

require_once '../includes/header.php';
?>

<div class="page-header">
  <h1>Borrowing History</h1>
  <p>Your complete borrowing record.</p>
</div>

<div class="stats-grid" style="margin-bottom:20px">
  <div class="stat-card" style="--accent:#2980b9">
    <div class="stat-icon">📖</div>
    <div class="stat-value"><?= $total ?></div>
    <div class="stat-label">Total Borrows</div>
  </div>
  <div class="stat-card" style="--accent:#e74c3c">
    <div class="stat-icon">💰</div>
    <div class="stat-value">₱<?= number_format($totalFines, 2) ?></div>
    <div class="stat-label">Total Fines Incurred</div>
  </div>
</div>

<div class="card">
  <?php if ($history->num_rows === 0): ?>
    <p style="color:#888;text-align:center;padding:30px">No borrowing history yet.</p>
  <?php else: ?>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Book</th><th>Issue Date</th><th>Due Date</th><th>Return Date</th><th>Fine</th><th>Status</th></tr></thead>
      <tbody>
      <?php while ($r = $history->fetch_assoc()):
        $status = ($r['status'] === 'borrowed' && $r['due_date'] < date('Y-m-d')) ? 'overdue' : $r['status'];
        $sc = match($status){ 'returned'=>'badge-success','overdue'=>'badge-danger',default=>'badge-info'};
      ?>
        <tr>
          <td>
            <strong><?= htmlspecialchars($r['title']) ?></strong><br>
            <span style="font-size:12px;color:#888"><?= htmlspecialchars($r['author']) ?></span>
          </td>
          <td style="font-size:12px"><?= $r['issue_date'] ?></td>
          <td style="font-size:12px"><?= $r['due_date'] ?></td>
          <td style="font-size:12px;color:#888"><?= $r['return_date'] ?? '—' ?></td>
          <td><?= $r['fine_amount'] > 0 ? '<span style="color:#e74c3c">₱'.number_format($r['fine_amount'],2).'</span>' : '<span style="color:#aaa">—</span>' ?></td>
          <td><span class="badge <?= $sc ?>"><?= ucfirst($status) ?></span></td>
        </tr>
      <?php endwhile; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>
