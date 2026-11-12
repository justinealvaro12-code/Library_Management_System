<?php
require_once '../includes/config.php';
requireRole('admin');

$pageTitle  = 'Settings';
$activePage = 'settings';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach (['library_name','loan_period_days','fine_per_day','max_books_per_student','reservation_expiry_days'] as $key) {
        $val = sanitize($conn, $_POST[$key] ?? '');
        $conn->query("UPDATE settings SET setting_value='$val' WHERE setting_key='$key'");
    }
    logActivity($conn, $_SESSION['user_id'], 'update_settings', 'System settings updated');
    header("Location: settings.php?msg=saved"); exit();
}

$settings = [];
$res = $conn->query("SELECT * FROM settings");
while ($r = $res->fetch_assoc()) $settings[$r['setting_key']] = $r['setting_value'];

require_once '../includes/header.php';
?>

<div class="page-header">
  <h1>System Settings</h1>
  <p>Configure library rules and settings.</p>
</div>

<?php if (isset($_GET['msg'])): ?>
  <div class="alert alert-success">✅ Settings saved successfully.</div>
<?php endif; ?>

<div class="card" style="max-width:600px">
  <form method="POST">
    <div class="form-group">
      <label>Library Name</label>
      <input type="text" name="library_name" class="form-control" value="<?= htmlspecialchars($settings['library_name'] ?? '') ?>">
    </div>
    <div class="form-group">
      <label>Loan Period (days)</label>
      <input type="number" name="loan_period_days" class="form-control" min="1" value="<?= htmlspecialchars($settings['loan_period_days'] ?? 7) ?>">
      <small style="color:#888;font-size:12px">How many days students can borrow a book.</small>
    </div>
    <div class="form-group">
      <label>Fine Per Day (₱)</label>
      <input type="number" name="fine_per_day" class="form-control" step="0.50" min="0" value="<?= htmlspecialchars($settings['fine_per_day'] ?? 5) ?>">
    </div>
    <div class="form-group">
      <label>Max Books Per Student</label>
      <input type="number" name="max_books_per_student" class="form-control" min="1" value="<?= htmlspecialchars($settings['max_books_per_student'] ?? 3) ?>">
    </div>
    <div class="form-group">
      <label>Reservation Expiry (days)</label>
      <input type="number" name="reservation_expiry_days" class="form-control" min="1" value="<?= htmlspecialchars($settings['reservation_expiry_days'] ?? 2) ?>">
    </div>
    <button type="submit" class="btn btn-primary">Save Settings</button>
  </form>
</div>

<?php require_once '../includes/footer.php'; ?>
