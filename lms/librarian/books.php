<?php
require_once '../includes/config.php';
requireRole('librarian');

$pageTitle  = 'Book Catalog';
$activePage = 'books';

$search = sanitize($conn, $_GET['q'] ?? '');
$where  = $search ? "WHERE title LIKE '%$search%' OR author LIKE '%$search%' OR isbn LIKE '%$search%'" : '';
$books  = $conn->query("SELECT * FROM books $where ORDER BY title ASC");

require_once '../includes/header.php';
?>

<div class="page-header">
  <h1>Book Catalog</h1>
  <p>Browse and manage the library book inventory.</p>
</div>

<div style="display:flex;gap:12px;margin-bottom:20px">
  <form method="GET" style="display:flex;gap:10px;flex:1">
    <input type="text" name="q" class="form-control" placeholder="Search books…" value="<?= htmlspecialchars($search) ?>" style="max-width:380px">
    <button type="submit" class="btn btn-outline">Search</button>
    <?php if ($search): ?><a href="books.php" class="btn btn-outline">Clear</a><?php endif; ?>
  </form>
</div>

<div class="card">
  <div class="table-wrap">
    <table>
      <thead><tr><th>Title</th><th>Author</th><th>Category</th><th>ISBN</th><th>Copies</th><th>Available</th><th>Location</th></tr></thead>
      <tbody>
      <?php while ($b = $books->fetch_assoc()): ?>
        <tr>
          <td><strong><?= htmlspecialchars($b['title']) ?></strong></td>
          <td style="font-size:13px;color:#666"><?= htmlspecialchars($b['author']) ?></td>
          <td><span class="badge badge-info"><?= htmlspecialchars($b['category']) ?></span></td>
          <td style="font-size:12px;color:#aaa"><?= htmlspecialchars($b['isbn']) ?></td>
          <td><?= $b['total_copies'] ?></td>
          <td><span class="badge <?= $b['available_copies']>0?'badge-success':'badge-danger' ?>"><?= $b['available_copies'] ?></span></td>
          <td style="font-size:12px;color:#aaa"><?= htmlspecialchars($b['location']) ?></td>
        </tr>
      <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once '../includes/footer.php'; ?>
