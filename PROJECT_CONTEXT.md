# HearMed Portal - Master Project Reference Document
**Version 4.0 — February 2026**
**Single source of truth. Read this before touching any code.**

---

## SECTION 1: THE BUSINESS & WHAT THIS IS

### The Business
HearMed Acoustic Health Care Ltd — Irish audiology practice, 4 clinics (Tullamore, Portlaoise, Newbridge, Portumna), 10–15 staff, ~5,000 patients.

Services: hearing tests, hearing aid fitting/sales, wax removal, repairs, returns, patient management.

Staff roles: C-Level (owner/director), Admin, Finance, Dispensers/Audiologists, Clinical Assistants, Reception, Scheme workers.

### Why Custom Software Was Built
No off-the-shelf audiology PMS handles:
- Irish PRSI/HSE grant structure (€500 per ear, max €1,000 binaural)
- Irish-specific commission structures with monthly cut-off dates
- QuickBooks Online integration the way the practice needs it
- Direct Power BI reporting from the data layer
- GDPR compliance at Irish healthcare regulation level
- Audiology-specific appointment, outcome, and invoice workflow

### What HearMed Portal Is
A complete Electronic Health Record (EHR) + Practice Management System that handles:
- Patient records, demographics, clinical history
- Appointment scheduling and outcomes
- Hearing aid orders, invoicing, and payment
- Staff commissions, KPI tracking, and reporting
- GDPR consent, audit logging, and data management
- QuickBooks Online sync for accounting
- Team communications and internal notifications
- Data import from legacy system with full historical records

---

## SECTION 2: CURRENT ACTUAL STATE — NOTHING IS FUNCTIONAL YET

### This must be stated clearly:
**As of today (February 2026), the portal is NOT functional on the live server.**

### Why It's Not Working

**Problem 1: Syntax errors blocking PHP**
The codebase went through automated conversion from WordPress/MySQL to PostgreSQL. This left broken artifacts throughout:
- Escaped quote artifacts (`\'` instead of `'`)
- Orphaned array fragments (leftover `'posts_per_page' => -1` in PostgreSQL code)
- **Unclosed comment patterns** — converter wrote `/* USE PostgreSQL: comment */ /* get_post_meta(...)` which left unclosed comment blocks causing `Parse error: syntax error`
- Bracket mismatches (extra `)` from old `$wpdb->prepare()` patterns)
- Undefined `$wpdb` (global removed but still referenced)
- Duplicate function declarations (e.g., `HearMed_Core::table()` declared twice)
- MySQL syntax in PostgreSQL queries (`DATE_SUB()`, `JSON_SEARCH()`, backticks, `SHOW TABLES LIKE`, `LIKE %s` placeholders)
- WordPress functions still scattered (`get_post_meta()`, `wp_insert_post()`, `get_posts()`)
- JetEngine CCT fields (`cct_status => 'publish'`)
- Wrong load order (class loading before dependencies)

**Result:** PHP refuses to parse files at all. Plugin crashes on activation. White screen or fatal error. Staff cannot log in. Nothing works.

**Problem 2: PostgreSQL is a shell with no data**
Railway PostgreSQL has 59 tables created with correct schema — but **zero rows of business data** in any of them.

No patient data, no clinic data, no staff data, no products, no services. Database is structurally complete but entirely empty.

**Problem 3: No data migration has run**
Old data still lives in the legacy system (WordPress CPTs and wp_postmeta). It has not been migrated to PostgreSQL yet.

**Problem 4: WordPress may block things**
WordPress.com Business has restrictions on certain PHP functions, external database connections, and file system operations. The `pg_connect()` call may require specific configuration or whitelisting.

### What HAS Actually Been Accomplished
- ✅ 59 PostgreSQL tables designed, created, live on Railway — correct schema, indexes, foreign keys, helper functions
- ✅ 37 PHP files cleaned — all syntax errors found and fixed, all MySQL patterns replaced, all WordPress functions replaced, all broken comments removed
- ✅ Fixed plugin code is deployed and no longer crashes on activation
- ✅ Admin console and Clinics page are functional enough to create/edit clinics
- ✅ Staff auth groundwork added (custom PostgreSQL auth + optional TOTP 2FA)
- ✅ Dispenser Schedules admin page added (clinic/day + weekly or 2-week rotation)
- ✅ Calendar assignees now respect schedule rules (per clinic/date)

### What Must Happen Next (In Order)
1. Deploy fixed plugin files to live server via SFTP
2. Confirm `pg_connect()` to Railway works from WordPress.com
3. Seed reference data (clinics, staff, products, services, appointment types)
4. **Build and run patient data migration** (this is CRITICAL)
5. Test each shortcode renders correctly
6. Build remaining modules

---

## SECTION 3: ARCHITECTURE — NON-NEGOTIABLE

