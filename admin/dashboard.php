<?php
require_once '../includes/config.php';
requireRole('admin');
require_once '../includes/reservations.php';

$pageTitle  = 'Admin Dashboard';
$activePage = 'dashboard';

// Stats
$totalBooks     = $conn->query("SELECT COUNT(*) c FROM books")->fetch_assoc()['c'];
$totalUsers     = $conn->query("SELECT COUNT(*) c FROM users WHERE role='student'")->fetch_assoc()['c'];
$activeBorrows  = $conn->query("SELECT COUNT(*) c FROM borrow_records WHERE status='borrowed'")->fetch_assoc()['c'];
$overdue        = $conn->query("SELECT COUNT(*) c FROM borrow_records WHERE status='borrowed' AND due_date < CURDATE()")->fetch_assoc()['c'];
$readyPickup    = $conn->query("SELECT COUNT(*) c FROM reservations WHERE status='ready'")->fetch_assoc()['c'];
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

<div class="dashboard-kpi-grid">
  <div class="kpi-card" style="--accent:#c9a84c"><span class="stat-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H20v16H6.5A2.5 2.5 0 0 0 4 21.5z"/><path d="M4 5.5v16M8 7h8M8 11h8"/></svg></span><div class="stat-value"><?= $totalBooks ?></div><div class="stat-label">Total Books</div></div>
  <div class="kpi-card" style="--accent:#2980b9"><span class="stat-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="9" cy="8" r="3"/><path d="M3 20v-1a6 6 0 0 1 12 0v1zM16 5a3 3 0 0 1 0 6M18 14a5 5 0 0 1 3 4.5V20h-4"/></svg></span><div class="stat-value"><?= $totalUsers ?></div><div class="stat-label">Students</div></div>
  <div class="kpi-card" style="--accent:#2980b9"><span class="stat-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H20v16H6.5A2.5 2.5 0 0 0 4 21.5z"/><path d="M4 5.5v16M8 7h8M8 11h8"/></svg></span><div class="stat-value"><?= $activeBorrows ?></div><div class="stat-label">Active Borrows</div></div>
  <div class="kpi-card" style="--accent:#e74c3c"><span class="stat-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="12" cy="13" r="8"/><path d="M12 9v4l2.5 1.5M9 2h6M12 2v3"/></svg></span><div class="stat-value"><?= $overdue ?></div><div class="stat-label">Overdue</div></div>
  <div class="kpi-card" style="--accent:#d97706"><span class="stat-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M3 10h18v10H3zM3 10l2-6h14l2 6M12 10v7m-3-3 3 3 3-3"/></svg></span><div class="stat-value"><?= (int)$readyPickup ?></div><div class="stat-label">Ready for Pickup</div></div>
  <div class="kpi-card" style="--accent:#9b59b6"><span class="stat-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H20v16H6.5A2.5 2.5 0 0 0 4 21.5z"/><path d="M4 5.5v16M8 7h8M8 11h8M16 14v4m-3-3 3 3 3-3"/></svg></span><div class="stat-value"><?= $returnedToday ?></div><div class="stat-label">Returned Today</div></div>
  <div class="kpi-card" style="--accent:#27ae60"><span class="stat-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H20v16H6.5A2.5 2.5 0 0 0 4 21.5z"/><path d="M4 5.5v16M8 7h8M8 11h8M16 14v4m-3-3 3-3 3 3"/></svg></span><div class="stat-value"><?= $issuedToday ?></div><div class="stat-label">Issued Today</div></div>
</div>

<div class="dashboard-panels">
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
    <div class="dashboard-actions">
      <a href="<?= SITE_URL ?>/admin/users.php?action=add" class="btn btn-primary">👤 Add New User</a>
      <a href="<?= SITE_URL ?>/admin/books.php?action=add" class="btn btn-gold">📗 Add New Book</a>
      <a href="<?= SITE_URL ?>/admin/reports.php" class="btn btn-outline">📊 View Reports</a>
      <a href="<?= SITE_URL ?>/admin/transactions.php?filter=overdue" class="btn btn-outline" style="color:#e74c3c;border-color:#e74c3c">⚠️ View Overdue</a>
    </div>
  </div>
</div>

<?php require_once '../includes/footer.php'; ?>
