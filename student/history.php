<?php
require_once '../includes/config.php';
require_once '../includes/pagination.php';
requireRole('student');

$pageTitle  = 'Borrowing History';
$activePage = 'history';
$uid = (int)$_SESSION['user_id'];

$searchRaw = trim($_GET['q'] ?? '');
$statusRaw = trim($_GET['status'] ?? '');
if (!in_array($statusRaw, ['', 'borrowed', 'returned', 'overdue'], true)) {
    $statusRaw = '';
}

$where = "WHERE br.user_id = ?";
$types = 'i';
$params = [$uid];

if ($searchRaw !== '') {
    $like = '%' . $searchRaw . '%';
    $where .= " AND (b.title LIKE ? OR b.author LIKE ?)";
    $types .= 'ss';
    $params[] = $like;
    $params[] = $like;
}
if ($statusRaw === 'returned') {
    $where .= " AND br.status = 'returned'";
} elseif ($statusRaw === 'borrowed') {
    $where .= " AND br.status = 'borrowed'";
} elseif ($statusRaw === 'overdue') {
    $where .= " AND br.status = 'borrowed' AND br.due_date < CURDATE()";
}

$countRes = dbSelect($conn, "SELECT COUNT(*) c FROM borrow_records br JOIN books b ON br.book_id=b.id $where", $types, $params);
$total = (int)($countRes ? $countRes->fetch_assoc()['c'] : 0);
$pager = paginate($total, 15);

$typesP  = $types . 'ii';
$paramsP = array_merge($params, [$pager['per_page'], $pager['offset']]);
$history = dbSelect($conn, "
    SELECT br.*, b.title, b.author, b.category
    FROM borrow_records br JOIN books b ON br.book_id=b.id
    $where
    ORDER BY br.created_at DESC
    LIMIT ? OFFSET ?
", $typesP, $paramsP);

$totalBorrows = (int)dbSelect($conn, "SELECT COUNT(*) c FROM borrow_records WHERE user_id = ?", 'i', [$uid])->fetch_assoc()['c'];
$totalFines   = (float)dbSelect($conn, "SELECT COALESCE(SUM(fine_amount),0) s FROM borrow_records WHERE user_id = ?", 'i', [$uid])->fetch_assoc()['s'];
$unpaidBalance = (float)dbSelect($conn, "SELECT COALESCE(SUM(fine_amount),0) s FROM borrow_records WHERE user_id = ? AND status = 'returned' AND fine_amount > 0 AND fine_paid = 'no'", 'i', [$uid])->fetch_assoc()['s'];

require_once '../includes/header.php';
?>

<div class="page-header">
  <h1>Borrowing History</h1>
  <p>Your complete borrowing record.</p>
</div>

<div class="stats-grid" style="margin-bottom:20px">
  <div class="stat-card" style="--accent:#2980b9">
    <div class="stat-icon">📖</div>
    <div class="stat-value"><?= $totalBorrows ?></div>
    <div class="stat-label">Total Borrows</div>
  </div>
  <div class="stat-card" style="--accent:#e74c3c">
    <div class="stat-icon">💸</div>
    <div class="stat-value">₱<?= number_format($totalFines, 2) ?></div>
    <div class="stat-label">Total Fines Incurred</div>
  </div>
  <div class="stat-card" style="--accent:#e67e22">
    <div class="stat-icon">⚠️</div>
    <div class="stat-value">₱<?= number_format($unpaidBalance, 2) ?></div>
    <div class="stat-label">Unpaid Fine Balance</div>
  </div>
</div>

<div class="card" style="margin-bottom:20px">
  <form method="GET" style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap">
    <div class="form-group" style="flex:1;min-width:220px;margin:0">
      <label>Search</label>
      <input type="text" name="q" class="form-control" placeholder="Book title or author…" value="<?= htmlspecialchars($searchRaw) ?>">
    </div>
    <div class="form-group" style="min-width:160px;margin:0">
      <label>Status</label>
      <select name="status" class="form-control">
        <option value="">All</option>
        <option value="borrowed" <?= $statusRaw==='borrowed'?'selected':'' ?>>Borrowed</option>
        <option value="returned" <?= $statusRaw==='returned'?'selected':'' ?>>Returned</option>
        <option value="overdue" <?= $statusRaw==='overdue'?'selected':'' ?>>Overdue</option>
      </select>
    </div>
    <button type="submit" class="btn btn-primary">Filter</button>
    <?php if ($searchRaw !== '' || $statusRaw !== ''): ?>
      <a href="history.php" class="btn btn-outline">Clear</a>
    <?php endif; ?>
  </form>
</div>

<div class="card">
  <?php if ($total === 0): ?>
    <p style="color:#888;text-align:center;padding:30px">No records found.</p>
  <?php else: ?>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Book</th><th>Issue Date</th><th>Due Date</th><th>Return Date</th><th>Fine</th><th>Payment</th><th>Status</th></tr></thead>
      <tbody>
      <?php while ($r = $history->fetch_assoc()):
        // 'overdue' is computed at render time; the stored status stays 'borrowed'.
        $status = ($r['status'] === 'borrowed' && $r['due_date'] < date('Y-m-d')) ? 'overdue' : $r['status'];
        $sc = match($status){ 'returned'=>'badge-success','overdue'=>'badge-danger',default=>'badge-info'};
        $fine = $r['status'] === 'borrowed' ? calculateFine($r['due_date']) : (float)$r['fine_amount'];
        $isUnpaid = $r['status'] === 'returned' && (float)$r['fine_amount'] > 0 && ($r['fine_paid'] ?? 'no') === 'no';
        $isPaid = $r['status'] === 'returned' && (float)$r['fine_amount'] > 0 && ($r['fine_paid'] ?? 'no') === 'yes';
      ?>
        <tr>
          <td>
            <strong><?= htmlspecialchars($r['title']) ?></strong><br>
            <span style="font-size:12px;color:#888"><?= htmlspecialchars($r['author']) ?></span>
          </td>
          <td style="font-size:12px"><?= $r['issue_date'] ?></td>
          <td style="font-size:12px"><?= $r['due_date'] ?></td>
          <td style="font-size:12px;color:#888"><?= $r['return_date'] ?? '—' ?></td>
          <td><?= $fine > 0 ? '<span style="color:#e74c3c">₱'.number_format($fine,2).'</span>' : '<span style="color:#aaa">—</span>' ?></td>
          <td>
            <?php if ($isPaid): ?>
              <span class="badge badge-success">Paid</span>
            <?php elseif ($isUnpaid): ?>
              <span class="badge badge-danger">Unpaid</span>
            <?php else: ?>
              <span style="color:#aaa">—</span>
            <?php endif; ?>
          </td>
          <td><span class="badge <?= $sc ?>"><?= ucfirst($status) ?></span></td>
        </tr>
      <?php endwhile; ?>
      </tbody>
    </table>
  </div>
  <?= renderPagination($pager) ?>
  <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>