### Core Principle
**WordPress = UI shell + authentication ONLY**
**PostgreSQL = ALL business data**

WordPress's database (wp_users, wp_usermeta) used only for login/session management. Every patient, appointment, order, invoice, staff record, commission, KPI, notification, and piece of clinical data lives in PostgreSQL on Railway.

**Zero business data in wp_posts, wp_postmeta, or any WordPress table.**

### The Tech Stack
| Layer | Technology | Role | Hosting |
|-------|-----------|------|---------|
| UI Shell | WordPress.com Business | Page routing, chrome | Page builder |
| Page Layout | Elementor Pro | Dynamic content | Theme |
| Core Plugin | hearmed-calendar | All business logic, shortcodes, AJAX | /srv/htdocs/wp-content/plugins/ |
| Database | PostgreSQL on Railway | All 59 tables of business data | Railway |
| Accounting | QuickBooks Online | Accounting mirror | QBO API |
| Automation | Make.com | QBO webhooks, AI features | Make automation platform |
| AI | OpenRouter (via Make) | AI transcript of consultations | OpenRouter API |
| Reporting | Power BI | Direct PostgreSQL connection | Power BI Cloud |
| Deployment | SFTP/WinSCP | Manual sync to server | /srv/htdocs/wp-content/plugins/hearmed-calendar/ |

### Plugin Structure
```
hearmed-calendar/
├── hearmed-calendar.php          ← Plugin entry point, bootstrap
├── core/
│   ├── class-hearmed-db.php               ← PostgreSQL abstraction layer (6 methods, parameterised queries)
│   ├── class-hearmed-pg.php               ← Alias file only (HearMed_PG → HearMed_DB)
│   ├── class-hearmed-core.php             ← Singleton bootstrap, loads everything
│   ├── class-hearmed-auth.php             ← Role checks, clinic scoping, multi-tenant logic
│   ├── class-hearmed-enqueue.php          ← Asset loading, conditional per-module
│   ├── class-hearmed-router.php           ← Shortcode registration & routing
│   ├── class-hearmed-ajax.php             ← Central AJAX dispatcher
│   └── class-hearmed-utils.php            ← Formatting, money, dates, phone, Irish formats
├── admin/                        ← 12 WordPress admin pages (clinics, users, products, KPI, SMS, audit, settings, debug, console, etc.)
├── modules/                      ← 12 portal modules (shortcodes: patients, calendar, orders, approvals, notifications, repairs, chat, accounting, reports, commissions, KPI, cash)
├── includes/
│   ├── class-hearmed-roles.php  ← Role capabilities mapping
│   ├── class-cpts.php           ← Custom post types (nav only, no data)
│   └── ajax-handlers.php        ← AJAX response handlers
├── templates/
│   └── privacy-notice.php       ← GDPR staff notice gate on first login
└── assets/
    ├── css/                     ← Per-module CSS files (hearmed-{module}.css)
    └── js/                      ← Per-module JavaScript
```

---

## SECTION 4: CSS & FRONTEND — LOCKED RULES

**These rules are permanent. Never break them regardless of context.**

### What Must NOT Change
Main Elementor page templates and all global/theme CSS are **FROZEN**. Nothing is to be modified in Elementor's page-level styling, the WordPress theme, or any template-level CSS.

**Only permitted global change:** The overall page container has `max-width: 100vw` applied to prevent horizontal scroll on portal pages with wide data tables.

### Where ALL Styling Must Live
Every visual change, layout, colour, spacing must be scoped exclusively inside:
- `#hm-app` — root div that every portal shortcode outputs as outermost wrapper
- `.hm-content` — inner content wrapper used by some modules

All module CSS written as:
- `<style>` blocks inside the PHP shortcode output (scoped with `#hm-app` as root), OR
- Files in `assets/css/hearmed-{module}.css` loaded only when that module's shortcode is on page

**Nothing in any module CSS leaks out to affect Elementor's chrome, sidebar, header, or any other page element.**

### HearMed Brand Constants
- Primary navy: `#151B33`
- Teal accent: `#0BB4C4`
- Font: Sahitya (headings), system sans (body)
- CSS prefix: `hm-` (all portal classes use this)

---

## SECTION 5: DATABASE SCHEMA — 59 TABLES, ALL CURRENTLY EMPTY

All tables exist on Railway PostgreSQL. All are currently empty with zero business data rows.

