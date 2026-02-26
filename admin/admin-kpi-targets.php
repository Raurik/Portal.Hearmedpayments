<?php
/**
 * HearMed Admin — KPI Targets (Per-Dispenser)
 * Shortcode: [hearmed_kpi_targets]
 * Stores per-dispenser targets in hearmed_admin.kpi_targets (with staff_id column).
 * Also supports global targets (staff_id = NULL).
 *
 * @package HearMed_Portal
 * @since   5.1.0
 */
if (!defined('ABSPATH')) exit;

class HearMed_Admin_KPI_Targets {

    private static $metrics = [
        'closing_rate'            => ['label' => 'Closing Rate',                'unit' => '%',  'default' => 50],
        'conversion_rate'         => ['label' => 'Conversion Rate',             'unit' => '%',  'default' => 40],
        'appointment_completion'  => ['label' => 'Appointment Completion Rate', 'unit' => '%',  'default' => 90],
        'binaural_rate'           => ['label' => 'Binaural Rate',               'unit' => '%',  'default' => 70],
        'avg_order_price'         => ['label' => 'Average Order Price',         'unit' => '€',  'default' => 2500],
        'wax_to_test_rate'        => ['label' => 'Wax Removal to Test Rate',    'unit' => '%',  'default' => 30],
    ];

    public function __construct() {
        add_shortcode('hearmed_kpi_targets', [$this, 'render']);
        add_action('wp_ajax_hm_admin_save_kpi_targets', [$this, 'ajax_save']);
    }

    private function get_dispensers() {
        return HearMed_DB::get_results(
            "SELECT id, first_name, last_name FROM hearmed_reference.staff
             WHERE is_active = true AND role IN ('dispenser','audiologist','Dispenser','Audiologist')
             ORDER BY last_name, first_name"
        ) ?: [];
    }

    private function get_targets() {
        $t = HearMed_DB::table('kpi_targets');
        $check = HearMed_DB::get_var("SELECT to_regclass('{$t}')");
        if ($check === null) return [];
        return HearMed_DB::get_results("SELECT * FROM {$t} ORDER BY staff_id NULLS FIRST, target_name") ?: [];
    }

