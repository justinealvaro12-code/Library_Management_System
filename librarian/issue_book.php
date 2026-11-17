<?php
require_once '../includes/config.php';
require_once '../includes/reservations.php';
requireRole('librarian');

$pageTitle  = 'Issue Book';
$activePage = 'issue_book';

$msg = '';
$formUser = (int)($_GET['user_id'] ?? 0);
$formBook = (int)($_GET['book_id'] ?? 0);
$formDays = LOAN_DAYS;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = (int)($_POST['user_id'] ?? 0);
    $book_id = (int)($_POST['book_id'] ?? 0);
    $days    = (int)($_POST['loan_days'] ?? LOAN_DAYS);
    $lib_id  = (int)$_SESSION['user_id'];
    $issue   = date('Y-m-d');

    $formUser = $user_id;
    $formBook = $book_id;
    $formDays = $days;

    if ($user_id <= 0) {
        $msg = 'error:Please select an active student.';
    } elseif ($book_id <= 0) {
        $msg = 'error:Book not found.';
    } elseif ($days < 1 || $days > 60) {
        $msg = 'error:Loan period must be between 1 and 60 days.';
    } else {
        for ($attempt = 1; $attempt <= 2; $attempt++) {
            $conn->begin_transaction();
            try {
                $book = dbSelect($conn, "SELECT * FROM books WHERE id = ? FOR UPDATE", 'i', [$book_id])->fetch_assoc();
                if (!$book) {
                    throw new Exception('Book not found.');
                }

                $student = dbSelect(
                    $conn,
                    "SELECT id, full_name, status, role FROM users WHERE id = ? FOR UPDATE",
                    'i',
                    [$user_id]
                )->fetch_assoc();

                if (!$student || $student['role'] !== 'student' || $student['status'] !== 'active') {
                    throw new Exception('Please select an active student.');
                }

                if (($settings['block_unpaid_fines'] ?? '0') === '1') {
                    $unpaidTotal = reservationUnpaidFines($conn, $user_id);
                    if ($unpaidTotal > 0) {
                        throw new Exception('Cannot issue book: student has unpaid fines totaling PHP ' . number_format($unpaidTotal, 2) . '.');
                    }
                }

                $alreadyBorrowed = (int)dbSelect(
                    $conn,
                    "SELECT COUNT(*) c FROM borrow_records WHERE user_id = ? AND book_id = ? AND status = 'borrowed'",
                    'ii',
                    [$user_id, $book_id]
                )->fetch_assoc()['c'];
                $current = (int)dbSelect(
                    $conn,
                    "SELECT COUNT(*) c FROM borrow_records WHERE user_id = ? AND status = 'borrowed'",
                    'i',
                    [$user_id]
                )->fetch_assoc()['c'];

                if ($alreadyBorrowed > 0) {
                    throw new Exception('This student already has this book borrowed.');
                }
                if ($current >= MAX_BORROW) {
                    throw new Exception('Student has already borrowed the maximum of ' . MAX_BORROW . ' books.');
                }

                $state = reservationBookState($conn, $book_id);
                $ready = dbSelect(
                    $conn,
                    "SELECT id FROM reservations
                     WHERE user_id = ? AND book_id = ? AND status = 'ready'
                     FOR UPDATE",
                    'ii',
                    [$user_id, $book_id]
                )->fetch_assoc();

                if (!$ready && (int)$state['effective_available'] <= 0) {
                    if ((int)$state['ready_holds'] > 0) {
                        throw new Exception('This copy is held for a reserved student. Use the reservations page to issue it to the reserved student.');
                    }
                    throw new Exception('No available copies of this book.');
                }
                if ($ready && (int)$state['available_copies'] <= 0) {
                    throw new Exception('No physical copy is available to fulfill this reservation.');
                }

                $update = dbExecute(
                    $conn,
                    "UPDATE books SET available_copies = available_copies - 1
                     WHERE id = ? AND available_copies > 0",
                    'i',
                    [$book_id]
                );
                if ($update->affected_rows !== 1) {
                    throw new Exception('No available copies of this book.');
                }

                if ($ready) {
                    $fulfilled = dbExecute(
                        $conn,
                        "UPDATE reservations SET status = 'fulfilled', expiry_date = NULL
                         WHERE id = ? AND status = 'ready'",
                        'i',
                        [(int)$ready['id']]
                    );
                    if ($fulfilled->affected_rows !== 1) {
                        throw new Exception('The reservation is no longer ready for pickup.');
                    }
                    logActivity(
                        $conn,
                        $lib_id,
                        'reservation_fulfilled',
                        "Reservation #{$ready['id']} fulfilled by issue (book #$book_id, user #$user_id)"
                    );
                }

                $due = date('Y-m-d', strtotime("+$days days"));
                dbExecute(
                    $conn,
                    "INSERT INTO borrow_records (user_id, book_id, issue_date, due_date, issued_by) VALUES (?, ?, ?, ?, ?)",
                    'iissi',
                    [$user_id, $book_id, $issue, $due, $lib_id]
                );

                reservationSyncBookStatus($conn, $book_id);
                $conn->commit();

                $bname = $book['title'];
                logActivity($conn, $lib_id, 'issue_book', "Issued '$bname' to user #$user_id");
                $msg = "success:Book issued successfully! Due date: $due";
                break;
            } catch (mysqli_sql_exception $e) {
                $conn->rollback();
                if ((int)$e->getCode() === 1213 && $attempt === 1) {
                    continue;
                }
                if ((int)$e->getCode() === 1062) {
                    $msg = 'error:This issue conflicts with another request. Please try again.';
                } else {
                    $msg = 'error:Unable to issue the book due to a database error.';
                }
                break;
            } catch (Exception $e) {
                $conn->rollback();
                $msg = 'error:' . $e->getMessage();
                break;
            }
        }
    }
}