### hearmed_reference — Lookup & Configuration Data
| Table | Purpose |
|-------|---------|
| `clinics` | The 4 HearMed clinic locations (name, address, phone, colour) |
| `staff` | All staff members with WP user ID link |
| `staff_clinics` | Which staff work at which clinics (many-to-many, with primary flag) |
| `staff_qualifications` | Professional qualifications per staff member |
| `appointment_types` | Types of appointment with colour, duration, category, outcomes |
| `services` | Services offered (wax removal, hearing test, etc.) |
| `service_prerequisites` | Prerequisites between service types |
| `products` | Hearing aids and accessories (cost/retail, manufacturer, range) |
| `product_specifications` | Technical specs per product |
| `manufacturers` | Hearing aid manufacturers |
| `payment_methods` | Cash, Card, Bank Transfer, Cheque, PRSI, Finance, Voucher — with QBO account mapping |
| `referral_sources` | Referral sources with parent_id for sub-sources |
| `speakers` | Speaker/receiver inventory |
| `domes` | Dome inventory |
| `coupled_items` | Paired product configurations |
| `inventory_stock` | Stock levels per product per clinic |
| `stock_movements` | Inventory movement audit trail |

### hearmed_core — Operational Data
| Table | Purpose |
|-------|---------|
| `patients` | **THE foundational table** — all demographics, contact, PRSI, GDPR consent, marketing prefs, annual review date, C-number |
| `appointments` | All appointments — date, time, type, status, clinic, dispenser, patient |
| `appointment_outcomes` | Outcomes recorded for each appointment |
| `outcome_templates` | Configurable outcome types per appointment type |
| `calendar_settings` | Global calendar configuration |
| `calendar_blockouts` | Holiday and unavailability blocks |
| `staff_absences` | Formal absence records |
| `orders` | Hearing aid orders — status tracked from creation to fitting |
| `order_items` | Line items within orders |
| `order_shipments` | Shipping/delivery records |
| `order_status_history` | Full audit trail of status changes |
| `invoices` | All invoices (linked to orders and/or appointments) |
| `invoice_items` | Line items within invoices |
| `payments` | Payments received against invoices |
| `credit_notes` | Returns and refunds — with cheque_sent tracking |
| `financial_transactions` | Double-entry ledger records |
| `fitting_queue` | Patients awaiting fitting after aids received |
| `repairs` | Hearing aid repairs — sent/received dates, warranty status |
| `patient_devices` | Hearing aids fitted to each patient (serial numbers, status) |
| `patient_notes` | Clinical and administrative notes per patient |
| `patient_forms` | Jotform submissions — consent, case history, GDPR, marketing prefs |
| `patient_documents` | Files and documents attached to patient records |
| `cash_transactions` | Individual cash in/out entries per clinic |
| `till_reconciliations` | Daily till open/close reconciliation |

### hearmed_communication — Messaging & Notifications
| Table | Purpose |
|-------|---------|
| `internal_notifications` | Notification records (subject, message, type, priority, entity link) |
| `notification_recipients` | Per-user read/dismiss/flag state |
| `notification_actions` | Clickable action buttons on notifications |
| `notifications` | Patient-facing scheduled notifications (SMS, email) |
| `sms_messages` | SMS message log |
| `sms_templates` | Configurable SMS templates per appointment type |
| `chat_channels` | Company-wide channel + DM channels (channel_type: 'company' or 'dm') |
| `chat_channel_members` | Who is in each channel, last_read_at for unread count |
| `chat_messages` | Individual chat messages (soft delete only — never hard delete) |

### hearmed_admin — Administration & Reporting
| Table | Purpose |
|-------|---------|
| `audit_log` | Complete audit trail of all portal activity (who, what, when, IP) |
| `commission_periods` | Monthly commission periods with is_finalised flag |
| `commission_rules` | Tier structure (seeded with rates: 0%/8%/10%/20%) |
| `commission_entries` | Per-sale commission calculation |
| `kpi_targets` | Target values per KPI metric |
| `gdpr_deletions` | GDPR right-to-erasure requests |
| `gdpr_exports` | GDPR right-of-access requests |

### PostgreSQL Helper Functions (Live in DB)
```sql
hearmed_next_patient_number()          -- Returns next C-XXXX, atomically, never duplicates
hearmed_commission_cutoff(year, month) -- Returns nearest weekday to the 23rd
                                       -- Sat → Fri, Sun → Mon, Weekday → that day
```

### Key Relationships
```
Clinic (1) ──── (many) Dispensers
Clinic (1) ──── (many) Appointments
Dispenser (1) ──── (many) Appointments
Service (1) ──── (many) Appointments
Patient (1) ──── (many) Appointments
Patient (1) ──── (many) Orders
Dispenser (1) ──── (many) Orders
Order (1) ──── (many) Order Line Items
Product (1) ──── (many) Order Line Items
```

---

## SECTION 6: COMMISSION STRUCTURE — EXACT BUSINESS RULES

### Commission Cut-Off Date
Using PostgreSQL function `hearmed_commission_cutoff(year, month)`:
- If 23rd is Mon–Fri → use 23rd
- If 23rd is Saturday → use Friday 22nd
- If 23rd is Sunday → use Monday 24th
- New period starts next working day after cut-off

