# UIU Friends Loan & Crowdfunding

A peer-to-peer student **loan** and **crowdfunding** platform for UIU students, built with
**PHP, MySQL, HTML, CSS and JavaScript** — including a full **Admin Panel**.

## Features

**Students**
- Post loan requests, receive offers, accept one, e-sign a digital agreement, pay by EMI or in full (simulated bKash / Nagad / Card / Bank)
- **NID verification** — a verified National ID is required to borrow, lend or start a campaign, so every loan is tied to a real, identifiable person (and can be pursued legally if someone runs away). Other students only ever see a masked NID (`******1234`)
- Start crowdfunding campaigns with **full credentials**: beneficiary details, how the money is received, and many supporting documents (medical report, prescription, hospital estimate, recommendation letter, police GD, account proof...). Donors see which credentials are verified; the files themselves stay private
- **Report an Issue** — file complaints (fraud, non-payment, fake campaign, abusive chat...) with evidence, and read the admin's response
- Unified search (loans + campaigns), chat, wallet, profile, trust score, reviews

**Admin panel** (`/admin`)
- Dashboard with titled KPI cards (students, NID queue, loans, campaigns, complaints, agreements, volumes)
- **NID review**: see NID number, date of birth, address and front/back photos, then Approve / Reject with a reason
- **Complaints**: list + detail page with evidence, linked agreement, chat history and other reports against the same user; set status, reply to the reporter, suspend the account, mark an agreement as disputed
- Verify each campaign document individually, verify/reject campaigns, moderate loan posts, view all transactions, verify/suspend users

## Requirements
- PHP 8.0+ (extensions `pdo_mysql` and `fileinfo` - both on by default in XAMPP)
- MySQL 5.7+ / MariaDB 10.4+
- Apache (XAMPP / WAMP) or PHP's built-in server

## Quick start (XAMPP)
1. Copy this folder to `C:\xampp\htdocs\uiu-friends`
2. Start **Apache** and **MySQL** in the XAMPP control panel
3. phpMyAdmin -> *Databases* -> create `uiu_friends` -> select it -> **Import** -> `database/schema.sql`
4. Check `config/db.php` (defaults: user `root`, empty password)
5. Open `http://localhost/uiu-friends/login.php`

Without Apache: `mysql -u root < database/schema.sql` then `php -S localhost:8000`.

### Upgrading an existing database
Already imported the older schema? Run `database/migration_v2.sql` once (phpMyAdmin -> SQL tab).
Existing students start with *NID not submitted* and must verify before borrowing.

## Demo logins (password for all: `password123`)
| Role | Email | Notes |
|---|---|---|
| Student | nadia@bscee.uiu.ac.bd | Borrower with an active agreement |
| Student | mahmud@bscse.uiu.ac.bd | Lender |
| Student | rashed@bscse.uiu.ac.bd | Campaign organiser |
| Student | karim@bscse.uiu.ac.bd | **NID pending** - good for testing the NID gate |
| Admin | admin@uiufriends.com | `/admin/login.php` |

> Demo NID numbers are fake. **Change every password and the admin account before any real deployment.**

## Project structure
```
config/db.php              database connection
database/schema.sql        full schema + demo data (fresh install)
database/migration_v2.sql  upgrade for older databases
includes/                  helpers (auth, CSRF, uploads, NID) + layout partials
assets/                    css, js, images
uploads/                   PRIVATE files (NID photos, documents) - blocked from direct access
file.php                   permission-checked file viewer (students)
home, loan_posts, loan_details, agreement, payment, summary
crowdfunding, campaign_details, search, messages, wallet, profile
security.php               NID + verification + password
report.php                 "Report an Issue"
admin/                     dashboard, users, view_user (NID review), loan_posts, view_post,
                           campaigns, view_campaign (credentials), transactions,
                           reports, view_report, file.php (admin file viewer)
```

## Security notes
- Passwords: `password_hash()` (bcrypt) - CSRF tokens on all forms - PDO prepared statements - escaped output
- Uploads: JPG/PNG/PDF only, 4 MB max, content type checked, random file names, stored in `uploads/`
  (denied by `.htaccess` on Apache) and streamed only through permission-checked scripts.
  **On Nginx add** `location /uploads { deny all; }`. Make sure PHP's `upload_max_filesize` and
  `post_max_size` are at least 8M for multi-document uploads
- Suspended accounts are logged out immediately and cannot log in
- Payments are **simulated** - integrate a real gateway (SSLCommerz / bKash API) in `payment.php` for production
- NID numbers are stored in plain text so admins can verify them; restrict database access accordingly
