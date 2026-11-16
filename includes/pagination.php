<?php
if (!defined('SITE_URL')) {
    http_response_code(403);
    exit('Forbidden');
}

function paginate(int $total, int $perPage = 20): array {
    $perPage = max(1, $perPage);
    $pages = max(1, (int)ceil($total / $perPage));
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    if ($page < 1) $page = 1;
    if ($page > $pages) $page = $pages;
    $offset = ($page - 1) * $perPage;
    $from = $total > 0 ? $offset + 1 : 0;
    $to = $total > 0 ? min($offset + $perPage, $total) : 0;
    return ['page'=>$page,'per_page'=>$perPage,'pages'=>$pages,'offset'=>$offset,'total'=>$total,'from'=>$from,'to'=>$to];
}

function pageUrl(int $page): string {
    $params = $_GET;
    $params['page'] = $page;
    $uri = strtok($_SERVER['REQUEST_URI'] ?? '', '?');
    return $uri . '?' . http_build_query($params);
}

function renderPagination(array $p): string {
    $html = '<div style="display:flex;justify-content:space-between;align-items:center;gap:12px;margin-top:14px;flex-wrap:wrap">';
    $html .= '<div style="font-size:13px;color:#666">Showing ' . $p['from'] . '-' . $p['to'] . ' of ' . $p['total'] . '</div>';
    if ($p['pages'] <= 1) { $html .= '</div>'; return $html; }
    $html .= '<div style="display:flex;gap:6px;flex-wrap:wrap;align-items:center">';
    $start = max(1, $p['page'] - 2); $end = min($p['pages'], $p['page'] + 2);
    if ($p['page'] > 1) $html .= '<a class="btn btn-sm btn-outline" href="' . htmlspecialchars(pageUrl($p['page']-1)) . '">Prev</a>';
    if ($start > 1) $html .= '<a class="btn btn-sm btn-outline" href="' . htmlspecialchars(pageUrl(1)) . '">1</a>';
    if ($start > 2) $html .= '<span style="padding:4px 6px;color:#888">…</span>';
    for ($i=$start; $i<=$end; $i++) {
        $active = $i === $p['page'] ? 'btn-primary' : 'btn-outline';
        $html .= '<a class="btn btn-sm ' . $active . '" href="' . htmlspecialchars(pageUrl($i)) . '">' . $i . '</a>';
    }
    if ($end < $p['pages']-1) $html .= '<span style="padding:4px 6px;color:#888">…</span>';
    if ($end < $p['pages']) $html .= '<a class="btn btn-sm btn-outline" href="' . htmlspecialchars(pageUrl($p['pages'])) . '">' . $p['pages'] . '</a>';
    if ($p['page'] < $p['pages']) $html .= '<a class="btn btn-sm btn-outline" href="' . htmlspecialchars(pageUrl($p['page']+1)) . '">Next</a>';
    $html .= '</div></div>';
    return $html;
}
?>