### Dispenser/Audiologist Commission (Tiered on Hearing Aid Sales Only)
| Revenue Bracket | Rate |
|-----------------|------|
| €0 – €4,000 | 0% |
| €4,001 – €18,000 | 8% |
| €18,001 – €35,000 | 10% |
| Above €35,000 | 20% |

Only hearing aid sales count. Accessories, services, wax removal do NOT contribute.

**Return deductions:** Returns within a commission period = flat 10% of hearing aid sale price (not of commission earned).

### CA (Clinical Assistant) & Reception Commission
- 1% of all hearing aid sales made in their primary clinic in the same cut-off period
- NOT on their own sales — on whole clinic's sales
- Same start/end dates as dispenser periods

### Scheme Role
- Zero commission
- Zero access to commission or reports pages
- Reports and commission sidebar entries hidden entirely
- No visible gap left — CSS handles the hide cleanly

### Commission Reports & PDF Statements
- Printable PDF per dispenser each month
- Includes: HearMed logo, dispenser name, period dates, C-numbers with sale amounts, commission per tier, total earned
- Second section: projected commission if all awaiting-fitting patients are fitted
- Professional typography, ready to accompany payslip
- CA/Reception version shows clinic-wide HA sales with 1% flat rate

---

## SECTION 7: GDPR COMPLIANCE — FULL REQUIREMENTS

### First Login Gate (PERMANENT)
On every staff member's first login (or if `privacy_accepted` WP user meta is not set or is older than policy version), the router intercepts every shortcode and returns `templates/privacy-notice.php` instead.

Notice explains:
- What Special Category personal data they will access
- Their obligations as data handlers
- Lawful basis: Article 9(2)(h) GDPR — healthcare provision
- Retention: patient records 8 years after last contact, portal audit logs 3 years

Staff must click Accept → `wp_ajax_hm_acknowledge_privacy_notice` records acceptance date, IP, policy version. Until done, no portal content shown.

### Patient Data Access Controls
- Dispensers/Reception: see only patients assigned to their primary clinic
- Finance/C-Level/Admin: see all patients across all clinics
- Every read of patient record written to `hearmed_admin.audit_log` — user ID, action, entity type, entity ID, IP, timestamp
- Audit log is append-only — no portal UI can edit/delete entries

### Printing & Exporting
- Every print action triggers GDPR notice banner beforehand: "This document contains personal data. Handle securely."
- All exports (CSV, Excel, PDF) logged in audit_log with user, timestamp, data type, filter parameters
- Role restrictions:
  - Dispensers: export only their own patients
  - Finance/C-Level: export across all clinics
  - Scheme: no export access

### Patient Anonymisation
- Available to C-Level and Admin only
- Permanently replaces all identifiable fields (first_name, last_name, DOB, phone, mobile, email, address, eircode, PRSI, medical card) with `[ANONYMISED]`
- Clinical records (appointments, outcomes, invoices) preserved for regulatory purposes
- Logged in `hearmed_admin.gdpr_deletions`

### Consent Forms
- Jotform embedded in patient file Forms tab
- On submission, webhook fires to WordPress endpoint which writes to `hearmed_core.patient_forms`
- GDPR consent, marketing_email, marketing_sms, marketing_phone auto-synced to patient record
- Every change to marketing preferences timestamped and audit-logged

### Team Chat GDPR
- Chat is NOT using Frontend PM WordPress plugin (would store data in wp_posts — unencrypted, unaudited)
- Chat is built natively in `hearmed_communication.chat_messages`
- Messages are soft-deleted ONLY (`is_deleted = true`) — never physically removed
- C-Level can review all message history for compliance

### Data Residency
- All patient data resides on Railway PostgreSQL (EU region)
- No patient data in WordPress's MySQL
- No Special Category clinical data passes through Make.com — only financial reference data (invoice amounts, payment totals) for QBO sync

---

## SECTION 8: DATA IMPORT — FULL SPECIFICATION

### Why Critical
~5,000 existing patients in legacy system. PostgreSQL currently empty. Until migration runs, portal cannot be used. **This is priority #1 after deployment.**

### Import Tool Location
Admin console → Data Import section. C-Level and Admin access only.

### Scenario 1: Patient & Clinical Data (CSV/Excel Upload)
**What it imports:**
- Patient demographics to `hearmed_core.patients` (all fields including PRSI, marketing prefs)
- Patient notes to `hearmed_core.patient_notes` (note_type = 'imported', original date preserved)
- Invoices to `hearmed_core.invoices + invoice_items` (status = 'paid', source = 'legacy_import')
- Patient devices to `hearmed_core.patient_devices` (hearing aids, serial numbers, fitting dates)
- Documents to `hearmed_core.patient_documents` (PDF links)

