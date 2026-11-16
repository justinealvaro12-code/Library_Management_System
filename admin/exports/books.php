<?php
require_once '../../includes/config.php';
require_once '../../includes/export.php';
requireRole('admin');

$filters = exportAllowedFilters(['q']);
$searchRaw = isset($filters['q']) ? trim((string)$filters['q']) : '';
$where = '';
$types = '';
$params = [];
if ($searchRaw !== '') {
    $like = '%' . $searchRaw . '%';
    $where = "WHERE title LIKE ? OR author LIKE ? OR isbn LIKE ?";
    $types = 'sss'; $params = [$like,$like,$like];
}
logActivity($conn, $_SESSION['user_id'], 'export_csv', 'Exported books CSV');
$out = csvStart('books_' . date('Y-m-d') . '.csv', ['ID','ISBN','Title','Author','Publisher','Category','Year','Total Copies','Available Copies','Location','Status']);
$stmt = exportPrepared($conn, "SELECT id,isbn,title,author,publisher,category,year_published,total_copies,available_copies,location,status FROM books $where ORDER BY title ASC", $types, $params);
$stmt->bind_result($id, $isbn, $title, $author, $publisher, $category, $year, $total, $available, $location, $status);
while ($stmt->fetch()) {
    csvRow($out, [$id, $isbn, $title, $author, $publisher, $category, $year, $total, $available, $location, $status]);
}
$stmt->close();
fclose($out);
exit();
?>
