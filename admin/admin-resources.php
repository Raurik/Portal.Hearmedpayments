<?php
/**
 * HearMed Admin — Resources
 * Shortcode: [hearmed_admin_resources]
 * PostgreSQL CRUD for resources
 */
if (!defined('ABSPATH')) exit;

class HearMed_Admin_Resources {

    public function __construct() {
        add_shortcode('hearmed_admin_resources', [$this, 'render']);
        add_action('wp_ajax_hm_admin_save_resource', [$this, 'ajax_save']);
        add_action('wp_ajax_hm_admin_delete_resource', [$this, 'ajax_delete']);
    }

    private function get_resources() {
        return HearMed_DB::get_results(
            "SELECT id, title, category, url, description, sort_order, is_active
             FROM hearmed_reference.resources
             ORDER BY sort_order ASC, title ASC"
        ) ?: [];
    }

    public function render() {
        if (!is_user_logged_in()) return '<p>Please log in.</p>';
        $rows = $this->get_resources();

        ob_start(); ?>
        <div class="hm-admin" id="hm-resources-admin">
            <div class="hm-admin-hd">
                <h2>Resources</h2>
                <button class="hm-btn hm-btn-teal" onclick="hmRes.open()">+ Add Resource</button>
            </div>

            <?php if (empty($rows)): ?>
                <div class="hm-empty-state"><p>No resources yet.</p></div>
            <?php else: ?>
            <table class="hm-table">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Category</th>
                        <th>URL</th>
                        <th class="hm-num">Sort</th>
                        <th>Status</th>
                        <th style="width:100px"></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $r):
                    $row = json_encode((array) $r, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
                ?>
                    <tr>
                        <td><strong><?php echo esc_html($r->title); ?></strong></td>
                        <td><?php echo esc_html($r->category ?: '—'); ?></td>
                        <td><?php echo esc_html($r->url ?: '—'); ?></td>
                        <td class="hm-num"><?php echo esc_html((string) ($r->sort_order ?? 0)); ?></td>
                        <td><?php echo $r->is_active ? '<span class="hm-badge hm-badge-green">Active</span>' : '<span class="hm-badge hm-badge-red">Inactive</span>'; ?></td>
                        <td class="hm-table-acts">
                            <button class="hm-btn hm-btn-sm" onclick='hmRes.open(<?php echo $row; ?>)'>Edit</button>
                            <button class="hm-btn hm-btn-sm hm-btn-red" onclick="hmRes.del(<?php echo (int) $r->id; ?>,'<?php echo esc_js($r->title); ?>')">Delete</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>

            <div class="hm-modal-bg" id="hm-res-modal">
                <div class="hm-modal" style="width:640px">
                    <div class="hm-modal-hd">
                        <h3 id="hm-res-title">Add Resource</h3>
                        <button class="hm-modal-x" onclick="hmRes.close()">&times;</button>
                    </div>
                    <div class="hm-modal-body">
                        <input type="hidden" id="hmr-id">
                        <div class="hm-form-row">
                            <div class="hm-form-group">
                                <label>Title *</label>
                                <input type="text" id="hmr-title">
                            </div>
                            <div class="hm-form-group">
                                <label>Category</label>
                                <input type="text" id="hmr-category">
                            </div>
                        </div>
                        <div class="hm-form-group">
                            <label>URL</label>
                            <input type="text" id="hmr-url" placeholder="https://">
                        </div>
                        <div class="hm-form-row">
                            <div class="hm-form-group">
                                <label>Sort Order</label>
                                <input type="number" id="hmr-sort" min="0" step="1" value="0">
                            </div>
                            <div class="hm-form-group">
                                <label class="hm-toggle-label">
                                    <input type="checkbox" id="hmr-active" checked>
                                    Active
                                </label>
                            </div>
                        </div>
                        <div class="hm-form-group">
                            <label>Description</label>
                            <textarea id="hmr-desc" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="hm-modal-ft">
                        <button class="hm-btn" onclick="hmRes.close()">Cancel</button>
                        <button class="hm-btn hm-btn-teal" onclick="hmRes.save()" id="hmr-save">Save</button>
                    </div>
                </div>
            </div>
        </div>

        <script>
        var hmRes = {
            open: function(data) {
                var isEdit = !!(data && data.id);
                document.getElementById('hm-res-title').textContent = isEdit ? 'Edit Resource' : 'Add Resource';
                document.getElementById('hmr-id').value = isEdit ? data.id : '';
                document.getElementById('hmr-title').value = isEdit ? data.title : '';
                document.getElementById('hmr-category').value = isEdit ? (data.category || '') : '';
                document.getElementById('hmr-url').value = isEdit ? (data.url || '') : '';
                document.getElementById('hmr-sort').value = isEdit ? (data.sort_order || 0) : 0;
                document.getElementById('hmr-active').checked = isEdit ? !!data.is_active : true;
                document.getElementById('hmr-desc').value = isEdit ? (data.description || '') : '';
                document.getElementById('hm-res-modal').classList.add('open');
            },
            close: function() { document.getElementById('hm-res-modal').classList.remove('open'); },
            save: function() {
                var title = document.getElementById('hmr-title').value.trim();
                if (!title) { alert('Title is required.'); return; }

                var payload = {
                    action: 'hm_admin_save_resource',
                    nonce: HM.nonce,
                    id: document.getElementById('hmr-id').value,
                    title: title,
                    category: document.getElementById('hmr-category').value,
                    url: document.getElementById('hmr-url').value,
                    sort_order: document.getElementById('hmr-sort').value,
                    is_active: document.getElementById('hmr-active').checked ? 1 : 0,
                    description: document.getElementById('hmr-desc').value
                };

                var btn = document.getElementById('hmr-save');
                btn.textContent = 'Saving...'; btn.disabled = true;
                jQuery.post(HM.ajax_url, payload, function(r) {
                    if (r.success) location.reload();
                    else { alert(r.data || 'Error'); btn.textContent = 'Save'; btn.disabled = false; }
                });
            },
            del: function(id, name) {
                if (!confirm('Delete "' + name + '"?')) return;
                jQuery.post(HM.ajax_url, { action:'hm_admin_delete_resource', nonce:HM.nonce, id:id }, function(r) {
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
        if (!current_user_can('edit_posts')) { wp_send_json_error('Denied'); return; }

        $id = intval($_POST['id'] ?? 0);
        $title = sanitize_text_field($_POST['title'] ?? '');
        if (!$title) { wp_send_json_error('Missing fields'); return; }

        $data = [
            'title' => $title,
            'category' => sanitize_text_field($_POST['category'] ?? ''),
            'url' => sanitize_text_field($_POST['url'] ?? ''),
            'description' => sanitize_text_field($_POST['description'] ?? ''),
            'sort_order' => intval($_POST['sort_order'] ?? 0),
            'is_active' => intval($_POST['is_active'] ?? 1),
            'updated_at' => current_time('mysql'),
        ];

        if ($id) {
            $result = HearMed_DB::update('hearmed_reference.resources', $data, ['id' => $id]);
        } else {
            $data['created_at'] = current_time('mysql');
            $id = HearMed_DB::insert('hearmed_reference.resources', $data);
            $result = $id ? 1 : false;
        }

        if ($result === false) {
            wp_send_json_error('Database error');
        } else {
            wp_send_json_success(['id' => $id]);
        }
    }

    public function ajax_delete() {
        check_ajax_referer('hm_nonce', 'nonce');
        if (!current_user_can('edit_posts')) { wp_send_json_error('Denied'); return; }

        $id = intval($_POST['id'] ?? 0);
        if (!$id) { wp_send_json_error('Invalid ID'); return; }

        $result = HearMed_DB::update(
            'hearmed_reference.resources',
            ['is_active' => false, 'updated_at' => current_time('mysql')],
            ['id' => $id]
        );

        if ($result === false) {
            wp_send_json_error('Database error');
        } else {
            wp_send_json_success();
        }
    }
}

new HearMed_Admin_Resources();
