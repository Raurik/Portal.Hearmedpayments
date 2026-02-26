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
        // PostgreSQL source of truth: hearmed_reference.clinics
        $rows = HearMed_DB::get_results(
            "SELECT id, clinic_name, address_line1, phone, email, postcode, clinic_color, is_active, opening_hours
             FROM hearmed_reference.clinics
             WHERE is_active = true
             ORDER BY clinic_name"
        );

        $clinics = [];

        foreach ($rows as $row) {
            $extra = [
                'days_available' => '1,2,3,4,5',
                'text_colour'    => '#ffffff',
            ];

            if (!empty($row->opening_hours)) {
                $decoded = json_decode($row->opening_hours, true);
                if (is_array($decoded)) {
                    if (!empty($decoded['days_available'])) {
                        $extra['days_available'] = (string) $decoded['days_available'];
                    }
                    if (!empty($decoded['text_colour'])) {
                        $extra['text_colour'] = (string) $decoded['text_colour'];
                    }
                }
            }

            $clinics[] = [
                'id'             => (int) $row->id,
                'name'           => $row->clinic_name,
                'address'        => $row->address_line1 ?? '',
                'clinic_phone'   => $row->phone ?? '',
                'clinic_email'   => $row->email ?? '',
                'eircode'        => $row->postcode ?? '',
                'clinic_colour'  => $row->clinic_color ?: 'var(--hm-teal)',
                'text_colour'    => $extra['text_colour'],
                'days_available' => $extra['days_available'],
                'is_active'      => $row->is_active ? '1' : '0',
            ];
        }

        return $clinics;
    }

    public function render() {
        if (!is_user_logged_in()) return '<p>Please log in.</p>';

        $clinics = $this->get_clinics();
        $days_labels = ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];

        ob_start(); ?>
        <div class="hm-page" id="hm-clinics-app">
            <a href="<?php echo esc_url(home_url("/admin-console/")); ?>" class="hm-back">← Back</a>
            <div class="hm-page-hd">
                <h1 class="hm-page-title">Clinics</h1>
                <button class="hm-btn--add" type="button" onclick="hmClinic.open()">+ Add Clinic</button>
            </div>

            <?php if (empty($clinics)): ?>
                <div class="hm-empty-state">
                    <p>No clinics yet. Add your first clinic to get started.</p>
                </div>
            <?php else: ?>
                <div class="hm-card hm-clinics-table-card">
                    <table class="hm-table hm-clinics-table">
                        <thead>
                            <tr>
                                <th style="width:50px">Colour</th>
                                <th style="width:140px">Name</th>
                                <th style="width:180px">Address</th>
                                <th style="width:120px">Phone</th>
                                <th style="width:160px">Email</th>
                                <th style="width:90px">Eircode</th>
                                <th style="width:80px">Status</th>
                                <th style="width:140px">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($clinics as $c): ?>
                            <tr data-id="<?php echo $c['id']; ?>">
                                <td style="text-align:center">
                                    <span class="hm-colour-dot" style="background:<?php echo esc_attr($c['clinic_colour']); ?>;color:<?php echo esc_attr($c['text_colour']); ?>"></span>
                                </td>
                                <td>
                                    <strong class="hm-clinic-name" onclick='hmClinic.open(<?php echo json_encode($c); ?>)'>
                                        <?php echo esc_html($c['name']); ?>
                                    </strong>
                                </td>
                                <td><?php echo esc_html($c['address']); ?></td>
                                <td><?php echo esc_html($c['clinic_phone']); ?></td>
                                <td><?php echo esc_html($c['clinic_email']); ?></td>
                                <td><?php echo esc_html($c['eircode']); ?></td>
                                <td>
                                    <?php if ($c['is_active'] === '1'): ?>
                                        <span class="hm-badge hm-badge--green">Active</span>
                                    <?php else: ?>
                                        <span class="hm-badge hm-badge--red">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td class="hm-table-acts">
                                    <button class="hm-btn hm-btn--sm" onclick='hmClinic.open(<?php echo json_encode($c); ?>)'>Edit</button>
                                    <button class="hm-btn hm-btn--sm hm-btn--danger" onclick="hmClinic.del(<?php echo $c['id']; ?>,'<?php echo esc_js($c['name']); ?>')">Delete</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <!-- Add/Edit Form (hidden by default) -->
            <div class="hm-card hm-clinic-form" id="hm-clinic-form">
                <div class="hm-form-hd">
                    <h2 id="hm-clinic-form-title">Add Clinic</h2>
                </div>
                <div class="hm-form-body">
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
                                <input type="checkbox" id="hmc-active" checked> <span>Active</span>
                            </label>
                        </div>
                </div>
                <div class="hm-form-ft">
                    <button class="hm-btn" onclick="hmClinic.close()">Cancel</button>
                    <button class="hm-btn hm-btn--primary" onclick="hmClinic.save()" id="hmc-save-btn">Save Clinic</button>
                </div>
            </div>
        </div>


        <script>
        var hmClinic = {
            open: function(data) {
                var form = document.getElementById('hm-clinic-form');
                var isEdit = data && data.id;
                document.getElementById('hm-clinic-form-title').textContent = isEdit ? 'Edit Clinic' : 'Add Clinic';
                document.getElementById('hmc-id').value = isEdit ? data.id : '';
                document.getElementById('hmc-name').value = isEdit ? data.name : '';
                document.getElementById('hmc-address').value = isEdit ? (data.address || '') : '';
                document.getElementById('hmc-phone').value = isEdit ? (data.clinic_phone || '') : '';
                document.getElementById('hmc-email').value = isEdit ? (data.clinic_email || '') : '';
                document.getElementById('hmc-eircode').value = isEdit ? (data.eircode || '') : '';
                document.getElementById('hmc-colour').value = isEdit ? (data.clinic_colour || 'var(--hm-teal)') : 'var(--hm-teal)';
                document.getElementById('hmc-text-colour').value = isEdit ? (data.text_colour || '#ffffff') : '#ffffff';
                document.getElementById('hmc-active').checked = isEdit ? data.is_active === '1' : true;

                // Days
                var days = isEdit && data.days_available ? data.days_available.split(',') : ['1','2','3','4','5'];
                document.querySelectorAll('#hmc-days input').forEach(function(cb) {
                    cb.checked = days.indexOf(cb.value) !== -1;
                });

                form.style.display = 'block';
                form.scrollIntoView({ behavior: 'smooth', block: 'start' });
            },

            close: function() {
                var form = document.getElementById('hm-clinic-form');
                form.style.display = 'none';

                // Reset form back to "Add Clinic" state
                document.getElementById('hm-clinic-form-title').textContent = 'Add Clinic';
                document.getElementById('hmc-id').value = '';
                document.getElementById('hmc-name').value = '';
                document.getElementById('hmc-address').value = '';
                document.getElementById('hmc-phone').value = '';
                document.getElementById('hmc-email').value = '';
                document.getElementById('hmc-eircode').value = '';
                document.getElementById('hmc-colour').value = 'var(--hm-teal)';
                document.getElementById('hmc-text-colour').value = '#ffffff';
                document.getElementById('hmc-active').checked = true;

                var defaultDays = ['1','2','3','4','5'];
                document.querySelectorAll('#hmc-days input').forEach(function(cb) {
                    cb.checked = defaultDays.indexOf(cb.value) !== -1;
                });
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

        $id   = intval($_POST['id'] ?? 0);
        $name = sanitize_text_field($_POST['name'] ?? '');
        if (empty($name)) { wp_send_json_error('Name required'); return; }

        $data = [
            'clinic_name'  => $name,
            'address_line1'=> sanitize_textarea_field($_POST['address'] ?? ''),
            'phone'        => sanitize_text_field($_POST['clinic_phone'] ?? ''),
            'email'        => sanitize_email($_POST['clinic_email'] ?? ''),
            'postcode'     => sanitize_text_field($_POST['eircode'] ?? ''),
            'clinic_color' => sanitize_text_field($_POST['clinic_colour'] ?? 'var(--hm-teal)'),
            'is_active'    => ($_POST['is_active'] ?? '1') === '1',
        ];

        // Store extra UI-only fields (days_available, text_colour) inside opening_hours JSON
        $extra = [
            'days_available' => sanitize_text_field($_POST['days_available'] ?? ''),
            'text_colour'    => sanitize_text_field($_POST['text_colour'] ?? ''),
        ];
        if ($extra['days_available'] !== '' || $extra['text_colour'] !== '') {
            $data['opening_hours'] = wp_json_encode($extra);
        }

            if ($id) {
                $updated = HearMed_DB::update('clinics', $data, ['id' => $id]);
                if ($updated === false) {
                    wp_send_json_error('Failed to update clinic: ' . (HearMed_DB::last_error() ?: 'database error'));
                    return;
                }
        } else {
            $id = HearMed_DB::insert('clinics', $data);
            if (!$id) {
                wp_send_json_error('Failed to create clinic: ' . (HearMed_DB::last_error() ?: 'database error'));
                return;
            }
        }

        wp_send_json_success(['id' => $id]);
    }

    public function ajax_delete() {
        check_ajax_referer('hm_nonce', 'nonce');
        if (!current_user_can('edit_posts')) { wp_send_json_error('Permission denied'); return; }

        $id = intval($_POST['id'] ?? 0);
        if ($id) {
            // Soft delete — set is_active = false
            HearMed_DB::update('clinics', [
                'is_active'  => false,
                'updated_at' => current_time('mysql'),
            ], ['id' => $id]);
        }
        wp_send_json_success();
    }
}

new HearMed_Admin_Clinics();
