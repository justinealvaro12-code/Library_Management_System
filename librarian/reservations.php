<?php
require_once '../includes/config.php';
require_once '../includes/pagination.php';
require_once '../includes/reservations.php';
requireRole('librarian');

$pageTitle  = 'Reservations';
$activePage = 'reservations';
$uid = (int)$_SESSION['user_id'];
$msg = '';
$msgType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cancel') {
    $reservationId = (int)($_POST['reservation_id'] ?? 0);
    $error = '';
    $cancelled = false;

    for ($attempt = 1; $attempt <= 2; $attempt++) {
        $conn->begin_transaction();
        try {
            $seed = dbSelect(
                $conn,
                "SELECT book_id FROM reservations WHERE id = ?",
                'i',
                [$reservationId]
            )->fetch_assoc();
            if (!$seed) {
                throw new Exception('Reservation not found.');
            }

            $bookId = (int)$seed['book_id'];
            dbSelect($conn, "SELECT id FROM books WHERE id = ? FOR UPDATE", 'i', [$bookId]);

            $reservation = dbSelect(
                $conn,
                "SELECT id, user_id, status FROM reservations WHERE id = ? FOR UPDATE",
                'i',
                [$reservationId]
            )->fetch_assoc();
            if (!$reservation) {
                throw new Exception('Reservation not found.');
            }
            if (!in_array($reservation['status'], ['waiting', 'ready'], true)) {
                throw new Exception('Only waiting or ready reservations can be cancelled.');
            }

            dbExecute(
                $conn,
                "UPDATE reservations SET status = 'cancelled', expiry_date = NULL WHERE id = ?",
                'i',
                [$reservationId]
            );

            if ($reservation['status'] === 'ready') {
                reservationPromoteQueue($conn, $bookId, $uid, $settings);
            }

            $conn->commit();
            logActivity($conn, $uid, 'reservation_cancelled', "Reservation #$reservationId cancelled by librarian for user #{$reservation['user_id']}");
            $cancelled = true;
            break;
        } catch (mysqli_sql_exception $e) {
            $conn->rollback();
            if ((int)$e->getCode() === 1213 && $attempt === 1) {
                continue;
            }
            $error = 'Unable to cancel the reservation due to a database error.';
            break;
        } catch (Exception $e) {
            $conn->rollback();
            $error = $e->getMessage();
            break;
        }
    }

    if ($cancelled) {
        header('Location: reservations.php?' . http_build_query(array_merge($_GET, ['msg' => 'cancelled'])));
        exit();
    }

    $msg = $error;
    $msgType = 'danger';
}

$filter = trim($_GET['filter'] ?? '');
if (!in_array($filter, ['', 'waiting', 'ready', 'fulfilled', 'cancelled', 'expired'], true)) {
    $filter = '';
}
$searchRaw = trim($_GET['q'] ?? '');

$where = "WHERE 1=1";
$types = '';
$params = [];
if ($filter !== '') {
    $where .= " AND r.status = ?";
    $types .= 's';
    $params[] = $filter;
}
if ($searchRaw !== '') {
    $like = '%' . $searchRaw . '%';
    $where .= " AND (u.full_name LIKE ? OR u.student_id LIKE ? OR b.title LIKE ?)";
    $types .= 'sss';
    $params = array_merge($params, [$like, $like, $like]);
}

$countRow = dbSelect(
    $conn,
    "SELECT COUNT(*) c FROM reservations r
     JOIN users u ON u.id = r.user_id
     JOIN books b ON b.id = r.book_id
     $where",
    $types,
    $params
)->fetch_assoc();
$totalRecords = (int)($countRow['c'] ?? 0);
$pager = paginate($totalRecords, 20);

$orderBy = "ORDER BY FIELD(r.status, 'ready', 'waiting', 'fulfilled', 'cancelled', 'expired'), r.reservation_date DESC, r.id DESC";
if ($filter === 'waiting') {
    $orderBy = "ORDER BY r.reservation_date ASC, r.id ASC";
} elseif ($filter === 'ready') {
    $orderBy = "ORDER BY r.expiry_date ASC, r.id ASC";
}

$queryParams = array_merge($params, [$pager['per_page'], $pager['offset']]);
$typesP = $types . 'ii';
$records = dbSelect(
    $conn,
    "SELECT r.*, u.full_name, u.student_id, b.title
     FROM reservations r
     JOIN users u ON u.id = r.user_id
     JOIN books b ON b.id = r.book_id
     $where
     $orderBy
     LIMIT ? OFFSET ?",
    $typesP,
    $queryParams
);

if ($msg === '' && isset($_GET['msg']) && $_GET['msg'] === 'cancelled') {
    $msg = 'Reservation cancelled.';
}

$statusClasses = [
    'waiting' => 'badge-info',
    'ready' => 'badge-warning',
    'fulfilled' => 'badge-success',
    'cancelled' => 'badge-danger',
    'expired' => 'badge-danger',
];

