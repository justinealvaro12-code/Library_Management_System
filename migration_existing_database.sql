-- ============================================
-- MIGRATION FOR EXISTING lms_rmc DATABASES
-- Safe to run more than once. Brings an older
-- database to the same structure as database.sql.
-- ============================================

USE lms_rmc;

-- ============================================
-- TABLES (create any that are missing)
-- ============================================
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id VARCHAR(20) UNIQUE,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'librarian', 'student') NOT NULL DEFAULT 'student',
    contact_number VARCHAR(20),
    address TEXT,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS books (
    id INT AUTO_INCREMENT PRIMARY KEY,
    isbn VARCHAR(20) UNIQUE,
    title VARCHAR(255) NOT NULL,
    author VARCHAR(150) NOT NULL,
    publisher VARCHAR(150),
    category VARCHAR(100),
    year_published SMALLINT UNSIGNED NULL,
    total_copies INT DEFAULT 1,
    available_copies INT DEFAULT 1,
    description TEXT,
    cover_image VARCHAR(255),
    location VARCHAR(100),
    status ENUM('available', 'unavailable') DEFAULT 'available',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS borrow_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    book_id INT NOT NULL,
    issue_date DATE NOT NULL,
    due_date DATE NOT NULL,
    return_date DATE DEFAULT NULL,
    fine_amount DECIMAL(10,2) DEFAULT 0.00,
    fine_paid ENUM('yes', 'no') DEFAULT 'no',
    status ENUM('borrowed', 'returned', 'overdue') DEFAULT 'borrowed',
    issued_by INT,
    received_by INT,
    remarks TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE CASCADE,
    FOREIGN KEY (issued_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (received_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS reservations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    book_id INT NOT NULL,
    reservation_date TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expiry_date DATETIME DEFAULT NULL,
    status ENUM('waiting', 'ready', 'fulfilled', 'cancelled', 'expired') NOT NULL DEFAULT 'waiting',
    active_flag TINYINT GENERATED ALWAYS AS (CASE WHEN status IN ('waiting', 'ready') THEN 1 ELSE NULL END) STORED,
    UNIQUE KEY uq_reservations_active (user_id, book_id, active_flag),
    KEY user_id (user_id),
    KEY book_id (book_id),
    KEY idx_reservations_queue (book_id, status, reservation_date, id),
    KEY idx_reservations_user (user_id, status, reservation_date, id),
    KEY idx_reservations_expiry (status, expiry_date),
    CONSTRAINT fk_reservations_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_reservations_book FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE RESTRICT
);

CREATE TABLE IF NOT EXISTS settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(255),
    details TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- ============================================
-- COLUMNS ADDED AFTER THE FIRST RELEASE
-- ============================================
-- YEAR cannot store years before 1901, so widen the column.
ALTER TABLE books MODIFY COLUMN year_published SMALLINT UNSIGNED NULL;
ALTER TABLE books ADD COLUMN IF NOT EXISTS status ENUM('available', 'unavailable') DEFAULT 'available';
ALTER TABLE books ADD COLUMN IF NOT EXISTS cover_image VARCHAR(255) AFTER description;
ALTER TABLE borrow_records ADD COLUMN IF NOT EXISTS fine_paid ENUM('yes', 'no') DEFAULT 'no';

-- ============================================
-- RESERVATIONS UPGRADE (safe to re-run)
-- Enum and expiry changes happen before the generated
-- active_flag column is added, because MariaDB restricts
-- altering a column referenced by a generated column.
-- ============================================
DELIMITER //
DROP PROCEDURE IF EXISTS migrate_reservations_schema //
CREATE PROCEDURE migrate_reservations_schema()
BEGIN
    DECLARE v_status_type LONGTEXT;
    DECLARE v_expiry_type VARCHAR(64);
    DECLARE v_fk_name VARCHAR(64);
    DECLARE v_delete_rule VARCHAR(64);
    DECLARE v_count INT;
    DECLARE v_sql LONGTEXT;
    DECLARE v_needs_tighten TINYINT DEFAULT 0;

    SELECT COLUMN_TYPE INTO v_status_type
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'reservations' AND COLUMN_NAME = 'status';

    IF v_status_type LIKE '%pending%' OR v_status_type LIKE '%approved%' THEN
        ALTER TABLE reservations
            MODIFY COLUMN status ENUM('pending','approved','waiting','ready','fulfilled','cancelled','expired')
            NOT NULL DEFAULT 'waiting';
        UPDATE reservations SET status = 'waiting' WHERE status = 'pending';
        UPDATE reservations SET status = 'ready' WHERE status = 'approved';
        SET v_needs_tighten = 1;
    ELSEIF v_status_type NOT LIKE '%fulfilled%' THEN
        ALTER TABLE reservations
            MODIFY COLUMN status ENUM('waiting','ready','fulfilled','cancelled','expired')
            NOT NULL DEFAULT 'waiting';
    END IF;

    SELECT DATA_TYPE INTO v_expiry_type
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'reservations' AND COLUMN_NAME = 'expiry_date';

    IF v_expiry_type IS NULL THEN
        ALTER TABLE reservations ADD COLUMN expiry_date DATETIME NULL;
    ELSEIF v_expiry_type <> 'datetime' THEN
        ALTER TABLE reservations MODIFY COLUMN expiry_date DATETIME NULL;
    END IF;

    UPDATE reservations r
    JOIN (
        SELECT user_id, book_id, MIN(id) AS keep_id
        FROM reservations
        WHERE status IN ('waiting','ready')
        GROUP BY user_id, book_id
        HAVING COUNT(*) > 1
    ) d ON d.user_id = r.user_id AND d.book_id = r.book_id
    SET r.status = 'cancelled', r.expiry_date = NULL
    WHERE r.status IN ('waiting','ready') AND r.id <> d.keep_id;

    IF v_needs_tighten = 1 THEN
        ALTER TABLE reservations
            MODIFY COLUMN status ENUM('waiting','ready','fulfilled','cancelled','expired')
            NOT NULL DEFAULT 'waiting';
    END IF;

    SELECT COUNT(*) INTO v_count
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'reservations' AND COLUMN_NAME = 'active_flag';

    IF v_count = 0 THEN
        ALTER TABLE reservations
            ADD COLUMN active_flag TINYINT
            GENERATED ALWAYS AS (CASE WHEN status IN ('waiting','ready') THEN 1 ELSE NULL END) STORED;
    END IF;

    SELECT COUNT(*) INTO v_count FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'reservations' AND INDEX_NAME = 'user_id';
    IF v_count = 0 THEN
        CREATE INDEX user_id ON reservations (user_id);
    END IF;

    SELECT COUNT(*) INTO v_count FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'reservations' AND INDEX_NAME = 'book_id';
    IF v_count = 0 THEN
        CREATE INDEX book_id ON reservations (book_id);
    END IF;

    SELECT COUNT(*) INTO v_count FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'reservations' AND INDEX_NAME = 'uq_reservations_active';
    IF v_count = 0 THEN
        CREATE UNIQUE INDEX uq_reservations_active ON reservations (user_id, book_id, active_flag);
    END IF;

    SELECT COUNT(*) INTO v_count FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'reservations' AND INDEX_NAME = 'idx_reservations_queue';
    IF v_count = 0 THEN
        CREATE INDEX idx_reservations_queue ON reservations (book_id, status, reservation_date, id);
    END IF;

    SELECT COUNT(*) INTO v_count FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'reservations' AND INDEX_NAME = 'idx_reservations_user';
    IF v_count = 0 THEN
        CREATE INDEX idx_reservations_user ON reservations (user_id, status, reservation_date, id);
    END IF;

    SELECT COUNT(*) INTO v_count FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'reservations' AND INDEX_NAME = 'idx_reservations_expiry';
    IF v_count = 0 THEN
        CREATE INDEX idx_reservations_expiry ON reservations (status, expiry_date);
    END IF;

    SET v_fk_name = NULL;
    SET v_delete_rule = NULL;
    SELECT CONSTRAINT_NAME, DELETE_RULE INTO v_fk_name, v_delete_rule
    FROM information_schema.REFERENTIAL_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'reservations'
      AND REFERENCED_TABLE_NAME = 'users'
    LIMIT 1;

    IF v_fk_name IS NULL THEN
        ALTER TABLE reservations
            ADD CONSTRAINT fk_reservations_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT;
    ELSEIF v_delete_rule NOT IN ('RESTRICT', 'NO ACTION') THEN
        SET v_sql = CONCAT('ALTER TABLE reservations DROP FOREIGN KEY `', REPLACE(v_fk_name, '`', '``'), '`');
        PREPARE stmt FROM v_sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
        ALTER TABLE reservations
            ADD CONSTRAINT fk_reservations_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT;
    END IF;

    SET v_fk_name = NULL;
    SET v_delete_rule = NULL;
    SELECT CONSTRAINT_NAME, DELETE_RULE INTO v_fk_name, v_delete_rule
    FROM information_schema.REFERENTIAL_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'reservations'
      AND REFERENCED_TABLE_NAME = 'books'
    LIMIT 1;

    IF v_fk_name IS NULL THEN
        ALTER TABLE reservations
            ADD CONSTRAINT fk_reservations_book FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE RESTRICT;
    ELSEIF v_delete_rule NOT IN ('RESTRICT', 'NO ACTION') THEN
        SET v_sql = CONCAT('ALTER TABLE reservations DROP FOREIGN KEY `', REPLACE(v_fk_name, '`', '``'), '`');
        PREPARE stmt FROM v_sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
        ALTER TABLE reservations
            ADD CONSTRAINT fk_reservations_book FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE RESTRICT;
    END IF;
END //
DELIMITER ;

CALL migrate_reservations_schema();
DROP PROCEDURE migrate_reservations_schema;

-- ============================================
-- DATA CLEANUP
-- ============================================
-- Normalize empty values that should be NULL (UNIQUE columns included).
UPDATE books SET year_published = NULL WHERE year_published = 0;
UPDATE books SET isbn = NULL WHERE isbn = '';
UPDATE users SET student_id = NULL WHERE student_id = '';

-- Decode old HTML entities. &amp; must be decoded last.
UPDATE users SET
    full_name      = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(full_name, '&#039;', CHAR(39)), '&quot;', '"'), '&lt;', '<'), '&gt;', '>'), '&amp;', '&'),
    contact_number = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(contact_number, '&#039;', CHAR(39)), '&quot;', '"'), '&lt;', '<'), '&gt;', '>'), '&amp;', '&'),
    address        = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(address, '&#039;', CHAR(39)), '&quot;', '"'), '&lt;', '<'), '&gt;', '>'), '&amp;', '&'),
    student_id     = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(student_id, '&#039;', CHAR(39)), '&quot;', '"'), '&lt;', '<'), '&gt;', '>'), '&amp;', '&');

