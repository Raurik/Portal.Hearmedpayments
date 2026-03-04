# HearMed Portal — Financial Layer Rebuild

## MASTER INSTRUCTIONS — READ THIS FIRST

**Date:** 4 March 2026
**Author:** Rauri (Co-Director, HearMed)

---

## CRITICAL: READ THIS ENTIRE FILE BEFORE WRITING ANY CODE

This document describes the HearMed Portal financial layer rebuild. There are 5 task files that accompany this document. Complete them **in order** — each builds on the previous one.

```
TASK FILES (complete in this order):
  TASK_1_finance_class.md        ← HearMed_Finance transaction recorder
  TASK_2_wire_transactions.md    ← Wire financial_transactions into all existing flows
  TASK_3_patient_account.md      ← Patient Account tab on patient file
  TASK_4_apply_credit.md         ← Apply patient credit at fitting/payment
  TASK_5_outcome_triggers.md     ← Appointment outcome → order/invoice wiring
```

Do ONE task at a time. Verify it works. Then move to the next. Do not attempt all five simultaneously.

---

## THE ORDER LIFECYCLE — LOCKED. DO NOT CHANGE. EVER.

This is the order status flow. It is final. You must never add statuses, remove statuses, skip statuses, or reorder statuses. Every order follows this exact path:

```
┌─────────────────────┐
│  1. Order Created    │  Status: "Awaiting Approval"
│                      │  Created by: dispenser (staff_id = logged-in user)
│                      │  Patient, clinic, items, PRSI, deposit all captured here
│                      │  Deposit payment recorded if deposit > 0
└──────────┬──────────┘
           │
           ▼
┌─────────────────────┐
│  2. C-Level Approval │  Status: "Approved"
│                      │  approved_by, approved_at, approval_note set
│                      │  C-Level reviews and approves or rejects
│                      │  If rejected → Status: "Cancelled" (end of line)
└──────────┬──────────┘
           │
           ▼
┌─────────────────────┐
│  3. Ordered from     │  Status: "Ordered"
│     Supplier         │  ordered_at timestamp set
│                      │  Hearing aids ordered from manufacturer
└──────────┬──────────┘
           │
           ▼
┌─────────────────────┐
│  4. Received in      │  Status: "Received"
│     Branch           │  arrived_at / received_date set
│                      │  received_by = current user
│                      │  Hearing aids physically arrived at clinic
│                      │  Serial numbers CAN be entered here (optional)
└──────────┬──────────┘
           │
           ▼
┌─────────────────────┐
│  5. Awaiting Fitting │  Status: "Awaiting Fitting"
│                      │  Order added to fitting_queue
│                      │  Serial numbers MUST exist before completion
│                      │  Patient contacted to schedule fitting
└──────────┬──────────┘
           │
           ▼
┌─────────────────────┐
│  6. Fitting Complete │  Status: "Complete"
│                      │  fitted_at, fitted_by set
│                      │  Serials verified (gate check — cannot complete without)
│                      │  patient_devices records created/updated
│                      │  Invoice created (HearMed_Invoice::create_from_order)
│                      │  Payment recorded (balance after deposit)
│                      │  Patient credit applied if available and chosen
│                      │  financial_transactions entry created
│                      │  fitting_queue updated to "Fitted"
└─────────────────────┘

CANCELLATION (any pre-Complete status):
  → Status: "Cancelled"
  → cancellation_type, cancellation_reason, cancellation_date set
  → If deposit was paid → credit note raised → patient_credits entry
  → fitting_queue set to "Cancelled" if it existed
```

### DB constraint (already exists):
```sql
CONSTRAINT chk_order_status CHECK (current_status IN (
  'Awaiting Approval', 'Approved', 'Ordered', 'Received',
  'Awaiting Fitting', 'Complete', 'Cancelled'
))
```

### EXCEPTION — Service-only quickpay:
If ALL items are services (no products), the order skips approval and goes straight to "Complete" with auto-generated invoice. This already works. DO NOT CHANGE IT.

---

## CODEBASE REFERENCE

### File locations (relative to plugin root `hearmed-calendar/`):

```
core/
  class-hearmed-db.php          ← DB abstraction. DO NOT MODIFY.
  class-hearmed-invoice.php     ← Invoice creation. Modified in Task 2.
  class-hearmed-auth.php        ← Auth. DO NOT MODIFY.
  class-hearmed-settings.php    ← Settings. DO NOT MODIFY.
  class-hearmed-utils.php       ← Utilities. DO NOT MODIFY.
  class-hearmed-stock.php       ← Stock. DO NOT MODIFY.
  class-hearmed-finance.php     ← NEW FILE. Created in Task 1.

modules/
  mod-orders.php                ← Order CRUD + UI. Modified in Tasks 2 + 4.
  mod-refunds.php               ← Credit notes + refunds. Modified in Task 2.
  mod-calendar.php              ← Calendar + outcomes. Modified in Task 5.
  mod-patients.php              ← Patient file. Modified in Task 3.
  mod-kpi.php                   ← KPI dashboard. DO NOT MODIFY.

admin/
  admin-finance-form-builder.php ← Form builder. DO NOT MODIFY.
```