**Process:**
1. Admin uploads CSV or Excel
2. System runs validation:
   - Missing required fields (first_name, last_name mandatory)
   - Duplicate patient numbers
   - Unrecognised clinic names (must match hearmed_reference.clinics)
   - Unrecognised dispenser names (must match hearmed_reference.staff)
   - Invalid dates, phone formats
3. Validation preview shows: clean rows / rows with errors / what each error is
4. Admin can proceed (skipping error rows) or fix CSV and re-upload
5. Import runs in batches of 50 (prevents PHP timeout)
6. Progress bar shows batch completion
7. On completion: full report — rows imported, rows skipped, errors per row, time taken

**Patient Number Handling:**
- If source CSV has C-number and no conflict → preserve it
- If conflict exists → assign new C-number using `hearmed_next_patient_number()`
- All imported patients get `source = 'legacy_import'` flag

### Scenario 2: Legacy Appointment History
Historical appointments are not editable but must be visible for full patient history.

**How stored:**
- Imported to `hearmed_core.appointments` with `status = 'legacy'`
- Outcome matched to `hearmed_core.outcome_templates` by name (case-insensitive)
- If no match → new outcome template created as `[Legacy] {original outcome name}` with `source = 'legacy_import'`
- Original outcome text stored in `legacy_notes` field

**How they appear in portal:**
- Patient's Appointments tab shows with "Legacy Record" badge
- Tooltip: "This record was imported from the previous system. It cannot be edited."
- Read-only — no edit, reschedule, or delete buttons
- Appear in historical report counts, clearly marked as legacy in exports

**What legacy appointments do NOT trigger:**
- No notifications
- No annual review date calculation
- No GDPR reminders
- No commission entries
- No awaiting fitting or fitting queue entries

**Import file format for appointments:**
```
patient_number | appointment_date | appointment_time | appointment_type | 
outcome | notes | dispenser_name | clinic_name
```
- appointment_type matched by name to `hearmed_reference.appointment_types`
- Unmatched types created as `[Legacy] {name}` entries
- dispenser_name matched by full name to `hearmed_reference.staff`
- Unmatched dispensers assigned to placeholder, flagged for manual review

---

## SECTION 9: OTHER CRITICAL BUSINESS LOGIC

### Duplicate Detection (During Order Creation)
When creating order for patient with invoice containing same product within last 90 days:
- Order is flagged (`flagged = true`, `flag_reason` populated)
- Order still proceeds — NOT blocked
- Approvals page shows flag prominently
- Check uses: `line_items::text ILIKE '%{product_name}%' AND created_at > NOW() - INTERVAL '90 days'`

### Order Lifecycle
```
Create Order
    ↓
Awaiting Approval  [notification to C-Level / Finance]
    ↓
Approved           [notification to Finance to place order]
    ↓
Ordered            [Finance marks as ordered with supplier]
    ↓
Received in Branch [who received, what date — logged]
    ↓
Awaiting Fitting   [patient on fitting queue]
    ↓
Fitting appointment booked [fitting date appears on queue]
    ↓
Invoice paid + appointment closed as "Fitted"
    ↓
Cleared from fitting queue, marked as prescribed+fitted in reports
    ↓
Commission entry calculated for period
```
If patient in fitting queue has fitting appointment deleted → ask dispenser: "Cancel pre-fit?" If yes, removed from queue and added to cancellation report.

### Credit Note / Returns / Cheque Flow
1. Credit note created in portal (returns tab or from invoice)
2. Portal saves to `hearmed_core.credit_notes`
3. Make.com webhook fires → creates credit note in QBO → QBO returns credit note ID
4. QBO ID stored back on portal record
5. `cheque_sent` defaults to false
6. Staff logs cheque sent date manually when sending refund
7. If `cheque_sent` still false after X days (configurable):
   - Auto-notification fires to C-Level + Finance
   - Repeats daily until resolved
   - Notification includes patient name, C-number, amount, credit note #
8. Once `cheque_sent = true` set → all reminders stop permanently

### QuickBooks Online Integration
Portal is source of truth. QBO is mirror.

**Outbound (portal → QBO via Make.com):**
- New invoice → Make webhook → QBO invoice created → QBO ID stored
- Payment recorded → Make → QBO payment applied
- Credit note → Make → QBO credit note created → QBO ID stored back

**Inbound (QBO → portal via Make.com):**
- Invoice marked paid in QBO → Make webhook → portal invoice status updated

**Direct QBO API (portal → QBO, no Make.com):**
- Accounting page uses `HearMed_QBO` PHP class with OAuth2
- Calls QBO Reporting API directly
- Used for: P&L report, aged debtors, VAT summary
- OAuth2 tokens stored in wp_options, auto-refreshed
- Never requires opening QBO manually

