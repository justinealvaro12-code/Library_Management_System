<?php
require_once '../includes/config.php';
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
    $postAction = $_POST['action'] ?? '';

    if ($postAction === 'add' || $postAction === 'update') {
        $id            = (int)($_POST['id'] ?? 0);
        $isbnRaw       = trim($_POST['isbn'] ?? '');
        $titleRaw      = trim($_POST['title'] ?? '');
        $authorRaw     = trim($_POST['author'] ?? '');
        $publisherRaw  = trim($_POST['publisher'] ?? '');
        $categoryRaw   = trim($_POST['category'] ?? '');
        $yearRaw       = trim((string)($_POST['year_published'] ?? ''));
        $copies        = (int)($_POST['total_copies'] ?? 1);
        $locationRaw   = trim($_POST['location'] ?? '');
        $descriptionRaw = trim($_POST['description'] ?? '');
        $year          = $yearRaw === '' ? null : (int)$yearRaw;

        $isbn      = sanitize($conn, $isbnRaw);
        $title     = sanitize($conn, $titleRaw);
        $author    = sanitize($conn, $authorRaw);
        $publisher = sanitize($conn, $publisherRaw);
        $category  = sanitize($conn, $categoryRaw);
        $location  = sanitize($conn, $locationRaw);
        $desc      = sanitize($conn, $descriptionRaw);

        $errors   = [];
        $borrowed = 0;
        $available = $copies;

        if ($titleRaw === '') {
            $errors[] = 'Title is required.';
        }
        if ($authorRaw === '') {
            $errors[] = 'Author is required.';
        }
        if ($copies < 1) {
            $errors[] = 'Total copies must be at least 1.';
        }
        if ($yearRaw !== '' && ($year < 1000 || $year > (int)date('Y') + 1)) {
            $errors[] = 'Year published must be between 1000 and ' . ((int)date('Y') + 1) . '.';
        }

        if ($postAction === 'update') {
            $borrowed = (int)$conn->query("SELECT COUNT(*) c FROM borrow_records WHERE book_id=$id AND status='borrowed'")->fetch_assoc()['c'];
            $available = max(0, $copies - $borrowed);
            if ($copies < $borrowed) {
                $errors[] = "Total copies cannot be less than the $borrowed currently borrowed.";
            }
        }

        $editBook = [
            'id'               => $id,
            'isbn'             => $isbnRaw,
            'title'            => $titleRaw,
            'author'           => $authorRaw,
            'publisher'        => $publisherRaw,
            'category'         => $categoryRaw,
            'year_published'   => $yearRaw,
            'total_copies'     => $copies,
            'available_copies' => $available,
            'location'         => $locationRaw,
            'description'      => $descriptionRaw,
        ];

        if (!empty($errors)) {
            $msgType = 'danger';
            $msg     = implode(' ', $errors);
            $action  = $postAction === 'add' ? 'add' : 'edit';
        } else {
            $isbnSql = $isbn !== '' ? "'$isbn'" : "NULL";
            $yearSql = $year === null ? "NULL" : (string)$year;

            try {
                if ($postAction === 'add') {
                    $conn->query("INSERT INTO books (isbn, title, author, publisher, category, year_published, total_copies, available_copies, location, description)
                        VALUES ($isbnSql, '$title', '$author', '$publisher', '$category', $yearSql, $copies, $copies, '$location', '$desc')");
                    logActivity($conn, $_SESSION['user_id'], 'add_book', "Added: $title");
                    header("Location: books.php?msg=added");
                    exit();
                }

                $conn->query("UPDATE books SET isbn=$isbnSql, title='$title', author='$author', publisher='$publisher',
                    category='$category', year_published=$yearSql, total_copies=$copies, available_copies=$available,
                    location='$location', description='$desc' WHERE id=$id");
                logActivity($conn, $_SESSION['user_id'], 'update_book', "Updated: $title");
                header("Location: books.php?msg=updated");
                exit();
            } catch (mysqli_sql_exception $e) {
                $msgType = 'danger';
                $msg     = (int)$e->getCode() === 1062
                    ? 'A book with that ISBN already exists.'
                    : 'Unable to save the book. Please try again.';
                $action  = $postAction === 'add' ? 'add' : 'edit';
            }
        }
    } elseif ($postAction === 'delete') {
        $id       = (int)($_POST['id'] ?? 0);
        $borrowed = (int)$conn->query("SELECT COUNT(*) c FROM borrow_records WHERE book_id=$id AND status='borrowed'")->fetch_assoc()['c'];

        if ($borrowed > 0) {
            $msgType = 'danger';
            $msg     = "Cannot delete this book: $borrowed copy/copies are still borrowed.";
        } else {
            try {
                $conn->query("DELETE FROM books WHERE id=$id");
                logActivity($conn, $_SESSION['user_id'], 'delete_book', "Deleted book #$id");
                header("Location: books.php?msg=deleted");
                exit();
            } catch (mysqli_sql_exception $e) {
                $msgType = 'danger';
                $msg     = 'Unable to delete this book because it is referenced by other records.';
            }
        }
    }
}

// Load book for editing via GET
if ($action === 'edit' && $editBook === null && isset($_GET['id'])) {
    $editBook = $conn->query("SELECT * FROM books WHERE id=" . (int)$_GET['id'])->fetch_assoc();
}

$searchRaw = $_GET['q'] ?? '';
$search    = sanitize($conn, $searchRaw);
$where     = $search ? "WHERE title LIKE '%$search%' OR author LIKE '%$search%' OR isbn LIKE '%$search%'" : '';
$books     = $conn->query("SELECT * FROM books $where ORDER BY title ASC");

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

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;gap:12px;">
  <form method="GET" style="display:flex;gap:10px;flex:1">
    <input type="text" name="q" class="form-control" placeholder="Search by title, author, or ISBN…" value="<?= htmlspecialchars($searchRaw) ?>" style="max-width:380px">
    <button type="submit" class="btn btn-outline">Search</button>
  </form>
  <a href="?action=add" class="btn btn-gold">+ Add Book</a>
</div>

<?php if ($action === 'add' || $action === 'edit'): ?>
<div class="card" style="margin-bottom:24px;">
  <div class="card-title"><?= $action === 'edit' ? 'Edit Book' : 'Add New Book' ?></div>
  <form method="POST">
    <input type="hidden" name="action" value="<?= $action === 'edit' ? 'update' : 'add' ?>">
    <?php if ($action === 'edit' && !empty($editBook['id'])): ?><input type="hidden" name="id" value="<?= (int)$editBook['id'] ?>"><?php endif; ?>
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
          <?php foreach ($categoryOptions as $cat): ?>
            <option value="<?= htmlspecialchars($cat) ?>" <?= ($editBook['category'] ?? '') === $cat ? 'selected' : '' ?>><?= htmlspecialchars($cat) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>Year Published</label>
        <input type="number" name="year_published" class="form-control" min="1000" max="<?= (int)date('Y') + 1 ?>" value="<?= htmlspecialchars($editBook['year_published'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label>Total Copies</label>
        <input type="number" name="total_copies" class="form-control" min="1" value="<?= htmlspecialchars($editBook['total_copies'] ?? 1) ?>">
      </div>
      <?php if ($action === 'edit'): ?>
      <div class="form-group">
        <label>Available Copies</label>
        <input type="number" class="form-control" value="<?= (int)($editBook['available_copies'] ?? 0) ?>" disabled readonly style="background:#f0f0f0;cursor:not-allowed;">
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
          <td style="font-size:12px"><?= htmlspecialchars($book['isbn'] ?? '') ?></td>
          <td><span class="badge badge-info"><?= htmlspecialchars($book['category'] ?? '') ?></span></td>
          <td><?= $book['total_copies'] ?></td>
          <td>
            <span class="badge <?= $book['available_copies'] > 0 ? 'badge-success' : 'badge-danger' ?>">
              <?= $book['available_copies'] ?>
            </span>
          </td>
          <td style="font-size:12px;color:#888"><?= htmlspecialchars($book['location'] ?? '') ?></td>
          <td>
            <a href="?action=edit&id=<?= $book['id'] ?>" class="btn btn-sm btn-outline">Edit</a>
            <form method="POST" style="display:inline" onsubmit="return confirm('Delete this book?')">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= $book['id'] ?>">
              <button type="submit" class="btn btn-sm btn-danger">Del</button>
            </form>
          </td>
        </tr>
      <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once '../includes/footer.php'; ?>