### Database schemas:
```
hearmed_core          ← orders, invoices, payments, credit_notes, patient_credits, financial_transactions
hearmed_reference     ← clinics, staff, products, services, inventory_stock
hearmed_admin         ← settings, qbo_batch_queue, audit_log
hearmed_communication ← sms, emails, notifications
```

### DB layer API:
```php
$db = HearMed_DB::instance();

// Insert — returns new row ID or false
$id = $db->insert('hearmed_core.orders', ['column' => 'value']);

// Update — returns affected rows or false
$db->update('hearmed_core.orders', ['column' => 'value'], ['id' => $order_id]);

// Select one row
$row = $db->get_row("SELECT * FROM hearmed_core.orders WHERE id = $1", [$order_id]);

// Select many rows
$rows = $db->get_results("SELECT * FROM hearmed_core.orders WHERE patient_id = $1", [$pid]);

// Select single value
$val = $db->get_var("SELECT COUNT(*) FROM hearmed_core.orders WHERE patient_id = $1", [$pid]);

// Raw query
$db->query("UPDATE hearmed_core.orders SET current_status = $1 WHERE id = $2", ['Approved', $id]);

// Transactions
HearMed_DB::begin_transaction();
HearMed_DB::commit();
HearMed_DB::rollback();
```

### AJAX handler pattern:
```php
public static function ajax_something() {
    check_ajax_referer('hm_nonce', 'nonce');
    if (!HearMed_Auth::can('required_permission')) wp_send_json_error('Access denied.');
    // ... do work ...
    wp_send_json_success(['message' => 'Done', 'data' => $whatever]);
}
// Registered as: add_action('wp_ajax_hm_something', [self::class, 'ajax_something']);
```

### Current user / clinic:
```php
$user   = HearMed_Auth::current_user();   // ->id, ->first_name, ->last_name, ->role
$clinic = HearMed_Auth::current_clinic();  // integer clinic ID
```

### CSS classes (from hearmed-core.css):
```
Panels:   .hm-settings-panel, .hm-card
Forms:    .hm-form__group, .hm-form__label, .hm-form__hint, .hm-input, .hm-select
Buttons:  .hm-btn, .hm-btn--primary, .hm-btn--sm, .hm-btn--danger
Tables:   .hm-table, .hm-table th, .hm-table td
Text:     .hm-muted, .hm-text--teal, .hm-text--danger
Layout:   .hm-flex, .hm-gap-sm, .hm-gap-md
Status:   .hm-badge, .hm-badge--green, .hm-badge--amber, .hm-badge--red
Wrapper:  #hm-app (ALL portal content must be inside this)
```

### Brand colours:
```
Navy:  #151B33  (--hm-navy)
Teal:  #0BB4C4  (--hm-teal)
```

---

## THINGS YOU MUST NOT DO

1. **DO NOT change the order status flow.** Awaiting Approval → Approved → Ordered → Received → Awaiting Fitting → Complete. Plus Cancelled. Nothing else.

2. **DO NOT modify class-hearmed-db.php.** The database abstraction layer is stable.

3. **DO NOT create new WordPress pages or shortcodes.** Everything fits into existing pages.

4. **DO NOT restructure existing tables.** You may ADD columns if needed (like `notes` on payments). Never drop, rename, or reorganize existing columns.

5. **DO NOT create a separate exchange flow.** Exchanges are: return → credit note → patient credit → new order → apply credit at payment. No special exchange table or status.

6. **DO NOT duplicate functionality.** ONE credit note creator (mod-refunds.php). ONE invoice creator (class-hearmed-invoice.php). ONE transaction recorder (HearMed_Finance). Use them.

7. **DO NOT change the CSS framework.** Use existing `.hm-` classes. Use `--hm-navy` and `--hm-teal` variables. Everything inside `#hm-app`.

8. **DO NOT add npm packages, composer packages, or external dependencies.** Vanilla PHP + jQuery.

9. **DO NOT modify the Finance Form Builder.** It configures print templates. Your work is data layer and UI panels.

10. **DO NOT auto-sync to QuickBooks.** QBO uses a manual review queue (`qbo_batch_queue`, `status = 'pending'`). Rauri reviews and sends. Do not change this.

---

## MASTER CHECKLIST

After completing all 5 tasks, run these integration tests:

- [ ] Create a test order with €200 deposit → check `financial_transactions` has a `deposit` row
- [ ] Approve → Order → Receive → Await Fitting → Complete the order → check `financial_transactions` has a `payment` row
- [ ] Go to patient file → Account tab → verify deposit and payment both show
- [ ] Create a credit note for that patient → check `financial_transactions` has a `credit_note` row
- [ ] Verify patient Account tab shows credit with remaining balance
- [ ] Create a new order for same patient → at completion, apply credit → verify credit drawn down
- [ ] Check `financial_transactions` has a `credit_applied` row
- [ ] Save an appointment outcome with `triggers_order = true` → verify redirect to order form with patient pre-filled
- [ ] Save an appointment outcome with `triggers_invoice = true` → verify redirect to quickpay form
- [ ] Check PHP error log — no errors throughout all tests