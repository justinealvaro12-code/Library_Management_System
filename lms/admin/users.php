<?php
require_once '../includes/config.php';
requireRole('admin');

$pageTitle  = 'Manage Users';
$activePage = 'users';

// Add user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    $sid    = sanitize($conn, $_POST['student_id'] ?? '');
    $name   = sanitize($conn, $_POST['full_name'] ?? '');
    $email  = sanitize($conn, $_POST['email'] ?? '');
    $role   = sanitize($conn, $_POST['role'] ?? 'student');
    $pass   = password_hash($_POST['password'] ?? 'password', PASSWORD_DEFAULT);
    $phone  = sanitize($conn, $_POST['contact_number'] ?? '');
    $sid_val = $sid ? "'$sid'" : "NULL";
    $conn->query("INSERT INTO users (student_id, full_name, email, password, role, contact_number) VALUES ($sid_val, '$name', '$email', '$pass', '$role', '$phone')");
    logActivity($conn, $_SESSION['user_id'], 'add_user', "Added user: $name ($role)");
    header("Location: users.php?msg=added"); exit();
}

// Toggle status
if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $conn->query("UPDATE users SET status = IF(status='active','inactive','active') WHERE id=$id");
    header("Location: users.php?msg=updated"); exit();
}

// Delete
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $conn->query("DELETE FROM users WHERE id=$id");
    header("Location: users.php?msg=deleted"); exit();
}

$role_filter = sanitize($conn, $_GET['role'] ?? '');
$where = $role_filter ? "WHERE role='$role_filter'" : '';
$users = $conn->query("SELECT * FROM users $where ORDER BY role, full_name");
$action = $_GET['action'] ?? '';

require_once '../includes/header.php';
?>

<div class="page-header">
  <h1>Manage Users</h1>
  <p>Manage student, librarian, and admin accounts.</p>
</div>

<?php if (isset($_GET['msg'])): ?>
  <div class="alert alert-success">✅ <?= ['added'=>'User added.','deleted'=>'User deleted.','updated'=>'Status updated.'][$_GET['msg']] ?? '' ?></div>
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
    <input type="hidden" name="action" value="add">
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;">
      <div class="form-group">
        <label>Full Name *</label>
        <input type="text" name="full_name" class="form-control" required>
      </div>
      <div class="form-group">
        <label>Email *</label>
        <input type="email" name="email" class="form-control" required>
      </div>
      <div class="form-group">
        <label>Role *</label>
        <select name="role" class="form-control">
          <option value="student">Student</option>
          <option value="librarian">Librarian</option>
          <option value="admin">Admin</option>
        </select>
      </div>
      <div class="form-group">
        <label>Student ID</label>
        <input type="text" name="student_id" class="form-control" placeholder="e.g. 2024-0001">
      </div>
      <div class="form-group">
        <label>Contact Number</label>
        <input type="text" name="contact_number" class="form-control">
      </div>
      <div class="form-group">
        <label>Password</label>
        <input type="password" name="password" class="form-control" placeholder="Default: password">
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
              <?= strtoupper(substr($u['full_name'],0,1)) ?>
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
            <a href="?toggle=<?= $u['id'] ?>" class="btn btn-sm btn-outline"><?= $u['status']==='active'?'Disable':'Enable' ?></a>
            <?php if ($u['id'] !== $_SESSION['user_id']): ?>
            <a href="?delete=<?= $u['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this user?')">Del</a>
            <?php endif; ?>
          </td>
        </tr>
      <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once '../includes/footer.php'; ?>
