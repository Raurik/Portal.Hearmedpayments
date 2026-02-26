<?php
/**
 * HearMed Admin — Audiometers
 * Shortcode: [hearmed_audiometers]
 * PostgreSQL CRUD for audiometers reference table
 */
if (!defined('ABSPATH')) exit;

class HearMed_Admin_Audiometers {

    private $fields = ['audiometer_make','audiometer_model','serial_number','calibration_date','clinic_id','is_active'];

    public function __construct() {
        add_shortcode('hearmed_audiometers', [$this, 'render']);
        add_action('wp_ajax_hm_admin_save_audiometer', [$this, 'ajax_save']);
        add_action('wp_ajax_hm_admin_delete_audiometer', [$this, 'ajax_delete']);
    }

    private function get_audiometers() {
        $rows = HearMed_DB::get_results(
            "SELECT id, audiometer_name as name, audiometer_make, audiometer_model,
                    serial_number, calibration_date, clinic_id, is_active
             FROM hearmed_reference.audiometers
             WHERE is_active = true
             ORDER BY audiometer_name"
        ) ?: [];

        $items = [];
        foreach ($rows as $r) {
            $items[] = [
                'id' => (int) $r->id,
                'name' => $r->name,
                'audiometer_make' => $r->audiometer_make,
                'audiometer_model' => $r->audiometer_model,
                'serial_number' => $r->serial_number,
                'calibration_date' => $r->calibration_date,
                'clinic_id' => $r->clinic_id,
                'is_active' => (bool) $r->is_active,
            ];
        }

        return $items;
    }

    private function get_clinics() {
        $posts = HearMed_DB::get_results(
            "SELECT id, clinic_name as post_title, id as ID 
             FROM hearmed_reference.clinics 
             WHERE is_active = true 
             ORDER BY clinic_name"
        );
        return array_map(function($p) { return ['id' => $p->ID, 'name' => $p->post_title]; }, $posts);
    }

    public function render() {
        if (!is_user_logged_in()) return '<p>Please log in.</p>';
        $items = $this->get_audiometers();
        $clinics = $this->get_clinics();
        $clinic_map = [];
        foreach ($clinics as $c) $clinic_map[$c['id']] = $c['name'];

        ob_start(); ?>
        <div class="hm-admin">
            <div style="margin-bottom:16px;"><a href="<?php echo esc_url(home_url("/admin-console/")); ?>" class="hm-btn">&larr; Back</a></div>
            <div class="hm-admin-hd">
                <h2>Audiometers</h2>
                <button class="hm-btn hm-btn--primary" onclick="hmAud.open()">+ Add Audiometer</button>
            </div>

            <?php if (empty($items)): ?>
                <div class="hm-empty-state"><p>No audiometers tracked yet.</p></div>
            <?php else: ?>
            <table class="hm-table">
                <thead><tr><th>Name</th><th>Make</th><th>Model</th><th>Serial</th><th>Clinic</th><th>Calibration</th><th>Status</th><th style="width:100px"></th></tr></thead>
                <tbody>
                <?php foreach ($items as $a):
                    $cal_date = $a['calibration_date'] ?? '';
                    $overdue = $cal_date && strtotime($cal_date) < strtotime('-12 months');
                ?>
                <tr>
                    <td><strong><?php echo esc_html($a['name']); ?></strong></td>
                    <td><?php echo esc_html($a['audiometer_make'] ?? '—'); ?></td>
                    <td><?php echo esc_html($a['audiometer_model'] ?? '—'); ?></td>
                    <td><code><?php echo esc_html($a['serial_number'] ?? '—'); ?></code></td>
                    <td><?php echo esc_html($clinic_map[intval($a['clinic_id'] ?? 0)] ?? '—'); ?></td>
                    <td>
                        <?php if ($cal_date): ?>
                            <span class="<?php echo $overdue ? 'hm-badge hm-badge--red' : ''; ?>"><?php echo esc_html(date('d M Y', strtotime($cal_date))); ?></span>
                            <?php if ($overdue): ?> <small style="color:var(--hm-red)">⚠ Overdue</small><?php endif; ?>
                        <?php else: ?>—<?php endif; ?>
                    </td>
                    <td><?php echo $a['is_active'] ? '<span class="hm-badge hm-badge--green">Active</span>' : '<span class="hm-badge hm-badge--red">Inactive</span>'; ?></td>
                    <td class="hm-table-acts">
                        <button class="hm-btn hm-btn--sm" onclick='hmAud.open(<?php echo json_encode($a); ?>)'>Edit</button>
                        <button class="hm-btn hm-btn--sm hm-btn--danger" onclick="hmAud.del(<?php echo $a['id']; ?>,'<?php echo esc_js($a['name']); ?>')">Delete</button>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>

            <div class="hm-modal-bg" id="hm-aud-modal">
                <div class="hm-modal hm-modal--md">
                    <div class="hm-modal-hd"><h3 id="hm-aud-title">Add Audiometer</h3><button class="hm-close" onclick="hmAud.close()">&times;</button></div>
                    <div class="hm-modal-body">
                        <input type="hidden" id="hma-id">
                        <div class="hm-form-group"><label>Name *</label><input type="text" id="hma-name" placeholder="e.g. Main Audiometer"></div>
                        <div class="hm-form-row">
                            <div class="hm-form-group"><label>Make</label><input type="text" id="hma-make" placeholder="e.g. Interacoustics"></div>
                            <div class="hm-form-group"><label>Model</label><input type="text" id="hma-model" placeholder="e.g. AD629"></div>
                        </div>
                        <div class="hm-form-row">
                            <div class="hm-form-group"><label>Serial Number</label><input type="text" id="hma-serial"></div>
                            <div class="hm-form-group"><label>Calibration Date</label><input type="date" id="hma-cal"></div>
                        </div>
                        <div class="hm-form-row">
                            <div class="hm-form-group"><label>Clinic</label>
                                <select id="hma-clinic" data-entity="clinic" data-label="Clinic"><option value="">— None —</option>
                                <?php foreach ($clinics as $c): ?><option value="<?php echo $c['id']; ?>"><?php echo esc_html($c['name']); ?></option><?php endforeach; ?>
                                <option value="__add_new__">+ Add New…</option>
                                </select>
                            </div>
                            <div class="hm-form-group"><label class="hm-toggle-label"><input type="checkbox" id="hma-active" checked> Active</label></div>
                        </div>
                    </div>
                    <div class="hm-modal-ft"><button class="hm-btn" onclick="hmAud.close()">Cancel</button><button class="hm-btn hm-btn--primary" onclick="hmAud.save()" id="hma-save">Save</button></div>
                </div>
            </div>
        </div>

        <script>
        var hmAud={
            open:function(d){
                var e=d&&d.id;
                document.getElementById('hm-aud-title').textContent=e?'Edit Audiometer':'Add Audiometer';
                document.getElementById('hma-id').value=e?d.id:'';
                document.getElementById('hma-name').value=e?d.name:'';
                document.getElementById('hma-make').value=e?(d.audiometer_make||''):'';
                document.getElementById('hma-model').value=e?(d.audiometer_model||''):'';
                document.getElementById('hma-serial').value=e?(d.serial_number||''):'';
                document.getElementById('hma-cal').value=e?(d.calibration_date||''):'';
                document.getElementById('hma-clinic').value=e?(d.clinic_id||''):'';
                document.getElementById('hma-active').checked=e?d.is_active:true;
                document.getElementById('hm-aud-modal').classList.add('open');
            },
            close:function(){document.getElementById('hm-aud-modal').classList.remove('open');},
            save:function(){
                var n=document.getElementById('hma-name').value.trim();
                if(!n){alert('Name required.');return;}
                var b=document.getElementById('hma-save');b.textContent='Saving...';b.disabled=true;
                jQuery.post(HM.ajax_url,{action:'hm_admin_save_audiometer',nonce:HM.nonce,
                    id:document.getElementById('hma-id').value,name:n,
                    audiometer_make:document.getElementById('hma-make').value,
                    audiometer_model:document.getElementById('hma-model').value,
                    serial_number:document.getElementById('hma-serial').value,
                    calibration_date:document.getElementById('hma-cal').value,
                    clinic_id:document.getElementById('hma-clinic').value,
                    is_active:document.getElementById('hma-active').checked?1:0
                },function(r){if(r.success)location.reload();else{alert(r.data||'Error');b.textContent='Save';b.disabled=false;}});
            },
            del:function(id,n){if(!confirm('Delete "'+n+'"?'))return;
                jQuery.post(HM.ajax_url,{action:'hm_admin_delete_audiometer',nonce:HM.nonce,id:id},function(r){if(r.success)location.reload();else alert(r.data||'Error');});
            }
        };
        </script>
        <?php return ob_get_clean();
    }

