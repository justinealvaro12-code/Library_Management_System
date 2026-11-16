<?php
require_once '../includes/config.php';
requireRole('admin');

$pageTitle  = 'Settings';
$activePage = 'settings';
$errors     = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $library_name = trim($_POST['library_name'] ?? '');
    $loan_period  = (int)($_POST['loan_period_days'] ?? 0);
    $fine_per_day = (float)($_POST['fine_per_day'] ?? 0);
    $max_books    = (int)($_POST['max_books_per_student'] ?? 0);
    $block_unpaid_fines = isset($_POST['block_unpaid_fines']) && $_POST['block_unpaid_fines'] === '1' ? '1' : '0';

    if ($library_name === '') {
        $errors[] = 'Library name is required.';
    }
    if ($loan_period < 1 || $loan_period > 60) {
        $errors[] = 'Loan period must be between 1 and 60 days.';
    }
    if ($fine_per_day < 0) {
        $errors[] = 'Fine per day cannot be negative.';
    }
    if ($max_books < 1) {
        $errors[] = 'Max books per student must be at least 1.';
    }

    if (empty($errors)) {
        $values = [
            'library_name'             => $library_name,
            'loan_period_days'         => (string)$loan_period,
            'fine_per_day'             => number_format($fine_per_day, 2, '.', ''),
            'max_books_per_student'    => (string)$max_books,
            'block_unpaid_fines'       => $block_unpaid_fines,
        ];

        foreach ($values as $key => $value) {
            dbExecute(
                $conn,
                "INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)",
                'ss',
                [$key, $value]
            );
        }

        logActivity($conn, $_SESSION['user_id'], 'update_settings', 'System settings updated');
        header("Location: settings.php?msg=saved");
        exit();
    }

    $settings = [
        'library_name'            => $_POST['library_name'] ?? '',
        'loan_period_days'        => $_POST['loan_period_days'] ?? '',
        'fine_per_day'            => $_POST['fine_per_day'] ?? '',
        'max_books_per_student'   => $_POST['max_books_per_student'] ?? '',
        'block_unpaid_fines'      => $block_unpaid_fines,
    ];
} else {
    $settings = [];
    $res = $conn->query("SELECT * FROM settings");
    while ($r = $res->fetch_assoc()) {
        $settings[$r['setting_key']] = $r['setting_value'];
    }
}

require_once '../includes/header.php';
?>

<div class="page-header">
  <h1>System Settings</h1>
  <p>Configure library rules and settings.</p>
</div>

<?php if (isset($_GET['msg'])): ?>
  <div class="alert alert-success">✅ Settings saved successfully.</div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
  <div class="alert alert-danger">⚠️ <?= htmlspecialchars(implode(' ', $errors)) ?></div>
<?php endif; ?>

<div class="card" style="max-width:600px">
  <form method="POST">
    <?= csrfField() ?>
    <div class="form-group">
      <label>Library Name</label>
      <input type="text" name="library_name" class="form-control" value="<?= htmlspecialchars($settings['library_name'] ?? '') ?>">
    </div>
    <div class="form-group">
      <label>Loan Period (days)</label>
      <input type="number" name="loan_period_days" class="form-control" min="1" max="60" value="<?= htmlspecialchars($settings['loan_period_days'] ?? 7) ?>">
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
      <label style="display:flex;align-items:center;gap:8px;font-weight:500">
        <input type="checkbox" name="block_unpaid_fines" value="1" <?= (($settings['block_unpaid_fines'] ?? '0') === '1') ? 'checked' : '' ?>>
        Block borrowing when student has unpaid fines
      </label>
      <small style="color:#888;font-size:12px">When enabled, students with unpaid fines (returned records with fine &gt; 0 and not marked paid) cannot borrow new books.</small>
    </div>
    <button type="submit" class="btn btn-primary">Save Settings</button>
  </form>
</div>

<?php require_once '../includes/footer.php'; ?>
