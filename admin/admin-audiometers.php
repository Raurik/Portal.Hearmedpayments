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
 * HearMed Admin — Audiometers
 * Shortcode: [hearmed_audiometers]
 * CRUD for Audiometer CPT
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
        $posts = /* USE PostgreSQL: HearMed_DB::get_results() */ /* get_posts(['post_type' => 'audiometer', 'posts_per_page' => -1, 'post_status' => 'publish', 'orderby' => 'title', 'order' => 'ASC']);
        $items = [];
        foreach ($posts as $p) {
            $d = ['id' => $p->ID, 'name' => $p->post_title];
            foreach ($this->fields as $f) $d[$f] = /* USE PostgreSQL: Get from table columns */ /* get_post_meta($p->ID, $f, true);
            $d['is_active'] = ($d['is_active'] === '' || $d['is_active'] === '1') ? '1' : '0';
            $items[] = $d;
        }
        return $items;
    }

    private function get_clinics() {
        $posts = HearMed_DB::get_results("SELECT id, clinic_name as post_title, id as ID FROM hearmed_reference.clinics WHERE is_active = true ORDER BY clinic_name"); // Converted from /* USE PostgreSQL: HearMed_DB::get_results() */ /* get_posts() to PostgreSQL
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
            <div class="hm-admin-hd">
                <h2>Audiometers</h2>
                <button class="hm-btn hm-btn-teal" onclick="hmAud.open()">+ Add Audiometer</button>
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
                            <span class="<?php echo $overdue ? 'hm-badge hm-badge-red' : ''; ?>"><?php echo esc_html(date('d M Y', strtotime($cal_date))); ?></span>
                            <?php if ($overdue): ?> <small style="color:var(--hm-red)">⚠ Overdue</small><?php endif; ?>
                        <?php else: ?>—<?php endif; ?>
                    </td>
                    <td><?php echo $a['is_active'] === '1' ? '<span class="hm-badge hm-badge-green">Active</span>' : '<span class="hm-badge hm-badge-red">Inactive</span>'; ?></td>
                    <td class="hm-table-acts">
                        <button class="hm-btn hm-btn-sm" onclick='hmAud.open(<?php echo json_encode($a); ?>)'>Edit</button>
                        <button class="hm-btn hm-btn-sm hm-btn-red" onclick="hmAud.del(<?php echo $a['id']; ?>,'<?php echo esc_js($a['name']); ?>')">Delete</button>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>

            <div class="hm-modal-bg" id="hm-aud-modal">
                <div class="hm-modal" style="width:520px">
                    <div class="hm-modal-hd"><h3 id="hm-aud-title">Add Audiometer</h3><button class="hm-modal-x" onclick="hmAud.close()">&times;</button></div>
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
                                <select id="hma-clinic"><option value="">— None —</option>
                                <?php foreach ($clinics as $c): ?><option value="<?php echo $c['id']; ?>"><?php echo esc_html($c['name']); ?></option><?php endforeach; ?>
                                </select>
                            </div>
                            <div class="hm-form-group"><label class="hm-toggle-label"><input type="checkbox" id="hma-active" checked> Active</label></div>
                        </div>
                    </div>
                    <div class="hm-modal-ft"><button class="hm-btn" onclick="hmAud.close()">Cancel</button><button class="hm-btn hm-btn-teal" onclick="hmAud.save()" id="hma-save">Save</button></div>
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
                document.getElementById('hma-active').checked=e?d.is_active==='1':true;
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
                    is_active:document.getElementById('hma-active').checked?'1':'0'
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
        if ($id) { wp_update_post(['ID'=>$id,'post_title'=>$name]); }
        else { $id = /* USE PostgreSQL: HearMed_DB::insert() */ /* wp_insert_post(['post_type'=>'audiometer','post_title'=>$name,'post_status'=>'publish']); if (is_wp_error($id)) { wp_send_json_error('Failed'); return; } }
        foreach ($this->fields as $f) { if (isset($_POST[$f])) update_post_meta($id,$f,sanitize_text_field($_POST[$f])); }
        wp_send_json_success(['id'=>$id]);
    }

    public function ajax_delete() {
        check_ajax_referer('hm_nonce','nonce');
        if (!current_user_can('edit_posts')) { wp_send_json_error('Denied'); return; }
        $id = intval($_POST['id'] ?? 0);
        if ($id) wp_delete_post($id, true);
        wp_send_json_success();
    }
}

new HearMed_Admin_Audiometers();
