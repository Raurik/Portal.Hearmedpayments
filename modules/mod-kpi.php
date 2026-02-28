<?php
/**
 * HearMed KPI Dashboard
 * 
 * Role-specific performance dashboard with real-time metrics from
 * invoices, orders, appointments, and commission tables.
 *
 * Shortcode: [hearmed_kpi]
 * Security: Staff see own data only. c_level + administrator see all.
 *
 * @package HearMed_Portal
 * @since   5.3.0
 */
if (!defined('ABSPATH')) exit;

/* ═══════════════════════════════════════════════════════════
   STANDALONE RENDER (called by router)
   ═══════════════════════════════════════════════════════════ */
function hm_kpi_render() {
    if (!is_user_logged_in()) return;

    $uid      = get_current_user_id();
    $staff    = HearMed_DB::get_row(
        "SELECT s.id, s.first_name, s.last_name, s.role, s.base_salary,
                sc.clinic_id AS primary_clinic_id,
                c.clinic_name AS primary_clinic_name
         FROM hearmed_reference.staff s
         LEFT JOIN hearmed_reference.staff_clinics sc 
              ON sc.staff_id = s.id AND sc.is_primary_clinic = true
         LEFT JOIN hearmed_reference.clinics c ON c.id = sc.clinic_id
         WHERE s.wp_user_id = $1 AND s.is_active = true
         LIMIT 1",
        [$uid]
    );
    if (!$staff) { echo '<p>Staff record not found.</p>'; return; }

    $is_admin = in_array($staff->role, ['c_level', 'administrator']);

    // Check if staff has a commission PIN set
    $has_pin = (bool) HearMed_DB::get_var(
        "SELECT commission_pin FROM hearmed_reference.staff_auth WHERE staff_id = $1",
        [(int) $staff->id]
    );

    // For admin view: get all active dispensers + CA + reception
    $all_staff = [];
    if ($is_admin) {
        $all_staff = HearMed_DB::get_results(
            "SELECT s.id, s.first_name, s.last_name, s.role,
                    c.clinic_name
             FROM hearmed_reference.staff s
             LEFT JOIN hearmed_reference.staff_clinics sc 
                  ON sc.staff_id = s.id AND sc.is_primary_clinic = true
             LEFT JOIN hearmed_reference.clinics c ON c.id = sc.clinic_id
             WHERE s.is_active = true
             AND s.role IN ('dispenser','audiologist','clinical_assistant','reception','Dispenser','Audiologist')
             ORDER BY s.role, s.first_name"
        ) ?: [];
    }

    // Current period
    $now = new DateTime('now', new DateTimeZone('Europe/Dublin'));
    $period_start = $now->format('Y-m-01');
    $period_end   = $now->format('Y-m-t');
    $period_label = $now->format('F Y');
    $day_of_month = (int) $now->format('j');
    $days_in_month = (int) $now->format('t');

    ?>
    <style>
    /* ═══ KPI Dashboard Styles ═══ */
    :root {
        --kpi-navy: #151B33;
        --kpi-teal: #0BB4C4;
        --kpi-teal-hover: #0a9eac;
        --kpi-green: #059669;
        --kpi-green-bg: #ECFDF5;
        --kpi-red: #DC2626;
        --kpi-red-bg: #FEF2F2;
        --kpi-amber: #D97706;
        --kpi-amber-bg: #FFFBEB;
        --kpi-g900: #111827;
        --kpi-g700: #374151;
        --kpi-g500: #6B7280;
        --kpi-g400: #9CA3AF;
        --kpi-g300: #D1D5DB;
        --kpi-g200: #E5E7EB;
        --kpi-g100: #F3F4F6;
        --kpi-g50: #F9FAFB;
        --kpi-mono: 'JetBrains Mono', 'SF Mono', 'Consolas', monospace;
        --kpi-body: 'Outfit', system-ui, sans-serif;
        --kpi-shadow: 0 1px 3px rgba(0,0,0,0.04);
    }
    @import url('https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&display=swap');
    @import url('https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600;700&display=swap');

    #hm-kpi { font-family: var(--kpi-body); -webkit-font-smoothing: antialiased; max-width: 1400px; margin: 0 auto; padding: 0 16px; }
    #hm-kpi * { box-sizing: border-box; margin: 0; padding: 0; }

    /* Layout */
    .kpi-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 28px; padding-bottom: 20px; border-bottom: 1px solid var(--kpi-g200); }
    .kpi-header__left { display: flex; align-items: center; gap: 14px; }
    .kpi-avatar { width: 36px; height: 36px; border-radius: 10px; background: var(--kpi-navy); display: flex; align-items: center; justify-content: center; font-family: var(--kpi-mono); font-size: 12px; font-weight: 700; color: var(--kpi-teal); }
    .kpi-header__name { font-size: 15px; font-weight: 700; color: var(--kpi-g900); letter-spacing: -0.02em; }
    .kpi-header__meta { font-size: 11px; color: var(--kpi-g400); margin-top: 1px; }
    .kpi-header__controls { display: flex; gap: 8px; align-items: center; }
    .kpi-select { padding: 6px 12px; border: 1px solid var(--kpi-g200); border-radius: 8px; font-family: var(--kpi-body); font-size: 12px; color: var(--kpi-g700); background: #fff; cursor: pointer; outline: none; }
    .kpi-select:focus { border-color: var(--kpi-teal); }
    .kpi-period-nav { display: flex; gap: 4px; align-items: center; }
    .kpi-period-btn { width: 28px; height: 28px; border-radius: 6px; border: 1px solid var(--kpi-g200); background: #fff; display: flex; align-items: center; justify-content: center; cursor: pointer; color: var(--kpi-g500); font-size: 14px; }
    .kpi-period-btn:hover { background: var(--kpi-g50); }
    .kpi-period-label { font-size: 13px; font-weight: 600; color: var(--kpi-g700); padding: 0 8px; min-width: 120px; text-align: center; }

    /* Cards */
    .kpi-card { background: #fff; border-radius: 12px; box-shadow: var(--kpi-shadow); overflow: hidden; }
    .kpi-label { font-size: 10px; font-weight: 500; color: var(--kpi-g400); text-transform: uppercase; letter-spacing: 0.08em; }
    .kpi-num { font-family: var(--kpi-mono); font-variant-numeric: tabular-nums; letter-spacing: -0.02em; line-height: 1.1; color: var(--kpi-g900); }

    /* Grid layouts */
    .kpi-row { display: grid; gap: 20px; margin-bottom: 24px; }
    .kpi-row--hero { grid-template-columns: 1fr; }
    .kpi-row--6 { grid-template-columns: repeat(3, 1fr); gap: 16px; background: transparent; border-radius: 0; overflow: visible; }
    .kpi-row--6 .kpi-card { border-radius: 12px; box-shadow: var(--kpi-shadow); }
    .kpi-row--2 { grid-template-columns: 1fr 1fr; }
    .kpi-row--split { grid-template-columns: 3fr 2fr; }

    /* Progress bar */
    .kpi-bar { height: 3px; background: var(--kpi-g100); border-radius: 99px; overflow: hidden; }
    .kpi-bar__fill { height: 100%; border-radius: 99px; transition: width 1.2s cubic-bezier(0.16,1,0.3,1); }

    /* Revenue hero */
    .kpi-revenue { padding: 32px 36px; }
    .kpi-revenue__top { display: flex; justify-content: space-between; align-items: flex-start; }
    .kpi-revenue__amount { font-family: var(--kpi-mono); font-size: 40px; font-weight: 700; color: var(--kpi-g900); letter-spacing: -0.03em; line-height: 1; margin-top: 8px; }
    .kpi-revenue__stats { display: flex; align-items: center; gap: 16px; margin-top: 14px; font-size: 13px; color: var(--kpi-g500); }
    .kpi-revenue__stats strong { font-weight: 600; }
    .kpi-revenue__sep { width: 1px; height: 14px; background: var(--kpi-g200); }
    .kpi-revenue__track { margin-top: 20px; }
    .kpi-revenue__track-ends { display: flex; justify-content: space-between; margin-top: 6px; font-family: var(--kpi-mono); font-size: 10px; color: var(--kpi-g400); }

    /* KPI metric card */
    .kpi-metric { padding: 24px 22px; background: #fff; min-height: 140px; }
    .kpi-metric__top { display: flex; justify-content: space-between; align-items: flex-end; }
    .kpi-metric__value { font-family: var(--kpi-mono); font-size: 24px; font-weight: 700; color: var(--kpi-g900); line-height: 1; }
    .kpi-metric__bar { margin-top: 12px; }
    .kpi-metric__footer { display: flex; justify-content: space-between; align-items: center; margin-top: 12px; font-size: 11px; }
    .kpi-metric__target { color: var(--kpi-g400); }
    .kpi-metric__status { font-weight: 600; display: flex; align-items: center; gap: 3px; }
    .kpi-metric__status--hit { color: var(--kpi-green); }
    .kpi-metric__status--miss { font-family: var(--kpi-mono); }
    .kpi-metric--hit { border-top: 2px solid var(--kpi-green); }

    /* Sparkline */
    .kpi-spark { display: block; }

    /* Ring gauge */
    .kpi-ring { position: relative; display: inline-block; }
    .kpi-ring__label { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; }
    .kpi-ring__pct { font-family: var(--kpi-mono); font-weight: 700; }
    .kpi-ring__pct small { font-size: 0.7em; color: var(--kpi-g400); }

    /* Appointment / Order summary */
    .kpi-summary { padding: 28px 28px; }
    .kpi-summary__ring { display: flex; align-items: center; gap: 24px; margin-top: 16px; }
    .kpi-summary__list { flex: 1; }
    .kpi-summary__item { display: flex; justify-content: space-between; align-items: center; padding: 6px 0; font-size: 13px; }
    .kpi-summary__dot { width: 6px; height: 6px; border-radius: 50%; display: inline-block; }
    .kpi-summary__item-label { display: flex; align-items: center; gap: 6px; color: var(--kpi-g500); }
    .kpi-summary__item-val { font-family: var(--kpi-mono); font-size: 13px; font-weight: 600; color: var(--kpi-g900); }
    .kpi-orders__grid { display: flex; margin-bottom: 20px; padding: 12px 0; }
    .kpi-orders__col { flex: 1; text-align: center; border-right: 1px solid var(--kpi-g100); padding: 8px 0; }
    .kpi-orders__col:last-child { border-right: none; }
    .kpi-orders__num { font-family: var(--kpi-mono); font-size: 26px; font-weight: 700; }
    .kpi-orders__label { font-size: 12px; color: var(--kpi-g400); margin-top: 4px; }
    .kpi-orders__tags { display: flex; gap: 12px; margin-top: 8px; }
    .kpi-tag { flex: 1; padding: 14px 16px; border-radius: 10px; }
    .kpi-tag__title { font-size: 10px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; }
    .kpi-tag__val { font-family: var(--kpi-mono); font-size: 14px; font-weight: 600; color: var(--kpi-g900); margin-top: 4px; }
    .kpi-tag__val span { color: var(--kpi-g400); font-weight: 400; }
    .kpi-tag--pipeline { background: var(--kpi-g50); border: 1px solid var(--kpi-g200); }
    .kpi-tag--pipeline .kpi-tag__title { color: var(--kpi-g400); }
    .kpi-tag--return { background: var(--kpi-red-bg); border: 1px solid #FECACA; }
    .kpi-tag--return .kpi-tag__title { color: var(--kpi-red); }

    /* Activity feed */
    .kpi-feed { padding: 28px; }
    .kpi-feed__item { display: flex; align-items: center; gap: 14px; padding: 12px 0; }
    .kpi-feed__item + .kpi-feed__item { border-top: 1px solid var(--kpi-g100); }
    .kpi-feed__pill { font-size: 10px; font-weight: 600; padding: 3px 8px; border-radius: 4px; min-width: 48px; text-align: center; letter-spacing: 0.01em; }
    .kpi-feed__text { flex: 1; min-width: 0; }
    .kpi-feed__main { font-size: 13px; font-weight: 500; color: var(--kpi-g900); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .kpi-feed__sub { font-size: 12px; color: var(--kpi-g400); margin-top: 1px; }
    .kpi-feed__right { text-align: right; flex-shrink: 0; }
    .kpi-feed__amt { font-family: var(--kpi-mono); font-size: 13px; font-weight: 600; }
    .kpi-feed__time { font-size: 11px; color: var(--kpi-g400); margin-top: 1px; }

    /* Commission vault */
    .kpi-vault__header { padding: 14px 24px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--kpi-g100); }
    .kpi-vault__header-left { display: flex; align-items: center; gap: 8px; }
    .kpi-vault__title { font-size: 11px; font-weight: 600; color: var(--kpi-g900); text-transform: uppercase; letter-spacing: 0.06em; }
    .kpi-vault__body { position: relative; min-height: 220px; }
    .kpi-vault__content { padding: 28px; transition: all 0.5s cubic-bezier(0.16,1,0.3,1); }
    .kpi-vault__content--locked { filter: blur(16px); opacity: 0.6; user-select: none; pointer-events: none; }
    .kpi-vault__overlay { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; background: rgba(255,255,255,0.2); z-index: 2; }
    .kpi-vault__prompt { text-align: center; cursor: pointer; }
    .kpi-vault__icon { width: 56px; height: 56px; border-radius: 16px; background: #fff; border: 1px solid var(--kpi-g200); display: flex; align-items: center; justify-content: center; margin: 0 auto; box-shadow: 0 4px 16px rgba(0,0,0,0.08); }
    .kpi-vault__prompt-text { font-size: 13px; font-weight: 600; color: var(--kpi-g900); margin-top: 12px; }
    .kpi-vault__prompt-sub { font-size: 12px; color: var(--kpi-g400); margin-top: 2px; }
    .kpi-vault__pin-box { text-align: center; padding: 28px 32px; border-radius: 16px; background: #fff; border: 1px solid var(--kpi-g200); box-shadow: 0 8px 32px rgba(0,0,0,0.12); }
    .kpi-vault__pin-input { width: 110px; padding: 10px 14px; border-radius: 8px; border: 1px solid var(--kpi-g200); background: var(--kpi-g50); font-size: 18px; text-align: center; letter-spacing: 0.3em; outline: none; font-family: var(--kpi-mono); color: var(--kpi-g900); }
    .kpi-vault__pin-input--error { border-color: var(--kpi-red); }
    .kpi-vault__pin-btn { padding: 10px 20px; border-radius: 8px; border: none; background: var(--kpi-navy); color: #fff; font-size: 13px; font-weight: 600; cursor: pointer; font-family: var(--kpi-body); }
    .kpi-vault__error { font-size: 12px; color: var(--kpi-red); margin-top: 10px; font-weight: 500; }
    .kpi-vault__cancel { margin-top: 12px; font-size: 12px; color: var(--kpi-g400); background: none; border: none; cursor: pointer; text-decoration: underline; text-underline-offset: 2px; }
    .kpi-vault__lock-btn { font-size: 11px; font-weight: 500; color: var(--kpi-g400); background: none; border: 1px solid var(--kpi-g200); border-radius: 6px; padding: 4px 12px; cursor: pointer; }
    .kpi-vault__set-pin { display: flex; gap: 8px; margin-top: 12px; }
    .kpi-vault__set-pin input { flex: 1; padding: 8px 12px; border: 1px solid var(--kpi-g200); border-radius: 6px; font-family: var(--kpi-mono); font-size: 14px; text-align: center; letter-spacing: 0.2em; }

    /* Commission summary boxes */
    .kpi-comm__grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 24px; }
    .kpi-comm__box { padding: 20px; border-radius: 10px; }
    .kpi-comm__box--current { background: var(--kpi-g50); border: 1px solid var(--kpi-g200); }
    .kpi-comm__box--projected { background: #F0FDFA; border: 1px solid #CCFBF1; }
    .kpi-comm__amount { font-family: var(--kpi-mono); font-size: 32px; font-weight: 700; color: var(--kpi-g900); margin-top: 8px; letter-spacing: -0.02em; }
    .kpi-comm__detail { font-size: 12px; color: var(--kpi-g500); margin-top: 8px; line-height: 1.7; }

    /* Commission table */
    .kpi-comm__table-wrap { border: 1px solid var(--kpi-g200); border-radius: 8px; overflow: hidden; }
    .kpi-comm__table { width: 100%; border-collapse: collapse; }
    .kpi-comm__table th { padding: 10px 16px; text-align: left; font-size: 10px; font-weight: 600; color: var(--kpi-g400); text-transform: uppercase; letter-spacing: 0.06em; border-bottom: 1px solid var(--kpi-g200); background: var(--kpi-g50); }
    .kpi-comm__table td { padding: 12px 16px; font-size: 13px; border-bottom: 1px solid var(--kpi-g100); }
    .kpi-comm__table tfoot td { background: var(--kpi-g50); border-top: 2px solid var(--kpi-g200); font-weight: 700; }
    .kpi-comm__bar { margin-top: 16px; padding: 14px 16px; border-radius: 8px; background: var(--kpi-g50); border: 1px solid var(--kpi-g200); }

    /* Delta arrow */
    .kpi-delta { display: inline-flex; align-items: center; gap: 2px; font-size: 11px; font-weight: 600; }
    .kpi-delta--up { color: var(--kpi-green); }
    .kpi-delta--down { color: var(--kpi-red); }

    /* Clinical assistant stats */
    .kpi-row--3 { grid-template-columns: repeat(3, 1fr); }
    .kpi-stat { padding: 24px 26px; }
    .kpi-stat__value { font-family: var(--kpi-mono); font-size: 30px; font-weight: 700; color: var(--kpi-g900); line-height: 1; margin-top: 10px; }
    .kpi-stat__row { display: flex; justify-content: space-between; align-items: flex-end; margin-top: 10px; }

    /* Schedule */
    .kpi-sched__row { display: flex; align-items: center; gap: 10px; padding: 10px 12px; border-radius: 8px; font-size: 13px; }
    .kpi-sched__row:nth-child(even) { background: var(--kpi-g50); }
    .kpi-sched__time { font-family: var(--kpi-mono); font-size: 12px; font-weight: 600; color: var(--kpi-teal); width: 40px; flex-shrink: 0; }
    .kpi-sched__h { font-family: var(--kpi-mono); font-size: 12px; font-weight: 600; color: var(--kpi-g900); width: 56px; flex-shrink: 0; }
    .kpi-sched__type { color: var(--kpi-g700); flex: 1; }
    .kpi-sched__staff { color: var(--kpi-g400); font-size: 12px; width: 64px; flex-shrink: 0; }
    .kpi-sched__badge { font-size: 10px; font-weight: 600; padding: 3px 8px; border-radius: 4px; }
    .kpi-sched__badge--arrived { background: var(--kpi-green-bg); color: var(--kpi-green); }
    .kpi-sched__badge--confirmed { background: #EFF6FF; color: #2563EB; }
    .kpi-sched__badge--pending { background: var(--kpi-amber-bg); color: var(--kpi-amber); }

    /* Bar chart */
    .kpi-bars { display: flex; align-items: flex-end; gap: 6px; height: 130px; padding-top: 10px; }
    .kpi-bars__col { flex: 1; display: flex; flex-direction: column; align-items: center; gap: 4px; }
    .kpi-bars__val { font-family: var(--kpi-mono); font-size: 10px; font-weight: 600; }
    .kpi-bars__bar { width: 100%; border-radius: 4px; transition: height 0.5s ease; }
    .kpi-bars__day { font-size: 10px; color: var(--kpi-g400); font-weight: 500; }
    .kpi-bars__total { text-align: center; margin-top: 16px; padding: 10px; border-radius: 8px; background: var(--kpi-g50); border: 1px solid var(--kpi-g200); font-family: var(--kpi-mono); font-size: 12px; color: var(--kpi-g500); }

    /* Loading state */
    .kpi-loading { text-align: center; padding: 60px 20px; color: var(--kpi-g400); font-size: 14px; }
    .kpi-loading__spinner { width: 32px; height: 32px; border: 3px solid var(--kpi-g200); border-top-color: var(--kpi-teal); border-radius: 50%; animation: kpiSpin 0.8s linear infinite; margin: 0 auto 12px; }
    @keyframes kpiSpin { to { transform: rotate(360deg); } }

    /* Responsive */
    @media (min-width: 1400px) {
        .kpi-row--6 { grid-template-columns: repeat(6, 1fr); }
    }
    @media (max-width: 1399px) and (min-width: 1101px) {
        .kpi-row--6 { grid-template-columns: repeat(3, 1fr); }
    }
    @media (max-width: 1100px) {
        .kpi-row--6 { grid-template-columns: repeat(3, 1fr); }
        .kpi-row--split { grid-template-columns: 1fr; }
    }
    @media (max-width: 768px) {
        .kpi-row--6 { grid-template-columns: repeat(2, 1fr); }
        .kpi-row--2, .kpi-row--3 { grid-template-columns: 1fr; }
        .kpi-header { flex-direction: column; gap: 12px; align-items: flex-start; }
        .kpi-revenue__amount { font-size: 28px; }
    }
    </style>

    <div id="hm-kpi" 
         data-staff-id="<?php echo (int) $staff->id; ?>"
         data-role="<?php echo esc_attr($staff->role); ?>"
         data-is-admin="<?php echo $is_admin ? '1' : '0'; ?>"
         data-has-pin="<?php echo $has_pin ? '1' : '0'; ?>"
         data-staff-name="<?php echo esc_attr(trim($staff->first_name . ' ' . $staff->last_name)); ?>"
         data-staff-initials="<?php echo esc_attr(strtoupper(substr($staff->first_name,0,1) . substr($staff->last_name,0,1))); ?>"
         data-clinic="<?php echo esc_attr($staff->primary_clinic_name ?? ''); ?>"
         data-period-start="<?php echo esc_attr($period_start); ?>"
         data-period-end="<?php echo esc_attr($period_end); ?>"
         data-period-label="<?php echo esc_attr($period_label); ?>"
         data-day="<?php echo $day_of_month; ?>"
         data-days="<?php echo $days_in_month; ?>">

        <!-- Header -->
        <div class="kpi-header">
            <div class="kpi-header__left">
                <div class="kpi-avatar" id="kpi-avatar"><?php echo esc_html(strtoupper(substr($staff->first_name,0,1) . substr($staff->last_name,0,1))); ?></div>
                <div>
                    <div class="kpi-header__name" id="kpi-name"><?php echo esc_html(trim($staff->first_name . ' ' . $staff->last_name)); ?></div>
                    <div class="kpi-header__meta" id="kpi-meta">
                        <?php echo esc_html(ucwords(str_replace('_',' ',$staff->role))); ?> · 
                        <?php echo esc_html($staff->primary_clinic_name ?? 'No clinic'); ?> · 
                        <?php echo esc_html($period_label); ?>
                    </div>
                </div>
            </div>
            <div class="kpi-header__controls">
                <?php if ($is_admin): ?>
                <select class="kpi-select" id="kpi-staff-picker">
                    <option value="<?php echo (int) $staff->id; ?>">My Dashboard</option>
                    <?php foreach ($all_staff as $s): ?>
                    <option value="<?php echo (int) $s->id; ?>" 
                            data-name="<?php echo esc_attr(trim($s->first_name . ' ' . $s->last_name)); ?>"
                            data-initials="<?php echo esc_attr(strtoupper(substr($s->first_name,0,1) . substr($s->last_name,0,1))); ?>"
                            data-role="<?php echo esc_attr($s->role); ?>"
                            data-clinic="<?php echo esc_attr($s->clinic_name ?? ''); ?>">
                        <?php echo esc_html(trim($s->first_name . ' ' . $s->last_name) . ' — ' . ucwords(str_replace('_',' ',$s->role))); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <?php endif; ?>
                <div class="kpi-period-nav">
                    <button class="kpi-period-btn" id="kpi-prev" title="Previous month">&#8249;</button>
                    <span class="kpi-period-label" id="kpi-period"><?php echo esc_html($period_label); ?></span>
                    <button class="kpi-period-btn" id="kpi-next" title="Next month">&#8250;</button>
                </div>
            </div>
        </div>

        <!-- Dashboard content (populated by JS) -->
        <div id="kpi-content">
            <div class="kpi-loading">
                <div class="kpi-loading__spinner"></div>
                Loading dashboard&hellip;
            </div>
        </div>
    </div>

    <script>
    (function($){
        'use strict';

        var KPI = {
            el: $('#hm-kpi'),
            staffId: null,
            role: null,
            periodStart: null,
            periodEnd: null,
            periodLabel: null,
            isAdmin: false,
            hasPin: false,
            vaultOpen: false,

            init: function() {
                this.staffId     = parseInt(this.el.data('staff-id'));
                this.role        = this.el.data('role');
                this.isAdmin     = this.el.data('is-admin') == '1';
                this.hasPin      = this.el.data('has-pin') == '1';
                this.periodStart = this.el.data('period-start');
                this.periodEnd   = this.el.data('period-end');
                this.periodLabel = this.el.data('period-label');

                this.bindEvents();
                this.loadDashboard();
            },

            bindEvents: function() {
                var self = this;

                // Staff picker (admin only)
                $('#kpi-staff-picker').on('change', function() {
                    var opt = $(this).find(':selected');
                    self.staffId = parseInt($(this).val());
                    self.role    = opt.data('role') || self.el.data('role');
                    self.vaultOpen = false;
                    self.loadDashboard();
                });

                // Period navigation
                $('#kpi-prev').on('click', function() { self.shiftPeriod(-1); });
                $('#kpi-next').on('click', function() { self.shiftPeriod(1); });
            },

            shiftPeriod: function(delta) {
                var d = new Date(this.periodStart + 'T00:00:00');
                d.setMonth(d.getMonth() + delta);
                this.periodStart = d.toISOString().slice(0,10);
                var end = new Date(d.getFullYear(), d.getMonth() + 1, 0);
                this.periodEnd = end.toISOString().slice(0,10);
                this.periodLabel = d.toLocaleDateString('en-IE', { month: 'long', year: 'numeric' });
                $('#kpi-period').text(this.periodLabel);
                this.vaultOpen = false;
                this.loadDashboard();
            },

            loadDashboard: function() {
                var self = this;
                $('#kpi-content').html('<div class="kpi-loading"><div class="kpi-loading__spinner"></div>Loading dashboard&hellip;</div>');

                $.post(HM.ajax_url, {
                    action: 'hm_kpi_get_data',
                    nonce: HM.nonce,
                    staff_id: this.staffId,
                    period_start: this.periodStart,
                    period_end: this.periodEnd
                }, function(r) {
                    if (r.success) {
                        self.render(r.data);
                    } else {
                        $('#kpi-content').html('<p style="color:var(--kpi-red);padding:20px;">' + (r.data || 'Error loading data') + '</p>');
                    }
                });
            },

            eur: function(n) { return '\u20AC' + Math.abs(n).toLocaleString('en-IE'); },

            pct: function(v, t) { return t ? Math.round((v / t) * 100) : 0; },

            statusColor: function(v, t) {
                var p = this.pct(v, t);
                return p >= 100 ? 'var(--kpi-green)' : p >= 85 ? 'var(--kpi-amber)' : 'var(--kpi-red)';
            },

            spark: function(data, color, w, h) {
                w = w || 80; h = h || 32;
                if (!data || data.length < 2) return '';
                var max = Math.max.apply(null, data), min = Math.min.apply(null, data), range = max - min || 1;
                var pts = data.map(function(v, i) {
                    var x = 4 + (i / (data.length - 1)) * (w - 8);
                    var y = 4 + (1 - (v - min) / range) * (h - 8);
                    return x + ',' + y;
                });
                var pathD = pts.map(function(p, i) { return (i === 0 ? 'M' : 'L') + p; }).join(' ');
                var last = pts[pts.length - 1].split(',');
                var area = pathD + ' L' + last[0] + ',' + h + ' L' + pts[0].split(',')[0] + ',' + h + ' Z';
                return '<svg width="' + w + '" height="' + h + '" class="kpi-spark"><defs><linearGradient id="sg' + color.replace(/[^a-zA-Z0-9]/g,'') + '" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="' + color + '" stop-opacity="0.08"/><stop offset="100%" stop-color="' + color + '" stop-opacity="0"/></linearGradient></defs><path d="' + area + '" fill="url(#sg' + color.replace(/[^a-zA-Z0-9]/g,'') + ')"/><path d="' + pathD + '" fill="none" stroke="' + color + '" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><circle cx="' + last[0] + '" cy="' + last[1] + '" r="2.5" fill="#fff" stroke="' + color + '" stroke-width="1.5"/></svg>';
            },

            ringGauge: function(pct, size, stroke) {
                size = size || 80; stroke = stroke || 4;
                var r = (size - stroke) / 2, c = 2 * Math.PI * r;
                var color = pct >= 100 ? 'var(--kpi-green)' : pct >= 80 ? 'var(--kpi-amber)' : 'var(--kpi-red)';
                var offset = c - (Math.min(pct, 100) / 100) * c;
                return '<div class="kpi-ring"><svg width="' + size + '" height="' + size + '" style="transform:rotate(-90deg)"><circle cx="' + size/2 + '" cy="' + size/2 + '" r="' + r + '" fill="none" stroke="var(--kpi-g100)" stroke-width="' + stroke + '"/><circle cx="' + size/2 + '" cy="' + size/2 + '" r="' + r + '" fill="none" stroke="' + color + '" stroke-width="' + stroke + '" stroke-dasharray="' + c + '" stroke-dashoffset="' + offset + '" stroke-linecap="round" style="transition:stroke-dashoffset 1.5s cubic-bezier(0.16,1,0.3,1)"/></svg><div class="kpi-ring__label"><span class="kpi-ring__pct" style="font-size:' + Math.round(size * 0.22) + 'px">' + pct + '<small>%</small></span></div></div>';
            },

            render: function(d) {
                var isDispenser = ['dispenser', 'audiologist', 'Dispenser', 'Audiologist'].indexOf(d.staff_role) !== -1;
                var html = isDispenser ? this.renderDispenser(d) : this.renderClinical(d);
                $('#kpi-content').html(html);
                this.bindVaultEvents(d);
            },

            renderDispenser: function(d) {
                var self = this;
                var rev = d.revenue;
                var pct = this.pct(rev.current, rev.target);
                var proj = rev.day_of_month > 0 ? Math.round((rev.current / rev.day_of_month) * rev.days_in_month) : 0;
                var mom = rev.last_month > 0 ? ((rev.current - rev.last_month) / rev.last_month * 100).toFixed(1) : '0.0';
                var momUp = parseFloat(mom) >= 0;

                // Revenue hero
                var html = '<div class="kpi-row kpi-row--hero"><div class="kpi-card kpi-revenue"><div class="kpi-revenue__top"><div><div class="kpi-label">Monthly Revenue</div><div class="kpi-revenue__amount">' + self.eur(rev.current) + '</div><div class="kpi-revenue__stats"><span>of <strong>' + self.eur(rev.target) + '</strong> target</span><span class="kpi-revenue__sep"></span><span>Projected <strong style="color:' + (proj >= rev.target ? 'var(--kpi-green)' : 'var(--kpi-amber)') + '">' + self.eur(proj) + '</strong></span><span class="kpi-revenue__sep"></span><span class="kpi-delta kpi-delta--' + (momUp ? 'up' : 'down') + '">' + (momUp ? '\u25B2' : '\u25BC') + ' ' + Math.abs(mom) + '%</span> <span style="color:var(--kpi-g400)">vs last month</span></div></div><div>' + self.ringGauge(pct, 80, 5) + '</div></div><div class="kpi-revenue__track"><div class="kpi-bar" style="height:4px"><div class="kpi-bar__fill" style="width:' + Math.min(pct, 100) + '%;background:var(--kpi-teal)"></div></div><div class="kpi-revenue__track-ends"><span>\u20AC0</span><span>' + self.eur(rev.target) + '</span></div></div></div></div>';

                // KPI row
                html += '<div class="kpi-row kpi-row--6">';
                (d.kpis || []).forEach(function(k) {
                    var kpct = self.pct(k.value, k.target);
                    var color = self.statusColor(k.value, k.target);
                    var hit = kpct >= 100;
                    var fmt = k.unit === '\u20AC' ? self.eur(k.value) : k.value + k.unit;
                    var tgtFmt = k.unit === '\u20AC' ? self.eur(k.target) : k.target + k.unit;
                    html += '<div class="kpi-card kpi-metric' + (hit ? ' kpi-metric--hit' : '') + '"><div class="kpi-label" style="margin-bottom:12px">' + k.label + '</div><div class="kpi-metric__top"><div class="kpi-metric__value">' + fmt + '</div>' + self.spark(k.trend, color, 56, 24) + '</div><div class="kpi-metric__bar"><div class="kpi-bar"><div class="kpi-bar__fill" style="width:' + Math.min(kpct, 100) + '%;background:' + color + '"></div></div></div><div class="kpi-metric__footer"><span class="kpi-metric__target">Target ' + tgtFmt + '</span>' + (hit ? '<span class="kpi-metric__status kpi-metric__status--hit">\u2713 Hit</span>' : '<span class="kpi-metric__status kpi-metric__status--miss" style="color:' + color + '">' + kpct + '%</span>') + '</div></div>';
                });
                html += '</div>';

                // Appointments + Orders
                var a = d.appointments || {};
                var o = d.orders || {};
                var upco = a.total - a.completed - a.noshow - a.cancelled;
                var aPct = self.pct(a.completed, a.total);

                html += '<div class="kpi-row kpi-row--2">';

                // Appointments card
                html += '<div class="kpi-card kpi-summary"><div class="kpi-label">Appointments</div><div class="kpi-summary__ring">' + self.ringGauge(aPct, 56, 3.5);
                html += '<div class="kpi-summary__list">';
                [{l:'Completed',v:a.completed,c:'var(--kpi-green)'},{l:'No-show',v:a.noshow,c:'var(--kpi-red)'},{l:'Cancelled',v:a.cancelled,c:'var(--kpi-amber)'},{l:'Upcoming',v:upco,c:'var(--kpi-g300)'}].forEach(function(s) {
                    html += '<div class="kpi-summary__item"><span class="kpi-summary__item-label"><span class="kpi-summary__dot" style="background:' + s.c + '"></span>' + s.l + '</span><span class="kpi-summary__item-val">' + (s.v || 0) + '</span></div>';
                });
                html += '</div></div></div>';

                // Orders card
                html += '<div class="kpi-card kpi-summary"><div class="kpi-label" style="margin-bottom:16px">Orders</div><div class="kpi-orders__grid">';
                [{v:o.total||0,l:'Total',c:'var(--kpi-g900)'},{v:o.binaural||0,l:'Binaural',c:'var(--kpi-teal)'},{v:o.monaural||0,l:'Monaural',c:'var(--kpi-g500)'}].forEach(function(x) {
                    html += '<div class="kpi-orders__col"><div class="kpi-orders__num" style="color:' + x.c + '">' + x.v + '</div><div class="kpi-orders__label">' + x.l + '</div></div>';
                });
                html += '</div><div class="kpi-orders__tags"><div class="kpi-tag kpi-tag--pipeline"><div class="kpi-tag__title">Pipeline</div><div class="kpi-tag__val">' + (o.pipeline || 0) + ' <span>\u00B7 ' + self.eur(o.pipeline_value || 0) + '</span></div></div>';
                if ((o.returns || 0) > 0) {
                    html += '<div class="kpi-tag kpi-tag--return"><div class="kpi-tag__title">Returns</div><div class="kpi-tag__val">' + o.returns + ' <span>\u00B7 ' + self.eur(o.return_value || 0) + '</span></div></div>';
                }
                html += '</div></div>';
                html += '</div>';

                // Commission + Activity
                html += '<div class="kpi-row kpi-row--split">';
                html += this.renderVault(d);
                html += this.renderFeed(d.activity || []);
                html += '</div>';

                return html;
            },

            renderClinical: function(d) {
                var self = this;
                var html = '';

                // Stats row
                html += '<div class="kpi-row kpi-row--3">';
                (d.stats || []).forEach(function(s) {
                    var ch = s.prev ? Math.round(((s.value - s.prev) / s.prev) * 100) : null;
                    html += '<div class="kpi-card kpi-stat"><div class="kpi-label">' + s.label + '</div><div class="kpi-stat__row"><span class="kpi-stat__value">' + s.value + '</span>';
                    if (ch !== null) {
                        html += '<span class="kpi-delta kpi-delta--' + (ch >= 0 ? 'up' : 'down') + '">' + (ch >= 0 ? '\u25B2' : '\u25BC') + ' ' + Math.abs(ch) + '%</span>';
                    }
                    html += '</div></div>';
                });
                html += '</div>';

                // Schedule + Weekly
                html += '<div class="kpi-row kpi-row--split">';

                // Schedule
                html += '<div class="kpi-card" style="padding:24px"><div class="kpi-label" style="margin-bottom:14px">Today\'s Schedule</div>';
                (d.schedule || []).forEach(function(r) {
                    var badgeCls = r.status === 'Arrived' ? 'arrived' : (r.status === 'Confirmed' ? 'confirmed' : 'pending');
                    html += '<div class="kpi-sched__row"><span class="kpi-sched__time">' + r.time + '</span><span class="kpi-sched__h">' + r.h_number + '</span><span class="kpi-sched__type">' + r.service + '</span><span class="kpi-sched__staff">' + r.staff + '</span><span class="kpi-sched__badge kpi-sched__badge--' + badgeCls + '">' + r.status + '</span></div>';
                });
                if (!d.schedule || !d.schedule.length) html += '<p style="color:var(--kpi-g400);font-size:13px">No appointments today</p>';
                html += '</div>';

                // Weekly checkins
                html += '<div class="kpi-card" style="padding:24px"><div class="kpi-label" style="margin-bottom:14px">Check-ins This Week</div>';
                var week = d.weekly_checkins || [0,0,0,0,0,0,0];
                var maxCk = Math.max.apply(null, week) || 1;
                var days = ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];
                html += '<div class="kpi-bars">';
                week.forEach(function(v, i) {
                    var h = v > 0 ? Math.max((v / maxCk) * 85, 4) : 2;
                    html += '<div class="kpi-bars__col"><span class="kpi-bars__val" style="color:' + (v > 0 ? 'var(--kpi-g700)' : 'var(--kpi-g300)') + '">' + (v || '\u2013') + '</span><div class="kpi-bars__bar" style="height:' + h + 'px;background:' + (i < 5 ? 'var(--kpi-teal)' : 'var(--kpi-g200)') + ';opacity:' + (i < 5 ? '0.75' : '1') + '"></div><span class="kpi-bars__day">' + days[i] + '</span></div>';
                });
                html += '</div>';
                var total = week.reduce(function(a, b) { return a + b; }, 0);
                html += '<div class="kpi-bars__total">' + total + ' <span style="font-family:var(--kpi-body)">check-ins total</span></div>';
                html += '</div>';
                html += '</div>';

                // Commission vault
                html += '<div class="kpi-row kpi-row--hero">';
                html += this.renderVault(d);
                html += '</div>';

                return html;
            },

            renderVault: function(d) {
                var c = d.commission || {};
                var isDisp = ['dispenser', 'audiologist', 'Dispenser', 'Audiologist'].indexOf(d.staff_role) !== -1;
                var self = this;

                var html = '<div class="kpi-card" id="kpi-vault">';
                html += '<div class="kpi-vault__header"><div class="kpi-vault__header-left"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--kpi-g400)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg><span class="kpi-vault__title">Earnings</span></div><button class="kpi-vault__lock-btn" id="kpi-vault-lock" style="display:none">Lock</button></div>';
                html += '<div class="kpi-vault__body">';

                // Content (always rendered, blurred when locked)
                html += '<div class="kpi-vault__content kpi-vault__content--locked" id="kpi-vault-content">';

                // Summary boxes
                html += '<div class="kpi-comm__grid"><div class="kpi-comm__box kpi-comm__box--current"><div class="kpi-label">Current Take-Home</div><div class="kpi-comm__amount">' + self.eur(c.current_pay || 0) + '</div><div class="kpi-comm__detail">Base salary ' + self.eur(c.base_salary || 0);
                if (isDisp) html += '<br>Commission ' + self.eur(c.commission_earned || 0);
                if (isDisp && (c.return_deductions || 0) > 0) html += '<br><span style="color:var(--kpi-red)">Returns \u2212' + self.eur(c.return_deductions) + '</span>';
                html += '</div></div>';

                html += '<div class="kpi-comm__box kpi-comm__box--projected"><div class="kpi-label" style="color:#0D9488">Projected (all pipeline)</div><div class="kpi-comm__amount">' + self.eur(c.projected_pay || 0) + '</div><div class="kpi-comm__detail">';
                if (isDisp) {
                    html += '+' + self.eur((c.commission_if_fitted || 0) - (c.commission_earned || 0)) + ' potential<br>from ' + (c.pipeline_count || 0) + ' awaiting fitting';
                } else {
                    html += c.note || 'Base salary only';
                }
                html += '</div></div></div>';

                // Tier table (dispensers only)
                if (isDisp && c.tiers && c.tiers.length) {
                    html += '<div class="kpi-label" style="margin-bottom:10px">Commission Breakdown</div>';
                    html += '<div class="kpi-comm__table-wrap"><table class="kpi-comm__table"><thead><tr><th>Revenue Bracket</th><th>Rate</th><th>Earned</th></tr></thead><tbody>';
                    c.tiers.forEach(function(t) {
                        html += '<tr><td style="color:var(--kpi-g700)">' + t.bracket + '</td><td style="font-family:var(--kpi-mono);font-weight:600;color:var(--kpi-teal)">' + t.rate + '</td><td style="font-family:var(--kpi-mono);font-weight:600;color:var(--kpi-g900)">' + self.eur(t.earned) + '</td></tr>';
                    });
                    if ((c.return_deductions || 0) > 0) {
                        html += '<tr><td style="color:var(--kpi-red)">Returns (10% flat)</td><td style="font-family:var(--kpi-mono);font-weight:600;color:var(--kpi-red)">10%</td><td style="font-family:var(--kpi-mono);font-weight:600;color:var(--kpi-red)">\u2212' + self.eur(c.return_deductions) + '</td></tr>';
                    }
                    html += '</tbody><tfoot><tr><td style="font-weight:700;color:var(--kpi-g900)">Net Commission</td><td></td><td style="font-family:var(--kpi-mono);font-size:15px;font-weight:700;color:var(--kpi-navy)">' + self.eur((c.commission_earned || 0) - (c.return_deductions || 0)) + '</td></tr></tfoot></table></div>';

                    // Invoiced vs pipeline bar
                    var inv = c.invoiced_total || 0;
                    var pip = c.pipeline_value || 0;
                    if (inv + pip > 0) {
                        html += '<div class="kpi-comm__bar"><div style="display:flex;justify-content:space-between;margin-bottom:8px;font-family:var(--kpi-mono);font-size:11px;color:var(--kpi-g400)"><span>Invoiced ' + self.eur(inv) + '</span><span>Pipeline ' + self.eur(pip) + '</span></div><div class="kpi-bar" style="height:4px;display:flex;gap:2px"><div style="flex:' + inv + ';background:var(--kpi-navy);border-radius:2px"></div><div style="flex:' + pip + ';background:var(--kpi-teal);opacity:0.35;border-radius:2px"></div></div></div>';
                    }
                }

                if (!isDisp) {
                    html += '<p style="font-size:13px;color:var(--kpi-g500)">' + (c.note || 'Base salary \u2014 commission not applicable') + '</p>';
                }

                html += '</div>'; // vault content

                // Overlay
                html += '<div class="kpi-vault__overlay" id="kpi-vault-overlay">';
                html += '<div class="kpi-vault__prompt" id="kpi-vault-prompt"><div class="kpi-vault__icon"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="var(--kpi-navy)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></div><div class="kpi-vault__prompt-text">View Earnings</div><div class="kpi-vault__prompt-sub" id="kpi-vault-sub">PIN required</div></div>';
                html += '<div class="kpi-vault__pin-box" id="kpi-vault-pin" style="display:none"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--kpi-navy)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="margin-bottom:14px"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg><div style="font-size:13px;font-weight:600;color:var(--kpi-g900);margin-bottom:16px" id="kpi-pin-title">Enter your PIN</div><div style="display:flex;gap:8px" id="kpi-pin-form"><input type="password" maxlength="6" class="kpi-vault__pin-input" id="kpi-pin-input" placeholder="\u2022\u2022\u2022\u2022"><button class="kpi-vault__pin-btn" id="kpi-pin-submit">Unlock</button></div><div class="kpi-vault__error" id="kpi-pin-error" style="display:none"></div><button class="kpi-vault__cancel" id="kpi-pin-cancel">Cancel</button>';

                // Set PIN form (hidden unless no PIN set)
                html += '<div id="kpi-set-pin-form" style="display:none;margin-top:16px;padding-top:16px;border-top:1px solid var(--kpi-g200)"><div style="font-size:12px;font-weight:600;color:var(--kpi-g700);margin-bottom:8px">Set your PIN (4-6 digits)</div><div class="kpi-vault__set-pin"><input type="password" maxlength="6" id="kpi-set-pin" placeholder="PIN"><input type="password" maxlength="6" id="kpi-set-pin-confirm" placeholder="Confirm"></div><button class="kpi-vault__pin-btn" style="margin-top:8px;width:100%" id="kpi-set-pin-btn">Set PIN</button><div class="kpi-vault__error" id="kpi-set-pin-error" style="display:none"></div></div>';

                html += '</div>'; // pin box
                html += '</div>'; // overlay

                html += '</div>'; // vault body
                html += '</div>'; // vault card

                return html;
            },

            renderFeed: function(items) {
                var self = this;
                var colors = {
                    sale: { bg: 'var(--kpi-green-bg)', color: 'var(--kpi-green)' },
                    fitting: { bg: '#EFF6FF', color: '#2563EB' },
                    'return': { bg: 'var(--kpi-red-bg)', color: 'var(--kpi-red)' },
                    nosale: { bg: 'var(--kpi-g100)', color: 'var(--kpi-g500)' },
                    other: { bg: 'var(--kpi-g100)', color: 'var(--kpi-g500)' }
                };
                var html = '<div class="kpi-card kpi-feed"><div class="kpi-label" style="margin-bottom:16px">Recent Activity</div>';
                if (!items.length) {
                    html += '<p style="color:var(--kpi-g400);font-size:13px">No recent activity</p>';
                }
                items.forEach(function(item) {
                    var c = colors[item.type] || colors.other;
                    html += '<div class="kpi-feed__item"><span class="kpi-feed__pill" style="background:' + c.bg + ';color:' + c.color + '">' + (item.label || item.type) + '</span><div class="kpi-feed__text"><div class="kpi-feed__main">' + item.text + '</div><div class="kpi-feed__sub">' + item.detail + '</div></div><div class="kpi-feed__right">';
                    if (item.amount !== undefined && item.amount !== null) {
                        html += '<div class="kpi-feed__amt" style="color:' + (item.amount < 0 ? 'var(--kpi-red)' : 'var(--kpi-green)') + '">' + (item.amount < 0 ? '\u2212' : '+') + self.eur(item.amount) + '</div>';
                    }
                    html += '<div class="kpi-feed__time">' + item.time + '</div></div></div>';
                });
                html += '</div>';
                return html;
            },

            bindVaultEvents: function(d) {
                var self = this;
                var hasPin = self.hasPin;

                // If admin viewing another staff member, auto-reveal for admins
                if (self.isAdmin && self.staffId !== parseInt(self.el.data('staff-id'))) {
                    self.openVault();
                    return;
                }

                // Click prompt to show PIN input
                $('#kpi-vault-prompt').off('click').on('click', function() {
                    if (!hasPin) {
                        // Show set-PIN form
                        $('#kpi-vault-prompt').hide();
                        $('#kpi-vault-pin').show();
                        $('#kpi-pin-title').text('Set your PIN first');
                        $('#kpi-pin-form').hide();
                        $('#kpi-set-pin-form').show();
                        $('#kpi-set-pin').focus();
                    } else {
                        $('#kpi-vault-prompt').hide();
                        $('#kpi-vault-pin').show();
                        $('#kpi-pin-input').focus();
                    }
                });

                // Set PIN
                $('#kpi-set-pin-btn').off('click').on('click', function() {
                    var pin = $('#kpi-set-pin').val();
                    var confirm = $('#kpi-set-pin-confirm').val();
                    if (!pin || !/^\d{4,6}$/.test(pin)) {
                        $('#kpi-set-pin-error').text('PIN must be 4-6 digits').show();
                        return;
                    }
                    if (pin !== confirm) {
                        $('#kpi-set-pin-error').text('PINs do not match').show();
                        return;
                    }
                    $.post(HM.ajax_url, {
                        action: 'hm_set_commission_pin',
                        nonce: HM.nonce,
                        staff_id: self.staffId,
                        new_pin: pin
                    }, function(r) {
                        if (r.success) {
                            self.hasPin = hasPin = true;
                            $('#kpi-set-pin-form').hide();
                            $('#kpi-pin-title').text('Enter your PIN');
                            $('#kpi-pin-form').show();
                            $('#kpi-pin-input').focus();
                        } else {
                            $('#kpi-set-pin-error').text(r.data || 'Error').show();
                        }
                    });
                });

                // Verify PIN
                $('#kpi-pin-submit').off('click').on('click', function() { self.verifyPin(); });
                $('#kpi-pin-input').off('keydown').on('keydown', function(e) {
                    if (e.key === 'Enter') self.verifyPin();
                    if (e.key === 'Escape') self.resetVault();
                });

                // Cancel
                $('#kpi-pin-cancel').off('click').on('click', function() { self.resetVault(); });

                // Lock
                $('#kpi-vault-lock').off('click').on('click', function() {
                    self.vaultOpen = false;
                    self.resetVault();
                    $('#kpi-vault-content').addClass('kpi-vault__content--locked');
                    $('#kpi-vault-overlay').show();
                    $('#kpi-vault-lock').hide();
                });
            },

            verifyPin: function() {
                var self = this;
                var pin = $('#kpi-pin-input').val();
                if (!pin) return;
                $.post(HM.ajax_url, {
                    action: 'hm_verify_commission_pin',
                    nonce: HM.nonce,
                    staff_id: self.staffId,
                    pin: pin
                }, function(r) {
                    if (r.success) {
                        self.openVault();
                    } else {
                        $('#kpi-pin-error').text(r.data || 'Incorrect PIN').show();
                        $('#kpi-pin-input').val('').addClass('kpi-vault__pin-input--error').focus();
                        setTimeout(function() { $('#kpi-pin-input').removeClass('kpi-vault__pin-input--error'); }, 1500);
                    }
                });
            },

            openVault: function() {
                this.vaultOpen = true;
                $('#kpi-vault-content').removeClass('kpi-vault__content--locked');
                $('#kpi-vault-overlay').hide();
                $('#kpi-vault-lock').show();
            },

            resetVault: function() {
                $('#kpi-vault-pin').hide();
                $('#kpi-vault-prompt').show();
                $('#kpi-pin-input').val('');
                $('#kpi-pin-error').hide();
            }
        };

        $(function() { KPI.init(); });

    })(jQuery);
    </script>
    <?php
}


/* ═══════════════════════════════════════════════════════════
   CLASS + SHORTCODE
   ═══════════════════════════════════════════════════════════ */
class HearMed_KPI {

    public static function init() {
        add_shortcode('hearmed_kpi', [__CLASS__, 'render']);
        add_action('wp_ajax_hm_kpi_get_data', [__CLASS__, 'ajax_get_data']);
    }

    public static function render($atts = []): string {
        if (!is_user_logged_in()) return '';
        ob_start();
        hm_kpi_render();
        return ob_get_clean();
    }

    /* ═══════════════════════════════════════════════════════
       AJAX: Get all dashboard data for a staff member + period
       ═══════════════════════════════════════════════════════ */
    public static function ajax_get_data() {
        check_ajax_referer('hm_nonce', 'nonce');

        $uid = get_current_user_id();
        if (!$uid) { wp_send_json_error('Not logged in'); return; }

        $target_staff_id = intval($_POST['staff_id'] ?? 0);
        $period_start    = sanitize_text_field($_POST['period_start'] ?? '');
        $period_end      = sanitize_text_field($_POST['period_end'] ?? '');

        if (!$target_staff_id || !$period_start || !$period_end) {
            wp_send_json_error('Missing parameters');
            return;
        }

        // Security: non-admins can only see their own data
        $viewer = HearMed_DB::get_row(
            "SELECT id, role FROM hearmed_reference.staff WHERE wp_user_id = $1 AND is_active = true",
            [$uid]
        );
        if (!$viewer) { wp_send_json_error('Staff not found'); return; }

        $is_admin = in_array($viewer->role, ['c_level', 'administrator']);
        if (!$is_admin && (int)$viewer->id !== $target_staff_id) {
            wp_send_json_error('Access denied');
            return;
        }

        // Get target staff info
        $staff = HearMed_DB::get_row(
            "SELECT s.*, sc.clinic_id AS primary_clinic_id
             FROM hearmed_reference.staff s
             LEFT JOIN hearmed_reference.staff_clinics sc ON sc.staff_id = s.id AND sc.is_primary_clinic = true
             WHERE s.id = $1",
            [$target_staff_id]
        );
        if (!$staff) { wp_send_json_error('Target staff not found'); return; }

        $is_dispenser = in_array(strtolower($staff->role), ['dispenser', 'audiologist']);

        // Build response
        $data = ['staff_role' => $staff->role];

        if ($is_dispenser) {
            $data = array_merge($data, self::get_dispenser_data($staff, $period_start, $period_end));
        } else {
            $data = array_merge($data, self::get_clinical_data($staff, $period_start, $period_end));
        }

        // Commission data (for all roles)
        $data['commission'] = self::get_commission_data($staff, $period_start, $period_end);

        wp_send_json_success($data);
    }

    /* ─── DISPENSER DATA ─── */
    private static function get_dispenser_data($staff, $start, $end) {
        $sid = (int) $staff->id;

        // Revenue
        $current_rev = (float) HearMed_DB::get_var(
            "SELECT COALESCE(SUM(grand_total), 0) FROM hearmed_core.invoices
             WHERE staff_id = $1 AND invoice_date BETWEEN $2 AND $3 AND payment_status != 'Refunded'",
            [$sid, $start, $end]
        );

        // Previous month revenue
        $prev_start = date('Y-m-01', strtotime($start . ' -1 month'));
        $prev_end   = date('Y-m-t', strtotime($prev_start));
        $prev_rev = (float) HearMed_DB::get_var(
            "SELECT COALESCE(SUM(grand_total), 0) FROM hearmed_core.invoices
             WHERE staff_id = $1 AND invoice_date BETWEEN $2 AND $3 AND payment_status != 'Refunded'",
            [$sid, $prev_start, $prev_end]
        );

        // Revenue target
        $rev_target = self::get_target($sid, 'monthly_revenue', 40000);

        // Period info
        $now = new DateTime('now', new DateTimeZone('Europe/Dublin'));
        $ps  = new DateTime($start);
        $pe  = new DateTime($end);
        $is_current_month = ($ps->format('Y-m') === $now->format('Y-m'));

        $revenue = [
            'current'       => $current_rev,
            'target'        => $rev_target,
            'last_month'    => $prev_rev,
            'day_of_month'  => $is_current_month ? (int) $now->format('j') : (int) $pe->format('j'),
            'days_in_month' => (int) $pe->format('j'),
        ];

        // ── KPIs ──

        // Closing Rate: Sale / (Sale + Tested Not Sold) from Hearing Test appointments
        $closing_data = HearMed_DB::get_row(
            "SELECT 
                COUNT(*) FILTER (WHERE ot.report_outcome = 'Sale') AS sales,
                COUNT(*) FILTER (WHERE ot.report_outcome IN ('Sale', 'Tested Not Sold')) AS testable
             FROM hearmed_core.appointments a
             JOIN hearmed_reference.services sv ON sv.id = a.service_id
             JOIN hearmed_core.appointment_outcomes ao ON ao.appointment_id = a.id
             JOIN hearmed_core.outcome_templates ot ON ot.outcome_name = ao.outcome_name AND ot.service_id = sv.id
             WHERE a.staff_id = $1 AND a.appointment_date BETWEEN $2 AND $3
             AND sv.is_reportable = true AND sv.report_category = 'Hearing Test'
             AND ot.is_reportable = true AND ot.report_outcome IN ('Sale', 'Tested Not Sold')",
            [$sid, $start, $end]
        );
        $closing_rate = ($closing_data && $closing_data->testable > 0) 
            ? round(($closing_data->sales / $closing_data->testable) * 100) : 0;

        // Conversion Rate: Sale / ALL Hearing Test outcomes
        $conv_data = HearMed_DB::get_row(
            "SELECT 
                COUNT(*) FILTER (WHERE ot.report_outcome = 'Sale') AS sales,
                COUNT(*) AS total
             FROM hearmed_core.appointments a
             JOIN hearmed_reference.services sv ON sv.id = a.service_id
             JOIN hearmed_core.appointment_outcomes ao ON ao.appointment_id = a.id
             JOIN hearmed_core.outcome_templates ot ON ot.outcome_name = ao.outcome_name AND ot.service_id = sv.id
             WHERE a.staff_id = $1 AND a.appointment_date BETWEEN $2 AND $3
             AND sv.is_reportable = true AND sv.report_category = 'Hearing Test'
             AND ot.is_reportable = true",
            [$sid, $start, $end]
        );
        $conversion_rate = ($conv_data && $conv_data->total > 0) 
            ? round(($conv_data->sales / $conv_data->total) * 100) : 0;

        // Completion Rate
        $comp_data = HearMed_DB::get_row(
            "SELECT 
                COUNT(*) FILTER (WHERE appointment_status = 'Completed') AS completed,
                COUNT(*) FILTER (WHERE appointment_status != 'Cancelled') AS bookable
             FROM hearmed_core.appointments
             WHERE staff_id = $1 AND appointment_date BETWEEN $2 AND $3",
            [$sid, $start, $end]
        );
        $completion_rate = ($comp_data && $comp_data->bookable > 0) 
            ? round(($comp_data->completed / $comp_data->bookable) * 100) : 0;

        // Binaural Rate: orders with hearing aids on BOTH ears / total HA orders
        $bin_data = HearMed_DB::get_row(
            "SELECT 
                COUNT(DISTINCT o.id) AS total_orders,
                COUNT(DISTINCT o.id) FILTER (
                    WHERE EXISTS (
                        SELECT 1 FROM hearmed_core.order_items oi2
                        JOIN hearmed_reference.products p2 ON p2.id = oi2.item_id AND p2.item_type = 'product'
                        WHERE oi2.order_id = o.id AND LOWER(oi2.ear_side) = 'left'
                    ) AND EXISTS (
                        SELECT 1 FROM hearmed_core.order_items oi3
                        JOIN hearmed_reference.products p3 ON p3.id = oi3.item_id AND p3.item_type = 'product'
                        WHERE oi3.order_id = o.id AND LOWER(oi3.ear_side) = 'right'
                    )
                ) AS binaural_orders
             FROM hearmed_core.orders o
             JOIN hearmed_core.order_items oi ON oi.order_id = o.id
             JOIN hearmed_reference.products p ON p.id = oi.item_id AND p.item_type = 'product'
             WHERE o.staff_id = $1 AND o.order_date BETWEEN $2 AND $3
             AND o.current_status NOT IN ('Cancelled')",
            [$sid, $start, $end]
        );
        $binaural_rate = ($bin_data && $bin_data->total_orders > 0) 
            ? round(($bin_data->binaural_orders / $bin_data->total_orders) * 100) : 0;

        // Average Order Value
        $aov = (float) HearMed_DB::get_var(
            "SELECT COALESCE(AVG(o.grand_total), 0)
             FROM hearmed_core.orders o
             WHERE o.staff_id = $1 AND o.order_date BETWEEN $2 AND $3
             AND o.current_status NOT IN ('Cancelled')",
            [$sid, $start, $end]
        );

        // Wax to Test (90-day window)
        $wax_data = HearMed_DB::get_row(
            "SELECT 
                COUNT(DISTINCT wax.patient_id) AS wax_patients,
                COUNT(DISTINCT test.patient_id) AS converted
             FROM hearmed_core.appointments wax
             JOIN hearmed_reference.services wsv ON wsv.id = wax.service_id 
                  AND wsv.is_reportable = true AND wsv.report_category = 'Wax Removal'
             LEFT JOIN hearmed_core.appointments test 
                  ON test.patient_id = wax.patient_id
                  AND test.id != wax.id
                  AND test.appointment_date BETWEEN wax.appointment_date AND (wax.appointment_date + INTERVAL '90 days')
                  AND test.appointment_status = 'Completed'
                  AND EXISTS (
                      SELECT 1 FROM hearmed_reference.services tsv 
                      WHERE tsv.id = test.service_id AND tsv.is_reportable = true AND tsv.report_category = 'Hearing Test'
                  )
             WHERE wax.staff_id = $1 AND wax.appointment_date BETWEEN $2 AND $3
             AND wax.appointment_status = 'Completed'",
            [$sid, $start, $end]
        );
        $wax_to_test = ($wax_data && $wax_data->wax_patients > 0) 
            ? round(($wax_data->converted / $wax_data->wax_patients) * 100) : 0;

        // Get targets
        $targets = [
            'closing_rate'           => self::get_target($sid, 'closing_rate', 50),
            'conversion_rate'        => self::get_target($sid, 'conversion_rate', 40),
            'appointment_completion' => self::get_target($sid, 'appointment_completion', 90),
            'binaural_rate'          => self::get_target($sid, 'binaural_rate', 70),
            'avg_order_price'        => self::get_target($sid, 'avg_order_price', 2500),
            'wax_to_test_rate'       => self::get_target($sid, 'wax_to_test_rate', 30),
        ];

        $kpis = [
            ['label' => 'Closing Rate',    'value' => $closing_rate,    'target' => $targets['closing_rate'],           'unit' => '%', 'trend' => [$closing_rate]],
            ['label' => 'Binaural Rate',   'value' => $binaural_rate,   'target' => $targets['binaural_rate'],          'unit' => '%', 'trend' => [$binaural_rate]],
            ['label' => 'Avg Order Value', 'value' => round($aov),      'target' => $targets['avg_order_price'],        'unit' => '€', 'trend' => [round($aov)]],
            ['label' => 'Completion',      'value' => $completion_rate,  'target' => $targets['appointment_completion'], 'unit' => '%', 'trend' => [$completion_rate]],
            ['label' => 'Wax → Test', 'value' => $wax_to_test, 'target' => $targets['wax_to_test_rate'],    'unit' => '%', 'trend' => [$wax_to_test]],
            ['label' => 'Conversion',      'value' => $conversion_rate,  'target' => $targets['conversion_rate'],       'unit' => '%', 'trend' => [$conversion_rate]],
        ];

        // ── Appointments breakdown ──
        $appts = HearMed_DB::get_row(
            "SELECT 
                COUNT(*) AS total,
                COUNT(*) FILTER (WHERE appointment_status = 'Completed') AS completed,
                COUNT(*) FILTER (WHERE appointment_status = 'No Show') AS noshow,
                COUNT(*) FILTER (WHERE appointment_status = 'Cancelled') AS cancelled
             FROM hearmed_core.appointments
             WHERE staff_id = $1 AND appointment_date BETWEEN $2 AND $3",
            [$sid, $start, $end]
        );

        // ── Orders breakdown ──
        $orders_row = HearMed_DB::get_row(
            "SELECT 
                COUNT(DISTINCT o.id) AS total,
                COUNT(DISTINCT o.id) FILTER (WHERE o.current_status IN ('Awaiting Fitting','Approved','Ordered','Received')) AS pipeline,
                COALESCE(SUM(o.grand_total) FILTER (WHERE o.current_status IN ('Awaiting Fitting','Approved','Ordered','Received')), 0) AS pipeline_value
             FROM hearmed_core.orders o
             WHERE o.staff_id = $1 AND o.order_date BETWEEN $2 AND $3
             AND o.current_status != 'Cancelled'",
            [$sid, $start, $end]
        );

        $returns_row = HearMed_DB::get_row(
            "SELECT COUNT(*) AS cnt, COALESCE(SUM(amount), 0) AS total
             FROM hearmed_core.credit_notes
             WHERE patient_id IN (
                 SELECT patient_id FROM hearmed_core.orders WHERE staff_id = $1 AND order_date BETWEEN $2 AND $3
             ) AND credit_date BETWEEN $2 AND $3",
            [$sid, $start, $end, $start, $end]
        );

        $orders = [
            'total'          => (int)($orders_row->total ?? 0),
            'binaural'       => (int)($bin_data->binaural_orders ?? 0),
            'monaural'       => (int)(($orders_row->total ?? 0) - ($bin_data->binaural_orders ?? 0)),
            'pipeline'       => (int)($orders_row->pipeline ?? 0),
            'pipeline_value' => (float)($orders_row->pipeline_value ?? 0),
            'returns'        => (int)($returns_row->cnt ?? 0),
            'return_value'   => (float)($returns_row->total ?? 0),
        ];

        // ── Activity feed (last 10 events) ──
        $activity = self::get_activity($sid, $start, $end);

        return [
            'revenue'      => $revenue,
            'kpis'         => $kpis,
            'appointments' => [
                'total'     => (int)($appts->total ?? 0),
                'completed' => (int)($appts->completed ?? 0),
                'noshow'    => (int)($appts->noshow ?? 0),
                'cancelled' => (int)($appts->cancelled ?? 0),
            ],
            'orders'       => $orders,
            'activity'     => $activity,
        ];
    }

    /* ─── CLINICAL ASSISTANT DATA ─── */
    private static function get_clinical_data($staff, $start, $end) {
        $sid = (int) $staff->id;
        $clinic_id = (int) ($staff->primary_clinic_id ?? 0);

        $prev_start = date('Y-m-01', strtotime($start . ' -1 month'));
        $prev_end   = date('Y-m-t', strtotime($prev_start));

        // Count metrics for current and previous period
        $cur = HearMed_DB::get_row(
            "SELECT 
                COUNT(*) FILTER (WHERE appointment_status = 'Completed') AS patients_seen,
                COUNT(*) FILTER (WHERE appointment_date = CURRENT_DATE) AS today_appts
             FROM hearmed_core.appointments
             WHERE clinic_id = $1 AND appointment_date BETWEEN $2 AND $3",
            [$clinic_id, $start, $end]
        );
        $prev = HearMed_DB::get_row(
            "SELECT COUNT(*) FILTER (WHERE appointment_status = 'Completed') AS patients_seen
             FROM hearmed_core.appointments
             WHERE clinic_id = $1 AND appointment_date BETWEEN $2 AND $3",
            [$clinic_id, $prev_start, $prev_end]
        );

        $stats = [
            ['label' => 'Patients Seen',    'value' => (int)($cur->patients_seen ?? 0), 'prev' => (int)($prev->patients_seen ?? 0)],
            ['label' => 'Today\'s Appts',   'value' => (int)($cur->today_appts ?? 0)],
        ];

        // Today's schedule
        $schedule = HearMed_DB::get_results(
            "SELECT a.start_time, p.patient_number, sv.service_name,
                    s.first_name || ' ' || LEFT(s.last_name, 1) || '.' AS staff_short,
                    a.appointment_status
             FROM hearmed_core.appointments a
             JOIN hearmed_core.patients p ON p.id = a.patient_id
             JOIN hearmed_reference.services sv ON sv.id = a.service_id
             LEFT JOIN hearmed_reference.staff s ON s.id = a.staff_id
             WHERE a.clinic_id = $1 AND a.appointment_date = CURRENT_DATE
             AND a.appointment_status NOT IN ('Cancelled')
             ORDER BY a.start_time",
            [$clinic_id]
        ) ?: [];

        $sched_out = [];
        foreach ($schedule as $row) {
            $st = $row->appointment_status;
            $status = in_array($st, ['Arrived', 'In Progress', 'Completed']) ? 'Arrived' : ($st === 'Confirmed' ? 'Confirmed' : 'Pending');
            $sched_out[] = [
                'time'     => substr($row->start_time, 0, 5),
                'h_number' => $row->patient_number ?? "\xe2\x80\x94",
                'service'  => $row->service_name,
                'staff'    => $row->staff_short ?? "\xe2\x80\x94",
                'status'   => $status,
            ];
        }

        // Weekly checkins (Mon-Sun of current week)
        $week_start = date('Y-m-d', strtotime('monday this week'));
        $week_end   = date('Y-m-d', strtotime('sunday this week'));
        $weekly = HearMed_DB::get_results(
            "SELECT EXTRACT(ISODOW FROM appointment_date)::int AS dow, COUNT(*) AS cnt
             FROM hearmed_core.appointments
             WHERE clinic_id = $1 AND appointment_date BETWEEN $2 AND $3
             AND appointment_status IN ('Completed', 'Arrived', 'In Progress')
             GROUP BY dow ORDER BY dow",
            [$clinic_id, $week_start, $week_end]
        ) ?: [];
        $checkins = [0,0,0,0,0,0,0];
        foreach ($weekly as $w) {
            $checkins[((int)$w->dow) - 1] = (int) $w->cnt;
        }

        return [
            'stats'           => $stats,
            'schedule'        => $sched_out,
            'weekly_checkins' => $checkins,
        ];
    }

    /* ─── COMMISSION CALCULATION ─── */
    private static function get_commission_data($staff, $start, $end) {
        $sid = (int) $staff->id;
        $role = strtolower($staff->role);
        $is_dispenser = in_array($role, ['dispenser', 'audiologist']);
        $base_salary = (float) ($staff->base_salary ?? 0);
        $clinic_id = (int) ($staff->primary_clinic_id ?? 0);

        if ($is_dispenser) {
            // Sum hearing aid revenue for this dispenser
            $ha_revenue = (float) HearMed_DB::get_var(
                "SELECT COALESCE(SUM(ii.line_total), 0)
                 FROM hearmed_core.invoices i
                 JOIN hearmed_core.invoice_items ii ON ii.invoice_id = i.id
                 JOIN hearmed_reference.products p ON p.id = ii.item_id AND p.item_type = 'product'
                 WHERE i.staff_id = $1 AND i.invoice_date BETWEEN $2 AND $3
                 AND i.payment_status != 'Refunded' AND ii.item_type = 'product'",
                [$sid, $start, $end]
            );

            // Pipeline hearing aid value
            $pipeline_ha = (float) HearMed_DB::get_var(
                "SELECT COALESCE(SUM(oi.line_total), 0)
                 FROM hearmed_core.orders o
                 JOIN hearmed_core.order_items oi ON oi.order_id = o.id
                 JOIN hearmed_reference.products p ON p.id = oi.item_id AND p.item_type = 'product'
                 WHERE o.staff_id = $1 AND o.order_date BETWEEN $2 AND $3
                 AND o.current_status IN ('Awaiting Fitting','Approved','Ordered','Received')
                 AND oi.item_type = 'product'",
                [$sid, $start, $end]
            );

            $pipeline_count = (int) HearMed_DB::get_var(
                "SELECT COUNT(DISTINCT o.id) FROM hearmed_core.orders o
                 WHERE o.staff_id = $1 AND o.order_date BETWEEN $2 AND $3
                 AND o.current_status IN ('Awaiting Fitting','Approved','Ordered','Received')",
                [$sid, $start, $end]
            );

            // Get tiered commission rules
            $rules = HearMed_DB::get_results(
                "SELECT bracket_from, bracket_to, rate_pct FROM hearmed_admin.commission_rules
                 WHERE role_type IN ('dispenser','audiologist') AND rule_type = 'tiered'
                 AND applies_to = 'hearing_aids' AND is_active = true
                 ORDER BY bracket_from",
                []
            ) ?: [];

            // Calculate tiered commission
            $comm_earned = 0;
            $tiers_display = [];
            $remaining = $ha_revenue;
            foreach ($rules as $rule) {
                $from = (float) $rule->bracket_from;
                $to   = $rule->bracket_to !== null ? (float) $rule->bracket_to : PHP_FLOAT_MAX;
                $rate = (float) $rule->rate_pct;
                $bracket_size = $to - $from;
                $in_bracket = min(max($remaining, 0), $bracket_size);
                $earned = $in_bracket * ($rate / 100);
                $comm_earned += $earned;
                $remaining -= $in_bracket;

                $tiers_display[] = [
                    'bracket' => "\xe2\x82\xAC" . number_format($from, 0) . " \xe2\x80\x93 " . ($rule->bracket_to !== null ? "\xe2\x82\xAC" . number_format($to, 0) : "\xe2\x80\x94"),
                    'rate'    => $rate . '%',
                    'earned'  => round($earned, 2),
                ];
            }

            // Calculate commission if pipeline also fitted
            $comm_if_fitted = 0;
            $remaining_proj = $ha_revenue + $pipeline_ha;
            foreach ($rules as $rule) {
                $from = (float) $rule->bracket_from;
                $to   = $rule->bracket_to !== null ? (float) $rule->bracket_to : PHP_FLOAT_MAX;
                $rate = (float) $rule->rate_pct;
                $bracket_size = $to - $from;
                $in_bracket = min(max($remaining_proj, 0), $bracket_size);
                $comm_if_fitted += $in_bracket * ($rate / 100);
                $remaining_proj -= $in_bracket;
            }

            // Returns: 10% flat deduction
            $return_value = (float) HearMed_DB::get_var(
                "SELECT COALESCE(SUM(cn.amount), 0) FROM hearmed_core.credit_notes cn
                 JOIN hearmed_core.orders o ON o.id = cn.order_id
                 WHERE o.staff_id = $1 AND cn.credit_date BETWEEN $2 AND $3",
                [$sid, $start, $end]
            );
            $return_deductions = round($return_value * 0.10, 2);

            $net_commission = round($comm_earned - $return_deductions, 2);
            $current_pay = round($base_salary + $net_commission, 2);
            $net_if_fitted = round($comm_if_fitted - $return_deductions, 2);
            $projected_pay = round($base_salary + $net_if_fitted, 2);

            return [
                'base_salary'         => $base_salary,
                'invoiced_total'      => $ha_revenue,
                'commission_earned'   => round($comm_earned, 2),
                'commission_if_fitted'=> round($comm_if_fitted, 2),
                'return_deductions'   => $return_deductions,
                'current_pay'         => $current_pay,
                'projected_pay'       => $projected_pay,
                'pipeline_value'      => $pipeline_ha,
                'pipeline_count'      => $pipeline_count,
                'tiers'               => $tiers_display,
            ];

        } else {
            // CA / Reception: 1% of all hearing aids in their clinic
            $clinic_ha = (float) HearMed_DB::get_var(
                "SELECT COALESCE(SUM(ii.line_total), 0)
                 FROM hearmed_core.invoices i
                 JOIN hearmed_core.invoice_items ii ON ii.invoice_id = i.id
                 JOIN hearmed_reference.products p ON p.id = ii.item_id AND p.item_type = 'product'
                 WHERE i.clinic_id = $1 AND i.invoice_date BETWEEN $2 AND $3
                 AND i.payment_status != 'Refunded' AND ii.item_type = 'product'",
                [$clinic_id, $start, $end]
            );

            $rule = HearMed_DB::get_row(
                "SELECT rate_pct FROM hearmed_admin.commission_rules
                 WHERE role_type = $1 AND is_active = true LIMIT 1",
                [$role]
            );
            $rate = $rule ? (float) $rule->rate_pct : 1.0;
            $comm = round($clinic_ha * ($rate / 100), 2);

            return [
                'base_salary'        => $base_salary,
                'commission_earned'  => $comm,
                'current_pay'        => round($base_salary + $comm, 2),
                'projected_pay'      => round($base_salary + $comm, 2),
                'note'               => $comm > 0 
                    ? "1% of \xe2\x82\xAC" . number_format($clinic_ha, 0) . " clinic hearing aid sales"
                    : "Base salary \xe2\x80\x94 1% clinic HA commission applies when sales recorded",
            ];
        }
    }

    /* ─── ACTIVITY FEED ─── */
    private static function get_activity($staff_id, $start, $end) {
        // Recent orders + credit notes
        $rows = HearMed_DB::get_results(
            "(SELECT 'order' AS src, o.id, o.order_number AS ref, o.grand_total AS amount,
                     o.current_status AS status, o.updated_at,
                     p.patient_number, p.first_name || ' ' || p.last_name AS patient_name,
                     (SELECT string_agg(pr.product_name, ', ')
                      FROM hearmed_core.order_items oi
                      JOIN hearmed_reference.products pr ON pr.id = oi.item_id
                      WHERE oi.order_id = o.id AND oi.item_type = 'product' LIMIT 1) AS product_desc
             FROM hearmed_core.orders o
             JOIN hearmed_core.patients p ON p.id = o.patient_id
             WHERE o.staff_id = $1 AND o.order_date BETWEEN $2 AND $3
             ORDER BY o.updated_at DESC LIMIT 8)
            UNION ALL
            (SELECT 'credit' AS src, cn.id, cn.credit_note_number AS ref, cn.amount,
                    'Return' AS status, cn.updated_at,
                    p.patient_number, p.first_name || ' ' || p.last_name AS patient_name,
                    cn.reason AS product_desc
             FROM hearmed_core.credit_notes cn
             JOIN hearmed_core.patients p ON p.id = cn.patient_id
             WHERE cn.created_by = $1 AND cn.credit_date BETWEEN $2 AND $3
             ORDER BY cn.updated_at DESC LIMIT 3)
            ORDER BY updated_at DESC LIMIT 10",
            [$staff_id, $start, $end]
        ) ?: [];

        $out = [];
        foreach ($rows as $r) {
            $is_return = $r->src === 'credit';
            $is_complete = in_array($r->status, ['Complete', 'Fitted']);
            $is_pipeline = in_array($r->status, ['Awaiting Fitting', 'Approved', 'Ordered', 'Received']);

            if ($is_return) {
                $type = 'return'; $label = 'Return';
            } elseif ($is_complete) {
                $type = 'sale'; $label = 'Sale';
            } elseif ($r->status === 'Awaiting Fitting') {
                $type = 'fitting'; $label = 'Fitting';
            } else {
                $type = 'other'; $label = ucfirst($r->status);
            }

            $ago = human_time_diff(strtotime($r->updated_at), current_time('timestamp'));

            $out[] = [
                'type'   => $type,
                'label'  => $label,
                'text'   => $r->product_desc ?: $r->ref,
                'detail' => ($r->patient_number ?? '') . " \xC2\xB7 " . ($r->patient_name ?? ''),
                'amount' => $is_return ? -abs((float)$r->amount) : ($is_complete ? (float)$r->amount : null),
                'time'   => $ago . ' ago',
            ];
        }
        return $out;
    }

    /* ─── HELPER: Get KPI target (per-staff or global fallback) ─── */
    private static function get_target($staff_id, $key, $default) {
        // Try staff-specific first
        $val = HearMed_DB::get_var(
            "SELECT target_value FROM hearmed_admin.kpi_targets
             WHERE staff_id = $1 AND target_name = $2 AND is_active = true",
            [$staff_id, $key]
        );
        if ($val !== null) return (float) $val;

        // Fall back to global
        $val = HearMed_DB::get_var(
            "SELECT target_value FROM hearmed_admin.kpi_targets
             WHERE staff_id IS NULL AND target_name = $1 AND is_active = true",
            [$key]
        );
        return $val !== null ? (float) $val : $default;
    }
}

HearMed_KPI::init();
