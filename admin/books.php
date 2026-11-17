<?php
require_once '../includes/config.php';
require_once '../includes/pagination.php';
require_once '../includes/book_cover.php';
require_once '../includes/book_actions.php';
requireRole('admin');

$pageTitle  = 'Manage Books';
$activePage = 'books';
$msg        = '';
$msgType    = 'success';
$action     = $_GET['action'] ?? '';
$editBook   = null;

$categoryOptions = ['Fiction','Non-Fiction','Science','Technology','History','Classic Literature','Fantasy','Young Adult','Mystery Thriller','Reference','Textbook','Other'];

// Handle Add / Update / Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bookState = bookHandlePost($conn, $_POST['action'] ?? '', $action, $editBook, $msg, $msgType);
    $action    = $bookState['action'];
    $editBook  = $bookState['editBook'];
    $msg       = $bookState['msg'];
    $msgType   = $bookState['msgType'];
}

// Load book for editing via GET
if ($action === 'edit' && $editBook === null && isset($_GET['id'])) {
    $editBook = dbSelect($conn, "SELECT * FROM books WHERE id = ?", 'i', [(int)$_GET['id']])->fetch_assoc();
}

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
$sql = "SELECT b.*, " . reservationEffectiveAvailableSql('b') . " AS effective_available FROM books b $where ORDER BY title ASC LIMIT ? OFFSET ?";
$typesP = $types . 'ii';
$paramsP = array_merge($params, [$pager['per_page'], $pager['offset']]);
$books = dbSelect($conn, $sql, $typesP, $paramsP);

if ($msg === '' && isset($_GET['msg'])) {
    $map = ['added' => 'Book added.', 'deleted' => 'Book deleted.', 'updated' => 'Book updated.'];
    if (isset($map[$_GET['msg']])) {
        $msgType = 'success';
        $msg     = $map[$_GET['msg']];
    }
}

$currentCategory = $editBook['category'] ?? '';
if ($currentCategory !== '' && !in_array($currentCategory, $categoryOptions, true)) {
    $categoryOptions[] = $currentCategory;
}

require_once '../includes/header.php';
?>

<div class="page-header">
  <h1>Manage Books</h1>
  <p>Add, edit, or remove books from the library catalog.</p>
</div>

<?php if ($msg !== ''): ?>
  <div class="alert alert-<?= $msgType === 'danger' ? 'danger' : 'success' ?>"><?= $msgType === 'danger' ? '⚠️' : '✅' ?> <?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;gap:12px;flex-wrap:wrap;">
  <form method="GET" style="display:flex;gap:10px;flex:1">
    <input type="text" name="q" class="form-control" placeholder="Search by title, author, or ISBN…" value="<?= htmlspecialchars($searchRaw) ?>" style="max-width:380px">
    <button type="submit" class="btn btn-outline">Search</button>
  </form>
  <div style="display:flex;gap:8px">
    <a class="btn btn-outline" href="<?= SITE_URL ?>/admin/exports/books.php?<?= http_build_query(array_intersect_key($_GET, array_flip(['q']))) ?>">Export CSV</a>
    <a href="?action=add" class="btn btn-gold">+ Add Book</a>
  </div>
</div>

<?php require_once '../includes/book_form.php'; ?>

<div class="card">
  <div class="table-wrap">
    <table>
      <thead><tr><th>Cover</th><th>Title / Author</th><th>ISBN</th><th>Category</th><th>Copies</th><th>Available</th><th>Location</th><th>Actions</th></tr></thead>
      <tbody>
      <?php if ($totalBooks === 0): ?>
        <tr><td colspan="8" style="text-align:center;color:#888;padding:24px">No results found.</td></tr>
      <?php else: while ($book = $books->fetch_assoc()): ?>
        <tr>
          <td><img src="<?= htmlspecialchars(bookCoverUrl($book['cover_image'] ?? '')) ?>" alt="" class="cover-thumb cover-thumb-admin" loading="lazy"></td>
          <td>
            <strong><?= htmlspecialchars($book['title']) ?></strong><br>
            <span style="font-size:12px;color:#888"><?= htmlspecialchars($book['author']) ?></span>
          </td>
          <td style="font-size:12px"><?= htmlspecialchars($book['isbn'] ?? '') ?></td>
          <td><span class="badge badge-info"><?= htmlspecialchars($book['category'] ?? '') ?></span></td>
          <td><?= $book['total_copies'] ?></td>
          <td>
            <span class="badge <?= $book['effective_available'] > 0 ? 'badge-success' : 'badge-danger' ?>">
              <?= $book['effective_available'] ?>
            </span>
          </td>
          <td style="font-size:12px;color:#888"><?= htmlspecialchars($book['location'] ?? '') ?></td>
          <td>
            <a href="?action=edit&id=<?= $book['id'] ?>" class="btn btn-sm btn-outline">Edit</a>
            <form method="POST" style="display:inline" onsubmit="return confirm('Delete this book?')">
              <?= csrfField() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= $book['id'] ?>">
              <button type="submit" class="btn btn-sm btn-danger">Del</button>
            </form>
          </td>
        </tr>
      <?php endwhile; endif; ?>
      </tbody>
    </table>
  </div>
  <?= renderPagination($pager) ?>
</div>

<?php require_once '../includes/footer.php'; ?>
