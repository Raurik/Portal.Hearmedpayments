# HearMed Portal - Master Project Reference Document
**Version 5.4 — February 2026**
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

## SECTION 2: CURRENT STATE — ADMIN FOUNDATION COMPLETE

### Overall Status
**As of 25 February 2026, all admin pages are fully built, styled, and deployed.** The admin foundation is complete. Portal modules (patients, calendar, orders, etc.) are next.

### What IS Working ✅
- ✅ 60+ PostgreSQL tables on Railway — correct schema, indexes, FK relationships, helper functions
- ✅ PostgreSQL connection from SiteGround working via `pg_connect()`
- ✅ Auto-deployment: GitHub Actions → SSH to SiteGround → `git pull origin main` on every push
- ✅ All 25 admin pages fully built with PostgreSQL CRUD, AJAX, styled per design system
- ✅ Admin console landing page with card grid linking to all sub-pages
- ✅ Staff auth system (`class-hearmed-staff-auth.php`) with PostgreSQL-backed credentials + optional TOTP 2FA
- ✅ Role-based access control (`class-hearmed-auth.php`) with `current_role()`, `current_clinic()`, `can()`, `is_admin()`
- ✅ Conditional CSS/JS loading — only loads module assets when that module's shortcode is on the page
- ✅ Unified admin design system — all pages use white-bubble cards, teal accents, consistent typography
- ✅ Team Chat module scaffold with Pusher real-time messaging
- ✅ Calendar search queries PostgreSQL directly (not WordPress CPTs)
- ✅ GDPR document upload (admin-settings.php GDPR section)
- ✅ Audit log viewer + data export (CSV/Excel) with GDPR logging
- ✅ All syntax errors from MySQL→PostgreSQL conversion fully resolved
- ✅ Table Enhancer — global search, auto column filters, rows-per-page pagination, sortable column headers
- ✅ Finance Settings — 3-column layout (VAT, Payment & DSP, Invoice) with auto-numbering
- ✅ Report Layout — PNG logo upload, footer textarea, T&C page, section visibility toggles
- ✅ Document Types & Templates — DB-backed CRUD, per-type template section editor, AI keyword rules
- ✅ GDPR consent modal — required checkbox + privacy policy link before any PDF download
- ✅ QuickBooks Online direct integration class (`class-hearmed-qbo.php`) — OAuth 2.0 + full API

### What Remains To Build
- 🚧 **Patient data migration** — ~5,000 patients from legacy system (CSV import tool needed)
- 🚧 **mod-patients** — framework exists, full functionality not built
- 🚧 **mod-calendar** — framework exists, booking flow not functional
- 🚧 **mod-orders** — framework exists, order creation not functional
- 🚧 **mod-approvals** — framework exists, approval flow not functional
- 🚧 **Invoicing + QBO direct integration** — `HearMed_QBO` class exists with OAuth 2.0, needs QB app approval + production keys
- 🚧 **mod-reports** — scaffold only
- 🚧 **mod-commissions** — scaffold only, commission rules seeded in DB
- 🚧 **mod-notifications** — scaffold only, auto-fired notification triggers not wired
- 🚧 **mod-repairs** — scaffold only
- 🚧 **mod-cash** — scaffold only (till reconciliation)
- 🚧 **KPI dashboard** (mod-kpi) — scaffold only, targets admin page complete
- 🚧 **PDF generation** — DomPDF library needed for password-protected PDF creation from document templates
- 🚧 **JotForm integration** — webhook/API to pull patient address, contact details, consent data into patient records
- 🚧 **JotForm digital consent/signature form** — integrated signature capture for consent forms
- 🚧 **AI transcription PDF formatting** — OpenRouter/Make.com webhook processes audio → structured sections using template keyword rules

