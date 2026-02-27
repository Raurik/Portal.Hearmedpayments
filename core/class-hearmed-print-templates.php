<?php
/**
 * HearMed Print Template Engine
 * 
 * Renders Invoice, Order, Repair Docket, and Credit Note documents
 * using admin-configurable layout settings (section order, fonts, colours, toggles).
 * 
 * Settings stored as wp_option: hm_print_template_{type}
 * 
 * Usage:
 *   $html = HearMed_Print_Templates::render('invoice', $data);
 *   $html = HearMed_Print_Templates::render('order', $data);
 *   $html = HearMed_Print_Templates::render('repair', $data);
 *   $html = HearMed_Print_Templates::render('creditnote', $data);
 * 
 * @package HearMed_Portal
 * @since   5.4.0
 */
if (!defined('ABSPATH')) exit;

class HearMed_Print_Templates {

    /* ──────────────────────────────────────────────
       DEFAULTS — used if no saved settings exist
       ────────────────────────────────────────────── */
    private static $defaults = [
        'invoice' => [
            'companyName'    => 'HearMed Acoustic Health Care Ltd',
            'tagline'        => 'Payment Receipt',
            'logo'           => true,
            'headerFont'     => 'Cormorant Garamond',
            'headerSize'     => 18,
            'headerColor'    => '#0BB4C4',
            'accentColor'    => '#0BB4C4',
            'invoiceMeta'    => true,
            'clinicPhone'    => true,
            'patient'        => true,
            'patientAddress' => true,
            'tableFont'      => 'Source Sans 3',
            'tableSize'      => 11,
            'vatLabel'       => 'VAT (23%)',
            'prsi'           => true,
            'serials'        => true,
            'payments'       => true,
            'footerLine1'    => 'Thank you for choosing HearMed.',
            'footerLine2'    => '',
            'footerFont'     => 'Source Sans 3',
            'footerSize'     => 9,
            'footerColor'    => '#94a3b8',
            'sections'       => ['companyHeader','patient','patientAddress','itemsTable','prsi','serials','payments','footer'],
        ],
        'order' => [
            'companyName'    => 'HearMed Acoustic Health Care Ltd',
            'tagline'        => 'Hearing Aid Order',
            'logo'           => true,
            'headerFont'     => 'Cormorant Garamond',
            'headerSize'     => 18,
            'headerColor'    => '#0BB4C4',
            'accentColor'    => '#0BB4C4',
            'orderMeta'      => true,
            'showPatientDOB' => true,
            'showPatientPhone'=> true,
            'showPatientPPS' => false,
            'clinicInfo'     => true,
            'pricing'        => true,
            'earMoulds'      => true,
            'notes'          => true,
            'approvalInfo'   => true,
            'footerLine1'    => '',
            'footerLine2'    => '',
            'footerFont'     => 'Source Sans 3',
            'footerSize'     => 9,
            'footerColor'    => '#94a3b8',
            'sections'       => ['companyHeader','patient','itemsTable','pricing','earMoulds','notes','approvalInfo','footer'],
        ],
        'repair' => [
            'companyName'    => 'HearMed Acoustic Health Care Ltd',
            'tagline'        => 'Repair Docket',
            'logo'           => true,
            'headerFont'     => 'Cormorant Garamond',
            'headerSize'     => 18,
            'headerColor'    => '#0BB4C4',
            'accentColor'    => '#0BB4C4',
            'repairRef'      => true,
            'showPatientPhone'=> true,
            'showPatientAddress'=> false,
            'device'         => true,
            'warranty'       => true,
            'faultDesc'      => true,
            'dateTracking'   => true,
            'returnClinic'   => 'Tullamore',
            'showReturnAddress'=> true,
            'signature'      => false,
            'footerLine1'    => 'Estimated turnaround: 5–10 working days.',
            'footerLine2'    => '',
            'footerFont'     => 'Source Sans 3',
            'footerSize'     => 9,
            'footerColor'    => '#94a3b8',
            'sections'       => ['companyHeader','patient','warranty','faultDesc','dateTracking','returnAddress','signature','footer'],
        ],
        'creditnote' => [
            'companyName'    => 'HearMed Acoustic Health Care Ltd',
            'tagline'        => 'Credit Note',
            'logo'           => true,
            'headerFont'     => 'Cormorant Garamond',
            'headerSize'     => 18,
            'headerColor'    => '#0BB4C4',
            'accentColor'    => '#0BB4C4',
            'creditMeta'     => true,
            'originalInvoice'=> true,
            'patientAddress' => true,
            'creditReason'   => true,
            'vatLabel'       => 'VAT (23%)',
            'refundMethod'   => true,
            'exchangeDetails'=> false,
            'footerLine1'    => 'Credit processed by HearMed Acoustic Health Care Ltd.',
            'footerLine2'    => '',
            'footerFont'     => 'Source Sans 3',
            'footerSize'     => 9,
            'footerColor'    => '#94a3b8',
            'sections'       => ['companyHeader','patient','creditReason','itemsTable','refundMethod','exchangeDetails','footer'],
        ],
    ];

