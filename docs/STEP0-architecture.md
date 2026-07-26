# AK CLOUD — STEP 0
### aaPanel-powered Multi-Tenant SaaS Hosting & Billing Platform

> આ document એ **STEP 0** છે — કોડ પહેલાંનું architecture, file tree, database schema અને
> module build order. Schema અલગ file `install/sql/schema.sql` માં પૂરી છે (64 tables + seed data).

---

## 1. ARCHITECTURE (ASCII)

```
                         ┌─────────────────────────────────────────────┐
                         │              CUSTOMER / CLIENT               │
                         │      (browser — panel.akdwk.in only)         │
                         └───────────────────────┬─────────────────────┘
                                                 │ HTTPS
                                                 ▼
        ┌────────────────────────────────────────────────────────────────────────┐
        │                        AK CLOUD  (this VPS, inside aaPanel site)         │
        │                                                                          │
        │   index.php  ──►  Router ──►  Middleware ──►  Controller ──►  View       │
        │      (front         │         (Auth, CSRF,      (Admin /       (Bootstrap │
        │      controller)    │          Tenant scope,     Reseller /     5 +       │
        │                     │          RateLimit)        Client / Api)  Alpine)   │
        │                     ▼                                                     │
        │   ┌──────────────────────────── app/Core ─────────────────────────────┐  │
        │   │ DB(PDO) · Auth · Session · Validator · Cache · Logger · Mail       │  │
        │   │ Crypt(AES-256) · Event/Hook · View · Request · Response            │  │
        │   └────────────────────────────────────────────────────────────────────┘ │
        │                     │                                                     │
        │   ┌──────────────── app/Services ─────────────────────────────────────┐  │
        │   │ AaPanelService ★  ProvisioningService  BillingService             │  │
        │   │ WhatsAppService   QuotaService  BandwidthService  IsolationService │  │
        │   │ BackupService     UpdateService                                    │  │
        │   └───────┬──────────────────────┬───────────────────────┬────────────┘  │
        │           │                      │                       │               │
        └───────────┼──────────────────────┼───────────────────────┼───────────────┘
                    │                      │                       │
      ┌─────────────▼──────┐   ┌───────────▼──────────┐   ┌────────▼───────────────┐
      │  MySQL / MariaDB   │   │  aaPanel BT API      │   │  WhatsApp Gateway      │
      │  (64 tables)       │   │  127.0.0.1:35435     │   │  bulk.akdwk.in/api.php │
      │  + MySQL job queue │   │  (signature auth +   │   │                        │
      │                    │   │   cookie jar)        │   │  + SMTP (email)        │
      └────────────────────┘   └──────────┬───────────┘   └────────────────────────┘
                                          │
                    ┌─────────────────────▼──────────────────────┐
                    │   Linux layer (shell_exec, escapeshellarg)  │
                    │   du · setquota · php-fpm pools · useradd    │
                    │   nginx access logs (bandwidth parsing)      │
                    └──────────────────────────────────────────────┘

              ┌────────────────────────── cron/cron.php ──────────────────────────┐
              │  every 5 min → dispatcher → provisioning / whatsapp / quota /      │
              │  bandwidth / health / invoices / reminders / ssl / backups /       │
              │  github-update-check / daily-summary   (lock file, per-job log)    │
              └───────────────────────────────────────────────────────────────────┘
```

---

## 2. PROVISIONING FLOW (order → live site)

```
 Client                AK Cloud                 provisioning_queue        aaPanel API
   │                      │                            │                       │
   │ 1. buy plan ─────────►                            │                       │
   │                      │ 2. create order+invoice    │                       │
   │ 3. pay (Razorpay) ───►                            │                       │
   │                      │ 4. webhook verify sig      │                       │
   │                      │ 5. invoice=paid ──────────►│ enqueue action=create │
   │                      │                            │                       │
   │                 [cron: provisioning_worker every 5 min]                   │
   │                      │ 6. reserve job ───────────►│                       │
   │                      │ 7. AddSite ───────────────────────────────────────►│  create site+ftp+db
   │                      │ 8. AddDatabase / AddUser (if not bundled) ─────────►│
   │                      │ 9. apply_cert_api (SSL) ──────────────────────────►│  Let's Encrypt
   │                      │ 10. IsolationService: php-fpm pool + linux user     │  (shell_exec)
   │                      │ 11. QuotaService: set disk limit                    │
   │                      │ 12. save service_details (enc passwords)            │
   │                      │ 13. service=active                                  │
   │ 14. WhatsApp + Email ◄─ hosting_ready template (all creds)                 │
   │                      │                                                     │
   │            ┌─────────┴──────────┐                                          │
   │            │  ANY step fails?   │                                          │
   │            │  → 3 retries (exp) │                                          │
   │            │  → ROLLBACK: Delete created site/db/ftp                       │
   │            │  → admin WhatsApp + manual "Retry" button                     │
   │            └────────────────────┘                                          │
```