### Pending Setup Actions
- 📋 **Create WordPress page**: `/document-template-editor/` with shortcode `[hearmed_document_template_editor]` (same Elementor template as other admin pages)
- 📋 **Run migration**: `MIGRATION_DOCUMENT_TEMPLATES.sql` on Railway PostgreSQL (auto-migrates on first page load, but manual run recommended)
- 📋 **Install DomPDF**: `composer require dompdf/dompdf` on SiteGround (when ready for PDF generation)
- 📋 **QuickBooks app**: Register app at developer.intuit.com, get production Client ID/Secret, add to wp-config.php
- 📋 **JotForm setup**: Create patient intake form, configure webhook to push to portal endpoint
- 📋 **Privacy Policy URL**: Set in GDPR Settings → used by consent modal on all document downloads

### Known Issues Requiring Attention
- ⚠️ `FIX_CALENDAR_SETTINGS_COLUMNS.sql` needs to be run on Railway (15 missing columns in calendar_settings table)
- ⚠️ Seed data (clinics, staff, products, appointment types) may need refresh after schema changes
- ⚠️ Portal modules need `HearMed_Auth` role checks wired into their render methods

---

## SECTION 3: ARCHITECTURE — NON-NEGOTIABLE

### Core Principle
**WordPress = UI shell + authentication ONLY**
**PostgreSQL = ALL business data**

WordPress's database (wp_users, wp_usermeta) used only for login/session management. Every patient, appointment, order, invoice, staff record, commission, KPI, notification, and piece of clinical data lives in PostgreSQL on Railway.

**Zero business data in wp_posts, wp_postmeta, or any WordPress table.**

### The Tech Stack
| Tech Stack Item | Technology | Role | Status |
|---|-----------|------|--------|
| UI Shell | WordPress.com Business | Page routing, chrome | ✅ Live |
| Page Layout | Elementor Pro | Dynamic content | ✅ Live |
| Core Plugin | hearmed-calendar | All business logic, shortcodes, AJAX | ✅ Framework ready |
| Database | PostgreSQL on Railway | All 59 tables of business data | ✅ Schema complete, empty |
| Accounting | QuickBooks Online | Accounting mirror | 🚧 Direct OAuth2 integration (no Make.com) |
| Reporting | Direct PostgreSQL queries | All reports + dashboards | 🚧 Planned |

