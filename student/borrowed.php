<?php
require_once '../includes/config.php';
require_once '../includes/book_cover.php';
requireRole('student');

$pageTitle  = 'My Borrowed Books';
$activePage = 'borrowed';
$uid = (int)$_SESSION['user_id'];

$borrowed = dbSelect($conn, "
    SELECT br.*, b.title, b.author, b.isbn, b.category, b.cover_image
    FROM borrow_records br JOIN books b ON br.book_id=b.id
    WHERE br.user_id = ? AND br.status = 'borrowed'
    ORDER BY br.due_date ASC
", 'i', [$uid]);

require_once '../includes/header.php';
?>

<div class="page-header">
  <h1>My Borrowed Books</h1>
  <p>Books you currently have checked out.</p>
</div>

<div class="card">
  <?php if ($borrowed->num_rows === 0): ?>
    <div style="text-align:center;padding:40px;color:#aaa">
      <div style="font-size:48px;margin-bottom:12px">📚</div>
      <p>You have no books currently borrowed.</p>
      <a href="<?= SITE_URL ?>/student/search.php" class="btn btn-gold" style="margin-top:12px">Browse Catalog</a>
    </div>
  <?php else: ?>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Book</th><th>Category</th><th>Issue Date</th><th>Due Date</th><th>Days Left</th><th>Fine</th></tr></thead>
      <tbody>
      <?php while ($r = $borrowed->fetch_assoc()):
        $daysLeft = daysUntilDue($r['due_date']);
        $isOD     = $daysLeft < 0;
        $fine     = calculateFine($r['due_date']);
      ?>
        <tr>
          <td>
            <div class="book-cell">
              <img src="<?= htmlspecialchars(bookCoverUrl($r['cover_image'] ?? '')) ?>" alt="" class="cover-thumb cover-thumb-borrowed" loading="lazy">
              <div>
                <strong><?= htmlspecialchars($r['title']) ?></strong><br>
                <span style="font-size:12px;color:#888">by <?= htmlspecialchars($r['author']) ?></span>
              </div>
            </div>
          </td>
          <td><span class="badge badge-info"><?= htmlspecialchars($r['category']) ?></span></td>
          <td style="font-size:12px"><?= $r['issue_date'] ?></td>
          <td style="color:<?= $isOD?'#e74c3c':($daysLeft<=2?'#e67e22':'inherit') ?>"><?= $r['due_date'] ?></td>
          <td>
            <?php if ($isOD): ?>
              <span class="badge badge-danger"><?= abs($daysLeft) ?> days overdue</span>
            <?php elseif ($daysLeft <= 2): ?>
              <span class="badge badge-warning"><?= $daysLeft ?> day(s) left</span>
            <?php else: ?>
              <span style="font-size:13px;color:#27ae60"><?= $daysLeft ?> days</span>
            <?php endif; ?>
          </td>
          <td>
            <?= $fine > 0 ? '<span style="color:#e74c3c;font-weight:600">₱'.number_format($fine,2).'</span>' : '<span style="color:#27ae60;font-size:12px">None</span>' ?>
          </td>
        </tr>
      <?php endwhile; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>
