-- ============================================================
-- HearMed Portal — Complete Database Schema
-- Database: railway (PostgreSQL 17.7 on Railway)
-- Extracted from: new_db_postgres.sql (pg_dump custom format)
-- 4 Schemas | 60 Tables | 4 Functions | 120+ Indexes | 70+ FK Constraints
-- Status: ALL TABLES EMPTY — zero rows of data
-- ============================================================

-- ============================================================
-- SCHEMAS
-- ============================================================
CREATE SCHEMA hearmed_admin;        -- Admin, audit, commissions, KPIs
CREATE SCHEMA hearmed_communication; -- Notifications, SMS, chat
CREATE SCHEMA hearmed_core;          -- Core operations: patients, appointments, orders, invoices
CREATE SCHEMA hearmed_reference;     -- Lookup/config: clinics, staff, products, services

CREATE EXTENSION IF NOT EXISTS "uuid-ossp" WITH SCHEMA public;

-- ============================================================
-- FUNCTIONS
-- ============================================================

-- Commission cut-off: nearest weekday to the 23rd
-- Saturday → Friday, Sunday → Monday, Weekday → that day
CREATE OR REPLACE FUNCTION public.hearmed_commission_cutoff(year integer, month integer)
RETURNS date LANGUAGE plpgsql IMMUTABLE AS $$
DECLARE
    the_23rd  DATE;
    dow       INT;
BEGIN
    the_23rd := MAKE_DATE(year, month, 23);
    dow       := EXTRACT(DOW FROM the_23rd)::INT;
    IF dow = 6 THEN
        RETURN the_23rd - INTERVAL '1 day';   -- Saturday → Friday
    ELSIF dow = 0 THEN
        RETURN the_23rd + INTERVAL '1 day';   -- Sunday → Monday
    ELSE
        RETURN the_23rd;
    END IF;
END;
$$;

-- Auto C-number generator: C-0001, C-0002, etc.
CREATE OR REPLACE FUNCTION public.hearmed_next_patient_number()
RETURNS character varying LANGUAGE plpgsql AS $_$
DECLARE
    next_num INTEGER;
BEGIN
    SELECT COALESCE(MAX(CAST(SUBSTRING(patient_number FROM 3) AS INTEGER)), 0) + 1
    INTO next_num
    FROM hearmed_core.patients
    WHERE patient_number ~ '^C-[0-9]+$';
    RETURN 'C-' || LPAD(next_num::TEXT, 4, '0');
END;
$_$;

-- Trigger: auto-set updated_at
CREATE OR REPLACE FUNCTION public.hearmed_set_updated_at()
RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    NEW.updated_at = NOW();
    RETURN NEW;
END;
$$;

-- Trigger: auto-set updated_at (alternate version)
CREATE OR REPLACE FUNCTION public.update_updated_at_column()
RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$;

-- ============================================================
-- SCHEMA: hearmed_admin
-- ============================================================