### Plugin Structure
```
hearmed-calendar/
├── hearmed-calendar.php          ← Plugin entry point, bootstrap
├── core/
│   ├── class-hearmed-db.php               ← PostgreSQL abstraction layer (6 methods, parameterised queries)
│   ├── class-hearmed-pg.php               ← Alias file only (HearMed_PG → HearMed_DB)
│   ├── class-hearmed-core.php             ← Singleton bootstrap, loads everything
│   ├── class-hearmed-auth.php             ← Role checks, clinic scoping, current_role(), current_clinic(), can(), is_admin()
│   ├── class-hearmed-staff-auth.php       ← PostgreSQL-backed staff credentials + optional TOTP 2FA
│   ├── class-hearmed-enqueue.php          ← Conditional asset loading, detect_and_load() per module
│   ├── class-hearmed-router.php           ← Shortcode registration & routing
│   ├── class-hearmed-qbo.php              ← QuickBooks Online direct API (OAuth 2.0, invoice/payment sync)
│   ├── class-hearmed-ajax.php             ← Central AJAX dispatcher
│   ├── class-hearmed-logger.php           ← Audit logging to hearmed_admin.audit_log
│   └── class-hearmed-utils.php            ← Formatting, money, dates, phone, page_url(), Irish formats
├── admin/                        ← 24 admin pages (all complete with PostgreSQL CRUD + AJAX)
│   ├── admin-console.php                  ← Landing page with card grid to all sub-pages
│   ├── admin-clinics.php                  ← Clinics CRUD (hm-page pattern, inline edit form)
│   ├── admin-manage-users.php             ← Staff CRUD with clinic assignments + role management
│   ├── admin-products.php                 ← Products/Services/Bundled CRUD with tab bar
│   ├── admin-audiometers.php              ← Audiometer inventory tracking
│   ├── admin-calendar-settings.php        ← Calendar config (time, view, colours, display prefs)
│   ├── admin-groups.php                   ← Staff groups by clinic & role (modal CRUD)
│   ├── admin-resources.php                ← Calendar resources (dispensers per clinic, sortable)
│   ├── admin-taxonomies.php               ← Brands, HearMed Range, Lead Types (generic CRUD)
│   ├── admin-kpi-targets.php              ← Per-dispenser KPI targets with tab bar
│   ├── admin-sms-templates.php            ← SMS templates per appointment type
│   ├── admin-audit-export.php             ← Audit log viewer + GDPR-logged data export
│   ├── admin-dispenser-schedules.php      ← Clinic/day schedules (weekly/2-week rotation)
│   ├── admin-staff-login.php              ← Staff portal login form
│   ├── admin-settings.php                 ← 11 settings sub-pages (finance, comms, GDPR, AI, Pusher, etc.)
│   ├── admin-blockouts.php                ← Calendar blockout periods
│   ├── admin-holidays.php                 ← Public holidays management
│   ├── admin-exclusions.php               ← Resource exclusions by clinic/type
│   ├── admin-appointment-types.php        ← Appointment type CRUD
│   ├── admin-chat-logs.php                ← GDPR-compliant chat audit trail (admin only)
│   ├── admin-document-templates.php       ← Document types CRUD + per-type template section editor
│   ├── admin-debug.php                    ← WP Admin debug/health check (DB, tables, config)
│   └── admin-system-status.php            ← WP Admin system status dashboard
├── modules/                      ← 12 portal modules (shortcode-based, scaffolds awaiting full build)
│   ├── mod-patients.php
│   ├── mod-calendar.php
│   ├── mod-orders.php
│   ├── mod-approvals.php
│   ├── mod-accounting.php
│   ├── mod-reports.php
│   ├── mod-commissions.php
│   ├── mod-kpi.php
│   ├── mod-cash.php
│   ├── mod-repairs.php
│   ├── mod-notifications.php
│   └── mod-team-chat.php
├── includes/
│   ├── class-hearmed-roles.php  ← Role capabilities mapping (8 portal roles)
│   ├── class-cpts.php           ← Custom post types (nav only, no data)
│   └── ajax-handlers.php        ← AJAX response handlers
├── hearmed-theme/               ← Minimal WP theme wrapper
│   ├── functions.php
│   ├── header.php / footer.php
│   └── style.css
└── assets/
    ├── css/                     ← Per-module CSS (hearmed-{module}.css) + hearmed-design.css (foundation)
    │   ├── hearmed-design.css             ← Foundation: CSS variables, base elements, #hm-app rules
    │   ├── hearmed-core.css               ← Core shared styles
    │   ├── hearmed-admin.css              ← Admin pages: white-bubble cards, tables, modals, tabs, forms
    │   ├── hearmed-calendar.css           ← Calendar module styles
    │   ├── calendar-settings.css          ← Calendar settings card grid + colour pickers
    │   ├── hearmed-patients.css
    │   ├── hearmed-reports.css
    │   ├── hearmed-layout.css
    │   └── ... (per-module CSS files)
    └── js/                      ← Per-module JavaScript
        ├── hearmed-core.js                ← HM global namespace, AJAX helpers, toast notifications
        ├── hearmed-admin.js               ← Admin page shared JS (modals, CRUD, table actions)
        ├── hearmed-calendar.js
        ├── hearmed-calendar-settings.js
        ├── hearmed-kpi.js
        ├── hearmed-orders.js
        ├── hearmed-patients.js
        ├── hearmed-reports.js
        └── ... (per-module JS files)
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

### QuickBooks Online Integration — Direct OAuth2

**Setup:** 
- Developer credentials registered with Intuit
- QBO credentials (Client ID, Client Secret, Realm ID) stored in `wp-config.php`
- Direct PHP integration via `HearMed_QBO` class (no Make.com webhooks)

**Outbound (portal → QBO via direct API):**
- New invoice created in portal
- Trigger: Invoice marked as `paid`
- Portal calls QBO API directly with invoice data
- QBO ID returned and stored in `hearmed_core.invoices.qbo_invoice_id`

**Inbound (QBO → portal via direct API):**
- Optional: Pull invoice status from QBO periodically
- Used for reconciliation + accounting dashboard

**Data sent to QBO:**
- Customer name + C-number
- Line items (hearing aids, accessories, services with serial numbers)
- Amount
- Payment method (maps to QBO account)
- Payment date
- Staff member who performed sale
- Clinic location (for reference)
- Invoice number (from portal)

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

**Phase 1: ADMIN FOUNDATION** ✅ COMPLETE
| # | Admin Page | Status | DB Source | Notes |
|---|-----------|--------|-----------|-------|
| ✅ | **All 24 Admin Pages** | ✅ Complete | PostgreSQL | Fully working, CRUD functional, styled consistently |

See Section 14 for the full breakdown of every admin page and its status.

**Phase 2: PORTAL SECTIONS** (In this strict order — NEXT FOCUS)
| # | Module | Status | Dependencies | Notes |
|---|--------|--------|--------------|-------|
| 1️⃣ | **mod-patients** (all parts) | 🚧 NOT WORKING | Admin complete | Framework exists but NOT functional. All tabs: profile, history, outcomes, devices, notes, forms, documents. Needs full build |
| 2️⃣ | **mod-calendar** (appointments) | 🚧 NOT WORKING | Admin complete | Framework exists but NOT functional. Book appointments, connect to patients, add notes. Search now uses PostgreSQL ✅ but rest not working |
| 3️⃣ | **mod-orders** | 🚧 NOT WORKING | Patients + calendar | Framework exists but NOT functional |
| 4️⃣ | **mod-approvals** | 🚧 NOT WORKING | Orders | Framework exists but NOT functional |
| 5️⃣ | **Invoicing (QuickBooks)** | 🚧 In Build | Approvals complete | Direct QBO integration via OAuth2 credentials (wp-config.php). Create invoice → await approval → send directly to QBO |
| 6️⃣ | **mod-team-chat** | 🚧 Scaffold | Patients complete | In-house messaging, soft-delete only, audit trail |
| 7️⃣ | **mod-reports** | 🚧 Scaffold | Invoicing + chat | Patient history, sales, commissions, accounting reports |
| 8️⃣ | **In-House Notifications** | 🚧 Scaffold | Orders + chat | Pop-up badge in top-right; "Order received", "Aids ready", "Call patient" |
| 9️⃣ | **KPI + Till Tracking** | 🚧 Scaffold | Appointments complete | Per-staff KPI dashboard + clinic till reconciliation |
| 🔟 | **Accounting** | 🚧 Scaffold | Reports complete | Supplier invoices, receipts, staff photo upload, QBO sync |

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

## SECTION 14: ADMIN PAGES STATUS — ALL COMPLETE ✅

### Admin Pages — Full Status
| Page | Shortcode(s) | DB Source | Status |
|------|-------------|-----------|--------|
| Admin Console | `hearmed_admin_console` | — | ✅ Landing page with card grid |
| Clinics | `hearmed_manage_clinics` | `hearmed_reference.clinics` | ✅ Full CRUD, inline edit form |
| Users (Staff) | `hearmed_manage_users` | `hearmed_reference.staff` + `staff_clinics` | ✅ Full CRUD, clinic assignments, roles |
| Products | `hearmed_products` | `hearmed_reference.products` | ✅ Full CRUD, tab bar (Products/Services/Bundled) |
| Audiometers | `hearmed_audiometers` | `hearmed_reference.audiometers` | ✅ Full CRUD, inventory tracking |
| Calendar Settings | `hearmed_calendar_settings` | `hearmed_core.calendar_settings` | ✅ Time, view, colours, display prefs |
| Appointment Types | `hearmed_appointment_types` | `hearmed_reference.appointment_types` | ✅ Full CRUD with modal |
| Staff Groups | `hearmed_admin_groups` | `hearmed_reference.staff_groups` + `staff_group_members` | ✅ Group by clinic, member management |
| Resources | `hearmed_admin_resources` | `hearmed_reference.staff` | ✅ Sortable dispenser list per clinic |
| Brands | `hearmed_brands` | `hearmed_reference.manufacturers` | ✅ Full CRUD with modal |
| HearMed Range | `hearmed_range_settings` | `hearmed_reference.hearmed_range` | ✅ Full CRUD, €-formatted prices |
| Lead Types | `hearmed_lead_types` | `hearmed_reference.referral_sources` | ✅ Full CRUD, parent/child hierarchy |
| KPI Targets | `hearmed_kpi_targets` | `hearmed_admin.kpi_targets` | ✅ Per-dispenser targets with tab bar |
| SMS Templates | `hearmed_sms_templates` | `hearmed_communication.sms_templates` | ✅ Full CRUD, per appointment type |
| Audit Log | `hearmed_audit_log` | `hearmed_admin.audit_log` | ✅ Filterable log viewer |
| Data Export | `hearmed_data_export` | Multiple tables | ✅ CSV/Excel export with GDPR logging |
| Dispenser Schedules | `hearmed_dispenser_schedules` | `hearmed_reference.staff_schedules` | ✅ Clinic/day, weekly/2-week rotation |
| Blockouts | `hearmed_admin_blockouts` | `hearmed_core.calendar_blockouts` | ✅ Full CRUD with modal |
| Holidays | `hearmed_admin_holidays` | `hearmed_core.calendar_blockouts` | ✅ Full CRUD with modal |
| Exclusions | `hearmed_admin_exclusions` | `hearmed_core.calendar_exclusions` | ✅ Card-based CRUD |
| Staff Login | `hearmed_staff_login` | WP auth + `hearmed_reference.staff_auth` | ✅ Login form with error handling |
| Settings (11 sub-pages) | `hearmed_finance_settings`, `hearmed_comms_settings`, `hearmed_gdpr_settings`, etc. | `wp_options` + PostgreSQL | ✅ All settings pages with white-bubble form cards |
| Chat Logs | `hearmed_chat_logs` | `hearmed_communication.chat_messages` | ✅ Admin audit trail, filterable, GDPR notice |
| Debug | WP Admin page | Multiple | ✅ DB health check, table counts, config dump |
| System Status | WP Admin page | Multiple | ✅ Connection status, PHP info, WP info |

### Design System — Consistent Across All Pages
All admin pages use the unified HearMed admin design pattern:
- **White-bubble cards**: `.hm-settings-panel` (white bg, border, border-radius: 12px, subtle shadow)
- **Admin tables**: `.hm-admin .hm-table` (white card, grey header, teal name links, hover highlight)
- **Header bar**: `.hm-admin-hd` (h2 title + teal action button, flex layout)
- **Tab bar**: `.hm-tab-bar` + `.hm-tab` / `.hm-tab.active` (teal underline active state)
- **Modals**: `.hm-modal-bg` > `.hm-modal` (backdrop + centered card)
- **Badges**: `.hm-badge` + `.hm-badge-green` / `.hm-badge-red` / `.hm-badge-blue` / `.hm-badge-yellow`
- **Buttons**: `.hm-btn .hm-btn-teal` (primary), `.hm-btn .hm-btn-sm` (table actions), `.hm-btn .hm-btn-red` (delete)
- **Empty states**: `.hm-empty-state` (centred message in white card)
- **Toggles**: `.hm-toggle-label` (inline flex, teal accent-color checkbox)
- **Alerts**: `.hm-alert .hm-alert-warning` / `.hm-alert-error` / `.hm-alert-success`

### CSS Architecture
- `hearmed-design.css` — Foundation: CSS variables, `#hm-app` base elements, grid, typography
- `hearmed-core.css` — Core shared components used by portal modules
- `hearmed-admin.css` (~1,100 lines) — All admin-specific styles: console grid, settings panels, tables, modals, forms, tabs, sortable lists, product/user/clinic-specific styles
- `calendar-settings.css` — Calendar settings card grid + colour pickers (loaded separately)
- Per-module CSS loaded conditionally via `detect_and_load()` in `class-hearmed-enqueue.php`