### Forms — Jotform Integration
- Embedded in patient file Forms tab via `<iframe>`
- Used for: initial questionnaire, GDPR consent, case history
- On submission, webhook fires to WordPress endpoint (registered by plugin)
- Endpoint writes to `hearmed_core.patient_forms` — form type, form data (JSON), signature image URL, GDPR consent, marketing prefs
- Marketing preferences from form auto-sync to patient's record
- Every form submission audit-logged

### Annual Review Automations
- When appointment closed with outcome other than "Normal Hearing" → `annual_review_date` on patient set to 12 months from appointment date
- When `annual_review_date` passes without new hearing test appointment → auto-notification fires to assigned dispenser
- **Only for native appointments — legacy imported appointments do NOT trigger**

### Team Chat
- Company-wide channel pre-seeded as `channel_type = 'company'`
- DM channels created on demand as `channel_type = 'dm'` with both users in `chat_channel_members`
- Messages soft-deleted (`is_deleted = true, deleted_at` set) — never physically removed
- C-Level can view all messages including soft-deleted
- Unread count tracked via `last_read_at` in `chat_channel_members`
- No third-party plugin — all native PostgreSQL

---

## SECTION 10: PRSI / HSE GRANT
- €500 per hearing aid ear
- Maximum €1,000 binaural (both ears)
- Applied as deduction at invoice creation
- Stored on order record (`prsi_applicable`, `prsi_amount`)
- Pushed to QBO as line item deduction

---

## SECTION 11: NOTIFICATIONS SYSTEM

All notifications use two tables:
- `hearmed_communication.internal_notifications` — the notification itself
- `hearmed_communication.notification_recipients` — per-user state (read, dismissed, flagged)

### Auto-fired Notifications
| Trigger | Type | Recipients | Notes |
|---------|------|------------|-------|
| Order created | approval_needed | C-Level + Finance | Includes patient, GP margin, product details |
| Order approved | order_status | Finance (Diana) | Prompts to place order with supplier |
| Order ordered | order_status | Assigned dispenser | Confirms order placed |
| Order received | order_status | Assigned dispenser | Aids in branch |
| Fitting date expired | fitting_overdue | Assigned dispenser | Fires when fitting date passes with no invoice close |
| Cheque not sent within X days | cheque_reminder | C-Level + Finance | Fires daily until resolved |
| Repair returned | repair_update | Assigned dispenser | Confirms repair back |
| Annual review overdue | annual_review | Assigned dispenser | Patient overdue for review |
| New chat message | message | Recipient(s) | DMs only, not company channel |

### Manual Notifications by Staff
- Phone call reminder (date/time + patient link; fires at that time)
- Follow up reminder (same structure)
- Custom (free-text subject + message, date/time, target recipient)

### Bell Icon in Portal Header
- Shows unread count
- Updates via polling every 60 seconds
- Click opens notifications page

### Notification Page Features
- Filter by type, priority (🔴 High / 🟡 Normal / 🟢 Low), unread only
- Flag dots on each notification
- Mark read, mark actioned (cleared but not deleted)
- Mark all read, clear all actioned
- Action buttons link to relevant page or fire AJAX action

---

## SECTION 12: BUILD PRIORITY & DELIVERY ORDER

**FOUNDATION PRINCIPLE:** Build feature-complete, fully-functional sections in priority order. Each section must be 100% working, save to database correctly, look good per design system, and deploy automatically.

### Actual Build Queue (User-Specified Priority Order)

**Phase 1: ADMIN FOUNDATION** 🔴 CRITICAL
| # | Admin Page | Status | DB Source | Notes |
|---|-----------|--------|-----------|-------|
| 1️⃣ | **Clinics** | ✅ Complete | PostgreSQL | Fully working, CRUD functional |
| 1️⃣ | **Users (Staff)** | ✅ Complete | PostgreSQL | Fully working, clinic assignments, roles |
| 1️⃣ | **Audiometers** | ✅ Complete | PostgreSQL | Fully working, inventory tracking |
| 2️⃣ | **Calendar Settings** | 🔧 In Progress | PostgreSQL | Needs save fix (now complete), color pickers done, need final tweaks |
| 2️⃣ | **All Remaining Admin Pages** | 🚧 Complete Structure Only | PostgreSQL | Must be fully functional + styled |

