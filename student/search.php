<?php
require_once '../includes/config.php';
require_once '../includes/pagination.php';
require_once '../includes/book_cover.php';
requireRole('student');

$pageTitle  = 'Search Books';
$activePage = 'search';

$searchRaw   = trim($_GET['q'] ?? '');
$categoryRaw = trim($_GET['cat'] ?? '');
$where       = "WHERE 1=1";
$types       = '';
$params      = [];
if ($searchRaw !== '') {
    $like    = '%' . $searchRaw . '%';
    $where  .= " AND (title LIKE ? OR author LIKE ? OR isbn LIKE ?)";
    $types  .= 'sss';
    $params = array_merge($params, [$like, $like, $like]);
}
if ($categoryRaw !== '') {
    $where  .= " AND category = ?";
    $types  .= 's';
    $params[] = $categoryRaw;
}

$countRes = dbSelect($conn, "SELECT COUNT(*) c FROM books $where", $types, $params);
$totalBooks = (int)($countRes ? $countRes->fetch_assoc()['c'] : 0);
$pager = paginate($totalBooks, 12);

$typesP  = $types . 'ii';
$paramsP = array_merge($params, [$pager['per_page'], $pager['offset']]);
$books   = dbSelect($conn, "SELECT * FROM books $where ORDER BY title ASC LIMIT ? OFFSET ?", $typesP, $paramsP);
$categories = $conn->query("SELECT DISTINCT category FROM books ORDER BY category");

require_once '../includes/header.php';
?>

<div class="page-header">
  <h1>Search Books</h1>
  <p>Browse the library catalog and find books to borrow.</p>
</div>

<div class="card" style="margin-bottom:20px">
  <form method="GET" style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap">
    <div class="form-group" style="flex:1;min-width:200px;margin:0">
      <label>Search</label>
      <input type="text" name="q" class="form-control" placeholder="Title, author, or ISBN…" value="<?= htmlspecialchars($searchRaw) ?>">
    </div>
    <div class="form-group" style="min-width:180px;margin:0">
      <label>Category</label>
      <select name="cat" class="form-control">
        <option value="">All Categories</option>
        <?php while ($c = $categories->fetch_assoc()): ?>
          <option value="<?= htmlspecialchars($c['category'] ?? '') ?>" <?= $categoryRaw===$c['category']?'selected':'' ?>>
            <?= htmlspecialchars($c['category'] ?? '') ?>
          </option>
        <?php endwhile; ?>
      </select>
    </div>
    <button type="submit" class="btn btn-primary">Search</button>
    <?php if ($searchRaw !== '' || $categoryRaw !== ''): ?>
      <a href="search.php" class="btn btn-outline">Clear</a>
    <?php endif; ?>
  </form>
</div>

<?php if ($totalBooks === 0): ?>
  <div class="card" style="text-align:center;padding:40px;color:#888">
    <p>No results found<?= ($searchRaw !== '' || $categoryRaw !== '') ? ' for your search' : '' ?>.</p>
  </div>
<?php else: ?>
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px">
<?php while ($b = $books->fetch_assoc()):
  $bookYear = (int)($b['year_published'] ?? 0);
?>
  <div style="background:white;border-radius:12px;padding:20px;box-shadow:0 1px 4px rgba(0,0,0,0.06);border-top:3px solid <?= $b['available_copies']>0?'#27ae60':'#e74c3c' ?>">
    <div style="display:flex;gap:14px;align-items:flex-start">
      <img src="<?= htmlspecialchars(bookCoverUrl($b['cover_image'] ?? '')) ?>" alt="" class="cover-thumb cover-thumb-search" loading="lazy">
      <div style="min-width:0;flex:1">
        <div style="font-size:11px;text-transform:uppercase;letter-spacing:1px;color:#aaa;margin-bottom:8px"><?= htmlspecialchars($b['category'] ?? '') ?></div>
        <h3 style="font-size:16px;font-weight:600;color:#0f1e38;margin-bottom:4px;line-height:1.3"><?= htmlspecialchars($b['title']) ?></h3>
        <p style="font-size:13px;color:#666;margin-bottom:12px">by <?= htmlspecialchars($b['author']) ?></p>
        <?php if (!empty($b['publisher'])): ?>
          <p style="font-size:12px;color:#aaa;margin-bottom:4px">📚 <?= htmlspecialchars($b['publisher'] ?? '') ?> <?= $bookYear > 0 ? '(' . $bookYear . ')' : '' ?></p>
        <?php endif; ?>
        <?php if (!empty($b['location'])): ?>
          <p style="font-size:12px;color:#aaa;margin-bottom:8px">📍 <?= htmlspecialchars($b['location'] ?? '') ?></p>
        <?php endif; ?>
      </div>
    </div>
    <div style="display:flex;justify-content:space-between;align-items:center;margin-top:12px;padding-top:12px;border-top:1px solid #f0f0f0">
      <span class="badge <?= $b['available_copies']>0?'badge-success':'badge-danger' ?>">
        <?= $b['available_copies'] > 0 ? "✅ {$b['available_copies']} Available" : "⛔ Unavailable" ?>
      </span>
      <?php if (!empty($b['isbn'])): ?>
        <span style="font-size:11px;color:#ccc">ISBN: <?= htmlspecialchars($b['isbn'] ?? '') ?></span>
      <?php endif; ?>
    </div>
    <?php if ($b['available_copies'] == 0): ?>
      <p style="font-size:12px;color:#e74c3c;margin-top:8px">Ask a librarian for assistance.</p>
    <?php endif; ?>
  </div>
<?php endwhile; ?>
</div>
<div class="card" style="margin-top:16px">
  <?= renderPagination($pager) ?>
</div>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>
