<?php
/**
 * HearMed Admin — Reference Data Managers
 * Shortcodes: [hearmed_brands], [hearmed_range_settings], [hearmed_lead_types]
 * PostgreSQL CRUD for reference tables
 */
if (!defined('ABSPATH')) exit;

class HearMed_Admin_Taxonomies {

    private $configs = [
        'hearmed_brands' => [
            'table' => 'hearmed_reference.manufacturers',
            'id_col' => 'id',
            'name_col' => 'name',
            'title' => 'Brands / Manufacturers',
            'singular' => 'Brand',
            'placeholder' => 'e.g. Widex, GN Hearing, Oticon',
        ],
        'hearmed_range_settings' => [
            'table' => 'hearmed_reference.hearmed_range',
            'id_col' => 'id',
            'name_col' => 'range_name',
            'title' => 'HearMed Range',
            'singular' => 'Range',
            'placeholder' => 'e.g. Premium, Premium+, Essential, Entry',
        ],
        'hearmed_lead_types' => [
            'table' => 'hearmed_reference.referral_sources',
            'id_col' => 'id',
            'name_col' => 'source_name',
            'title' => 'Lead Types / Referral Sources',
            'singular' => 'Source',
            'placeholder' => 'e.g. GP Referral, Walk-in, Website',
        ],
    ];

    public function __construct() {
        foreach (array_keys($this->configs) as $sc) {
            add_shortcode($sc, [$this, 'render']);
        }
        add_action('wp_ajax_hm_admin_save_term', [$this, 'ajax_save']);
        add_action('wp_ajax_hm_admin_delete_term', [$this, 'ajax_delete']);
    }

    public function render($atts, $content, $tag) {
        if (!is_user_logged_in()) return '<p>Please log in.</p>';
        $cfg = $this->configs[$tag] ?? null;
        if (!$cfg) return '<p>Unknown reference table.</p>';

        $table = $cfg['table'];
        $id_col = $cfg['id_col'];
        $name_col = $cfg['name_col'];
        
        $terms = HearMed_DB::get_results(
            "SELECT {$id_col} as term_id, {$name_col} as name FROM {$table} WHERE is_active = true ORDER BY {$name_col}"
        ) ?: [];

        ob_start(); ?>
        <div class="hm-admin">
            <div class="hm-admin-hd">
                <h2><?php echo esc_html($cfg['title']); ?></h2>
                <button class="hm-btn hm-btn-teal" onclick="hmTax.open('<?php echo esc_attr($tax); ?>')">+ Add <?php echo esc_html($cfg['singular']); ?></button>
            </div>

            <?php if (empty($terms)): ?>
                <div class="hm-empty-state"><p>No <?php echo esc_html(strtolower($cfg['title'])); ?> yet.</p></div>
            <?php else: ?>
            <table class="hm-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Slug</th>
                        <th class="hm-num">Products</th>
                        <th style="width:100px"></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($terms as $t): ?>
                    <tr>
                        <td><strong><?php echo esc_html($t->name); ?></strong></td>
                        <td><code><?php echo str_replace(' ', '-', strtolower(esc_html($t->name))); ?></code></td>
                        <td class="hm-num">—</td>
                        <td class="hm-table-acts">
                            <button class="hm-btn hm-btn-sm" onclick="hmTax.open('<?php echo esc_attr($tag); ?>',<?php echo $t->term_id; ?>,'<?php echo esc_js($t->name); ?>')">Edit</button>
                            <button class="hm-btn hm-btn-sm hm-btn-red" onclick="hmTax.del('<?php echo esc_attr($tag); ?>',<?php echo $t->term_id; ?>,'<?php echo esc_js($t->name); ?>')">Delete</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>

            <!-- Modal -->
            <div class="hm-modal-bg" id="hm-tax-modal">
                <div class="hm-modal" style="width:420px">
                    <div class="hm-modal-hd">
                        <h3 id="hm-tax-modal-title">Add <?php echo esc_html($cfg['singular']); ?></h3>
                        <button class="hm-modal-x" onclick="hmTax.close()">&times;</button>
                    </div>
                    <div class="hm-modal-body">
                        <input type="hidden" id="hmt-tag" value="">
                        <input type="hidden" id="hmt-id" value="">
                        <div class="hm-form-group">
                            <label>Name *</label>
                            <input type="text" id="hmt-name" placeholder="<?php echo esc_attr($cfg['placeholder']); ?>">
                        </div>
                    </div>
                    <div class="hm-modal-ft">
                        <button class="hm-btn" onclick="hmTax.close()">Cancel</button>
                        <button class="hm-btn hm-btn-teal" onclick="hmTax.save()" id="hmt-save-btn">Save</button>
                    </div>
                </div>
            </div>
        </div>

        <script>
        var hmTax = {
            open: function(tag, id, name) {
                document.getElementById('hm-tax-modal-title').textContent = id ? 'Edit' : 'Add';
                document.getElementById('hmt-tag').value = tag;
                document.getElementById('hmt-id').value = id || '';
                document.getElementById('hmt-name').value = name || '';
                document.getElementById('hm-tax-modal').classList.add('open');
                document.getElementById('hmt-name').focus();
            },
            close: function() { document.getElementById('hm-tax-modal').classList.remove('open'); },
            save: function() {
                var name = document.getElementById('hmt-name').value.trim();
                if (!name) { alert('Name is required.'); return; }
                var btn = document.getElementById('hmt-save-btn');
                btn.textContent = 'Saving...'; btn.disabled = true;
                jQuery.post(HM.ajax_url, {
                    action: 'hm_admin_save_term',
                    nonce: HM.nonce,
                    tag: document.getElementById('hmt-tag').value,
                    term_id: document.getElementById('hmt-id').value,
                    name: name
                }, function(r) {
                    if (r.success) location.reload();
                    else { alert(r.data || 'Error'); btn.textContent = 'Save'; btn.disabled = false; }
                });
            },
            del: function(tag, id, name) {
                if (!confirm('Delete "' + name + '"?')) return;
                jQuery.post(HM.ajax_url, {
                    action: 'hm_admin_delete_term',
                    nonce: HM.nonce,
                    tag: tag,
                    term_id: id
                }, function(r) {
                    if (r.success) location.reload();
                    else alert(r.data || 'Error');
                });
            }
        };
        </script>
        <?php
        return ob_get_clean();
    }

