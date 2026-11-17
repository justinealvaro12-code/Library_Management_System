<?php
require_once '../includes/config.php';
require_once '../includes/reservations.php';
require_once '../includes/book_cover.php';
requireRole('student');

$pageTitle  = 'My Reservations';
$activePage = 'reservations';
$uid = (int)$_SESSION['user_id'];

$msg = '';
$msgType = 'success';
if (isset($_GET['msg']) && $_GET['msg'] === 'reserved') {
    $msg = 'Reservation created. You will be notified when a copy is ready.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cancel') {
    $reservationId = (int)($_POST['reservation_id'] ?? 0);
    $error = '';
    $cancelled = false;

    for ($attempt = 1; $attempt <= 2; $attempt++) {
        $conn->begin_transaction();
        try {
            $seed = dbSelect(
                $conn,
                "SELECT book_id FROM reservations WHERE id = ? AND user_id = ?",
                'ii',
                [$reservationId, $uid]
            )->fetch_assoc();
            if (!$seed) {
                throw new Exception('Reservation not found.');
            }

            $bookId = (int)$seed['book_id'];
            dbSelect($conn, "SELECT id FROM books WHERE id = ? FOR UPDATE", 'i', [$bookId]);

            $reservation = dbSelect(
                $conn,
                "SELECT id, status FROM reservations WHERE id = ? AND user_id = ? FOR UPDATE",
                'ii',
                [$reservationId, $uid]
            )->fetch_assoc();
            if (!$reservation) {
                throw new Exception('Reservation not found.');
            }
            if (!in_array($reservation['status'], ['waiting', 'ready'], true)) {
                throw new Exception('Only waiting or ready reservations can be cancelled.');
            }

            dbExecute(
                $conn,
                "UPDATE reservations SET status = 'cancelled', expiry_date = NULL WHERE id = ? AND user_id = ?",
                'ii',
                [$reservationId, $uid]
            );

            if ($reservation['status'] === 'ready') {
                reservationPromoteQueue($conn, $bookId, $uid, $settings);
            }

            $conn->commit();
            logActivity($conn, $uid, 'reservation_cancelled', "Reservation #$reservationId cancelled by student");
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
        header('Location: reservations.php?msg=cancelled');
        exit();
    }

    $msg = $error;
    $msgType = 'danger';
}

if (isset($_GET['msg']) && $_GET['msg'] === 'cancelled') {
    $msg = 'Reservation cancelled.';
    $msgType = 'success';
}

$reservations = dbSelect(
    $conn,
    "SELECT r.*, b.title, b.author, b.cover_image
     FROM reservations r
     JOIN books b ON b.id = r.book_id
     WHERE r.user_id = ?
     ORDER BY FIELD(r.status, 'ready', 'waiting', 'fulfilled', 'cancelled', 'expired'), r.reservation_date DESC, r.id DESC",
    'i',
    [$uid]
);

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
  <h1>My Reservations</h1>
  <p>Track your queue position and ready-for-pickup reservations.</p>
</div>

<?php if ($msg !== ''): ?>
  <div class="alert alert-<?= $msgType === 'danger' ? 'danger' : 'success' ?>"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<div class="card">
  <?php if ($reservations->num_rows === 0): ?>
    <div style="text-align:center;padding:40px;color:#aaa">
      <p>You have no reservations.</p>
      <a href="<?= SITE_URL ?>/student/search.php" class="btn btn-gold" style="margin-top:12px">Browse Catalog</a>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr><th>Book</th><th>Status</th><th>Queue / Pickup</th><th>Reserved</th><th>Action</th></tr>
        </thead>
        <tbody>
        <?php while ($r = $reservations->fetch_assoc()):
          $statusClass = $statusClasses[$r['status']] ?? 'badge-info';
          $queueText = '--';
          if ($r['status'] === 'waiting') {
              $queueText = 'Position #' . reservationQueuePosition($conn, (int)$r['book_id'], (string)$r['reservation_date'], (int)$r['id']);
          } elseif ($r['status'] === 'ready') {
              $queueText = 'Ready until ' . htmlspecialchars((string)($r['expiry_date'] ?? ''));
          }
        ?>
          <tr>
            <td>
              <div class="book-cell">
                <img src="<?= htmlspecialchars(bookCoverUrl($r['cover_image'] ?? '')) ?>" alt="" class="cover-thumb cover-thumb-borrowed" loading="lazy">
                <div>
                  <strong><?= htmlspecialchars($r['title']) ?></strong><br>
                  <span style="font-size:12px;color:#888">by <?= htmlspecialchars($r['author']) ?></span>
                </div>
              </div>
            </td>
            <td><span class="badge <?= $statusClass ?>"><?= ucfirst(htmlspecialchars($r['status'])) ?></span></td>
            <td style="font-size:13px"><?= $queueText ?></td>
            <td style="font-size:12px"><?= htmlspecialchars((string)$r['reservation_date']) ?></td>
            <td>
              <?php if (in_array($r['status'], ['waiting', 'ready'], true)): ?>
                <form method="POST" onsubmit="return confirm('Cancel this reservation?')">
                  <?= csrfField() ?>
                  <input type="hidden" name="action" value="cancel">
                  <input type="hidden" name="reservation_id" value="<?= (int)$r['id'] ?>">
                  <button type="submit" class="btn btn-sm btn-outline">Cancel</button>
                </form>
              <?php else: ?>
                <span style="color:#ccc;font-size:12px">--</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>
