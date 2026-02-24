-- Add staff auth and dispenser schedules tables

CREATE TABLE IF NOT EXISTS hearmed_reference.staff_auth (
    id                  bigint PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    staff_id            bigint UNIQUE REFERENCES hearmed_reference.staff(id) ON DELETE CASCADE,
    username            character varying(150) NOT NULL UNIQUE,
    password_hash       text NOT NULL,
    temp_password       boolean DEFAULT true,
    two_factor_enabled  boolean DEFAULT false,
    totp_secret         character varying(64),
    last_login          timestamp without time zone,
    created_at          timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at          timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS hearmed_reference.dispenser_schedules (
    id             bigint PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    staff_id       bigint REFERENCES hearmed_reference.staff(id) ON DELETE CASCADE,
    clinic_id      bigint REFERENCES hearmed_reference.clinics(id) ON DELETE CASCADE,
    day_of_week    smallint NOT NULL,
    rotation_weeks smallint DEFAULT 1 NOT NULL,
    week_number    smallint DEFAULT 1 NOT NULL,
    is_active      boolean DEFAULT true NOT NULL,
    created_at     timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at     timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (staff_id, clinic_id, day_of_week, rotation_weeks, week_number)
);
