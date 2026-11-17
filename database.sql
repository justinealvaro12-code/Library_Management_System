-- ============================================
-- LIBRARY MANAGEMENT SYSTEM - RMC
-- Database: lms_rmc
-- ============================================

CREATE DATABASE IF NOT EXISTS lms_rmc;
USE lms_rmc;

-- ============================================
-- USERS TABLE (Admin, Librarian, Student)
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

-- ============================================
-- BOOKS TABLE
-- ============================================
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

-- ============================================
-- BORROW RECORDS TABLE
-- ============================================
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

-- ============================================
-- RESERVATIONS TABLE
-- ============================================
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

-- ============================================
-- SYSTEM SETTINGS
-- ============================================
CREATE TABLE IF NOT EXISTS settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ============================================
-- ACTIVITY LOGS
-- ============================================
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
-- DEFAULT DATA
-- ============================================

-- Default Admin
INSERT IGNORE INTO users (full_name, email, password, role) VALUES
('System Admin', 'admin@rmc.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');

-- Default Librarian
INSERT IGNORE INTO users (full_name, email, password, role) VALUES
('Maria Santos', 'librarian@rmc.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'librarian');

-- Default Settings
INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
('library_name', 'Regis Marie College Library'),
('loan_period_days', '7'),
('fine_per_day', '20.00'),
('max_books_per_student', '3'),
('reservation_expiry_days', '2'),
('max_active_reservations', '3'),
('reservation_last_sweep', '1970-01-01 00:00:00'),
('block_unpaid_fines', '0');


-- ============================================
-- NOTE: Default passwords are all 'password'
-- Change immediately after first login!
-- ============================================