require_once '../includes/header.php';
?>

<div class="page-header">
  <h1>Reservations</h1>
  <p>Manage the reservation queue. Fulfillment happens through Issue Book only.</p>
</div>

<?php if ($msg !== ''): ?>
  <div class="alert alert-<?= $msgType === 'danger' ? 'danger' : 'success' ?>"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<div style="display:flex;gap:10px;margin-bottom:20px;align-items:center;flex-wrap:wrap">
  <a href="reservations.php" class="btn btn-sm btn-outline <?= $filter===''?'btn-primary':'' ?>">All</a>
  <a href="?filter=waiting" class="btn btn-sm btn-outline <?= $filter==='waiting'?'btn-primary':'' ?>">Waiting</a>
  <a href="?filter=ready" class="btn btn-sm btn-outline <?= $filter==='ready'?'btn-primary':'' ?>">Ready</a>
  <a href="?filter=fulfilled" class="btn btn-sm btn-outline <?= $filter==='fulfilled'?'btn-primary':'' ?>">Fulfilled</a>
  <a href="?filter=cancelled" class="btn btn-sm btn-outline <?= $filter==='cancelled'?'btn-primary':'' ?>">Cancelled</a>
  <a href="?filter=expired" class="btn btn-sm btn-outline <?= $filter==='expired'?'btn-primary':'' ?>">Expired</a>
</div>

<div class="card" style="margin-bottom:20px">
  <form method="GET" style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap">
    <?php if ($filter !== ''): ?>
      <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
    <?php endif; ?>
    <div class="form-group" style="flex:1;min-width:220px;margin:0">
      <label>Search</label>
      <input type="text" name="q" class="form-control" placeholder="Student name, student ID, or book title" value="<?= htmlspecialchars($searchRaw) ?>">
    </div>
    <button type="submit" class="btn btn-primary">Search</button>
    <?php if ($searchRaw !== ''): ?>
      <a href="reservations.php<?= $filter !== '' ? '?filter=' . rawurlencode($filter) : '' ?>" class="btn btn-outline">Clear</a>
    <?php endif; ?>
  </form>
</div>

<div class="card">
  <div class="table-wrap">
    <table>
      <thead>
        <tr><th>#</th><th>Student</th><th>Book</th><th>Status</th><th>Queue / Pickup</th><th>Reserved</th><th>Action</th></tr>
      </thead>
      <tbody>
      <?php if ($totalRecords === 0): ?>
        <tr><td colspan="7" style="text-align:center;color:#888;padding:24px">No reservations found.</td></tr>
      <?php else: while ($r = $records->fetch_assoc()):
        $statusClass = $statusClasses[$r['status']] ?? 'badge-info';
        $queueText = '--';
        if ($r['status'] === 'waiting') {
            $queueText = 'Position #' . reservationQueuePosition($conn, (int)$r['book_id'], (string)$r['reservation_date'], (int)$r['id']);
        } elseif ($r['status'] === 'ready') {
            $queueText = 'Ready until ' . htmlspecialchars((string)($r['expiry_date'] ?? ''));
        }
      ?>
        <tr>
          <td style="color:#aaa;font-size:12px"><?= (int)$r['id'] ?></td>
          <td>
            <?= htmlspecialchars($r['full_name']) ?><br>
            <span style="font-size:11px;color:#aaa"><?= htmlspecialchars($r['student_id'] ?? '') ?></span>
          </td>
          <td style="font-size:13px"><?= htmlspecialchars($r['title']) ?></td>
          <td><span class="badge <?= $statusClass ?>"><?= ucfirst(htmlspecialchars($r['status'])) ?></span></td>
          <td style="font-size:12px"><?= $queueText ?></td>
          <td style="font-size:12px"><?= htmlspecialchars((string)$r['reservation_date']) ?></td>
          <td>
            <?php if ($r['status'] === 'ready'): ?>
              <a href="issue_book.php?user_id=<?= (int)$r['user_id'] ?>&book_id=<?= (int)$r['book_id'] ?>" class="btn btn-sm btn-gold">Go to issue</a>
            <?php endif; ?>
            <?php if (in_array($r['status'], ['waiting', 'ready'], true)): ?>
              <form method="POST" style="display:inline" onsubmit="return confirm('Cancel this reservation?')">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="cancel">
                <input type="hidden" name="reservation_id" value="<?= (int)$r['id'] ?>">
                <button type="submit" class="btn btn-sm btn-outline">Cancel</button>
              </form>
            <?php endif; ?>
            <?php if (!in_array($r['status'], ['waiting', 'ready'], true)): ?>
              <span style="color:#ccc;font-size:12px">--</span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endwhile; endif; ?>
      </tbody>
    </table>
  </div>
  <?php if ($totalRecords > 0): ?>
    <?= renderPagination($pager) ?>
  <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>
