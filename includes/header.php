<?php
if (!defined('SITE_URL')) {
    http_response_code(403);
    exit('Forbidden');
}
// includes/header.php — call with $pageTitle and $activePage set
$role = $_SESSION['user_role'] ?? '';
$name = $_SESSION['user_name'] ?? '';

$studentNotice = null;
if ($role === 'student') {
    $studentNotice = dbSelect($conn,
        "SELECT
            COALESCE(SUM(status = 'borrowed' AND due_date >= CURDATE() AND due_date <= DATE_ADD(CURDATE(), INTERVAL 2 DAY)), 0) AS due_soon,
            COALESCE(SUM(status = 'borrowed' AND due_date < CURDATE()), 0) AS overdue_count,
            COALESCE(SUM(CASE
                WHEN status = 'returned' AND fine_amount > 0 AND fine_paid = 'no' THEN fine_amount
                WHEN status = 'borrowed' AND due_date < CURDATE() THEN DATEDIFF(CURDATE(), due_date) * ?
                ELSE 0 END), 0) AS estimated_unpaid_fines
         FROM borrow_records WHERE user_id = ?",
        'di', [FINE_PER_DAY, (int)$_SESSION['user_id']])->fetch_assoc();
}

$navItems = [];
if ($role === 'admin') {
    $navItems = [
        ['url' => SITE_URL.'/admin/dashboard.php',   'icon' => '🏠', 'label' => 'Dashboard',   'key' => 'dashboard'],
        ['url' => SITE_URL.'/admin/users.php',        'icon' => '👥', 'label' => 'Users',        'key' => 'users'],
        ['url' => SITE_URL.'/admin/books.php',        'icon' => '📚', 'label' => 'Books',        'key' => 'books'],
        ['url' => SITE_URL.'/admin/transactions.php', 'icon' => '📋', 'label' => 'Transactions', 'key' => 'transactions'],
        ['url' => SITE_URL.'/admin/reports.php',      'icon' => '📊', 'label' => 'Reports',      'key' => 'reports'],
        ['url' => SITE_URL.'/admin/settings.php',     'icon' => '⚙️', 'label' => 'Settings',     'key' => 'settings'],
        ['url' => SITE_URL.'/admin/profile.php',      'icon' => '👤', 'label' => 'My Profile',    'key' => 'profile'],
    ];
} elseif ($role === 'librarian') {
    $navItems = [
        ['url' => SITE_URL.'/librarian/dashboard.php',    'icon' => '🏠', 'label' => 'Dashboard',    'key' => 'dashboard'],
        ['url' => SITE_URL.'/librarian/books.php',         'icon' => '📚', 'label' => 'Books',         'key' => 'books'],
        ['url' => SITE_URL.'/librarian/issue_book.php',    'icon' => '➕', 'label' => 'Issue Book',    'key' => 'issue_book'],
        ['url' => SITE_URL.'/librarian/return_book.php',   'icon' => '↩️', 'label' => 'Return Book',   'key' => 'return_book'],
        ['url' => SITE_URL.'/librarian/reservations.php',  'icon' => '📅', 'label' => 'Reservations',  'key' => 'reservations'],
        ['url' => SITE_URL.'/librarian/transactions.php',  'icon' => '📋', 'label' => 'Transactions',  'key' => 'transactions'],
        ['url' => SITE_URL.'/librarian/reports.php',       'icon' => '📊', 'label' => 'Reports',       'key' => 'reports'],
        ['url' => SITE_URL.'/librarian/profile.php',       'icon' => '👤', 'label' => 'My Profile',    'key' => 'profile'],
    ];
} elseif ($role === 'student') {
    $navItems = [
        ['url' => SITE_URL.'/student/dashboard.php',  'icon' => '🏠', 'label' => 'Dashboard', 'key' => 'dashboard'],
        ['url' => SITE_URL.'/student/search.php',     'icon' => '🔍', 'label' => 'Search Books', 'key' => 'search'],
        ['url' => SITE_URL.'/student/borrowed.php',   'icon' => '📖', 'label' => 'My Books',   'key' => 'borrowed'],
        ['url' => SITE_URL.'/student/reservations.php', 'icon' => '📅', 'label' => 'My Reservations', 'key' => 'reservations'],
        ['url' => SITE_URL.'/student/history.php',    'icon' => '🕐', 'label' => 'History',    'key' => 'history'],
        ['url' => SITE_URL.'/student/profile.php',    'icon' => '👤', 'label' => 'My Profile',  'key' => 'profile'],
    ];
}

