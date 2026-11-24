# Passenger Information System (PIS) — Starter Skeleton

BIC21203 Web Development Group Project | 7 members | PHP + MySQL

## Setup (every member, local machine)

1. Install XAMPP (or WAMP).
2. Copy this whole `pis-website` folder into `htdocs/`.
3. Start Apache + MySQL in the XAMPP control panel.
4. Open phpMyAdmin (`http://localhost/phpmyadmin`), create a database, and
   import `database/schema.sql` (this creates all tables + sample data +
   a demo admin login: `admin` / `admin123`).
5. Visit `http://localhost/pis-website/public/index.php` — you should see
   the home page with sample departures.
6. Visit `http://localhost/pis-website/admin/login.php` and log in with
   the demo account to reach the admin dashboard.

If your MySQL root user has a password, update `config/db.php` with it.

## Folder structure

```
pis-website/
├── config/
│   ├── db.php          <- shared PDO database connection (already done)
│   └── auth.php         <- shared login/session helper (already done)
├── includes/
│   ├── header.php        <- shared navbar + <head> (already done)
│   └── footer.php        <- shared footer + scripts (already done)
├── assets/
│   ├── css/style.css     <- shared site-wide styles
│   └── js/script.js       <- shared site-wide JS
├── database/
│   └── schema.sql         <- run this once in phpMyAdmin
├── public/                <- public-facing pages
│   └── index.php (done, template to copy)
└── admin/                 <- staff-only pages (behind login)
    ├── login.php (done)
    ├── dashboard.php (done)
    ├── logout.php (done)
    └── manage_trains.php (done, template for other CRUD pages)
```

## How to add a new page (copy this pattern)

Every PUBLIC page should look like:

```php
<?php
require_once __DIR__ . '/../config/db.php';
$page_title = 'Your Page Title';
// ... fetch any data you need with $pdo here ...
require_once __DIR__ . '/../includes/header.php';
?>

<!-- your page's HTML goes here -->

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
```

Every ADMIN page should look like:

```php
<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_login();  // must be called before ANY HTML output

$page_title = 'Your Page Title';
require_once __DIR__ . '/../includes/header.php';
?>

<!-- your page's HTML goes here -->

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
```

**Always use prepared statements** (`$pdo->prepare(...)->execute([...])`) for
any query involving user input — never concatenate variables into SQL
strings. This is what the CRUD Security marks are based on.

## Remaining pages to build (task division — 2 pages per member)

| Owner | Page | File | Status |
|---|---|---|---|
| Member 1 | Home | `public/index.php` | ✅ done |
| Member 1 | About | `public/about.php` | ✅ done |
| Member 2 | Live Departures Board | `public/departures.php` | ✅ done |
| Member 2 | Search Schedule | `public/search.php` | ✅ done |
| Member 3 | Station Information | `public/stations.php` | ✅ done |
| Member 3 | Help / FAQ | `public/faq.php` | ✅ done |
| Member 4 | Alerts (public) | `public/alerts.php` | ✅ done |
| Member 4 | Contact / Feedback | `public/contact.php` | ✅ done|
| Member 5 | Admin Login | `admin/login.php` | ✅ done |
| Member 5 | Admin Dashboard | `admin/dashboard.php` | ✅ done |
| Member 6 | Manage Trains & Schedules | `admin/manage_trains.php` | ✅ done (template) |
| Member 6 | Manage Delays & Alerts | `admin/manage_alerts.php` | ✅ done (copy manage_trains.php pattern) |
| Member 7 | Manage Stations/Platforms | `admin/manage_stations.php` | ✅ done (copy manage_trains.php pattern) |
| Member 7 | Manage Staff Accounts | `admin/manage_users.php` |  ✅ done (copy manage_trains.php pattern) |

Members 6 & 7: `manage_trains.php` is a full working CRUD example
(Create, Read, Delete all working — Edit linked but not yet built, that's
a good addition). Copy its structure for your own tables.

## Database tables (database/schema.sql)

- `stations` — station_id, station_name, station_code, platform_count
- `trains` — train_id, train_no, line_name, origin_station_id, destination_station_id
- `schedules` — links trains to station stops with times + live status
- `alerts` — announcements/delay messages shown on the public site
- `feedback` — contact form submissions
- `admin_users` — staff login accounts (bcrypt-hashed passwords)

## Before final submission

- [ ] Change the demo admin password
- [ ] Remove/hide the "demo account" hint shown on the login page
- [ ] Test on a phone-width screen (responsive design marks)
- [ ] Make sure every CRUD page uses prepared statements
- [ ] Take screenshots of each page for Chapter 4 of the report