**Phase 2: PORTAL SECTIONS** (In this strict order)
| # | Module | Status | Dependencies | Notes |
|---|--------|--------|--------------|-------|
| 1️⃣ | **mod-patients** (all parts) | ✅ Partial | Admin complete | All tabs: profile, history, outcomes, devices, notes, forms, documents. Must search PostgreSQL correctly |
| 2️⃣ | **mod-calendar** (appointments) | ✅ Partial | Patients complete | Book appointments, connect to patients, add notes. ✅ Search fix: now uses PostgreSQL |
| 3️⃣ | **Invoicing (QuickBooks)** | 🚧 In Build | Calendar complete | Full integration with QBO. Create invoice → await approval → send to QBO |
| 4️⃣ | **Order Flow (CRITICAL)** | 🚧 Spec Complete | Invoicing | See detailed spec below |
| 5️⃣ | **mod-team-chat** | 🚧 Scaffold | Patients complete | In-house messaging, soft-delete only, audit trail |
| 6️⃣ | **mod-reports** | 🚧 Scaffold | Invoicing + chat | Patient history, sales, commissions, accounting reports |
| 7️⃣ | **In-House Notifications** | 🚧 Scaffold | Orders + chat | Pop-up badge in top-right; "Order received", "Aids ready", "Call patient" |
| 8️⃣ | **KPI + Till Tracking** | 🚧 Scaffold | Appointments complete | Per-staff KPI dashboard + clinic till reconciliation |
| 9️⃣ | **Accounting** | 🚧 Scaffold | Reports complete | Supplier invoices, receipts, staff photo upload, QBO sync |

---

## SECTION 13: ORDER FLOW SPECIFICATION - COMPLETE WORKFLOW

**This is the complete, detailed order flow from creation to clearance. Must be implemented exactly as specified.**

### 1. Order Creation (Dispenser Interface)
<div class="hm-spec">
- Dispenser creates order in mod-orders.php
- Selects patient (search PostgreSQL)
- Selects hearing aids + accessories from products table
- System auto-calculates:
  - Unit price × quantity
  - PRSI deduction (€500/ear, max €1,000 binaural) if applicable
  - GP margin % if prescribed by GP
  - Total invoice value
- Order saved to `hearmed_core.orders` with status = `pending`
- Order items saved to `hearmed_core.order_items`
</div>

### 2. Approval Stage (C-Level Email Notification)
<div class="hm-spec">
- **Auto-trigger:** When order status = `pending`
- **Who receives:** All C-Level user email addresses (from `hearmed_reference.staff` WHERE `role = 'hm_clevel'`)
- **Email contents:**
  - Patient name + C-number
  - Hearing aid model, colour, range
  - Unit price
  - Cost price (internal)
  - **GP margin %** (if applicable)
  - Total invoice amount
  - Link to approval dashboard
- **C-Level action:** Click Approve or Deny in mod-approvals.php
- **If Deny:** Order status = `cancelled`, reason recorded
- **If Approve:** Order status = `approved`, moves to Finance queue
</div>

