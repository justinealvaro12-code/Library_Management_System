<?php
require_once '../includes/config.php';
requireRole('librarian');

$pageTitle  = 'Reports';
$activePage = 'reports';

$topBooks    = $conn->query("SELECT b.id, b.title, b.author, COUNT(*) times FROM borrow_records br JOIN books b ON br.book_id=b.id GROUP BY b.id, b.title, b.author ORDER BY times DESC LIMIT 8");
$overdueList = $conn->query("SELECT br.*, u.full_name, u.student_id, b.title FROM borrow_records br JOIN users u ON br.user_id=u.id JOIN books b ON br.book_id=b.id WHERE br.status='borrowed' AND br.due_date < CURDATE() ORDER BY br.due_date ASC");
$monthly     = $conn->query("SELECT DATE_FORMAT(issue_date,'%Y-%m') ym, COUNT(*) c FROM borrow_records GROUP BY ym ORDER BY ym DESC LIMIT 6");
$fineStats   = $conn->query("SELECT COALESCE(SUM(CASE WHEN status='returned' AND fine_amount > 0 AND fine_paid = 'no' THEN fine_amount ELSE 0 END),0) uncollected, COALESCE(SUM(CASE WHEN status='returned' AND fine_amount > 0 AND fine_paid = 'yes' THEN fine_amount ELSE 0 END),0) collected FROM borrow_records")->fetch_assoc();

require_once '../includes/header.php';
?>

<div class="page-header" style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap">
  <div>
    <h1>Reports</h1>
    <p>Library circulation summary.</p>
  </div>
  <div><a class="btn btn-outline" href="<?= SITE_URL ?>/librarian/exports/reports.php">Export CSV</a></div>
</div>

<div class="stats-grid" style="margin-bottom:24px">
  <div class="stat-card" style="--accent:#e67e22">
    <div class="stat-icon">₱</div>
    <div class="stat-value">₱<?= number_format((float)($fineStats['uncollected'] ?? 0), 2) ?></div>
    <div class="stat-label">Uncollected Fines</div>
  </div>
  <div class="stat-card" style="--accent:#16a085">
    <div class="stat-icon">₱</div>
    <div class="stat-value">₱<?= number_format((float)($fineStats['collected'] ?? 0), 2) ?></div>
    <div class="stat-label">Collected Fines</div>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:24px">
  <div class="card">
    <div class="card-title">Most Borrowed Books</div>
    <table>
      <thead><tr><th>Title</th><th>Times</th></tr></thead>
      <tbody>
      <?php while ($b = $topBooks->fetch_assoc()): ?>
        <tr>
          <td><?= htmlspecialchars(substr($b['title'],0,40)) ?><br><span style="font-size:11px;color:#aaa"><?= htmlspecialchars($b['author']) ?></span></td>
          <td><strong><?= $b['times'] ?></strong></td>
        </tr>
      <?php endwhile; ?>
      </tbody>
    </table>
  </div>

  <div class="card">
    <div class="card-title">Monthly Borrows</div>
    <table>
      <thead><tr><th>Month</th><th>Count</th></tr></thead>
      <tbody>
      <?php while ($m = $monthly->fetch_assoc()): ?>
        <tr>
          <td><?= date('M Y', strtotime($m['ym'] . '-01')) ?></td>
          <td>
            <div style="display:flex;align-items:center;gap:8px">
              <div style="height:8px;background:#c9a84c;border-radius:4px;width:<?= min($m['c']*12,120) ?>px"></div>
              <strong><?= $m['c'] ?></strong>
            </div>
          </td>
        </tr>
      <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="card">
  <div class="card-title">Overdue Books</div>
  <?php $od = $overdueList->fetch_all(MYSQLI_ASSOC); ?>
  <?php if (empty($od)): ?>
    <p style="color:#888">No overdue books right now. 🎉</p>
  <?php else: ?>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Student</th><th>ID</th><th>Book</th><th>Due Date</th><th>Days Overdue</th><th>Fine</th></tr></thead>
      <tbody>
      <?php foreach ($od as $o):
        $days = daysOverdue($o['due_date']);
        $fine = calculateFine($o['due_date']);
      ?>
        <tr>
          <td><?= htmlspecialchars($o['full_name']) ?></td>
          <td style="font-size:12px;color:#aaa"><?= htmlspecialchars($o['student_id'] ?? '—') ?></td>
          <td><?= htmlspecialchars(substr($o['title'],0,40)) ?></td>
          <td style="color:#e74c3c"><?= $o['due_date'] ?></td>
          <td><span class="badge badge-danger"><?= $days ?> days</span></td>
          <td style="color:#e74c3c;font-weight:600">₱<?= number_format($fine,2) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>