    /* ──────────────────────────────────────────────
       Clinic addresses for repair return
       ────────────────────────────────────────────── */
    private static function get_clinic_addresses() {
        $db = HearMed_DB::instance();
        $rows = $db->get_results("SELECT clinic_name, CONCAT_WS(', ', address_line1, city, county, COALESCE(postcode, '')) AS full_address, phone FROM hearmed_reference.clinics WHERE is_active = true ORDER BY clinic_name");
        $map = [];
        foreach ($rows ?: [] as $c) {
            $map[$c->clinic_name] = $c->full_address ?: '';
        }
        return $map;
    }

    /* ──────────────────────────────────────────────
       SETTINGS: load / save
       ────────────────────────────────────────────── */
    public static function get_settings(string $type): array {
        $saved = HearMed_Settings::get("hm_print_template_{$type}", null);
        if ($saved && is_string($saved)) {
            $saved = json_decode($saved, true);
        }
        $defaults = self::$defaults[$type] ?? [];
        return is_array($saved) ? array_merge($defaults, $saved) : $defaults;
    }

    public static function save_settings(string $type, array $settings): bool {
        $allowed = ['invoice', 'order', 'repair', 'creditnote'];
        if (!in_array($type, $allowed)) return false;
        return HearMed_Settings::set("hm_print_template_{$type}", wp_json_encode($settings));
    }

    /* ──────────────────────────────────────────────
       AJAX: save / load settings
       ────────────────────────────────────────────── */
    public static function register_ajax() {
        add_action('wp_ajax_hm_save_print_template', [__CLASS__, 'ajax_save']);
        add_action('wp_ajax_hm_load_print_template', [__CLASS__, 'ajax_load']);
    }

    public static function ajax_save() {
        check_ajax_referer('hm_nonce', 'nonce');
        if (!HearMed_Auth::can('manage_settings')) {
            wp_send_json_error('Permission denied');
        }
        $type     = sanitize_text_field($_POST['template_type'] ?? '');
        $settings = json_decode(stripslashes($_POST['settings'] ?? '{}'), true);
        if (!$type || !is_array($settings)) wp_send_json_error('Invalid data');
        self::save_settings($type, $settings);
        wp_send_json_success(['saved' => true]);
    }

    public static function ajax_load() {
        check_ajax_referer('hm_nonce', 'nonce');
        if (!is_user_logged_in()) wp_send_json_error('Not logged in');
        $type = sanitize_text_field($_POST['template_type'] ?? $_GET['template_type'] ?? '');
        if (!$type) wp_send_json_error('Missing type');
        wp_send_json_success(self::get_settings($type));
    }

    /* ──────────────────────────────────────────────
       RENDER: main entry point
       ────────────────────────────────────────────── */

