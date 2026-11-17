<?php
if (!defined('SITE_URL')) {
    http_response_code(403);
    exit('Forbidden');
}

function overdueCount($conn): int {
    $row = dbSelect($conn,
        "SELECT COUNT(*) AS c FROM borrow_records WHERE status = 'borrowed' AND due_date < CURDATE()"
    )->fetch_assoc();
    return (int)($row['c'] ?? 0);
}

function overdueRows($conn, int $limit = 0, int $offset = 0) {
    $sql = "SELECT br.id, br.user_id, br.book_id, br.due_date, u.full_name, u.student_id, b.title
            FROM borrow_records br
            JOIN users u ON u.id = br.user_id
            JOIN books b ON b.id = br.book_id
            WHERE br.status = 'borrowed' AND br.due_date < CURDATE()
            ORDER BY br.due_date ASC, u.full_name ASC, u.student_id ASC, b.title ASC, br.id ASC";
    if ($limit > 0) {
        $sql .= ' LIMIT ? OFFSET ?';
        return dbSelect($conn, $sql, 'ii', [$limit, $offset]);
    }
    return $conn->query($sql);
}

function overdueDetails(array $row): array {
    return ['days' => daysOverdue($row['due_date']), 'fine' => calculateFine($row['due_date'])];
}
?>
