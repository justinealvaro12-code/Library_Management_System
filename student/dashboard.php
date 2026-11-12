<?php
require_once '../includes/config.php';
requireRole('student');

$pageTitle  = 'Student Dashboard';
$activePage = 'dashboard';
$uid = $_SESSION['user_id'];

$activeBorrows  = $conn->query("SELECT COUNT(*) c FROM borrow_records WHERE user_id=$uid AND status='borrowed'")->fetch_assoc()['c'];
$totalBorrowed  = $conn->query("SELECT COUNT(*) c FROM borrow_records WHERE user_id=$uid")->fetch_assoc()['c'];
$overdueCount   = $conn->query("SELECT COUNT(*) c FROM borrow_records WHERE user_id=$uid AND status='borrowed' AND due_date < CURDATE()")->fetch_assoc()['c'];
$maxBooks       = MAX_BORROW;

$currentBooks = $conn->query("
    SELECT br.*, b.title, b.author, b.isbn
    FROM borrow_records br JOIN books b ON br.book_id=b.id
    WHERE br.user_id=$uid AND br.status='borrowed'
    ORDER BY br.due_date ASC
");

require_once '../includes/header.php';
?>

<div class="page-header">
  <h1>My Dashboard</h1>
  <p>Welcome, <?= htmlspecialchars(explode(' ',$_SESSION['user_name'])[0]) ?>! Manage your borrowed books here.</p>
</div>

<div class="stats-grid">
  <div class="stat-card" style="--accent:#2980b9">
    <div class="stat-icon">📖</div>
    <div class="stat-value"><?= $activeBorrows ?> / <?= $maxBooks ?></div>
    <div class="stat-label">Books Borrowed</div>
  </div>
  <div class="stat-card" style="--accent:#27ae60">
    <div class="stat-icon">📚</div>
    <div class="stat-value"><?= $totalBorrowed ?></div>
    <div class="stat-label">Total Borrowed (All Time)</div>
  </div>
  <div class="stat-card" style="--accent:#e74c3c">
    <div class="stat-icon">⚠️</div>
    <div class="stat-value"><?= $overdueCount ?></div>
    <div class="stat-label">Overdue Books</div>
  </div>
</div>

<?php if ($overdueCount > 0): ?>
<div class="alert alert-danger">
  ⚠️ You have <strong><?= $overdueCount ?></strong> overdue book(s). Please return them to the library as soon as possible to avoid additional fines.
</div>
<?php endif; ?>

<div class="card">
  <div class="card-title">Currently Borrowed Books</div>
  <?php if ((int)$activeBorrows === 0): ?>
    <p style="color:#888;font-size:14px">You haven't borrowed any books yet. <a href="<?= SITE_URL ?>/student/search.php" style="color:#c9a84c">Search the catalog →</a></p>
  <?php else: ?>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Book Title</th><th>Author</th><th>Issue Date</th><th>Due Date</th><th>Status</th><th>Fine</th></tr></thead>
      <tbody>
      <?php while ($r = $currentBooks->fetch_assoc()):
        $daysLeft = daysUntilDue($r['due_date']);
        $isOD     = $daysLeft < 0;
        $fine     = calculateFine($r['due_date']);
      ?>
        <tr>
          <td><strong><?= htmlspecialchars($r['title']) ?></strong></td>
          <td style="font-size:13px;color:#666"><?= htmlspecialchars($r['author']) ?></td>
          <td style="font-size:12px"><?= $r['issue_date'] ?></td>
          <td style="color:<?= $isOD?'#e74c3c':($daysLeft<=2?'#e67e22':'inherit') ?>;font-weight:<?= $isOD?'600':'400' ?>">
            <?= $r['due_date'] ?>
            <?php if (!$isOD && $daysLeft <= 2): ?>
              <br><span style="font-size:11px" class="badge badge-warning">Due soon!</span>
            <?php endif; ?>
          </td>
          <td>
            <?php if ($isOD): ?>
              <span class="badge badge-danger">Overdue</span>
            <?php else: ?>
              <span class="badge badge-info">Borrowed</span>
            <?php endif; ?>
          </td>
          <td>
            <?php if ($fine > 0): ?>
              <span style="color:#e74c3c;font-weight:600">₱<?= number_format($fine,2) ?></span>
            <?php else: ?>
              <span style="color:#27ae60;font-size:12px">None</span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endwhile; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<div style="margin-top:16px">
  <a href="<?= SITE_URL ?>/student/search.php" class="btn btn-gold">🔍 Search Books</a>
  <a href="<?= SITE_URL ?>/student/history.php" class="btn btn-outline" style="margin-left:10px">View Full History</a>
</div>

<?php require_once '../includes/footer.php'; ?>