### Asset Loading Strategy
`class-hearmed-enqueue.php` uses a two-tier conditional loading system:
1. **`is_portal_page()`** — checks if ANY portal shortcode is present → loads foundation CSS (design + core)
2. **`detect_and_load($module, $content, $shortcodes)`** — checks for specific shortcodes → loads `hearmed-{$module}.css` + `hearmed-{$module}.js`
3. All 40+ shortcodes are registered in both the `portal_shortcodes` list and their respective module's detection list

---

## SECTION 15: CURRENT CODE STATUS

### All PHP Syntax Clean ✅
- ✅ 50+ PHP files syntax-clean (all core/, admin/, modules/, includes/)
- ✅ All MySQL patterns replaced with PostgreSQL parameterised queries
- ✅ All WordPress CPT dependencies replaced with PostgreSQL queries
- ✅ `class-hearmed-auth.php` fully refactored — static method wrappers, `current_role()`, `current_clinic()`
- ✅ `class-hearmed-utils.php` — `page_url($module)` helper for cross-module navigation
- ✅ `class-hearmed-staff-auth.php` — PostgreSQL-backed credential system with TOTP 2FA support
- ✅ `class-hearmed-logger.php` — Audit logging for GDPR compliance
- ✅ Search functions use PostgreSQL exclusively
- ✅ Router shortcode_map includes all module shortcodes

