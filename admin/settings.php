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
    $reservationExpiryInput = filter_var($_POST['reservation_expiry_days'] ?? null, FILTER_VALIDATE_INT);
    $maxActiveInput = filter_var($_POST['max_active_reservations'] ?? null, FILTER_VALIDATE_INT);
    $reservation_expiry = $reservationExpiryInput === false ? 0 : (int)$reservationExpiryInput;
    $max_active_reservations = $maxActiveInput === false ? 0 : (int)$maxActiveInput;
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
    if ($reservation_expiry < 1 || $reservation_expiry > 30) {
        $errors[] = 'Reservation pickup window must be between 1 and 30 days.';
    }
    if ($max_active_reservations < 1 || $max_active_reservations > 10) {
        $errors[] = 'Max active reservations must be between 1 and 10.';
    }

    if (empty($errors)) {
        $values = [
            'library_name'             => $library_name,
            'loan_period_days'         => (string)$loan_period,
            'fine_per_day'             => number_format($fine_per_day, 2, '.', ''),
            'max_books_per_student'    => (string)$max_books,
            'reservation_expiry_days'  => (string)$reservation_expiry,
            'max_active_reservations'  => (string)$max_active_reservations,
            'block_unpaid_fines'       => $block_unpaid_fines,
        ];

        $conn->begin_transaction();
        try {
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
            $conn->commit();
            header("Location: settings.php?msg=saved");
            exit();
        } catch (Throwable $e) {
            $conn->rollback();
            error_log('Settings update failed: ' . $e->getMessage());
            $errors[] = 'Unable to save settings. Please try again.';
        }
    }

    $settings = [
        'library_name'            => $_POST['library_name'] ?? '',
        'loan_period_days'        => $_POST['loan_period_days'] ?? '',
        'fine_per_day'            => $_POST['fine_per_day'] ?? '',
        'max_books_per_student'   => $_POST['max_books_per_student'] ?? '',
        'reservation_expiry_days' => $_POST['reservation_expiry_days'] ?? '',
        'max_active_reservations' => $_POST['max_active_reservations'] ?? '',
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
      <label>Reservation Pickup Window (days)</label>
      <input type="number" name="reservation_expiry_days" class="form-control" min="1" max="30" value="<?= htmlspecialchars($settings['reservation_expiry_days'] ?? 2) ?>">
      <small style="color:#888;font-size:12px">How long a ready reservation is held for pickup.</small>
    </div>
    <div class="form-group">
      <label>Max Active Reservations Per Student</label>
      <input type="number" name="max_active_reservations" class="form-control" min="1" max="10" value="<?= htmlspecialchars($settings['max_active_reservations'] ?? 3) ?>">
      <small style="color:#888;font-size:12px">Limits how many waiting or ready reservations one student can hold.</small>
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