---

## 3. FULL FILE TREE

```
/
├── index.php                          # front controller (bootstraps Core, routes)
├── .htaccess                          # Apache fallback + protect dirs
├── nginx.conf.snippet                 # paste into aaPanel site config
├── version.json                       # current version + build
├── composer.json                      # optional (autoload only)
├── .env.example                       # copy → .env in installer
├── .gitignore
│
├── install/
│   ├── index.php                      # installer entry (10 steps)
│   ├── steps/
│   │   ├── 1_welcome.php
│   │   ├── 2_requirements.php
│   │   ├── 3_database.php
│   │   ├── 4_schema.php
│   │   ├── 5_admin.php
│   │   ├── 6_site.php
│   │   ├── 7_aapanel.php
│   │   ├── 8_whatsapp.php
│   │   ├── 9_cron.php
│   │   └── 10_finish.php
│   └── sql/
│       └── schema.sql                 # ✅ DONE (64 tables + seed)
│
├── app/
│   ├── Core/
│   │   ├── App.php  Router.php  Request.php  Response.php
│   │   ├── Database.php  QueryBuilder.php  Model.php
│   │   ├── Auth.php  Session.php  Validator.php
│   │   ├── Cache.php  Logger.php  Mail.php  Crypt.php  Event.php  View.php
│   ├── Controllers/
│   │   ├── Admin/    (Dashboard, Clients, Services, Servers, Products,
│   │   │             Invoices, Tickets, Settings, Updates, Reports ...)
│   │   ├── Reseller/ (Dashboard, Clients, Pricing, Wallet ...)
│   │   ├── Client/   (Dashboard, Services, FileManager, Database,
│   │   │             Invoices, Tickets, Domains, Profile ...)
│   │   └── Api/      (v1 endpoints)
│   ├── Models/                        # Tenant, User, Client, Service, Invoice ...
│   ├── Services/
│   │   ├── AaPanelService.php         # ★ Module 5
│   │   ├── ProvisioningService.php
│   │   ├── WhatsAppService.php
│   │   ├── BillingService.php
│   │   ├── QuotaService.php
│   │   ├── BandwidthService.php
│   │   ├── IsolationService.php
│   │   ├── BackupService.php
│   │   └── UpdateService.php
│   ├── Middleware/                    # AuthMiddleware, TenantMiddleware, CsrfMiddleware ...
│   ├── Helpers/                       # functions.php, format.php, gst.php
│   └── Views/{layouts,admin,reseller,client,emails}/
│
├── public/
│   ├── assets/{css,js,img}            # Bootstrap 5.3, Alpine, Chart.js (all local)
│   └── uploads/                       # NEVER overwritten by updater
│
├── storage/
│   ├── logs/  cache/  backups/  temp/  invoices/     # NEVER overwritten
│
├── config/
│   └── config.php                     # generated by installer, NEVER overwritten
├── .env                               # generated by installer, NEVER overwritten
│
├── database/migrations/               # 2026_xx_xx_xxxxxx_name.sql (tracked in `migrations`)
├── cron/
│   ├── cron.php                       # single entry, dispatches jobs
│   └── jobs/                          # one file per job slug
├── lang/{gu,hi,en}.php
├── vendor-local/                      # bundled third-party libs (no CDN)
└── custom/                            # user overrides, NEVER overwritten
```

---

## 4. DATABASE — 64 TABLES (see `install/sql/schema.sql`)