### Core Framework Classes
| Class | File | Purpose | Status |
|-------|------|---------|--------|
| `HearMed_DB` | `class-hearmed-db.php` | PostgreSQL abstraction (get_results, get_row, get_var, insert, update, delete) | ✅ Complete |
| `HearMed_Core` | `class-hearmed-core.php` | Singleton bootstrap, loads all classes | ✅ Complete |
| `HearMed_Auth` | `class-hearmed-auth.php` | Role checks, clinic scoping, permissions | ✅ Complete |
| `HearMed_Staff_Auth` | `class-hearmed-staff-auth.php` | Staff credentials + optional TOTP 2FA | ✅ Complete |
| `HearMed_Enqueue` | `class-hearmed-enqueue.php` | Conditional CSS/JS per-module loading | ✅ Complete |
| `HearMed_Router` | `class-hearmed-router.php` | Shortcode registration + privacy gate | ✅ Complete |
| `HearMed_Ajax` | `class-hearmed-ajax.php` | Central AJAX dispatcher | ✅ Complete |
| `HearMed_Utils` | `class-hearmed-utils.php` | Formatting, money, dates, page_url() | ✅ Complete |
| `HearMed_Logger` | `class-hearmed-logger.php` | Audit trail logging | ✅ Complete |
| `HearMed_QBO` | `class-hearmed-qbo.php` | QuickBooks Online direct OAuth2 integration | 🚧 Scaffold |

