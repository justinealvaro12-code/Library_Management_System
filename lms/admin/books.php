<?php
require_once '../includes/config.php';
requireRole('admin');

$pageTitle  = 'Manage Books';
$activePage = 'books';
$msg = '';

// Handle Add
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    $isbn      = sanitize($conn, $_POST['isbn'] ?? '');
    $title     = sanitize($conn, $_POST['title'] ?? '');
    $author    = sanitize($conn, $_POST['author'] ?? '');
    $publisher = sanitize($conn, $_POST['publisher'] ?? '');
    $category  = sanitize($conn, $_POST['category'] ?? '');
    $year      = (int)($_POST['year_published'] ?? 0);
    $copies    = (int)($_POST['total_copies'] ?? 1);
    $location  = sanitize($conn, $_POST['location'] ?? '');
    $desc      = sanitize($conn, $_POST['description'] ?? '');

    $conn->query("INSERT INTO books (isbn, title, author, publisher, category, year_published, total_copies, available_copies, location, description)
        VALUES ('$isbn','$title','$author','$publisher','$category',$year,$copies,$copies,'$location','$desc')");
    logActivity($conn, $_SESSION['user_id'], 'add_book', "Added: $title");
    $msg = 'success:Book added successfully.';
    header("Location: books.php?msg=added"); exit();
}

// Handle Delete
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $conn->query("DELETE FROM books WHERE id = $id");
    header("Location: books.php?msg=deleted"); exit();
}

// Handle Update copies
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update') {
    $id      = (int)$_POST['id'];
    $title   = sanitize($conn, $_POST['title'] ?? '');
    $author  = sanitize($conn, $_POST['author'] ?? '');
    $copies  = (int)($_POST['total_copies'] ?? 1);
    $avail   = (int)($_POST['available_copies'] ?? 0);
    $cat     = sanitize($conn, $_POST['category'] ?? '');
    $loc     = sanitize($conn, $_POST['location'] ?? '');
    $conn->query("UPDATE books SET title='$title', author='$author', total_copies=$copies, available_copies=$avail, category='$cat', location='$loc' WHERE id=$id");
    header("Location: books.php?msg=updated"); exit();
}

$search = sanitize($conn, $_GET['q'] ?? '');
$where  = $search ? "WHERE title LIKE '%$search%' OR author LIKE '%$search%' OR isbn LIKE '%$search%'" : '';
$books  = $conn->query("SELECT * FROM books $where ORDER BY title ASC");
$action = $_GET['action'] ?? '';
$editBook = null;
if ($action === 'edit' && isset($_GET['id'])) {
    $editBook = $conn->query("SELECT * FROM books WHERE id=".(int)$_GET['id'])->fetch_assoc();
}

require_once '../includes/header.php';
?>

<div class="page-header">
  <h1>Manage Books</h1>
  <p>Add, edit, or remove books from the library catalog.</p>
</div>

<?php if (isset($_GET['msg'])): ?>
  <div class="alert alert-success">✅ <?= ['added'=>'Book added.','deleted'=>'Book deleted.','updated'=>'Book updated.'][$_GET['msg']] ?? '' ?></div>
<?php endif; ?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;gap:12px;">
  <form method="GET" style="display:flex;gap:10px;flex:1">
    <input type="text" name="q" class="form-control" placeholder="Search by title, author, or ISBN…" value="<?= htmlspecialchars($search) ?>" style="max-width:380px">
    <button type="submit" class="btn btn-outline">Search</button>
  </form>
  <a href="?action=add" class="btn btn-gold">+ Add Book</a>
</div>

