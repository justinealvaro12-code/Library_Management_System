<?php
require_once '../includes/config.php';
requireRole('librarian');

$pageTitle  = 'Return Book';
$activePage = 'return_book';

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $borrow_id = (int)($_POST['borrow_id'] ?? 0);
    $lib_id    = $_SESSION['user_id'];
    $today     = date('Y-m-d');

    $conn->begin_transaction();
    try {
        $result = dbSelect($conn, "SELECT * FROM borrow_records WHERE id = ? AND status = 'borrowed' FOR UPDATE", 'i', [$borrow_id]);
        $record = $result->fetch_assoc();

        if (!$record) {
            throw new Exception('Record not found or already returned.');
        }

        $fine    = calculateFine($record['due_date']);
        $fineStr = number_format($fine, 2, '.', '');

        dbExecute(
            $conn,
            "UPDATE borrow_records SET status='returned', return_date = ?, fine_amount = ?, received_by = ? WHERE id = ? AND status='borrowed'",
            'ssii',
            [$today, $fineStr, (int)$lib_id, $borrow_id]
        );
        dbExecute($conn, "UPDATE books SET available_copies = LEAST(total_copies, available_copies + 1) WHERE id = ?", 'i', [(int)$record['book_id']]);
        $conn->commit();

        logActivity($conn, $lib_id, 'return_book', "Returned borrow #$borrow_id");
        $fineMsg = $fine > 0 ? " Fine: ₱" . number_format($fine, 2) : "";
        $msg = "success:Book returned successfully!$fineMsg";
    } catch (Exception $e) {
        $conn->rollback();
        $msg = 'error:' . ($e instanceof mysqli_sql_exception ? 'Unable to return the book due to a database error.' : $e->getMessage());
    }
}

// Active borrows
$borrows = $conn->query("
    SELECT br.id, br.due_date, u.full_name, u.student_id, b.title, b.author
    FROM borrow_records br
    JOIN users u ON br.user_id=u.id
    JOIN books b ON br.book_id=b.id
    WHERE br.status='borrowed'
    ORDER BY br.due_date ASC
");

require_once '../includes/header.php';
?>

<div class="page-header">
  <h1>Return Book</h1>
  <p>Process book returns and calculate any fines.</p>
</div>

<?php if ($msg): ?>
  <?php [$type, $text] = explode(':', $msg, 2); ?>
  <div class="alert alert-<?= $type === 'success' ? 'success' : 'danger' ?>"><?= $type==='success'?'✅':'⚠️' ?> <?= htmlspecialchars($text) ?></div>
<?php endif; ?>

<div class="card">
  <div class="card-title">Active Borrow Records</div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>ID</th>
          <th>Student</th>
          <th>Book</th>
          <th>Due Date</th>
          <th>Fine (Est.)</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
      <?php while ($r = $borrows->fetch_assoc()):
        $isOD = $r['due_date'] < date('Y-m-d');
        $fine = calculateFine($r['due_date']);
      ?>
        <tr>
          <td style="color:#aaa;font-size:12px">#<?= $r['id'] ?></td>
          <td>
            <strong><?= htmlspecialchars($r['full_name']) ?></strong><br>
            <span style="font-size:11px;color:#999"><?= htmlspecialchars($r['student_id'] ?? '') ?></span>
          </td>
          <td>
            <?= htmlspecialchars(substr($r['title'],0,35)) ?><?= strlen($r['title'])>35?'...':'' ?><br>
            <span style="font-size:11px;color:#aaa"><?= htmlspecialchars($r['author']) ?></span>
          </td>
          <td style="color:<?= $isOD?'#e74c3c':'#333' ?>;font-weight:<?= $isOD?'600':'400' ?>">
            <?= $r['due_date'] ?>
            <?php if ($isOD): ?><br><span style="font-size:11px" class="badge badge-danger">OVERDUE</span><?php endif; ?>
          </td>
          <td>
            <?php if ($fine > 0): ?>
              <span style="color:#e74c3c;font-weight:600">₱<?= number_format($fine,2) ?></span>
            <?php else: ?>
              <span style="color:#27ae60">No fine</span>
            <?php endif; ?>
          </td>
          <td>
            <form method="POST" onsubmit="return confirm('Process return for <?= htmlspecialchars(addslashes($r['full_name'])) ?>?')">
              <?= csrfField() ?>
              <input type="hidden" name="borrow_id" value="<?= $r['id'] ?>">
              <button type="submit" class="btn btn-sm btn-primary">Process Return</button>
            </form>
          </td>
        </tr>
      <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once '../includes/footer.php'; ?>
