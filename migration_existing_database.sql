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
    reservation_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expiry_date DATE,
    status ENUM('pending', 'approved', 'cancelled', 'expired') DEFAULT 'pending',
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE CASCADE
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
ALTER TABLE borrow_records ADD COLUMN IF NOT EXISTS fine_paid ENUM('yes', 'no') DEFAULT 'no';

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
UPDATE books SET status = IF(available_copies > 0, 'available', 'unavailable');

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
('reservation_expiry_days', '2');
