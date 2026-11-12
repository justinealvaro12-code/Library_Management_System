-- ============================================
-- MIGRATION FOR EXISTING lms_rmc DATABASES
-- Run this once against an existing database.
-- ============================================

USE lms_rmc;

-- YEAR cannot store years before 1901, so widen the column.
ALTER TABLE books MODIFY COLUMN year_published SMALLINT UNSIGNED NULL;

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

-- Create any missing default settings rows.
INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
('library_name', 'Regis Marie College Library'),
('loan_period_days', '7'),
('fine_per_day', '5.00'),
('max_books_per_student', '3'),
('reservation_expiry_days', '2');
