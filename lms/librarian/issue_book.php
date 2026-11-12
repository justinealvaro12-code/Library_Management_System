<?php
require_once '../includes/config.php';
requireRole('librarian');

$pageTitle  = 'Issue Book';
$activePage = 'issue_book';

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id  = (int)$_POST['user_id'];
    $book_id  = (int)$_POST['book_id'];
    $days     = (int)$_POST['loan_days'];
    $issue    = date('Y-m-d');
    $due      = date('Y-m-d', strtotime("+$days days"));
    $lib_id   = $_SESSION['user_id'];

    // Check availability
    $book = $conn->query("SELECT * FROM books WHERE id=$book_id")->fetch_assoc();
    if (!$book || $book['available_copies'] < 1) {
        $msg = 'error:No available copies of this book.';
    } else {
        // Check student borrow limit
        $maxBooks = (int)($conn->query("SELECT setting_value FROM settings WHERE setting_key='max_books_per_student'")->fetch_assoc()['setting_value'] ?? 3);
        $current  = $conn->query("SELECT COUNT(*) c FROM borrow_records WHERE user_id=$user_id AND status='borrowed'")->fetch_assoc()['c'];
        if ($current >= $maxBooks) {
            $msg = "error:Student has already borrowed the maximum of $maxBooks books.";
        } else {
            $conn->query("INSERT INTO borrow_records (user_id, book_id, issue_date, due_date, issued_by) VALUES ($user_id, $book_id, '$issue', '$due', $lib_id)");
            $conn->query("UPDATE books SET available_copies = available_copies - 1 WHERE id=$book_id");
            $bname = $book['title'];
            logActivity($conn, $lib_id, 'issue_book', "Issued '$bname' to user #$user_id");
            $msg = "success:Book issued successfully! Due date: $due";
        }
    }
}

// Load data
$students = $conn->query("SELECT id, full_name, student_id FROM users WHERE role='student' AND status='active' ORDER BY full_name");
$books    = $conn->query("SELECT * FROM books WHERE available_copies > 0 ORDER BY title");
$loanDays = (int)($conn->query("SELECT setting_value FROM settings WHERE setting_key='loan_period_days'")->fetch_assoc()['setting_value'] ?? 7);

require_once '../includes/header.php';
?>

<div class="page-header">
  <h1>Issue Book</h1>
  <p>Assign a book to a student borrower.</p>
</div>

<?php if ($msg): ?>
  <?php [$type, $text] = explode(':', $msg, 2); ?>
  <div class="alert alert-<?= $type === 'success' ? 'success' : 'danger' ?>"><?= $type==='success'?'✅':'⚠️' ?> <?= htmlspecialchars($text) ?></div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">
  <div class="card">
    <div class="card-title">Issue Form</div>
    <form method="POST">
      <div class="form-group">
        <label>Student *</label>
        <select name="user_id" class="form-control" required>
          <option value="">— Select Student —</option>
          <?php while ($s = $students->fetch_assoc()): ?>
            <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['full_name']) ?> (<?= htmlspecialchars($s['student_id'] ?? 'N/A') ?>)</option>
          <?php endwhile; ?>
        </select>
      </div>
      <div class="form-group">
        <label>Book *</label>
        <select name="book_id" class="form-control" required>
          <option value="">— Select Book —</option>
          <?php while ($b = $books->fetch_assoc()): ?>
            <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['title']) ?> — <?= htmlspecialchars($b['author']) ?> [<?= $b['available_copies'] ?> avail.]</option>
          <?php endwhile; ?>
        </select>
      </div>
      <div class="form-group">
        <label>Loan Period (days)</label>
        <input type="number" name="loan_days" class="form-control" value="<?= $loanDays ?>" min="1" max="60">
        <small style="font-size:12px;color:#888">Due date: today + <?= $loanDays ?> days</small>
      </div>
      <button type="submit" class="btn btn-primary">Issue Book</button>
    </form>
  </div>

  <div class="card">
    <div class="card-title">Currently Borrowed</div>
    <?php
    $current = $conn->query("
        SELECT br.*, u.full_name, b.title FROM borrow_records br
        JOIN users u ON br.user_id=u.id JOIN books b ON br.book_id=b.id
        WHERE br.status='borrowed' ORDER BY br.due_date ASC LIMIT 15
    ");
    ?>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Student</th><th>Book</th><th>Due</th></tr></thead>
        <tbody>
        <?php while ($r = $current->fetch_assoc()):
          $isOD = $r['due_date'] < date('Y-m-d');
        ?>
          <tr>
            <td style="font-size:13px"><?= htmlspecialchars($r['full_name']) ?></td>
            <td style="font-size:12px"><?= htmlspecialchars(substr($r['title'],0,30)) ?></td>
            <td style="font-size:12px;color:<?= $isOD?'#e74c3c':'inherit' ?>"><?= $r['due_date'] ?></td>
          </tr>
        <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php require_once '../includes/footer.php'; ?>
