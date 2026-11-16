<?php
if (!defined('SITE_URL')) { http_response_code(403); exit('Forbidden'); }

function reportDate($value) {
    $value = trim((string)$value);
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m)) {
        return null;
    }
    if (!checkdate((int)$m[2], (int)$m[3], (int)$m[1])) {
        return null;
    }
    $dt = DateTime::createFromFormat('!Y-m-d', $value);
    return $dt ? $dt->format('Y-m-d') : null;
}

function reportRange($input) {
    $today = new DateTime('today');
    $defaultTo = $today->format('Y-m-d');
    $defaultFrom = (clone $today)->modify('-29 days')->format('Y-m-d');

    $fromRaw = trim((string)($input['from'] ?? ''));
    $toRaw   = trim((string)($input['to'] ?? ''));

    $from = reportDate($fromRaw);
    $to   = reportDate($toRaw);
    $message = '';

    if ($fromRaw !== '' || $toRaw !== '') {
        if ($from === null || $to === null) {
            $message = 'Invalid date range. Showing the last 30 days instead.';
            $from = $defaultFrom;
            $to = $defaultTo;
        } elseif ($from > $to) {
            $message = 'Start date must be on or before end date. Showing the last 30 days instead.';
            $from = $defaultFrom;
            $to = $defaultTo;
        }
    } else {
        $from = $defaultFrom;
        $to = $defaultTo;
    }

    return [
        'from' => $from,
        'to' => $to,
        'default_from' => $defaultFrom,
        'default_to' => $defaultTo,
        'message' => $message,
        'is_default' => ($from === $defaultFrom && $to === $defaultTo),
    ];
}

function reportScalar($conn, $sql, $types, $params) {
    $res = dbSelect($conn, $sql, $types, $params);
    $row = $res ? $res->fetch_row() : null;
    return $row ? $row[0] : 0;
}

function reportBorrowsOverTime($conn, $from, $to) {
    $fromDate = new DateTime($from);
    $toDate = new DateTime($to);
    $spanDays = (int)$fromDate->diff($toDate)->days;

    $labels = [];
    $values = [];
    $map = [];

    if ($spanDays > 62) {
        $res = dbSelect(
            $conn,
            "SELECT DATE_FORMAT(issue_date, '%Y-%m') k, COUNT(*) c
             FROM borrow_records
             WHERE issue_date BETWEEN ? AND ?
             GROUP BY k ORDER BY k ASC",
            'ss',
            [$from, $to]
        );
        while ($row = $res->fetch_assoc()) {
            $map[$row['k']] = (int)$row['c'];
        }
        $cursor = new DateTime($fromDate->format('Y-m-01'));
        $end = new DateTime($toDate->format('Y-m-01'));
        while ($cursor <= $end) {
            $key = $cursor->format('Y-m');
            $labels[] = $cursor->format('M Y');
            $values[] = $map[$key] ?? 0;
            $cursor->modify('+1 month');
        }
        $granularity = 'month';
    } else {
        $res = dbSelect(
            $conn,
            "SELECT issue_date k, COUNT(*) c
             FROM borrow_records
             WHERE issue_date BETWEEN ? AND ?
             GROUP BY k ORDER BY k ASC",
            'ss',
            [$from, $to]
        );
        while ($row = $res->fetch_assoc()) {
            $map[$row['k']] = (int)$row['c'];
        }
        $cursor = clone $fromDate;
        while ($cursor <= $toDate) {
            $key = $cursor->format('Y-m-d');
            $labels[] = $cursor->format('M j');
            $values[] = $map[$key] ?? 0;
            $cursor->modify('+1 day');
        }
        $granularity = 'day';
    }

    return ['labels' => $labels, 'values' => $values, 'granularity' => $granularity];
}

