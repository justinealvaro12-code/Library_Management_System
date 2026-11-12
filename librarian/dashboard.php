<?php
require_once '../includes/config.php';
requireRole('librarian');

$pageTitle  = 'Librarian Dashboard';
$activePage = 'dashboard';

$activeBorrows = $conn->query("SELECT COUNT(*) c FROM borrow_records WHERE status='borrowed'")->fetch_assoc()['c'];
$overdue       = $conn->query("SELECT COUNT(*) c FROM borrow_records WHERE status='borrowed' AND due_date < CURDATE()")->fetch_assoc()['c'];
$issuedToday   = $conn->query("SELECT COUNT(*) c FROM borrow_records WHERE DATE(issue_date)=CURDATE()")->fetch_assoc()['c'];
$returnedToday = $conn->query("SELECT COUNT(*) c FROM borrow_records WHERE DATE(return_date)=CURDATE()")->fetch_assoc()['c'];

$recent = $conn->query("
    SELECT br.*, u.full_name, u.student_id, b.title
    FROM borrow_records br
    JOIN users u ON br.user_id = u.id
    JOIN books b ON br.book_id = b.id
    ORDER BY br.created_at DESC LIMIT 10
");

require_once '../includes/header.php';
?>

<div class="page-header">
  <h1>Librarian Dashboard</h1>
  <p>Good day, <?= htmlspecialchars(explode(' ',$_SESSION['user_name'])[0]) ?>! Here's today's library status.</p>
</div>

<div class="stats-grid">
  <div class="stat-card" style="--accent:#2980b9">
    <div class="stat-icon">📖</div>
    <div class="stat-value"><?= $activeBorrows ?></div>
    <div class="stat-label">Active Borrows</div>
  </div>
  <div class="stat-card" style="--accent:#e74c3c">
    <div class="stat-icon">⚠️</div>
    <div class="stat-value"><?= $overdue ?></div>
    <div class="stat-label">Overdue</div>
  </div>
  <div class="stat-card" style="--accent:#27ae60">
    <div class="stat-icon">➕</div>
    <div class="stat-value"><?= $issuedToday ?></div>
    <div class="stat-label">Issued Today</div>
  </div>
  <div class="stat-card" style="--accent:#9b59b6">
    <div class="stat-icon">↩️</div>
    <div class="stat-value"><?= $returnedToday ?></div>
    <div class="stat-label">Returned Today</div>
  </div>
</div>

<div style="display:grid;grid-template-columns:2fr 1fr;gap:20px">
  <div class="card">
    <div class="card-title">Recent Activity</div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Student</th><th>Book</th><th>Due</th><th>Status</th></tr></thead>
        <tbody>
        <?php while ($r = $recent->fetch_assoc()):
          $isOD = $r['status']==='borrowed' && $r['due_date'] < date('Y-m-d');
          $status = $isOD ? 'overdue' : $r['status'];
          $sc = match($status){ 'returned'=>'badge-success','overdue'=>'badge-danger',default=>'badge-info'};
        ?>
          <tr>
            <td><?= htmlspecialchars($r['full_name']) ?><br><span style="font-size:11px;color:#aaa"><?= htmlspecialchars($r['student_id'] ?? '') ?></span></td>
            <td style="font-size:13px"><?= htmlspecialchars(substr($r['title'],0,35)) ?></td>
            <td style="font-size:12px"><?= $r['due_date'] ?></td>
            <td><span class="badge <?= $sc ?>"><?= ucfirst($status) ?></span></td>
          </tr>
        <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="card">
    <div class="card-title">Quick Actions</div>
    <div style="display:flex;flex-direction:column;gap:10px">
      <a href="<?= SITE_URL ?>/librarian/issue_book.php" class="btn btn-primary">➕ Issue a Book</a>
      <a href="<?= SITE_URL ?>/librarian/return_book.php" class="btn btn-gold">↩️ Process Return</a>
      <a href="<?= SITE_URL ?>/librarian/books.php" class="btn btn-outline">📚 View Catalog</a>
      <a href="<?= SITE_URL ?>/librarian/transactions.php?filter=overdue" class="btn btn-outline" style="color:#e74c3c;border-color:#e74c3c">⚠️ Overdue List</a>
    </div>
  </div>
</div>

<?php require_once '../includes/footer.php'; ?>
