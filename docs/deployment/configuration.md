# Installation & Configuration Guide

This guide details the technical requirements, unified repository layout, environment setup, private document storage, database initialization, CLI tools, and file system permissions necessary to run the application.

---

## 1. System Requirements

- **PHP**: Version 8.0+ / 8.3+ (requires extensions: `mysqli`, `session`, `json`, `mbstring`, `curl`, `openssl`, `gd`, `zip`).
- **Web Server**: Apache 2.4+ (`mod_rewrite` enabled, `.htaccess` `AllowOverride All` enabled).
- **Database**: MariaDB 10.4+ or MySQL 8.0+ (`utf8mb4` character set).
- **Hosting Stack**: Tested on macOS MAMP, XAMPP, and Linux LAMP environments.

---

## 2. Directory Layout & Unified Application Architecture

The repository consists of two decoupled web portals (`jntuacea/` and `jntuaceastudents/`) sharing a synchronized 22-file Trusted Core (`core/`), a 90-table MariaDB/MySQL database (`database/`), CLI maintenance tools (`tools/`), and automated verification suites (`tests/`):

```text
/Applications/MAMP/htdocs/classattendance.in/
├── .env.example              <-- Environment configuration template
├── .env.php                  <-- Shared environment configuration file (git-ignored)
├── .htaccess                 <-- Root Apache access hardening rules
├── FACULTY_USER_MANUAL.md    <-- Complete 25-feature Faculty Operations Manual
├── database/
│   ├── baselines/            <-- Baseline schema verification files
│   ├── dumps/                <-- Complete 90-table SQL schema dump
│   ├── migrations/           <-- Active & archived replay-safe SQL migrations
│   └── seeds/                <-- Foundation seed data
├── docs/                     <-- Technical, database, workflow & deployment documentation
├── jntuacea/                 <-- Staff, Faculty, HOD, Academic Section, Admin & SuperAdmin Portal
│   ├── index.php
│   ├── dbcredentials.class.php
│   ├── core/                 <-- 22 Shared Trusted Core classes (namespace College)
│   ├── services/             <-- 21 Domain, PDF, Excel, Attainment, Review & AI services
│   ├── views/                <-- Modular UI view templates
│   ├── logs/                 <-- Daily activity and error logs (Apache-denied)
│   └── uploads/              <-- Attachments & legacy student docs (Apache-hardened)
├── jntuaceastudents/         <-- Student Self-Service Portal
│   ├── index.php
│   ├── dbcredentials.class.php
│   ├── core/                 <-- Synchronized copy of 22 Shared Trusted Core classes
│   ├── services/             <-- Student-scoped domain services
│   ├── logs/                 <-- Daily activity and error logs (Apache-denied)
│   └── uploads/
├── tests/                    <-- Reproducible workflow, core, attainment, AI & migration tests
└── tools/                    <-- CLI tools (migrate.php, baseline.php, sync_core.php, prepare_results_test.php, ai_worker.php, etc.)
```

On production servers, each portal can be deployed to its own document root or subdomain (e.g., `jntuacea.classattendance.in` and `jntuaceastudents.classattendance.in`) after synchronizing shared core files via `php tools/sync_core.php`.

---

## 3. Environment Configuration (`.env.php`)

Copy `.env.example` to `.env.php` in the repository root:
```bash
cp .env.example .env.php
```

> [!IMPORTANT]
> `DBCredentials` (`dbcredentials.class.php`) and `\College\Connection` (`core/Connection.php`) resolve `.env.php` first in the portal directory (`__DIR__ . '/.env.php'`) and fall back to the repository root (`dirname(__DIR__) . '/.env.php'`).

Configure `.env.php`:

```php
<?php
// Database Configuration (e.g., MAMP default port 8889 or standard 3306)
defined('DB_HOST') || define('DB_HOST', 'localhost:8889');
defined('DB_USER') || define('DB_USER', 'u182589698_jntuaceasarb');
defined('DB_PASS') || define('DB_PASS', 'YourStrongPasswordHere');
defined('DB_NAME') || define('DB_NAME', 'u182589698_jntuaceasarb');

// Private Student Document Repository (MUST reside outside the web document root)
defined('COLLEGE_DOCUMENT_ROOT') || define('COLLEGE_DOCUMENT_ROOT', '/var/lib/jntuacea/private_documents');

// Optional Cloudflare Turnstile CAPTCHA Secret
defined('TURNSTILE_SECRET') || define('TURNSTILE_SECRET', '');
```

---

## 4. Database Setup, Migrations & CLI Tools

1. **Create the Database**:
   ```sql
   CREATE DATABASE `u182589698_jntuaceasarb`
   CHARACTER SET utf8mb4
   COLLATE utf8mb4_general_ci;
   ```

2. **Initialize or Upgrade the Schema**:
   - **Fresh Local Schema Import**:
     ```bash
     mysql -u root -p u182589698_jntuaceasarb < database/dumps/u182589698_jntuaceasarb_local_db_scheme_.sql
     ```
   - **Upgrading an Existing Production Server Baseline**:
     Follow the step-by-step instructions in [production-migration-2026-10-14.md](./production-migration-2026-10-14.md) using `database/migrations/archive/migration_production_complete_20261014.sql`.
   - **CLI Migration & Baseline Runner (`tools/`)**:
     ```bash
     php tools/migrate.php
     php tools/baseline.php
     php tools/sync_core.php --check
     ```

3. **Verify Schema Completeness**:
   Confirm that all **90 tables** (821 columns across 12 functional domains) are present:
   ```sql
   USE u182589698_jntuaceasarb;
   SHOW TABLES;
   ```

---

## 5. File System & Private Document Storage Permissions

1. **Private Document Vault (`COLLEGE_DOCUMENT_ROOT`)**:
   Student document uploads (`\College\DocumentStorage`) are stored outside the web document root and streamed via `viewdocument.php`:
   ```bash
   mkdir -p /var/lib/jntuacea/private_documents/student_docs
   chmod -R 770 /var/lib/jntuacea/private_documents
   ```

2. **Portal Log & Attachment Directories**:
   ```bash
   chmod -R 775 jntuacea/logs/ jntuaceastudents/logs/
   mkdir -p jntuacea/uploads/cia_attachments jntuacea/uploads/syllabus jntuacea/uploads/student_docs jntuacea/uploads/results_csv
   chmod -R 775 jntuacea/uploads/ jntuaceastudents/uploads/
   ```

---

## 6. Web Server Configuration

Ensure `AllowOverride All` is enabled on the repository root so `.htaccess` rules protect `.env*`, `*.sql`, `database/`, `tools/`, `tests/`, `core/`, `logs/`, and `uploads/student_docs/`:

```apache
<Directory "/Applications/MAMP/htdocs/classattendance.in">
    Options FollowSymLinks
    AllowOverride All
    Require all granted
</Directory>
```

### Verification
- Staff / Admin Portal: `http://localhost:8888/classattendance.in/jntuacea/` (or port `80` depending on MAMP/Apache configuration)
- Student Portal: `http://localhost:8888/classattendance.in/jntuaceastudents/`
