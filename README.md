# AMIMS — Asset, Maintenance & Inventory Management System
### Oil & Gas Division

A complete web-based management system for tracking assets, scheduling maintenance, and managing inventory — built with PHP 8.x, MySQL, and vanilla JavaScript.

---

## Prerequisites

- [XAMPP](https://www.apachefriends.org) (Apache 2.4 + PHP 8.x + MySQL 8.x)
- [Composer](https://getcomposer.org/download/) (for PHPMailer + TCPDF)

---

## Setup Steps

### 1. Install and Start XAMPP
1. Download and install XAMPP from https://www.apachefriends.org
2. Open **XAMPP Control Panel**
3. Start **Apache** and **MySQL**

### 2. Copy Project Files
Copy the `amims/` folder to:
```
C:\xampp\htdocs\amims\
```

### 3. Install PHP Dependencies
Open Command Prompt or PowerShell in the project folder:
```bash
cd C:\xampp\htdocs\amims
composer install
```
This installs **PHPMailer** (email) and **TCPDF** (PDF generation).

### 4. Create the Database
1. Open your browser: **http://localhost/phpmyadmin**
2. Click **Import** (top menu)
3. Choose file: `C:\xampp\htdocs\amims\database\amims.sql`
4. Click **Go**

### 5. Set the Admin Password

Generate the bcrypt hash for `Admin@1234`:
```bash
php -r "echo password_hash('Admin@1234', PASSWORD_BCRYPT, ['cost'=>12]);"
```

Copy the output hash, then run this in phpMyAdmin's **SQL** tab:
```sql
UPDATE users SET password_hash='PASTE_HASH_HERE' WHERE email='admin@amims.ng';
```

Repeat for other seed users if needed (`david@amims.ng`, `fatima@amims.ng`, `emeka@amims.ng`).

### 6. Configure Email (Optional)
Edit `config/mail.php`:
```php
define('MAIL_USERNAME', 'your_gmail@gmail.com');
define('MAIL_PASSWORD', 'your_app_password');  // Gmail App Password
```

**How to create a Gmail App Password:**
1. Go to your Google Account → Security → 2-Step Verification (enable it)
2. Then go to Security → App passwords → Generate for "Mail"
3. Use that 16-character password in `MAIL_PASSWORD`

### 7. Open the Application
```
http://localhost/amims/modules/auth/login.php
```

**Default login credentials:**
| Role | Email | Password |
|------|-------|----------|
| Administrator | admin@amims.ng | Admin@1234 |
| Manager | emeka@amims.ng | Admin@1234 |
| Maintenance Officer | david@amims.ng | Admin@1234 |

---

## Automatic Daily Notifications (Optional)

The cron script checks for overdue maintenance and low stock every day.

**Option A — Windows Task Scheduler:**
1. Open Task Scheduler → Create Basic Task
2. Name: `AMIMS Daily Check`
3. Trigger: Daily at 7:00 AM
4. Action: Start a program → `C:\xampp\php\php.exe`
5. Arguments: `C:\xampp\htdocs\amims\cron\check_schedules.php`

**Option B — Manual trigger (browser):**
```
http://localhost/amims/cron/check_schedules.php
```

---

## Project Structure

```
amims/
├── config/          Database + mail configuration
├── includes/        Auth, helper functions, shared header
├── assets/          CSS (style.css) + JS (app.js)
├── modules/
│   ├── auth/        Login / logout
│   ├── dashboard/   Role-aware dashboard
│   ├── users/       User management (admin/manager)
│   ├── departments/ Department CRUD
│   ├── categories/  Category CRUD
│   ├── assets/      Asset register, edit, view
│   ├── maintenance/ Schedules, history, record task
│   ├── inventory/   Stock management, issue stock
│   ├── notifications/ Notification centre
│   └── reports/     PDF report generation + email
├── cron/            Daily schedule checker
├── database/        amims.sql (schema + seed data)
├── reports/pdf/     Generated PDF files (auto-created)
└── vendor/          Composer packages
```

---

## User Roles

| Role | Capabilities |
|------|-------------|
| **Administrator** | Full access: all modules, users, departments, categories, reports |
| **Manager** | Dashboard, assets, maintenance, inventory, reports (no user management) |
| **Maintenance Officer** | View own assigned tasks, record maintenance, view assets/inventory |

---

## Features

- **Asset Management** — Register, track, and update oil & gas assets with condition monitoring
- **Maintenance Scheduling** — Create preventive/corrective schedules with email notifications
- **Maintenance History** — Record and review all completed maintenance tasks
- **Inventory Management** — Track parts/supplies with automatic low-stock alerts
- **Notification Centre** — Tabbed notification hub with read/unread tracking
- **PDF Reports** — Four report types with optional email delivery
- **CSRF Protection** — All forms protected against CSRF attacks
- **Role-based Access** — Three-tier role system with route guards
- **Responsive Design** — Works on desktop and mobile (hamburger menu)

---

## Tech Stack

- **Backend:** PHP 8.x (pure PDO — no framework)
- **Database:** MySQL 8.x via phpMyAdmin
- **Frontend:** HTML5, CSS3, Vanilla JavaScript
- **Email:** PHPMailer (Gmail SMTP)
- **PDF:** TCPDF
- **Server:** XAMPP (Apache + PHP + MySQL)

---

*AMIMS Final Year Project — Oil & Gas Asset Management*