$navItems = match ($role) {
    'admin' => array_merge(array_slice($navItems, 0, -1), [['url' => SITE_URL.'/librarian/reservations.php', 'icon' => '!', 'label' => 'Reservations', 'key' => 'reservations'], ['url' => SITE_URL.'/admin/overdue.php', 'icon' => '!', 'label' => 'Overdue', 'key' => 'overdue']], array_slice($navItems, -1)),
    'librarian' => array_merge(array_slice($navItems, 0, -1), [['url' => SITE_URL.'/librarian/overdue.php', 'icon' => '!', 'label' => 'Overdue', 'key' => 'overdue']], array_slice($navItems, -1)),
    default => $navItems,
};

$roleColors = ['admin' => '#e74c3c', 'librarian' => '#2980b9', 'student' => '#27ae60'];
$roleColor  = $roleColors[$role] ?? '#555';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($pageTitle ?? 'Dashboard') ?> – RMC LMS</title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
  :root {
    --navy: #0f1e38;
    --gold: #c9a84c;
    --cream: #fdf8ef;
    --sidebar-w: 240px;
    --role-color: <?= $roleColor ?>;
  }
  * { margin:0; padding:0; box-sizing:border-box; }
  body { font-family: 'DM Sans', sans-serif; background: #f4f6fb; color: #1a1a2e; display: flex; min-height: 100vh; }

  /* SIDEBAR */
  .sidebar {
    width: var(--sidebar-w);
    background: var(--navy);
    min-height: 100vh;
    position: fixed;
    top: 0; left: 0;
    display: flex;
    flex-direction: column;
    z-index: 100;
    transition: transform 0.25s ease;
  }
  .sidebar-brand {
    padding: 24px 20px;
    border-bottom: 1px solid rgba(255,255,255,0.08);
  }
  .sidebar-brand h3 {
    font-family: 'Playfair Display', serif;
    color: white;
    font-size: 15px;
    line-height: 1.4;
  }
  .sidebar-brand p {
    font-size: 11px;
    color: rgba(255,255,255,0.4);
    margin-top: 2px;
  }
  .sidebar-brand .book-icon {
    font-size: 22px;
    display: block;
    margin-bottom: 8px;
  }

  .nav-section { padding: 12px 0; flex: 1; }
  .nav-label {
    font-size: 10px;
    text-transform: uppercase;
    letter-spacing: 1.5px;
    color: rgba(255,255,255,0.25);
    padding: 8px 20px 6px;
  }
  .nav-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 11px 20px;
    color: rgba(255,255,255,0.55);
    text-decoration: none;
    font-size: 14px;
    transition: all 0.2s;
    border-left: 3px solid transparent;
  }
  .nav-item:hover { color: white; background: rgba(255,255,255,0.05); }
  .nav-item.active {
    color: white;
    background: rgba(201,168,76,0.12);
    border-left-color: var(--gold);
  }
  .nav-item .icon { font-size: 16px; width: 20px; text-align: center; }

  .sidebar-user {
    padding: 16px 20px;
    border-top: 1px solid rgba(255,255,255,0.08);
  }
  .user-info { display: flex; align-items: center; gap: 10px; margin-bottom: 10px; }
  .user-avatar {
    width: 34px; height: 34px;
    background: var(--role-color);
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 14px; font-weight: 600; color: white;
    flex-shrink: 0;
  }
  .user-details .name { font-size: 13px; color: white; font-weight: 500; }
  .user-details .role-tag {
    font-size: 10px;
    background: var(--role-color);
    color: white;
    padding: 1px 6px;
    border-radius: 10px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
  }
  .btn-logout {
    display: block;
    text-align: center;
    padding: 8px;
    background: rgba(255,255,255,0.07);
    color: rgba(255,255,255,0.5);
    text-decoration: none;
    border-radius: 6px;
    font-size: 12px;
    transition: all 0.2s;
  }
  .btn-logout:hover { background: rgba(214,69,69,0.2); color: #ff8080; }

  /* CONTENT COLUMN */
  .content-wrap {
    flex: 1;
    min-width: 0;
    min-height: 100vh;
    margin-left: var(--sidebar-w);
    display: flex;
    flex-direction: column;
  }

  /* MAIN */
  .main { flex: 1; padding: 32px; }
  .page-header { margin-bottom: 28px; }
  .page-header h1 { font-family: 'Playfair Display', serif; font-size: 26px; color: var(--navy); }
  .page-header p { color: #888; font-size: 14px; margin-top: 4px; }

  /* CARDS */
  .card {
    background: white;
    border-radius: 12px;
    padding: 24px;
    box-shadow: 0 1px 4px rgba(0,0,0,0.06);
  }
  .card-title {
    font-size: 14px;
    font-weight: 600;
    color: var(--navy);
    margin-bottom: 16px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
  }

  /* STATS GRID */
  .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; margin-bottom: 24px; }
  .stat-card {
    background: white;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 1px 4px rgba(0,0,0,0.06);
    position: relative;
    overflow: hidden;
  }
  .stat-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 3px;
    background: var(--accent, var(--gold));
  }
  .stat-icon { font-size: 28px; margin-bottom: 10px; }
  .stat-value { font-size: 28px; font-weight: 600; color: var(--navy); }
  .stat-label { font-size: 12px; color: #888; margin-top: 2px; }

  /* TABLE */
  .table-wrap { overflow-x: auto; }
  table { width: 100%; border-collapse: collapse; }
  th {
    background: #f8f9fc;
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    color: #888;
    padding: 10px 14px;
    text-align: left;
    border-bottom: 1px solid #eee;
  }
  td { padding: 12px 14px; font-size: 14px; border-bottom: 1px solid #f0f0f0; color: #333; }
  tr:last-child td { border-bottom: none; }
  tr:hover td { background: #fafafa; }

  /* BADGES */
  .badge {
    display: inline-block;
    padding: 3px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 500;
  }
  .badge-success { background: #d4f4dd; color: #166534; }
  .badge-warning { background: #fef3c7; color: #92400e; }
  .badge-danger  { background: #fee2e2; color: #991b1b; }
  .badge-info    { background: #dbeafe; color: #1e40af; }
  .badge-secondary { background: #f3f4f6; color: #374151; }

  /* FORMS */
  .form-group { margin-bottom: 18px; }
  .form-group label { display: block; font-size: 12px; font-weight: 500; letter-spacing: 0.5px; color: #555; margin-bottom: 6px; }
  .form-control {
    width: 100%;
    padding: 10px 14px;
    border: 1.5px solid #e0e0e0;
    border-radius: 8px;
    font-family: 'DM Sans', sans-serif;
    font-size: 14px;
    color: var(--navy);
    outline: none;
    transition: border-color 0.2s;
  }
  .form-control:focus { border-color: var(--gold); }
  select.form-control { cursor: pointer; }

  /* BUTTONS */
  .btn {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 9px 18px;
    border-radius: 8px;
    font-family: 'DM Sans', sans-serif;
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    border: none;
    text-decoration: none;
    transition: all 0.2s;
  }
  .btn-primary { background: var(--navy); color: white; }
  .btn-primary:hover { background: #1a3460; }
  .btn-gold { background: var(--gold); color: white; }
  .btn-gold:hover { background: #b8973a; }
  .btn-danger { background: #e74c3c; color: white; }
  .btn-danger:hover { background: #c0392b; }
  .btn-sm { padding: 5px 12px; font-size: 12px; }
  .btn-outline { background: transparent; border: 1.5px solid #ddd; color: #555; }
  .btn-outline:hover { border-color: var(--navy); color: var(--navy); }

  .alert {
    padding: 12px 16px;
    border-radius: 8px;
    margin-bottom: 20px;
    font-size: 14px;
  }
  .alert-success { background: #d4f4dd; color: #166534; border: 1px solid #bbf7d0; }
  .alert-danger  { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }

  .site-footer {
    margin-top: auto;
    padding: 16px 32px;
    font-size: 12px;
    color: #aaa;
    border-top: 1px solid #eee;
    background: white;
  }
  .mobile-menu-toggle {
    display: none;
    position: fixed;
    top: 14px;
    left: 14px;
    z-index: 200;
    width: 42px;
    height: 42px;
    border: none;
    border-radius: 8px;
    background: var(--navy);
    color: white;
    font-size: 20px;
    cursor: pointer;
    box-shadow: 0 2px 8px rgba(0,0,0,0.2);
  }

  /* BOOK COVERS */
  .cover-thumb { object-fit: cover; border-radius: 4px; border: 1px solid #eee; background: #f3f4f6; display: block; }
  .cover-thumb-admin { width: 48px; height: 64px; }
  .cover-thumb-search { width: 96px; height: 128px; flex: none; }
  .cover-thumb-borrowed { width: 40px; height: 56px; flex: none; }
  .cover-preview { width: 96px; height: 128px; object-fit: cover; border-radius: 6px; border: 1px solid #eee; background: #f3f4f6; flex: none; }
  .cover-form-row { display: flex; gap: 16px; align-items: flex-start; flex-wrap: wrap; }
  .form-hint { font-size: 12px; color: #888; margin-top: 6px; }
  .checkbox-row { display: flex; align-items: center; gap: 8px; font-size: 13px; color: #555; margin-top: 8px; }
  .book-cell { display: flex; align-items: center; gap: 10px; }

  /* REPORTS */
  .report-filter { display: flex; gap: 16px; align-items: flex-end; flex-wrap: wrap; }
  .report-filter .form-group { margin: 0; min-width: 170px; }
  .report-filter-actions { display: flex; gap: 8px; flex-wrap: wrap; }
  .report-range { font-size: 12px; color: #888; margin-top: 10px; }
  .report-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 20px; margin-bottom: 24px; }
  .chart-card .card-title { margin-bottom: 4px; }
  .chart-wrap { position: relative; height: 260px; margin-top: 16px; }
  .report-empty { color: #888; font-size: 14px; padding: 18px 0; }

  @media (max-width: 900px) {
    .report-grid { grid-template-columns: 1fr; }
  }

  @media (max-width: 768px) {
    .sidebar { transform: translateX(-100%); }
    body.menu-open .sidebar { transform: translateX(0); }
    .mobile-menu-toggle { display: flex; align-items: center; justify-content: center; }
    .main { padding: 20px; }
    .content-wrap { margin-left: 0; }
  }
</style>
</head>
<body>

<button type="button" class="mobile-menu-toggle" aria-label="Toggle menu" onclick="document.body.classList.toggle('menu-open')">☰</button>

<aside class="sidebar">
  <div class="sidebar-brand">
    <span class="book-icon">📚</span>
    <h3><?= htmlspecialchars(LIBRARY_NAME) ?></h3>
    <p>Management System</p>
  </div>
  <nav class="nav-section">
    <div class="nav-label">Menu</div>
    <?php foreach ($navItems as $item): ?>
      <a href="<?= $item['url'] ?>" class="nav-item <?= ($activePage ?? '') === $item['key'] ? 'active' : '' ?>">
        <span class="icon"><?= $item['icon'] ?></span>
        <?= htmlspecialchars($item['label']) ?>
      </a>
    <?php endforeach; ?>
  </nav>
  <div class="sidebar-user">
    <div class="user-info">
      <div class="user-avatar"><?= htmlspecialchars(initial($name)) ?></div>
      <div class="user-details">
        <div class="name"><?= htmlspecialchars(explode(' ', $name)[0]) ?></div>
        <span class="role-tag"><?= ucfirst($role) ?></span>
      </div>
    </div>
    <a href="<?= SITE_URL ?>/logout.php" class="btn-logout">← Sign Out</a>
  </div>
</aside>

<div class="content-wrap">
<main class="main">
<?php if ($studentNotice && ((int)$studentNotice['due_soon'] > 0 || (int)$studentNotice['overdue_count'] > 0 || (float)$studentNotice['estimated_unpaid_fines'] > 0)): ?>
  <div class="alert <?= (int)$studentNotice['overdue_count'] > 0 ? 'alert-danger' : 'alert-success' ?>">
    <?php if ((int)$studentNotice['due_soon'] > 0): ?><div><?= (int)$studentNotice['due_soon'] ?> loan(s) due within 2 days.</div><?php endif; ?>
    <?php if ((int)$studentNotice['overdue_count'] > 0): ?><div><?= (int)$studentNotice['overdue_count'] ?> loan(s) overdue.</div><?php endif; ?>
    <div>Estimated unpaid fines (includes fines still accruing): PHP <?= number_format((float)$studentNotice['estimated_unpaid_fines'], 2) ?></div>
  </div>
<?php endif; ?>
