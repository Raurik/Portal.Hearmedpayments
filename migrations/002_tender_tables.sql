-- ============================================================================
-- HearMed Tender / Cash Management Tables
-- Schema: hearmed_core
-- Run on Railway PostgreSQL
-- ============================================================================

-- Staff tender (one per staff member)
CREATE TABLE IF NOT EXISTS hearmed_core.staff_tenders (
    id              BIGSERIAL PRIMARY KEY,
    staff_id        BIGINT NOT NULL REFERENCES hearmed_reference.staff(id),
    clinic_id       BIGINT REFERENCES hearmed_reference.clinics(id),
    cash_balance    NUMERIC(12,2) NOT NULL DEFAULT 0,
    cheque_balance  NUMERIC(12,2) NOT NULL DEFAULT 0,
    float_amount    NUMERIC(12,2) NOT NULL DEFAULT 0,
    status          VARCHAR(20) NOT NULL DEFAULT 'active'
                    CHECK (status IN ('active','suspended','closed')),
    last_reconciled TIMESTAMPTZ,
    created_at      TIMESTAMPTZ DEFAULT NOW(),
    updated_at      TIMESTAMPTZ DEFAULT NOW()
);
CREATE UNIQUE INDEX IF NOT EXISTS idx_staff_tenders_staff ON hearmed_core.staff_tenders(staff_id);

-- Individual tender entries (running log)
CREATE TABLE IF NOT EXISTS hearmed_core.tender_entries (
    id              BIGSERIAL PRIMARY KEY,
    tender_id       BIGINT NOT NULL REFERENCES hearmed_core.staff_tenders(id),
    entry_type      VARCHAR(30) NOT NULL
                    CHECK (entry_type IN (
                        'payment_in','opening_float','float_topup',
                        'lodgment_cash','lodgment_cheque',
                        'petty_cash','till_float','adjustment'
                    )),
    tender_type     VARCHAR(10) NOT NULL CHECK (tender_type IN ('cash','cheque')),
    direction       VARCHAR(5) NOT NULL CHECK (direction IN ('in','out')),
    amount          NUMERIC(12,2) NOT NULL,
    running_cash    NUMERIC(12,2) NOT NULL DEFAULT 0,
    running_cheque  NUMERIC(12,2) NOT NULL DEFAULT 0,
    payment_id      BIGINT,
    invoice_id      BIGINT,
    lodgment_id     BIGINT,
    expense_id      BIGINT,
    notes           TEXT,
    created_by      BIGINT,
    created_at      TIMESTAMPTZ DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_tender_entries_tender ON hearmed_core.tender_entries(tender_id);

-- Lodgments (bank deposits)
CREATE TABLE IF NOT EXISTS hearmed_core.tender_lodgments (
    id              BIGSERIAL PRIMARY KEY,
    tender_id       BIGINT NOT NULL REFERENCES hearmed_core.staff_tenders(id),
    lodge_type      VARCHAR(10) NOT NULL CHECK (lodge_type IN ('cash','cheque','both')),
    cash_amount     NUMERIC(12,2) NOT NULL DEFAULT 0,
    cheque_amount   NUMERIC(12,2) NOT NULL DEFAULT 0,
    cheque_count    INT DEFAULT 0,
    bank_reference  VARCHAR(100),
    lodge_slip_url  VARCHAR(500),
    notes           TEXT,
    status          VARCHAR(20) NOT NULL DEFAULT 'pending'
                    CHECK (status IN ('pending','confirmed','queried','rejected')),
    confirmed_by    BIGINT,
    confirmed_at    TIMESTAMPTZ,
    qbo_deposit_id  VARCHAR(50),
    lodgment_date   DATE DEFAULT CURRENT_DATE,
    created_by      BIGINT,
    created_at      TIMESTAMPTZ DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_tender_lodgments_tender ON hearmed_core.tender_lodgments(tender_id);
CREATE INDEX IF NOT EXISTS idx_tender_lodgments_status ON hearmed_core.tender_lodgments(status);

-- Petty cash expenses
CREATE TABLE IF NOT EXISTS hearmed_core.petty_cash_expenses (
    id              BIGSERIAL PRIMARY KEY,
    tender_id       BIGINT NOT NULL REFERENCES hearmed_core.staff_tenders(id),
    amount          NUMERIC(12,2) NOT NULL,
    category        VARCHAR(30) NOT NULL,
    description     TEXT NOT NULL,
    vendor          VARCHAR(255),
    receipt_url     VARCHAR(500),
    notes           TEXT,
    status          VARCHAR(20) NOT NULL DEFAULT 'pending'
                    CHECK (status IN ('pending','approved','rejected')),
    qbo_expense_id  VARCHAR(50),
    expense_date    DATE DEFAULT CURRENT_DATE,
    created_by      BIGINT,
    created_at      TIMESTAMPTZ DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_petty_cash_tender ON hearmed_core.petty_cash_expenses(tender_id);

-- Upload tokens (for QR photo capture flow)
CREATE TABLE IF NOT EXISTS hearmed_core.upload_tokens (
    id           BIGSERIAL PRIMARY KEY,
    token        VARCHAR(64) NOT NULL UNIQUE,
    token_type   VARCHAR(20) NOT NULL CHECK (token_type IN ('lodge_slip','receipt')),
    reference_id BIGINT,
    staff_id     BIGINT NOT NULL REFERENCES hearmed_reference.staff(id),
    file_url     VARCHAR(500),
    status       VARCHAR(20) NOT NULL DEFAULT 'pending'
                 CHECK (status IN ('pending','uploaded','expired')),
    expires_at   TIMESTAMPTZ NOT NULL,
    created_at   TIMESTAMPTZ DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_upload_tokens_token ON hearmed_core.upload_tokens(token);
CREATE INDEX IF NOT EXISTS idx_upload_tokens_status ON hearmed_core.upload_tokens(status);