### Auto-Deployment Active ✅
- ✅ GitHub Actions workflow: `deploy.yml`
- ✅ On every push to `main` branch → SSH to SiteGround → `git pull origin main`
- ✅ No manual SFTP needed — fully automated
- ✅ Branch: `main` only

---

## SECTION 16: MIGRATION SQL FILES

Several SQL migration files exist in the repo root for schema changes that may need to be run on Railway:

| File | Purpose | Status |
|------|---------|--------|
| `DATABASE_SCHEMA.sql` | Full 60+ table schema | ✅ Applied |
| `SEED_DATA.sql` | Reference data seeding (clinics, appointment types, etc.) | ✅ Applied |
| `MIGRATION_ADD_AUDIOMETERS.sql` | Add audiometers table | ✅ Applied |
| `MIGRATION_ADD_MISSING_COLUMNS.sql` | Various missing columns across tables | ✅ Applied |
| `MIGRATION_ADD_ROLES_TABLE.sql` | Add roles reference table | ✅ Applied |
| `MIGRATION_ADMIN_GROUPS_RESOURCES.sql` | Staff groups + group members tables | ✅ Applied |
| `MIGRATION_STAFF_AUTH_SCHEDULES.sql` | Staff auth + schedules tables | ✅ Applied |
| `FIX_STAFF_TABLE_COLUMNS.sql` | Missing staff table columns | ✅ Applied |
| `FIX_STAFF_AUTH_TABLE_COLUMNS.sql` | Staff auth table fixes | ✅ Applied |
| `FIX_CALENDAR_SETTINGS_COLUMNS.sql` | 15 missing columns in calendar_settings | ⚠️ **NEEDS TO BE RUN ON RAILWAY** |

