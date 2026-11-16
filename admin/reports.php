<?php
require_once '../includes/config.php';
requireRole('admin');

$pageTitle  = 'Reports';
$activePage = 'reports';

// Stats for reports
$totalBooks     = $conn->query("SELECT COUNT(*) c, COALESCE(SUM(total_copies),0) t FROM books")->fetch_assoc();
$borrowStats    = $conn->query("SELECT status, COUNT(*) c FROM borrow_records GROUP BY status")->fetch_all(MYSQLI_ASSOC);
$topBooks       = $conn->query("SELECT b.id, b.title, b.author, COUNT(*) times FROM borrow_records br JOIN books b ON br.book_id=b.id GROUP BY b.id, b.title, b.author ORDER BY times DESC LIMIT 5");
$topStudents    = $conn->query("SELECT u.id, u.full_name, u.student_id, COUNT(*) times FROM borrow_records br JOIN users u ON br.user_id=u.id GROUP BY u.id, u.full_name, u.student_id ORDER BY times DESC LIMIT 5");
$overdueList    = $conn->query("SELECT br.*, u.full_name, u.student_id, b.title FROM borrow_records br JOIN users u ON br.user_id=u.id JOIN books b ON br.book_id=b.id WHERE br.status='borrowed' AND br.due_date < CURDATE() ORDER BY br.due_date ASC");
$monthly        = $conn->query("SELECT DATE_FORMAT(issue_date,'%Y-%m') ym, COUNT(*) c FROM borrow_records GROUP BY ym ORDER BY ym DESC LIMIT 6");
$fineStats      = $conn->query("SELECT COALESCE(SUM(CASE WHEN status='returned' AND fine_amount > 0 AND fine_paid = 'no' THEN fine_amount ELSE 0 END),0) uncollected, COALESCE(SUM(CASE WHEN status='returned' AND fine_amount > 0 AND fine_paid = 'yes' THEN fine_amount ELSE 0 END),0) collected FROM borrow_records")->fetch_assoc();

$borrowMap = [];
foreach ($borrowStats as $s) $borrowMap[$s['status']] = $s['c'];

require_once '../includes/header.php';
?>

<div class="page-header">
  <h1>Reports</h1>
  <p>Library analytics and summary reports.</p>
</div>

<div class="stats-grid" style="margin-bottom:24px">
  <div class="stat-card" style="--accent:#c9a84c">
    <div class="stat-icon">📚</div>
    <div class="stat-value"><?= $totalBooks['c'] ?></div>
    <div class="stat-label">Unique Titles</div>
  </div>
  <div class="stat-card" style="--accent:#2980b9">
    <div class="stat-icon">📦</div>
    <div class="stat-value"><?= $totalBooks['t'] ?></div>
    <div class="stat-label">Total Copies</div>
  </div>
  <div class="stat-card" style="--accent:#27ae60">
    <div class="stat-icon">↩️</div>
    <div class="stat-value"><?= $borrowMap['returned'] ?? 0 ?></div>
    <div class="stat-label">Total Returned</div>
  </div>
  <div class="stat-card" style="--accent:#e74c3c">
    <div class="stat-icon">⏰</div>
    <div class="stat-value"><?= $conn->query("SELECT COUNT(*) c FROM borrow_records WHERE status='borrowed' AND due_date < CURDATE()")->fetch_assoc()['c'] ?></div>
    <div class="stat-label">Currently Overdue</div>
  </div>
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
      <thead><tr><th>Title</th><th>Author</th><th>Times Borrowed</th></tr></thead>
      <tbody>
      <?php while ($b = $topBooks->fetch_assoc()): ?>
        <tr>
          <td><?= htmlspecialchars(substr($b['title'],0,30)) ?></td>
          <td style="font-size:12px;color:#888"><?= htmlspecialchars($b['author']) ?></td>
          <td><strong><?= $b['times'] ?></strong></td>
        </tr>
      <?php endwhile; ?>
      </tbody>
    </table>
  </div>

  <div class="card">
    <div class="card-title">Most Active Students</div>
    <table>
      <thead><tr><th>Student</th><th>ID</th><th>Total Borrows</th></tr></thead>
      <tbody>
      <?php while ($s = $topStudents->fetch_assoc()): ?>
        <tr>
          <td><?= htmlspecialchars($s['full_name']) ?></td>
          <td style="font-size:12px;color:#888"><?= htmlspecialchars($s['student_id'] ?? '—') ?></td>
          <td><strong><?= $s['times'] ?></strong></td>
        </tr>
      <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">
  <div class="card">
    <div class="card-title">Monthly Borrows</div>
    <table>
      <thead><tr><th>Month</th><th>Books Borrowed</th></tr></thead>
      <tbody>
      <?php while ($m = $monthly->fetch_assoc()): ?>
        <tr>
          <td><?= date('M Y', strtotime($m['ym'] . '-01')) ?></td>
          <td>
            <div style="display:flex;align-items:center;gap:8px">
              <div style="height:8px;background:#c9a84c;border-radius:4px;width:<?= min($m['c']*10,120) ?>px"></div>
              <strong><?= $m['c'] ?></strong>
            </div>
          </td>
        </tr>
      <?php endwhile; ?>
      </tbody>
    </table>
  </div>

  <div class="card">
    <div class="card-title">Overdue Books</div>
    <?php $overdue = $overdueList->fetch_all(MYSQLI_ASSOC); ?>
    <?php if (empty($overdue)): ?>
      <p style="color:#888;font-size:14px">No overdue books. 🎉</p>
    <?php else: ?>
    <table>
      <thead><tr><th>Student</th><th>Book</th><th>Days Overdue</th><th>Fine</th></tr></thead>
      <tbody>
      <?php foreach ($overdue as $o):
        $days = daysOverdue($o['due_date']);
        $fine = calculateFine($o['due_date']);
      ?>
        <tr>
          <td style="font-size:12px"><?= htmlspecialchars($o['full_name']) ?></td>
          <td style="font-size:12px"><?= htmlspecialchars(substr($o['title'],0,25)) ?></td>
          <td><span class="badge badge-danger"><?= $days ?>d</span></td>
          <td style="color:#e74c3c;font-weight:600">₱<?= number_format($fine,2) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
</div>

<?php require_once '../includes/footer.php'; ?>