UPDATE books SET
    title       = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(title, '&#039;', CHAR(39)), '&quot;', '"'), '&lt;', '<'), '&gt;', '>'), '&amp;', '&'),
    author      = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(author, '&#039;', CHAR(39)), '&quot;', '"'), '&lt;', '<'), '&gt;', '>'), '&amp;', '&'),
    publisher   = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(publisher, '&#039;', CHAR(39)), '&quot;', '"'), '&lt;', '<'), '&gt;', '>'), '&amp;', '&'),
    category    = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(category, '&#039;', CHAR(39)), '&quot;', '"'), '&lt;', '<'), '&gt;', '>'), '&amp;', '&'),
    location    = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(location, '&#039;', CHAR(39)), '&quot;', '"'), '&lt;', '<'), '&gt;', '>'), '&amp;', '&'),
    description = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(description, '&#039;', CHAR(39)), '&quot;', '"'), '&lt;', '<'), '&gt;', '>'), '&amp;', '&');

-- Keep the book status column consistent with the copy counts.
UPDATE books b
SET status = IF(
    GREATEST(0, b.available_copies - (
        SELECT COUNT(*) FROM reservations r WHERE r.book_id = b.id AND r.status = 'ready'
    )) > 0,
    'available', 'unavailable'
);

-- ============================================
-- DEFAULT DATA (existing rows are left untouched)
-- ============================================
INSERT IGNORE INTO users (full_name, email, password, role) VALUES
('System Admin', 'admin@rmc.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');

INSERT IGNORE INTO users (full_name, email, password, role) VALUES
('Maria Santos', 'librarian@rmc.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'librarian');

INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
('library_name', 'Regis Marie College Library'),
('loan_period_days', '7'),
('fine_per_day', '20.00'),
('max_books_per_student', '3'),
('reservation_expiry_days', '2'),
('max_active_reservations', '3'),
('reservation_last_sweep', '1970-01-01 00:00:00'),
('block_unpaid_fines', '0');
