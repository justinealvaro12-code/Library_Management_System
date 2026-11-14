<?php
$pageTitle  = 'My Profile';
$activePage = 'profile';
$uid        = (int)$_SESSION['user_id'];
$user       = getUser($conn, $uid);

if (!$user) {
    redirect(SITE_URL . '/index.php');
}

$role      = $user['role'];
$roleNames = ['admin' => 'Administrator', 'librarian' => 'Librarian', 'student' => 'Student'];
$roleBadges = ['admin' => 'badge-danger', 'librarian' => 'badge-info', 'student' => 'badge-success'];
$roleColors = ['admin' => '#e74c3c', 'librarian' => '#2980b9', 'student' => '#27ae60'];
$roleName   = $roleNames[$role] ?? ucfirst($role);
$roleBadge  = $roleBadges[$role] ?? 'badge-secondary';
$roleColor  = $roleColors[$role] ?? '#555';

$success = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name      = trim($_POST['full_name'] ?? '');
    $contact_number = trim($_POST['contact_number'] ?? '');
    $address        = trim($_POST['address'] ?? '');
    $new_pass       = $_POST['new_password'] ?? '';
    $confirm        = $_POST['confirm_password'] ?? '';
    $current_pass   = $_POST['current_password'] ?? '';
    $errors         = [];

    if ($full_name === '') {
        $errors[] = 'Full name is required.';
    }

    if ($new_pass !== '') {
        if (!password_verify($current_pass, $user['password'])) {
            $errors[] = 'Current password is incorrect.';
        } elseif (strlen($new_pass) < 6) {
            $errors[] = 'New password must be at least 6 characters.';
        } elseif ($new_pass !== $confirm) {
            $errors[] = 'New passwords do not match.';
        }
    }

    if (empty($errors)) {
        if ($new_pass !== '') {
            $hashed = password_hash($new_pass, PASSWORD_DEFAULT);
            dbExecute(
                $conn,
                "UPDATE users SET full_name = ?, contact_number = ?, address = ?, password = ? WHERE id = ?",
                'ssssi',
                [$full_name, $contact_number, $address, $hashed, $uid]
            );
        } else {
            dbExecute(
                $conn,
                "UPDATE users SET full_name = ?, contact_number = ?, address = ? WHERE id = ?",
                'sssi',
                [$full_name, $contact_number, $address, $uid]
            );
        }

        $_SESSION['user_name'] = $full_name;
        $user = getUser($conn, $uid);

        $success = $new_pass !== ''
            ? 'Profile and password updated successfully.'
            : 'Profile updated successfully.';
    } else {
        $error = implode(' ', $errors);
    }
}

require_once __DIR__ . '/header.php';
?>

<div class="page-header">
  <h1>My Profile</h1>
  <p>View and update your account information.</p>
</div>

<?php if ($success): ?><div class="alert alert-success">✅ <?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($error):   ?><div class="alert alert-danger">⚠️ <?= htmlspecialchars($error) ?></div><?php endif; ?>

<div style="display:grid;grid-template-columns:280px 1fr;gap:24px;align-items:start;">

  <!-- Profile Card -->
  <div class="card" style="text-align:center;padding:32px 20px;">
    <div style="width:80px;height:80px;border-radius:50%;background:<?= $roleColor ?>;display:flex;align-items:center;justify-content:center;color:white;font-size:32px;font-weight:700;margin:0 auto 16px;">
      <?= htmlspecialchars(initial($user['full_name'])) ?>
    </div>
    <div style="font-size:18px;font-weight:600;color:#0f1e38;"><?= htmlspecialchars($user['full_name']) ?></div>
    <div style="margin:8px 0;">
      <span class="badge <?= $roleBadge ?>"><?= htmlspecialchars($roleName) ?></span>
    </div>
    <div style="font-size:13px;color:#888;margin-top:6px;"><?= htmlspecialchars($user['email']) ?></div>
    <?php if ($role === 'student'): ?>
      <div style="margin-top:10px;padding:8px 12px;background:#f4f6fb;border-radius:6px;font-size:13px;">
        <span style="color:#999;">Student ID</span><br>
        <strong style="color:#0f1e38;"><?= htmlspecialchars($user['student_id'] ?? '—') ?></strong>
      </div>
      <div style="margin-top:10px;font-size:12px;color:#aaa;">
        Member since <?= date('F Y', strtotime($user['created_at'])) ?>
      </div>
    <?php else: ?>
      <div style="margin-top:10px;padding:8px 12px;background:#f4f6fb;border-radius:6px;font-size:13px;">
        <span style="color:#999;">Role</span><br>
        <strong style="color:#0f1e38;"><?= htmlspecialchars($roleName) ?></strong>
      </div>
      <div style="margin-top:10px;font-size:12px;color:#aaa;">
        Account since <?= date('F Y', strtotime($user['created_at'])) ?>
      </div>
    <?php endif; ?>
  </div>

  <!-- Edit Form -->
  <div class="card">
    <div class="card-title">Edit Profile</div>
    <form method="POST">
      <?= csrfField() ?>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
        <div class="form-group">
          <label>Full Name *</label>
          <input type="text" name="full_name" class="form-control" required value="<?= htmlspecialchars($user['full_name']) ?>">
        </div>
        <div class="form-group">
          <label>Email Address</label>
          <input type="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" disabled style="background:#f0f0f0;cursor:not-allowed;">
          <small style="color:#999;font-size:11px;">Email cannot be changed.</small>
        </div>
        <?php if ($role === 'student'): ?>
        <div class="form-group">
          <label>Student ID</label>
          <input type="text" class="form-control" value="<?= htmlspecialchars($user['student_id'] ?? '') ?>" disabled style="background:#f0f0f0;cursor:not-allowed;">
        </div>
        <?php else: ?>
        <div class="form-group">
          <label>Role</label>
          <input type="text" class="form-control" value="<?= htmlspecialchars($roleName) ?>" disabled style="background:#f0f0f0;cursor:not-allowed;">
        </div>
        <?php endif; ?>
        <div class="form-group">
          <label>Contact Number</label>
          <input type="text" name="contact_number" class="form-control" placeholder="09XXXXXXXXX" value="<?= htmlspecialchars($user['contact_number'] ?? '') ?>">
        </div>
        <div class="form-group" style="grid-column:1/-1;">
          <label>Address</label>
          <textarea name="address" class="form-control" style="resize:vertical;min-height:70px;"><?= htmlspecialchars($user['address'] ?? '') ?></textarea>
        </div>
      </div>

      <hr style="border:none;border-top:1px solid #eee;margin:20px 0;">
      <div class="card-title" style="margin-bottom:16px;">Change Password <span style="font-size:12px;color:#999;font-weight:400;">(leave blank to keep current)</span></div>
      <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;">
        <div class="form-group">
          <label>Current Password</label>
          <input type="password" name="current_password" class="form-control" placeholder="Current password">
        </div>
        <div class="form-group">
          <label>New Password</label>
          <input type="password" name="new_password" class="form-control" placeholder="Min. 6 characters">
        </div>
        <div class="form-group">
          <label>Confirm New Password</label>
          <input type="password" name="confirm_password" class="form-control" placeholder="Repeat new password">
        </div>
      </div>

      <div style="display:flex;gap:10px;margin-top:8px;">
        <button type="submit" class="btn btn-primary">Save Changes</button>
        <a href="<?= SITE_URL ?>/<?= htmlspecialchars($role) ?>/dashboard.php" class="btn btn-outline">Cancel</a>
      </div>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
