/**
 * HearMed Portal — Patient Module JS v4.0.0
 * Blueprint-02-Patients v2.0 — Items 1 & 2
 */
(function($){
'use strict';

function getHM(){if(typeof HMP!=='undefined')return HMP;if(typeof HM!=='undefined')return HM;return{ajax:window.location.origin+'/wp-admin/admin-ajax.php',nonce:''};}
var _hm=getHM(),PG='/patients/';
function euro(v){return '€'+parseFloat(v||0).toFixed(2);}
function esc(s){if(!s)return '';var d=document.createElement('div');d.textContent=s;return d.innerHTML;}
function fmtDate(d){if(!d)return '—';var p=d.split('-');return p[2]+'/'+p[1]+'/'+p[0];}
function fmtDateTime(d){if(!d)return '—';var dt=new Date(d);return dt.toLocaleDateString('en-IE')+' '+dt.toLocaleTimeString('en-IE',{hour:'2-digit',minute:'2-digit'});}
function initials(n){if(!n)return '?';var p=n.trim().split(' ');return(p[0][0]+(p.length>1?p[p.length-1][0]:'')).toUpperCase();}
function toast(msg,type){var t=$('<div class="hm-toast hm-toast-'+(type||'success')+'">'+esc(msg)+'</div>');$('body').append(t);setTimeout(function(){t.fadeOut(300,function(){t.remove();});},3000);}
function closeModal(){$('#hm-modal-overlay').remove();}
var HM_ICONS={
    calendar:'<span class="hm-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><rect x="3.5" y="4.5" width="17" height="16" rx="2"></rect><path d="M8 2.8v3.4M16 2.8v3.4M3.5 9.2h17"></path></svg></span>',
    note:'<span class="hm-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M7 3.5h10l3 3v14H7z"></path><path d="M17 3.5v3h3M10 11h7M10 15h7"></path></svg></span>',
    export:'<span class="hm-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3.5v11"></path><path d="m7.8 10.5 4.2 4.2 4.2-4.2"></path><path d="M4 19.5h16"></path></svg></span>',
    phone:'<span class="hm-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M5.5 4.5h4l1.2 4.1-2.1 1.7a14 14 0 0 0 5.1 5.1l1.7-2.1 4.1 1.2v4a1.8 1.8 0 0 1-1.8 1.8A14.2 14.2 0 0 1 3.7 6.3 1.8 1.8 0 0 1 5.5 4.5z"></path></svg></span>',
    email:'<span class="hm-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><rect x="3.5" y="6" width="17" height="12" rx="2"></rect><path d="m4.5 7.2 7.5 5.6 7.5-5.6"></path></svg></span>',
    clinic:'<span class="hm-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M5 20V7.5h6V20"></path><path d="M11 20V4h8v16"></path><path d="M3.5 20.5h17"></path></svg></span>',
    person:'<span class="hm-icon hm-icon-teal" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="3.2"></circle><path d="M5.5 20c.6-3.1 3-5.1 6.5-5.1s5.9 2 6.5 5.1"></path></svg></span>',
    check:'<span class="hm-icon hm-icon-success" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m5.8 12.4 4.2 4.2 8.2-8.2"></path></svg></span>',
    x:'<span class="hm-icon hm-icon-danger" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m6.5 6.5 11 11M17.5 6.5l-11 11"></path></svg></span>',
    warning:'<span class="hm-icon hm-icon-danger" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3.5 2.8 20.5h18.4L12 3.5z"></path><path d="M12 9v5.2M12 17.5h.01"></path></svg></span>',
    search:'<span class="hm-icon hm-icon-muted" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="6.5"></circle><path d="m16 16 4.2 4.2"></path></svg></span>',
    hearing:'<span class="hm-icon hm-icon-muted" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M15.5 9.2a3.6 3.6 0 0 0-7.2 0"></path><path d="M8.3 9.2c0 2 1.3 2.8 2.3 3.5.9.6 1.4 1 1.4 1.9v1.2"></path><path d="M7.2 5.6A6.7 6.7 0 0 0 5 10.6"></path><path d="M6.8 18a4.2 4.2 0 0 0 5.2 1.8"></path></svg></span>',
    repair:'<span class="hm-icon hm-icon-muted" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="m14.5 6.2 3.3 3.3-7.8 7.8H6.7v-3.3l7.8-7.8z"></path><path d="M13.2 7.5 16.5 10.8"></path></svg></span>',
    returns:'<span class="hm-icon hm-icon-muted" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M9 7H4v5"></path><path d="M4.3 12A7.5 7.5 0 1 0 7 6.7"></path></svg></span>',
    form:'<span class="hm-icon hm-icon-muted" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="4" width="14" height="16" rx="2"></rect><path d="M9 8h6M9 12h6M9 16h4"></path></svg></span>',
    audit:'<span class="hm-icon hm-icon-muted" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M4.5 18.5h15"></path><path d="M7 16V9M12 16V6M17 16v-4"></path></svg></span>',
    order:'<span class="hm-icon hm-icon-muted" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7.5h16v10H4z"></path><path d="M8 7.5V6a4 4 0 0 1 8 0v1.5"></path></svg></span>',
    invoice:'<span class="hm-icon hm-icon-muted" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M7 3.5h10l3 3v14l-3-1.5-2 1.5-2-1.5-2 1.5-2-1.5-2 1.5z"></path><path d="M9.5 10h5M9.5 14h5"></path></svg></span>',
    edit:'<span class="hm-icon hm-icon-teal" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="m14.8 5.7 3.5 3.5"></path><path d="M5 19h3.6l9.8-9.8a1.9 1.9 0 0 0 0-2.7l-.9-.9a1.9 1.9 0 0 0-2.7 0L5 15.4V19z"></path></svg></span>',
    mic:'<span class="hm-icon hm-icon-teal" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="3.8" width="6" height="11" rx="3"></rect><path d="M6 11.8a6 6 0 1 0 12 0M12 17.8v2.7M9.5 20.5h5"></path></svg></span>',
    status:'<span class="hm-icon hm-icon-teal" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="8.2"></circle><path d="m8.7 12.1 2.1 2.1 4.5-4.5"></path></svg></span>'
};

var _hmFieldAutoSeq=0,_hmFieldObserver=null;
function sanitizeFieldKey(v){return String(v||'field').toLowerCase().replace(/[^a-z0-9_-]+/g,'-').replace(/^-+|-+$/g,'')||'field';}
function ensureFieldIdentity(scope){
    if(!scope||!scope.querySelectorAll)return;
    var fields=scope.querySelectorAll('input:not([type="hidden"]), select, textarea');
    fields.forEach(function(el){
        var name=el.getAttribute('name');
        var id=el.getAttribute('id');
        if(!name){
            var nameBase=id||el.getAttribute('data-pref')||el.getAttribute('autocomplete')||el.getAttribute('type')||el.tagName;
            name=sanitizeFieldKey(nameBase);
            if(!id)name+='-'+(++_hmFieldAutoSeq);
            el.setAttribute('name',name);
        }
        if(!id){
            var idBase=sanitizeFieldKey(el.getAttribute('name')||el.getAttribute('data-pref')||'field');
            var candidate=idBase;
            while(document.getElementById(candidate))candidate=idBase+'-'+(++_hmFieldAutoSeq);
            el.setAttribute('id',candidate);
        }
    });
}
function installFieldIdentityPatch(){
    if(_hmFieldObserver||typeof MutationObserver==='undefined'){
        ensureFieldIdentity(document.body);
        return;
    }
    ensureFieldIdentity(document.body);
    _hmFieldObserver=new MutationObserver(function(){ensureFieldIdentity(document.body);});
    _hmFieldObserver.observe(document.body,{childList:true,subtree:true});
}

$(function(){
    console.log('HM-patients v4.0.0');
    installFieldIdentityPatch();
    var hasList=$('#hm-patient-list').length,hasProfile=$('#hm-patient-profile').length;
    if(hasList)initList();
    if(hasProfile)initProfile();
    initGlobalSearch();
    if(!hasList && !hasProfile){
        console.warn('[HearMed] patients.js loaded but no mount anchor (#hm-patient-list or #hm-patient-profile) found in DOM.');
    }
});

/* ════ GLOBAL SEARCH ════ */
function initGlobalSearch(){
    var $wrap=$('.hm-patient-search-bar');
    if(!$wrap.length)return;
    if(!$('#hm-gsr-dropdown').length)$('body').append('<div id="hm-gsr-dropdown" style="display:none;position:absolute;z-index:999999;background:#fff;border:1px solid #e2e8f0;border-radius:0 0 8px 8px;box-shadow:0 8px 24px rgba(0,0,0,.15);max-height:320px;overflow-y:auto;"></div>');
    var $dd=$('#hm-gsr-dropdown');
    function posDD(){var $sb=$('.hm-patient-search-bar').first();if(!$sb.length)return;var o=$sb.offset();$dd.css({top:o.top+$sb.outerHeight(),left:o.left,width:$sb.outerWidth()});}
    function run(){
        var q=$.trim($('.hm-patient-search-bar input').first().val());
        if(q.length<2){$dd.hide().empty();return;}
        $.post(_hm.ajax,{action:'hm_search_patients',nonce:_hm.nonce,q:q},function(r){
            var h='';
            if(!r.success||!r.data||!r.data.length)h='<div style="padding:12px 16px;text-align:center;color:#94a3b8;font-size:13px;">No patients found</div>';
            else for(var i=0;i<r.data.length;i++){var p=r.data[i];h+='<a href="'+PG+'?id='+p.id+'" style="display:block;padding:10px 16px;text-decoration:none;color:#151B33;border-bottom:1px solid #f1f5f9;" onmouseover="this.style.background=\'#f0fdfa\'" onmouseout="this.style.background=\'#fff\'"><span style="display:block;font-weight:500;font-size:14px;">'+esc(p.name)+'</span><span style="display:block;font-size:12px;color:#94a3b8;">'+esc(p.patient_number||'')+(p.phone?' · '+esc(p.phone):'')+'</span></a>';}
            h+='<a href="#" id="hm-gsr-add" style="display:block;padding:12px 16px;text-align:center;color:#0BB4C4;font-size:13px;font-weight:500;text-decoration:none;border-top:1px solid #edf2f7;" onmouseover="this.style.background=\'#f0fdfa\'" onmouseout="this.style.background=\'\'">+ Add new patient</a>';
            posDD();$dd.html(h).show();
        });
    }
    var tmr;
    $(document).on('input','.hm-patient-search-bar input',function(){clearTimeout(tmr);tmr=setTimeout(run,300);});
    $(document).on('click','.hm-patient-search-bar button',function(e){e.preventDefault();run();});
    $(document).on('click',function(e){if(!$(e.target).closest('.hm-patient-search-bar,#hm-gsr-dropdown').length)$dd.hide();});
    $(document).on('click','#hm-gsr-add',function(e){e.preventDefault();$dd.hide();showCreateModal();});
}

/* ════ PATIENT LIST ════ */
function initList(){
    var $el=$('#hm-patient-list'),state={page:1,search:'',clinic:'',dispenser:'',referral:'',active:'all'},clinics=[],dispensers=[];
    $.when($.post(_hm.ajax,{action:'hm_get_clinics',nonce:_hm.nonce}),$.post(_hm.ajax,{action:'hm_get_dispensers',nonce:_hm.nonce})).then(function(cr,dr){
        if(cr[0].success)clinics=cr[0].data||[];
        if(dr[0].success)dispensers=dr[0].data||[];
        renderShell();load();
    },function(){renderShell();load();});

    function renderShell(){
        var co='<option value="">All clinics</option>';clinics.forEach(function(c){co+='<option value="'+c.id+'">'+esc(c.name)+'</option>';});
        var dop='<option value="">All dispensers</option>';dispensers.forEach(function(d){dop+='<option value="'+d.id+'">'+esc(d.name)+'</option>';});
        $el.html(
            '<div class="hm-patient-list-shell">'+
                '<div class="hm-patients-header"><h2>Patients</h2><div class="hm-patients-actions"><button class="hm-btn hm-btn-teal" id="hm-create-patient">+ Add Patient</button></div></div>'+
                '<div class="hm-pt-search-wrap">'+HM_ICONS.search+'<input type="text" class="hm-search-input" id="hm-pt-search" placeholder="Search patients…"></div>'+
                '<div class="hm-pt-filters">'+
                    '<div class="hm-pt-filter"><div class="hm-pt-filter-label">'+HM_ICONS.clinic+'<span>Clinic</span></div><select class="hm-dd" id="hm-filter-clinic">'+co+'</select></div>'+
                    '<div class="hm-pt-filter"><div class="hm-pt-filter-label">'+HM_ICONS.person+'<span>Dispenser</span></div><select class="hm-dd" id="hm-filter-disp">'+dop+'</select></div>'+
                    '<div class="hm-pt-filter"><div class="hm-pt-filter-label">'+HM_ICONS.status+'<span>Status</span></div><select class="hm-dd" id="hm-filter-active"><option value="all">Active &amp; Inactive</option><option value="1">Active only</option><option value="0">Inactive only</option></select></div>'+
                    '<div class="hm-pt-filter"><div class="hm-pt-filter-label">'+HM_ICONS.person+'<span>Referral</span></div><input type="text" class="hm-inp" id="hm-filter-ref" placeholder="All sources"></div>'+
                '</div>'+
                '<button class="hm-btn hm-btn-outline hm-btn-sm" id="hm-filter-clear">Reset</button>'+
                '<div id="hm-pt-table-wrap" style="overflow-y:auto;flex:1 1 auto;min-height:0;"></div>'+
            '</div>'
        );
        $el.on('click','#hm-create-patient',showCreateModal);
        var st;
        $el.on('input','#hm-pt-search',function(){clearTimeout(st);var v=$(this).val();st=setTimeout(function(){state.search=v;state.page=1;load();},300);});
        $el.on('change','#hm-filter-clinic',function(){state.clinic=$(this).val();state.page=1;load();});
        $el.on('change','#hm-filter-disp',function(){state.dispenser=$(this).val();state.page=1;load();});
        $el.on('change','#hm-filter-active',function(){state.active=$(this).val();state.page=1;load();});
        $el.on('input','#hm-filter-ref',function(){clearTimeout(st);var v=$(this).val();st=setTimeout(function(){state.referral=v;state.page=1;load();},400);});
        $el.on('click','#hm-filter-clear',function(){state={page:1,search:'',clinic:'',dispenser:'',referral:'',active:'all'};$('#hm-pt-search,#hm-filter-ref').val('');$('#hm-filter-clinic,#hm-filter-disp').val('');$('#hm-filter-active').val('all');load();});
        $el.on('click','.hm-pt-view-btn,.hm-pt-name-link',function(e){e.preventDefault();window.location=PG+'?id='+$(this).data('id');});
        $el.on('click','.hm-pt-row',function(e){
            if($(e.target).closest('a,button,input,select,textarea,label').length)return;
            var id=$(this).data('id');
            if(id)window.location=PG+'?id='+id;
        });
        $el.on('click','.hm-page-btn',function(){state.page=$(this).data('page');load();});
    }

    function load(){
        var $w=$('#hm-pt-table-wrap');$w.html('<div class="hm-loading">Loading…</div>');
        $.post(_hm.ajax,{action:'hm_get_patients',nonce:_hm.nonce,search:state.search,clinic:state.clinic,dispenser:state.dispenser,referral:state.referral,active:state.active,page:state.page},function(r){
            if(!r.success){$w.html('<div class="hm-empty"><div class="hm-empty-icon">'+HM_ICONS.warning+'</div><div class="hm-empty-text">Error</div></div>');return;}
            var d=r.data;
            if(!d.patients.length){$w.html('<div class="hm-empty"><div class="hm-empty-icon">'+HM_ICONS.search+'</div><div class="hm-empty-text">No patients found</div></div>');return;}
            var h='<table class="hm-table hm-pt-table"><thead><tr><th>C-Number</th><th>Full name</th><th>DOB</th><th>Phone</th><th>Last appointment</th><th>Dispenser</th><th>Clinic</th><th>Status</th><th></th></tr></thead><tbody>';
            d.patients.forEach(function(p){
                var la=p.last_appt_date?fmtDate(p.last_appt_date)+(p.last_appt_time?' '+p.last_appt_time.substr(0,5):''):'—';
                var phoneCell=p.phone?'<div class="hm-pt-phone-cell">'+HM_ICONS.phone+'<span>'+esc(p.phone)+'</span></div>':'—';
                var apptCell=la!=='—'?'<div class="hm-pt-appt-cell">'+HM_ICONS.calendar+'<span>'+la+'</span></div>':'—';
                var sb=p.is_active?'<span class="hm-badge hm-badge-green">Active</span>':'<span class="hm-badge hm-badge-gray">Inactive</span>';
                h+='<tr class="hm-pt-row" data-id="'+p.id+'"><td class="hm-pt-hnum">'+esc(p.patient_number)+'</td>'+
                    '<td><div class="hm-pt-name-cell">'+HM_ICONS.person+'<a href="#" class="hm-pt-name-link" data-id="'+p.id+'">'+esc(p.name)+'</a></div></td>'+
                    '<td>'+fmtDate(p.dob)+'</td><td>'+phoneCell+'</td><td class="hm-pt-lastappt">'+apptCell+'</td>'+
                    '<td class="hm-pt-location">'+esc(p.dispenser_name)+'</td><td class="hm-pt-location">'+esc(p.clinic_name)+'</td>'+
                    '<td>'+sb+'</td><td><button class="hm-btn hm-btn-outline hm-btn-sm hm-pt-view-btn" data-id="'+p.id+'">View</button></td></tr>';
            });
            h+='</tbody></table>';
            if(d.pages>1){h+='<div class="hm-pagination" style="display:flex;gap:6px;margin-top:16px;align-items:center;">';for(var j=1;j<=d.pages;j++)h+='<button class="hm-btn hm-btn-sm hm-page-btn '+(j===d.page?'hm-btn-teal':'hm-btn-outline')+'" data-page="'+j+'">'+j+'</button>';h+='<span style="color:#94a3b8;font-size:13px;margin-left:8px;">'+d.total+' patients</span></div>';}
            $w.html(h);
        });
    }
}

/* ════ CREATE PATIENT MODAL ════ */
function showCreateModal(){
    if($('#hm-modal-overlay').length)return;
    $('body').append(
        '<div id="hm-modal-overlay" class="hm-modal-bg">'+
        '<div class="hm-modal" style="max-width:620px;width:100%;">'+
            '<div class="hm-modal-hd"><span>New Patient</span><button class="hm-modal-x">&times;</button></div>'+
            '<div class="hm-modal-body" style="max-height:75vh;overflow-y:auto;">'+
                '<div class="hm-form-row">'+
                    '<div class="hm-form-group" style="flex:0 0 100px;"><label class="hm-label">Title</label><select class="hm-dd" id="cp-title"><option value="">—</option><option>Mr</option><option>Mrs</option><option>Ms</option><option>Miss</option><option>Dr</option><option>Other</option></select></div>'+
                    '<div class="hm-form-group"><label class="hm-label">First name *</label><input type="text" class="hm-inp" id="cp-fn"></div>'+
                    '<div class="hm-form-group"><label class="hm-label">Last name *</label><input type="text" class="hm-inp" id="cp-ln"></div>'+
                '</div>'+
                '<div class="hm-form-row">'+
                    '<div class="hm-form-group"><label class="hm-label">Date of birth</label><input type="date" class="hm-inp" id="cp-dob"></div>'+
                    '<div class="hm-form-group"><label class="hm-label">Phone</label><input type="text" class="hm-inp" id="cp-phone"></div>'+
                    '<div class="hm-form-group"><label class="hm-label">Mobile</label><input type="text" class="hm-inp" id="cp-mobile"></div>'+
                '</div>'+
                '<div class="hm-form-group"><label class="hm-label">Email</label><input type="email" class="hm-inp" id="cp-email"></div>'+
                '<div class="hm-form-group"><label class="hm-label">Address</label><textarea class="hm-textarea" id="cp-address" rows="2"></textarea></div>'+
                '<div class="hm-form-row">'+
                    '<div class="hm-form-group"><label class="hm-label">Eircode</label><input type="text" class="hm-inp" id="cp-eircode"></div>'+
                    '<div class="hm-form-group"><label class="hm-label">Referral source</label><input type="text" class="hm-inp" id="cp-ref"></div>'+
                '</div>'+
                '<div class="hm-form-row">'+
                    '<div class="hm-form-group"><label class="hm-label">Dispenser</label><select class="hm-dd" id="cp-dispenser"><option value="">— Select —</option></select></div>'+
                    '<div class="hm-form-group"><label class="hm-label">Clinic</label><select class="hm-dd" id="cp-clinic"><option value="">— Select —</option></select></div>'+
                '</div>'+
                '<div style="display:flex;gap:20px;flex-wrap:wrap;margin:8px 0;">'+
                    '<label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer;"><input type="checkbox" id="cp-prsi"> PRSI eligible</label>'+
                    '<label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer;"><input type="checkbox" id="cp-memail"> Email marketing</label>'+
                    '<label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer;"><input type="checkbox" id="cp-msms"> SMS marketing</label>'+
                    '<label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer;"><input type="checkbox" id="cp-mphone"> Phone marketing</label>'+
                '</div>'+
                '<div style="margin-top:16px;padding:16px;background:#f0fdfa;border:1px solid #0BB4C4;border-radius:8px;">'+
                    '<p style="margin:0 0 10px;font-size:13px;font-weight:500;color:#151B33;">GDPR Consent — Required</p>'+
                    '<label style="display:flex;align-items:flex-start;gap:8px;font-size:13px;cursor:pointer;">'+
                        '<input type="checkbox" id="cp-gdpr" style="margin-top:2px;flex-shrink:0;">'+
                        '<span>I confirm this patient has provided informed consent for HearMed to store and process their personal and health data in accordance with our Privacy Policy.</span>'+
                    '</label>'+
                '</div>'+
            '</div>'+
            '<div class="hm-modal-ft"><button class="hm-btn hm-btn-outline" id="cp-cancel">Cancel</button><button class="hm-btn hm-btn-teal" id="cp-save" disabled>Create Patient</button></div>'+
        '</div></div>'
    );
    $('#cp-gdpr').on('change',function(){$('#cp-save').prop('disabled',!this.checked);});
    $.post(_hm.ajax,{action:'hm_get_dispensers',nonce:_hm.nonce},function(r){if(r.success)r.data.forEach(function(d){$('#cp-dispenser').append('<option value="'+d.id+'">'+esc(d.name)+'</option>');});});
    $.post(_hm.ajax,{action:'hm_get_clinics',nonce:_hm.nonce},function(r){if(r.success)r.data.forEach(function(c){$('#cp-clinic').append('<option value="'+c.id+'">'+esc(c.name)+'</option>');});});
    $('#cp-cancel,.hm-modal-x').on('click',closeModal);
    $('#hm-modal-overlay').on('click',function(e){if(e.target===this)closeModal();});
    $('#cp-save').on('click',function(){
        var fn=$.trim($('#cp-fn').val()),ln=$.trim($('#cp-ln').val());
        if(!fn||!ln){toast('First and last name required','error');return;}
        var $btn=$(this).prop('disabled',true).text('Creating…');
        $.post(_hm.ajax,{action:'hm_create_patient',nonce:_hm.nonce,patient_title:$('#cp-title').val(),first_name:fn,last_name:ln,dob:$('#cp-dob').val(),patient_phone:$('#cp-phone').val(),patient_mobile:$('#cp-mobile').val(),patient_email:$('#cp-email').val(),patient_address:$('#cp-address').val(),patient_eircode:$('#cp-eircode').val(),referral_source:$('#cp-ref').val(),assigned_dispenser_id:$('#cp-dispenser').val(),assigned_clinic_id:$('#cp-clinic').val(),prsi_eligible:$('#cp-prsi').is(':checked')?'1':'0',marketing_email:$('#cp-memail').is(':checked')?'1':'0',marketing_sms:$('#cp-msms').is(':checked')?'1':'0',marketing_phone:$('#cp-mphone').is(':checked')?'1':'0',gdpr_consent:'1'},function(r){
            if(r.success)window.location=PG+'?id='+r.data.id;
            else{toast(r.data||'Error','error');$btn.prop('disabled',false).text('Create Patient');}
        });
    });
}

/* ════ PATIENT PROFILE ════ */
function initProfile(){
    var $el=$('#hm-patient-profile'),pid=$el.data('patient-id'),patient=null,activeTab='overview';
    var TABS=[
        {id:'overview',label:'Overview'},{id:'details',label:'Details'},{id:'appointments',label:'Appointments'},
        {id:'notes',label:'Notes'},{id:'documents',label:'Documents'},{id:'orders',label:'Orders'},
        {id:'invoices',label:'Invoices'},{id:'hearing-aids',label:'Hearing Aids'},{id:'repairs',label:'Repairs'},
        {id:'returns',label:'Returns'},{id:'forms',label:'Forms'},{id:'case-history',label:'Case History'},
        {id:'activity',label:'Activity'}
    ];

    $.post(_hm.ajax,{action:'hm_get_patient',nonce:_hm.nonce,patient_id:pid},function(r){
        if(!r.success){$el.html('<div class="hm-empty"><div class="hm-empty-icon">'+HM_ICONS.warning+'</div><div class="hm-empty-text">Patient not found</div></div>');return;}
        patient=r.data;renderProfile();loadTab(activeTab);
    });

    function renderProfile(){
        var p=patient,ini=initials(p.name),th='';
        TABS.forEach(function(tab){
            if(tab.id==='activity'&&!p.show_audit)return;
            th+='<button class="hm-tab-btn'+(tab.id===activeTab?' active':'')+'" data-tab="'+tab.id+'">'+tab.label+'</button>';
        });
        var rh='';
        if(p.annual_review_date){
            var rc=p.review_status==='overdue'?'hm-badge-red':p.review_status==='soon'?'hm-badge-amber':'hm-badge-green';
            var rl=p.review_status==='overdue'?'Overdue '+Math.abs(p.review_days)+'d':'Review in '+p.review_days+'d';
            rh='<span class="hm-badge '+rc+'">'+rl+'</span>';
        }
         var ab='<button class="hm-btn hm-btn-outline hm-btn-sm" id="hm-btn-book-appt">'+HM_ICONS.calendar+'<span>Book Appt</span></button>'+
             '<button class="hm-btn hm-btn-outline hm-btn-sm" id="hm-btn-add-note">'+HM_ICONS.note+'<span>Add Note</span></button>';
         if(p.can_export)ab+='<button class="hm-btn hm-btn-outline hm-btn-sm" id="hm-btn-export-data">'+HM_ICONS.export+'<span>Export Data</span></button>';

        $el.html(
            '<div class="hm-patient-page">'+
                '<div class="hm-patient-fixed">'+
                    '<div class="hm-profile-back"><a href="'+PG+'" class="hm-back-btn">← Back to Patients</a></div>'+
                    '<div class="hm-patient-header">'+
                        '<div class="hm-patient-header-left">'+
                            '<div class="hm-patient-avatar">'+ini+'</div>'+
                            '<div class="hm-patient-header-info">'+
                                '<div class="hm-patient-name-row"><h1>'+esc(p.name)+'</h1><span class="hm-patient-num-badge">'+esc(p.patient_number)+'</span>'+
                                '<span class="hm-badge '+(p.is_active?'hm-badge-green':'hm-badge-gray')+'">'+(p.is_active?'Active':'Inactive')+'</span>'+
                                (p.prsi_eligible?'<span class="hm-badge hm-badge-blue">PRSI</span>':'')+rh+'</div>'+
                                '<div class="hm-patient-quick-info">'+
                                    (p.dob?'<span>'+HM_ICONS.calendar+fmtDate(p.dob)+(p.age?' ('+esc(p.age)+')':'')+'</span>':'')+
                                    (p.phone?'<span>'+HM_ICONS.phone+esc(p.phone)+'</span>':'')+
                                    (p.email&&p.email!=='Not provided'?'<span>'+HM_ICONS.email+esc(p.email)+'</span>':'')+
                                    '<span>'+HM_ICONS.clinic+esc(p.clinic_name)+'</span><span>'+HM_ICONS.person+esc(p.dispenser_name)+'</span>'+
                                '</div>'+
                            '</div>'+
                        '</div>'+
                        '<div class="hm-patient-header-right">'+ab+'</div>'+
                    '</div>'+
                    '<div class="hm-profile-tabs">'+th+'</div>'+
                '</div>'+
                '<div class="hm-patient-scroll">'+
                    '<div id="hm-tab-content" class="hm-tab-content"></div>'+
                '</div>'+
            '</div>'
        );
    }

    $el.on('click','.hm-tab-btn',function(){activeTab=$(this).data('tab');$('.hm-tab-btn').removeClass('active');$(this).addClass('active');loadTab(activeTab);});
    $el.on('click','#hm-btn-book-appt',function(){window.location='/calendar/?patient_id='+pid;});
    $el.on('click','#hm-btn-add-note',function(){activeTab='notes';$('.hm-tab-btn').removeClass('active').filter('[data-tab="notes"]').addClass('active');loadTab('notes');setTimeout(showNoteModal,300);});
    $el.on('click','#hm-btn-export-data',showExportModal);

    function loadTab(tab){
        var $c=$('#hm-tab-content');
        $c.html('<div class="hm-loading">Loading…</div>');
        switch(tab){
            case 'overview':loadOverview($c);break;case 'details':loadDetails($c);break;
            case 'appointments':loadAppointments($c);break;case 'notes':loadNotes($c);break;
            case 'documents':loadDocuments($c);break;case 'hearing-aids':loadHearingAids($c);break;
            case 'repairs':loadRepairs($c);break;case 'returns':loadReturns($c);break;
            case 'forms':loadForms($c);break;case 'case-history':loadCaseHistory($c);break;
            case 'activity':loadActivity($c);break;
            case 'orders':loadOrders($c);break;
            case 'invoices':loadInvoices($c);break;
            default:$c.html('<div class="hm-empty">Tab not found</div>');
        }
    }

    /* ── OVERVIEW ── */
    function loadOverview($c){
        var p=patient,s=p.stats;
        var fh=p.has_finance?'<div class="hm-overview-card"><h3>Outstanding Balance</h3><div style="font-size:24px;font-weight:500;color:'+(s.balance>0?'#e53e3e':'#0BB4C4')+';">'+euro(s.balance)+'</div><div style="font-size:12px;color:#94a3b8;margin-top:4px;">Revenue: '+euro(s.revenue)+' · Paid: '+euro(s.payments)+'</div><a href="#" class="hm-ov-tab" data-tab="invoices" style="font-size:13px;color:#0BB4C4;text-decoration:none;display:block;margin-top:8px;">View invoices →</a></div>':'';
        var rh='';if(p.annual_review_date){var rc=p.review_status==='overdue'?'#e53e3e':p.review_status==='soon'?'#d97706':'#0BB4C4';var rl=p.review_status==='overdue'?'Overdue '+Math.abs(p.review_days)+'d':'Review in '+p.review_days+'d';rh='<div class="hm-overview-card"><h3>Annual Review</h3><div style="font-size:14px;">'+fmtDate(p.annual_review_date)+'</div><div style="font-size:13px;color:'+rc+';margin-top:4px;">'+rl+'</div></div>';}
        $c.html('<div class="hm-tab-section"><div class="hm-overview-grid">'+
            '<div class="hm-overview-card" id="hm-ov-aids"><h3>Current Hearing Aids</h3><div style="color:#94a3b8;font-size:13px;">Loading…</div></div>'+
            '<div class="hm-overview-card" id="hm-ov-appts"><h3>Appointments</h3><div style="color:#94a3b8;font-size:13px;">Loading…</div></div>'+
            fh+rh+
            '<div class="hm-overview-card"><h3>Marketing Preferences</h3><div style="line-height:2;font-size:14px;">'+
                '<div style="display:flex;align-items:center;gap:6px;">'+(p.marketing_email?HM_ICONS.check:HM_ICONS.x)+'<span>Email</span></div><div style="display:flex;align-items:center;gap:6px;">'+(p.marketing_sms?HM_ICONS.check:HM_ICONS.x)+'<span>SMS</span></div><div style="display:flex;align-items:center;gap:6px;">'+(p.marketing_phone?HM_ICONS.check:HM_ICONS.x)+'<span>Phone</span></div>'+
            '</div></div>'+
        '</div></div>');
        $c.on('click','.hm-ov-tab',function(e){e.preventDefault();var t=$(this).data('tab');activeTab=t;$('.hm-tab-btn').removeClass('active').filter('[data-tab="'+t+'"]').addClass('active');loadTab(t);});
        loadNotifications($c);
        $.post(_hm.ajax,{action:'hm_get_patient_products',nonce:_hm.nonce,patient_id:pid},function(r){
            var $w=$('#hm-ov-aids');if(!r.success||!r.data.length){$w.find('div').last().text('No active hearing aids');return;}
            var act=r.data.filter(function(x){return x.status==='Active';});if(!act.length){$w.find('div').last().text('No active hearing aids');return;}
            var h='';act.forEach(function(pr){h+='<div style="display:flex;gap:10px;align-items:center;padding:8px 0;border-bottom:1px solid #f1f5f9;">'+(pr.product_image?'<img src="'+esc(pr.product_image)+'" style="width:36px;height:36px;object-fit:contain;">':'<div style="display:inline-flex;align-items:center;justify-content:center;width:24px;height:24px;">'+HM_ICONS.hearing+'</div>')+'<div><div style="font-size:14px;font-weight:500;">'+esc(pr.product_name)+'</div><div style="font-size:12px;color:#94a3b8;">'+esc(pr.manufacturer)+' · Fitted '+fmtDate(pr.fitting_date)+'</div></div></div>';});
            $w.find('div').last().replaceWith(h);
        });
        $.post(_hm.ajax,{action:'hm_get_patient_appointments',nonce:_hm.nonce,patient_id:pid},function(r){
            var $w=$('#hm-ov-appts');if(!r.success||!r.data.length){$w.find('div').last().text('No appointments');return;}
            var today=new Date().toISOString().split('T')[0],past=r.data.filter(function(a){return a.appointment_date<=today;}),fut=r.data.filter(function(a){return a.appointment_date>today;});
            var h='';if(past[0]){var a=past[0];h+='<div style="margin-bottom:10px;"><div style="font-size:11px;color:#94a3b8;text-transform:uppercase;">Last</div><div style="font-size:14px;font-weight:500;">'+esc(a.service_name)+'</div><div style="font-size:12px;color:#64748b;">'+fmtDate(a.appointment_date)+' · '+esc(a.clinic_name)+'</div></div>';}
            if(fut.length){var b=fut[fut.length-1];h+='<div style="padding-top:10px;border-top:1px solid #f1f5f9;"><div style="font-size:11px;color:#94a3b8;text-transform:uppercase;">Next</div><div style="font-size:14px;font-weight:500;">'+esc(b.service_name)+'</div><div style="font-size:12px;color:#64748b;">'+fmtDate(b.appointment_date)+' at '+(b.start_time||'—').substr(0,5)+'</div></div>';}
            else h+='<div style="padding-top:10px;border-top:1px solid #f1f5f9;font-size:13px;color:#94a3b8;">No upcoming appointments</div>';
            $w.find('div').last().replaceWith(h);
        });
    }

    /* ── DETAILS ── */
    function loadDetails($c){
        var p=patient;
        function dr(l,v){return '<div class="hm-detail-row"><span class="hm-detail-label">'+esc(l)+'</span><span class="hm-detail-value">'+(v!==undefined&&v!==null&&v!==''?esc(String(v)):'—')+'</span></div>';}
        function dc(t,b,ex){return '<div class="hm-detail-card'+(ex?' '+ex:'')+'"><div class="hm-detail-card-title">'+esc(t)+'</div>'+b+'</div>';}
        function ef(l,t,id,v,opts){if(t==='select'){var o=(opts||[]).map(function(x){return'<option'+(x===v?' selected':'')+' value="'+esc(x)+'">'+(x||'—')+'</option>';}).join('');return'<div class="hm-form-group"><label class="hm-label">'+esc(l)+'</label><select class="hm-dd" id="'+id+'">'+o+'</select></div>';}return'<div class="hm-form-group"><label class="hm-label">'+esc(l)+'</label><input type="'+t+'" class="hm-inp" id="'+id+'" value="'+esc(v||'')+'"></div>';}
        function mt(name,label,checked){return'<label class="hm-pref-item"><input type="checkbox" class="hm-pref-check" data-pref="'+name+'"'+(checked?' checked':'')+'><span>'+label+'</span></label>';}

        function renderView(){
            $c.html('<div class="hm-tab-section">'+
                '<div class="hm-section-header"><h3>Patient Details</h3><button class="hm-btn hm-btn-link hm-btn-sm" id="hm-details-edit">'+HM_ICONS.edit+'Edit</button></div>'+
                '<div class="hm-detail-grid">'+
                    dc('Name & Contact',dr('Title',p.patient_title)+dr('First name',p.first_name)+dr('Last name',p.last_name)+dr('Date of birth',fmtDate(p.dob))+dr('Phone',p.phone)+dr('Mobile',p.mobile)+dr('Email',p.email))+
                    dc('PRSI Details',dr('PRSI number',p.prsi_number)+dr('PRSI eligible',p.prsi_eligible?'Yes':'No'))+
                    dc('Address',dr('Address',p.address)+dr('Eircode',p.eircode))+
                    dc('Secondary Details',dr('GP name',p.gp_name)+dr('GP address',p.gp_address)+dr('Next of kin',p.nok_name)+dr('NOK phone',p.nok_phone))+
                    dc('In-house Details',dr('Referral source',p.referral_source)+dr('Sub-source',p.referral_sub_source)+dr('Dispenser',p.dispenser_name)+dr('Primary clinic',p.clinic_name)+dr('Annual review date',fmtDate(p.annual_review_date))+dr('Patient active',p.is_active?'Yes':'No'))+
                    '<div class="hm-detail-card hm-detail-card-gdpr"><div class="hm-detail-card-title">GDPR &amp; Marketing</div><div class="hm-detail-gdpr-row">'+dr('GDPR consent',p.gdpr_consent?'Consented '+fmtDate(p.gdpr_consent_date)+' (v'+(p.gdpr_consent_version||'1.0')+')':'No consent')+'</div>'+
                    '<div class="hm-pref-wrap"><strong class="hm-pref-title">Marketing preferences</strong>'+
                    '<div class="hm-pref-list">'+mt('marketing_email','Email',p.marketing_email)+mt('marketing_sms','SMS',p.marketing_sms)+mt('marketing_phone','Phone',p.marketing_phone)+'</div>'+
                    '<button class="hm-btn hm-btn-teal hm-btn-sm" id="hm-save-mkt">Update Preferences</button></div></div></div>'+
                '</div>'+
                (p.is_admin?'<div class="hm-card" style="margin-top:16px;border:1px solid #fecdd3;"><div class="hm-card-hd" style="color:#e53e3e;">'+HM_ICONS.warning+' GDPR — Right to Erasure</div><div class="hm-card-body"><p style="font-size:13px;color:#64748b;">Anonymises personal data. Clinical + financial records retained. Irreversible.</p><button class="hm-btn hm-btn-danger hm-btn-sm" id="hm-anonymise-btn">Anonymise Patient</button></div></div>':'')+
            '</div>');
            $c.on('click','#hm-details-edit',renderEdit);
            $c.on('click','#hm-save-mkt',function(){
                $.post(_hm.ajax,{action:'hm_update_marketing_prefs',nonce:_hm.nonce,patient_id:pid,marketing_email:$('[data-pref="marketing_email"]').is(':checked')?'1':'0',marketing_sms:$('[data-pref="marketing_sms"]').is(':checked')?'1':'0',marketing_phone:$('[data-pref="marketing_phone"]').is(':checked')?'1':'0'},function(r){if(r.success)toast('Preferences updated');else toast('Error','error');});
            });
            $c.on('click','#hm-anonymise-btn',showAnonymiseModal);
        }

        function renderEdit(){
            $c.html('<div class="hm-tab-section"><div class="hm-section-header"><h3>Edit Patient Details</h3></div>'+
                '<div class="hm-cols-2"><div>'+ef('Title','select','ep-title',p.patient_title,['','Mr','Mrs','Ms','Miss','Dr','Other'])+ef('First name','text','ep-fn',p.first_name)+ef('Last name','text','ep-ln',p.last_name)+ef('Date of birth','date','ep-dob',p.dob)+ef('Phone','text','ep-phone',p.phone)+ef('Mobile','text','ep-mobile',p.mobile)+ef('Email','email','ep-email',p.email)+
                '<div class="hm-form-group"><label class="hm-label">Address</label><textarea class="hm-textarea" id="ep-address" rows="3">'+esc(p.address)+'</textarea></div>'+ef('Eircode','text','ep-eircode',p.eircode)+'</div>'+
                '<div>'+ef('GP name','text','ep-gp-name',p.gp_name)+
                '<div class="hm-form-group"><label class="hm-label">GP address</label><textarea class="hm-textarea" id="ep-gp-addr" rows="2">'+esc(p.gp_address)+'</textarea></div>'+
                ef('Next of kin','text','ep-nok-name',p.nok_name)+ef('NOK phone','text','ep-nok-phone',p.nok_phone)+ef('PRSI number','text','ep-prsi-num',p.prsi_number)+ef('Referral source','text','ep-ref',p.referral_source)+
                '<div class="hm-form-group"><label class="hm-label">Primary clinic</label><select class="hm-dd" id="ep-clinic"><option value="">— Select —</option></select></div>'+
                ef('Annual review date','date','ep-review',p.annual_review_date)+
                '<div class="hm-form-group"><label style="display:flex;align-items:center;gap:8px;cursor:pointer;"><input type="checkbox" id="ep-prsi"'+(p.prsi_eligible?' checked':'')+'>PRSI Eligible</label></div>'+
                '<div class="hm-form-group"><label style="display:flex;align-items:center;gap:8px;cursor:pointer;"><input type="checkbox" id="ep-active"'+(p.is_active?' checked':'')+'>Patient Active</label></div>'+
                '</div></div>'+
                '<div style="display:flex;gap:10px;margin-top:20px;"><button class="hm-btn hm-btn-teal" id="ep-save">Save Changes</button><button class="hm-btn hm-btn-outline" id="ep-cancel">Cancel</button></div>'+
            '</div>');
            $.post(_hm.ajax,{action:'hm_get_clinics',nonce:_hm.nonce},function(r){
                if(!r.success)return;
                var $sel=$('#ep-clinic');
                r.data.forEach(function(c){$sel.append('<option value="'+c.id+'">'+esc(c.name)+'</option>');});
                $sel.val(p.assigned_clinic_id||'');
            });
            $c.on('click','#ep-cancel',renderView);
            $c.on('click','#ep-save',function(){
                var $btn=$(this).prop('disabled',true).text('Saving…');
                $.post(_hm.ajax,{action:'hm_update_patient',nonce:_hm.nonce,patient_id:pid,patient_title:$('#ep-title').val(),first_name:$('#ep-fn').val(),last_name:$('#ep-ln').val(),dob:$('#ep-dob').val(),patient_phone:$('#ep-phone').val(),patient_mobile:$('#ep-mobile').val(),patient_email:$('#ep-email').val(),patient_address:$('#ep-address').val(),patient_eircode:$('#ep-eircode').val(),gp_name:$('#ep-gp-name').val(),gp_address:$('#ep-gp-addr').val(),nok_name:$('#ep-nok-name').val(),nok_phone:$('#ep-nok-phone').val(),prsi_number:$('#ep-prsi-num').val(),referral_source:$('#ep-ref').val(),assigned_clinic_id:$('#ep-clinic').val(),annual_review_date:$('#ep-review').val(),prsi_eligible:$('#ep-prsi').is(':checked')?'1':'0',is_active:$('#ep-active').is(':checked')?'1':'0'},function(r){
                    if(r.success){toast('Patient updated');$.post(_hm.ajax,{action:'hm_get_patient',nonce:_hm.nonce,patient_id:pid},function(r2){if(r2.success){patient=r2.data;renderProfile();}});renderView();}
                    else{toast(r.data||'Error','error');$btn.prop('disabled',false).text('Save Changes');}
                });
            });
        }
        renderView();
    }

    /* ── APPOINTMENTS ── */
    function loadAppointments($c){
        $.post(_hm.ajax,{action:'hm_get_patient_appointments',nonce:_hm.nonce,patient_id:pid},function(r){
            if(!r.success){$c.html('<div class="hm-empty">Error</div>');return;}
            var a=r.data,h='<div class="hm-tab-section"><div class="hm-section-header"><h3>Appointments ('+a.length+')</h3><a href="/calendar/?patient_id='+pid+'" class="hm-btn hm-btn-teal hm-btn-sm">+ Book</a></div>';
            if(!a.length)h+='<div class="hm-empty"><div class="hm-empty-icon">'+HM_ICONS.calendar+'</div><div class="hm-empty-text">No appointments</div></div>';
            else{h+='<table class="hm-table"><thead><tr><th>Date</th><th>Time</th><th>Type</th><th>Dispenser</th><th>Clinic</th><th>Status</th><th>Outcome</th></tr></thead><tbody>';
                a.forEach(function(x){
                    var sc=x.status==='Confirmed'?'hm-badge-green':x.status==='Cancelled'?'hm-badge-red':'hm-badge-gray';
                    var dot=x.service_colour?'<span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:'+esc(x.service_colour)+';margin-right:4px;"></span>':'';
                    h+='<tr><td>'+fmtDate(x.appointment_date)+'</td><td>'+(x.start_time?x.start_time.substr(0,5):'—')+'</td><td>'+dot+esc(x.service_name)+'</td><td>'+esc(x.dispenser_name)+'</td><td>'+esc(x.clinic_name)+'</td>'+
                    '<td><span class="hm-badge hm-badge-sm '+sc+'">'+esc(x.status)+'</span></td>'+
                    '<td>'+(x.outcome_name?'<span class="hm-badge hm-badge-sm" style="background:'+(x.outcome_banner_colour||'#e2e8f0')+';color:#fff;">'+esc(x.outcome_name)+'</span>':'—')+'</td></tr>';
                    if(x.notes)h+='<tr><td colspan="7"><div class="hm-appt-note">'+esc(x.notes)+'</div></td></tr>';
                });
                h+='</tbody></table>';}
            $c.html(h+'</div>');
        });
    }

    /* ── NOTES ── */
    function loadNotes($c){
        $.post(_hm.ajax,{action:'hm_get_patient_notes',nonce:_hm.nonce,patient_id:pid},function(r){
            if(!r.success){$c.html('<div class="hm-empty">Error</div>');return;}
            var n=r.data,tc={clinical:'#0BB4C4',admin:'#64748b',cancellation:'#e53e3e',system:'#3b82f6','follow-up':'#d97706',manual:'#0BB4C4'};
            var h='<div class="hm-tab-section"><div class="hm-section-header"><h3>Notes ('+n.length+')</h3><button class="hm-btn hm-btn-teal hm-btn-sm" id="hm-add-note-btn">+ Add Note</button></div>';
            if(!n.length)h+='<div class="hm-empty"><div class="hm-empty-icon">'+HM_ICONS.note+'</div><div class="hm-empty-text">No notes</div></div>';
            else n.forEach(function(x){var c=tc[x.note_type.toLowerCase()]||'#0BB4C4';h+='<div class="hm-note-card" style="border-left-color:'+c+';"><div class="hm-note-type"><span class="hm-badge hm-badge-sm" style="background:'+c+';color:#fff;">'+esc(x.note_type)+'</span></div><div class="hm-note-text">'+esc(x.note_text)+'</div><div style="display:flex;gap:16px;align-items:center;margin-top:8px;"><div class="hm-note-meta">By '+esc(x.created_by)+' at '+fmtDateTime(x.created_at)+'</div>'+(x.can_edit?'<a href="#" class="hm-edit-note" data-id="'+x._ID+'" data-text="'+esc(x.note_text)+'" data-type="'+esc(x.note_type)+'" style="font-size:12px;color:#0BB4C4;">Edit</a><a href="#" class="hm-delete-note" data-id="'+x._ID+'" style="font-size:12px;color:#e53e3e;">Delete</a>':'')+'</div></div>';});
            $c.html(h+'</div>');
        });
        $c.off('click','#hm-add-note-btn').on('click','#hm-add-note-btn',function(){showNoteModal();});
        $c.off('click','.hm-edit-note').on('click','.hm-edit-note',function(e){e.preventDefault();showNoteModal($(this).data('id'),$(this).data('text'),$(this).data('type'));});
        $c.off('click','.hm-delete-note').on('click','.hm-delete-note',function(e){e.preventDefault();if(!confirm('Delete?'))return;$.post(_hm.ajax,{action:'hm_delete_patient_note',nonce:_hm.nonce,_ID:$(this).data('id')},function(r){if(r.success){toast('Deleted');loadNotes($('#hm-tab-content'));}else toast('Cannot delete','error');});});
    }

    function showNoteModal(id,text,type){
        if($('#hm-modal-overlay').length)return;
        $('body').append('<div id="hm-modal-overlay" class="hm-modal-bg"><div class="hm-modal"><div class="hm-modal-hd"><span>'+(id?'Edit':'Add')+' Note</span><button class="hm-modal-x">&times;</button></div><div class="hm-modal-body"><div class="hm-form-group"><label class="hm-label">Note type</label><select class="hm-dd" id="note-type">'+['Clinical','Admin','Follow-up','Cancellation','System','Manual'].map(function(t){return'<option'+(type&&type.toLowerCase()===t.toLowerCase()?' selected':'')+'>'+t+'</option>';}).join('')+'</select></div><div class="hm-form-group"><label class="hm-label">Note text</label><textarea class="hm-textarea" id="note-text" rows="6">'+esc(text||'')+'</textarea></div></div><div class="hm-modal-ft"><button class="hm-btn hm-btn-outline hm-modal-x">Cancel</button><button class="hm-btn hm-btn-teal" id="note-save">Save</button></div></div></div>');
        $('.hm-modal-x').on('click',closeModal);
        $('#hm-modal-overlay').on('click',function(e){if(e.target===this)closeModal();});
        $('#note-save').on('click',function(){
            var txt=$.trim($('#note-text').val());if(!txt){toast('Note text required','error');return;}
            $(this).prop('disabled',true).text('Saving…');
            $.post(_hm.ajax,{action:'hm_save_patient_note',nonce:_hm.nonce,patient_id:pid,_ID:id||0,note_type:$('#note-type').val(),note_text:txt},function(r){closeModal();if(r.success){toast('Note saved');loadNotes($('#hm-tab-content'));}else toast('Error','error');});
        });
    }

    /* ── DOCUMENTS ── */
    function loadDocuments($c){
        $.post(_hm.ajax,{action:'hm_get_patient_documents',nonce:_hm.nonce,patient_id:pid},function(r){
            if(!r.success){$c.html('<div class="hm-empty">Error</div>');return;}
            var d=r.data,h='<div class="hm-tab-section"><div class="hm-section-header"><h3>Documents ('+d.length+')</h3><button class="hm-btn hm-btn-teal hm-btn-sm" id="hm-upload-doc">+ Upload</button></div>';
            if(!d.length)h+='<div class="hm-empty"><div class="hm-empty-icon">'+HM_ICONS.form+'</div><div class="hm-empty-text">No documents</div></div>';
            else{h+='<table class="hm-table"><thead><tr><th>File name</th><th>Type</th><th>Uploaded by</th><th>Date</th><th></th></tr></thead><tbody>';d.forEach(function(x){h+='<tr><td>'+esc(x.file_name)+'</td><td><span class="hm-badge hm-badge-sm hm-badge-gray">'+esc(x.document_type)+'</span></td><td>'+esc(x.created_by)+'</td><td>'+fmtDate((x.created_at||'').split(' ')[0])+'</td><td><a href="#" class="hm-download-doc hm-btn hm-btn-outline hm-btn-sm" data-id="'+x._ID+'" data-type="'+esc(x.document_type)+'" data-url="'+esc(x.download_url)+'">Download</a></td></tr>';});h+='</tbody></table>';}
            $c.html(h+'</div>');
        });
        $c.off('click','#hm-upload-doc').on('click','#hm-upload-doc',showUploadDocModal);
        $c.off('click','.hm-download-doc').on('click','.hm-download-doc',function(e){
            e.preventDefault();var $l=$(this),dt=$l.data('type'),clinical=['Audiogram','Referral Letter','GP Letter','Consent Form'].indexOf(dt)!==-1;
            var ct=clinical?'I confirm this document is being shared for the purpose of providing healthcare to this patient.':'I confirm I am authorised to export this document under HearMed\'s data handling policy.';
            showDownloadConsent(ct,function(){window.location=$l.data('url');});
        });
    }
    function showUploadDocModal(){
        if($('#hm-modal-overlay').length)return;
        $('body').append('<div id="hm-modal-overlay" class="hm-modal-bg"><div class="hm-modal"><div class="hm-modal-hd"><span>Upload Document</span><button class="hm-modal-x">&times;</button></div><div class="hm-modal-body"><div class="hm-form-group"><label class="hm-label">Document type</label><select class="hm-dd" id="doc-type"><option>Audiogram</option><option>Referral Letter</option><option>GP Letter</option><option>Insurance Document</option><option>Consent Form</option><option>Other</option></select></div><div class="hm-form-group"><label class="hm-label">File (PDF, JPG, PNG, DOCX — max 10MB)</label><input type="file" id="doc-file" accept=".pdf,.jpg,.jpeg,.png,.docx"></div></div><div class="hm-modal-ft"><button class="hm-btn hm-btn-outline hm-modal-x">Cancel</button><button class="hm-btn hm-btn-teal" id="doc-save">Upload</button></div></div></div>');
        $('.hm-modal-x').on('click',closeModal);$('#hm-modal-overlay').on('click',function(e){if(e.target===this)closeModal();});
        $('#doc-save').on('click',function(){var file=$('#doc-file')[0].files[0];if(!file){toast('Select a file','error');return;}$(this).prop('disabled',true).text('Uploading…');var fd=new FormData();fd.append('action','hm_upload_patient_document');fd.append('nonce',_hm.nonce);fd.append('patient_id',pid);fd.append('document_type',$('#doc-type').val());fd.append('file',file);$.ajax({url:_hm.ajax,type:'POST',data:fd,processData:false,contentType:false,success:function(r){closeModal();if(r.success){toast('Uploaded');loadDocuments($('#hm-tab-content'));}else toast(r.data||'Failed','error');}});});
    }
    function showDownloadConsent(txt,cb){
        if($('#hm-modal-overlay').length)return;
        $('body').append('<div id="hm-modal-overlay" class="hm-modal-bg"><div class="hm-modal" style="max-width:460px;"><div class="hm-modal-hd"><span>Download Consent</span><button class="hm-modal-x">&times;</button></div><div class="hm-modal-body"><label style="display:flex;align-items:flex-start;gap:8px;font-size:13px;cursor:pointer;"><input type="checkbox" id="dl-consent" style="margin-top:2px;flex-shrink:0;"><span>'+esc(txt)+'</span></label></div><div class="hm-modal-ft"><button class="hm-btn hm-btn-outline hm-modal-x">Cancel</button><button class="hm-btn hm-btn-teal" id="dl-confirm" disabled>Download</button></div></div></div>');
        $('#dl-consent').on('change',function(){$('#dl-confirm').prop('disabled',!this.checked);});$('.hm-modal-x').on('click',closeModal);$('#hm-modal-overlay').on('click',function(e){if(e.target===this)closeModal();});
        $('#dl-confirm').on('click',function(){closeModal();cb();});
    }

    /* ── HEARING AIDS ── */
    function loadHearingAids($c){
        $.post(_hm.ajax,{action:'hm_get_patient_products',nonce:_hm.nonce,patient_id:pid},function(r){
            if(!r.success){$c.html('<div class="hm-empty">Error</div>');return;}
            var prods=r.data,act=prods.filter(function(p){return p.status==='Active';}),inact=prods.filter(function(p){return p.status!=='Active';});
            var h='<div class="hm-tab-section"><div class="hm-section-header"><h3>Hearing Aids</h3><button class="hm-btn hm-btn-teal hm-btn-sm" id="hm-add-product-btn">+ Add Hearing Aid</button></div>';
            if(!act.length&&!inact.length)h+='<div class="hm-empty"><div class="hm-empty-icon">'+HM_ICONS.hearing+'</div><div class="hm-empty-text">No hearing aids</div></div>';
            if(act.length){h+='<div style="margin-bottom:24px;">';act.forEach(function(pr){h+=pcard(pr,true);});h+='</div>';}
            if(inact.length){h+='<details style="margin-top:16px;"><summary style="font-size:14px;font-weight:500;color:#94a3b8;cursor:pointer;">Previous Hearing Aids ('+inact.length+')</summary><div style="margin-top:12px;">';inact.forEach(function(pr){h+=pcard(pr,false);});h+='</div></details>';}
            $c.html(h+'</div>');
        });
        function pcard(pr,isAct){var sc={Active:'hm-badge-green',Inactive:'hm-badge-gray',Lost:'hm-badge-red',Replaced:'hm-badge-amber'}[pr.status]||'hm-badge-gray';return'<div class="hm-product-card">'+(pr.product_image?'<div class="hm-product-img"><img src="'+esc(pr.product_image)+'" alt="'+esc(pr.product_name)+'"></div>':'<div class="hm-product-img">'+HM_ICONS.hearing+'</div>')+'<div style="flex:1;min-width:0;"><div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;"><strong style="font-size:15px;color:#151B33;">'+esc(pr.product_name)+'</strong><span class="hm-badge hm-badge-sm '+sc+'">'+esc(pr.status)+'</span></div><div style="font-size:13px;color:#64748b;margin-bottom:4px;">'+esc(pr.manufacturer)+(pr.model?' · '+esc(pr.model):'')+(pr.style?' · '+esc(pr.style):'')+'</div>'+(pr.serial_left||pr.serial_right?'<div style="font-size:13px;color:#64748b;margin-bottom:4px;">'+(pr.serial_left?'L: <code>'+esc(pr.serial_left)+'</code> ':'')+( pr.serial_right?'R: <code>'+esc(pr.serial_right)+'</code>':'')+'</div>':'')+'<div style="font-size:12px;color:#94a3b8;">Fitted: '+fmtDate(pr.fitting_date)+' · Warranty: '+fmtDate(pr.warranty_expiry)+'</div>'+(pr.inactive_reason?'<div style="font-size:12px;color:#94a3b8;">Reason: '+esc(pr.inactive_reason)+'</div>':'')+'</div>'+(isAct?'<div style="display:flex;flex-direction:column;gap:6px;flex-shrink:0;"><button class="hm-btn hm-btn-outline hm-btn-sm hm-mark-inactive" data-id="'+pr._ID+'">Mark Inactive</button><button class="hm-btn hm-btn-outline hm-btn-sm hm-log-repair" data-id="'+pr._ID+'" data-name="'+esc(pr.product_name)+'" data-sl="'+esc(pr.serial_left||'')+'" data-sr="'+esc(pr.serial_right||'')+'">Log Repair</button></div>':'')+'</div>';}
        $c.off('click','.hm-mark-inactive').on('click','.hm-mark-inactive',function(){showMarkInactiveModal($(this).data('id'),function(){loadHearingAids($c);});});
        $c.off('click','.hm-log-repair').on('click','.hm-log-repair',function(){var $b=$(this);showLogRepairModal($b.data('id'),$b.data('name'),$b.data('sl'),$b.data('sr'),function(){toast('Repair logged');loadRepairs($('#hm-tab-content'));});});
        $c.off('click','#hm-add-product-btn').on('click','#hm-add-product-btn',function(){showAddProductModal(function(){loadHearingAids($c);});});
    }
    function showMarkInactiveModal(ppId,cb){
        if($('#hm-modal-overlay').length)return;
        $('body').append('<div id="hm-modal-overlay" class="hm-modal-bg"><div class="hm-modal"><div class="hm-modal-hd"><span>Mark Inactive</span><button class="hm-modal-x">&times;</button></div><div class="hm-modal-body"><div class="hm-form-group"><label class="hm-label">Reason</label><select class="hm-dd" id="inactive-reason"><option>Lost</option><option>Bought New Aids</option><option>Returned</option><option>Other</option></select></div><div class="hm-form-group"><label class="hm-label">Notes (optional)</label><textarea class="hm-textarea" id="inactive-notes" rows="3"></textarea></div></div><div class="hm-modal-ft"><button class="hm-btn hm-btn-outline hm-modal-x">Cancel</button><button class="hm-btn hm-btn-danger" id="inactive-save">Mark Inactive</button></div></div></div>');
        $('.hm-modal-x').on('click',closeModal);$('#hm-modal-overlay').on('click',function(e){if(e.target===this)closeModal();});
        $('#inactive-save').on('click',function(){$(this).prop('disabled',true).text('Saving…');$.post(_hm.ajax,{action:'hm_update_patient_product_status',nonce:_hm.nonce,_ID:ppId,status:'Inactive',reason:$('#inactive-reason').val()+($.trim($('#inactive-notes').val())?': '+$.trim($('#inactive-notes').val()):'')},function(r){closeModal();if(r.success){toast('Marked inactive');cb();}else toast('Error','error');});});
    }
    function showLogRepairModal(ppId,name,sl,sr,cb){
        if($('#hm-modal-overlay').length)return;
        $('body').append('<div id="hm-modal-overlay" class="hm-modal-bg"><div class="hm-modal"><div class="hm-modal-hd"><span>Log Repair</span><button class="hm-modal-x">&times;</button></div><div class="hm-modal-body"><div class="hm-form-group"><label class="hm-label">Product</label><input class="hm-inp" value="'+esc(name||'Select on Hearing Aids tab')+'" readonly></div><div class="hm-form-group"><label class="hm-label">Serial number</label><input class="hm-inp" id="repair-serial" value="'+esc(sl||sr||'')+'"></div><div class="hm-form-group"><label class="hm-label">Warranty status</label><select class="hm-dd" id="repair-warranty"><option>In Warranty</option><option>Out of Warranty</option><option>Unknown</option></select></div><div class="hm-form-group"><label class="hm-label">Repair notes</label><textarea class="hm-textarea" id="repair-notes" rows="3" placeholder="Describe the issue…"></textarea></div></div><div class="hm-modal-ft"><button class="hm-btn hm-btn-outline hm-modal-x">Cancel</button><button class="hm-btn hm-btn-teal" id="repair-save">Log Repair</button></div></div></div>');
        $('.hm-modal-x').on('click',closeModal);$('#hm-modal-overlay').on('click',function(e){if(e.target===this)closeModal();});
        $('#repair-save').on('click',function(){$(this).prop('disabled',true).text('Saving…');$.post(_hm.ajax,{action:'hm_create_patient_repair',nonce:_hm.nonce,patient_id:pid,patient_product_id:ppId||0,serial_number:$('#repair-serial').val(),warranty_status:$('#repair-warranty').val(),repair_notes:$('#repair-notes').val()},function(r){closeModal();if(r.success&&cb)cb();else if(!r.success)toast('Error','error');});});
    }

    /* ── REPAIRS ── */
    function loadRepairs($c){
        $.post(_hm.ajax,{action:'hm_get_patient_repairs',nonce:_hm.nonce,patient_id:pid},function(r){
            if(!r.success){$c.html('<div class="hm-empty">Error</div>');return;}
            var rr=r.data,h='<div class="hm-tab-section"><div class="hm-section-header"><h3>Repairs ('+rr.length+')</h3><button class="hm-btn hm-btn-outline hm-btn-sm" id="hm-log-repair-btn">+ Log Repair</button></div>';
            if(!rr.length)h+='<div class="hm-empty"><div class="hm-empty-icon">'+HM_ICONS.repair+'</div><div class="hm-empty-text">No repairs</div></div>';
            else{h+='<table class="hm-table"><thead><tr><th>Hearing aid</th><th>Serial</th><th>Booked</th><th>Sent</th><th>Received</th><th>Status</th><th>Warranty</th></tr></thead><tbody>';rr.forEach(function(x){var sc=x.status==='Booked'?'hm-badge-amber':x.status==='Sent'?'hm-badge-blue':'hm-badge-green';h+='<tr><td>'+esc(x.product_name)+'</td><td><code>'+esc(x.serial_number||'—')+'</code></td><td>'+fmtDate(x.date_booked)+'</td><td>'+fmtDate(x.date_sent)+'</td><td>'+fmtDate(x.date_received)+'</td><td><span class="hm-badge hm-badge-sm '+sc+'">'+esc(x.status)+'</span></td><td>'+esc(x.warranty_status||'—')+'</td></tr>'+(x.repair_notes?'<tr><td colspan="7"><div class="hm-appt-note">'+esc(x.repair_notes)+'</div></td></tr>':'');});h+='</tbody></table>';}
            $c.html(h+'</div>');
        });
        $c.off('click','#hm-log-repair-btn').on('click','#hm-log-repair-btn',function(){showLogRepairModal(0,'','','',function(){toast('Repair logged');loadRepairs($c);});});
    }

    /* ── RETURNS ── */
    function loadReturns($c){
        $.post(_hm.ajax,{action:'hm_get_patient_returns',nonce:_hm.nonce,patient_id:pid},function(r){
            if(!r.success){$c.html('<div class="hm-empty">Error</div>');return;}
            var ret=r.data,h='<div class="hm-tab-section"><div class="hm-section-header"><h3>Returns / Credit Notes ('+ret.length+')</h3></div>';
            if(!ret.length)h+='<div class="hm-empty"><div class="hm-empty-icon">'+HM_ICONS.returns+'</div><div class="hm-empty-text">No returns</div></div>';
            else{h+='<table class="hm-table"><thead><tr><th>Hearing aid</th><th>Credit note #</th><th>Refund amount</th><th>Cheque status</th><th></th></tr></thead><tbody>';ret.forEach(function(x){var ch=x.cheque_sent?'<span class="hm-badge hm-badge-green">'+HM_ICONS.check+' Sent '+fmtDate(x.cheque_sent_date)+'</span>':'<span class="hm-badge hm-badge-red">'+HM_ICONS.x+' Cheque Outstanding</span>';h+='<tr><td>'+esc(x.product_name)+'</td><td><code>'+esc(x.credit_note_num)+'</code></td><td>'+euro(x.refund_amount)+'</td><td>'+ch+'</td><td>'+(!x.cheque_sent?'<button class="hm-btn hm-btn-outline hm-btn-sm hm-log-cheque" data-id="'+x._ID+'">Log Cheque Sent</button>':'')+'</td></tr>';});h+='</tbody></table>';}
            $c.html(h+'</div>');
        });
        $c.off('click','.hm-log-cheque').on('click','.hm-log-cheque',function(){var id=$(this).data('id');showLogChequeModal(id,function(){loadReturns($c);});});
    }
    function showLogChequeModal(cnId,cb){
        if($('#hm-modal-overlay').length)return;
        $('body').append('<div id="hm-modal-overlay" class="hm-modal-bg"><div class="hm-modal" style="max-width:380px;"><div class="hm-modal-hd"><span>Log Cheque Sent</span><button class="hm-modal-x">&times;</button></div><div class="hm-modal-body"><div class="hm-form-group"><label class="hm-label">Date sent</label><input type="date" class="hm-inp" id="cheque-date" value="'+new Date().toISOString().split('T')[0]+'"></div></div><div class="hm-modal-ft"><button class="hm-btn hm-btn-outline hm-modal-x">Cancel</button><button class="hm-btn hm-btn-teal" id="cheque-save">Log</button></div></div></div>');
        $('.hm-modal-x').on('click',closeModal);$('#hm-modal-overlay').on('click',function(e){if(e.target===this)closeModal();});
        $('#cheque-save').on('click',function(){$.post(_hm.ajax,{action:'hm_log_cheque_sent',nonce:_hm.nonce,_ID:cnId,cheque_date:$('#cheque-date').val()},function(r){closeModal();if(r.success){toast('Cheque logged');cb();}else toast('Error','error');});});
    }

    /* ── FORMS ── */
    function loadForms($c){
        $.post(_hm.ajax,{action:'hm_get_patient_forms',nonce:_hm.nonce,patient_id:pid},function(r){
            if(!r.success){$c.html('<div class="hm-empty">Error</div>');return;}
            var f=r.data,h='<div class="hm-tab-section"><div class="hm-section-header"><h3>Forms ('+f.length+')</h3></div>';
            if(!f.length)h+='<div class="hm-empty"><div class="hm-empty-icon">'+HM_ICONS.form+'</div><div class="hm-empty-text">No forms</div></div>';
            else{h+='<table class="hm-table"><thead><tr><th>Form type</th><th>Date</th><th>GDPR consent</th><th>Signed</th></tr></thead><tbody>';f.forEach(function(x){h+='<tr><td>'+esc(x.form_type)+'</td><td>'+fmtDateTime(x.created_at)+'</td><td>'+(x.gdpr_consent?HM_ICONS.check:HM_ICONS.x)+'</td><td>'+(x.has_signature?HM_ICONS.check:HM_ICONS.x)+'</td></tr>';});h+='</tbody></table>';}
            $c.html(h+'</div>');
        });
    }

    /* ── CASE HISTORY ── */
    function loadCaseHistory($c){
        $c.html('<div class="hm-tab-section"><div class="hm-section-header"><h3>Case History &amp; AI Transcription</h3></div>'+
            '<div class="hm-card" style="margin-bottom:20px;"><div class="hm-card-hd">Manual Case History</div><div class="hm-card-body">'+
                '<div class="hm-form-row"><div class="hm-form-group"><label class="hm-label">Date</label><input type="date" class="hm-inp" id="ch-date" value="'+new Date().toISOString().split('T')[0]+'"></div><div class="hm-form-group"><label class="hm-label">Appointment type</label><input type="text" class="hm-inp" id="ch-type" placeholder="e.g. Initial consultation"></div></div>'+
                '<div class="hm-form-group"><label class="hm-label">Chief complaint</label><textarea class="hm-textarea" id="ch-complaint" rows="2"></textarea></div>'+
                '<div class="hm-form-group"><label class="hm-label">History of presenting complaint</label><textarea class="hm-textarea" id="ch-hpc" rows="2"></textarea></div>'+
                '<div class="hm-form-group"><label class="hm-label">Audiological history</label><textarea class="hm-textarea" id="ch-audio" rows="2"></textarea></div>'+
                '<div class="hm-form-group"><label class="hm-label">Medical history relevant to hearing</label><textarea class="hm-textarea" id="ch-medical" rows="2"></textarea></div>'+
                '<div class="hm-form-group"><label class="hm-label">Outcome / recommendations</label><textarea class="hm-textarea" id="ch-outcome" rows="2"></textarea></div>'+
                '<div class="hm-form-group"><label class="hm-label">Follow-up plan</label><textarea class="hm-textarea" id="ch-followup" rows="2"></textarea></div>'+
                '<button class="hm-btn hm-btn-teal" id="ch-save">Save Case History</button>'+
            '</div></div>'+
            '<div class="hm-ai-notice"><div class="hm-ai-notice-icon">'+HM_ICONS.warning+'</div><div style="flex:1;">'+
                '<strong>AI Processing Notice</strong>'+
                '<p>This consultation will be processed by an AI system (OpenRouter via Make.com). The patient must be informed before recording begins.</p>'+
                '<label style="display:flex;align-items:flex-start;gap:8px;cursor:pointer;margin-top:10px;"><input type="checkbox" id="hm-ai-consent" style="margin-top:2px;flex-shrink:0;"><span>I confirm the patient has been informed that this consultation will be processed by AI.</span></label>'+
                '<button class="hm-btn hm-btn-teal" id="hm-start-recording" disabled style="margin-top:12px;">'+HM_ICONS.mic+'<span>Start Recording</span></button>'+
            '</div></div>'+
            '<div id="hm-rec-status" style="display:none;margin-top:12px;"></div>'+
            '<div id="hm-transcript-wrap" style="display:none;margin-top:16px;"></div>'+
        '</div>');

        $c.on('click','#ch-save',function(){
            var parts=[];
            if($('#ch-complaint').val())parts.push('Chief complaint: '+$('#ch-complaint').val());if($('#ch-hpc').val())parts.push('History: '+$('#ch-hpc').val());if($('#ch-audio').val())parts.push('Audiological: '+$('#ch-audio').val());if($('#ch-medical').val())parts.push('Medical: '+$('#ch-medical').val());if($('#ch-outcome').val())parts.push('Outcome: '+$('#ch-outcome').val());if($('#ch-followup').val())parts.push('Follow-up: '+$('#ch-followup').val());
            if(!parts.length){toast('Enter at least one field','error');return;}
            $(this).prop('disabled',true).text('Saving…');
            $.post(_hm.ajax,{action:'hm_save_case_history',nonce:_hm.nonce,patient_id:pid,appointment_type:$('#ch-type').val(),note_text:'['+$('#ch-date').val()+' · '+($('#ch-type').val()||'Case History')+']\n'+parts.join('\n')},function(r){if(r.success){toast('Case history saved');loadCaseHistory($c);}else{toast('Error','error');$('#ch-save').prop('disabled',false).text('Save Case History');}});
        });
        $c.on('change','#hm-ai-consent',function(){$('#hm-start-recording').prop('disabled',!this.checked);});

        var mr,chunks=[],isRec=false;
        $c.on('click','#hm-start-recording',function(){
            if(isRec)return;
            if(!navigator.mediaDevices||!navigator.mediaDevices.getUserMedia){toast('Recording not supported','error');return;}
            navigator.mediaDevices.getUserMedia({audio:true}).then(function(stream){
                isRec=true;chunks=[];mr=new MediaRecorder(stream);
                mr.ondataavailable=function(e){chunks.push(e.data);};
                mr.onstop=function(){stream.getTracks().forEach(function(t){t.stop();});isRec=false;$('#hm-rec-status').html('<div style="color:#94a3b8;font-size:13px;">Processing…</div>').show();
                    var blob=new Blob(chunks,{type:'audio/webm'}),reader=new FileReader();
                    reader.onloadend=function(){var wh=(typeof HM!=='undefined'&&HM.ai_webhook)?HM.ai_webhook:'';if(!wh){showTx('[AI webhook not configured — transcript would appear here]');return;}$.ajax({url:wh,method:'POST',data:JSON.stringify({audio:reader.result.split(',')[1],patient_id:pid}),contentType:'application/json',success:function(res){showTx(res.transcript||JSON.stringify(res));},error:function(){$('#hm-rec-status').html('<div style="color:#e53e3e;font-size:13px;">Transcription failed</div>');}});};
                    reader.readAsDataURL(blob);
                };
                mr.start();
                $('#hm-rec-status').html('<div style="display:flex;align-items:center;gap:10px;"><span style="width:10px;height:10px;background:#e53e3e;border-radius:50%;display:inline-block;"></span><span style="font-size:13px;color:#e53e3e;font-weight:500;">Recording…</span><button class="hm-btn hm-btn-outline hm-btn-sm" id="hm-stop-rec">Stop</button></div>').show();
            }).catch(function(){toast('Microphone access denied','error');});
        });
        $c.on('click','#hm-stop-rec',function(){if(mr&&mr.state!=='inactive')mr.stop();});
        function showTx(txt){$('#hm-rec-status').hide();$('#hm-transcript-wrap').html('<div class="hm-card"><div class="hm-card-hd">Transcript — review before saving</div><div class="hm-card-body"><textarea class="hm-textarea" id="ai-tx-text" rows="8">'+esc(txt)+'</textarea><div style="margin-top:12px;display:flex;gap:10px;"><button class="hm-btn hm-btn-teal" id="ai-save-tx">Save to Case History</button><button class="hm-btn hm-btn-outline" id="ai-discard-tx">Discard</button></div></div></div>').show();}
        $c.on('click','#ai-save-tx',function(){var t=$.trim($('#ai-tx-text').val());if(!t){toast('Empty','error');return;}$(this).prop('disabled',true).text('Saving…');$.post(_hm.ajax,{action:'hm_save_ai_transcript',nonce:_hm.nonce,patient_id:pid,transcript:t},function(r){if(r.success){toast('Saved');$('#hm-transcript-wrap').hide();}else toast('Error','error');});});
        $c.on('click','#ai-discard-tx',function(){$('#hm-transcript-wrap').hide();});
    }

    /* ── ACTIVITY ── */
    function loadActivity($c){
        $.post(_hm.ajax,{action:'hm_get_patient_audit',nonce:_hm.nonce,patient_id:pid},function(r){
            if(!r.success){$c.html('<div class="hm-empty">'+(r.data||'Access denied')+'</div>');return;}
            var a=r.data,h='<div class="hm-tab-section"><div class="hm-section-header"><h3>Activity Log ('+a.length+')</h3></div>';
            if(!a.length)h+='<div class="hm-empty"><div class="hm-empty-icon">'+HM_ICONS.audit+'</div><div class="hm-empty-text">No audit entries</div></div>';
            else{h+='<table class="hm-table"><thead><tr><th>Date / time</th><th>User</th><th>Action</th><th>Entity</th><th>Details</th></tr></thead><tbody>';a.forEach(function(x){var det='';try{det=JSON.stringify(JSON.parse(x.details),null,0).replace(/[{}]/g,'').substr(0,80);}catch(e){det=x.details||'';}h+='<tr class="hm-audit-row"><td style="white-space:nowrap;font-size:12px;">'+fmtDateTime(x.created_at)+'</td><td style="font-size:13px;">'+esc(x.user)+'</td><td><span class="hm-badge hm-badge-sm '+(x.action.indexOf('ERASURE')!==-1?'hm-badge-red':'hm-badge-gray')+'">'+esc(x.action)+'</span></td><td style="font-size:13px;color:#64748b;">'+esc(x.entity_type)+(x.entity_id?' #'+x.entity_id:'')+'</td><td style="font-size:12px;color:#94a3b8;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="'+esc(det)+'">'+esc(det)+'</td></tr>';});h+='</tbody></table>';}
            $c.html(h+'</div>');
        });
    }

    /* ── MODALS: ANONYMISE + EXPORT ── */
    function showAnonymiseModal(){
        if($('#hm-modal-overlay').length)return;
        $('body').append('<div id="hm-modal-overlay" class="hm-modal-bg"><div class="hm-modal" style="max-width:460px;"><div class="hm-modal-hd" style="background:#fef2f2;color:#e53e3e;"><span>'+HM_ICONS.warning+' Anonymise Patient — Irreversible</span><button class="hm-modal-x">&times;</button></div><div class="hm-modal-body"><p style="font-size:13px;color:#334155;">Replaces all personal data with "ANONYMISED [date]". Clinical notes, financial records and audit logs are retained. This cannot be undone.</p><div class="hm-form-group"><label class="hm-label">Type CONFIRM ERASURE to proceed</label><input type="text" class="hm-inp" id="anon-confirm" placeholder="CONFIRM ERASURE"></div></div><div class="hm-modal-ft"><button class="hm-btn hm-btn-outline hm-modal-x">Cancel</button><button class="hm-btn hm-btn-danger" id="anon-save" disabled>Anonymise Patient</button></div></div></div>');
        $('#anon-confirm').on('input',function(){$('#anon-save').prop('disabled',$(this).val()!=='CONFIRM ERASURE');});$('.hm-modal-x').on('click',closeModal);$('#hm-modal-overlay').on('click',function(e){if(e.target===this)closeModal();});
        $('#anon-save').on('click',function(){$(this).prop('disabled',true).text('Anonymising…');$.post(_hm.ajax,{action:'hm_anonymise_patient',nonce:_hm.nonce,patient_id:pid,confirm:'CONFIRM ERASURE'},function(r){closeModal();if(r.success){toast('Patient anonymised');window.location=PG;}else toast(r.data||'Error','error');});});
    }
    function showExportModal(){
        if($('#hm-modal-overlay').length)return;
        $('body').append('<div id="hm-modal-overlay" class="hm-modal-bg"><div class="hm-modal" style="max-width:460px;"><div class="hm-modal-hd"><span>Export Patient Data — GDPR Article 20</span><button class="hm-modal-x">&times;</button></div><div class="hm-modal-body"><p style="font-size:13px;color:#334155;">Exports all patient data. Identified by C-number only in filename (Tier 2 export).</p><label style="display:flex;align-items:flex-start;gap:8px;font-size:13px;cursor:pointer;margin-top:12px;"><input type="checkbox" id="export-consent" style="margin-top:2px;flex-shrink:0;"><span>I confirm I am authorised to export this patient\'s data.</span></label></div><div class="hm-modal-ft"><button class="hm-btn hm-btn-outline hm-modal-x">Cancel</button><button class="hm-btn hm-btn-teal" id="export-confirm" disabled>Export Data</button></div></div></div>');
        $('#export-consent').on('change',function(){$('#export-confirm').prop('disabled',!this.checked);});$('.hm-modal-x').on('click',closeModal);$('#hm-modal-overlay').on('click',function(e){if(e.target===this)closeModal();});
        $('#export-confirm').on('click',function(){$(this).prop('disabled',true).text('Exporting…');$.post(_hm.ajax,{action:'hm_export_patient_data',nonce:_hm.nonce,patient_id:pid},function(r){closeModal();if(r.success)toast(r.data.message||'Export logged');else toast(r.data||'Error','error');});});
    }

    /* ── ORDERS ── */
    function loadOrders($c){
        $.post(_hm.ajax,{action:'hm_get_patient_orders',nonce:_hm.nonce,patient_id:pid},function(r){
            if(!r.success){$c.html('<div class="hm-empty"><div class="hm-empty-icon">'+HM_ICONS.warning+'</div><div class="hm-empty-text">'+(r.data||'Error loading orders')+'</div></div>');return;}
            var d=r.data;
            var h='<div class="hm-tab-section"><div class="hm-section-header"><h3>Orders ('+d.length+')</h3><a href="/orders/?patient_id='+pid+'" class="hm-btn hm-btn-teal hm-btn-sm">+ Create Order</a></div>';
            if(!d.length){h+='<div class="hm-empty"><div class="hm-empty-icon">'+HM_ICONS.order+'</div><div class="hm-empty-text">No orders for this patient</div></div>';}
            else{
                var sc={Fitted:'hm-badge-green',Pending:'hm-badge-amber',Cancelled:'hm-badge-red',Refunded:'hm-badge-gray'};
                h+='<table class="hm-table"><thead><tr><th>Order #</th><th>Date</th><th>Description</th><th>Total</th><th>Status</th><th></th></tr></thead><tbody>';
                d.forEach(function(o){
                    var bc=sc[o.status]||'hm-badge-gray';
                    h+='<tr>'+
                        '<td><code class="hm-pt-hnum">'+esc(o.order_number)+'</code></td>'+
                        '<td>'+fmtDate((o.created_at||'').split(' ')[0])+'</td>'+
                        '<td style="max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:#64748b;font-size:13px;">'+esc(o.description||'—')+'</td>'+
                        '<td style="font-weight:500;">'+euro(o.grand_total)+'</td>'+
                        '<td><span class="hm-badge hm-badge-sm '+bc+'">'+esc(o.status)+'</span></td>'+
                        '<td><a href="/orders/?view='+o._ID+'" class="hm-btn hm-btn-outline hm-btn-sm">View</a></td>'+
                    '</tr>';
                });
                h+='</tbody></table>';
            }
            $c.html(h+'</div>');
        });
    }

    /* ── INVOICES ── */
    function loadInvoices($c){
        $.post(_hm.ajax,{action:'hm_get_patient_invoices',nonce:_hm.nonce,patient_id:pid},function(r){
            if(!r.success){$c.html('<div class="hm-empty"><div class="hm-empty-icon">'+HM_ICONS.warning+'</div><div class="hm-empty-text">'+(r.data||'Access denied')+'</div></div>');return;}
            var d=r.data;
            var h='<div class="hm-tab-section"><div class="hm-section-header"><h3>Invoices ('+d.length+')</h3></div>';
            if(!d.length){h+='<div class="hm-empty"><div class="hm-empty-icon">'+HM_ICONS.invoice+'</div><div class="hm-empty-text">No invoices for this patient</div></div>';}
            else{
                var sc={Paid:'hm-badge-green',Part:'hm-badge-amber',Unpaid:'hm-badge-red',Cancelled:'hm-badge-gray',Void:'hm-badge-gray'};
                var showAmts=patient.has_finance;
                h+='<table class="hm-table"><thead><tr><th>Invoice #</th><th>Date</th><th>Status</th>';
                if(showAmts)h+='<th>Total</th><th>Balance</th>';
                h+='<th></th></tr></thead><tbody>';
                d.forEach(function(inv){
                    var bc=sc[inv.status]||'hm-badge-gray';
                    h+='<tr>'+
                        '<td><code class="hm-pt-hnum">'+esc(inv.invoice_number)+'</code></td>'+
                        '<td>'+fmtDate((inv.created_at||'').split(' ')[0])+'</td>'+
                        '<td><span class="hm-badge hm-badge-sm '+bc+'">'+esc(inv.status)+'</span></td>';
                    if(showAmts)h+='<td style="font-weight:500;">'+euro(inv.grand_total)+'</td><td style="color:'+(parseFloat(inv.balance||0)>0?'#e53e3e':'#10b981')+';font-weight:500;">'+euro(inv.balance)+'</td>';
                    h+='<td><a href="#" class="hm-dl-invoice hm-btn hm-btn-outline hm-btn-sm" data-id="'+inv._ID+'" data-num="'+esc(inv.invoice_number)+'">Download</a></td></tr>';
                });
                h+='</tbody></table>';
            }
            $c.html(h+'</div>');
        });
        $c.off('click','.hm-dl-invoice').on('click','.hm-dl-invoice',function(e){
            e.preventDefault();
            var id=$(this).data('id'),num=$(this).data('num');
            showDownloadConsent('I confirm this document is being shared for the purpose of providing healthcare to this patient, and that the patient has been informed their data will be shared with the receiving party.',function(){
                window.open(_hm.ajax+'?action=hm_download_invoice&nonce='+_hm.nonce+'&_ID='+id,'_blank');
            });
        });
    }

    /* ── ADD PRODUCT MODAL ── */
    function showAddProductModal(cb){
        if($('#hm-modal-overlay').length)return;
        $('body').append(
            '<div id="hm-modal-overlay" class="hm-modal-bg">'+
            '<div class="hm-modal" style="max-width:560px;">'+
                '<div class="hm-modal-hd"><span>Add Hearing Aid</span><button class="hm-modal-x">&times;</button></div>'+
                '<div class="hm-modal-body">'+
                    '<div class="hm-form-group"><label class="hm-label">Product (from catalogue)</label>'+
                    '<select class="hm-dd" id="ap-product"><option value="">— Manual entry —</option></select></div>'+
                    '<div class="hm-form-row">'+
                        '<div class="hm-form-group"><label class="hm-label">Manufacturer</label><input type="text" class="hm-inp" id="ap-mfr"></div>'+
                        '<div class="hm-form-group"><label class="hm-label">Model</label><input type="text" class="hm-inp" id="ap-model"></div>'+
                    '</div>'+
                    '<div class="hm-form-group"><label class="hm-label">Style</label>'+
                    '<select class="hm-dd" id="ap-style"><option value="">— Select —</option><option>BTE</option><option>RIC</option><option>ITE</option><option>ITC</option><option>CIC</option><option>CROS</option><option>BiCROS</option></select></div>'+
                    '<div class="hm-form-row">'+
                        '<div class="hm-form-group"><label class="hm-label">Serial (Left)</label><input type="text" class="hm-inp" id="ap-sl" placeholder="L-XXXXXXXX"></div>'+
                        '<div class="hm-form-group"><label class="hm-label">Serial (Right)</label><input type="text" class="hm-inp" id="ap-sr" placeholder="R-XXXXXXXX"></div>'+
                    '</div>'+
                    '<div class="hm-form-row">'+
                        '<div class="hm-form-group"><label class="hm-label">Fitting date *</label><input type="date" class="hm-inp" id="ap-fit" value="'+new Date().toISOString().split('T')[0]+'"></div>'+
                        '<div class="hm-form-group"><label class="hm-label">Warranty expiry</label><input type="date" class="hm-inp" id="ap-war"></div>'+
                    '</div>'+
                '</div>'+
                '<div class="hm-modal-ft"><button class="hm-btn hm-btn-outline hm-modal-x">Cancel</button><button class="hm-btn hm-btn-teal" id="ap-save">Add Hearing Aid</button></div>'+
            '</div></div>'
        );
        // Load products from ha-product CPT
        $.post(_hm.ajax,{action:'hm_search_products',nonce:_hm.nonce,q:''},function(r){
            if(r&&r.success&&r.data){r.data.forEach(function(p){$('#ap-product').append('<option value="'+p.id+'">'+esc(p.name)+(p.manufacturer?' ('+esc(p.manufacturer)+')':'')+'</option>');});}
        });
        // On product select — autofill fields
        $('#ap-product').on('change',function(){
            var id=$(this).val();
            if(!id){$('#ap-mfr,#ap-model,#ap-style').val('');return;}
            $.post(_hm.ajax,{action:'hm_get_product_detail',nonce:_hm.nonce,product_id:id},function(r){
                if(r&&r.success&&r.data){
                    var d=r.data;
                    $('#ap-mfr').val(d.manufacturer||'');$('#ap-model').val(d.model||'');$('#ap-style').val(d.style||'');
                    // Auto-calc warranty
                    if(d.warranty_months&&$('#ap-fit').val()){
                        var fit=new Date($('#ap-fit').val());fit.setMonth(fit.getMonth()+parseInt(d.warranty_months));$('#ap-war').val(fit.toISOString().split('T')[0]);}
                }
            });
        });
        $('#ap-fit').on('change',function(){
            var pid_val=$('#ap-product').val();
            if(!pid_val)return;
            $.post(_hm.ajax,{action:'hm_get_product_detail',nonce:_hm.nonce,product_id:pid_val},function(r){
                if(r&&r.success&&r.data&&r.data.warranty_months&&$('#ap-fit').val()){
                    var fit=new Date($('#ap-fit').val());fit.setMonth(fit.getMonth()+parseInt(r.data.warranty_months));$('#ap-war').val(fit.toISOString().split('T')[0]);}
            });
        });
        $('.hm-modal-x').on('click',closeModal);
        $('#hm-modal-overlay').on('click',function(e){if(e.target===this)closeModal();});
        $('#ap-save').on('click',function(){
            var fit=$('#ap-fit').val();if(!fit){toast('Fitting date required','error');return;}
            $(this).prop('disabled',true).text('Saving…');
            $.post(_hm.ajax,{action:'hm_add_patient_product',nonce:_hm.nonce,patient_id:pid,product_id:$('#ap-product').val()||0,manufacturer:$('#ap-mfr').val(),model:$('#ap-model').val(),style:$('#ap-style').val(),serial_number_left:$('#ap-sl').val(),serial_number_right:$('#ap-sr').val(),fitting_date:fit,warranty_expiry:$('#ap-war').val()},function(r){
                closeModal();
                if(r.success){toast('Hearing aid added');if(cb)cb();}
                else{toast(r.data||'Error','error');}
            });
        });
    }

    /* ── FORMS (Full: list + submit) ── */
    function loadForms($c){
        $.post(_hm.ajax,{action:'hm_get_patient_forms',nonce:_hm.nonce,patient_id:pid},function(r){
            if(!r.success){$c.html('<div class="hm-empty">Error</div>');return;}
            var f=r.data;
            var h='<div class="hm-tab-section"><div class="hm-section-header"><h3>Forms ('+f.length+')</h3><button class="hm-btn hm-btn-teal hm-btn-sm" id="hm-add-form-btn">+ Add Form</button></div>';
            if(!f.length){h+='<div class="hm-empty"><div class="hm-empty-icon">'+HM_ICONS.form+'</div><div class="hm-empty-text">No forms completed yet</div></div>';}
            else{
                h+='<table class="hm-table"><thead><tr><th>Form type</th><th>Date</th><th>GDPR</th><th>Signed</th><th></th></tr></thead><tbody>';
                f.forEach(function(x){
                    h+='<tr>'+
                        '<td>'+esc(x.form_type)+'</td>'+
                        '<td>'+fmtDateTime(x.created_at)+'</td>'+
                        '<td>'+(x.gdpr_consent?'<span class="hm-badge hm-badge-green hm-badge-sm">'+HM_ICONS.check+' Yes</span>':'<span class="hm-badge hm-badge-gray hm-badge-sm">'+HM_ICONS.x+' No</span>')+'</td>'+
                        '<td>'+(x.has_signature?'<span class="hm-badge hm-badge-green hm-badge-sm">'+HM_ICONS.check+' Signed</span>':'<span class="hm-badge hm-badge-gray hm-badge-sm">'+HM_ICONS.x+' No sig</span>')+'</td>'+
                        '<td><a href="#" class="hm-dl-form hm-btn hm-btn-outline hm-btn-sm" data-id="'+x._ID+'">Download</a></td>'+
                    '</tr>';
                });
                h+='</tbody></table>';
            }
            $c.html(h+'</div>');
        });
        $c.off('click','#hm-add-form-btn').on('click','#hm-add-form-btn',function(){showAddFormModal(function(){loadForms($c);});});
        $c.off('click','.hm-dl-form').on('click','.hm-dl-form',function(e){
            e.preventDefault();var id=$(this).data('id');
            showDownloadConsent('I confirm this document is being shared for the purpose of providing healthcare to this patient, and that the patient has been informed their data will be shared with the receiving party.',function(){
                window.open(_hm.ajax+'?action=hm_download_patient_form&nonce='+_hm.nonce+'&_ID='+id,'_blank');
            });
        });
    }

    function showAddFormModal(cb){
        if($('#hm-modal-overlay').length)return;
        $('body').append(
            '<div id="hm-modal-overlay" class="hm-modal-bg">'+
            '<div class="hm-modal" style="max-width:560px;">'+
                '<div class="hm-modal-hd"><span>Add Form</span><button class="hm-modal-x">&times;</button></div>'+
                '<div class="hm-modal-body">'+
                    '<div class="hm-form-group"><label class="hm-label">Form type</label><select class="hm-dd" id="af-type"><option value="">Loading…</option></select></div>'+
                    '<div id="af-fields" style="margin-top:16px;"></div>'+
                    '<div style="margin-top:20px;padding:16px;background:rgba(11,180,196,0.06);border:1px solid rgba(11,180,196,0.2);border-radius:8px;">'+
                        '<p style="margin:0 0 12px;font-size:13px;font-weight:500;color:#151B33;">Consent & Preferences</p>'+
                        '<label style="display:flex;align-items:flex-start;gap:8px;font-size:13px;cursor:pointer;margin-bottom:10px;"><input type="checkbox" id="af-gdpr" style="margin-top:2px;flex-shrink:0;"><span>Patient consents to storage and processing of their data (GDPR)</span></label>'+
                        '<div style="display:flex;gap:16px;flex-wrap:wrap;">'+
                            '<label style="font-size:13px;display:flex;align-items:center;gap:6px;cursor:pointer;"><input type="checkbox" id="af-memail"> Email marketing</label>'+
                            '<label style="font-size:13px;display:flex;align-items:center;gap:6px;cursor:pointer;"><input type="checkbox" id="af-msms"> SMS marketing</label>'+
                            '<label style="font-size:13px;display:flex;align-items:center;gap:6px;cursor:pointer;"><input type="checkbox" id="af-mphone"> Phone marketing</label>'+
                        '</div>'+
                    '</div>'+
                    '<div style="margin-top:20px;">'+
                        '<label class="hm-label">Patient signature</label>'+
                        '<canvas id="af-sig-pad" class="hm-sig-pad" width="480" height="160" style="touch-action:none;"></canvas>'+
                        '<div class="hm-sig-controls"><button class="hm-btn hm-btn-outline hm-btn-sm" id="af-sig-clear">Clear</button></div>'+
                    '</div>'+
                '</div>'+
                '<div class="hm-modal-ft"><button class="hm-btn hm-btn-outline hm-modal-x">Cancel</button><button class="hm-btn hm-btn-teal" id="af-save">Save Form</button></div>'+
            '</div></div>'
        );
        // Load form templates
        $.post(_hm.ajax,{action:'hm_get_form_templates',nonce:_hm.nonce},function(r){
            if(r&&r.success&&r.data){
                $('#af-type').empty().append('<option value="">— Select form —</option>');
                r.data.forEach(function(t){$('#af-type').append('<option value="'+t.id+'|'+esc(t.type)+'">'+esc(t.name)+'</option>');});
            }
        });
        // Signature pad
        var sigPad=document.getElementById('af-sig-pad'),ctx=sigPad.getContext('2d'),drawing=false,hasSig=false;
        ctx.strokeStyle='#151B33';ctx.lineWidth=2;ctx.lineCap='round';
        function getPos(e){var r=sigPad.getBoundingClientRect(),src=e.touches?e.touches[0]:e;return{x:src.clientX-r.left,y:src.clientY-r.top};}
        sigPad.addEventListener('mousedown',function(e){drawing=true;var p=getPos(e);ctx.beginPath();ctx.moveTo(p.x,p.y);});
        sigPad.addEventListener('mousemove',function(e){if(!drawing)return;var p=getPos(e);ctx.lineTo(p.x,p.y);ctx.stroke();hasSig=true;});
        sigPad.addEventListener('mouseup',function(){drawing=false;});sigPad.addEventListener('mouseleave',function(){drawing=false;});
        sigPad.addEventListener('touchstart',function(e){e.preventDefault();drawing=true;var p=getPos(e);ctx.beginPath();ctx.moveTo(p.x,p.y);});
        sigPad.addEventListener('touchmove',function(e){e.preventDefault();if(!drawing)return;var p=getPos(e);ctx.lineTo(p.x,p.y);ctx.stroke();hasSig=true;});
        sigPad.addEventListener('touchend',function(){drawing=false;});
        $('#af-sig-clear').on('click',function(){ctx.clearRect(0,0,sigPad.width,sigPad.height);hasSig=false;});
        $('.hm-modal-x').on('click',closeModal);
        $('#hm-modal-overlay').on('click',function(e){if(e.target===this)closeModal();});
        $('#af-save').on('click',function(){
            var typeval=$('#af-type').val();if(!typeval){toast('Select a form type','error');return;}
            if(!$('#af-gdpr').is(':checked')){toast('GDPR consent is required','error');return;}
            var parts=typeval.split('|'),ftype=parts[1]||parts[0];
            var sigData=hasSig?sigPad.toDataURL('image/png'):'';
            $(this).prop('disabled',true).text('Saving…');
            $.post(_hm.ajax,{
                action:'hm_submit_patient_form',nonce:_hm.nonce,patient_id:pid,
                form_type:ftype,form_data:'{}',
                gdpr_consent:$('#af-gdpr').is(':checked')?1:0,
                marketing_email:$('#af-memail').is(':checked')?1:0,
                marketing_sms:$('#af-msms').is(':checked')?1:0,
                marketing_phone:$('#af-mphone').is(':checked')?1:0,
                signature_data:sigData
            },function(r){
                closeModal();
                if(r.success){toast('Form saved');if(cb)cb();}
                else{toast(r.data||'Error','error');}
            });
        });
    }

    /* ── NOTIFICATIONS PANEL (in Overview) ── */
    function loadNotifications($c){
        var h='<div class="hm-card hm-notif-card" style="margin-top:20px;">'+
            '<div class="hm-notif-head">'+
                '<span class="hm-notif-title">Notifications &amp; Reminders</span>'+
                '<button class="hm-btn hm-btn-outline hm-btn-sm" id="hm-add-notif-btn">+ Add Reminder</button>'+
            '</div>'+
            '<div class="hm-card-body hm-notif-body" id="hm-notif-list"><div class="hm-notif-loading">Loading…</div></div>'+
        '</div>';
        $c.append(h);
        $c.on('click','#hm-add-notif-btn',function(){showAddNotifModal(function(){loadNotifications($('#hm-tab-content'));});});
    }

    function showAddNotifModal(cb){
        if($('#hm-modal-overlay').length)return;
        $('body').append(
            '<div id="hm-modal-overlay" class="hm-modal-bg">'+
            '<div class="hm-modal" style="max-width:460px;">'+
                '<div class="hm-modal-hd"><span>Add Reminder</span><button class="hm-modal-x">&times;</button></div>'+
                '<div class="hm-modal-body">'+
                    '<div class="hm-form-row">'+
                        '<div class="hm-form-group"><label class="hm-label">Type</label>'+
                        '<select class="hm-dd" id="notif-type"><option>Phone Call</option><option>Follow-up</option><option>Annual Review</option><option>Custom</option></select></div>'+
                        '<div class="hm-form-group"><label class="hm-label">Priority</label>'+
                        '<select class="hm-dd" id="notif-priority"><option value="normal">Normal</option><option value="yellow">Yellow</option><option value="red">Red</option><option value="green">Green</option></select></div>'+
                    '</div>'+
                    '<div class="hm-form-group"><label class="hm-label">Message *</label><textarea class="hm-textarea" id="notif-msg" rows="3"></textarea></div>'+
                    '<div class="hm-form-row">'+
                        '<div class="hm-form-group"><label class="hm-label">Scheduled date</label><input type="date" class="hm-inp" id="notif-date" value="'+new Date().toISOString().split('T')[0]+'"></div>'+
                        '<div class="hm-form-group"><label class="hm-label">Assign to (dispenser)</label><select class="hm-dd" id="notif-assign"><option value="">— Myself —</option></select></div>'+
                    '</div>'+
                '</div>'+
                '<div class="hm-modal-ft"><button class="hm-btn hm-btn-outline hm-modal-x">Cancel</button><button class="hm-btn hm-btn-teal" id="notif-save">Save Reminder</button></div>'+
            '</div></div>'
        );
        $.post(_hm.ajax,{action:'hm_get_dispensers',nonce:_hm.nonce},function(r){if(r.success)r.data.forEach(function(d){$('#notif-assign').append('<option value="'+d.id+'">'+esc(d.name)+'</option>');});});
        $('.hm-modal-x').on('click',closeModal);
        $('#hm-modal-overlay').on('click',function(e){if(e.target===this)closeModal();});
        $('#notif-save').on('click',function(){
            var msg=$.trim($('#notif-msg').val());if(!msg){toast('Message required','error');return;}
            $(this).prop('disabled',true).text('Saving…');
            $.post(_hm.ajax,{action:'hm_create_patient_notification',nonce:_hm.nonce,patient_id:pid,notification_type:$('#notif-type').val(),message:msg,scheduled_date:$('#notif-date').val(),assigned_user_id:$('#notif-assign').val()||0,priority:$('#notif-priority').val()},function(r){
                closeModal();
                if(r.success){toast('Reminder saved');if(cb)cb();}
                else toast(r.data||'Error','error');
            });
        });
    }

} // end initProfile

})(jQuery);