---

## SECTION 17: NEXT PHASE — PORTAL MODULES

**Admin foundation is complete. All 24 admin pages are built, styled, and deployed.**

### Build Queue (Priority Order)
| # | Module | Status | Dependencies | Next Step |
|---|--------|--------|--------------|-----------|
| 1️⃣ | **mod-patients** | 🚧 Scaffold | Admin complete | Build full patient record: profile, history, outcomes, devices, notes, forms, documents |
| 2️⃣ | **mod-calendar** | 🚧 Scaffold | Patients working | Build appointment booking, drag/drop, outcome recording |
| 3️⃣ | **mod-orders** | 🚧 Scaffold | Patients + Calendar | Build order creation, status tracking, serial number entry |
| 4️⃣ | **mod-approvals** | 🚧 Scaffold | Orders working | Build approval dashboard, approve/deny flow |
| 5️⃣ | **Invoicing + QBO** | 🚧 Scaffold | Approvals complete | Build invoice creation, payment recording, QBO sync |
| 6️⃣ | **mod-team-chat** | 🚧 Scaffold | Independent | Pusher real-time messaging, company + DM channels |
| 7️⃣ | **mod-reports** | 🚧 Scaffold | Invoicing complete | Revenue, sales, commissions, patient reports |
| 8️⃣ | **mod-notifications** | 🚧 Scaffold | Orders + Chat | Auto-fired notifications, bell icon, polling |
| 9️⃣ | **mod-kpi** | 🚧 Scaffold | Appointments complete | Per-staff KPI dashboard |
| 🔟 | **mod-cash** | 🚧 Scaffold | Independent | Till reconciliation per clinic |
| 1️⃣1️⃣ | **mod-commissions** | 🚧 Scaffold | Reports complete | Commission calculation, PDF statements |
| 1️⃣2️⃣ | **mod-repairs** | 🚧 Scaffold | Patients + Orders | Repair tracking, warranty status |

### Build Checklist (Template for Each Module)
1. ✅ PostgreSQL queries working (read + write)
2. ✅ AJAX handlers responding correctly
3. ✅ UI renders data in `#hm-app` wrapper
4. ✅ Styled per HearMed design system
5. ✅ Role-based access checks via `HearMed_Auth`
6. ✅ GDPR audit logging for sensitive data access
7. ✅ Error handling and validation
8. ✅ Tested end-to-end, committed, auto-deployed

### Critical Pre-Requisite
**Patient data migration (~5,000 records)** must happen before modules can be properly tested. Build a CSV import tool in the admin console or run a one-time migration script.

---

## SECTION 18: SESSION LOG — RECENT WORK COMPLETED

### Session: 25 February 2026 (Evening)

**Focus: Admin page CSS consistency + enqueue fixes + style unification**

#### Issues Identified & Fixed
1. **CSS not loading on many admin pages** — Root cause: 16 admin shortcodes were missing from `detect_and_load('admin', ...)` list in `class-hearmed-enqueue.php`. Pages had correct HTML classes but `hearmed-admin.css` never loaded.
   - Added all settings sub-shortcodes, blockouts, holidays, exclusions, calendar-settings, appointment-types, chat-logs to both `portal_shortcodes` and admin detection lists

2. **KPI Targets page completely unstyled** — `hearmed_kpi_targets` was in the `kpi` module detection but NOT in the `admin` module detection. Fixed by adding to admin list.

