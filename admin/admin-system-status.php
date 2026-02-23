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
 * HearMed Admin — SMS Templates
 * Shortcode: [hearmed_sms_templates]
 * CRUD for sms_templates CCT
 */
if (!defined('ABSPATH')) exit;

class HearMed_Admin_SMS_Templates {

    private function table() { return HearMed_DB::table('sms_templates'); }

    public function __construct() {
        add_shortcode('hearmed_sms_templates', [$this, 'render']);
        add_action('wp_ajax_hm_admin_save_sms_tpl', [$this, 'ajax_save']);
        add_action('wp_ajax_hm_admin_delete_sms_tpl', [$this, 'ajax_delete']);
    }

    private function get_templates() {
        // PostgreSQL only - no $wpdb needed
        $t = $this->table();
        if (HearMed_DB::get_var("SHOW TABLES LIKE '$t'") !== $t) return [];
        return HearMed_DB::get_results("SELECT * FROM `$t` ORDER BY id ASC", ARRAY_A) ?: [];
    }

    private function get_services() {
        $posts = HearMed_DB::get_results("SELECT id, service_name as post_title, id as ID FROM hearmed_reference.services WHERE is_active = true ORDER BY service_name"); // Converted from // TODO: USE PostgreSQL: HearMed_DB::get_results()
    get_posts() to PostgreSQL
        return array_map(function($p) { return ['id' => $p->ID, 'name' => $p->post_title]; }, $posts);
    }

    public function render() {
        if (!is_user_logged_in()) return '<p>Please log in.</p>';
        $templates = $this->get_templates();
        $services = $this->get_services();
        $svc_map = [];
        foreach ($services as $s) $svc_map[$s['id']] = $s['name'];
        $triggers = ['confirmation' => 'Confirmation', 'reminder_24h' => '24hr Reminder', 'reminder_48h' => '48hr Reminder', 'manual' => 'Manual/Custom'];

        ob_start(); ?>
        <div class="hm-admin">
            <div class="hm-admin-hd">
                <h2>SMS Templates</h2>
                <button class="hm-btn hm-btn-teal" onclick="hmSms.open()">+ Add Template</button>
            </div>

            <div class="hm-sms-vars">
                <strong>Available variables:</strong>
                <code>{patient_name}</code> <code>{appointment_date}</code> <code>{appointment_time}</code> <code>{clinic_name}</code> <code>{dispenser_name}</code> <code>{clinic_phone}</code>
            </div>

            <?php if (empty($templates)): ?>
                <div class="hm-empty-state"><p>No SMS templates yet.</p></div>
            <?php else: ?>
            <table class="hm-table">
                <thead><tr><th>Name</th><th>Trigger</th><th>Appointment Type</th><th>Template</th><th>Active</th><th style="width:100px"></th></tr></thead>
                <tbody>
                <?php foreach ($templates as $t): ?>
                <tr>
                    <td><strong><?php echo esc_html($t['template_name']); ?></strong></td>
                    <td><span class="hm-badge hm-badge-blue"><?php echo esc_html($triggers[$t['trigger_type']] ?? $t['trigger_type']); ?></span></td>
                    <td><?php echo intval($t['service_id']) ? esc_html($svc_map[intval($t['service_id'])] ?? '—') : '<em>All types</em>'; ?></td>
                    <td style="max-width:300px"><span style="font-size:12px;color:var(--hm-text-light)"><?php echo esc_html(mb_strimwidth($t['template_text'], 0, 80, '...')); ?></span></td>
                    <td><?php echo $t['is_active'] ? '<span class="hm-badge hm-badge-green">Yes</span>' : '<span class="hm-badge hm-badge-red">No</span>'; ?></td>
                    <td class="hm-table-acts">
                        <button class="hm-btn hm-btn-sm" onclick='hmSms.open(<?php echo json_encode($t); ?>)'>Edit</button>
                        <button class="hm-btn hm-btn-sm hm-btn-red" onclick="hmSms.del(<?php echo $t['id']; ?>,'<?php echo esc_js($t['template_name']); ?>')">Delete</button>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>

            <div class="hm-modal-bg" id="hm-sms-modal">
                <div class="hm-modal" style="width:560px">
                    <div class="hm-modal-hd"><h3 id="hm-sms-title">Add Template</h3><button class="hm-modal-x" onclick="hmSms.close()">&times;</button></div>
                    <div class="hm-modal-body">
                        <input type="hidden" id="hms-id">
                        <div class="hm-form-group"><label>Template Name *</label><input type="text" id="hms-name" placeholder="e.g. Appointment Confirmation"></div>
                        <div class="hm-form-row">
                            <div class="hm-form-group"><label>Trigger Type</label>
                                <select id="hms-trigger">
                                    <?php foreach ($triggers as $k => $v): ?><option value="<?php echo $k; ?>"><?php echo $v; ?></option><?php endforeach; ?>
                                </select>
                            </div>
                            <div class="hm-form-group"><label>Appointment Type</label>
                                <select id="hms-service"><option value="0">All Types</option>
                                <?php foreach ($services as $s): ?><option value="<?php echo $s['id']; ?>"><?php echo esc_html($s['name']); ?></option><?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="hm-form-group"><label>Message Text *</label>
                            <textarea id="hms-text" rows="4" placeholder="Hi {patient_name}, this is a reminder..."></textarea>
                            <small style="color:var(--hm-text-light)">Max 160 chars per SMS segment. Current: <span id="hms-chars">0</span></small>
                        </div>
                        <div class="hm-form-group"><label class="hm-toggle-label"><input type="checkbox" id="hms-active" checked> Active</label></div>
                    </div>
                    <div class="hm-modal-ft"><button class="hm-btn" onclick="hmSms.close()">Cancel</button><button class="hm-btn hm-btn-teal" onclick="hmSms.save()" id="hms-save">Save</button></div>
                </div>
            </div>
        </div>


        <script>
        document.getElementById('hms-text').addEventListener('input',function(){document.getElementById('hms-chars').textContent=this.value.length;});
        var hmSms={
            open:function(d){
                var e=d&&d.id;
                document.getElementById('hm-sms-title').textContent=e?'Edit Template':'Add Template';
                document.getElementById('hms-id').value=e?d.id:'';
                document.getElementById('hms-name').value=e?(d.template_name||''):'';
                document.getElementById('hms-trigger').value=e?(d.trigger_type||'manual'):'confirmation';
                document.getElementById('hms-service').value=e?(d.service_id||'0'):'0';
                document.getElementById('hms-text').value=e?(d.template_text||''):'';
                document.getElementById('hms-active').checked=e?!!parseInt(d.is_active):true;
                document.getElementById('hms-chars').textContent=(e?d.template_text||'':'').length;
                document.getElementById('hm-sms-modal').classList.add('open');
            },
            close:function(){document.getElementById('hm-sms-modal').classList.remove('open');},
            save:function(){
                var n=document.getElementById('hms-name').value.trim();
                var txt=document.getElementById('hms-text').value.trim();
                if(!n||!txt){alert('Name and message text are required.');return;}
                var b=document.getElementById('hms-save');b.textContent='Saving...';b.disabled=true;
                jQuery.post(HM.ajax_url,{action:'hm_admin_save_sms_tpl',nonce:HM.nonce,
                    id:document.getElementById('hms-id').value,template_name:n,
                    trigger_type:document.getElementById('hms-trigger').value,
                    service_id:document.getElementById('hms-service').value,
                    template_text:txt,is_active:document.getElementById('hms-active').checked?1:0
                },function(r){if(r.success)location.reload();else{alert(r.data||'Error');b.textContent='Save';b.disabled=false;}});
            },
            del:function(id,n){if(!confirm('Delete "'+n+'"?'))return;
                jQuery.post(HM.ajax_url,{action:'hm_admin_delete_sms_tpl',nonce:HM.nonce,id:id},function(r){if(r.success)location.reload();else alert(r.data||'Error');});
            }
        };
        </script>
        <?php return ob_get_clean();
    }

