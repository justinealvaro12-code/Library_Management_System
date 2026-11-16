<?php
require_once '../includes/config.php';
require_once '../includes/pagination.php';
requireRole('librarian');

$pageTitle  = 'Transactions';
$activePage = 'transactions';
$msg = '';
$msgType = 'success';

$isFinePost = $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'set_fine_paid';
if ($isFinePost && ($_SESSION['user_role'] ?? '') !== 'librarian') {
    $msg = 'Only librarians can update fine payments.'; $msgType = 'danger';
} elseif ($isFinePost) {
    $borrow_id = (int)($_POST['borrow_id'] ?? 0);
    $paid = ($_POST['paid'] ?? '') === 'yes' ? 'yes' : (($_POST['paid'] ?? '') === 'no' ? 'no' : '');
    if ($borrow_id <= 0 || $paid === '') {
        $msg = 'Invalid request.'; $msgType = 'danger';
    } else {
        $rec = dbSelect($conn, "SELECT id, status, fine_amount, fine_paid, user_id FROM borrow_records WHERE id = ?", 'i', [$borrow_id]);
        $row = $rec ? $rec->fetch_assoc() : null;
        if (!$row) {
            $msg = 'Record not found.'; $msgType = 'danger';
        } elseif ($row['status'] !== 'returned') {
            $msg = 'Only returned records can be marked paid/unpaid.'; $msgType = 'danger';
        } elseif ((float)$row['fine_amount'] <= 0) {
            $msg = 'Cannot change payment status when fine is 0.'; $msgType = 'danger';
        } elseif ($row['fine_paid'] === $paid) {
            $msg = 'No change made.';
        } else {
            dbExecute($conn, "UPDATE borrow_records SET fine_paid = ? WHERE id = ? AND status = 'returned' AND fine_amount > 0", 'si', [$paid, $borrow_id]);
            $details = "Borrow #$borrow_id marked fine_paid=$paid for user #{$row['user_id']} by user #{$_SESSION['user_id']} at " . date('Y-m-d H:i:s');
            logActivity($conn, $_SESSION['user_id'], 'fine_payment_update', $details);
            header('Location: transactions.php?' . http_build_query(array_merge($_GET, ['msg' => 'fine_updated'])));
            exit();
        }
    }
}

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

$countRes = $conn->query("SELECT COUNT(*) c FROM borrow_records br $where");
$totalRecords = (int)($countRes ? $countRes->fetch_assoc()['c'] : 0);
$pager = paginate($totalRecords, 20);

$records = $conn->query("
    SELECT br.*, u.full_name, u.student_id, b.title
    FROM borrow_records br
    JOIN users u ON br.user_id=u.id
    JOIN books b ON br.book_id=b.id
    $where
    ORDER BY br.created_at DESC
    LIMIT {$pager['per_page']} OFFSET {$pager['offset']}
");

if ($msg === '' && isset($_GET['msg']) && $_GET['msg'] === 'fine_updated') {
    $msg = 'Fine payment status updated.';
}

require_once '../includes/header.php';
?>

<div class="page-header">
  <h1>Transactions</h1>
  <p>View borrow and return records.</p>
</div>

<?php if ($msg !== ''): ?>
  <div class="alert alert-<?= $msgType === 'danger' ? 'danger' : 'success' ?>"><?= $msgType === 'danger' ? '!' : '✓' ?> <?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<div style="display:flex;gap:10px;margin-bottom:20px;align-items:center;flex-wrap:wrap">
  <a href="transactions.php" class="btn btn-outline <?= !$filter?'btn-primary':'' ?>">All</a>
  <a href="?filter=borrowed" class="btn btn-outline <?= $filter==='borrowed'?'btn-primary':'' ?>">Active</a>
  <a href="?filter=returned" class="btn btn-outline <?= $filter==='returned'?'btn-primary':'' ?>">Returned</a>
  <a href="?filter=overdue" class="btn btn-outline" style="<?= $filter==='overdue'?'background:#e74c3c;color:white;border-color:#e74c3c':'' ?>">⚠ Overdue</a>
  <div style="margin-left:auto"><a class="btn btn-outline" href="<?= SITE_URL ?>/librarian/exports/transactions.php?<?= http_build_query(array_intersect_key($_GET, array_flip(['filter']))) ?>">Export CSV</a></div>
</div>

<div class="card">
  <div class="table-wrap">
    <table>
      <thead><tr><th>#</th><th>Student</th><th>Book</th><th>Issue</th><th>Due</th><th>Return</th><th>Fine</th><th>Status</th><th>Payment</th></tr></thead>
      <tbody>
      <?php if ($totalRecords === 0): ?>
        <tr><td colspan="9" style="text-align:center;color:#888;padding:24px">No results found.</td></tr>
      <?php else: while ($r = $records->fetch_assoc()):
        $isOD = $r['status']==='borrowed' && $r['due_date'] < date('Y-m-d');
        $fine = $isOD ? calculateFine($r['due_date']) : (float)$r['fine_amount'];
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
          <td>
            <?php if ($fine > 0): ?>
              <span style="color:#e74c3c;font-weight:600">₱<?= number_format($fine,2) ?></span>
              <?php if ($r['status']==='returned'): ?>
                <div style="font-size:11px;color:#888"><?= ($r['fine_paid'] ?? 'no') === 'yes' ? 'Paid' : 'Unpaid' ?></div>
              <?php endif; ?>
            <?php else: ?>
              <span style="color:#aaa">—</span>
            <?php endif; ?>
          </td>
          <td><span class="badge <?= $sc ?>"><?= ucfirst($status) ?></span></td>
          <td>
            <?php if (($_SESSION['user_role'] ?? '') === 'librarian' && $r['status']==='returned' && (float)$r['fine_amount'] > 0): ?>
              <form method="POST" style="display:inline">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="set_fine_paid">
                <input type="hidden" name="borrow_id" value="<?= $r['id'] ?>">
                <?php if (($r['fine_paid'] ?? 'no') === 'yes'): ?>
                  <input type="hidden" name="paid" value="no">
                  <button type="submit" class="btn btn-sm btn-outline" onclick="return confirm('Mark this fine as unpaid?')">Mark Unpaid</button>
                <?php else: ?>
                  <input type="hidden" name="paid" value="yes">
                  <button type="submit" class="btn btn-sm btn-primary" onclick="return confirm('Mark this fine as paid?')">Mark Paid</button>
                <?php endif; ?>
              </form>
            <?php else: ?>
              <span style="color:#aaa;font-size:12px">—</span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endwhile; endif; ?>
      </tbody>
    </table>
  </div>
  <?= renderPagination($pager) ?>
</div>

<?php require_once '../includes/footer.php'; ?>
