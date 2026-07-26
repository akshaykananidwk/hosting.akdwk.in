# AK Cloud — aaPanel-powered SaaS Hosting & Billing Platform

WHMCS જેવું પણ હલકું, **aaPanel (BT Panel) API** પર ચાલતું, ભારતીય માર્કેટ માટેનું
multi-tenant SaaS hosting + billing platform. Custom lightweight PHP 8.2 MVC (Laravel/CI વગર),
Bootstrap 5.3 + Alpine.js frontend, MySQL job queue, WhatsApp + Email automation.

> **Customer ક્યારેય aaPanel માં login કરતો નથી** — એ ફક્ત AK Cloud panel વાપરે છે.
> Order → payment → aaPanel API થી આપોઆપ site + FTP + DB + SSL બની જાય → credentials WhatsApp પર જાય.

---

## Target server

| | |
|---|---|
| Panel | aaPanel PRO 8.0.4 |
| OS | Ubuntu 24.04 |
| Stack | PHP 8.2 · MySQL 8 / MariaDB 10.6+ · Nginx |
| Capacity | ~120–180 shared hosting customers on this VPS |

---

## Status — બધા modules પૂરા ✅

| Layer | What's in the repo |
|---|---|
| **STEP 0** | `docs/STEP0-architecture.md` (diagrams, file tree, build order), `install/sql/schema.sql` — **64 tables** + FKs + seed data |
| **Core (M2)** | Router, Request/Response, PDO Database + QueryBuilder, Model, Auth, Session, Crypt (AES-256+HMAC), Validator, Cache, Logger, Event, Mail (raw SMTP), View |
| **Installer (M1)** | `/install` — 10-step wizard, generates `.env` + `config.php` + `installed.lock` |
| **Auth (M3)** | login / register / forgot / reset / 2FA (TOTP + WhatsApp OTP), lockout, audit log |
| **aaPanel (M5)** | `AaPanelService` — full BT API (site/db/ftp/ssl/files/cron), signature auth + cookie jar + retries + logging |
| **aaPanel gaps** | `QuotaService` (disk), `BandwidthService` (nginx log parsing), `IsolationService` (per-site PHP-FPM pool + Linux user) |
| **Provisioning (M7)** | order → site + DB + FTP + SSL + isolation, 3 retries, **rollback**, WhatsApp+Email delivery |
| **Billing (M8)** | invoices, GST (CGST/SGST/IGST), gateways (Razorpay + manual UPI), overdue suspend, renewals |
| **WhatsApp (M9)** | queue worker, 22 Gujarati templates, number formatter, rate limits, logs |
| **Areas** | Admin (M15), Client (M10), Reseller (M13), Store/checkout (M6/7/8), Tickets (M12) |
| **System** | Cron single entry + 16 jobs (M17), GitHub auto-updater + rollback (M18), Backups (M19), REST API (M20), Settings (M21), Reports + GSTR-1 CSV (M16) |

**Verification (run on every change):** 139 PHP files lint clean · 95/95 route actions resolve to real controller methods · all 48 views render real content · 22/22 integration assertions pass (routing, CSRF 419, guest redirects, API 401, AES roundtrip + tamper detection, SQL-injection rejection, GST math, WhatsApp number formatting, cron expressions).

See `docs/STEP0-architecture.md` for the architecture and module map.

---

## Tech stack (fixed)

| Layer | Technology |
|---|---|
| Backend | PHP 8.2, custom lightweight MVC |
| DB | MySQL 8 / MariaDB, PDO prepared statements |
| Frontend | Bootstrap 5.3 + Alpine.js + Chart.js (all local, no CDN) |
| Queue | MySQL job queue + Linux cron |
| Server API | aaPanel BT Panel API (signature auth + cookie jar) |
| Notifications | WhatsApp (`bulk.akdwk.in`) + SMTP email |

---

## Installation (once code modules are built)

1. Upload files to the panel site's webroot (or `git pull`).
2. Open `/install` → 10-step wizard (requirements → DB → schema → admin → aaPanel → WhatsApp → cron → finish).
3. No file needs to be edited by hand — the installer generates `.env` + `config/config.php`.
4. Add the cron entry the installer shows:
   ```
   */5 * * * * /www/server/php/82/bin/php /www/wwwroot/YOUR_DOMAIN/cron/cron.php >/dev/null 2>&1
   ```

---

## Security notes (from the build spec)

- All queries use PDO prepared statements; all output escaped; CSRF on every POST.
- `shell_exec` calls (quota/isolation) always wrapped with `escapeshellarg()`.
- File Manager enforces `realpath()` path-traversal checks — cannot escape the site folder.
- API keys / passwords stored AES-256 encrypted; the aaPanel key never reaches the frontend.
- **Shared hosting must run with isolation on** (per-site php-fpm pool + Linux user) — see Module 5(c).
