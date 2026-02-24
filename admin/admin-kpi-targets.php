<?php
/**
 * HearMed Admin — KPI Targets
 * Shortcode: [hearmed_kpi_targets]
 * CRUD for kpi_targets table (hearmed_admin.kpi_targets)
 *
 * @package HearMed_Portal
 * @since   5.0.0
 */
if (!defined('ABSPATH')) exit;

class HearMed_Admin_KPI_Targets {

    private function table() { return HearMed_DB::table('kpi_targets'); }

    public function __construct() {
        add_shortcode('hearmed_kpi_targets', [$this, 'render']);
        add_action('wp_ajax_hm_admin_save_kpi_target', [$this, 'ajax_save']);
    }

    private function get_targets() {
        $t = $this->table();
        if (HearMed_DB::get_var( HearMed_DB::prepare( "SELECT to_regclass(%s)", $t ) ) === null) return [];
        return HearMed_DB::get_results("SELECT * FROM {$t} ORDER BY id ASC") ?: [];
    }

    public function render() {
        if (!is_user_logged_in()) return '<p>Please log in.</p>';
        $targets = $this->get_targets();

        ob_start(); ?>
        <div class="hm-admin">
            <div class="hm-admin-hd">
                <h2>KPI Targets</h2>
                <button class="hm-btn hm-btn-teal" onclick="hmKpi.saveAll()" id="hmk-save">Save All</button>
            </div>

            <p style="color:var(--hm-text-light);font-size:13px;margin-bottom:20px;">Set target values for each KPI metric. These appear on the KPI dashboard alongside actual values.</p>

            <?php if (empty($targets)): ?>
                <div class="hm-empty-state"><p>No KPI targets found. Deactivate and reactivate the plugin to seed defaults.</p></div>
            <?php else: ?>
            <table class="hm-table">
                <thead>
                    <tr>
                        <th>Metric</th>
                        <th style="width:150px">Target Value</th>
                        <th style="width:80px">Unit</th>
                        <th style="width:80px">Active</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($targets as $t): ?>
                <tr data-id="<?php echo (int) $t->id; ?>">
                    <td><strong><?php echo esc_html($t->target_name); ?></strong></td>
                    <td>
                        <input type="number" class="hmk-val" data-id="<?php echo (int) $t->id; ?>" value="<?php echo esc_attr($t->target_value); ?>" step="0.1" min="0" style="width:120px;padding:6px 10px;border:1px solid var(--hm-border);border-radius:6px;font-size:14px;font-weight:600;">
                    </td>
                    <td>
                        <span class="hm-badge hm-badge-blue"><?php echo esc_html($t->target_unit); ?></span>
                    </td>
                    <td>
                        <label class="hm-toggle-label">
                            <input type="checkbox" class="hmk-active" data-id="<?php echo (int) $t->id; ?>" <?php echo $t->is_active ? 'checked' : ''; ?>>
                        </label>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <script>
        var hmKpi = {
            saveAll: function() {
                var targets = [];
                document.querySelectorAll('.hmk-val').forEach(function(inp) {
                    var id = inp.dataset.id;
                    var active = document.querySelector('.hmk-active[data-id="'+id+'"]');
                    targets.push({
                        id: id,
                        value: inp.value,
                        active: active && active.checked ? 1 : 0
                    });
                });

                var btn = document.getElementById('hmk-save');
                btn.textContent = 'Saving...'; btn.disabled = true;

                jQuery.post(HM.ajax_url, {
                    action: 'hm_admin_save_kpi_target',
                    nonce: HM.nonce,
                    targets: JSON.stringify(targets)
                }, function(r) {
                    if (r.success) {
                        btn.textContent = '✓ Saved';
                        setTimeout(function() { btn.textContent = 'Save All'; btn.disabled = false; }, 1500);
                    } else {
                        alert(r.data || 'Error');
                        btn.textContent = 'Save All'; btn.disabled = false;
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

        $t = $this->table();
        $targets = json_decode(stripslashes($_POST['targets'] ?? '[]'), true);

        if (!is_array($targets)) { wp_send_json_error('Invalid data'); return; }

        $uid = get_current_user_id();
        $now = current_time('mysql');

        foreach ($targets as $tgt) {
            HearMed_DB::update($t, [
                'target_value' => floatval($tgt['value']),
                'is_active'    => intval($tgt['active']),
                'updated_by'   => $uid,
                'updated_at'   => $now,
            ], ['id' => intval($tgt['id'])]);
        }

        wp_send_json_success();
    }
}

new HearMed_Admin_KPI_Targets();
