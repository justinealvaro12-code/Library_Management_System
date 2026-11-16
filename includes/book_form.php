<?php
if (!defined('SITE_URL')) { http_response_code(403); exit('Forbidden'); }
require_once __DIR__ . '/book_cover.php';
?>
<?php if ($action === 'add' || $action === 'edit'): ?>
<div class="card" style="margin-bottom:24px;">
  <div class="card-title"><?= $action === 'edit' ? 'Edit Book' : 'Add New Book' ?></div>
  <form method="POST" enctype="multipart/form-data">
    <?= csrfField() ?>
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
      <div class="form-group" style="grid-column:span 3">
        <label>Cover Image</label>
        <div class="cover-form-row">
          <img src="<?= htmlspecialchars(bookCoverUrl($editBook['cover_image'] ?? '')) ?>" alt="Book cover preview" class="cover-preview">
          <div style="flex:1;min-width:220px">
            <input type="file" name="cover_image" class="form-control" accept="image/jpeg,image/png,image/webp">
            <p class="form-hint">JPG, PNG, or WebP. Maximum 2 MB.</p>
            <?php if (!empty($editBook['cover_image'])): ?>
              <label class="checkbox-row"><input type="checkbox" name="remove_cover" value="1"> Remove current cover</label>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
    <div style="display:flex;gap:10px">
      <button type="submit" class="btn btn-primary"><?= $action === 'edit' ? 'Update Book' : 'Add Book' ?></button>
      <a href="books.php" class="btn btn-outline">Cancel</a>
    </div>
  </form>
</div>
<?php endif; ?>