CREATE TABLE hearmed_admin.audit_log (
    id          bigint PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    user_id     bigint NOT NULL,                          -- wp_user_id
    action      character varying(50) NOT NULL,           -- 'view', 'create', 'update', 'delete', 'export', 'login'
    entity_type character varying(50) NOT NULL,           -- 'patient', 'invoice', 'order', etc.
    entity_id   bigint NOT NULL,
    details     jsonb,                                    -- Additional context
    ip_address  inet,
    user_agent  text,
    created_at  timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE hearmed_admin.commission_periods (
    id           bigint PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    period_start date NOT NULL,
    period_end   date NOT NULL,
    period_label character varying(30) NOT NULL,           -- e.g. "Jan 2026"
    is_finalised boolean DEFAULT false,
    created_at   timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at   timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE hearmed_admin.commission_rules (
    id            integer PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    role_type     character varying(50) NOT NULL,          -- 'dispenser', 'ca', 'reception'
    rule_type     character varying(20) NOT NULL,          -- 'tiered' or 'flat'
    bracket_from  numeric(10,2) DEFAULT 0 NOT NULL,
    bracket_to    numeric(10,2),                           -- NULL = no upper limit
    rate_pct      numeric(5,2) DEFAULT 0 NOT NULL,
    applies_to    character varying(50) DEFAULT 'hearing_aids' NOT NULL,
    clinic_scope  character varying(20) DEFAULT 'primary' NOT NULL,
    is_active     boolean DEFAULT true NOT NULL,
    created_at    timestamp with time zone DEFAULT now() NOT NULL,
    updated_at    timestamp with time zone DEFAULT now() NOT NULL
);

CREATE TABLE hearmed_admin.commission_entries (
    id              integer PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    period_id       integer NOT NULL REFERENCES hearmed_admin.commission_periods(id) ON DELETE CASCADE,
    staff_id        integer NOT NULL REFERENCES hearmed_reference.staff(id),
    patient_id      integer NOT NULL REFERENCES hearmed_core.patients(id),
    invoice_id      integer NOT NULL REFERENCES hearmed_core.invoices(id),
    invoice_item_id integer NOT NULL REFERENCES hearmed_core.invoice_items(id),
    product_name    character varying(200) NOT NULL,
    sale_amount     numeric(10,2) DEFAULT 0 NOT NULL,
    is_hearing_aid  boolean DEFAULT true NOT NULL,
    is_return       boolean DEFAULT false NOT NULL,
    credit_note_id  integer REFERENCES hearmed_core.credit_notes(id),
    return_deduction numeric(10,2) DEFAULT 0 NOT NULL,
    commission_rate numeric(5,2) DEFAULT 0 NOT NULL,
    commission_amount numeric(10,2) DEFAULT 0 NOT NULL,
    net_commission  numeric(10,2) DEFAULT 0 NOT NULL,
    is_projected    boolean DEFAULT false NOT NULL,        -- awaiting fitting = projected
    is_locked       boolean DEFAULT false NOT NULL,        -- locked after payslip issued
    created_at      timestamp with time zone DEFAULT now() NOT NULL,
    updated_at      timestamp with time zone DEFAULT now() NOT NULL
);

CREATE TABLE hearmed_admin.gdpr_deletions (
    id           bigint PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    patient_id   bigint NOT NULL,
    requested_by bigint,
    requested_at timestamp without time zone,
    erased_by    bigint NOT NULL,
    erased_at    timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    confirmed_by bigint NOT NULL
);

CREATE TABLE hearmed_admin.gdpr_exports (
    id          bigint PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    patient_id  bigint NOT NULL,
    exported_by bigint NOT NULL,
    exported_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    export_type character varying(50) DEFAULT 'full',
    file_url    character varying(500)
);

CREATE TABLE hearmed_admin.gdpr_settings (
    id                           bigint PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    hm_privacy_policy_url        character varying(255),
    hm_retention_patient_years   integer,
    hm_retention_financial_years integer,
    hm_retention_sms_years       integer,
    hm_data_processors           text,
    created_at                   timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at                   timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE hearmed_admin.kpi_targets (
    id           bigint PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    target_name  character varying(50) NOT NULL,
    target_value numeric(10,2) DEFAULT 0.00,
    target_unit  character varying(5) DEFAULT '%',
    is_active    boolean DEFAULT true,
    updated_by   bigint,
    created_at   timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at   timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- SCHEMA: hearmed_communication
-- ============================================================

CREATE TABLE hearmed_communication.chat_channels (
    id           integer PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    channel_type character varying(20) DEFAULT 'dm' NOT NULL,  -- 'company', 'dm', 'group'
    channel_name character varying(200),                        -- NULL for DMs
    created_by   integer NOT NULL,
    is_active    boolean DEFAULT true NOT NULL,
    created_at   timestamp with time zone DEFAULT now() NOT NULL,
    updated_at   timestamp with time zone DEFAULT now() NOT NULL
);

CREATE TABLE hearmed_communication.chat_channel_members (
    channel_id   integer NOT NULL REFERENCES hearmed_communication.chat_channels(id) ON DELETE CASCADE,
    wp_user_id   integer NOT NULL,
    joined_at    timestamp with time zone DEFAULT now() NOT NULL,
    last_read_at timestamp with time zone,
    PRIMARY KEY (channel_id, wp_user_id)
);

CREATE TABLE hearmed_communication.chat_messages (
    id         integer PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    channel_id integer NOT NULL REFERENCES hearmed_communication.chat_channels(id) ON DELETE CASCADE,
    sender_id  integer NOT NULL,                               -- wp_user_id
    message    text NOT NULL,
    is_edited  boolean DEFAULT false NOT NULL,
    edited_at  timestamp with time zone,
    is_deleted boolean DEFAULT false NOT NULL,                 -- SOFT DELETE ONLY — never hard delete
    deleted_at timestamp with time zone,
    created_at timestamp with time zone DEFAULT now() NOT NULL
);

CREATE TABLE hearmed_communication.internal_notifications (
    id                  bigint PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    notification_type   character varying(50) NOT NULL,
    subject             character varying(200) NOT NULL,
    message             text NOT NULL,
    created_by          bigint REFERENCES hearmed_reference.staff(id),
    created_at          timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    priority            character varying(20) DEFAULT 'Normal',
    related_entity_type character varying(50),
    related_entity_id   bigint,
    expires_at          timestamp without time zone,
    is_active           boolean DEFAULT true
);

CREATE TABLE hearmed_communication.notification_actions (
    id              bigint PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    notification_id bigint REFERENCES hearmed_communication.internal_notifications(id) ON DELETE CASCADE,
    action_label    character varying(100) NOT NULL,
    action_type     character varying(20) NOT NULL,
    action_target   text,
    action_order    integer DEFAULT 1,
    created_at      timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE hearmed_communication.notification_recipients (
    id              bigint PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    notification_id bigint REFERENCES hearmed_communication.internal_notifications(id) ON DELETE CASCADE,
    recipient_type  character varying(20) NOT NULL,
    recipient_id    bigint,
    recipient_role  character varying(50),
    is_read         boolean DEFAULT false,
    read_at         timestamp without time zone,
    dismissed_at    timestamp without time zone,
    created_at      timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE hearmed_communication.notifications (
    id                    bigint PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    patient_id            bigint NOT NULL,
    notification_type     character varying(50) NOT NULL,
    message               text NOT NULL,
    scheduled_date        timestamp without time zone NOT NULL,
    sent_date             timestamp without time zone,
    notification_status   character varying(20) DEFAULT 'Pending',
    delivery_method       character varying(20) DEFAULT 'Email',
    appointment_id        bigint,
    invoice_id            bigint,
    created_by            bigint,
    created_at            timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at            timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE hearmed_communication.sms_messages (
    id                  bigint PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    patient_id          bigint NOT NULL,
    phone_number        character varying(20) NOT NULL,
    message             text NOT NULL,
    sms_status          character varying(20) DEFAULT 'Sent',
    sent_at             timestamp without time zone,
    delivered_at        timestamp without time zone,
    provider_message_id character varying(100),
    cost                numeric(5,2) DEFAULT 0.00,
    created_by          bigint,
    created_at          timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at          timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE hearmed_communication.sms_templates (
    id               bigint PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    template_name    character varying(100) NOT NULL,
    template_content text NOT NULL,
    category         character varying(50),
    is_active        boolean DEFAULT true,
    placeholders     jsonb,
    created_at       timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at       timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- SCHEMA: hearmed_core
-- ============================================================

CREATE TABLE hearmed_core.patients (
    id                    integer PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    patient_number        character varying(20) NOT NULL UNIQUE,
    patient_title         character varying(10),
    first_name            character varying(100) NOT NULL,
    last_name             character varying(100) NOT NULL,
    date_of_birth         date,
    gender                character varying(20),
    phone                 character varying(30),
    mobile                character varying(30),
    email                 character varying(200),
    address_line1         character varying(200),
    address_line2         character varying(200),
    city                  character varying(100),
    county                character varying(100),
    eircode               character varying(10),
    assigned_clinic_id    integer REFERENCES hearmed_reference.clinics(id) ON DELETE SET NULL,
    assigned_dispenser_id integer REFERENCES hearmed_reference.staff(id) ON DELETE SET NULL,
    prsi_eligible         boolean DEFAULT false NOT NULL,
    prsi_number           character varying(20),
    medical_card_number   character varying(20),
    referral_source_id    integer REFERENCES hearmed_reference.referral_sources(id) ON DELETE SET NULL,
    referral_sub_source_id integer REFERENCES hearmed_reference.referral_sources(id) ON DELETE SET NULL,
    referral_notes        text,
    marketing_email       boolean DEFAULT false NOT NULL,
    marketing_sms         boolean DEFAULT false NOT NULL,
    marketing_phone       boolean DEFAULT false NOT NULL,
    gdpr_consent          boolean DEFAULT false NOT NULL,
    gdpr_consent_date     timestamp with time zone,
    gdpr_consent_version  character varying(20),
    gdpr_consent_ip       character varying(45),
    is_active             boolean DEFAULT true NOT NULL,
    virtual_servicing     boolean DEFAULT false NOT NULL,
    is_deceased           boolean DEFAULT false NOT NULL,
    deceased_date         date,
    annual_review_date    date,
    last_test_date        date,
    created_at            timestamp with time zone DEFAULT now() NOT NULL,
    updated_at            timestamp with time zone DEFAULT now() NOT NULL,
    created_by            integer,
    updated_by            integer
);

CREATE TABLE hearmed_core.appointments (
    id                   bigint PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    patient_id           bigint NOT NULL,
    staff_id             bigint REFERENCES hearmed_reference.staff(id),
    clinic_id            bigint REFERENCES hearmed_reference.clinics(id),
    service_id           bigint REFERENCES hearmed_reference.services(id),
    appointment_type_id  bigint REFERENCES hearmed_reference.appointment_types(id),
    appointment_date     date NOT NULL,
    start_time           time without time zone NOT NULL,
    end_time             time without time zone NOT NULL,
    duration_minutes     integer DEFAULT 30,
    appointment_status   character varying(50) DEFAULT 'Confirmed',
    location_type        character varying(20) DEFAULT 'Clinic',
    referring_source     character varying(100),
    referral_document_url character varying(500),
    notes                text,
    internal_notes       text,
    outcome              character varying(255),
    created_by           bigint,
    created_at           timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at           timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE hearmed_core.appointment_outcomes (
    id                  bigint PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    appointment_id      bigint REFERENCES hearmed_core.appointments(id) ON DELETE CASCADE,
    patient_id          bigint NOT NULL,
    clinic_id           bigint REFERENCES hearmed_reference.clinics(id),
    staff_id            bigint REFERENCES hearmed_reference.staff(id),
    outcome_date        date NOT NULL,
    service_type        character varying(100),
    outcome_name        character varying(100) NOT NULL,
    outcome_color       character varying(10),
    is_invoiceable      boolean DEFAULT false,
    requires_note       boolean DEFAULT false,
    triggers_followup   boolean DEFAULT false,
    followup_service_ids jsonb,
    tns_reason          character varying(100),
    not_tested_reason   character varying(100),
    medical_notes       text,
    binaural            boolean DEFAULT false,
    invoice_id          bigint,
    skip_reason         text,
    created_by          bigint,
    created_at          timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at          timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE hearmed_core.calendar_settings (
    id                     bigint PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    start_time             time without time zone DEFAULT '09:00:00',
    end_time               time without time zone DEFAULT '18:00:00',
    time_interval_minutes  integer DEFAULT 30,
    slot_height            character varying(20) DEFAULT 'regular',
    default_view           character varying(10) DEFAULT 'week',
    default_mode           character varying(20) DEFAULT 'people',
    show_time_inline       boolean DEFAULT false,
    hide_end_time          boolean DEFAULT true,
    outcome_style          character varying(20) DEFAULT 'default',
    require_cancel_reason  boolean DEFAULT true,
    hide_cancelled         boolean DEFAULT true,
    require_reschedule_note boolean DEFAULT false,
    apply_clinic_colour    boolean DEFAULT false
);

CREATE TABLE hearmed_core.calendar_blockouts (
    id         bigint PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    service_id bigint REFERENCES hearmed_reference.services(id),
    staff_id   bigint REFERENCES hearmed_reference.staff(id),
    start_date date NOT NULL,
    end_date   date NOT NULL,
    start_time time without time zone DEFAULT '09:00:00',
    end_time   time without time zone DEFAULT '17:00:00',
    created_by bigint,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE hearmed_core.orders (
    id                   bigint PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    order_number         character varying(20) NOT NULL UNIQUE,
    patient_id           bigint NOT NULL,
    staff_id             bigint REFERENCES hearmed_reference.staff(id),
    clinic_id            bigint REFERENCES hearmed_reference.clinics(id),
    order_date           date NOT NULL,
    current_status       character varying(30) DEFAULT 'Awaiting Approval',
    subtotal             numeric(10,2) DEFAULT 0.00,
    discount_total       numeric(10,2) DEFAULT 0.00,
    vat_total            numeric(10,2) DEFAULT 0.00,
    grand_total          numeric(10,2) DEFAULT 0.00,
    gross_margin_percent numeric(5,2) DEFAULT 0.00,
    prsi_applicable      boolean DEFAULT false,
    prsi_amount          numeric(10,2) DEFAULT 0.00,
    warranty_months      integer DEFAULT 0,
    invoice_id           bigint,
    is_flagged           boolean DEFAULT false,
    flag_reason          text,
    notes                text,
    created_by           bigint,
    created_at           timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at           timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE hearmed_core.order_items (
    id               bigint PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    order_id         bigint REFERENCES hearmed_core.orders(id) ON DELETE CASCADE,
    line_number      integer NOT NULL,
    item_type        character varying(50) NOT NULL,
    item_id          bigint,
    item_description character varying(255),
    ear_side         character varying(10),
    quantity         integer DEFAULT 1,
    unit_cost_price  numeric(10,2),
    unit_retail_price numeric(10,2),
    discount_percent numeric(5,2) DEFAULT 0,
    discount_amount  numeric(10,2) DEFAULT 0,
    line_total       numeric(10,2),
    notes            text,
    created_at       timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE hearmed_core.order_shipments (
    id                    bigint PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    order_id              bigint REFERENCES hearmed_core.orders(id) ON DELETE CASCADE,
    shipped_from          character varying(200),
    shipped_date          date,
    tracking_number       character varying(100),
    carrier               character varying(100),
    expected_delivery_date date,
    actual_delivery_date  date,
    received_by           bigint REFERENCES hearmed_reference.staff(id),
    received_at_clinic_id bigint REFERENCES hearmed_reference.clinics(id),
    notes                 text,
    created_at            timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at            timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE hearmed_core.order_status_history (
    id          bigint PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    order_id    bigint REFERENCES hearmed_core.orders(id) ON DELETE CASCADE,
    from_status character varying(30),
    to_status   character varying(30) NOT NULL,
    changed_by  bigint REFERENCES hearmed_reference.staff(id),
    changed_at  timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    notes       text,
    ip_address  inet
);

CREATE TABLE hearmed_core.invoices (
    id                      bigint PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    invoice_number          character varying(20) NOT NULL UNIQUE,
    patient_id              bigint NOT NULL,
    order_id                bigint REFERENCES hearmed_core.orders(id),
    staff_id                bigint REFERENCES hearmed_reference.staff(id),
    clinic_id               bigint REFERENCES hearmed_reference.clinics(id),
    invoice_date            date NOT NULL,
    subtotal                numeric(10,2) DEFAULT 0.00,
    discount_total          numeric(10,2) DEFAULT 0.00,
    vat_total               numeric(10,2) DEFAULT 0.00,
    grand_total             numeric(10,2) DEFAULT 0.00,
    balance_remaining       numeric(10,2) DEFAULT 0.00,
    payment_status          character varying(20) DEFAULT 'Unpaid',
    pdf_url                 character varying(500),
    quickbooks_id           character varying(50),
    prsi_applicable         boolean DEFAULT false,
    prsi_amount             numeric(10,2) DEFAULT 0.00,
    prsi_document_received  boolean DEFAULT false,
    created_by              bigint,
    created_at              timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at              timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE hearmed_core.invoice_items (
    id               bigint PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    invoice_id       bigint REFERENCES hearmed_core.invoices(id) ON DELETE CASCADE,
    line_number      integer NOT NULL,
    item_type        character varying(50) NOT NULL,
    item_id          bigint,
    item_description character varying(255),
    ear_side         character varying(10),
    quantity         integer DEFAULT 1,
    unit_price       numeric(10,2),
    discount_percent numeric(5,2) DEFAULT 0,
    discount_amount  numeric(10,2) DEFAULT 0,
    vat_rate         numeric(5,2) DEFAULT 23,
    vat_amount       numeric(10,2),
    line_total       numeric(10,2),
    created_at       timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE hearmed_core.payments (
    id             bigint PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    invoice_id     bigint REFERENCES hearmed_core.invoices(id),
    patient_id     bigint NOT NULL,
    amount         numeric(10,2) NOT NULL,
    payment_date   date NOT NULL,
    payment_method character varying(30) DEFAULT 'Card',
    received_by    bigint REFERENCES hearmed_reference.staff(id),
    clinic_id      bigint REFERENCES hearmed_reference.clinics(id),
    quickbooks_id  character varying(50),
    is_refund      boolean DEFAULT false,
    refund_reason  text,
    created_by     bigint,
    created_at     timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at     timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE hearmed_core.credit_notes (
    id                   bigint PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    credit_note_number   character varying(20) NOT NULL UNIQUE,
    invoice_id           bigint REFERENCES hearmed_core.invoices(id),
    patient_id           bigint NOT NULL,
    order_id             bigint,
    amount               numeric(10,2) DEFAULT 0.00,
    reason               text,
    credit_date          date NOT NULL,
    quickbooks_id        character varying(50),
    pdf_url              character varying(500),
    cheque_sent          boolean DEFAULT false,
    cheque_sent_date     date,
    cheque_reminder_sent boolean DEFAULT false,
    created_by           bigint,
    created_at           timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at           timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE hearmed_core.financial_transactions (
    id               bigint PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    transaction_date date NOT NULL,
    transaction_type character varying(30) NOT NULL,
    amount           numeric(10,2) NOT NULL,
    description      text,
    debit_account    character varying(50),
    credit_account   character varying(50),
    reference_type   character varying(50),
    reference_id     bigint,
    created_by       bigint,
    created_at       timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at       timestamp without time zone DEFAULT CURRENT_TIMEZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE hearmed_core.fitting_queue (
    id                    bigint PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    patient_id            bigint NOT NULL,
    order_id              bigint REFERENCES hearmed_core.orders(id),
    invoice_id            bigint,
    clinic_id             bigint REFERENCES hearmed_reference.clinics(id),
    staff_id              bigint REFERENCES hearmed_reference.staff(id),
    product_description   character varying(255),
    total_price           numeric(10,2) DEFAULT 0.00,
    prsi_applicable       boolean DEFAULT false,
    fitting_appointment_id bigint,
    fitting_date          date,
    queue_status          character varying(30) DEFAULT 'Awaiting',
    pre_fit_cancel_reason text,
    pre_fit_cancelled_at  timestamp without time zone,
    created_by            bigint,
    created_at            timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at            timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE hearmed_core.repairs (
    id               bigint PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    patient_id       bigint NOT NULL,
    patient_device_id bigint REFERENCES hearmed_core.patient_devices(id),
    product_id       bigint REFERENCES hearmed_reference.products(id),
    serial_number    character varying(50),
    manufacturer_id  bigint REFERENCES hearmed_reference.manufacturers(id),
    clinic_id        bigint REFERENCES hearmed_reference.clinics(id),
    staff_id         bigint REFERENCES hearmed_reference.staff(id),
    date_booked      date NOT NULL,
    date_sent        date,
    date_received    date,
    received_by      bigint REFERENCES hearmed_reference.staff(id),
    repair_status    character varying(30) DEFAULT 'Booked',
    warranty_status  character varying(30) DEFAULT 'Unknown',
    repair_notes     text,
    created_by       bigint,
    created_at       timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at       timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE hearmed_core.patient_devices (
    id                bigint PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    patient_id        bigint NOT NULL,
    product_id        bigint REFERENCES hearmed_reference.products(id),
    serial_number_left  character varying(50),
    serial_number_right character varying(50),
    fitting_date      date,
    invoice_id        bigint,
    device_status     character varying(20) DEFAULT 'Active',
    inactive_reason   character varying(50),
    inactive_date     date,
    warranty_expiry   date,
    created_by        bigint,
    created_at        timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at        timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE hearmed_core.patient_notes (
    id             bigint PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    patient_id     bigint NOT NULL,
    note_type      character varying(50) DEFAULT 'Manual',
    note_text      text NOT NULL,
    appointment_id bigint REFERENCES hearmed_core.appointments(id),
    created_by     bigint,
    created_at     timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at     timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE hearmed_core.patient_forms (
    id                  bigint PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    patient_id          bigint NOT NULL,
    form_type           character varying(100),
    form_data           jsonb,
    signature_image_url character varying(500),
    gdpr_consent        boolean DEFAULT false,
    marketing_email     boolean DEFAULT false,
    marketing_phone     boolean DEFAULT false,
    marketing_sms       boolean DEFAULT false,
    created_by          bigint,
    created_at          timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at          timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE hearmed_core.patient_documents (
    id            bigint PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    patient_id    bigint NOT NULL,
    document_type character varying(100),
    file_url      character varying(500),
    file_name     character varying(255),
    created_by    bigint,
    created_at    timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at    timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE hearmed_core.outcome_templates (
    id                   bigint PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    service_id           bigint REFERENCES hearmed_reference.services(id),
    outcome_name         character varying(100) NOT NULL,
    outcome_color        character varying(10) DEFAULT '#cccccc',
    is_invoiceable       boolean DEFAULT false,
    requires_note        boolean DEFAULT false,
    triggers_followup    boolean DEFAULT false,
    followup_service_ids jsonb,
    created_at           timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at           timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE hearmed_core.cash_transactions (
    id               bigint PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    clinic_id        bigint REFERENCES hearmed_reference.clinics(id),
    transaction_date date NOT NULL,
    entry_type       character varying(30) NOT NULL,
    amount           numeric(10,2) DEFAULT 0.00,
    description      text,
    patient_id       bigint,
    invoice_id       bigint,
    logged_by        bigint REFERENCES hearmed_reference.staff(id),
    created_at       timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at       timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE hearmed_core.cash_drawer_readings (
    id           integer PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    clinic_id    integer NOT NULL REFERENCES hearmed_reference.clinics(id),
    staff_id     integer NOT NULL REFERENCES hearmed_reference.staff(id),
    reading_type character varying(10) NOT NULL,
    reading_date date DEFAULT CURRENT_DATE NOT NULL,
    amount       numeric(10,2) DEFAULT 0 NOT NULL,
    notes        text,
    created_at   timestamp with time zone DEFAULT now() NOT NULL
);

CREATE TABLE hearmed_core.till_reconciliations (
    id              bigint PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    staff_id        bigint REFERENCES hearmed_reference.staff(id),
    clinic_id       bigint REFERENCES hearmed_reference.clinics(id),
    till_date       date NOT NULL,
    opening_amount  numeric(10,2) DEFAULT 0.00,
    cash_in         numeric(10,2) DEFAULT 0.00,
    lodgement_amount numeric(10,2) DEFAULT 0.00,
    petty_cash_out  numeric(10,2) DEFAULT 0.00,
    closing_amount  numeric(10,2) DEFAULT 0.00,
    expected_amount numeric(10,2) DEFAULT 0.00,
    variance        numeric(10,2) DEFAULT 0.00,
    is_balanced     boolean DEFAULT false,
    logged_by       bigint REFERENCES hearmed_reference.staff(id),
    created_at      timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at      timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE hearmed_core.staff_absences (
    id         bigint PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    staff_id   bigint REFERENCES hearmed_reference.staff(id) ON DELETE CASCADE,
    start_date date NOT NULL,
    end_date   date NOT NULL,
    reason     character varying(255),
    repeats    character varying(20) DEFAULT 'no',
    created_by bigint,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- SCHEMA: hearmed_reference
-- ============================================================

CREATE TABLE hearmed_reference.clinics (
    id            bigint PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    clinic_name   character varying(200) NOT NULL,
    clinic_type   character varying(50) DEFAULT 'Clinic',
    address_line1 character varying(255),
    address_line2 character varying(255),
    city          character varying(100),
    county        character varying(100),
    postcode      character varying(20),
    country       character varying(100) DEFAULT 'Ireland',
    phone         character varying(50),
    email         character varying(100),
    opening_hours jsonb,
    clinic_color  character varying(10),
    is_active     boolean DEFAULT true,
    notes         text,
    created_at    timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at    timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE hearmed_reference.staff (
    id              bigint PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    wp_user_id      bigint UNIQUE,
    first_name      character varying(100) NOT NULL,
    last_name       character varying(100) NOT NULL,
    email           character varying(100) NOT NULL UNIQUE,
    phone           character varying(50),
    role            character varying(50) NOT NULL,
    employee_number character varying(50),
    qualifications  jsonb,
    hire_date       date,
    is_active       boolean DEFAULT true,
    photo_url       character varying(500),
    created_at      timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at      timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE hearmed_reference.staff_clinics (
    id                bigint PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    staff_id          bigint REFERENCES hearmed_reference.staff(id) ON DELETE CASCADE,
    clinic_id         bigint REFERENCES hearmed_reference.clinics(id) ON DELETE CASCADE,
    is_primary_clinic boolean DEFAULT false,
    start_date        date,
    created_at        timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (staff_id, clinic_id)
);

CREATE TABLE hearmed_reference.staff_qualifications (
    id                   bigint PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    staff_id             bigint REFERENCES hearmed_reference.staff(id) ON DELETE CASCADE,
    qualification_type   character varying(100) NOT NULL,
    certification_number character varying(100),
    issued_by            character varying(200),
    issue_date           date,
    expiry_date          date,
    document_url         character varying(500),
    created_at           timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at           timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE hearmed_reference.staff_groups (
    id          bigint PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    group_name  character varying(150) NOT NULL,
    description text,
    is_active   boolean DEFAULT true NOT NULL,
    created_at  timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at  timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE hearmed_reference.staff_group_members (
    id         bigint PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    group_id   bigint REFERENCES hearmed_reference.staff_groups(id) ON DELETE CASCADE,
    staff_id   bigint REFERENCES hearmed_reference.staff(id) ON DELETE CASCADE,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE hearmed_reference.hearmed_range (
    id             bigint PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    range_name     character varying(150) NOT NULL,
    price_total    numeric(10,2),
    price_ex_prsi  numeric(10,2),
    is_active      boolean DEFAULT true NOT NULL,
    created_at     timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at     timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE hearmed_reference.resources (
    id          bigint PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    title       character varying(200) NOT NULL,
    category    character varying(100),
    url         character varying(500),
    description text,
    sort_order  integer DEFAULT 0 NOT NULL,
    is_active   boolean DEFAULT true NOT NULL,
    created_at  timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at  timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE hearmed_reference.appointment_types (
    id                 bigint PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    type_name          character varying(100) NOT NULL,
    default_service_id bigint REFERENCES hearmed_reference.services(id),
    default_duration   integer DEFAULT 30,
    requires_referral  boolean DEFAULT false,
    is_active          boolean DEFAULT true,
    description        text,
    created_at         timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at         timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE hearmed_reference.services (
    id               bigint PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    service_name     character varying(200) NOT NULL,
    service_code     character varying(50),
    duration_minutes integer DEFAULT 30,
    default_price    numeric(10,2),
    is_invoiceable   boolean DEFAULT true,
    requires_outcome boolean DEFAULT true,
    service_color    character varying(10),
    is_active        boolean DEFAULT true,
    description      text,
    created_at       timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at       timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE hearmed_reference.service_prerequisites (
    id                    bigint PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    service_id            bigint REFERENCES hearmed_reference.services(id) ON DELETE CASCADE,
    required_qualification character varying(100),
    required_role         character varying(50),
    created_at            timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE hearmed_reference.products (
    id                bigint PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    manufacturer_id   bigint REFERENCES hearmed_reference.manufacturers(id),
    product_name      character varying(200) NOT NULL,
    product_code      character varying(50),
    category          character varying(50) NOT NULL,
    style             character varying(50),
    tech_level        character varying(50),
    features          jsonb,
    cost_price        numeric(10,2),
    retail_price      numeric(10,2),
    is_active         boolean DEFAULT true,
    discontinued_date date,
    created_at        timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at        timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE hearmed_reference.product_specifications (
    id                  bigint PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    product_id          bigint REFERENCES hearmed_reference.products(id) ON DELETE CASCADE,
    receiver_type       character varying(50),
    battery_type        character varying(50),
    battery_life_hours  integer,
    connectivity        character varying(100),
    waterproof_rating   character varying(20),
    color_options       jsonb,
    ear_orientation     character varying(20),
    max_fitting_range_db integer,
    created_at          timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at          timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE hearmed_reference.manufacturers (
    id             bigint PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    name           character varying(100) NOT NULL,
    country        character varying(100),
    website        character varying(255),
    support_phone  character varying(50),
    support_email  character varying(100),
    warranty_terms text,
    is_active      boolean DEFAULT true,
    notes          text,
    created_at     timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at     timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE hearmed_reference.payment_methods (
    id               integer PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    method_name      character varying(100) NOT NULL,
    qbo_account_id   character varying(50),
    qbo_account_name character varying(100),
    is_active        boolean DEFAULT true NOT NULL,
    is_prsi          boolean DEFAULT false NOT NULL,
    sort_order       integer DEFAULT 0 NOT NULL,
    created_at       timestamp with time zone DEFAULT now() NOT NULL,
    updated_at       timestamp with time zone DEFAULT now() NOT NULL
);

CREATE TABLE hearmed_reference.referral_sources (
    id          integer PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    source_name character varying(200) NOT NULL,
    parent_id   integer REFERENCES hearmed_reference.referral_sources(id) ON DELETE CASCADE,
    is_active   boolean DEFAULT true NOT NULL,
    sort_order  integer DEFAULT 0 NOT NULL,
    created_at  timestamp with time zone DEFAULT now() NOT NULL,
    updated_at  timestamp with time zone DEFAULT now() NOT NULL
);

CREATE TABLE hearmed_reference.audiometers (
    id                bigint PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    audiometer_name   character varying(200) NOT NULL,
    audiometer_make   character varying(100),
    audiometer_model  character varying(100),
    serial_number     character varying(100),
    calibration_date  date,
    clinic_id         bigint REFERENCES hearmed_reference.clinics(id) ON DELETE SET NULL,
    is_active         boolean DEFAULT true NOT NULL,
    notes             text,
    created_at        timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at        timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE hearmed_reference.coupled_items (
    id                  bigint PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    item_type           character varying(50) NOT NULL,
    item_name           character varying(200) NOT NULL,
    manufacturer_id     bigint REFERENCES hearmed_reference.manufacturers(id),
    compatible_products jsonb,
    cost_price          numeric(10,2),
    retail_price        numeric(10,2),
    is_active           boolean DEFAULT true,
    notes               text,
    created_at          timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at          timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE hearmed_reference.domes (
    id              bigint PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    coupled_item_id bigint REFERENCES hearmed_reference.coupled_items(id) ON DELETE CASCADE,
    dome_type       character varying(50),
    dome_size       character varying(10),
    vent_size       character varying(20),
    material        character varying(50),
    compatible_with jsonb,
    created_at      timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at      timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE hearmed_reference.speakers (
    id              bigint PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    coupled_item_id bigint REFERENCES hearmed_reference.coupled_items(id) ON DELETE CASCADE,
    speaker_size    character varying(10),
    power_level     character varying(50),
    impedance_ohms  numeric(5,2),
    max_output_db   integer,
    compatible_with jsonb,
    color_code      character varying(50),
    created_at      timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at      timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE hearmed_reference.inventory_stock (
    id                bigint PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    item_type         character varying(50) NOT NULL,
    item_id           bigint NOT NULL,
    clinic_id         bigint REFERENCES hearmed_reference.clinics(id),
    quantity_on_hand  integer DEFAULT 0,
    quantity_reserved integer DEFAULT 0,
    reorder_level     integer DEFAULT 5,
    reorder_quantity  integer DEFAULT 10,
    last_counted_date date,
    created_at        timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at        timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (item_type, item_id, clinic_id)
);

CREATE TABLE hearmed_reference.stock_movements (
    id                 bigint PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    inventory_stock_id bigint REFERENCES hearmed_reference.inventory_stock(id),
    movement_type      character varying(50) NOT NULL,
    quantity           integer NOT NULL,
    from_clinic_id     bigint REFERENCES hearmed_reference.clinics(id),
    to_clinic_id       bigint REFERENCES hearmed_reference.clinics(id),
    reference_type     character varying(50),
    reference_id       bigint,
    movement_date      date NOT NULL,
    created_by         bigint,
    notes              text,
    created_at         timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- SUMMARY
-- ============================================================
-- Database:   railway
-- Schemas:    4 (hearmed_admin, hearmed_communication, hearmed_core, hearmed_reference)
-- Tables:     60
-- Functions:  4 (hearmed_commission_cutoff, hearmed_next_patient_number,
--               hearmed_set_updated_at, update_updated_at_column)
-- Indexes:    120+ (all btree)
-- FK Constraints: 70+
-- CHECK Constraints: 4 (payments, appointments, fitting_queue, repairs)
-- Data:       ZERO ROWS — all tables empty
-- ============================================================
