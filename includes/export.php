<?php
if (!defined('SITE_URL')) {
    http_response_code(403);
    exit('Forbidden');
}

function csvGuard($value) {
    if ($value === null) return '';
    $s = (string)$value;
    if ($s !== '' && preg_match('/^[=\+\-@\t\r]/', $s)) {
        return "'" . $s;
    }
    return $s;
}

function csvStart($filename, $headers) {
    if (ob_get_level()) { ob_end_clean(); }
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, $headers);
    return $out;
}

function csvRow($out, $row) {
    $safe = [];
    foreach ($row as $v) { $safe[] = csvGuard($v); }
    fputcsv($out, $safe);
}

function exportAllowedFilters($allowed) {
    $out = [];
    foreach ($allowed as $k) {
        if (isset($_GET[$k]) && $_GET[$k] !== '') { $out[$k] = $_GET[$k]; }
    }
    return $out;
}

/**
 * Run a prepared SELECT using a server-side read-only cursor so rows are
 * streamed from MySQL instead of being buffered in PHP memory.
 */
function exportPrepared($conn, $sql, $types = '', $params = []) {
    $stmt = $conn->prepare($sql);
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->attr_set(MYSQLI_STMT_ATTR_CURSOR_TYPE, MYSQLI_CURSOR_TYPE_READ_ONLY);
    $stmt->execute();
    return $stmt;
}
?>