function reportData($conn, $from, $to) {
    $borrows = (int)reportScalar(
        $conn,
        "SELECT COUNT(*) FROM borrow_records WHERE issue_date BETWEEN ? AND ?",
        'ss',
        [$from, $to]
    );

    $returns = (int)reportScalar(
        $conn,
        "SELECT COUNT(*) FROM borrow_records
         WHERE status = 'returned' AND return_date IS NOT NULL AND return_date BETWEEN ? AND ?",
        'ss',
        [$from, $to]
    );

    $overdue = (int)reportScalar(
        $conn,
        "SELECT COUNT(*) FROM borrow_records
         WHERE issue_date <= ? AND due_date < ?
           AND (return_date IS NULL OR return_date > ?)",
        'sss',
        [$to, $to, $to]
    );

    $finesPaid = (float)reportScalar(
        $conn,
        "SELECT COALESCE(SUM(fine_amount), 0) FROM borrow_records
         WHERE status = 'returned' AND return_date BETWEEN ? AND ?
           AND fine_amount > 0 AND fine_paid = 'yes'",
        'ss',
        [$from, $to]
    );

    $finesUnpaid = (float)reportScalar(
        $conn,
        "SELECT COALESCE(SUM(fine_amount), 0) FROM borrow_records
         WHERE status = 'returned' AND return_date BETWEEN ? AND ?
           AND fine_amount > 0 AND fine_paid = 'no'",
        'ss',
        [$from, $to]
    );

    $activeBorrowers = (int)reportScalar(
        $conn,
        "SELECT COUNT(DISTINCT user_id) FROM borrow_records WHERE issue_date BETWEEN ? AND ?",
        'ss',
        [$from, $to]
    );

    $topBooks = [];
    $res = dbSelect(
        $conn,
        "SELECT b.title, b.author, COUNT(*) c
         FROM borrow_records br JOIN books b ON br.book_id = b.id
         WHERE br.issue_date BETWEEN ? AND ?
         GROUP BY b.id, b.title, b.author
         ORDER BY c DESC, b.title ASC LIMIT 5",
        'ss',
        [$from, $to]
    );
    while ($row = $res->fetch_assoc()) {
        $topBooks[] = ['title' => $row['title'], 'author' => $row['author'], 'count' => (int)$row['c']];
    }

    $topBorrowers = [];
    $res = dbSelect(
        $conn,
        "SELECT u.full_name, u.student_id, COUNT(*) c
         FROM borrow_records br JOIN users u ON br.user_id = u.id
         WHERE br.issue_date BETWEEN ? AND ?
         GROUP BY u.id, u.full_name, u.student_id
         ORDER BY c DESC, u.full_name ASC LIMIT 5",
        'ss',
        [$from, $to]
    );
    while ($row = $res->fetch_assoc()) {
        $topBorrowers[] = ['full_name' => $row['full_name'], 'student_id' => $row['student_id'], 'count' => (int)$row['c']];
    }

    $overdueRows = [];
    $res = dbSelect(
        $conn,
        "SELECT br.due_date, u.full_name, u.student_id, b.title
         FROM borrow_records br
         JOIN users u ON br.user_id = u.id
         JOIN books b ON br.book_id = b.id
         WHERE br.issue_date <= ? AND br.due_date < ?
           AND (br.return_date IS NULL OR br.return_date > ?)
         ORDER BY br.due_date ASC LIMIT 10",
        'sss',
        [$to, $to, $to]
    );
    $toTs = strtotime($to . ' 00:00:00');
    while ($row = $res->fetch_assoc()) {
        $days = max(0, (int)floor(($toTs - strtotime($row['due_date'] . ' 00:00:00')) / 86400));
        $overdueRows[] = [
            'full_name' => $row['full_name'],
            'student_id' => $row['student_id'],
            'title' => $row['title'],
            'due_date' => $row['due_date'],
            'days' => $days,
            'fine' => $days * FINE_PER_DAY,
        ];
    }

    return [
        'from' => $from,
        'to' => $to,
        'kpis' => [
            'borrows' => $borrows,
            'returns' => $returns,
            'overdue' => $overdue,
            'fines_paid' => $finesPaid,
            'fines_unpaid' => $finesUnpaid,
            'active_borrowers' => $activeBorrowers,
        ],
        'borrows_over_time' => reportBorrowsOverTime($conn, $from, $to),
        'top_books' => $topBooks,
        'top_borrowers' => $topBorrowers,
        'overdue_rows' => $overdueRows,
    ];
}
