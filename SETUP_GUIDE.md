# 🚀 HearMed Portal — Getting Started

## ✅ What's Fixed (Just Committed)

- **Critical**: Removed duplicate function declarations causing plugin crashes
- **All 36 PHP files** now pass syntax validation
- **PostgreSQL seed data script** created with minimal test data (1 clinic, 1 staff, 1 patient, etc.)

## 📋 Next Steps to Get the System Working

### Step 1: Reload WordPress & Activate Plugin
1. Go to your WordPress admin: `portal.hearmedpayments.net/wp-admin/`
2. Navigate to **Plugins**
3. The **HearMed Portal 5.0** plugin should now be visible (not throwing fatal error)
4. Click **Activate** if not already active
5. Check that the Elementor template is now visible on your page

### Step 2: Seed the Database (TEST DATA)
Run this SQL script on your Railway PostgreSQL database:

```bash
# Option 1: If you have psql installed locally
psql -U <your_user> -d hearmed_prod < SEED_DATA.sql

# Option 2: Via Railway Dashboard directly
# 1. Open Railway.app → your PostgreSQL database
# 2. Navigate to "Query" tab
# 3. Copy & paste contents of SEED_DATA.sql
# 4. Run the query
```

**What gets seeded:**
- 1 clinic: `HearMed Dublin`
- 1 staff member: `John Dispenser`
- 1 test patient: `Test Patient`
- 5 appointment types (consultation, fitting, review, etc.)
- 4 services (hearing test, fitting, etc.)
- 3 outcome templates
- 3 hearing aid products
- 4 payment methods

### Step 3: Test the Calendar
1. In WordPress admin: Go to **HearMed Calendar** page (if available)
2. Or access the calendar shortcode on your Elementor template
3. You should see:
   - Clinic selector showing "HearMed Dublin"
   - Dispenser selector showing "John Dispenser"
   - Available services
   - Appointment types

### Step 4: Test Patient Lookup
1. Go to **Patients** section
2. Search for "Test Patient"
3. Should find the patient and show creation option

## 📁 Key Files Reference

| File | Purpose |
|------|---------|
| `PROJECT_CONTEXT.md` | 📚 Master project documentation (architecture, business rules, GDPR, commission structure) |
| `DATABASE_SCHEMA.sql` | 🔧 Complete PostgreSQL schema (60 tables, 4 functions) |
| `SEED_DATA.sql` | 🌱 Minimal test data for UI/UX testing |
| `hearmed-calendar.php` | 🎯 Main plugin bootstrap file |
| `core/` | ⚙️ Core system classes (DB, Auth, Router, AJAX, Enqueue) |
| `admin/` | 🖥️ WordPress admin pages and tools |
| `modules/` | 📦 Feature modules (calendar, patients, orders, etc.) |

## 🐛 Troubleshooting

**If WordPress still shows error:**
- Check WordPress error log: `/wp-content/debug.log`
- Verify plugin is fetching latest code (GitHub → SiteGround SFTP)
- Try: `wp plugin deactivate hearmed-calendar && wp plugin activate hearmed-calendar` (via WP-CLI)

**If calendar doesn't load:**
- Check browser console (F12 → Console tab) for JavaScript errors
- Verify Railway PostgreSQL connection (check HearMed_DB logs)
- Ensure SEED_DATA.sql was run successfully

**If patient search returns nothing:**
- Verify SEED_DATA.sql executed without errors
- Check `hearmed_core.patients` table: `SELECT COUNT(*) FROM hearmed_core.patients;`

## 📞 System Status Check

To verify everything is connected:

```sql
-- On Railway PostgreSQL:
SELECT 'Clinics' as table_name, COUNT(*) as row_count FROM hearmed_reference.clinics
UNION
SELECT 'Staff', COUNT(*) FROM hearmed_reference.staff
UNION
SELECT 'Patients', COUNT(*) FROM hearmed_core.patients
UNION
SELECT 'Services', COUNT(*) FROM hearmed_reference.services;
```

Should return:
```
table_name  | row_count
------------|----------
Clinics     |         1
Staff       |         1
Patients    |         1
Services    |         4
```

## 🎯 Next Development Priority

According to PROJECT_CONTEXT.md, the module build priority is:

1. **✅ FOUNDATION** — Fixed & working
   - Core classes (DB, Auth, Router)
   - Calendar module (fixed PostgreSQL conversion)
   - Patient lookup
   - Order creation

2. **📋 NOTIFICATIONS** (BUILD NEXT)
   - Email + SMS notification system
   - Appointment reminders
   - Delivery tracking

3. **📋 REPAIRS**
   - Device repair workflow
   - Status tracking
   - Customer notifications

4. **📋 TEAM CHAT**
   - Internal messaging
   - File sharing
   - Activity logging

5. **📋 ACCOUNTING**
   - Invoice generation
   - Payment tracking
   - Commission calculations

6. **📋 REPORTS**
   - Sales reports
   - KPI dashboards
   - Staff performance

7. **📋 COMMISSIONS** (Tiered %)
   - Dispensers: 0%, 8%, 10%, 20% based on targets
   - CA/Reception: Flat %
   - PostgreSQL cutoff function

8. **📋 KPI & CASH**
   - Cash drawer management
   - Till reconciliation
   - Performance metrics

---

**Questions?** Check PROJECT_CONTEXT.md and DATABASE_SCHEMA.sql for detailed architecture and design decisions.
