<?php
require_once '../includes/config.php';
requireRole('admin');

$pageTitle     = 'Manage Users';
$activePage    = 'users';
$msg           = '';
$msgType       = 'success';
$action        = $_GET['action'] ?? '';
$formValues    = null;
$resetPassword = '';
$resetName     = '';

$allowedRoles = ['student', 'librarian', 'admin'];

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = $_POST['action'] ?? '';

    if ($postAction === 'add') {
        $sidRaw    = trim($_POST['student_id'] ?? '');
        $nameRaw   = trim($_POST['full_name'] ?? '');
        $emailRaw  = trim($_POST['email'] ?? '');
        $roleRaw   = $_POST['role'] ?? 'student';
        $passRaw   = $_POST['password'] ?? '';
        $phoneRaw  = trim($_POST['contact_number'] ?? '');

        $sid   = $sidRaw;
        $name  = $nameRaw;
        $email = $emailRaw;
        $phone = $phoneRaw;
        $role  = $roleRaw;

        $errors = [];
        if ($nameRaw === '') {
            $errors[] = 'Full name is required.';
        }
        if (!filter_var($emailRaw, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        }
        if (!in_array($roleRaw, $allowedRoles, true)) {
            $errors[] = 'Invalid role selected.';
        }
        if ($sidRaw !== '' && !preg_match('/^[A-Za-z0-9-]{4,20}$/', $sidRaw)) {
            $errors[] = 'Student ID must be 4-20 characters using letters, numbers, or dashes.';
        }
        if ($passRaw === '') {
            $errors[] = 'Password is required.';
        } elseif (strlen($passRaw) < 6) {
            $errors[] = 'Password must be at least 6 characters.';
        }

        $formValues = [
            'student_id'     => $sidRaw,
            'full_name'      => $nameRaw,
            'email'          => $emailRaw,
            'role'           => $roleRaw,
            'contact_number' => $phoneRaw,
        ];

        if (!empty($errors)) {
            $msgType = 'danger';
            $msg     = implode(' ', $errors);
            $action  = 'add';
        } else {
            $pass   = password_hash($passRaw, PASSWORD_DEFAULT);
            $sidVal = $sid !== '' ? $sid : null;

            try {
                dbExecute(
                    $conn,
                    "INSERT INTO users (student_id, full_name, email, password, role, contact_number)
                     VALUES (?, ?, ?, ?, ?, ?)",
                    'ssssss',
                    [$sidVal, $name, $email, $pass, $role, $phone]
                );
                logActivity($conn, $_SESSION['user_id'], 'add_user', "Added user: $name ($role)");
                header("Location: users.php?msg=added");
                exit();
            } catch (mysqli_sql_exception $e) {
                $msgType = 'danger';
                $msg     = (int)$e->getCode() === 1062
                    ? 'A user with that email or Student ID already exists.'
                    : 'Unable to add the user. Please try again.';
                $action  = 'add';
            }
        }
    } elseif ($postAction === 'toggle') {
        $id     = (int)($_POST['id'] ?? 0);
        $target = getUser($conn, $id);

        if ($id === (int)$_SESSION['user_id']) {
            $msgType = 'danger';
            $msg     = 'You cannot disable your own account.';
        } elseif (!$target) {
            $msgType = 'danger';
            $msg     = 'User not found.';
        } else {
            $lastActiveAdmin = false;
            if ($target['role'] === 'admin' && $target['status'] === 'active') {
                $otherAdmins = (int)dbSelect($conn, "SELECT COUNT(*) c FROM users WHERE role = 'admin' AND status = 'active' AND id <> ?", 'i', [$id])->fetch_assoc()['c'];
                $lastActiveAdmin = ($otherAdmins === 0);
            }

            if ($lastActiveAdmin) {
                $msgType = 'danger';
                $msg     = 'Cannot disable the last active administrator.';
            } else {
                dbExecute($conn, "UPDATE users SET status = IF(status='active','inactive','active') WHERE id = ?", 'i', [$id]);
                logActivity($conn, $_SESSION['user_id'], 'toggle_user', "Toggled status for user #$id");
                header("Location: users.php?msg=updated");
                exit();
            }
        }
    } elseif ($postAction === 'delete') {
        $id     = (int)($_POST['id'] ?? 0);
        $target = getUser($conn, $id);

        if ($id === (int)$_SESSION['user_id']) {
            $msgType = 'danger';
            $msg     = 'You cannot delete your own account.';
        } elseif (!$target) {
            $msgType = 'danger';
            $msg     = 'User not found.';
        } else {
            $records = (int)dbSelect($conn, "SELECT COUNT(*) c FROM borrow_records WHERE user_id = ?", 'i', [$id])->fetch_assoc()['c'];

            $lastActiveAdmin = false;
            if ($target['role'] === 'admin' && $target['status'] === 'active') {
                $otherAdmins = (int)dbSelect($conn, "SELECT COUNT(*) c FROM users WHERE role = 'admin' AND status = 'active' AND id <> ?", 'i', [$id])->fetch_assoc()['c'];
                $lastActiveAdmin = ($otherAdmins === 0);
            }

            if ($records > 0) {
                $msgType = 'danger';
                $msg     = "Cannot delete this user: they have $records borrowing record(s) in the system. Consider disabling the account instead.";
            } elseif ($lastActiveAdmin) {
                $msgType = 'danger';
                $msg     = 'Cannot delete the last active administrator.';
            } else {
                try {
                    dbExecute($conn, "DELETE FROM users WHERE id = ?", 'i', [$id]);
                    logActivity($conn, $_SESSION['user_id'], 'delete_user', "Deleted user #$id");
                    header("Location: users.php?msg=deleted");
                    exit();
                } catch (mysqli_sql_exception $e) {
                    $msgType = 'danger';
                    $msg     = 'Unable to delete this user because they are referenced by other records.';
                }
            }
        }
    } elseif ($postAction === 'reset_password') {
        $id   = (int)($_POST['id'] ?? 0);
        $user = getUser($conn, $id);

        if (!$user) {
            $msgType = 'danger';
            $msg     = 'User not found.';
        } else {
            $plain  = bin2hex(random_bytes(4));
            $hash   = password_hash($plain, PASSWORD_DEFAULT);
            dbExecute($conn, "UPDATE users SET password = ? WHERE id = ?", 'si', [$hash, $id]);
            logActivity($conn, $_SESSION['user_id'], 'reset_password', "Reset password for user #$id");
            $resetPassword = $plain;
            $resetName     = $user['full_name'];
        }
    }
}