    public function ajax_save() {
        check_ajax_referer('hm_nonce', 'nonce');
        if (!current_user_can('edit_posts')) { wp_send_json_error('Permission denied'); return; }

        $tag = sanitize_text_field($_POST['tag'] ?? '');
        $name = sanitize_text_field($_POST['name'] ?? '');
        $term_id = intval($_POST['term_id'] ?? 0);

        if (empty($tag) || empty($name)) { wp_send_json_error('Missing fields'); return; }

        $cfg = $this->configs[$tag] ?? null;
        if (!$cfg) { wp_send_json_error('Unknown reference table'); return; }

        $table = $cfg['table'];
        $id_col = $cfg['id_col'];
        $name_col = $cfg['name_col'];

        if ($term_id) {
            $result = HearMed_DB::update(
                $table,
                [$name_col => $name, 'updated_at' => current_time('mysql')],
                [$id_col => $term_id],
                ['%s', '%s'],
                ['%d']
            );
        } else {
            $result = HearMed_DB::insert(
                $table,
                [$name_col => $name, 'is_active' => true, 'created_at' => current_time('mysql')],
                ['%s', '%d', '%s']
            );
        }

        if ($result === false) {
            wp_send_json_error('Database error');
        } else {
            wp_send_json_success(['id' => $term_id ?: HearMed_DB::$last_insert_id]);
        }
    }

    public function ajax_delete() {
        check_ajax_referer('hm_nonce', 'nonce');
        if (!current_user_can('edit_posts')) { wp_send_json_error('Permission denied'); return; }

        $tag = sanitize_text_field($_POST['tag'] ?? '');
        $term_id = intval($_POST['term_id'] ?? 0);

        if (empty($tag) || !$term_id) { wp_send_json_error('Missing parameters'); return; }

        $cfg = $this->configs[$tag] ?? null;
        if (!$cfg) { wp_send_json_error('Unknown reference table'); return; }

        $table = $cfg['table'];
        $id_col = $cfg['id_col'];

        // Soft delete by setting is_active = false
        HearMed_DB::update(
            $table,
            ['is_active' => false, 'updated_at' => current_time('mysql')],
            [$id_col => $term_id],
            ['%d', '%s'],
            ['%d']
        );
        
        wp_send_json_success();
    }
}

new HearMed_Admin_Taxonomies();