### 3. Finance Ordering Stage
<div class="hm-spec">
- Order appears in Finance dashboard (Diana's view)
- Finance clicks "Order with Supplier"
- Status changes to `ordered`
- Order date/time logged in `hearmed_core.order_shipments` (order_placed_date)
- **Finance notifies staff somehow** (pop-up in portal, SMS, or notification system — to be decided)
</div>

### 4. Goods Received in Branch
<div class="hm-spec">
- Staff member clicks "Receive Order" in mod-orders.php OR "Awaiting Fitting" section
- System prompts: **"Are these hearing aids? If YES, enter serial numbers."**
- If YES (hearing aids):
  - Dispenser enters serial number for each aid on the invoice
  - System updates `hearmed_core.order_items` with serial numbers
  - If pairs (binaural), prompts for left AND right serial nums
  - Updates proforma invoice with: Model + Color + Range + Price + Serial Number
- Order status = `goods_received`
- Order moves to **Awaiting Fitting** queue with status = `pending_fit`
- Implicit trigger: Notification sent to assigned dispenser: "Aids in branch, ready for fitting"
</div>

### 5. Awaiting Fitting Queue
<div class="hm-spec">
- Patient appears in "Awaiting Fitting" section with:
  - Aid details (model, colour, serial)
  - Proforma invoice (with serial numbers filled in)
  - Button to "Schedule Fitting Appointment"
  - Button to "Mark as Ready" (manual override if fitting to happen at different time)
- Dispenser books fitting appointment via calendar for that patient
- Fitting appointment automatically links to the order
- OR: Dispenser marks "Mark as Ready" → patient stays in queue, can be fitted at any appointment
</div>

### 6. Fitting Complete + Invoice Paid
<div class="hm-spec">
- Appointment closed with outcome (e.g., "Fitted Left + Right")
- Invoice now created/modified with:
  - **How it was paid:** Cash / Card / Bank Transfer / Cheque / PRSI / Finance / Voucher (dropdown, must match payment method in `hearmed_reference.payment_methods`)
  - Amount paid
  - Payment date
  - Invoice status = `paid`
  - **DO NOT send to QuickBooks yet** — only after marked as paid
- Once invoice marked `paid`, order status = `complete`
- Patient removed from "Awaiting Fitting" queue
- Workflow dates + times logged to `hearmed_core.order_status_history`:
  - order_created_date
  - order_approved_date
  - order_ordered_date
  - goods_received_date
  - fitting_scheduled_date
  - fitting_completed_date
  - invoice_paid_date
  - All with timestamps
</div>

### 7. Invoice Sync to QuickBooks
<div class="hm-spec">
- **Trigger:** Invoice marked as `paid` → Send to QuickBooks immediately via Make.com webhook
- **Data sent to QBO:**
  - Customer (patient name + C-number)
  - Line items (hearing aids, accessories, services with serial numbers if applicable)
  - Amount
  - Payment method (maps to QBO account)
  - Payment date
  - Staff member who performed sale (links to QBO staff)
  - Clinic location (for reference)
  - Invoice number (from portal)
- **QBO returns:** Invoice ID (stored in `hearmed_core.invoices.qbo_invoice_id`)
- **Invoice can then be printed:** Full details including staff, clinic, all products with serials, payment method
</div>

### 8. Cancellation / Return Flow
<div class="hm-spec">
- If patient cancels fitting (in Awaiting Fitting queue):
  - Dispenser clicks "Cancel Order"
  - Is asked: "Do you want to create a credit note?"
  - If YES: Credit note created, patient refunded, cheque_sent = false, tracked for follow-up
  - Order status = `cancelled`
  - Removed from Awaiting Fitting queue
- If staff clicks "Remove from Queue" before appointment booked:
  - Order returns to `approved` status (can be reactivated)
- All cancellations logged to `hearmed_core.order_status_history`
</div>

### 9. Database Log for Audit Trail
<div class="hm-spec">
**Every order must have these dates/times recorded in `hearmed_core.order_status_history`:**
- `order_created_at` (auto timestamp)
- `order_approved_at` (when C-Level approved)
- `order_ordered_date` (when Finance ordered from supplier)
- `goods_received_date` (when staff clicked Receive)
- `fitting_scheduled_date` (when appointment booked)
- `fitting_completed_date` (when appointment closed with outcome)
- `invoice_paid_date` (when payment method selected + marked paid)
- `qbo_sync_date` (when sent to QuickBooks)
- `status_changed_at` (every status change logged)
- `changed_by` (user ID who changed status)
- `change_reason` (if applicable — e.g., cancellation reason)

**These allow you to pull full workflow timeline for any order on demand.**
</div>

---

## SECTION 14: ADMIN PAGES STATUS & NEXT FOCUS

### Admin Pages Currently Complete ✅
| Page | Function | DB Source | Status |
|------|----------|-----------|--------|
| Clinics | Full CRUD | PostgreSQL | ✅ Working perfectly |
| Users (Staff) | Full CRUD + clinic assignment | PostgreSQL | ✅ Working perfectly |
| Audiometers | Full inventory | PostgreSQL | ✅ Working perfectly |
| Calendar Settings | Time, view, colours, display | PostgreSQL | ✅ Mostly complete, save verified working |

### Admin Pages Needing Completion
| Page | What's Missing | Priority |
|------|---|----------|
| **All other admin pages** | Styling + DB integration | 🔴 HIGH |
| **Data Import** | Build complete CSV upload tool for ~5,000 patients | 🔴 CRITICAL |

### Search Bar Fix ✅ DONE
- **Issue:** Search bar in elementor topbar was pulling from WordPress CPTs instead of PostgreSQL
- **Fixed:** Calendar search function `hm_ajax_search_patients()` now queries `hearmed_core.patients` directly
- **Result:** All patient searches now use PostgreSQL as source of truth

---

## SECTION 15: CURRENT CODE STATUS

### All Syntax Errors Fixed ✅
- ✅ 37 PHP files syntax-clean
- ✅ All MySQL patterns replaced with PostgreSQL
- ✅ All WordPress CPT dependencies replaced
- ✅ auth.php refactored + static method wrappers added
- ✅ Search functions now use PostgreSQL exclusively

### What's Ready to Deploy 🚀
- ✅ All core/ framework files
- ✅ All completed admin pages (clinics, users, audiometers, settings)
- ✅ All working modules (patients, calendar, orders, approvals)

### Auto-Deployment Active ✅
- ✅ GitHub Actions workflow: `deploy.yml`
- ✅ On every push to `main` branch → auto-deploys to SiteGround
- ✅ No manual SFTP needed — fully automated

---

## SECTION 16: NEXT IMMEDIATE STEPS

**Priority 1: Complete Calendar Settings (save functionality now working)**
- Verify color pickers save to `hearmed_core.calendar_settings`
- Verify colors apply to calendar appointments
- Final tweaks as needed

**Priority 2: Build Remaining Admin Pages**
- Ensure all pages: save to PostgreSQL correctly, style per design system, function end-to-end
- Each page must be 100% complete before moving on

**Priority 3: Implement Order Flow**
- Complete mod-orders.php with all steps above
- Build approval queue UI
- Test full workflow from creation to QuickBooks sync

**All changes auto-deploy to SiteGround on every commit.**