    public function ajax_save() {
        check_ajax_referer('hm_nonce','nonce');
        if (!current_user_can('edit_posts')) { wp_send_json_error('Denied'); return; }
        // PostgreSQL only - no $wpdb needed
        $t = $this->table();
        $id = intval($_POST['id'] ?? 0);
        $data = [
            'template_name' => sanitize_text_field($_POST['template_name'] ?? ''),
            'trigger_type' => sanitize_text_field($_POST['trigger_type'] ?? 'manual'),
            'service_id' => intval($_POST['service_id'] ?? 0),
            'template_text' => sanitize_textarea_field($_POST['template_text'] ?? ''),
            'is_active' => intval($_POST['is_active'] ?? 1),
            'updated_at' => current_time('mysql'),
            'cct_author_id' => get_current_user_id(),
        ];
        if ($id) { HearMed_DB::update($t, $data, ['id' => $id]); }
        else { $data['cct_status'] = 'publish'; $data['created_at'] = current_time('mysql'); HearMed_DB::insert($t, $data); $id = $last_insert_id /* TODO: Get this from HearMed_DB::insert() return value */; }
        wp_send_json_success(['id' => $id]);
    }

    public function ajax_delete() {
        check_ajax_referer('hm_nonce','nonce');
        if (!current_user_can('edit_posts')) { wp_send_json_error('Denied'); return; }
        // PostgreSQL only - no $wpdb needed
        HearMed_DB::delete($this->table(), ['id' => intval($_POST['id'] ?? 0)]);
        wp_send_json_success();
    }
}

new HearMed_Admin_SMS_Templates();
