<?php
require_once '../../includes/config.php';
require_once '../../includes/export.php';
require_once '../../includes/report_data.php';
requireRole('librarian');

$range = reportRange($_GET);
$report = reportData($conn, $range['from'], $range['to']);

logActivity($conn, $_SESSION['user_id'], 'export_csv', 'Exported reports CSV (' . $range['from'] . ' to ' . $range['to'] . ')');
$out = csvStart('reports_' . date('Y-m-d') . '.csv', ['Section','Label','Value1','Value2']);
csvRow($out, ['range', 'from', $range['from'], $range['to']]);
if ($range['message'] !== '') {
    csvRow($out, ['range', 'warning', $range['message'], '']);
}

$k = $report['kpis'];
csvRow($out, ['kpi', 'borrows', $k['borrows'], '']);
csvRow($out, ['kpi', 'returns', $k['returns'], '']);
csvRow($out, ['kpi', 'overdue_at_range_end', $k['overdue'], '']);
csvRow($out, ['kpi', 'fines_paid_returns_in_range', number_format((float)$k['fines_paid'], 2, '.', ''), '']);
csvRow($out, ['kpi', 'fines_unpaid_returns_in_range', number_format((float)$k['fines_unpaid'], 2, '.', ''), '']);
csvRow($out, ['kpi', 'active_borrowers', $k['active_borrowers'], '']);

foreach ($report['borrows_over_time']['labels'] as $i => $label) {
    csvRow($out, ['borrows_over_time', $label, $report['borrows_over_time']['values'][$i], '']);
}
foreach ($report['top_books'] as $b) {
    csvRow($out, ['top_books', $b['title'], $b['author'], $b['count']]);
}
foreach ($report['top_borrowers'] as $b) {
    csvRow($out, ['top_borrowers', $b['full_name'], $b['student_id'] ?? '', $b['count']]);
}
foreach ($report['overdue_rows'] as $o) {
    csvRow($out, ['overdue', $o['full_name'] . ' / ' . $o['title'], $o['due_date'], $o['days'] . ' days / ' . number_format((float)$o['fine'], 2, '.', '')]);
}

fclose($out);
exit();