    /**
     * Render a print-ready HTML document.
     *
     * @param string $type  invoice|order|repair|creditnote
     * @param object $data  Data object with all fields needed for the template
     * @return string       Complete HTML document
     */
    public static function render(string $type, object $data): string {
        $s = self::get_settings($type);
        $accent   = $s['accentColor'] ?? '#0BB4C4';
        $sections = $s['sections'] ?? array_keys(self::$defaults[$type] ?? []);

        $google_fonts = self::collect_fonts($s);
        $font_link = '';
        if (!empty($google_fonts)) {
            $families = implode('&family=', array_map(function($f) {
                return str_replace(' ', '+', $f) . ':wght@400;600;700';
            }, $google_fonts));
            $font_link = '<link href="https://fonts.googleapis.com/css2?family=' . $families . '&display=swap" rel="stylesheet">';
        }

        ob_start();
        ?><!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title><?php echo esc_attr(self::get_title($type, $s, $data)); ?></title>
<?php echo $font_link; ?>
<style>
:root { --hm-accent: <?php echo esc_attr($accent); ?>; --hm-navy: #151B33; }
@page { size: A4; margin: 15mm; }
@media print { body { -webkit-print-color-adjust: exact; } .no-print { display: none !important; } }
* { margin: 0; padding: 0; box-sizing: border-box; }
body {
    font-family: '<?php echo esc_attr($s['tableFont'] ?? 'Source Sans 3'); ?>', -apple-system, sans-serif;
    font-size: <?php echo intval($s['tableSize'] ?? 11); ?>px;
    color: #1e293b; line-height: 1.5; padding: 30px; max-width: 700px; margin: 0 auto;
}
/* Header */
.hm-print-header { display: flex; justify-content: space-between; align-items: flex-start; padding-bottom: 12px; border-bottom: 3px solid var(--hm-accent); margin-bottom: 16px; }
.hm-print-logo { width: 48px; height: 48px; border-radius: 8px; background: var(--hm-accent); display: flex; align-items: center; justify-content: center; color: #fff; font-weight: 800; font-size: 18px; margin-bottom: 6px; }
.hm-print-company { font-family: '<?php echo esc_attr($s['headerFont'] ?? 'Cormorant Garamond'); ?>', serif; font-size: <?php echo intval($s['headerSize'] ?? 18); ?>px; font-weight: 700; color: <?php echo esc_attr($s['headerColor'] ?? '#0BB4C4'); ?>; }
.hm-print-tagline { font-size: 10px; color: #94a3b8; }
.hm-print-meta { text-align: right; font-size: 10px; color: #64748b; }
.hm-print-meta strong { color: #1e293b; display: block; }
/* Info boxes */
.hm-print-row { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 12px; }
.hm-print-box { background: #f8fafc; border-radius: 6px; padding: 8px 12px; }
.hm-print-box-label { font-size: 9px; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: .3px; margin-bottom: 4px; }
.hm-print-box strong { font-size: 12px; }
.hm-print-box .sub { font-size: 10px; color: #64748b; margin-top: 1px; }
/* Tables */
.hm-print-section-title { font-size: 9px; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: .3px; margin-bottom: 4px; }
table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
th { background: #f8fafc; text-align: left; padding: 5px 8px; font-size: 9px; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: .3px; border-bottom: 2px solid #e2e8f0; }
td { padding: 5px 8px; border-bottom: 1px solid #f1f5f9; }
.money { text-align: right; }
tfoot td { font-weight: 600; border-bottom: none; }
.total-row td { font-weight: 700; border-top: 2px solid #e2e8f0; padding-top: 6px; color: var(--hm-accent); }
/* Badges */
.badge { display: inline-block; padding: 2px 10px; border-radius: 4px; font-size: 10px; font-weight: 700; margin-top: 4px; }
.badge-paid { background: #d1fae5; color: #065f46; }
.badge-approved { background: #dbeafe; color: #1e40af; }
.badge-credit { background: #fef3cd; color: #92400e; }
.badge-warranty-in { background: #dbeafe; color: #1e40af; }
.badge-warranty-out { background: #fee2e2; color: #991b1b; }
/* Payments */
.hm-print-payments { background: #f0fdfa; border-radius: 6px; padding: 10px 12px; margin-bottom: 12px; }
.hm-print-payments h4 { font-size: 9px; font-weight: 600; color: #065f46; text-transform: uppercase; margin-bottom: 6px; }
.pay-row { display: flex; justify-content: space-between; font-size: 10px; padding: 2px 0; border-bottom: 1px solid #d1fae5; }
.pay-row:last-child { border-bottom: none; font-weight: 700; }
/* Fault / Reason boxes */
.hm-print-fault { border: 1px solid #fde68a; background: #fffbeb; border-radius: 6px; padding: 8px 12px; margin-bottom: 12px; }
.hm-print-fault-label { font-size: 9px; font-weight: 600; color: #92400e; text-transform: uppercase; margin-bottom: 4px; }
.hm-print-fault p { font-size: 10px; color: #78350f; }
.hm-print-credit-reason { background: #fef2f2; border: 1px solid #fecaca; border-radius: 6px; padding: 8px 12px; margin-bottom: 12px; }
.hm-print-credit-reason-label { font-size: 9px; font-weight: 600; color: #991b1b; text-transform: uppercase; margin-bottom: 2px; }
.hm-print-credit-reason p { font-size: 10px; color: #7f1d1d; }
/* Return address */
.hm-print-return { background: #f0f9ff; border: 1px solid #bae6fd; border-radius: 6px; padding: 8px 12px; margin-bottom: 12px; }
.hm-print-return-label { font-size: 9px; font-weight: 600; color: #0369a1; text-transform: uppercase; margin-bottom: 4px; }
/* Date tracking */
.hm-print-dates { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 8px; margin-bottom: 12px; }
.hm-print-date-box { background: #f8fafc; border-radius: 6px; padding: 8px 12px; text-align: center; }
.hm-print-date-label { font-size: 9px; font-weight: 600; color: #64748b; text-transform: uppercase; }
.hm-print-date-val { font-size: 12px; font-weight: 700; }
/* Serials */
.hm-print-serials { font-size: 10px; color: #475569; margin-bottom: 12px; }
.hm-print-serials code { font-family: monospace; background: #f1f5f9; padding: 0 4px; border-radius: 2px; }
/* Notes */
.hm-print-notes { border: 1px dashed #e2e8f0; border-radius: 6px; padding: 8px 12px; margin-bottom: 12px; }
.hm-print-notes-label { font-size: 9px; font-weight: 600; color: #64748b; text-transform: uppercase; margin-bottom: 4px; }
.hm-print-notes p { font-size: 10px; color: #64748b; }
/* Approval */
.hm-print-approval { background: #f0fdfa; border: 1px solid rgba(11,180,196,0.2); border-radius: 6px; padding: 8px 12px; margin-bottom: 12px; }
.hm-print-approval-label { font-size: 9px; font-weight: 600; color: var(--hm-accent); text-transform: uppercase; margin-bottom: 2px; }
/* Exchange */
.hm-print-exchange { background: #f0fdfa; border: 1px solid rgba(11,180,196,0.2); border-radius: 6px; padding: 8px 12px; margin-bottom: 12px; }
/* Ear moulds */
.hm-print-moulds { background: #f8fafc; border-radius: 6px; padding: 8px 12px; margin-bottom: 12px; }
.hm-print-moulds-label { font-size: 9px; font-weight: 600; color: #64748b; text-transform: uppercase; margin-bottom: 4px; }
/* Signature */
.hm-print-signature { margin-top: 24px; border-top: 1px solid #1e293b; padding-top: 4px; width: 60%; }
.hm-print-signature span { font-size: 9px; color: #64748b; }
/* Footer */
.hm-print-footer { margin-top: 16px; padding-top: 8px; border-top: 1px solid #e2e8f0; text-align: center; font-family: '<?php echo esc_attr($s['footerFont'] ?? 'Source Sans 3'); ?>', sans-serif; font-size: <?php echo intval($s['footerSize'] ?? 9); ?>px; color: <?php echo esc_attr($s['footerColor'] ?? '#94a3b8'); ?>; }
/* Print button */
.print-btn { position: fixed; top: 10px; right: 10px; padding: 10px 20px; background: var(--hm-accent); color: #fff; border: none; border-radius: 8px; cursor: pointer; font-size: 13px; font-weight: 600; z-index: 100; }
.print-btn:hover { opacity: 0.9; }
</style>
</head>
<body>
<button class="print-btn no-print" onclick="window.print()">Print / Save PDF</button>
<?php
        // Render sections in saved order
        foreach ($sections as $sec_id) {
            echo self::render_section($type, $sec_id, $s, $data);
        }
?>
</body>
</html>
<?php
        return ob_get_clean();
    }

    /* ──────────────────────────────────────────────
       SECTION RENDERERS
       ────────────────────────────────────────────── */
    private static function render_section(string $type, string $sec_id, array $s, object $d): string {
        $method = "section_{$type}_{$sec_id}";
        if (method_exists(__CLASS__, $method)) {
            return self::$method($s, $d);
        }
        // Try generic sections
        $generic = "section_{$sec_id}";
        if (method_exists(__CLASS__, $generic)) {
            return self::$generic($s, $d, $type);
        }
        return '';
    }

    /* ── SHARED: Company Header ── */
    private static function section_companyHeader(array $s, object $d, string $type): string {
        $company = esc_html($s['companyName'] ?? 'HearMed Acoustic Health Care Ltd');
        $tagline = esc_html($s['tagline'] ?? '');
        $logo_url = HearMed_Settings::get('hm_report_logo_url', '');
        
        ob_start(); ?>
        <div class="hm-print-header">
            <div>
                <?php if ($s['logo'] ?? true): ?>
                    <?php if ($logo_url): ?>
                    <img src="<?php echo esc_url($logo_url); ?>" alt="Logo" style="max-width:48px;max-height:48px;border-radius:8px;object-fit:contain;margin-bottom:6px;">
                    <?php else: ?>
                    <div class="hm-print-logo">H</div>
                    <?php endif; ?>
                <?php endif; ?>
                <div class="hm-print-company"><?php echo $company; ?></div>
                <?php if ($tagline): ?><div class="hm-print-tagline"><?php echo $tagline; ?></div><?php endif; ?>
            </div>
            <div class="hm-print-meta">
                <?php echo self::render_meta($type, $s, $d); ?>
            </div>
        </div>
        <?php return ob_get_clean();
    }

    private static function render_meta(string $type, array $s, object $d): string {
        ob_start();
        switch ($type) {
            case 'invoice':
                if ($s['invoiceMeta'] ?? true): ?>
                    <strong>Invoice: <?php echo esc_html($d->invoice_number ?? $d->order_number ?? ''); ?></strong>
                    <div>Date: <?php echo date('d M Y'); ?></div>
                    <div>Clinic: <?php echo esc_html($d->clinic_name ?? ''); ?></div>
                    <?php if ($s['clinicPhone'] ?? true): ?><div>Phone: <?php echo esc_html($d->clinic_phone ?? ''); ?></div><?php endif; ?>
                    <div class="badge badge-paid">PAID</div>
                <?php endif;
                break;
            case 'order':
                if ($s['orderMeta'] ?? true): ?>
                    <strong>Order: <?php echo esc_html($d->order_number ?? ''); ?></strong>
                    <div>Created: <?php echo esc_html(!empty($d->order_date) ? date('d M Y', strtotime($d->order_date)) : date('d M Y')); ?></div>
                    <div>Dispenser: <?php echo esc_html($d->dispenser_name ?? ''); ?></div>
                    <div class="badge badge-approved"><?php echo esc_html(strtoupper($d->order_status ?? 'APPROVED')); ?></div>
                <?php endif;
                break;
            case 'repair':
                if ($s['repairRef'] ?? true): ?>
                    <strong>Ref: <?php echo esc_html($d->repair_number ?? ''); ?></strong>
                    <div>Clinic: <?php echo esc_html($d->clinic_name ?? ''); ?></div>
                <?php endif;
                break;
            case 'creditnote':
                if ($s['creditMeta'] ?? true): ?>
                    <strong>CN: <?php echo esc_html($d->credit_note_number ?? ''); ?></strong>
                    <div>Date: <?php echo date('d M Y'); ?></div>
                    <?php if ($s['originalInvoice'] ?? true): ?>
                    <div>Original: <span style="color:var(--hm-accent);font-weight:600;"><?php echo esc_html($d->original_invoice_number ?? ''); ?></span></div>
                    <?php endif; ?>
                    <div class="badge badge-credit">CREDIT</div>
                <?php endif;
                break;
        }
        return ob_get_clean();
    }

    /* ── SHARED: Footer ── */
    private static function section_footer(array $s, object $d, string $type): string {
        $l1 = $s['footerLine1'] ?? '';
        $l2 = $s['footerLine2'] ?? '';
        if (!$l1 && !$l2) return '';
        ob_start(); ?>
        <div class="hm-print-footer">
            <?php if ($l1): ?><div><?php echo esc_html($l1); ?></div><?php endif; ?>
            <?php if ($l2): ?>
                <?php foreach (explode("\n", $l2) as $line): $line = trim($line); if ($line): ?>
                    <div style="font-size:<?php echo intval($s['footerSize'] ?? 9) - 1; ?>px;margin-top:1px;"><?php echo esc_html($line); ?></div>
                <?php endif; endforeach; ?>
            <?php endif; ?>
        </div>
        <?php return ob_get_clean();
    }

    /* ════════════════════════════════════════════════
       INVOICE SECTIONS
       ════════════════════════════════════════════════ */
    private static function section_invoice_patient(array $s, object $d): string {
        if (!($s['patient'] ?? true)) return '';
        ob_start(); ?>
        <div class="hm-print-box" style="margin-bottom:12px;">
            <strong><?php echo esc_html(($d->p_first ?? '') . ' ' . ($d->p_last ?? '')); ?></strong>
            <div class="sub">Patient #: <?php echo esc_html($d->patient_number ?? ''); ?></div>
            <?php if (($s['patientAddress'] ?? true) && !empty($d->address_line1)):
                $addr = array_filter([$d->address_line1 ?? '', $d->address_line2 ?? '', $d->city ?? '', $d->county ?? '', $d->eircode ?? '']);
            ?>
            <div class="sub"><?php echo esc_html(implode(', ', $addr)); ?></div>
            <?php endif; ?>
        </div>
        <?php return ob_get_clean();
    }
    private static function section_invoice_patientAddress() { return ''; }

    private static function section_invoice_itemsTable(array $s, object $d): string {
        $items   = $d->items ?? [];
        $invoice = $d->invoice ?? null;
        $order   = $d;
        ob_start(); ?>
        <div class="hm-print-section-title">Items</div>
        <table>
            <thead><tr>
                <th>Description</th><th>Ear</th><th>Qty</th><th class="money">Unit Price</th><th class="money">Total</th>
            </tr></thead>
            <tbody>
            <?php foreach ($items as $it): ?>
            <tr>
                <td><?php echo esc_html($it->product_name ?: ($it->item_description ?? '')); ?></td>
                <td><?php echo esc_html($it->ear_side ?: '—'); ?></td>
                <td><?php echo esc_html($it->quantity ?? 1); ?></td>
                <td class="money">€<?php echo number_format((float)($it->unit_price ?? $it->unit_retail_price ?? 0), 2); ?></td>
                <td class="money">€<?php echo number_format((float)($it->line_total ?? 0), 2); ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr><td colspan="4" class="money">Subtotal</td><td class="money">€<?php echo number_format((float)($invoice ? $invoice->subtotal : ($order->subtotal ?? 0)), 2); ?></td></tr>
                <?php if ((float)($invoice ? $invoice->discount_total : ($order->discount_total ?? 0)) > 0): ?>
                <tr><td colspan="4" class="money">Discount</td><td class="money">-€<?php echo number_format((float)($invoice ? $invoice->discount_total : $order->discount_total), 2); ?></td></tr>
                <?php endif; ?>
                <tr><td colspan="4" class="money"><?php echo esc_html($s['vatLabel'] ?? 'VAT (23%)'); ?></td><td class="money">€<?php echo number_format((float)($invoice ? $invoice->vat_total : ($order->vat_total ?? 0)), 2); ?></td></tr>
                <?php if (($s['prsi'] ?? true) && !empty($order->prsi_applicable)): ?>
                <tr><td colspan="4" class="money" style="color:var(--hm-accent);">PRSI Grant</td><td class="money" style="color:var(--hm-accent);">-€<?php echo number_format((float)($order->prsi_amount ?? 0), 2); ?></td></tr>
                <?php endif; ?>
                <tr class="total-row"><td colspan="4" class="money">Total Paid</td><td class="money">€<?php echo number_format((float)($invoice ? $invoice->grand_total : ($order->grand_total ?? 0)), 2); ?></td></tr>
            </tfoot>
        </table>
        <?php return ob_get_clean();
    }
    private static function section_invoice_vatLine() { return ''; }
    private static function section_invoice_tableFont() { return ''; }
    private static function section_invoice_prsi() { return ''; }
    private static function section_invoice_accentBar() { return ''; }

    private static function section_invoice_serials(array $s, object $d): string {
        if (!($s['serials'] ?? true) || empty($d->devices)) return '';
        ob_start(); ?>
        <div class="hm-print-serials">
            <strong>Device Serial Numbers:</strong><br>
            <?php foreach ($d->devices as $dev): ?>
                <?php echo esc_html($dev->product_name ?? 'Device'); ?>:
                <?php if (!empty($dev->serial_number_left)): ?> L: <code><?php echo esc_html($dev->serial_number_left); ?></code><?php endif; ?>
                <?php if (!empty($dev->serial_number_right)): ?> R: <code><?php echo esc_html($dev->serial_number_right); ?></code><?php endif; ?>
                <br>
            <?php endforeach; ?>
        </div>
        <?php return ob_get_clean();
    }

    private static function section_invoice_payments(array $s, object $d): string {
        if (!($s['payments'] ?? true) || empty($d->payments)) return '';
        ob_start(); ?>
        <div class="hm-print-payments">
            <h4>Payment Details</h4>
            <?php $total = 0; foreach ($d->payments as $pm): $total += (float)$pm->amount; ?>
            <div class="pay-row">
                <span><?php echo date('d M Y', strtotime($pm->payment_date)); ?> — <?php echo esc_html($pm->payment_method); ?></span>
                <span>€<?php echo number_format((float)$pm->amount, 2); ?></span>
            </div>
            <?php endforeach; ?>
            <div class="pay-row"><span>Total</span><span>€<?php echo number_format($total, 2); ?></span></div>
        </div>
        <?php return ob_get_clean();
    }

    /* ════════════════════════════════════════════════
       ORDER SECTIONS
       ════════════════════════════════════════════════ */
    private static function section_order_patient(array $s, object $d): string {
        ob_start(); ?>
        <div class="hm-print-row">
            <div class="hm-print-box">
                <div class="hm-print-box-label">Patient</div>
                <strong><?php echo esc_html(($d->p_first ?? '') . ' ' . ($d->p_last ?? '')); ?></strong>
                <div class="sub"><?php echo esc_html($d->patient_number ?? ''); ?></div>
                <?php if ($s['showPatientDOB'] ?? true): ?><div class="sub">DOB: <?php echo esc_html(!empty($d->date_of_birth) ? date('d/m/Y', strtotime($d->date_of_birth)) : '—'); ?></div><?php endif; ?>
                <?php if ($s['showPatientPhone'] ?? true): ?><div class="sub">Phone: <?php echo esc_html($d->phone ?? $d->mobile ?? '—'); ?></div><?php endif; ?>
                <?php if ($s['showPatientPPS'] ?? false): ?><div class="sub">PPS: <?php echo esc_html($d->pps_number ?? '—'); ?></div><?php endif; ?>
            </div>
            <?php if ($s['clinicInfo'] ?? true): ?>
            <div class="hm-print-box">
                <div class="hm-print-box-label">Clinic</div>
                <strong>HearMed — <?php echo esc_html($d->clinic_name ?? ''); ?></strong>
                <div class="sub"><?php echo esc_html($d->clinic_address ?? ''); ?></div>
                <?php if (!empty($d->clinic_phone)): ?><div class="sub"><?php echo esc_html($d->clinic_phone); ?></div><?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php return ob_get_clean();
    }
    private static function section_order_clinicInfo() { return ''; }

    private static function section_order_itemsTable(array $s, object $d): string {
        $items = $d->items ?? [];
        ob_start(); ?>
        <div class="hm-print-section-title">Order Items</div>
        <table>
            <thead><tr><th>Product</th><th>Code</th><th>Ear</th><th>Tech Level</th><th>Style</th><th>Qty</th></tr></thead>
            <tbody>
            <?php foreach ($items as $it): ?>
            <tr>
                <td><?php echo esc_html($it->product_name ?? $it->item_description ?? ''); ?></td>
                <td><?php echo esc_html($it->product_code ?? ''); ?></td>
                <td><?php echo esc_html($it->ear_side ?? ''); ?></td>
                <td><?php echo esc_html($it->tech_level ?? ''); ?></td>
                <td><?php echo esc_html($it->style ?? ''); ?></td>
                <td><?php echo (int)($it->quantity ?? 1); ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php return ob_get_clean();
    }

    private static function section_order_pricing(array $s, object $d): string {
        if (!($s['pricing'] ?? true)) return '';
        ob_start(); ?>
        <div style="border-top:1px solid #e2e8f0;padding-top:8px;margin-bottom:12px;">
            <?php
            $rows = [
                ['Subtotal', '€' . number_format((float)($d->subtotal ?? 0), 2), ''],
                ['Discount', !empty($d->discount_total) ? '-€' . number_format((float)$d->discount_total, 2) : '—', ''],
                [$s['vatLabel'] ?? 'VAT (23%)', '€' . number_format((float)($d->vat_total ?? 0), 2), ''],
            ];
            if (!empty($d->prsi_applicable)) {
                $rows[] = ['PRSI Grant', '-€' . number_format((float)($d->prsi_amount ?? 0), 2), 'color:var(--hm-accent)'];
            }
            foreach ($rows as [$label, $val, $style]): ?>
            <div style="display:flex;justify-content:flex-end;gap:32px;font-size:11px;padding:2px 0;<?php echo $style; ?>">
                <span style="color:#64748b;min-width:100px;text-align:right;"><?php echo esc_html($label); ?></span>
                <span style="font-weight:600;min-width:80px;text-align:right;"><?php echo $val; ?></span>
            </div>
            <?php endforeach; ?>
            <div style="display:flex;justify-content:flex-end;gap:32px;font-size:13px;padding:6px 0 0;border-top:2px solid #e2e8f0;margin-top:4px;">
                <span style="font-weight:700;color:var(--hm-accent);">Total</span>
                <span style="font-weight:700;color:var(--hm-accent);min-width:80px;text-align:right;">€<?php echo number_format((float)($d->grand_total ?? 0), 2); ?></span>
            </div>
        </div>
        <?php return ob_get_clean();
    }

    private static function section_order_earMoulds(array $s, object $d): string {
        if (!($s['earMoulds'] ?? true) || empty($d->ear_mould_type)) return '';
        ob_start(); ?>
        <div class="hm-print-moulds">
            <div class="hm-print-moulds-label">Ear Impressions</div>
            <div style="font-size:10px;">
                Type: <strong><?php echo esc_html($d->ear_mould_type ?? '—'); ?></strong>
                | Vent: <strong><?php echo esc_html($d->ear_mould_vent ?? '—'); ?></strong>
                | Material: <strong><?php echo esc_html($d->ear_mould_material ?? '—'); ?></strong>
            </div>
        </div>
        <?php return ob_get_clean();
    }

    private static function section_order_notes(array $s, object $d): string {
        if (!($s['notes'] ?? true) || empty($d->special_instructions) && empty($d->notes)) return '';
        ob_start(); ?>
        <div class="hm-print-notes">
            <div class="hm-print-notes-label">Special Instructions</div>
            <p><?php echo esc_html($d->special_instructions ?? $d->notes ?? ''); ?></p>
        </div>
        <?php return ob_get_clean();
    }

    private static function section_order_approvalInfo(array $s, object $d): string {
        if (!($s['approvalInfo'] ?? true) || empty($d->approved_by_name)) return '';
        ob_start(); ?>
        <div class="hm-print-approval">
            <div class="hm-print-approval-label">Approval</div>
            <div style="font-size:10px;">Approved by <strong><?php echo esc_html($d->approved_by_name); ?></strong>
            <?php if (!empty($d->approved_at)): ?> on <?php echo date('d M Y \\a\\t H:i', strtotime($d->approved_at)); ?><?php endif; ?></div>
        </div>
        <?php return ob_get_clean();
    }
    private static function section_order_accentBar() { return ''; }

    /* ════════════════════════════════════════════════
       REPAIR SECTIONS
       ════════════════════════════════════════════════ */
    private static function section_repair_patient(array $s, object $d): string {
        ob_start(); ?>
        <div class="hm-print-row">
            <div class="hm-print-box">
                <div class="hm-print-box-label">Patient</div>
                <strong><?php echo esc_html($d->patient_name ?? ''); ?></strong>
                <?php if ($s['showPatientPhone'] ?? true): ?><div class="sub">Phone: <?php echo esc_html($d->phone ?? $d->mobile ?? '—'); ?></div><?php endif; ?>
                <?php if ($s['showPatientAddress'] ?? false): ?><div class="sub"><?php echo esc_html($d->patient_address ?? ''); ?></div><?php endif; ?>
            </div>
            <?php if ($s['device'] ?? true): ?>
            <div class="hm-print-box">
                <div class="hm-print-box-label">Device</div>
                <strong><?php echo esc_html($d->product_name ?? ''); ?></strong>
                <div class="sub">Manufacturer: <?php echo esc_html($d->manufacturer_name ?? '—'); ?></div>
                <div class="sub">Serial: <code><?php echo esc_html($d->serial_number ?? '—'); ?></code></div>
            </div>
            <?php endif; ?>
        </div>
        <?php return ob_get_clean();
    }
    private static function section_repair_device() { return ''; }

    private static function section_repair_warranty(array $s, object $d): string {
        if (!($s['warranty'] ?? true)) return '';
        $in_warranty = !empty($d->under_warranty) && (function_exists('hm_pg_bool') ? hm_pg_bool($d->under_warranty) : $d->under_warranty);
        $cls = $in_warranty ? 'badge-warranty-in' : 'badge-warranty-out';
        $txt = $in_warranty ? 'UNDER WARRANTY' : 'OUT OF WARRANTY';
        return '<div style="margin-bottom:12px;"><span class="badge ' . $cls . '">' . $txt . '</span></div>';
    }

    private static function section_repair_faultDesc(array $s, object $d): string {
        if (!($s['faultDesc'] ?? true) || (empty($d->repair_reason) && empty($d->repair_notes))) return '';
        ob_start(); ?>
        <div class="hm-print-fault">
            <div class="hm-print-fault-label">Fault Description</div>
            <p><?php echo esc_html($d->repair_reason ?? ''); ?><?php if (!empty($d->repair_notes)): ?> — <?php echo esc_html($d->repair_notes); ?><?php endif; ?></p>
        </div>
        <?php return ob_get_clean();
    }

    private static function section_repair_dateTracking(array $s, object $d): string {
        if (!($s['dateTracking'] ?? true)) return '';
        $booked = !empty($d->date_booked) ? date('d M Y', strtotime($d->date_booked)) : '—';
        $sent   = !empty($d->date_sent)   ? date('d M Y', strtotime($d->date_sent))   : 'Pending…';
        $recvd  = !empty($d->date_received) ? date('d M Y', strtotime($d->date_received)) : 'Pending…';
        ob_start(); ?>
        <div class="hm-print-dates">
            <div class="hm-print-date-box"><div class="hm-print-date-label">Booked</div><div class="hm-print-date-val"><?php echo $booked; ?></div></div>
            <div class="hm-print-date-box"><div class="hm-print-date-label">Sent</div><div class="hm-print-date-val" style="color:var(--hm-accent);"><?php echo $sent; ?></div></div>
            <div class="hm-print-date-box"><div class="hm-print-date-label">Received</div><div class="hm-print-date-val" style="color:#94a3b8;"><?php echo $recvd; ?></div></div>
        </div>
        <?php return ob_get_clean();
    }

    private static function section_repair_returnAddress(array $s, object $d): string {
        if (!($s['showReturnAddress'] ?? true)) return '';
        $clinic = $s['returnClinic'] ?? ($d->clinic_name ?? 'Tullamore');
        // Try to get address from data or lookup
        $addr = $d->clinic_address ?? '';
        if (!$addr) {
            $addrs = self::get_clinic_addresses();
            $addr = $addrs[$clinic] ?? '';
        }
        ob_start(); ?>
        <div class="hm-print-return">
            <div class="hm-print-return-label">Return To</div>
            <strong>HearMed — <?php echo esc_html($clinic); ?></strong>
            <?php if ($addr): ?><div class="sub"><?php echo esc_html($addr); ?></div><?php endif; ?>
        </div>
        <?php return ob_get_clean();
    }

    private static function section_repair_signature(array $s, object $d): string {
        if (!($s['signature'] ?? false)) return '';
        return '<div class="hm-print-signature"><span>Patient Signature</span></div>';
    }
    private static function section_repair_accentBar() { return ''; }

    /* ════════════════════════════════════════════════
       CREDIT NOTE SECTIONS
       ════════════════════════════════════════════════ */
    private static function section_creditnote_patient(array $s, object $d): string {
        if (!($s['patient'] ?? true)) return '';
        ob_start(); ?>
        <div class="hm-print-row">
            <div class="hm-print-box">
                <div class="hm-print-box-label">Patient</div>
                <strong><?php echo esc_html(($d->p_first ?? '') . ' ' . ($d->p_last ?? $d->patient_name ?? '')); ?></strong>
                <div class="sub">Patient #: <?php echo esc_html($d->patient_number ?? ''); ?></div>
                <?php if (($s['patientAddress'] ?? true) && !empty($d->address_line1)):
                    $addr = array_filter([$d->address_line1 ?? '', $d->city ?? '', $d->county ?? '']);
                ?>
                <div class="sub"><?php echo esc_html(implode(', ', $addr)); ?></div>
                <?php endif; ?>
            </div>
            <?php if ($s['refundMethod'] ?? true): ?>
            <div class="hm-print-box">
                <div class="hm-print-box-label">Refund Method</div>
                <strong><?php echo esc_html($d->refund_type ?? 'cheque'); ?></strong>
                <?php if (!empty($d->cheque_number)): ?><div class="sub">Cheque #<?php echo esc_html($d->cheque_number); ?></div><?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php return ob_get_clean();
    }
    private static function section_creditnote_patientAddress() { return ''; }
    private static function section_creditnote_refundMethod() { return ''; }

    private static function section_creditnote_creditReason(array $s, object $d): string {
        if (!($s['creditReason'] ?? true) || empty($d->reason)) return '';
        ob_start(); ?>
        <div class="hm-print-credit-reason">
            <div class="hm-print-credit-reason-label">Reason for Credit</div>
            <p><?php echo esc_html($d->reason); ?></p>
        </div>
        <?php return ob_get_clean();
    }

    private static function section_creditnote_itemsTable(array $s, object $d): string {
        $items = $d->items ?? [];
        ob_start(); ?>
        <table>
            <thead><tr><th>Description</th><th>Qty</th><th class="money">Amount</th></tr></thead>
            <tbody>
            <?php foreach ($items as $it): ?>
            <tr>
                <td><?php echo esc_html($it->product_name ?? $it->item_description ?? ''); ?></td>
                <td><?php echo (int)($it->quantity ?? 1); ?></td>
                <td class="money">€<?php echo number_format((float)($it->line_total ?? 0), 2); ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr><td colspan="2" class="money">Subtotal</td><td class="money">€<?php echo number_format((float)($d->subtotal ?? 0), 2); ?></td></tr>
                <tr><td colspan="2" class="money"><?php echo esc_html($s['vatLabel'] ?? 'VAT'); ?></td><td class="money">€<?php echo number_format((float)($d->vat_total ?? 0), 2); ?></td></tr>
                <tr class="total-row"><td colspan="2" class="money">Total Credit</td><td class="money">-€<?php echo number_format((float)($d->grand_total ?? 0), 2); ?></td></tr>
            </tfoot>
        </table>
        <?php return ob_get_clean();
    }
    private static function section_creditnote_vatLine() { return ''; }
    private static function section_creditnote_originalInvoice() { return ''; }

    private static function section_creditnote_exchangeDetails(array $s, object $d): string {
        if (!($s['exchangeDetails'] ?? false) || empty($d->exchange_order_number)) return '';
        ob_start(); ?>
        <div class="hm-print-exchange">
            <div style="font-size:9px;font-weight:600;color:var(--hm-accent);text-transform:uppercase;margin-bottom:2px;">Exchange — New Order</div>
            <div style="font-size:10px;">New Order: <strong><?php echo esc_html($d->exchange_order_number); ?></strong>
            <?php if (!empty($d->exchange_product)): ?> — <?php echo esc_html($d->exchange_product); ?><?php endif; ?></div>
        </div>
        <?php return ob_get_clean();
    }
    private static function section_creditnote_accentBar() { return ''; }

    /* ──────────────────────────────────────────────
       HELPERS
       ────────────────────────────────────────────── */
    private static function collect_fonts(array $s): array {
        $fonts = [];
        foreach (['headerFont', 'tableFont', 'footerFont'] as $key) {
            if (!empty($s[$key]) && !in_array($s[$key], $fonts)) {
                $fonts[] = $s[$key];
            }
        }
        return $fonts;
    }

    private static function get_title(string $type, array $s, object $d): string {
        switch ($type) {
            case 'invoice':    return 'Receipt — ' . ($d->invoice_number ?? $d->order_number ?? '');
            case 'order':      return 'Order — ' . ($d->order_number ?? '');
            case 'repair':     return 'Repair Docket — ' . ($d->repair_number ?? '');
            case 'creditnote': return 'Credit Note — ' . ($d->credit_note_number ?? '');
        }
        return 'HearMed Document';
    }
}

// Register AJAX handlers
HearMed_Print_Templates::register_ajax();
