<?php
if (!defined('SITE_URL')) { http_response_code(403); exit('Forbidden'); }

require_once __DIR__ . '/book_cover.php';

/**
 * Handle add/update/delete POST actions for book management.
 * Returns the updated page state; successful writes redirect and exit.
 */
function bookHandlePost($conn, $postAction, $action, $editBook, $msg, $msgType) {
    if ($postAction !== 'add' && $postAction !== 'update' && $postAction !== 'delete') {
        return ['action' => $action, 'editBook' => $editBook, 'msg' => $msg, 'msgType' => $msgType];
    }

    if ($postAction === 'add' || $postAction === 'update') {
        $id            = (int)($_POST['id'] ?? 0);
        $isbnRaw       = trim($_POST['isbn'] ?? '');
        $titleRaw      = trim($_POST['title'] ?? '');
        $authorRaw     = trim($_POST['author'] ?? '');
        $publisherRaw  = trim($_POST['publisher'] ?? '');
        $categoryRaw   = trim($_POST['category'] ?? '');
        $yearRaw       = trim((string)($_POST['year_published'] ?? ''));
        $copies        = (int)($_POST['total_copies'] ?? 1);
        $locationRaw   = trim($_POST['location'] ?? '');
        $descriptionRaw = trim($_POST['description'] ?? '');
        $year          = $yearRaw === '' ? null : (int)$yearRaw;

        $isbn      = $isbnRaw;
        $title     = $titleRaw;
        $author    = $authorRaw;
        $publisher = $publisherRaw;
        $category  = $categoryRaw;
        $location  = $locationRaw;
        $desc      = $descriptionRaw;

        $errors   = [];
        $borrowed = 0;
        $available = $copies;

        if ($titleRaw === '') {
            $errors[] = 'Title is required.';
        }
        if ($authorRaw === '') {
            $errors[] = 'Author is required.';
        }
        if ($copies < 1) {
            $errors[] = 'Total copies must be at least 1.';
        }
        if ($yearRaw !== '' && ($year < 1000 || $year > (int)date('Y') + 1)) {
            $errors[] = 'Year published must be between 1000 and ' . ((int)date('Y') + 1) . '.';
        }

        $status = 'available';
        $existingCover = '';

        if ($postAction === 'update') {
            $currentBook = dbSelect($conn, "SELECT total_copies, available_copies, cover_image FROM books WHERE id = ?", 'i', [$id])->fetch_assoc();
            $borrowed    = (int)dbSelect($conn, "SELECT COUNT(*) c FROM borrow_records WHERE book_id = ? AND status = 'borrowed'", 'i', [$id])->fetch_assoc()['c'];

            if (!$currentBook) {
                $errors[] = 'Book not found.';
            } else {
                $existingCover = (string)($currentBook['cover_image'] ?? '');
                $available = max(0, min($copies, (int)$currentBook['available_copies'] + ($copies - (int)$currentBook['total_copies'])));
                if ($copies < $borrowed) {
                    $errors[] = "Total copies cannot be less than the $borrowed currently borrowed.";
                }
            }

            $status = $available > 0 ? 'available' : 'unavailable';
        }

        $cover = [
            'filename' => bookCoverFilename($existingCover) !== '' ? bookCoverFilename($existingCover) : null,
            'old_to_delete' => null,
            'error' => '',
        ];
        if (empty($errors)) {
            $cover = bookCoverProcess('cover_image', $existingCover, isset($_POST['remove_cover']));
            if ($cover['error'] !== '') {
                $errors[] = $cover['error'];
            }
        }

        $editBook = [
            'id'               => $id,
            'isbn'             => $isbnRaw,
            'title'            => $titleRaw,
            'author'           => $authorRaw,
            'publisher'        => $publisherRaw,
            'category'         => $categoryRaw,
            'year_published'   => $yearRaw,
            'total_copies'     => $copies,
            'available_copies' => $available,
            'location'         => $locationRaw,
            'description'      => $descriptionRaw,
            'cover_image'      => $existingCover,
        ];

        if (!empty($errors)) {
            $msgType = 'danger';
            $msg     = implode(' ', $errors);
            $action  = $postAction === 'add' ? 'add' : 'edit';
        } else {
            $isbnSql = $isbn !== '' ? $isbn : null;
            $coverFile = $cover['filename'] !== null ? $cover['filename'] : null;

            try {
                if ($postAction === 'add') {
                    dbExecute(
                        $conn,
                        "INSERT INTO books (isbn, title, author, publisher, category, year_published, total_copies, available_copies, location, description, cover_image, status)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                        'sssssiiissss',
                        [$isbnSql, $title, $author, $publisher, $category, $year, $copies, $copies, $location, $desc, $coverFile, $status]
                    );
                    logActivity($conn, $_SESSION['user_id'], 'add_book', "Added: $title");
                    header("Location: books.php?msg=added");
                    exit();
                }

                dbExecute(
                    $conn,
                    "UPDATE books SET isbn = ?, title = ?, author = ?, publisher = ?, category = ?, year_published = ?,
                     total_copies = ?, available_copies = ?, location = ?, description = ?, cover_image = ?, status = ? WHERE id = ?",
                    'sssssiiissssi',
                    [$isbnSql, $title, $author, $publisher, $category, $year, $copies, $available, $location, $desc, $coverFile, $status, $id]
                );
                if (!empty($cover['old_to_delete'])) {
                    bookCoverDelete($cover['old_to_delete']);
                }
                logActivity($conn, $_SESSION['user_id'], 'update_book', "Updated: $title");
                header("Location: books.php?msg=updated");
                exit();
            } catch (mysqli_sql_exception $e) {
                if (!empty($cover['filename']) && $cover['filename'] !== $existingCover) {
                    bookCoverDelete($cover['filename']);
                }
                $msgType = 'danger';
                $msg     = (int)$e->getCode() === 1062
                    ? 'A book with that ISBN already exists.'
                    : 'Unable to save the book. Please try again.';
                $action  = $postAction === 'add' ? 'add' : 'edit';
            }
        }
    } elseif ($postAction === 'delete') {
        if (($_SESSION['user_role'] ?? '') !== 'admin') {
            return ['action' => $action, 'editBook' => $editBook, 'msg' => 'Only administrators can delete books.', 'msgType' => 'danger'];
        }

        $id      = (int)($_POST['id'] ?? 0);
        $records = (int)dbSelect($conn, "SELECT COUNT(*) c FROM borrow_records WHERE book_id = ?", 'i', [$id])->fetch_assoc()['c'];

        if ($records > 0) {
            $msgType = 'danger';
            $msg     = "Cannot delete this book: it has $records borrowing record(s) in the system. Consider marking it Unavailable instead.";
        } else {
            $bookRow = dbSelect($conn, "SELECT cover_image FROM books WHERE id = ?", 'i', [$id])->fetch_assoc();
            $coverToDelete = (string)($bookRow['cover_image'] ?? '');

            try {
                dbExecute($conn, "DELETE FROM books WHERE id = ?", 'i', [$id]);
                bookCoverDelete($coverToDelete);
                logActivity($conn, $_SESSION['user_id'], 'delete_book', "Deleted book #$id");
                header("Location: books.php?msg=deleted");
                exit();
            } catch (mysqli_sql_exception $e) {
                $msgType = 'danger';
                $msg     = 'Unable to delete this book because it is referenced by other records.';
            }
        }
    }

    return ['action' => $action, 'editBook' => $editBook, 'msg' => $msg, 'msgType' => $msgType];
}
