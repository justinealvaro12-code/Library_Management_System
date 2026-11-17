# RMC Library Management System

A PHP/MySQL library system for Regis Marie College with student, librarian, and administrator roles.

## Requirements

- XAMPP with Apache, PHP 8.0+, and MariaDB/MySQL
- Web browser

## XAMPP Setup

1. Install XAMPP and start **Apache** and **MySQL** from the XAMPP Control Panel.
2. Place this project in `C:\xampp\htdocs\lms` (the folder containing `index.php`).
3. Open `http://localhost/lms` in your browser.

## Import the Database

For a new installation, import `database.sql`:

- **phpMyAdmin:** open `http://localhost/phpmyadmin`, go to Import, choose `database.sql`, and click Go.
- **Command line:** `C:\xampp\mysql\bin\mysql.exe -u root < database.sql`

The script creates the `lms_rmc` database, tables, and default data. It is safe to run more than once.

If you already have an older `lms_rmc` database, run `migration_existing_database.sql` instead to add missing tables, columns, and default settings.

## Reservations and overdue notices

Students can reserve a title when no unheld copies are available. Reservations enter a first-come-first-served waiting queue. When a copy becomes available, the next waiting reservation is marked ready for pickup for the configured number of days. Librarians fulfill a ready reservation only from **Issue Book**; expired or cancelled holds release the copy to the next student.

Expiry is lazy and runs from normal student, librarian, and administrator pages and circulation actions. A shared sweep timestamp in `settings.reservation_last_sweep` throttles the sweep to about once per minute. The database session timezone is set to `+08:00`, matching PHP's `Asia/Manila` timezone.

Administrators can set **Reservation Pickup Window** from 1 to 30 days (default 2) and **Max Active Reservations Per Student** from 1 to 10 (default 3) in **Settings**. Overdue notices are computed from active loans and the current fine-per-day setting; no overdue status or accruing fine is stored on the loan row. Admin and librarian overdue pages provide a paginated queue and CSV export.

For an existing installation, run `migration_existing_database.sql`. It upgrades the reservation status and expiry columns before adding the generated active flag and unique active-hold index, cleans duplicate active holds, and changes reservation foreign keys to restrict deletes. The migration is designed to be rerun safely; back up the database before applying schema migrations.

## Local Configuration

The default settings in `includes/config.php` match a standard XAMPP installation (`root` user, empty password, database `lms_rmc`).

To use different credentials or a different URL:

1. Copy `includes/config.local.php.example` to `includes/config.local.php`.
2. Uncomment and edit the values you need.
3. Leave `includes/config.local.php` out of version control. It is gitignored.

## Default Login Accounts

| Role | Email | Password |
|---|---|---|
| Administrator | `admin@rmc.edu.ph` | `password` |
| Librarian | `librarian@rmc.edu.ph` | `password` |

**Security warning:** these are default accounts for local setup only. Change both passwords immediately after the first login, using **My Profile** or **Admin > Users > Reset PW**. Do not use these defaults on a public or production server.

Students create their own accounts through the **Create an account** link on the login page.

## Student ID Rule

Student IDs must be **4 to 20 characters** and may only contain **letters, numbers, and dashes**. Leading/trailing spaces are trimmed.

- Valid examples: `2026-0001`, `202300166`, `REG-9001`
- Invalid examples: `123` (too short), `2026 0001` (space), `2026_0001` (underscore)

Student IDs must also be unique.

## Vendored Chart.js

Reports use a local copy of Chart.js so charts work offline on XAMPP.

- Version: **4.4.7** (official `chart.js` npm package, downloaded from jsDelivr)
- File: `assets/js/chart.umd.min.js`
- SHA-256: `206b6e8bb00fc7bba2c7ee80ca41db3e9e05ba7be0aa35abeba9cfd5357f5d0e`
- License: `assets/js/chart.LICENSE.md` (MIT)
- License SHA-256: `41a84aa2caba645f966a18d9c2056b73e6d3a81d80bc0046bc0011a2634d4cce`
- The bundle includes the Chart.js UMD build and exposes `window.Chart`; no CDN is loaded at runtime.