    public function ajax_save() {
        check_ajax_referer('hm_nonce','nonce');
        if (!current_user_can('edit_posts')) { wp_send_json_error('Denied'); return; }
        
        $id = intval($_POST['id'] ?? 0);
        $name = sanitize_text_field($_POST['name'] ?? '');
        if (!$name) { wp_send_json_error('Name required'); return; }
        
        $data = [
            'audiometer_name' => $name,
            'audiometer_make' => sanitize_text_field($_POST['audiometer_make'] ?? ''),
            'audiometer_model' => sanitize_text_field($_POST['audiometer_model'] ?? ''),
            'serial_number' => sanitize_text_field($_POST['serial_number'] ?? ''),
            'calibration_date' => sanitize_text_field($_POST['calibration_date'] ?? null),
            'clinic_id' => intval($_POST['clinic_id'] ?? 0) ?: null,
            'is_active' => intval($_POST['is_active'] ?? 1),
            'updated_at' => current_time('mysql')
        ];
        
        if ($id) {
            $result = HearMed_DB::update(
                'hearmed_reference.audiometers',
                $data,
                ['id' => $id]
            );
        } else {
            $data['created_at'] = current_time('mysql');
            $id = HearMed_DB::insert(
                'hearmed_reference.audiometers',
                $data
            );
            $result = $id ? 1 : false;
        }
        
        if ($result === false) {
            wp_send_json_error(HearMed_DB::last_error() ?: 'Database error');
            return;
        }

        wp_send_json_success(['id' => $id]);
    }

    public function ajax_delete() {
        check_ajax_referer('hm_nonce','nonce');
        if (!current_user_can('edit_posts')) { wp_send_json_error('Denied'); return; }
        
        $id = intval($_POST['id'] ?? 0);
        if (!$id) { wp_send_json_error('Invalid ID'); return; }
        
        // Soft delete by setting is_active = false
        $result = HearMed_DB::update(
            'hearmed_reference.audiometers',
            ['is_active' => false, 'updated_at' => current_time('mysql')],
            ['id' => $id]
        );

        if ($result === false) {
            wp_send_json_error(HearMed_DB::last_error() ?: 'Database error');
        } else {
            wp_send_json_success();
        }
    }
}

new HearMed_Admin_Audiometers();
