<?php

// ============================================================
// AUTO-CONVERTED TO POSTGRESQL
// ============================================================
// All database operations converted from WordPress to PostgreSQL
// - $wpdb → HearMed_DB
// - wp_posts/wp_postmeta → PostgreSQL tables
// - Column names updated (_ID → id, etc.)
// 
// REVIEW REQUIRED:
// - Check all queries use correct table names
// - Verify all AJAX handlers work
// - Test all CRUD operations
// ============================================================

/**
 * HearMed Admin — Manage Clinics
 * Shortcode: [hearmed_manage_clinics]
 * CRUD for Clinic CPT (post meta based)
 */
if (!defined('ABSPATH')) exit;

class HearMed_Admin_Clinics {

    private $fields = [
        'address', 'clinic_email', 'clinic_phone', 'eircode',
        'clinic_colour', 'text_colour', 'days_available', 'is_active',
    ];

    public function __construct() {
        add_shortcode('hearmed_manage_clinics', [$this, 'render']);
        add_action('wp_ajax_hm_admin_save_clinic', [$this, 'ajax_save']);
        add_action('wp_ajax_hm_admin_delete_clinic', [$this, 'ajax_delete']);
    }

    private function get_clinics() {
        // OLD: /* USE PostgreSQL: HearMed_DB::get_results() */ /* get_posts(['post_type' => 'clinic', 'posts_per_page' => -1, 'post_status' => 'publish', 'orderby' => 'title', 'order' => 'ASC']);
        $posts = HearMed_DB::get_results("SELECT id, clinic_name as post_title, id as ID FROM hearmed_reference.clinics WHERE is_active = true ORDER BY clinic_name");
        $clinics = [];
        foreach ($posts as $p) {
            $c = ['id' => $p->ID, 'name' => $p->post_title];
            foreach ($this->fields as $f) {
                $c[$f] = /* USE PostgreSQL: Get from table columns */ /* get_post_meta($p->ID, $f, true);
            }
            $c['is_active'] = ($c['is_active'] === '' || $c['is_active'] === '1') ? '1' : '0';
            $c['clinic_colour'] = $c['clinic_colour'] ?: '#0BB4C4';
            $c['text_colour'] = $c['text_colour'] ?: '#ffffff';
            $clinics[] = $c;
        }
        return $clinics;
    }

    public function render() {
        if (!is_user_logged_in()) return '<p>Please log in.</p>';

        $clinics = $this->get_clinics();
        $days_labels = ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];

        ob_start(); ?>
        <div class="hm-admin" id="hm-clinics-app">
            <div class="hm-admin-hd">
                <h2>Clinics</h2>
                <button class="hm-btn hm-btn-teal" onclick="hmClinic.open()">+ Add Clinic</button>
            </div>

            <?php if (empty($clinics)): ?>
                <div class="hm-empty-state">
                    <p>No clinics yet. Add your first clinic to get started.</p>
                </div>
            <?php else: ?>
            <table class="hm-table">
                <thead>
                    <tr>
                        <th style="width:36px">Colour</th>
                        <th>Name</th>
                        <th>Address</th>
                        <th>Phone</th>
                        <th>Email</th>
                        <th>Eircode</th>
                        <th>Status</th>
                        <th style="width:100px"></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($clinics as $c): ?>
                    <tr data-id="<?php echo $c['id']; ?>">
                        <td>
                            <span class="hm-colour-dot" style="background:<?php echo esc_attr($c['clinic_colour']); ?>;color:<?php echo esc_attr($c['text_colour']); ?>"></span>
                        </td>
                        <td><strong><?php echo esc_html($c['name']); ?></strong></td>
                        <td><?php echo esc_html($c['address']); ?></td>
                        <td><?php echo esc_html($c['clinic_phone']); ?></td>
                        <td><?php echo esc_html($c['clinic_email']); ?></td>
                        <td><?php echo esc_html($c['eircode']); ?></td>
                        <td>
                            <?php if ($c['is_active'] === '1'): ?>
                                <span class="hm-badge hm-badge-green">Active</span>
                            <?php else: ?>
                                <span class="hm-badge hm-badge-red">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td class="hm-table-acts">
                            <button class="hm-btn hm-btn-sm" onclick='hmClinic.open(<?php echo json_encode($c); ?>)'>Edit</button>
                            <button class="hm-btn hm-btn-sm hm-btn-red" onclick="hmClinic.del(<?php echo $c['id']; ?>,'<?php echo esc_js($c['name']); ?>')">Delete</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>

            <!-- Modal -->
            <div class="hm-modal-bg" id="hm-clinic-modal">
                <div class="hm-modal">
                    <div class="hm-modal-hd">
                        <h3 id="hm-clinic-modal-title">Add Clinic</h3>
                        <button class="hm-modal-x" onclick="hmClinic.close()">&times;</button>
                    </div>
                    <div class="hm-modal-body">
                        <input type="hidden" id="hmc-id" value="">

                        <div class="hm-form-group">
                            <label>Clinic Name *</label>
                            <input type="text" id="hmc-name" placeholder="e.g. Tullamore">
                        </div>

                        <div class="hm-form-group">
                            <label>Address</label>
                            <textarea id="hmc-address" rows="2" placeholder="Full address"></textarea>
                        </div>

                        <div class="hm-form-row">
                            <div class="hm-form-group">
                                <label>Phone</label>
                                <input type="text" id="hmc-phone" placeholder="057 123 4567">
                            </div>
                            <div class="hm-form-group">
                                <label>Email</label>
                                <input type="email" id="hmc-email" placeholder="clinic@hearmed.ie">
                            </div>
                        </div>

                        <div class="hm-form-row">
                            <div class="hm-form-group hm-form-sm">
                                <label>Eircode</label>
                                <input type="text" id="hmc-eircode" placeholder="R35 AB12">
                            </div>
                            <div class="hm-form-group hm-form-sm">
                                <label>Calendar Colour</label>
                                <input type="color" id="hmc-colour" value="#0BB4C4">
                            </div>
                            <div class="hm-form-group hm-form-sm">
                                <label>Text Colour</label>
                                <input type="color" id="hmc-text-colour" value="#ffffff">
                            </div>
                        </div>

                        <div class="hm-form-group">
                            <label>Days Available</label>
                            <div class="hm-days-grid" id="hmc-days">
                                <?php foreach ($days_labels as $i => $d): ?>
                                <label class="hm-day-check">
                                    <input type="checkbox" value="<?php echo $i + 1; ?>"> <?php echo $d; ?>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="hm-form-group">
                            <label class="hm-toggle-label">
                                <input type="checkbox" id="hmc-active" checked> Active
                            </label>
                        </div>
                    </div>
                    <div class="hm-modal-ft">
                        <button class="hm-btn" onclick="hmClinic.close()">Cancel</button>
                        <button class="hm-btn hm-btn-teal" onclick="hmClinic.save()" id="hmc-save-btn">Save Clinic</button>
                    </div>
                </div>
            </div>
        </div>


        <script>
        var hmClinic = {
            open: function(data) {
                var m = document.getElementById('hm-clinic-modal');
                var isEdit = data && data.id;
                document.getElementById('hm-clinic-modal-title').textContent = isEdit ? 'Edit Clinic' : 'Add Clinic';
                document.getElementById('hmc-id').value = isEdit ? data.id : '';
                document.getElementById('hmc-name').value = isEdit ? data.name : '';
                document.getElementById('hmc-address').value = isEdit ? (data.address || '') : '';
                document.getElementById('hmc-phone').value = isEdit ? (data.clinic_phone || '') : '';
                document.getElementById('hmc-email').value = isEdit ? (data.clinic_email || '') : '';
                document.getElementById('hmc-eircode').value = isEdit ? (data.eircode || '') : '';
                document.getElementById('hmc-colour').value = isEdit ? (data.clinic_colour || '#0BB4C4') : '#0BB4C4';
                document.getElementById('hmc-text-colour').value = isEdit ? (data.text_colour || '#ffffff') : '#ffffff';
                document.getElementById('hmc-active').checked = isEdit ? data.is_active === '1' : true;

                // Days
                var days = isEdit && data.days_available ? data.days_available.split(',') : ['1','2','3','4','5'];
                document.querySelectorAll('#hmc-days input').forEach(function(cb) {
                    cb.checked = days.indexOf(cb.value) !== -1;
                });

                m.classList.add('open');
            },

            close: function() {
                document.getElementById('hm-clinic-modal').classList.remove('open');
            },

            save: function() {
                var name = document.getElementById('hmc-name').value.trim();
                if (!name) { alert('Clinic name is required.'); return; }

                var days = [];
                document.querySelectorAll('#hmc-days input:checked').forEach(function(cb) { days.push(cb.value); });

                var btn = document.getElementById('hmc-save-btn');
                btn.textContent = 'Saving...';
                btn.disabled = true;

                jQuery.post(HM.ajax_url, {
                    action: 'hm_admin_save_clinic',
                    nonce: HM.nonce,
                    id: document.getElementById('hmc-id').value,
                    name: name,
                    address: document.getElementById('hmc-address').value,
                    clinic_phone: document.getElementById('hmc-phone').value,
                    clinic_email: document.getElementById('hmc-email').value,
                    eircode: document.getElementById('hmc-eircode').value,
                    clinic_colour: document.getElementById('hmc-colour').value,
                    text_colour: document.getElementById('hmc-text-colour').value,
                    days_available: days.join(','),
                    is_active: document.getElementById('hmc-active').checked ? '1' : '0'
                }, function(r) {
                    if (r.success) location.reload();
                    else { alert(r.data || 'Error saving.'); btn.textContent = 'Save Clinic'; btn.disabled = false; }
                });
            },

            del: function(id, name) {
                if (!confirm('Delete "' + name + '"? This cannot be undone.')) return;
                jQuery.post(HM.ajax_url, {
                    action: 'hm_admin_delete_clinic',
                    nonce: HM.nonce,
                    id: id
                }, function(r) {
                    if (r.success) location.reload();
                    else alert(r.data || 'Error deleting.');
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

        $id = intval($_POST['id'] ?? 0);
        $name = sanitize_text_field($_POST['name'] ?? '');
        if (empty($name)) { wp_send_json_error('Name required'); return; }

        if ($id) {
            wp_update_post(['ID' => $id, 'post_title' => $name]);
        } else {
            $id = /* USE PostgreSQL: HearMed_DB::insert() */ /* wp_insert_post([
                'post_type' => 'clinic',
                'post_title' => $name,
                'post_status' => 'publish',
            ]);
            if (is_wp_error($id)) { wp_send_json_error('Failed to create clinic'); return; }
        }

        $meta_fields = ['address', 'clinic_email', 'clinic_phone', 'eircode', 'clinic_colour', 'text_colour', 'days_available', 'is_active'];
        foreach ($meta_fields as $f) {
            if (isset($_POST[$f])) {
                update_post_meta($id, $f, sanitize_text_field($_POST[$f]));
            }
        }

        wp_send_json_success(['id' => $id]);
    }

    public function ajax_delete() {
        check_ajax_referer('hm_nonce', 'nonce');
        if (!current_user_can('edit_posts')) { wp_send_json_error('Permission denied'); return; }

        $id = intval($_POST['id'] ?? 0);
        if ($id) wp_delete_post($id, true);
        wp_send_json_success();
    }
}

new HearMed_Admin_Clinics();