    public function render() {
        if (!is_user_logged_in()) return '<p>Please log in.</p>';

        $dispensers = $this->get_dispensers();
        $targets    = $this->get_targets();
        $metrics    = self::$metrics;

        // Build lookup: staff_id => metric_key => target_value
        $target_map = [];
        foreach ($targets as $t) {
            $sid = $t->staff_id ?: 'global';
            if (!isset($target_map[$sid])) $target_map[$sid] = [];
            $target_map[$sid][$t->target_name] = [
                'id'    => $t->id,
                'value' => $t->target_value,
                'active' => $t->is_active,
            ];
        }

        // Build tabs: global + each dispenser
        $tabs = [['id' => 'global', 'label' => 'Global Defaults']];
        foreach ($dispensers as $d) {
            $tabs[] = ['id' => $d->id, 'label' => trim($d->first_name . ' ' . $d->last_name)];
        }

        $active_tab = sanitize_text_field($_GET['staff'] ?? 'global');
        if ($active_tab !== 'global') $active_tab = intval($active_tab);
        $found = false;
        foreach ($tabs as $tab) { if ($tab['id'] == $active_tab) { $found = true; break; } }
        if (!$found) $active_tab = 'global';

        $current_targets = $target_map[$active_tab] ?? [];

        ob_start(); ?>
        <div class="hm-admin">
            <div style="margin-bottom:16px;"><a href="<?php echo esc_url(home_url("/admin-console/")); ?>" class="hm-btn">&larr; Back</a></div>
            <div class="hm-admin-hd">
                <h2>KPI Targets</h2>
                <button class="hm-btn hm-btn--primary" onclick="hmKpi.saveAll()" id="hmk-save">Save Targets</button>
            </div>

            <p style="color:var(--hm-text-light);font-size:13px;margin-bottom:20px;">
                Set KPI targets per dispenser. Global defaults apply to any dispenser without specific targets.
            </p>

            <!-- Dispenser Tabs -->
            <div class="hm-tab-bar">
                <?php foreach ($tabs as $tab):
                    $is_active = ($tab['id'] == $active_tab);
                ?>
                <a href="?staff=<?php echo esc_attr($tab['id']); ?>"
                   class="hm-tab<?php echo $is_active ? ' active' : ''; ?>">
                    <?php echo esc_html($tab['label']); ?>
                </a>
                <?php endforeach; ?>
            </div>

            <div class="hm-settings-panel">
                <input type="hidden" id="hmk-staff-id" value="<?php echo esc_attr($active_tab); ?>">
                <table class="hm-table">
                    <thead>
                        <tr>
                            <th>Metric</th>
                            <th style="width:150px">Target Value</th>
                            <th style="width:60px">Unit</th>
                            <th style="width:80px">Active</th>
                            <?php if ($active_tab !== 'global'): ?>
                            <th style="width:100px">Global Default</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($metrics as $key => $meta):
                        $saved = $current_targets[$key] ?? null;
                        $value = $saved ? $saved['value'] : $meta['default'];
                        $active = $saved ? $saved['active'] : true;
                        $global_val = isset($target_map['global'][$key]) ? $target_map['global'][$key]['value'] : $meta['default'];
                    ?>
                    <tr>
                        <td><strong><?php echo esc_html($meta['label']); ?></strong></td>
                        <td>
                            <input type="number" class="hmk-val" data-key="<?php echo esc_attr($key); ?>" value="<?php echo esc_attr($value); ?>" step="1" min="0" style="width:120px;padding:6px 10px;border:1px solid var(--hm-border);border-radius:6px;font-size:13px;font-weight:400;">
                        </td>
                        <td><span class="hm-badge hm-badge--blue"><?php echo esc_html($meta['unit']); ?></span></td>
                        <td>
                            <label class="hm-toggle-label">
                                <input type="checkbox" class="hmk-active" data-key="<?php echo esc_attr($key); ?>" <?php checked($active); ?>>
                            </label>
                        </td>
                        <?php if ($active_tab !== 'global'): ?>
                        <td style="color:var(--hm-text-light);font-size:12px;"><?php echo esc_html($global_val . '' . $meta['unit']); ?></td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <script>
        var hmKpi = {
            saveAll: function() {
                var staffId = document.getElementById('hmk-staff-id').value;
                var targets = [];
                document.querySelectorAll('.hmk-val').forEach(function(inp) {
                    var key = inp.dataset.key;
                    var active_cb = document.querySelector('.hmk-active[data-key="'+key+'"]');
                    targets.push({
                        key: key,
                        value: inp.value,
                        active: active_cb && active_cb.checked ? 1 : 0
                    });
                });

                var btn = document.getElementById('hmk-save');
                btn.textContent = 'Saving...'; btn.disabled = true;

                jQuery.post(HM.ajax_url, {
                    action: 'hm_admin_save_kpi_targets',
                    nonce: HM.nonce,
                    staff_id: staffId,
                    targets: JSON.stringify(targets)
                }, function(r) {
                    if (r.success) {
                        btn.textContent = '✓ Saved';
                        setTimeout(function() { btn.textContent = 'Save Targets'; btn.disabled = false; }, 1500);
                    } else {
                        alert(r.data || 'Error');
                        btn.textContent = 'Save Targets'; btn.disabled = false;
                    }
                });
            }
        };
        </script>
        <?php return ob_get_clean();
    }

    public function ajax_save() {
        check_ajax_referer('hm_nonce', 'nonce');
        if (!current_user_can('edit_posts')) { wp_send_json_error('Denied'); return; }

        $t = HearMed_DB::table('kpi_targets');
        $staff_id_raw = sanitize_text_field($_POST['staff_id'] ?? 'global');
        $staff_id = ($staff_id_raw === 'global') ? null : intval($staff_id_raw);

        $targets = json_decode(stripslashes($_POST['targets'] ?? '[]'), true);
        if (!is_array($targets)) { wp_send_json_error('Invalid data'); return; }

        $uid = get_current_user_id();
        $now = current_time('mysql');

        foreach ($targets as $tgt) {
            $key   = sanitize_text_field($tgt['key']);
            $value = floatval($tgt['value']);
            $active = intval($tgt['active']);

            // Check if target exists for this staff_id + key
            if ($staff_id === null) {
                $existing = HearMed_DB::get_var(
                    "SELECT id FROM {$t} WHERE target_name = $1 AND staff_id IS NULL",
                    [$key]
                );
            } else {
                $existing = HearMed_DB::get_var(
                    "SELECT id FROM {$t} WHERE target_name = $1 AND staff_id = $2",
                    [$key, $staff_id]
                );
            }

            $data = [
                'target_value' => $value,
                'is_active'    => $active,
                'updated_by'   => $uid,
                'updated_at'   => $now,
            ];

            if ($existing) {
                HearMed_DB::update($t, $data, ['id' => intval($existing)]);
            } else {
                $data['target_name'] = $key;
                $data['target_unit'] = self::$metrics[$key]['unit'] ?? '%';
                $data['staff_id']    = $staff_id;
                $data['created_at']  = $now;
                HearMed_DB::insert($t, $data);
            }
        }

        wp_send_json_success();
    }
}

new HearMed_Admin_KPI_Targets();