| Domain | Tables |
|---|---|
| Multi-tenant | tenants, tenant_subscriptions |
| Auth / RBAC | users, roles, permissions, role_permissions, user_sessions, login_logs, audit_logs |
| Clients | clients, client_contacts |
| Servers (aaPanel) | servers, server_health_logs, aapanel_logs |
| Products | product_groups, products, product_pricing |
| Orders / Services | orders, order_items, services, service_details, provisioning_queue, provisioning_logs |
| Quota / Bandwidth | disk_usage, bandwidth_usage, quota_warnings |
| Domains | domains, domain_dns |
| Billing | invoices, invoice_items, transactions, credits, coupons, coupon_usage, payment_gateways |
| Support | ticket_departments, tickets, ticket_replies, ticket_attachments |
| Reseller / Wallet / Affiliate | resellers, reseller_pricing, wallets, wallet_transactions, affiliates, affiliate_commissions |
| Email | email_templates, email_queue, email_logs |
| WhatsApp | whatsapp_templates, whatsapp_queue, whatsapp_logs, whatsapp_campaigns |
| Content | announcements, knowledgebase, kb_categories |
| System | settings, cron_jobs, cron_logs, backups, migrations, update_history, api_keys, api_logs, notifications |

**Every table**: `id`, `tenant_id` (where scoped), `created_at`, `updated_at`, `utf8mb4_unicode_ci`,
InnoDB, indexed FKs. Seed data included for tenant #1 (AK Cloud), roles, all 23 permissions,
the 4 VPS plans + pricing, 8 payment gateway drivers, 16 cron jobs, 22 Gujarati WhatsApp
templates, 4 email templates, and all default settings.

---

## 5. MODULE BUILD ORDER & FILE COUNTS

Build order chosen so every module only depends on earlier ones. Counts are estimates.

| # | Module | Depends on | Est. files |
|---|--------|-----------|-----------:|
| **0** | **STEP 0 — schema + architecture** *(this deliverable)* | — | 2 ✅ |
| 2 | Core Framework (Router, DB, Auth, Crypt, View …) | 0 | ~16 |
| 1 | Installer (`/install`, 10 steps) | 2 | ~14 |
| 3 | Auth & Security (login, 2FA, OTP, audit) | 2 | ~10 |
| 21 | Settings (DB-driven, cached) | 2,3 | ~8 |
| **5** | **★ aaPanel API integration** | 2,21 | ~6 |
| 9 | WhatsApp integration (queue, templates) | 2,21 | ~5 |
| 6 | Products / Plans | 2,3 | ~8 |
| 7 | Order & Provisioning (queue worker, rollback) | 5,6,9 | ~10 |
| 8 | Billing & Payments (GST, gateways, invoices) | 6,7 | ~18 |
| 10 | Client Area (file manager, DB, usage) | 5,7,8 | ~16 |
| 17 | Cron (single entry + 16 jobs) | all services | ~18 |
| 18 | GitHub Auto-Updater (backup, migrate, rollback) | 2,21 | ~6 |
| 15 | Admin Panel (dashboard, CRUD, reports) | all | ~30 |
| 12 | Support Tickets | 3,9 | ~8 |
| 4 | Multi-Tenant SaaS engine (scope, plans, suspend) | 2,3 | ~8 |
| 11 | Domain management (registrar drivers, DNS) | 6 | ~10 |
| 13 | Reseller & White-label | 4,6,8 | ~10 |
| 14 | Affiliate | 8 | ~5 |
| 16 | Reports (revenue, MRR, GST CSV) | 8 | ~8 |
| 19 | Backup & Restore (local, GDrive, S3, FTP) | 5 | ~8 |
| 20 | REST API (tokens, scopes) | all | ~10 |

**aaPanel gaps that AK Cloud solves itself** (Module 5 c/d):
1. **Disk quota** — `QuotaService` via `du -sb` + `information_schema` (aaPanel has none)
2. **Bandwidth** — `BandwidthService` parses nginx access logs incrementally
3. **User isolation** — `IsolationService` = per-site php-fpm pool + Linux user + disabled_functions
4. **Client access** — built-in File Manager + Adminer proxy (customer never logs into aaPanel)

---

## 6. WHAT'S DONE IN STEP 0

- ✅ `docs/STEP0-architecture.md` — this file (diagrams, tree, build order)
- ✅ `install/sql/schema.sql` — 64 tables + indexes + FKs + seed data
- ✅ Repository skeleton (all folders from the tree above)
- ✅ `version.json`, `.env.example`, `config/config.sample.php`, `.gitignore`, `README.md`

**Next:** request `MODULE 2 નો કોડ આપ` (Core Framework) to begin the code build.
