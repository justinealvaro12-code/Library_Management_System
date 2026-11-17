<?php
if (!defined('SITE_URL')) {
    http_response_code(403);
    exit('Forbidden');
}

/**
 * Canonical effective-availability expression.
 * A ready reservation is a logical hold on one physical available copy.
 */
function reservationEffectiveAvailableSql(string $bookAlias = 'b'): string {
    return "GREATEST(0, {$bookAlias}.available_copies - (SELECT COUNT(*) FROM reservations rh WHERE rh.book_id = {$bookAlias}.id AND rh.status = 'ready'))";
}

function reservationSettingInt(array $settings, string $key, int $default, int $min, int $max): int {
    $value = isset($settings[$key]) ? (int)$settings[$key] : $default;
    return max($min, min($max, $value));
}

function reservationExpiryDays(array $settings): int {
    return reservationSettingInt($settings, 'reservation_expiry_days', 2, 1, 30);
}

function reservationMaxActive(array $settings): int {
    return reservationSettingInt($settings, 'max_active_reservations', 3, 1, 10);
}

function reservationBookState($conn, int $bookId, bool $currentRead = false): ?array {
    if ($currentRead) {
        $row = dbSelect(
            $conn,
            "SELECT id, total_copies, available_copies FROM books WHERE id = ? FOR UPDATE",
            'i',
            [$bookId]
        )->fetch_assoc();
        if (!$row) {
            return null;
        }
        $readyRows = dbSelect(
            $conn,
            "SELECT id FROM reservations WHERE book_id = ? AND status = 'ready' ORDER BY id ASC FOR UPDATE",
            'i',
            [$bookId]
        );
        $readyHolds = 0;
        while ($readyRows->fetch_assoc()) {
            $readyHolds++;
        }
        $row['ready_holds'] = $readyHolds;
        $row['effective_available'] = max(0, (int)$row['available_copies'] - $readyHolds);
        return $row;
    }

    $sql = "SELECT b.id, b.total_copies, b.available_copies, " . reservationEffectiveAvailableSql('b') . " AS effective_available,
                   (SELECT COUNT(*) FROM reservations rr WHERE rr.book_id = b.id AND rr.status = 'ready') AS ready_holds
            FROM books b WHERE b.id = ?";
    $row = dbSelect($conn, $sql, 'i', [$bookId])->fetch_assoc();
    return $row ?: null;
}

function reservationSyncBookStatus($conn, int $bookId): void {
    $state = reservationBookState($conn, $bookId, true);
    if (!$state) {
        return;
    }
    $status = ((int)$state['effective_available'] > 0) ? 'available' : 'unavailable';
    dbExecute($conn, "UPDATE books SET status = ? WHERE id = ? AND status <> ?", 'sis', [$status, $bookId, $status]);
}

function reservationQueuePosition($conn, int $bookId, string $reservationDate, int $reservationId): int {
    $row = dbSelect(
        $conn,
        "SELECT COUNT(*) AS c FROM reservations
         WHERE book_id = ? AND status = 'waiting'
           AND (reservation_date < ? OR (reservation_date = ? AND id < ?))",
        'issi',
        [$bookId, $reservationDate, $reservationDate, $reservationId]
    )->fetch_assoc();
    return ((int)($row['c'] ?? 0)) + 1;
}

function reservationUnpaidFines($conn, int $userId): float {
    $row = dbSelect(
        $conn,
        "SELECT COALESCE(SUM(CASE
             WHEN status = 'returned' AND fine_amount > 0 AND fine_paid = 'no' THEN fine_amount
             WHEN status = 'borrowed' AND due_date < CURDATE() THEN DATEDIFF(CURDATE(), due_date) * ?
             ELSE 0 END),0) AS total
         FROM borrow_records
         WHERE user_id = ?",
        'di',
        [FINE_PER_DAY, $userId]
    )->fetch_assoc();
    return (float)($row['total'] ?? 0);
}

/**
 * Promote waiting reservations while unused physical capacity exists.
 * Caller must be inside a transaction and must already hold the book row lock.
 */
