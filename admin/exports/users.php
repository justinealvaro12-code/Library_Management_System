<?php
require_once '../../includes/config.php';
require_once '../../includes/export.php';
requireRole('admin');

$filters = exportAllowedFilters(['role']);
$role = isset($filters['role']) ? trim((string)$filters['role']) : '';
if (!in_array($role, ['','student','librarian','admin'], true)) { $role = ''; }
$where = '';$types='';$params=[];
if ($role !== '') { $where = "WHERE role = ?"; $types='s'; $params=[$role]; }

logActivity($conn, $_SESSION['user_id'], 'export_csv', 'Exported users CSV' . ($role ? " (role=$role)" : ''));
$out = csvStart('users_' . date('Y-m-d') . '.csv', ['ID','Student ID','Full Name','Email','Role','Contact Number','Status','Created At']);
$stmt = exportPrepared($conn, "SELECT id,student_id,full_name,email,role,contact_number,status,created_at FROM users $where ORDER BY role, full_name", $types, $params);
$stmt->bind_result($id, $studentId, $fullName, $email, $userRole, $contact, $status, $createdAt);
while ($stmt->fetch()) {
    csvRow($out, [$id, $studentId, $fullName, $email, $userRole, $contact, $status, $createdAt]);
}
$stmt->close();
fclose($out);
exit();
?>
