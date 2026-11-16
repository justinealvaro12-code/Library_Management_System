<?php
require_once '../includes/config.php';
require_once '../includes/pagination.php';
requireRole('librarian');

$pageTitle  = 'Book Catalog';
$activePage = 'books';

$searchRaw = trim($_GET['q'] ?? '');
$where = '';
$types = '';
$params = [];
if ($searchRaw !== '') {
    $like  = '%' . $searchRaw . '%';
    $where = "WHERE title LIKE ? OR author LIKE ? OR isbn LIKE ?";
    $types = 'sss';
    $params = [$like, $like, $like];
}

$countRes = dbSelect($conn, "SELECT COUNT(*) c FROM books $where", $types, $params);
$totalBooks = (int)($countRes ? $countRes->fetch_assoc()['c'] : 0);
$pager = paginate($totalBooks, 20);

$sql = "SELECT * FROM books $where ORDER BY title ASC LIMIT ? OFFSET ?";
$typesP = $types . 'ii';
$paramsP = array_merge($params, [$pager['per_page'], $pager['offset']]);
$books = dbSelect($conn, $sql, $typesP, $paramsP);

require_once '../includes/header.php';
?>

<div class="page-header">
  <h1>Book Catalog</h1>
  <p>Browse and manage the library book inventory.</p>
</div>

<div style="display:flex;gap:12px;margin-bottom:20px;align-items:center;flex-wrap:wrap">
  <form method="GET" style="display:flex;gap:10px;flex:1">
    <input type="text" name="q" class="form-control" placeholder="Search books…" value="<?= htmlspecialchars($searchRaw) ?>" style="max-width:380px">
    <button type="submit" class="btn btn-outline">Search</button>
    <?php if ($searchRaw !== ''): ?><a href="books.php" class="btn btn-outline">Clear</a><?php endif; ?>
  </form>
</div>

<div class="card">
  <div class="table-wrap">
    <table>
      <thead><tr><th>Title</th><th>Author</th><th>Category</th><th>ISBN</th><th>Copies</th><th>Available</th><th>Location</th></tr></thead>
      <tbody>
      <?php if ($totalBooks === 0): ?>
        <tr><td colspan="7" style="text-align:center;color:#888;padding:24px">No results found.</td></tr>
      <?php else: while ($b = $books->fetch_assoc()): ?>
        <tr>
          <td><strong><?= htmlspecialchars($b['title']) ?></strong></td>
          <td style="font-size:13px;color:#666"><?= htmlspecialchars($b['author']) ?></td>
          <td><span class="badge badge-info"><?= htmlspecialchars($b['category'] ?? '') ?></span></td>
          <td style="font-size:12px;color:#aaa"><?= htmlspecialchars($b['isbn'] ?? '') ?></td>
          <td><?= $b['total_copies'] ?></td>
          <td><span class="badge <?= $b['available_copies']>0?'badge-success':'badge-danger' ?>"><?= $b['available_copies'] ?></span></td>
          <td style="font-size:12px;color:#aaa"><?= htmlspecialchars($b['location'] ?? '') ?></td>
        </tr>
      <?php endwhile; endif; ?>
      </tbody>
    </table>
  </div>
  <?= renderPagination($pager) ?>
</div>

<?php require_once '../includes/footer.php'; ?>
