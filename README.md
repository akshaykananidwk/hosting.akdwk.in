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

## Status — STEP 0 complete ✅

This branch currently contains the **STEP 0** foundation:

- **`docs/STEP0-architecture.md`** — architecture + provisioning flow diagrams, full file tree, module build order
- **`install/sql/schema.sql`** — complete database: **64 tables** + indexes + foreign keys + seed data
- Repository skeleton (all folders)
- `version.json`, `.env.example`, `config/config.sample.php`, `.gitignore`

See `docs/STEP0-architecture.md` for the full plan and the 21-module build order.

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