$role_filter = trim($_GET['role'] ?? '');
if (!in_array($role_filter, $allowedRoles, true)) {
    $role_filter = '';
}
if ($role_filter !== '') {
    $users = dbSelect($conn, "SELECT * FROM users WHERE role = ? ORDER BY role, full_name", 's', [$role_filter]);
} else {
    $users = $conn->query("SELECT * FROM users ORDER BY role, full_name");
}

if ($msg === '' && isset($_GET['msg'])) {
    $map = ['added' => 'User added.', 'deleted' => 'User deleted.', 'updated' => 'Status updated.'];
    if (isset($map[$_GET['msg']])) {
        $msgType = 'success';
        $msg     = $map[$_GET['msg']];
    }
}

require_once '../includes/header.php';
?>

<div class="page-header">
  <h1>Manage Users</h1>
  <p>Manage student, librarian, and admin accounts.</p>
</div>

<?php if ($msg !== ''): ?>
  <div class="alert alert-<?= $msgType === 'danger' ? 'danger' : 'success' ?>"><?= $msgType === 'danger' ? '⚠️' : '✅' ?> <?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<?php if ($resetPassword !== ''): ?>
  <div class="alert alert-success">
    ✅ Password reset for <strong><?= htmlspecialchars($resetName) ?></strong>.
    New password: <strong><code><?= htmlspecialchars($resetPassword) ?></code></strong><br>
    Share it and ask them to change it from My Profile.
  </div>
<?php endif; ?>

<div style="display:flex;justify-content:space-between;margin-bottom:20px;gap:12px;">
  <div style="display:flex;gap:10px">
    <a href="users.php" class="btn btn-outline <?= !$role_filter?'btn-primary':'' ?>">All</a>
    <a href="?role=student" class="btn btn-outline <?= $role_filter==='student'?'btn-primary':'' ?>">Students</a>
    <a href="?role=librarian" class="btn btn-outline <?= $role_filter==='librarian'?'btn-primary':'' ?>">Librarians</a>
    <a href="?role=admin" class="btn btn-outline <?= $role_filter==='admin'?'btn-primary':'' ?>">Admins</a>
  </div>
  <a href="?action=add" class="btn btn-gold">+ Add User</a>
</div>

