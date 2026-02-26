<?php
/**
 * HearMed Admin — Products & Services
 * Shortcode: [hearmed_products]
 * 5 primary tabs: Hearing Aids, Services, Bundled Items, Accessories, Consumables
 * All stored in hearmed_reference.products with item_type discriminator.
 *
 * @package HearMed_Portal
 * @since   5.2.0
 */
if (!defined('ABSPATH')) exit;

class HearMed_Admin_Products {

    private static $item_types = [
        'product'    => 'Hearing Aids',
        'service'    => 'Services',
        'bundled'    => 'Bundled Items',
        'accessory'  => 'Accessories',
        'consumable' => 'Consumables',
    ];

    private static $ha_styles = ['BTE','RIC','ITE','ITC','CIC','IIC','CROS','BiCROS','Other'];
    private static $ha_classes = ['Custom','Ready-Fit'];

    private static $speaker_powers = [
        '60'  => 'Small (60)',
        '85'  => 'Medium (85)',
        '100' => 'Power (100)',
        '105' => 'Power 105',
        'mould' => 'Mould',
    ];

    private static $dome_types = ['Open','Closed','Power','Double','Tulip','Bass','Other'];
    private static $dome_sizes = ['XS','S','M','L','XL'];

    /** Build VAT options dynamically from finance settings */
    private static function get_vat_options() {
        $rates = [
            'Hearing Aids'       => get_option('hm_vat_hearing_aids', '0'),
            'Services'           => get_option('hm_vat_services', '13.5'),
            'Bundled Items'      => get_option('hm_vat_bundled', '0'),
            'Accessories'        => get_option('hm_vat_accessories', '0'),
            'Consumables'        => get_option('hm_vat_consumables', '23'),
            'Other Audiological' => get_option('hm_vat_other_aud', '13.5'),
        ];
        $options = [];
        foreach ($rates as $label => $rate) {
            $r = rtrim(rtrim(number_format((float) $rate, 2), '0'), '.');
            $options[] = $label . ' (' . $r . '%)';
        }
        return $options;
    }

    /** Default VAT category per item type */
    private static function get_vat_default($item_type) {
        $map = [
            'product'    => ['Hearing Aids',  'hm_vat_hearing_aids', '0'],
            'service'    => ['Services',      'hm_vat_services',    '13.5'],
            'bundled'    => ['Bundled Items',  'hm_vat_bundled',     '0'],
            'accessory'  => ['Accessories',    'hm_vat_accessories', '0'],
            'consumable' => ['Consumables',    'hm_vat_consumables', '23'],
        ];
        $info = $map[$item_type] ?? $map['product'];
        $rate = get_option($info[1], $info[2]);
        $r = rtrim(rtrim(number_format((float) $rate, 2), '0'), '.');
        return $info[0] . ' (' . $r . '%)';
    }

    public function __construct() {
        add_shortcode('hearmed_products', [$this, 'render']);
        add_action('wp_ajax_hm_admin_save_product',    [$this, 'ajax_save']);
        add_action('wp_ajax_hm_admin_delete_product',  [$this, 'ajax_delete']);
        add_action('wp_ajax_hm_admin_import_products', [$this, 'ajax_import']);
        add_action('wp_ajax_hm_admin_add_bundled_category', [$this, 'ajax_add_bundled_category']);
        $this->ensure_product_columns();
    }

    /** Category labels per item_type (stored in DB 'category' column) */
    private static $category_labels = [
        'product'    => 'Hearing Aid',
        'service'    => 'Service',
        'bundled'    => 'Bundled Item',
        'accessory'  => 'Accessory',
        'consumable' => 'Consumable',
    ];

    private function get_hearmed_ranges() {
        // 1. Try PostgreSQL table first (cast prices to numeric in case of money type)
        $rows = HearMed_DB::get_results(
            "SELECT id, range_name, price_total::numeric AS price_total, price_ex_prsi::numeric AS price_ex_prsi FROM hearmed_reference.hearmed_range WHERE COALESCE(is_active::text,'true') NOT IN ('false','f','0') ORDER BY range_name"
        ) ?: [];
        if (!empty($rows)) return $rows;

        // 2. Fallback: pull from WP taxonomy 'hearmed-range' (legacy data source)
        $terms = get_terms(['taxonomy' => 'hearmed-range', 'hide_empty' => false]);
        if (is_wp_error($terms) || empty($terms)) return [];
        $out = [];
        foreach ($terms as $t) {
            $obj = new \stdClass();
            $obj->id           = $t->term_id;
            $obj->range_name   = $t->name;
            $obj->price_total  = get_term_meta($t->term_id, 'price_total', true) ?: null;
            $obj->price_ex_prsi = get_term_meta($t->term_id, 'price_ex_prsi', true) ?: null;
            $out[] = $obj;
        }
        return $out;
    }