function reservationPromoteQueue($conn, int $bookId, int $actorId, array $settings): int {
    $state = reservationBookState($conn, $bookId, true);
    if (!$state) {
        return 0;
    }

    $capacity = (int)$state['available_copies'] - (int)$state['ready_holds'];
    if ($capacity <= 0) {
        reservationSyncBookStatus($conn, $bookId);
        return 0;
    }

    $expiryDays = reservationExpiryDays($settings);
    $promoted = 0;

    while ($promoted < $capacity) {
        $waiting = dbSelect(
            $conn,
            "SELECT id, user_id FROM reservations
             WHERE book_id = ? AND status = 'waiting'
             ORDER BY reservation_date ASC, id ASC
             LIMIT 1 FOR UPDATE",
            'i',
            [$bookId]
        )->fetch_assoc();

        if (!$waiting) {
            break;
        }

        $updated = dbExecute(
            $conn,
            "UPDATE reservations
             SET status = 'ready', expiry_date = DATE_ADD(NOW(), INTERVAL ? DAY)
             WHERE id = ? AND status = 'waiting'",
            'ii',
            [$expiryDays, (int)$waiting['id']]
        );

        if ($updated->affected_rows !== 1) {
            break;
        }

        logActivity(
            $conn,
            $actorId,
            'reservation_ready',
            "Reservation #{$waiting['id']} is ready for pickup (book #$bookId, user #{$waiting['user_id']})"
        );
        $promoted++;
    }

    reservationSyncBookStatus($conn, $bookId);
    return $promoted;
}

/**
 * Expire ready reservations and promote the next waiting students.
 * This helper owns its transaction and is throttled to about once per minute.
 */
function reservationExpirySweep($conn, int $actorId, array $settings): int {
    for ($attempt = 1; $attempt <= 2; $attempt++) {
        $conn->begin_transaction();
        try {
            $lastRow = dbSelect(
                $conn,
                "SELECT setting_value FROM settings WHERE setting_key = 'reservation_last_sweep' FOR UPDATE"
            )->fetch_assoc();
            $nowRow = dbSelect($conn, "SELECT NOW() AS db_now")->fetch_assoc();
            $dbNow = strtotime((string)($nowRow['db_now'] ?? 'now'));
            $last = $lastRow ? strtotime((string)$lastRow['setting_value']) : false;

            if ($last !== false && $last > $dbNow - 60) {
                $conn->commit();
                return 0;
            }

            if ($lastRow) {
                dbExecute(
                    $conn,
                    "UPDATE settings SET setting_value = DATE_FORMAT(NOW(), '%Y-%m-%d %H:%i:%s')
                     WHERE setting_key = 'reservation_last_sweep'"
                );
            } else {
                dbExecute(
                    $conn,
                    "INSERT INTO settings (setting_key, setting_value)
                     VALUES ('reservation_last_sweep', DATE_FORMAT(NOW(), '%Y-%m-%d %H:%i:%s'))"
                );
            }

            $result = dbSelect(
                $conn,
                "SELECT id, book_id, user_id FROM reservations
                 WHERE status = 'ready' AND expiry_date IS NOT NULL AND expiry_date <= NOW()
                 ORDER BY book_id ASC, id ASC"
            );
            $rows = [];
            $bookIds = [];
            while ($row = $result->fetch_assoc()) {
                $rows[] = $row;
                $bookIds[(int)$row['book_id']] = true;
            }

            if (empty($rows)) {
                $conn->commit();
                return 0;
            }

            $bookIds = array_keys($bookIds);
            sort($bookIds, SORT_NUMERIC);
            foreach ($bookIds as $id) {
                dbSelect($conn, "SELECT id FROM books WHERE id = ? FOR UPDATE", 'i', [$id]);
            }

            $expired = 0;
            foreach ($bookIds as $id) {
                $locked = dbSelect(
                    $conn,
                    "SELECT id, user_id FROM reservations
                     WHERE book_id = ? AND status = 'ready' AND expiry_date IS NOT NULL AND expiry_date <= NOW()
                     ORDER BY id ASC FOR UPDATE",
                    'i',
                    [$id]
                );

                while ($row = $locked->fetch_assoc()) {
                    dbExecute(
                        $conn,
                        "UPDATE reservations SET status = 'expired', expiry_date = NULL WHERE id = ? AND status = 'ready'",
                        'i',
                        [(int)$row['id']]
                    );
                    logActivity(
                        $conn,
                        $actorId,
                        'reservation_expired',
                        "Reservation #{$row['id']} expired (book #$id, user #{$row['user_id']})"
                    );
                    $expired++;
                }

                reservationPromoteQueue($conn, (int)$id, $actorId, $settings);
            }

            $conn->commit();
            return $expired;
        } catch (mysqli_sql_exception $e) {
            $conn->rollback();
            if ((int)$e->getCode() === 1213 && $attempt === 1) {
                continue;
            }
            error_log('Reservation expiry sweep failed: ' . $e->getMessage());
            return 0;
        } catch (Throwable $e) {
            $conn->rollback();
            error_log('Reservation expiry sweep failed: ' . $e->getMessage());
            return 0;
        }
    }
    return 0;
}
?>