$students = $conn->query("SELECT id, full_name, student_id FROM users WHERE role='student' AND status='active' ORDER BY full_name");
$books = $conn->query(
    "SELECT b.*, " . reservationEffectiveAvailableSql('b') . " AS effective_available
     FROM books b WHERE b.available_copies > 0 ORDER BY b.title"
);
$loanDays = $formDays;

require_once '../includes/header.php';
?>

<div class="page-header">
  <h1>Issue Book</h1>
  <p>Assign a book to a student borrower.</p>
</div>

<?php if ($msg): ?>
  <?php [$type, $text] = explode(':', $msg, 2); ?>
  <div class="alert alert-<?= $type === 'success' ? 'success' : 'danger' ?>"><?= htmlspecialchars($text) ?></div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">
  <div class="card">
    <div class="card-title">Issue Form</div>
    <form method="POST">
      <?= csrfField() ?>
      <div class="form-group">
        <label>Student *</label>
        <select name="user_id" class="form-control" required>
          <option value="">-- Select Student --</option>
          <?php while ($s = $students->fetch_assoc()): ?>
            <option value="<?= (int)$s['id'] ?>" <?= (int)$s['id'] === $formUser ? 'selected' : '' ?>><?= htmlspecialchars($s['full_name']) ?> (<?= htmlspecialchars($s['student_id'] ?? 'N/A') ?>)</option>
          <?php endwhile; ?>
        </select>
      </div>
      <div class="form-group">
        <label>Book *</label>
        <select name="book_id" class="form-control" required>
          <option value="">-- Select Book --</option>
          <?php while ($b = $books->fetch_assoc()): ?>
            <option value="<?= (int)$b['id'] ?>" <?= (int)$b['id'] === $formBook ? 'selected' : '' ?>><?= htmlspecialchars($b['title']) ?> - <?= htmlspecialchars($b['author']) ?> [<?= (int)$b['effective_available'] ?> general avail.]</option>
          <?php endwhile; ?>
        </select>
      </div>
      <div class="form-group">
        <label>Loan Period (days)</label>
        <input type="number" name="loan_days" class="form-control" value="<?= (int)$loanDays ?>" min="1" max="60">
        <small style="font-size:12px;color:#888">Due date: today + <?= (int)$loanDays ?> days</small>
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