    /** Auto-add missing columns to products/manufacturers tables (runs once per request) */
    private function ensure_product_columns() {
        static $done = false;
        if ($done) return;
        $done = true;
        // dome_type, dome_size on products
        $check = HearMed_DB::get_var(
            "SELECT column_name FROM information_schema.columns
             WHERE table_schema = 'hearmed_reference' AND table_name = 'products' AND column_name = 'dome_type'"
        );
        if ($check === null) {
            HearMed_DB::get_results("ALTER TABLE hearmed_reference.products
                ADD COLUMN IF NOT EXISTS dome_type VARCHAR(50),
                ADD COLUMN IF NOT EXISTS dome_size VARCHAR(20)");
        }
        // manufacturer_category on manufacturers
        $check2 = HearMed_DB::get_var(
            "SELECT column_name FROM information_schema.columns
             WHERE table_schema = 'hearmed_reference' AND table_name = 'manufacturers' AND column_name = 'manufacturer_category'"
        );
        if ($check2 === null) {
            HearMed_DB::get_results("ALTER TABLE hearmed_reference.manufacturers
                ADD COLUMN IF NOT EXISTS manufacturer_category VARCHAR(100) DEFAULT ''");
        }
        // manufacturer_other_desc on manufacturers
        $check3 = HearMed_DB::get_var(
            "SELECT column_name FROM information_schema.columns
             WHERE table_schema = 'hearmed_reference' AND table_name = 'manufacturers' AND column_name = 'manufacturer_other_desc'"
        );
        if ($check3 === null) {
            HearMed_DB::get_results("ALTER TABLE hearmed_reference.manufacturers
                ADD COLUMN IF NOT EXISTS manufacturer_other_desc VARCHAR(255) DEFAULT ''");
        }
        // hearing_aid_class on products (Custom / Ready-Fit)
        $check4 = HearMed_DB::get_var(
            "SELECT column_name FROM information_schema.columns
             WHERE table_schema = 'hearmed_reference' AND table_name = 'products' AND column_name = 'hearing_aid_class'"
        );
        if ($check4 === null) {
            HearMed_DB::get_results("ALTER TABLE hearmed_reference.products
                ADD COLUMN IF NOT EXISTS hearing_aid_class VARCHAR(20) DEFAULT ''");
        }
        // hearmed_range_id on products (FK to hearmed_range)
        $check5 = HearMed_DB::get_var(
            "SELECT column_name FROM information_schema.columns
             WHERE table_schema = 'hearmed_reference' AND table_name = 'products' AND column_name = 'hearmed_range_id'"
        );
        if ($check5 === null) {
            HearMed_DB::get_results("ALTER TABLE hearmed_reference.products
                ADD COLUMN IF NOT EXISTS hearmed_range_id BIGINT");
        }
    }

    private function get_products($type = null) {
        $sql = "SELECT p.*, m.name AS manufacturer_name, hr.range_name AS hearmed_range_name
                FROM hearmed_reference.products p
                LEFT JOIN hearmed_reference.manufacturers m ON p.manufacturer_id = m.id
                LEFT JOIN hearmed_reference.hearmed_range hr ON p.hearmed_range_id = hr.id
                WHERE p.is_active = true";
        $params = [];
        if ($type) {
            $sql .= " AND p.item_type = $1";
            $params[] = $type;
        }
        $sql .= " ORDER BY m.name, p.product_name";
        return HearMed_DB::get_results($sql, $params) ?: [];
    }

    private function get_manufacturers() {
        return HearMed_DB::get_results(
            "SELECT id, name FROM hearmed_reference.manufacturers WHERE is_active = true ORDER BY name"
        ) ?: [];
    }

    private function get_bundled_categories() {
        $check = HearMed_DB::get_var("SELECT to_regclass('hearmed_reference.bundled_categories')");
        if ($check === null) return ['Speaker','Dome','Other'];
        $rows = HearMed_DB::get_results(
            "SELECT category_name FROM hearmed_reference.bundled_categories WHERE is_active = true ORDER BY sort_order, category_name"
        ) ?: [];
        $cats = array_map(function($r) { return $r->category_name; }, $rows);
        return !empty($cats) ? $cats : ['Speaker','Dome','Other'];
    }

    /**
     * Auto-generate product code based on item type
     */
    private static function generate_code($type, $data) {
        if ($type === 'product') {
            // Hearing Aid: first 2 of manufacturer + first letter of model + tech level numbers + (style)
            $mfr   = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $data['manufacturer_name'] ?? ''), 0, 2));
            $model  = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $data['model'] ?? ''), 0, 1));
            $tech   = preg_replace('/[^0-9]/', '', $data['tech_level'] ?? '');
            $style  = strtoupper($data['style'] ?? '');
            $code   = $mfr . $model . $tech;
            if ($style) $code .= ' (' . $style . ')';
            return $code;
        }
        if ($type === 'service' || $type === 'bundled') {
            // First letters of each word in name
            $words = preg_split('/\s+/', trim($data['name'] ?? ''));
            $code = '';
            foreach ($words as $w) {
                $letter = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $w), 0, 1));
                if ($letter) $code .= $letter;
            }
            return $code;
        }
        return '';
    }

    public function render() {
        if (!is_user_logged_in()) return '<p>Please log in.</p>';

        $active_tab = sanitize_text_field($_GET['tab'] ?? 'product');
        if (!isset(self::$item_types[$active_tab])) $active_tab = 'product';

        $products       = $this->get_products($active_tab);
        $manufacturers  = $this->get_manufacturers();
        $bundled_cats   = $this->get_bundled_categories();
        $hearmed_ranges = $this->get_hearmed_ranges();
        $vat_options    = self::get_vat_options();
        $vat_default    = self::get_vat_default($active_tab);

        ob_start(); ?>
        <div class="hm-admin">
            <div style="margin-bottom:16px;"><a href="<?php echo esc_url(home_url("/admin-console/")); ?>" class="hm-btn">&larr; Back</a></div>
            <div class="hm-admin-hd">
                <h2>Products &amp; Services</h2>
                <button class="hm-btn hm-btn--primary" onclick="hmProd.open()">+ Add <?php echo esc_html(rtrim(self::$item_types[$active_tab], 's')); ?></button>
            </div>

            <!-- Category Tabs -->
            <div class="hm-tab-bar">
                <?php foreach (self::$item_types as $key => $label):
                    $active = ($key === $active_tab);
                ?>
                <a href="?tab=<?php echo esc_attr($key); ?>"
                   class="hm-tab<?php echo $active ? ' active' : ''; ?>">
                    <?php echo esc_html($label); ?>
                </a>
                <?php endforeach; ?>
            </div>

            <?php if (empty($products)): ?>
                <div class="hm-empty-state"><p>No <?php echo esc_html(strtolower(self::$item_types[$active_tab])); ?> yet.</p></div>
            <?php else: ?>
            <table class="hm-table">
                <thead>
                    <tr>
                        <?php if ($active_tab === 'product'): ?>
                            <th>Name</th><th>Manufacturer</th><th>Model</th><th>Tech Level</th><th>Class</th><th>Style</th><th>Power</th><th>Range</th>
                            <th>Code</th><th>VAT</th><th class="hm-num">Cost</th><th class="hm-num">Retail</th>
                        <?php elseif ($active_tab === 'service'): ?>
                            <th>Name</th><th>Code</th><th>VAT</th><th class="hm-num">Retail</th>
                        <?php elseif ($active_tab === 'bundled'): ?>
                            <th>Name</th><th>Manufacturer</th><th>Code</th><th>Category</th>
                            <th>VAT</th><th class="hm-num">Cost</th><th class="hm-num">Retail</th>
                        <?php elseif ($active_tab === 'accessory' || $active_tab === 'consumable'): ?>
                            <th>Name</th><th>Manufacturer</th><th>Code</th>
                            <th>VAT</th><th class="hm-num">Cost</th><th class="hm-num">Retail</th>
                        <?php endif; ?>
                        <th style="width:100px"></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($products as $p):
                    $row = json_encode((array) $p, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
                ?>
                    <tr>
                        <?php if ($active_tab === 'product'):
                            // Build auto-generated display name: Manufacturer - Model - TechLevel - Style (R/B)
                            $power_val = $p->power_type ?? '';
                            $power_abbr = '';
                            if (stripos($power_val, 'rechargeable') !== false) $power_abbr = 'R';
                            elseif (stripos($power_val, 'battery') !== false) $power_abbr = 'B';
                            $name_parts = array_filter([
                                $p->manufacturer_name ?? '',
                                $p->product_name ?? '',
                                $p->tech_level ?? '',
                                $p->style ?? '',
                            ]);
                            $display_name = implode(' - ', $name_parts);
                            if ($power_abbr) $display_name .= ' (' . $power_abbr . ')';
                        ?>
                            <td><strong><?php echo esc_html($display_name ?: '—'); ?></strong></td>
                            <td><?php echo esc_html($p->manufacturer_name ?: '—'); ?></td>
                            <td><?php echo esc_html($p->product_name ?: '—'); ?></td>
                            <td><?php echo esc_html($p->tech_level ?: '—'); ?></td>
                            <td><?php echo esc_html($p->hearing_aid_class ?: '—'); ?></td>
                            <td><?php echo esc_html($p->style ?: '—'); ?></td>
                            <td><?php echo esc_html($p->power_type ?: '—'); ?></td>
                            <td><?php echo esc_html($p->hearmed_range_name ?? '—'); ?></td>
                            <td><code style="font-size:11px;"><?php echo esc_html($p->product_code ?: '—'); ?></code></td>
                            <td><?php echo esc_html($p->vat_category ?: '—'); ?></td>
                            <td class="hm-num"><?php echo $p->cost_price !== null ? '€' . number_format((float) $p->cost_price, 2) : '—'; ?></td>
                            <td class="hm-num"><?php echo $p->retail_price !== null ? '€' . number_format((float) $p->retail_price, 2) : '—'; ?></td>
                        <?php elseif ($active_tab === 'service'): ?>
                            <td><strong><?php echo esc_html($p->product_name); ?></strong></td>
                            <td><code style="font-size:11px;"><?php echo esc_html($p->product_code ?: '—'); ?></code></td>
                            <td><?php echo esc_html($p->vat_category ?: '—'); ?></td>
                            <td class="hm-num"><?php echo $p->retail_price !== null ? '€' . number_format((float) $p->retail_price, 2) : '—'; ?></td>
                        <?php elseif ($active_tab === 'bundled'):
                            // Build auto-generated display name: Manufacturer - ItemName - Category (details)
                            $bcat = $p->bundled_category ?? ($p->style ?? '');
                            $bnd_parts = array_filter([
                                $p->manufacturer_name ?? '',
                                $p->product_name ?? '',
                                $bcat,
                            ]);
                            $bnd_display = implode(' - ', $bnd_parts);
                            if (strtolower($bcat) === 'speaker') {
                                $sub = [];
                                if (!empty($p->speaker_length)) $sub[] = 'L' . $p->speaker_length;
                                if (!empty($p->speaker_power)) $sub[] = $p->speaker_power;
                                if ($sub) $bnd_display .= ' (' . implode(' / ', $sub) . ')';
                            } elseif (strtolower($bcat) === 'dome') {
                                $sub = [];
                                if (!empty($p->dome_type)) $sub[] = $p->dome_type;
                                if (!empty($p->dome_size)) $sub[] = $p->dome_size;
                                if ($sub) $bnd_display .= ' (' . implode(' / ', $sub) . ')';
                            }
                        ?>
                            <td><strong><?php echo esc_html($bnd_display ?: $p->product_name); ?></strong></td>
                            <td><?php echo esc_html($p->manufacturer_name ?: '—'); ?></td>
                            <td><code style="font-size:11px;"><?php echo esc_html($p->product_code ?: '—'); ?></code></td>
                            <td>
                                <?php
                                    echo esc_html($bcat ?: '—');
                                    if (strtolower($bcat) === 'speaker'):
                                        $len = $p->speaker_length ?? '';
                                        $pwr = $p->speaker_power ?? '';
                                        if ($len !== '' || $pwr !== ''):
                                ?>
                                    <span style="font-size:11px;color:var(--hm-text-light);margin-left:4px;">(<?php
                                        $parts = [];
                                        if ($len !== '') $parts[] = 'L' . $len;
                                        if ($pwr !== '') $parts[] = $pwr;
                                        echo esc_html(implode(' / ', $parts));
                                    ?>)</span>
                                <?php endif; endif; ?>
                                <?php if (strtolower($bcat) === 'dome'):
                                    $dt = $p->dome_type ?? '';
                                    $ds = $p->dome_size ?? '';
                                    if ($dt !== '' || $ds !== ''):
                                ?>
                                    <span style="font-size:11px;color:var(--hm-text-light);margin-left:4px;">(<?php
                                        $parts = [];
                                        if ($dt !== '') $parts[] = $dt;
                                        if ($ds !== '') $parts[] = $ds;
                                        echo esc_html(implode(' / ', $parts));
                                    ?>)</span>
                                <?php endif; endif; ?>
                            </td>
                            <td><?php echo esc_html($p->vat_category ?: '—'); ?></td>
                            <td class="hm-num"><?php echo $p->cost_price !== null ? '€' . number_format((float) $p->cost_price, 2) : '—'; ?></td>
                            <td class="hm-num"><?php echo $p->retail_price !== null ? '€' . number_format((float) $p->retail_price, 2) : '—'; ?></td>
                        <?php elseif ($active_tab === 'accessory' || $active_tab === 'consumable'): ?>
                            <td><strong><?php echo esc_html($p->product_name); ?></strong></td>
                            <td><?php echo esc_html($p->manufacturer_name ?: '—'); ?></td>
                            <td><code style="font-size:11px;"><?php echo esc_html($p->product_code ?: '—'); ?></code></td>
                            <td><?php echo esc_html($p->vat_category ?: '—'); ?></td>
                            <td class="hm-num"><?php echo $p->cost_price !== null ? '€' . number_format((float) $p->cost_price, 2) : '—'; ?></td>
                            <td class="hm-num"><?php echo $p->retail_price !== null ? '€' . number_format((float) $p->retail_price, 2) : '—'; ?></td>
                        <?php endif; ?>
                        <td class="hm-table-acts">
                            <button class="hm-btn hm-btn--sm" onclick='hmProd.open(<?php echo $row; ?>)'>Edit</button>
                            <button class="hm-btn hm-btn--sm hm-btn--danger" onclick="hmProd.del(<?php echo (int) $p->id; ?>,'<?php echo esc_js($p->product_name); ?>')">Delete</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>

            <div style="display:flex;flex-direction:column;gap:8px;margin-top:20px;align-items:flex-start;">
                <button class="hm-btn" style="background:#e2e8f0;color:#64748b;border:1px solid #cbd5e1;font-size:13px;" onclick="hmProd.showImport()">&#8593; Import CSV</button>
                <button class="hm-btn" style="background:#e2e8f0;color:#64748b;border:1px solid #cbd5e1;font-size:13px;" onclick="hmProd.downloadTemplate()">&#8595; Download Template</button>
            </div>

            <!-- ========== ADD/EDIT MODAL ========== -->
            <div class="hm-modal-bg" id="hm-prod-modal">
                <div class="hm-modal" style="width:700px">
                    <div class="hm-modal-hd">
                        <h3 id="hm-prod-modal-title">Add Item</h3>
                        <button class="hm-modal-x" onclick="hmProd.close()">&times;</button>
                    </div>
                    <div class="hm-modal-body">
                        <input type="hidden" id="hmp-id">
                        <input type="hidden" id="hmp-item-type" value="<?php echo esc_attr($active_tab); ?>">

                        <!-- ===== HEARING AID FIELDS ===== -->
                        <div id="hmp-product-fields" class="hmp-type-fields" style="display:<?php echo $active_tab === 'product' ? 'block' : 'none'; ?>">
                            <div class="hm-form-group">
                                <label>Name <span style="font-size:11px;color:var(--hm-text-light);">(auto-generated)</span></label>
                                <input type="text" id="hmp-name-ha" readonly style="background:#f1f5f9;font-weight:600;">
                            </div>
                            <div class="hm-form-row">
                                <div class="hm-form-group">
                                    <label>Manufacturer *</label>
                                    <select id="hmp-manufacturer" data-name-attr="1" onchange="hmProd.genCode();hmProd.genName()">
                                        <option value="">Select brand</option>
                                        <?php foreach ($manufacturers as $m): ?>
                                            <option value="<?php echo (int) $m->id; ?>" data-name="<?php echo esc_attr($m->name); ?>"><?php echo esc_html($m->name); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="hm-form-group">
                                    <label>Model * <span style="font-size:11px;color:var(--hm-text-light);">(e.g. Allure M)</span></label>
                                    <input type="text" id="hmp-model" placeholder="e.g. Allure M" oninput="hmProd.genCode();hmProd.genName()">
                                </div>
                            </div>
                            <div class="hm-form-row">
                                <div class="hm-form-group">
                                    <label>Style *</label>
                                    <select id="hmp-style" data-entity="ha_style" data-label="Style" onchange="hmProd.genCode();hmProd.genName()">
                                        <option value="">Select</option>
                                        <?php foreach (self::$ha_styles as $s): ?>
                                            <option value="<?php echo esc_attr($s); ?>"><?php echo esc_html($s); ?></option>
                                        <?php endforeach; ?>
                                        <?php foreach (hm_get_dropdown_options('ha_style') as $custom): ?>
                                            <?php if (!in_array($custom, self::$ha_styles)): ?>
                                            <option value="<?php echo esc_attr($custom); ?>"><?php echo esc_html($custom); ?></option>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                        <option value="__add_new__">+ Add New…</option>
                                    </select>
                                </div>
                                <div class="hm-form-group">
                                    <label>Tech Level *</label>
                                    <input type="text" id="hmp-tech" placeholder="e.g. 330" oninput="hmProd.genCode();hmProd.genName()">
                                </div>
                            </div>
                            <div class="hm-form-row">
                                <div class="hm-form-group">
                                    <label>Class *</label>
                                    <select id="hmp-ha-class">
                                        <option value="">Select</option>
                                        <?php foreach (self::$ha_classes as $hc): ?>
                                            <option value="<?php echo esc_attr($hc); ?>"><?php echo esc_html($hc); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="hm-form-group">
                                    <label>Auto Code</label>
                                    <input type="text" id="hmp-code-ha" readonly style="background:#f1f5f9;font-family:monospace;font-weight:600;">
                                </div>
                                <div class="hm-form-group">
                                    <label>HearMed Range</label>
                                    <select id="hmp-range">
                                        <option value="">— Select Range —</option>
                                        <?php foreach ($hearmed_ranges as $hr): ?>
                                            <option value="<?php echo (int) $hr->id; ?>" data-price="<?php echo esc_attr($hr->price_total); ?>" data-exprsi="<?php echo esc_attr($hr->price_ex_prsi); ?>"><?php echo esc_html($hr->range_name); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="hm-form-group">
                                    <label>Power</label>
                                    <?php $power_defaults = ['Rechargeable','312 Battery','13 Battery','10 Battery','675 Battery']; ?>
                                    <select id="hmp-power" data-entity="power_type" data-label="Power Type" onchange="hmProd.genName()">
                                        <option value="">Select</option>
                                        <?php foreach ($power_defaults as $pw): ?>
                                            <option value="<?php echo esc_attr($pw); ?>"><?php echo esc_html($pw); ?></option>
                                        <?php endforeach; ?>
                                        <?php foreach (hm_get_dropdown_options('power_type') as $custom): ?>
                                            <?php if (!in_array($custom, $power_defaults)): ?>
                                            <option value="<?php echo esc_attr($custom); ?>"><?php echo esc_html($custom); ?></option>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                        <option value="__add_new__">+ Add New…</option>
                                    </select>
                                </div>
                            </div>
                            <div class="hm-form-row">
                                <div class="hm-form-group">
                                    <label>Cost Price (€)</label>
                                    <input type="number" id="hmp-cost-ha" step="0.01" min="0" placeholder="0.00">
                                </div>
                                <div class="hm-form-group">
                                    <label>Retail Price (€)</label>
                                    <input type="number" id="hmp-retail-ha" step="0.01" min="0" placeholder="0.00">
                                </div>
                            </div>
                            <div class="hm-form-row">
                                <div class="hm-form-group">
                                    <label>VAT Category</label>
                                    <select id="hmp-vat-ha">
                                        <?php foreach ($vat_options as $vo): ?>
                                        <option value="<?php echo esc_attr($vo); ?>"<?php echo $vo === self::get_vat_default('product') ? ' selected' : ''; ?>><?php echo esc_html($vo); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- ===== SERVICE FIELDS ===== -->
                        <div id="hmp-service-fields" class="hmp-type-fields" style="display:<?php echo $active_tab === 'service' ? 'block' : 'none'; ?>">
                            <div class="hm-form-row">
                                <div class="hm-form-group" style="flex:2">
                                    <label>Service Name *</label>
                                    <input type="text" id="hmp-svc-name" placeholder="e.g. Wax Removal" oninput="hmProd.genSvcCode()">
                                </div>
                                <div class="hm-form-group" style="flex:1">
                                    <label>Auto Code</label>
                                    <input type="text" id="hmp-code-svc" readonly style="background:#f1f5f9;font-family:monospace;font-weight:600;">
                                </div>
                            </div>
                            <div class="hm-form-row">
                                <div class="hm-form-group">
                                    <label>Retail Price (€)</label>
                                    <input type="number" id="hmp-retail-svc" step="0.01" min="0" placeholder="0.00">
                                </div>
                            </div>
                            <div class="hm-form-row">
                                <div class="hm-form-group">
                                    <label>VAT Category</label>
                                    <select id="hmp-vat-svc">
                                        <?php foreach ($vat_options as $vo): ?>
                                        <option value="<?php echo esc_attr($vo); ?>"<?php echo $vo === self::get_vat_default('service') ? ' selected' : ''; ?>><?php echo esc_html($vo); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- ===== BUNDLED ITEM FIELDS ===== -->
                        <div id="hmp-bundled-fields" class="hmp-type-fields" style="display:<?php echo $active_tab === 'bundled' ? 'block' : 'none'; ?>">
                            <div class="hm-form-group">
                                <label>Name <span style="font-size:11px;color:var(--hm-text-light);">(auto-generated)</span></label>
                                <input type="text" id="hmp-name-bnd" readonly style="background:#f1f5f9;font-weight:600;">
                            </div>
                            <div class="hm-form-row">
                                <div class="hm-form-group" style="flex:2">
                                    <label>Item Name *</label>
                                    <input type="text" id="hmp-bnd-name" placeholder="e.g. miniFit Speaker" oninput="hmProd.genBndCode();hmProd.genBndName()">
                                </div>
                                <div class="hm-form-group" style="flex:1">
                                    <label>Auto Code</label>
                                    <input type="text" id="hmp-code-bnd" readonly style="background:#f1f5f9;font-family:monospace;font-weight:600;">
                                </div>
                            </div>
                            <div class="hm-form-row">
                                <div class="hm-form-group">
                                    <label>Manufacturer</label>
                                    <select id="hmp-bnd-mfr" data-name-attr="1" onchange="hmProd.genBndName()">
                                        <option value="">Select brand</option>
                                        <?php foreach ($manufacturers as $m): ?>
                                            <option value="<?php echo (int) $m->id; ?>" data-name="<?php echo esc_attr($m->name); ?>"><?php echo esc_html($m->name); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="hm-form-group">
                                    <label>Category *</label>
                                    <select id="hmp-bnd-cat" data-entity="bundled_category" data-label="Category" onchange="hmProd.toggleSubFields()">
                                        <option value="">Select</option>
                                        <?php foreach ($bundled_cats as $bc): ?>
                                            <option value="<?php echo esc_attr($bc); ?>"><?php echo esc_html($bc); ?></option>
                                        <?php endforeach; ?>
                                        <option value="__add_new__">+ Add New…</option>
                                    </select>
                                </div>
                            </div>
                            <!-- Speaker sub-fields (shown when category = Speaker) -->
                            <div id="hmp-speaker-fields" style="display:none;">
                                <div class="hm-form-row">
                                    <div class="hm-form-group">
                                        <label>Length</label>
                                        <select id="hmp-spk-length" onchange="hmProd.genBndName()">
                                            <option value="">Select</option>
                                            <option value="0">0</option>
                                            <option value="1">1</option>
                                            <option value="2">2</option>
                                            <option value="3">3</option>
                                            <option value="4">4</option>
                                            <option value="5">5</option>
                                        </select>
                                    </div>
                                    <div class="hm-form-group">
                                        <label>Power</label>
                                        <select id="hmp-spk-power" data-entity="speaker_power" data-label="Speaker Power" onchange="hmProd.genBndName()">
                                            <option value="">Select</option>
                                            <?php foreach (self::$speaker_powers as $val => $lbl): ?>
                                                <option value="<?php echo esc_attr($val); ?>"><?php echo esc_html($lbl); ?></option>
                                            <?php endforeach; ?>
                                            <?php foreach (hm_get_dropdown_options('speaker_power') as $custom): ?>
                                                <?php if (!array_key_exists($custom, self::$speaker_powers)): ?>
                                                <option value="<?php echo esc_attr($custom); ?>"><?php echo esc_html($custom); ?></option>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                            <option value="__add_new__">+ Add New…</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <!-- Dome sub-fields (shown when category = Dome) -->
                            <div id="hmp-dome-fields" style="display:none;">
                                <div class="hm-form-row">
                                    <div class="hm-form-group">
                                        <label>Dome Type</label>
                                        <select id="hmp-dome-type" data-entity="dome_type" data-label="Dome Type" onchange="hmProd.genBndName()">
                                            <option value="">Select</option>
                                            <?php foreach (self::$dome_types as $dt): ?>
                                                <option value="<?php echo esc_attr($dt); ?>"><?php echo esc_html($dt); ?></option>
                                            <?php endforeach; ?>
                                            <?php foreach (hm_get_dropdown_options('dome_type') as $custom): ?>
                                                <?php if (!in_array($custom, self::$dome_types)): ?>
                                                <option value="<?php echo esc_attr($custom); ?>"><?php echo esc_html($custom); ?></option>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                            <option value="__add_new__">+ Add New…</option>
                                        </select>
                                    </div>
                                    <div class="hm-form-group">
                                        <label>Dome Size</label>
                                        <select id="hmp-dome-size" data-entity="dome_size" data-label="Dome Size" onchange="hmProd.genBndName()">
                                            <option value="">Select</option>
                                            <?php foreach (self::$dome_sizes as $ds): ?>
                                                <option value="<?php echo esc_attr($ds); ?>"><?php echo esc_html($ds); ?></option>
                                            <?php endforeach; ?>
                                            <?php foreach (hm_get_dropdown_options('dome_size') as $custom): ?>
                                                <?php if (!in_array($custom, self::$dome_sizes)): ?>
                                                <option value="<?php echo esc_attr($custom); ?>"><?php echo esc_html($custom); ?></option>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                            <option value="__add_new__">+ Add New…</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="hm-form-row">
                                <div class="hm-form-group">
                                    <label>Cost Price (€)</label>
                                    <input type="number" id="hmp-cost-bnd" step="0.01" min="0" placeholder="0.00">
                                </div>
                                <div class="hm-form-group">
                                    <label>Retail Price (€)</label>
                                    <input type="number" id="hmp-retail-bnd" step="0.01" min="0" placeholder="0.00">
                                </div>
                            </div>
                            <div class="hm-form-row">
                                <div class="hm-form-group">
                                    <label>VAT Category</label>
                                    <select id="hmp-vat-bnd">
                                        <?php foreach ($vat_options as $vo): ?>
                                        <option value="<?php echo esc_attr($vo); ?>"<?php echo $vo === self::get_vat_default('bundled') ? ' selected' : ''; ?>><?php echo esc_html($vo); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- ===== ACCESSORY / CONSUMABLE FIELDS ===== -->
                        <div id="hmp-acc-fields" class="hmp-type-fields" style="display:<?php echo ($active_tab === 'accessory' || $active_tab === 'consumable') ? 'block' : 'none'; ?>">
                            <div class="hm-form-row">
                                <div class="hm-form-group" style="flex:2">
                                    <label>Item Name *</label>
                                    <input type="text" id="hmp-acc-name" placeholder="e.g. Cleaning Spray" oninput="hmProd.genAccCode()">
                                </div>
                                <div class="hm-form-group" style="flex:1">
                                    <label>Auto Code</label>
                                    <input type="text" id="hmp-code-acc" readonly style="background:#f1f5f9;font-family:monospace;font-weight:600;">
                                </div>
                            </div>
                            <div class="hm-form-row">
                                <div class="hm-form-group">
                                    <label>Manufacturer</label>
                                    <select id="hmp-acc-mfr">
                                        <option value="">Select brand</option>
                                        <?php foreach ($manufacturers as $m): ?>
                                            <option value="<?php echo (int) $m->id; ?>"><?php echo esc_html($m->name); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="hm-form-row">
                                <div class="hm-form-group">
                                    <label>Cost Price (€)</label>
                                    <input type="number" id="hmp-acc-cost" step="0.01" min="0" placeholder="0.00">
                                </div>
                                <div class="hm-form-group">
                                    <label>Retail Price (€)</label>
                                    <input type="number" id="hmp-acc-retail" step="0.01" min="0" placeholder="0.00">
                                </div>
                            </div>
                            <div class="hm-form-row">
                                <div class="hm-form-group">
                                    <label>VAT Category</label>
                                    <select id="hmp-vat-acc">
                                        <?php foreach ($vat_options as $vo): ?>
                                        <option value="<?php echo esc_attr($vo); ?>"<?php echo $vo === $vat_default ? ' selected' : ''; ?>><?php echo esc_html($vo); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="hm-modal-ft">
                        <button class="hm-btn" onclick="hmProd.close()">Cancel</button>
                        <button class="hm-btn hm-btn--primary" onclick="hmProd.save()" id="hmp-save-btn">Save</button>
                    </div>
                </div>
            </div>

            <!-- ========== IMPORT MODAL ========== -->
            <div class="hm-modal-bg" id="hm-import-modal">
                <div class="hm-modal" style="width:560px">
                    <div class="hm-modal-hd">
                        <h3>Import from CSV</h3>
                        <button class="hm-modal-x" onclick="document.getElementById('hm-import-modal').classList.remove('open')">&times;</button>
                    </div>
                    <div class="hm-modal-body">
                        <p style="font-size:13px;color:var(--hm-text-light);margin-bottom:12px;">
                            Upload a CSV file matching the template for <strong><?php echo esc_html(self::$item_types[$active_tab]); ?></strong>.
                            Download the template first to see the expected columns.
                        </p>
                        <div class="hm-form-group">
                            <label>CSV File</label>
                            <input type="file" id="hmp-csv-file" accept=".csv,.txt">
                        </div>
                        <div id="hmp-import-preview" style="display:none;margin-top:12px;">
                            <p style="font-size:12px;"><strong id="hmp-import-count">0</strong> rows detected</p>
                        </div>
                    </div>
                    <div class="hm-modal-ft">
                        <button class="hm-btn" onclick="document.getElementById('hm-import-modal').classList.remove('open')">Cancel</button>
                        <button class="hm-btn hm-btn--primary" onclick="hmProd.doImport()" id="hmp-import-btn">Import</button>
                    </div>
                </div>
            </div>
        </div>

        <script>
        var hmProd = {
            activeTab: '<?php echo esc_js($active_tab); ?>',
            manufacturers: <?php echo json_encode(array_map(function($m) { return ['id'=>(int)$m->id,'name'=>$m->name]; }, $manufacturers)); ?>,

            // === Auto name generator (Hearing Aids) ===
            genName: function() {
                var sel = document.getElementById('hmp-manufacturer');
                var mfr = sel.options[sel.selectedIndex] ? (sel.options[sel.selectedIndex].getAttribute('data-name') || '') : '';
                var model = document.getElementById('hmp-model').value.trim();
                var tech = document.getElementById('hmp-tech').value.trim();
                var style = document.getElementById('hmp-style').value;
                var power = document.getElementById('hmp-power').value;
                var abbr = '';
                if (power.toLowerCase().indexOf('rechargeable') !== -1) abbr = 'R';
                else if (power.toLowerCase().indexOf('battery') !== -1 || power.toLowerCase().indexOf('312') !== -1 || power.toLowerCase().indexOf('13') !== -1 || power.toLowerCase().indexOf('10') !== -1 || power.toLowerCase().indexOf('675') !== -1) abbr = 'B';
                var parts = [mfr, model, tech, style].filter(Boolean);
                var name = parts.join(' - ');
                if (abbr) name += ' (' + abbr + ')';
                document.getElementById('hmp-name-ha').value = name;
            },

            // === Auto code generators ===
            genCode: function() {
                var sel = document.getElementById('hmp-manufacturer');
                var mfr = sel.options[sel.selectedIndex] ? (sel.options[sel.selectedIndex].getAttribute('data-name') || '') : '';
                var model = document.getElementById('hmp-model').value.trim();
                var tech = document.getElementById('hmp-tech').value.trim();
                var style = document.getElementById('hmp-style').value;
                var m2 = mfr.replace(/[^A-Za-z]/g, '').substring(0, 2).toUpperCase();
                var m1 = model.replace(/[^A-Za-z]/g, '').substring(0, 1).toUpperCase();
                var tn = tech.replace(/[^0-9]/g, '');
                var code = m2 + m1 + tn;
                if (style) code += ' (' + style + ')';
                document.getElementById('hmp-code-ha').value = code;
            },
            genSvcCode: function() {
                var name = document.getElementById('hmp-svc-name').value.trim();
                var words = name.split(/\s+/);
                var code = '';
                words.forEach(function(w) { var l = w.replace(/[^A-Za-z0-9]/g,'').substring(0,1).toUpperCase(); if(l) code += l; });
                document.getElementById('hmp-code-svc').value = code;
            },
            genBndCode: function() {
                var name = document.getElementById('hmp-bnd-name').value.trim();
                var words = name.split(/\s+/);
                var code = '';
                words.forEach(function(w) { var l = w.replace(/[^A-Za-z0-9]/g,'').substring(0,1).toUpperCase(); if(l) code += l; });
                document.getElementById('hmp-code-bnd').value = code;
            },
            genAccCode: function() {
                var name = document.getElementById('hmp-acc-name').value.trim();
                var words = name.split(/\s+/);
                var code = '';
                words.forEach(function(w) { var l = w.replace(/[^A-Za-z0-9]/g,'').substring(0,1).toUpperCase(); if(l) code += l; });
                document.getElementById('hmp-code-acc').value = code;
            },

            toggleSubFields: function() {
                var cat = document.getElementById('hmp-bnd-cat').value.toLowerCase();
                document.getElementById('hmp-speaker-fields').style.display = (cat === 'speaker') ? 'block' : 'none';
                document.getElementById('hmp-dome-fields').style.display    = (cat === 'dome')    ? 'block' : 'none';
                hmProd.genBndName();
            },

            // === Auto name generator (Bundled Items) ===
            genBndName: function() {
                var sel = document.getElementById('hmp-bnd-mfr');
                var mfr = sel.options[sel.selectedIndex] ? (sel.options[sel.selectedIndex].getAttribute('data-name') || '') : '';
                var itemName = document.getElementById('hmp-bnd-name').value.trim();
                var cat = document.getElementById('hmp-bnd-cat').value;
                var parts = [];
                if (mfr) parts.push(mfr);
                if (itemName) parts.push(itemName);
                if (cat) parts.push(cat);
                var catLower = cat.toLowerCase();
                if (catLower === 'speaker') {
                    var len = document.getElementById('hmp-spk-length').value;
                    var pwr = document.getElementById('hmp-spk-power').value;
                    var sub = [];
                    if (len !== '') sub.push('L' + len);
                    if (pwr) sub.push(pwr);
                    if (sub.length) parts.push('(' + sub.join(' / ') + ')');
                } else if (catLower === 'dome') {
                    var dType = document.getElementById('hmp-dome-type').value;
                    var dSize = document.getElementById('hmp-dome-size').value;
                    var sub = [];
                    if (dType) sub.push(dType);
                    if (dSize) sub.push(dSize);
                    if (sub.length) parts.push('(' + sub.join(' / ') + ')');
                }
                document.getElementById('hmp-name-bnd').value = parts.join(' - ');
            },

            addBundledCat: function() {
                var name = prompt('Enter new category name:');
                if (!name || !name.trim()) return;
                name = name.trim();
                jQuery.post(HM.ajax_url, { action:'hm_admin_add_bundled_category', nonce:HM.nonce, category_name:name }, function(r) {
                    if (r.success) {
                        var sel = document.getElementById('hmp-bnd-cat');
                        var opt = document.createElement('option');
                        opt.value = name; opt.textContent = name;
                        sel.insertBefore(opt, sel.lastElementChild);
                        sel.value = name;
                        hmProd.toggleSubFields();
                    } else { alert(r.data || 'Error'); }
                });
            },

            // === Modal open/close ===
            open: function(data) {
                var isEdit = !!(data && data.id);
                var type = this.activeTab;
                var titles = {product:'Hearing Aid', service:'Service', bundled:'Bundled Item', accessory:'Accessory', consumable:'Consumable'};
                document.getElementById('hm-prod-modal-title').textContent = (isEdit ? 'Edit ' : 'Add ') + (titles[type] || 'Item');
                document.getElementById('hmp-id').value = isEdit ? data.id : '';
                document.getElementById('hmp-item-type').value = type;

                // Show/hide correct field set
                document.querySelectorAll('.hmp-type-fields').forEach(function(el) { el.style.display = 'none'; });
                if (type === 'product') document.getElementById('hmp-product-fields').style.display = 'block';
                else if (type === 'service') document.getElementById('hmp-service-fields').style.display = 'block';
                else if (type === 'bundled') document.getElementById('hmp-bundled-fields').style.display = 'block';
                else if (type === 'accessory' || type === 'consumable') document.getElementById('hmp-acc-fields').style.display = 'block';

                if (type === 'product') {
                    document.getElementById('hmp-manufacturer').value = isEdit && data.manufacturer_id ? data.manufacturer_id : '';
                    document.getElementById('hmp-model').value        = isEdit ? (data.product_name || '') : '';
                    document.getElementById('hmp-style').value        = isEdit ? (data.style || '') : '';
                    document.getElementById('hmp-tech').value         = isEdit ? (data.tech_level || '') : '';
                    document.getElementById('hmp-ha-class').value     = isEdit ? (data.hearing_aid_class || '') : '';
                    document.getElementById('hmp-power').value        = isEdit ? (data.power_type || '') : '';
                    document.getElementById('hmp-range').value        = isEdit ? (data.hearmed_range_id || '') : '';
                    document.getElementById('hmp-code-ha').value      = isEdit ? (data.product_code || '') : '';
                    document.getElementById('hmp-cost-ha').value      = isEdit && data.cost_price != null ? data.cost_price : '';
                    document.getElementById('hmp-retail-ha').value    = isEdit && data.retail_price != null ? data.retail_price : '';
                    document.getElementById('hmp-vat-ha').value       = isEdit && data.vat_category ? data.vat_category : '<?php echo esc_js(self::get_vat_default('product')); ?>';
                    hmProd.genName();
                } else if (type === 'service') {
                    document.getElementById('hmp-svc-name').value     = isEdit ? (data.product_name || '') : '';
                    document.getElementById('hmp-code-svc').value     = isEdit ? (data.product_code || '') : '';
                    document.getElementById('hmp-retail-svc').value   = isEdit && data.retail_price != null ? data.retail_price : '';
                    document.getElementById('hmp-vat-svc').value      = isEdit && data.vat_category ? data.vat_category : '<?php echo esc_js(self::get_vat_default('service')); ?>';
                } else if (type === 'bundled') {
                    document.getElementById('hmp-bnd-name').value     = isEdit ? (data.product_name || '') : '';
                    document.getElementById('hmp-code-bnd').value     = isEdit ? (data.product_code || '') : '';
                    document.getElementById('hmp-bnd-mfr').value      = isEdit && data.manufacturer_id ? data.manufacturer_id : '';
                    document.getElementById('hmp-bnd-cat').value      = isEdit ? (data.bundled_category || data.style || '') : '';
                    document.getElementById('hmp-spk-length').value   = isEdit ? (data.speaker_length || '') : '';
                    document.getElementById('hmp-spk-power').value    = isEdit ? (data.speaker_power || '') : '';
                    document.getElementById('hmp-dome-type').value    = isEdit ? (data.dome_type || '') : '';
                    document.getElementById('hmp-dome-size').value    = isEdit ? (data.dome_size || '') : '';
                    document.getElementById('hmp-cost-bnd').value     = isEdit && data.cost_price != null ? data.cost_price : '';
                    document.getElementById('hmp-retail-bnd').value   = isEdit && data.retail_price != null ? data.retail_price : '';
                    document.getElementById('hmp-vat-bnd').value      = isEdit && data.vat_category ? data.vat_category : '<?php echo esc_js(self::get_vat_default('bundled')); ?>';
                    hmProd.toggleSubFields();
                } else if (type === 'accessory' || type === 'consumable') {
                    document.getElementById('hmp-acc-name').value     = isEdit ? (data.product_name || '') : '';
                    document.getElementById('hmp-code-acc').value     = isEdit ? (data.product_code || '') : '';
                    document.getElementById('hmp-acc-mfr').value      = isEdit && data.manufacturer_id ? data.manufacturer_id : '';
                    document.getElementById('hmp-acc-cost').value     = isEdit && data.cost_price != null ? data.cost_price : '';
                    document.getElementById('hmp-acc-retail').value   = isEdit && data.retail_price != null ? data.retail_price : '';
                    var vatDefault = (type === 'consumable') ? '<?php echo esc_js(self::get_vat_default('consumable')); ?>' : '<?php echo esc_js(self::get_vat_default('accessory')); ?>';
                    document.getElementById('hmp-vat-acc').value      = isEdit && data.vat_category ? data.vat_category : vatDefault;
                }

                document.getElementById('hm-prod-modal').classList.add('open');
            },
            close: function() { document.getElementById('hm-prod-modal').classList.remove('open'); },

            // === Save ===
            save: function() {
                var type = document.getElementById('hmp-item-type').value;
                var payload = {
                    action: 'hm_admin_save_product',
                    nonce: HM.nonce,
                    id: document.getElementById('hmp-id').value,
                    item_type: type
                };

                if (type === 'product') {
                    var model = document.getElementById('hmp-model').value.trim();
                    if (!model) { alert('Model is required.'); return; }
                    if (!document.getElementById('hmp-manufacturer').value) { alert('Manufacturer is required.'); return; }
                    if (!document.getElementById('hmp-style').value) { alert('Style is required.'); return; }
                    if (!document.getElementById('hmp-ha-class').value) { alert('Class is required.'); return; }
                    payload.product_name       = model;
                    payload.manufacturer_id    = document.getElementById('hmp-manufacturer').value;
                    payload.style              = document.getElementById('hmp-style').value;
                    payload.hearmed_range_id   = document.getElementById('hmp-range').value;
                    payload.tech_level         = document.getElementById('hmp-tech').value;
                    payload.hearing_aid_class  = document.getElementById('hmp-ha-class').value;
                    payload.power_type         = document.getElementById('hmp-power').value;
                    payload.product_code    = document.getElementById('hmp-code-ha').value;
                    payload.cost_price      = document.getElementById('hmp-cost-ha').value;
                    payload.retail_price    = document.getElementById('hmp-retail-ha').value;
                    payload.vat_category    = document.getElementById('hmp-vat-ha').value;
                    payload.display_name    = document.getElementById('hmp-name-ha').value;
                } else if (type === 'service') {
                    var svcName = document.getElementById('hmp-svc-name').value.trim();
                    if (!svcName) { alert('Service name is required.'); return; }
                    payload.product_name  = svcName;
                    payload.product_code  = document.getElementById('hmp-code-svc').value;
                    payload.retail_price  = document.getElementById('hmp-retail-svc').value;
                    payload.vat_category  = document.getElementById('hmp-vat-svc').value;
                } else if (type === 'bundled') {
                    var bndName = document.getElementById('hmp-bnd-name').value.trim();
                    if (!bndName) { alert('Item name is required.'); return; }
                    payload.product_name     = bndName;
                    payload.product_code     = document.getElementById('hmp-code-bnd').value;
                    payload.manufacturer_id  = document.getElementById('hmp-bnd-mfr').value;
                    payload.bundled_category = document.getElementById('hmp-bnd-cat').value;
                    payload.speaker_length   = document.getElementById('hmp-spk-length').value;
                    payload.speaker_power    = document.getElementById('hmp-spk-power').value;
                    payload.dome_type        = document.getElementById('hmp-dome-type').value;
                    payload.dome_size        = document.getElementById('hmp-dome-size').value;
                    payload.cost_price       = document.getElementById('hmp-cost-bnd').value;
                    payload.retail_price     = document.getElementById('hmp-retail-bnd').value;
                    payload.vat_category     = document.getElementById('hmp-vat-bnd').value;
                    payload.display_name     = document.getElementById('hmp-name-bnd').value;
                } else if (type === 'accessory' || type === 'consumable') {
                    var accName = document.getElementById('hmp-acc-name').value.trim();
                    if (!accName) { alert('Item name is required.'); return; }
                    payload.product_name    = accName;
                    payload.product_code    = document.getElementById('hmp-code-acc').value;
                    payload.manufacturer_id = document.getElementById('hmp-acc-mfr').value;
                    payload.cost_price      = document.getElementById('hmp-acc-cost').value;
                    payload.retail_price    = document.getElementById('hmp-acc-retail').value;
                    payload.vat_category    = document.getElementById('hmp-vat-acc').value;
                }

                var btn = document.getElementById('hmp-save-btn');
                btn.textContent = 'Saving...'; btn.disabled = true;
                jQuery.post(HM.ajax_url, payload, function(r) {
                    if (r.success) location.reload();
                    else { alert(r.data || 'Error'); btn.textContent = 'Save'; btn.disabled = false; }
                });
            },

            del: function(id, name) {
                if (!confirm('Delete "' + name + '"?')) return;
                jQuery.post(HM.ajax_url, { action:'hm_admin_delete_product', nonce:HM.nonce, id:id }, function(r) {
                    if (r.success) location.reload();
                    else alert(r.data || 'Error');
                });
            },

            // === Import ===
            showImport: function() { document.getElementById('hm-import-modal').classList.add('open'); },
            doImport: function() {
                var fileInput = document.getElementById('hmp-csv-file');
                if (!fileInput.files.length) { alert('Please select a CSV file.'); return; }
                var fd = new FormData();
                fd.append('action', 'hm_admin_import_products');
                fd.append('nonce', HM.nonce);
                fd.append('item_type', hmProd.activeTab);
                fd.append('csv_file', fileInput.files[0]);
                var btn = document.getElementById('hmp-import-btn');
                btn.textContent = 'Importing...'; btn.disabled = true;
                jQuery.ajax({ url: HM.ajax_url, type:'POST', data:fd, processData:false, contentType:false,
                    success: function(r) {
                        if (r.success) { alert('Imported ' + (r.data.count || 0) + ' items.'); location.reload(); }
                        else { alert(r.data || 'Import error'); btn.textContent = 'Import'; btn.disabled = false; }
                    }
                });
            },
            downloadTemplate: function() {
                var type = hmProd.activeTab;
                var headers, filename;
                if (type === 'product') {
                    headers = 'manufacturer,model,style,tech_level,power_type,cost_price,retail_price';
                    filename = 'hearing_aids_template.csv';
                } else if (type === 'service') {
                    headers = 'service_name,retail_price';
                    filename = 'services_template.csv';
                } else if (type === 'bundled') {
                    headers = 'item_name,manufacturer,category,speaker_length,speaker_power,cost_price,retail_price';
                    filename = 'bundled_items_template.csv';
                } else if (type === 'accessory') {
                    headers = 'item_name,manufacturer,cost_price,retail_price';
                    filename = 'accessories_template.csv';
                } else {
                    headers = 'item_name,manufacturer,cost_price,retail_price';
                    filename = 'consumables_template.csv';
                }
                var blob = new Blob([headers + '\n'], {type:'text/csv'});
                var a = document.createElement('a');
                a.href = URL.createObjectURL(blob); a.download = filename; a.click();
            }
        };

        // CSV preview
        document.getElementById('hmp-csv-file').addEventListener('change', function() {
            var file = this.files[0];
            if (!file) return;
            var reader = new FileReader();
            reader.onload = function(e) {
                var lines = e.target.result.split('\n').filter(function(l) { return l.trim(); });
                var count = Math.max(0, lines.length - 1); // minus header
                document.getElementById('hmp-import-count').textContent = count;
                document.getElementById('hmp-import-preview').style.display = 'block';
            };
            reader.readAsText(file);
        });
        </script>
        <?php return ob_get_clean();
    }

    public function ajax_save() {
        check_ajax_referer('hm_nonce', 'nonce');
        if (!current_user_can('edit_posts')) { wp_send_json_error('Permission denied'); return; }

        $id   = intval($_POST['id'] ?? 0);
        $name = sanitize_text_field($_POST['product_name'] ?? '');
        $type = sanitize_text_field($_POST['item_type'] ?? 'product');

        if (empty($name)) { wp_send_json_error('Name is required'); return; }

        $data = [
            'product_name' => $name,
            'product_code' => sanitize_text_field($_POST['product_code'] ?? ''),
            'item_type'    => $type,
            'is_active'    => true,
            'updated_at'   => current_time('mysql'),
            'vat_category' => sanitize_text_field($_POST['vat_category'] ?? ''),
        ];

        // Auto-set category from item_type
        $data['category'] = self::$category_labels[$type] ?? 'Hearing Aid';

        if ($type === 'product') {
            $data['manufacturer_id'] = !empty($_POST['manufacturer_id']) ? intval($_POST['manufacturer_id']) : null;
            $data['style']           = sanitize_text_field($_POST['style'] ?? '');
            $data['hearmed_range_id']= !empty($_POST['hearmed_range_id']) ? intval($_POST['hearmed_range_id']) : null;
            $data['tech_level']      = sanitize_text_field($_POST['tech_level'] ?? '');
            $data['hearing_aid_class'] = sanitize_text_field($_POST['hearing_aid_class'] ?? '');
            $data['power_type']      = sanitize_text_field($_POST['power_type'] ?? '');
            $data['cost_price']      = $_POST['cost_price'] !== '' ? floatval($_POST['cost_price']) : null;
            $data['retail_price']    = $_POST['retail_price'] !== '' ? floatval($_POST['retail_price']) : null;
            $data['display_name']    = sanitize_text_field($_POST['display_name'] ?? '');
        } elseif ($type === 'service') {
            $data['retail_price'] = $_POST['retail_price'] !== '' ? floatval($_POST['retail_price']) : null;
        } elseif ($type === 'bundled') {
            $data['manufacturer_id']  = !empty($_POST['manufacturer_id']) ? intval($_POST['manufacturer_id']) : null;
            $data['bundled_category'] = sanitize_text_field($_POST['bundled_category'] ?? '');
            $data['speaker_length']   = sanitize_text_field($_POST['speaker_length'] ?? '');
            $data['speaker_power']    = sanitize_text_field($_POST['speaker_power'] ?? '');
            $data['dome_type']        = sanitize_text_field($_POST['dome_type'] ?? '');
            $data['dome_size']        = sanitize_text_field($_POST['dome_size'] ?? '');
            $data['cost_price']       = $_POST['cost_price'] !== '' ? floatval($_POST['cost_price']) : null;
            $data['retail_price']     = $_POST['retail_price'] !== '' ? floatval($_POST['retail_price']) : null;
            $data['display_name']     = sanitize_text_field($_POST['display_name'] ?? '');
        } elseif ($type === 'accessory' || $type === 'consumable') {
            $data['manufacturer_id'] = !empty($_POST['manufacturer_id']) ? intval($_POST['manufacturer_id']) : null;
            $data['cost_price']      = $_POST['cost_price'] !== '' ? floatval($_POST['cost_price']) : null;
            $data['retail_price']    = $_POST['retail_price'] !== '' ? floatval($_POST['retail_price']) : null;
        }

        $table = 'hearmed_reference.products';
        if ($id) {
            $result = HearMed_DB::update($table, $data, ['id' => $id]);
        } else {
            $data['created_at'] = current_time('mysql');
            $result = HearMed_DB::insert($table, $data);
            $id = $result ?: 0;
        }

        if ($result === false) {
            wp_send_json_error(HearMed_DB::last_error() ?: 'Database error');
        } else {
            wp_send_json_success(['id' => $id]);
        }
    }

    public function ajax_delete() {
        check_ajax_referer('hm_nonce', 'nonce');
        if (!current_user_can('edit_posts')) { wp_send_json_error('Permission denied'); return; }

        $id = intval($_POST['id'] ?? 0);
        if (!$id) { wp_send_json_error('Invalid ID'); return; }

        $result = HearMed_DB::update(
            'hearmed_reference.products',
            ['is_active' => false, 'updated_at' => current_time('mysql')],
            ['id' => $id]
        );

        if ($result === false) {
            wp_send_json_error(HearMed_DB::last_error() ?: 'Database error');
        } else {
            wp_send_json_success();
        }
    }

    public function ajax_import() {
        check_ajax_referer('hm_nonce', 'nonce');
        if (!current_user_can('edit_posts')) { wp_send_json_error('Permission denied'); return; }

        if (empty($_FILES['csv_file'])) { wp_send_json_error('No file uploaded'); return; }

        $type = sanitize_text_field($_POST['item_type'] ?? 'product');
        $file = $_FILES['csv_file']['tmp_name'];
        $handle = fopen($file, 'r');
        if (!$handle) { wp_send_json_error('Could not read file'); return; }

        $headers = fgetcsv($handle);
        if (!$headers) { fclose($handle); wp_send_json_error('Empty file'); return; }
        $headers = array_map('trim', array_map('strtolower', $headers));

        // Build manufacturer name→id map
        $mfrs = $this->get_manufacturers();
        $mfr_map = [];
        foreach ($mfrs as $m) $mfr_map[strtolower($m->name)] = (int)$m->id;

        $count = 0;
        $table = 'hearmed_reference.products';
        $now = current_time('mysql');

        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) < 2) continue;
            $r = array_combine($headers, array_pad($row, count($headers), ''));

            $data = [
                'item_type'  => $type,
                'category'   => self::$category_labels[$type] ?? 'Hearing Aid',
                'is_active'  => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if ($type === 'product') {
                $mfr_name = trim($r['manufacturer'] ?? '');
                $mfr_id   = $mfr_map[strtolower($mfr_name)] ?? null;
                $data['manufacturer_id'] = $mfr_id;
                $data['product_name']    = trim($r['model'] ?? '');
                $data['style']           = trim($r['style'] ?? '');
                $data['tech_level']      = trim($r['tech_level'] ?? '');
                $data['cost_price']      = ($r['cost_price'] ?? '') !== '' ? floatval($r['cost_price']) : null;
                $data['retail_price']    = ($r['retail_price'] ?? '') !== '' ? floatval($r['retail_price']) : null;
                // Auto-gen code
                $data['product_code'] = self::generate_code('product', [
                    'manufacturer_name' => $mfr_name,
                    'model'             => $data['product_name'],
                    'tech_level'        => $data['tech_level'],
                    'style'             => $data['style'],
                ]);
            } elseif ($type === 'service') {
                $data['product_name']  = trim($r['service_name'] ?? '');
                $data['retail_price']  = ($r['retail_price'] ?? '') !== '' ? floatval($r['retail_price']) : null;
                $data['product_code']  = self::generate_code('service', ['name' => $data['product_name']]);
            } elseif ($type === 'bundled') {
                $mfr_name = trim($r['manufacturer'] ?? '');
                $mfr_id   = $mfr_map[strtolower($mfr_name)] ?? null;
                $data['manufacturer_id']  = $mfr_id;
                $data['product_name']     = trim($r['item_name'] ?? '');
                $data['bundled_category'] = trim($r['category'] ?? '');
                $data['speaker_length']   = trim($r['speaker_length'] ?? '');
                $data['speaker_power']    = trim($r['speaker_power'] ?? '');
                $data['cost_price']       = ($r['cost_price'] ?? '') !== '' ? floatval($r['cost_price']) : null;
                $data['retail_price']     = ($r['retail_price'] ?? '') !== '' ? floatval($r['retail_price']) : null;
                $data['product_code']     = self::generate_code('bundled', ['name' => $data['product_name']]);
            } elseif ($type === 'accessory' || $type === 'consumable') {
                $mfr_name = trim($r['manufacturer'] ?? '');
                $mfr_id   = $mfr_map[strtolower($mfr_name)] ?? null;
                $data['manufacturer_id'] = $mfr_id;
                $data['product_name']    = trim($r['item_name'] ?? '');
                $data['cost_price']      = ($r['cost_price'] ?? '') !== '' ? floatval($r['cost_price']) : null;
                $data['retail_price']    = ($r['retail_price'] ?? '') !== '' ? floatval($r['retail_price']) : null;
                $data['product_code']    = self::generate_code('bundled', ['name' => $data['product_name']]);
            }

            if (!empty($data['product_name'])) {
                $result = HearMed_DB::insert($table, $data);
                if ($result) $count++;
            }
        }

        fclose($handle);
        wp_send_json_success(['count' => $count]);
    }

    public function ajax_add_bundled_category() {
        check_ajax_referer('hm_nonce', 'nonce');
        if (!current_user_can('edit_posts')) { wp_send_json_error('Denied'); return; }

        $name = sanitize_text_field($_POST['category_name'] ?? '');
        if (!$name) { wp_send_json_error('Name required'); return; }

        // Try to insert (table may not exist yet)
        $check = HearMed_DB::get_var("SELECT to_regclass('hearmed_reference.bundled_categories')");
        if ($check === null) {
            // Create table on the fly
            HearMed_DB::get_results("CREATE TABLE IF NOT EXISTS hearmed_reference.bundled_categories (
                id BIGINT PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
                category_name VARCHAR(100) NOT NULL UNIQUE,
                is_active BOOLEAN DEFAULT TRUE,
                sort_order INTEGER DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )");
            // Seed defaults
            HearMed_DB::get_results("INSERT INTO hearmed_reference.bundled_categories (category_name, sort_order) VALUES ('Speaker',1),('Dome',2),('Other',99) ON CONFLICT DO NOTHING");
        }

        $id = HearMed_DB::insert('hearmed_reference.bundled_categories', [
            'category_name' => $name,
            'created_at'    => current_time('mysql'),
        ]);

        if ($id) wp_send_json_success(['id' => $id]);
        else wp_send_json_error(HearMed_DB::last_error() ?: 'Error');
    }
}

new HearMed_Admin_Products();
