<?php
require_once '../includes/config.php';
require_once '../includes/reservations.php';
requireRole('student');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: search.php');
    exit();
}

$uid = (int)$_SESSION['user_id'];
$book_id = (int)($_POST['book_id'] ?? 0);
$error = '';
$success = false;

if ($book_id <= 0) {
    $error = 'Invalid book selected.';
} else {
    for ($attempt = 1; $attempt <= 2; $attempt++) {
        $conn->begin_transaction();
        try {
            $book = dbSelect(
                $conn,
                "SELECT id, title, total_copies, available_copies FROM books WHERE id = ? FOR UPDATE",
                'i',
                [$book_id]
            )->fetch_assoc();
            if (!$book) {
                throw new Exception('Book not found.');
            }

            $student = dbSelect(
                $conn,
                "SELECT id, status, role FROM users WHERE id = ? FOR UPDATE",
                'i',
                [$uid]
            )->fetch_assoc();
            if (!$student || $student['role'] !== 'student' || $student['status'] !== 'active') {
                throw new Exception('Your account is not active.');
            }

            $state = reservationBookState($conn, $book_id);
            if ((int)$state['effective_available'] > 0) {
                throw new Exception('This book is currently available. Borrow it at the circulation desk instead.');
            }

            $alreadyBorrowed = (int)dbSelect(
                $conn,
                "SELECT COUNT(*) c FROM borrow_records WHERE user_id = ? AND book_id = ? AND status = 'borrowed'",
                'ii',
                [$uid, $book_id]
            )->fetch_assoc()['c'];
            if ($alreadyBorrowed > 0) {
                throw new Exception('You already have this book borrowed.');
            }

            if (($settings['block_unpaid_fines'] ?? '0') === '1') {
                $unpaid = reservationUnpaidFines($conn, $uid);
                if ($unpaid > 0) {
                    throw new Exception('Cannot reserve: you have unpaid fines totaling PHP ' . number_format($unpaid, 2) . '.');
                }
            }

            $activeCount = (int)dbSelect(
                $conn,
                "SELECT COUNT(*) c FROM reservations WHERE user_id = ? AND status IN ('waiting','ready')",
                'i',
                [$uid]
            )->fetch_assoc()['c'];
            $maxActive = reservationMaxActive($settings);
            if ($activeCount >= $maxActive) {
                throw new Exception("You already have $activeCount active reservations (maximum $maxActive).");
            }

            $duplicate = dbSelect(
                $conn,
                "SELECT id FROM reservations
                 WHERE user_id = ? AND book_id = ? AND status IN ('waiting','ready')
                 FOR UPDATE",
                'ii',
                [$uid, $book_id]
            )->fetch_assoc();
            if ($duplicate) {
                throw new Exception('You already have an active reservation for this book.');
            }

            dbExecute(
                $conn,
                "INSERT INTO reservations (user_id, book_id, status) VALUES (?, ?, 'waiting')",
                'ii',
                [$uid, $book_id]
            );
            $conn->commit();

            logActivity($conn, $uid, 'reservation_created', "Reserved book #$book_id");
            $success = true;
            break;
        } catch (mysqli_sql_exception $e) {
            $conn->rollback();
            if ((int)$e->getCode() === 1213 && $attempt === 1) {
                continue;
            }
            if ((int)$e->getCode() === 1062) {
                $error = 'You already have an active reservation for this book.';
            } else {
                $error = 'Unable to create the reservation due to a database error.';
            }
            break;
        } catch (Exception $e) {
            $conn->rollback();
            $error = $e->getMessage();
            break;
        }
    }
}

if ($success) {
    header('Location: reservations.php?msg=reserved');
    exit();
}

header('Location: search.php?reserve_error=' . rawurlencode($error));
exit();