<?php if ($action === 'add' || $action === 'edit'): ?>
<div class="card" style="margin-bottom:24px;">
  <div class="card-title"><?= $action === 'edit' ? 'Edit Book' : 'Add New Book' ?></div>
  <form method="POST">
    <input type="hidden" name="action" value="<?= $action === 'edit' ? 'update' : 'add' ?>">
    <?php if ($editBook): ?><input type="hidden" name="id" value="<?= $editBook['id'] ?>"><?php endif; ?>
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;">
      <div class="form-group">
        <label>ISBN</label>
        <input type="text" name="isbn" class="form-control" value="<?= htmlspecialchars($editBook['isbn'] ?? '') ?>">
      </div>
      <div class="form-group" style="grid-column:span 2">
        <label>Title *</label>
        <input type="text" name="title" class="form-control" required value="<?= htmlspecialchars($editBook['title'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label>Author *</label>
        <input type="text" name="author" class="form-control" required value="<?= htmlspecialchars($editBook['author'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label>Publisher</label>
        <input type="text" name="publisher" class="form-control" value="<?= htmlspecialchars($editBook['publisher'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label>Category</label>
        <select name="category" class="form-control">
          <?php foreach(['Fiction','Non-Fiction','Science','Technology','History','Classic Literature','Fantasy','Young Adult','Mystery Thriller','Reference','Textbook','Other'] as $cat): ?>
            <option value="<?= $cat ?>" <?= ($editBook['category'] ?? '') === $cat ? 'selected':'' ?>><?= $cat ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>Year Published</label>
        <input type="number" name="year_published" class="form-control" min="1800" max="<?= date('Y') ?>" value="<?= htmlspecialchars($editBook['year_published'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label>Total Copies</label>
        <input type="number" name="total_copies" class="form-control" min="1" value="<?= htmlspecialchars($editBook['total_copies'] ?? 1) ?>">
      </div>
      <?php if ($editBook): ?>
      <div class="form-group">
        <label>Available Copies</label>
        <input type="number" name="available_copies" class="form-control" min="0" value="<?= htmlspecialchars($editBook['available_copies'] ?? 0) ?>">
      </div>
      <?php endif; ?>
      <div class="form-group" style="grid-column:span 3">
        <label>Shelf Location</label>
        <input type="text" name="location" class="form-control" placeholder="e.g. Section A - Shelf 1" value="<?= htmlspecialchars($editBook['location'] ?? '') ?>">
      </div>
      <div class="form-group" style="grid-column:span 3">
        <label>Description</label>
        <textarea name="description" class="form-control" rows="2"><?= htmlspecialchars($editBook['description'] ?? '') ?></textarea>
      </div>
    </div>
    <div style="display:flex;gap:10px">
      <button type="submit" class="btn btn-primary"><?= $action === 'edit' ? 'Update Book' : 'Add Book' ?></button>
      <a href="books.php" class="btn btn-outline">Cancel</a>
    </div>
  </form>
</div>
<?php endif; ?>

<div class="card">
  <div class="table-wrap">
    <table>
      <thead><tr><th>Title / Author</th><th>ISBN</th><th>Category</th><th>Copies</th><th>Available</th><th>Location</th><th>Actions</th></tr></thead>
      <tbody>
      <?php while ($book = $books->fetch_assoc()): ?>
        <tr>
          <td>
            <strong><?= htmlspecialchars($book['title']) ?></strong><br>
            <span style="font-size:12px;color:#888"><?= htmlspecialchars($book['author']) ?></span>
          </td>
          <td style="font-size:12px"><?= htmlspecialchars($book['isbn']) ?></td>
          <td><span class="badge badge-info"><?= htmlspecialchars($book['category']) ?></span></td>
          <td><?= $book['total_copies'] ?></td>
          <td>
            <span class="badge <?= $book['available_copies'] > 0 ? 'badge-success' : 'badge-danger' ?>">
              <?= $book['available_copies'] ?>
            </span>
          </td>
          <td style="font-size:12px;color:#888"><?= htmlspecialchars($book['location']) ?></td>
          <td>
            <a href="?action=edit&id=<?= $book['id'] ?>" class="btn btn-sm btn-outline">Edit</a>
            <a href="?delete=<?= $book['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this book?')">Del</a>
          </td>
        </tr>
      <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once '../includes/footer.php'; ?>