<?php if ($action === 'add'): ?>
<div class="card" style="margin-bottom:24px;">
  <div class="card-title">Add New User</div>
  <form method="POST">
    <?= csrfField() ?>
    <input type="hidden" name="action" value="add">
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;">
      <div class="form-group">
        <label>Full Name *</label>
        <input type="text" name="full_name" class="form-control" required value="<?= htmlspecialchars($formValues['full_name'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label>Email *</label>
        <input type="email" name="email" class="form-control" required value="<?= htmlspecialchars($formValues['email'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label>Role *</label>
        <select name="role" class="form-control">
          <option value="student" <?= ($formValues['role'] ?? '') === 'student' ? 'selected' : '' ?>>Student</option>
          <option value="librarian" <?= ($formValues['role'] ?? '') === 'librarian' ? 'selected' : '' ?>>Librarian</option>
          <option value="admin" <?= ($formValues['role'] ?? '') === 'admin' ? 'selected' : '' ?>>Admin</option>
        </select>
      </div>
      <div class="form-group">
        <label>Student ID</label>
        <input type="text" name="student_id" class="form-control" placeholder="e.g. 2024-0001" value="<?= htmlspecialchars($formValues['student_id'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label>Contact Number</label>
        <input type="text" name="contact_number" class="form-control" value="<?= htmlspecialchars($formValues['contact_number'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label>Password *</label>
        <input type="password" name="password" class="form-control" placeholder="Minimum 6 characters" required>
      </div>
    </div>
    <div style="display:flex;gap:10px">
      <button type="submit" class="btn btn-primary">Add User</button>
      <a href="users.php" class="btn btn-outline">Cancel</a>
    </div>
  </form>
</div>
<?php endif; ?>

<div class="card">
  <div class="table-wrap">
    <table>
      <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Student ID</th><th>Status</th><th>Joined</th><th>Actions</th></tr></thead>
      <tbody>
      <?php while ($u = $users->fetch_assoc()): ?>
        <tr>
          <td>
            <div style="width:30px;height:30px;border-radius:50%;background:<?= ['admin'=>'#e74c3c','librarian'=>'#2980b9','student'=>'#27ae60'][$u['role']] ?>;display:inline-flex;align-items:center;justify-content:center;color:white;font-size:12px;font-weight:600;margin-right:8px;vertical-align:middle">
              <?= htmlspecialchars(initial($u['full_name'])) ?>
            </div>
            <?= htmlspecialchars($u['full_name']) ?>
          </td>
          <td style="font-size:13px"><?= htmlspecialchars($u['email']) ?></td>
          <td>
            <span class="badge <?= ['admin'=>'badge-danger','librarian'=>'badge-info','student'=>'badge-success'][$u['role']] ?>">
              <?= ucfirst($u['role']) ?>
            </span>
          </td>
          <td style="font-size:12px;color:#888"><?= htmlspecialchars($u['student_id'] ?? '-') ?></td>
          <td>
            <span class="badge <?= $u['status']==='active'?'badge-success':'badge-secondary' ?>">
              <?= ucfirst($u['status']) ?>
            </span>
          </td>
          <td style="font-size:12px;color:#888"><?= date('M d, Y', strtotime($u['created_at'])) ?></td>
          <td>
            <?php if ((int)$u['id'] !== (int)$_SESSION['user_id']): ?>
            <form method="POST" style="display:inline">
              <?= csrfField() ?>
              <input type="hidden" name="action" value="toggle">
              <input type="hidden" name="id" value="<?= $u['id'] ?>">
              <button type="submit" class="btn btn-sm btn-outline"><?= $u['status']==='active'?'Disable':'Enable' ?></button>
            </form>
            <?php endif; ?>
            <form method="POST" style="display:inline" onsubmit="return confirm(<?= htmlspecialchars(json_encode('Reset the password for ' . $u['full_name'] . '?'), ENT_QUOTES) ?>)">
              <?= csrfField() ?>
              <input type="hidden" name="action" value="reset_password">
              <input type="hidden" name="id" value="<?= $u['id'] ?>">
              <button type="submit" class="btn btn-sm btn-outline">Reset PW</button>
            </form>
            <?php if ((int)$u['id'] !== (int)$_SESSION['user_id']): ?>
            <form method="POST" style="display:inline" onsubmit="return confirm('Delete this user?')">
              <?= csrfField() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= $u['id'] ?>">
              <button type="submit" class="btn btn-sm btn-danger">Del</button>
            </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once '../includes/footer.php'; ?>