3. **Settings panels not showing as white cards** — `.hm-settings-panel` had `background: transparent; border: none; padding: 0`. Changed to proper white-bubble card: `background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px 24px; box-shadow`.

4. **Form inputs unstyled in admin context** — Admin pages don't use `#hm-app` wrapper, so inputs missed the border/focus styles from `hearmed-design.css`. Added proper `border`, `padding`, `border-radius`, and teal focus glow to `.hm-settings-panel .hm-form-group` inputs.

5. **Tab bars using inline styles** — KPI Targets and Products pages had complex inline style strings for their tab navigation. Created shared `.hm-tab-bar` / `.hm-tab` / `.hm-tab.active` CSS classes and converted both pages.

6. **Chat Logs page non-conforming** — Was using custom wrapper, custom filter classes, inline `<style>` block, `hm-badge-navy`, `hm-btn-primary`/`hm-btn-outline`. Rebuilt to standard `hm-admin` pattern: `hm-admin-hd` header, `hm-settings-panel` filter card, `hm-table` directly in wrapper, `hm-alert-warning` for GDPR notice.

7. **Tables inside settings panels double-bordered** — When `hm-table` (white card) was nested inside `hm-settings-panel` (also white card), two borders appeared. Added `.hm-settings-panel .hm-table { border: none; border-radius: 0; box-shadow: none; }` to strip inner card styling.

8. **Staff auth NOT NULL violation** — `password_hash` column in `staff_auth` was NOT NULL but `ensure_auth_for_staff()` was inserting NULL. Fixed to use random placeholder hash.

9. **Calendar settings save error** — 15 columns missing from `calendar_settings` table (migration step 7b not run on production). Created `FIX_CALENDAR_SETTINGS_COLUMNS.sql`.

#### Files Changed
- `core/class-hearmed-enqueue.php` — Added 16+ shortcodes to portal + admin detection lists
- `assets/css/hearmed-admin.css` — White-bubble settings panels, form input styling, tab bar component, table-in-panel fix
- `admin/admin-chat-logs.php` — Full rebuild to standard hm-admin pattern
- `admin/admin-kpi-targets.php` — Converted to shared hm-tab-bar
- `admin/admin-products.php` — Converted to shared hm-tab-bar
- `core/class-hearmed-staff-auth.php` — Fixed NOT NULL violation with random placeholder hash
- `FIX_CALENDAR_SETTINGS_COLUMNS.sql` — Created (needs to be run on Railway)

#### Previous Sessions Summary
- Built/rebuilt all 25 admin pages from scratch with PostgreSQL CRUD + AJAX
- Created 7 migration SQL files for schema additions (including `MIGRATION_DOCUMENT_TEMPLATES.sql`)
- Added `HearMed_Auth::current_role()`, `current_clinic()`, `HearMed_Utils::page_url()`
- Fixed `hearmed_orders` missing from router shortcode_map
- Added Pusher settings, chat logs admin page, enqueue fix for chat module
- Converted admin-debug.php and admin-system-status.php to hm-admin pattern
- Added alert, filter, badge, and section CSS classes to hearmed-admin.css
- Table Enhancer v2: global search, auto column filters, rows-per-page pagination, sortable column headers (▲/▼)
- Finance Settings restructured to 3-column grid (VAT, Payment & DSP, Invoice) with auto-numbering
- Report Layout: PNG logo upload, larger footer textarea, T&C page, 9 section visibility toggles
- Document Types & Templates system: DB-backed CRUD, card UI, per-type template section editor with drag-reorder
- AI keyword detection rules for medical history + hearing results sections
- GDPR consent modal (`hmGdprConsent.require()`) enforced before PDF/document downloads
- Manufacturer "Other (Please Describe)" field with toggle visibility
- KPI targets lighter font-weight (400), step=1 on inputs
- Cash Management narrower bubble (max-width 480px)
- Dome type/size DB-backed with Add New dropdown
- Custom render dispatch in admin-settings.php for finance + report sub-pages

