# Installation & Configuration Guide

This guide details the technical requirements, environment setup, database installation, and file system permissions necessary to run the application.

---

## 1. System Requirements

- **PHP**: Version 7.4 or 8.0+ (requires extensions: `mysqli`, `session`, `json`, `mbstring`, `curl`, `openssl`).
- **Web Server**: Apache 2.4+ (mod_rewrite enabled, `.htaccess` override allowed).
- **Database**: MariaDB 10.4+ or MySQL 5.7+ / 8.0+ (`utf8mb4` character set).
- **Hosting Stack**: Tested on standard XAMPP / LAMP / WAMP environments.

---

## 2. Directory Layout & Standalone Application Architecture

The system consists of two independent, decoupled web applications communicating with the shared MariaDB database:
1. **`jntuacea`**: Faculty, HOD, Academic Section, Admin, and Superadmin Portal.
2. **`jntuaceastudents`**: Student Self-Service Portal (Profiles, Vault, Results, Certificates).

On development / shared environments:
```text
/Applications/XAMPP/xamppfiles/htdocs/classattendance.in/
├── .env.php                  <-- Shared environment configuration file (or placed locally)
├── jntuacea/                 <-- Staff / Admin Application (Independent)
│   ├── index.php
│   ├── dbcredentials.class.php
│   ├── services/
│   │   ├── FeatureManager.php
│   │   ├── ExamResultsService.php
│   │   ├── StudentProfileService.php
│   │   ├── BatchOBEService.php
│   │   ├── CourseMasterFilePDFService.php
│   │   ├── StudentAttendanceService.php
│   │   └── EBluebookPDFService.php
│   └── uploads/
└── jntuaceastudents/         <-- Student Portal Application (Independent)
    ├── index.php
    ├── dbcredentials.class.php
    ├── services/
    │   ├── FeatureManager.php
    │   ├── ExamResultsService.php
    │   └── StudentProfileService.php
    └── uploads/
```

On production servers, each application can be deployed to its own independent document root or subdomain (e.g., `jntuacea.classattendance.in` and `jntuaceastudents.classattendance.in`) with zero cross-application filesystem dependencies.

---

## 3. Environment Configuration (`.env.php`)

> [!IMPORTANT]
> The database connection class (`DBCredentials` in `dbcredentials.class.php`) in both applications dynamically looks for `.env.php` first in its own application root (`__DIR__ . '/.env.php'`) and falls back to the parent directory (`dirname(__DIR__) . '/.env.php'`).
> ```php
> if (!defined('DB_HOST')) {
>     if (file_exists(__DIR__ . '/.env.php')) {
>         require_once __DIR__ . '/.env.php';
>     } elseif (file_exists(dirname(__DIR__) . '/.env.php')) {
>         require_once dirname(__DIR__) . '/.env.php';
>     }
> }
> ```

Create or verify the `.env.php` file:

```php
<?php
// .env.php (Placed in parent directory of jntuacea)
define('DB_HOST', 'localhost');
define('DB_USER', 'u182589698_jntuaceasarb');
define('DB_PASS', 'YourStrongPasswordHere');
define('DB_NAME', 'u182589698_jntuaceasarb');
```

---

## 4. Database Setup & Initialization

1. Create the database in MariaDB / MySQL:
   ```sql
   CREATE DATABASE `u182589698_jntuaceasarb` 
   CHARACTER SET utf8mb4 
   COLLATE utf8mb4_general_ci;
   ```

2. Initialize database schema:
   ```bash
   mysql -u u182589698_jntuaceasarb -p u182589698_jntuaceasarb < schema_backup.sql
   ```

3. Verify that all 77 tables across 11 functional domains and constraints are loaded successfully:
   ```sql
   USE u182589698_jntuaceasarb;
   SHOW TABLES;
   ```
   *(A reference template is available in `dbcredentials.class.php.example`)*

---

## 5. File System Permissions

The application requires write access for logging and file attachments:

```bash
# Ensure web server (www-data, daemon, or _www on macOS) has write access:
chmod -R 775 logs/
chmod -R 775 uploads/

# Ensure subdirectories exist inside uploads/
mkdir -p uploads/cia_attachments
mkdir -p uploads/syllabus
mkdir -p uploads/student_docs
mkdir -p uploads/results_csv
chmod -R 775 uploads/cia_attachments
chmod -R 775 uploads/syllabus
chmod -R 775 uploads/student_docs
chmod -R 775 uploads/results_csv
```

---

## 6. Web Server Configuration

### Apache VirtualHost or Directory
Ensure `AllowOverride All` is set so that `.htaccess` rules (session settings and headers) are applied:

```apache
<Directory "/Applications/XAMPP/xamppfiles/htdocs/classattendance.in/jntuacea">
    Options Indexes FollowSymLinks
    AllowOverride All
    Require all granted
</Directory>
```

### Verification
Open `http://localhost/classattendance.in/jntuacea/` in your web browser. You should be greeted with the JNTUACEA Attendance System login portal.
