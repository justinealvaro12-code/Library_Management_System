<?php
require_once '../includes/config.php';
requireRole('admin');

$pageTitle  = 'Admin Dashboard';
$activePage = 'dashboard';

// Stats
$totalBooks     = $conn->query("SELECT COUNT(*) c FROM books")->fetch_assoc()['c'];
$totalUsers     = $conn->query("SELECT COUNT(*) c FROM users WHERE role='student'")->fetch_assoc()['c'];
$activeBorrows  = $conn->query("SELECT COUNT(*) c FROM borrow_records WHERE status='borrowed'")->fetch_assoc()['c'];
$overdue        = $conn->query("SELECT COUNT(*) c FROM borrow_records WHERE status='borrowed' AND due_date < CURDATE()")->fetch_assoc()['c'];
$returnedToday  = $conn->query("SELECT COUNT(*) c FROM borrow_records WHERE DATE(return_date)=CURDATE()")->fetch_assoc()['c'];
$issuedToday    = $conn->query("SELECT COUNT(*) c FROM borrow_records WHERE DATE(issue_date)=CURDATE()")->fetch_assoc()['c'];

// Recent transactions
$recent = $conn->query("
    SELECT br.*, u.full_name, u.student_id, b.title
    FROM borrow_records br
    JOIN users u ON br.user_id = u.id
    JOIN books b ON br.book_id = b.id
    ORDER BY br.created_at DESC LIMIT 8
");

require_once '../includes/header.php';
?>

<div class="page-header">
  <h1>Dashboard</h1>
  <p>Welcome back, <?= htmlspecialchars($_SESSION['user_name']) ?>. Here's what's happening today.</p>
</div>

<div class="stats-grid">
  <div class="stat-card" style="--accent:#c9a84c">
    <div class="stat-icon">📚</div>
    <div class="stat-value"><?= $totalBooks ?></div>
    <div class="stat-label">Total Books</div>
  </div>
  <div class="stat-card" style="--accent:#2980b9">
    <div class="stat-icon">👥</div>
    <div class="stat-value"><?= $totalUsers ?></div>
    <div class="stat-label">Students</div>
  </div>
  <div class="stat-card" style="--accent:#27ae60">
    <div class="stat-icon">📖</div>
    <div class="stat-value"><?= $activeBorrows ?></div>
    <div class="stat-label">Active Borrows</div>
  </div>
  <div class="stat-card" style="--accent:#e74c3c">
    <div class="stat-icon">⚠️</div>
    <div class="stat-value"><?= $overdue ?></div>
    <div class="stat-label">Overdue</div>
  </div>
  <div class="stat-card" style="--accent:#9b59b6">
    <div class="stat-icon">↩️</div>
    <div class="stat-value"><?= $returnedToday ?></div>
    <div class="stat-label">Returned Today</div>
  </div>
  <div class="stat-card" style="--accent:#e67e22">
    <div class="stat-icon">➕</div>
    <div class="stat-value"><?= $issuedToday ?></div>
    <div class="stat-label">Issued Today</div>
  </div>
</div>

<div style="display:grid;grid-template-columns:2fr 1fr;gap:20px;">
  <div class="card">
    <div class="card-title">Recent Transactions</div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Student</th><th>Book</th><th>Due Date</th><th>Status</th></tr></thead>
        <tbody>
        <?php while ($row = $recent->fetch_assoc()): ?>
          <?php
            $statusClass = match($row['status']) {
              'returned' => 'badge-success',
              'overdue'  => 'badge-danger',
              default    => 'badge-info'
            };
            $isOD = $row['status'] === 'borrowed' && $row['due_date'] < date('Y-m-d');
            if ($isOD) { $statusClass = 'badge-danger'; $row['status'] = 'overdue'; }
          ?>
          <tr>
            <td>
              <strong><?= htmlspecialchars($row['full_name']) ?></strong><br>
              <span style="font-size:11px;color:#999"><?= htmlspecialchars($row['student_id'] ?? '') ?></span>
            </td>
            <td style="max-width:200px"><?= htmlspecialchars(substr($row['title'],0,40)) ?><?= strlen($row['title'])>40?'...':'' ?></td>
            <td><?= $row['due_date'] ?></td>
            <td><span class="badge <?= $statusClass ?>"><?= ucfirst($row['status']) ?></span></td>
          </tr>
        <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="card">
    <div class="card-title">Quick Actions</div>
    <div style="display:flex;flex-direction:column;gap:10px;">
      <a href="<?= SITE_URL ?>/admin/users.php?action=add" class="btn btn-primary">👤 Add New User</a>
      <a href="<?= SITE_URL ?>/admin/books.php?action=add" class="btn btn-gold">📗 Add New Book</a>
      <a href="<?= SITE_URL ?>/admin/reports.php" class="btn btn-outline">📊 View Reports</a>
      <a href="<?= SITE_URL ?>/admin/transactions.php?filter=overdue" class="btn btn-outline" style="color:#e74c3c;border-color:#e74c3c">⚠️ View Overdue</a>
    </div>
  </div>
</div>

<?php require_once '../includes/footer.php'; ?>
