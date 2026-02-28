/**
 * HearMed Calendar v3.2 — Exclusion instances, calendar rendering, picker improvements
 * ─────────────────────────────────────────────────────
 * Renders views based on #hm-app[data-view]
 *   calendar  → Cal
 *   settings  → Settings
 *   blockouts → Blockouts
 *   holidays  → Holidays
 *
 * v3.0 changes:
 *   • Multi-select clinic/dispenser filters (click-to-highlight)
 *   • 1-second hover tooltip on appointment cards
 *   • Outcome banners on completed appointments
 *   • Fixed popover (was broken in v2)
 *   • Removed cog panel from calendar view
 */
(function($){
'use strict';

var DAYS=['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
var MO=['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
function pad(n){return String(n).padStart(2,'0');}
function fmt(d){return d.getFullYear()+'-'+pad(d.getMonth()+1)+'-'+pad(d.getDate());}
function isToday(d){var t=new Date();return d.getDate()===t.getDate()&&d.getMonth()===t.getMonth()&&d.getFullYear()===t.getFullYear();}
function esc(s){if(!s)return'';var d=document.createElement('div');d.textContent=s;return d.innerHTML;}
function post(action,data){data=data||{};data.action='hm_'+action;data.nonce=HM.nonce;return $.post(HM.ajax_url,data);}

// ═══ SVG Icons ═══
var IC={
    chevL:'<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="m15 18-6-6 6-6"/></svg>',
    chevR:'<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 18 6-6-6-6"/></svg>',
    chevDown:'<svg viewBox="0 0 24 24" width="10" height="10" fill="none" stroke="currentColor" stroke-width="2.5"><path d="m6 9 6 6 6-6"/></svg>',
    plus:'<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 5v14M5 12h14"/></svg>',
    print:'<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>',
    cal:'<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>',
    user:'<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>',
    clock:'<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>',
    x:'<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12"/></svg>',
    cog:'<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/></svg>',
    dots:'<svg viewBox="0 0 24 24" width="14" height="14" fill="currentColor"><circle cx="12" cy="5" r="1.5"/><circle cx="12" cy="12" r="1.5"/><circle cx="12" cy="19" r="1.5"/></svg>',
    note:'<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6"/><path d="M16 13H8"/><path d="M16 17H8"/></svg>',
    pound:'<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 20H6c0-4 2-8 2-12a4 4 0 0 1 8 0"/><path d="M6 14h8"/></svg>',
    edit:'<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 3a2.85 2.85 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/></svg>',
    trash:'<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>',
};

// Status pill colours — defaults, overridden by calendar settings
var STATUS_MAP_DEFAULTS={
    'Not Confirmed':{bg:'#fefce8',color:'#854d0e',border:'#fde68a'},
    Confirmed:{bg:'#eff6ff',color:'#1e40af',border:'#bfdbfe'},
    Arrived:{bg:'#ecfdf5',color:'#065f46',border:'#a7f3d0'},
    'In Progress':{bg:'#fff7ed',color:'#9a3412',border:'#fed7aa'},
    Completed:{bg:'#f9fafb',color:'#6b7280',border:'#e5e7eb'},
    'No Show':{bg:'#fef2f2',color:'#991b1b',border:'#fecaca'},
    Late:{bg:'#fffbeb',color:'#92400e',border:'#fde68a'},
    Pending:{bg:'#f5f3ff',color:'#5b21b6',border:'#ddd6fe'},
    Cancelled:{bg:'#fef2f2',color:'#991b1b',border:'#fecaca'},
    Rescheduled:{bg:'#f0f9ff',color:'#0c4a6e',border:'#bae6fd'},
};
var STATUS_MAP=STATUS_MAP_DEFAULTS;

// Status card style defaults for Cancelled / No Show / Rescheduled
var SCS_DEFAULTS={
    Cancelled:{pattern:'striped',overlayColor:'#ef4444',overlayOpacity:10,label:'CANCELLED',labelColor:'#7f1d1d',labelSize:8,contentOpacity:35,halfWidth:true},
    'No Show':{pattern:'striped',overlayColor:'#f59e0b',overlayOpacity:8,label:'',labelColor:'#92400e',labelSize:8,contentOpacity:35,halfWidth:false},
    Rescheduled:{pattern:'striped',overlayColor:'#0e7490',overlayOpacity:10,label:'Rescheduled',labelColor:'#155e75',labelSize:8,contentOpacity:35,halfWidth:true}
};
var SCS_MAP=SCS_DEFAULTS;

// ═══ ROUTER ═══
var App={
    init:function(){
        var $el=$('#hm-app');
        if(!$el.length)return;
        var v=$el.data('view')||$el.attr('data-view')||'';
        // Fallback: if #hm-calendar-container exists, this is the calendar page
        if(!v && $('#hm-calendar-container').length) v='calendar';
        if(v==='calendar')Cal.init($el);
        else if(v==='settings')Settings.init($el);
        else if(v==='blockouts')Blockouts.init($el);
        else if(v==='holidays')Holidays.init($el);
    }
};

// ═══════════════════════════════════════════════════════
// CALENDAR VIEW — v3.1
// ═══════════════════════════════════════════════════════
var Cal={
    $el:null,date:new Date(),mode:'week',viewMode:'people',
    dispensers:[],services:[],clinics:[],appts:[],holidays:[],blockouts:[],exclusionTypes:[],
    selClinics:[],selDisps:[],svcMap:{},cfg:{},
    _hoverTimer:null,_popAppt:null,

    init:function($el){
        this.$el=$el;
        var s=HM.settings||{};
        var bv=function(v,def){if(v===true||v==='1'||v==='yes'||v==='t')return true;if(v===false||v==='0'||v==='no'||v==='f'||v===null)return false;return def;};
        this.cfg={
            slotMin:parseInt(s.time_interval)||30,
            startH:parseInt((s.start_time||'09:00').split(':')[0]),
            endH:parseInt((s.end_time||'18:00').split(':')[0]),
            slotHt:s.slot_height||'regular',
            showTimeInline:bv(s.show_time_inline,false),
            hideEndTime:bv(s.hide_end_time,true),
            outcomeStyle:s.outcome_style||'default',
            hideCancelled:bv(s.hide_cancelled,true),
            displayFull:bv(s.display_full_name,false),
            enabledDays:(s.enabled_days||'mon,tue,wed,thu,fri').split(','),
            // Card appearance
            cardStyle:s.card_style||'solid',
            bannerStyle:s.banner_style||'default',
            bannerSize:s.banner_size||'default',
            // Card content toggles
            showApptType:bv(s.show_appointment_type,true),
            showTime:bv(s.show_time,true),
            showClinic:bv(s.show_clinic,false),
            showDispIni:bv(s.show_dispenser_initials,true),
            showStatusBadge:bv(s.show_status_badge,true),
            showBadges:bv(s.show_badges,true),
            // Card colours
            apptBg:s.appt_bg_color||'#0BB4C4',
            apptFont:s.appt_font_color||'#ffffff',
            apptName:s.appt_name_color||'#ffffff',
            apptTime:s.appt_time_color||'#38bdf8',
            apptBadge:s.appt_badge_color||'#3b82f6',
            apptBadgeFont:s.appt_badge_font_color||'#ffffff',
            apptMeta:s.appt_meta_color||'#38bdf8',
            borderColor:s.border_color||'',
            tintOpacity:parseInt(s.tint_opacity)||12,
            // Calendar theme
            indicatorColor:s.indicator_color||'#00d59b',
            todayHighlight:s.today_highlight_color||'#e6f7f9',
            gridLineColor:s.grid_line_color||'#e2e8f0',
            calBg:s.cal_bg_color||'#ffffff',
            workingDays:(s.working_days||'1,2,3,4,5').split(',').map(function(x){return parseInt(x.trim());}),
            // Card typography
            cardFontFamily:s.card_font_family||'Plus Jakarta Sans',
            cardFontSize:parseInt(s.card_font_size)||11,
            cardFontWeight:parseInt(s.card_font_weight)||600,
            outcomeFontFamily:s.outcome_font_family||'Plus Jakarta Sans',
            outcomeFontSize:parseInt(s.outcome_font_size)||9,
            outcomeFontWeight:parseInt(s.outcome_font_weight)||600,
        };
        // Override STATUS_MAP with saved per-status badge colours
        if(s.status_badge_colours&&typeof s.status_badge_colours==='object'){
            STATUS_MAP={};
            for(var k in STATUS_MAP_DEFAULTS){STATUS_MAP[k]=STATUS_MAP_DEFAULTS[k];}
            for(var k2 in s.status_badge_colours){
                if(s.status_badge_colours[k2]&&typeof s.status_badge_colours[k2]==='object'){
                    STATUS_MAP[k2]=s.status_badge_colours[k2];
                }
            }
        }
        // Override SCS_MAP with saved status card styles
        if(s.status_card_styles&&typeof s.status_card_styles==='object'){
            SCS_MAP={};
            for(var sk in SCS_DEFAULTS){SCS_MAP[sk]=SCS_DEFAULTS[sk];}
            for(var sk2 in s.status_card_styles){
                if(s.status_card_styles[sk2]&&typeof s.status_card_styles[sk2]==='object'){
                    SCS_MAP[sk2]=s.status_card_styles[sk2];
                }
            }
        }
        this.mode=s.default_view||'week';
        this.cfg.totalSlots=Math.ceil((this.cfg.endH-this.cfg.startH)*60/this.cfg.slotMin);
        this.render();
        this.bind();
        this.loadData();
    },

    render:function(){
        this.$el.html(
        '<div class="hm-cal-wrap">'+
            '<div class="hm-toolbar">'+
                '<div class="hm-tb-left">'+
                    '<button class="hm-nav-btn" id="hm-prev">'+IC.chevL+'</button>'+
                    '<button class="hm-nav-btn" id="hm-next">'+IC.chevR+'</button>'+
                    '<div class="hm-date-box" id="hm-dateBox">'+IC.cal+' <span id="hm-dateLbl"></span><input type="date" id="hm-datePick" style="position:absolute;opacity:0;width:1px;height:1px;"></div>'+
                '</div>'+
                '<div class="hm-tb-right">'+
                    '<div class="hm-view-tog"><button class="hm-view-btn" data-v="day">Day</button><button class="hm-view-btn" data-v="week">Week</button></div>'+
                    '<button class="hm-icon-btn" onclick="window.print()" title="Print">'+IC.print+'</button>'+
                    '<div class="hm-sep"></div>'+
                    /* Multi-select clinic */
                    '<div class="hm-ms" id="hm-clinicMs">'+
                        '<button class="hm-ms-btn" id="hm-clinicMsBtn"><span class="hm-ms-lbl">All Clinics</span><span class="hm-ms-chev">'+IC.chevDown+'</span></button>'+
                        '<div class="hm-ms-drop" id="hm-clinicMsDrop"></div>'+
                    '</div>'+
                    /* Multi-select dispenser */
                    '<div class="hm-ms" id="hm-dispMs">'+
                        '<button class="hm-ms-btn" id="hm-dispMsBtn"><span class="hm-ms-lbl">All Assignees</span><span class="hm-ms-chev">'+IC.chevDown+'</span></button>'+
                        '<div class="hm-ms-drop" id="hm-dispMsDrop"></div>'+
                    '</div>'+
                    '<div style="position:relative"><button class="hm-plus-btn" id="hm-plusBtn">'+IC.plus+'</button>'+
                        '<div class="hm-plus-menu" id="hm-plusMenu">'+
                            '<div class="hm-plus-item" data-act="appointment">'+IC.cal+' Appointment</div>'+
                            '<div class="hm-plus-item" data-act="patient">'+IC.user+' Patient</div>'+
                            '<div class="hm-plus-item" data-act="holiday">'+IC.clock+' Holiday / Unavailability</div>'+
                        '</div>'+
                    '</div>'+
                '</div>'+
            '</div>'+
            '<div class="hm-grid-wrap" id="hm-gridWrap"></div>'+
        '</div>'+
        '<div class="hm-pop" id="hm-pop"></div>'+
        '<div class="hm-tooltip" id="hm-tooltip"></div>'
        );
    },

    bind:function(){
        var self=this;
        $(document).on('click','#hm-prev',function(){self.nav(-1);});
        $(document).on('click','#hm-next',function(){self.nav(1);});
        $(document).on('click','#hm-dateBox',function(){var dp=$('#hm-datePick');dp[0].showPicker?dp[0].showPicker():dp.trigger('click');});
        $(document).on('change','#hm-datePick',function(){var v=$(this).val();if(v){self.date=new Date(v+'T12:00:00');self.refresh();}});
        $(document).on('click','.hm-view-btn',function(){self.mode=$(this).data('v');self.refreshUI();});

        // Multi-select toggles
        $(document).on('click','#hm-clinicMsBtn',function(e){e.stopPropagation();$('#hm-clinicMsDrop').toggleClass('open');$('#hm-dispMsDrop').removeClass('open');});
        $(document).on('click','#hm-dispMsBtn',function(e){e.stopPropagation();$('#hm-dispMsDrop').toggleClass('open');$('#hm-clinicMsDrop').removeClass('open');});
        $(document).on('click','.hm-ms-item',function(e){
            e.stopPropagation();
            var $t=$(this),id=parseInt($t.data('id')),group=$t.data('group');
            if(id===0){
                // "All" clicked — clear selection
                if(group==='clinic'){self.selClinics=[];}else{self.selDisps=[];}
            } else {
                var arr=group==='clinic'?self.selClinics:self.selDisps;
                var idx=arr.indexOf(id);
                if(idx>-1)arr.splice(idx,1); else arr.push(id);
                if(group==='clinic')self.selClinics=arr; else self.selDisps=arr;
            }
            self.renderMultiSelect();
            if(group==='clinic')self.loadDispensers().then(function(){self.refresh();});
            else self.refresh();
        });

        // Close dropdowns on outside click
        $(document).on('click',function(){$('.hm-ms-drop').removeClass('open');$('#hm-plusMenu').removeClass('open');$('.hm-ctx-menu').remove();});
        $(document).on('click','#hm-plusBtn',function(e){e.stopPropagation();$('#hm-plusMenu').toggleClass('open');$('.hm-ms-drop').removeClass('open');});
        $(document).on('click','.hm-plus-item',function(){$('#hm-plusMenu').removeClass('open');self.onPlusAction($(this).data('act'));});

        // Popover close
        $(document).on('click',function(e){if(!$(e.target).closest('.hm-pop,.hm-appt,.hm-ctx-menu').length)$('#hm-pop').removeClass('open');});
        $(document).on('click','.hm-pop-x',function(){$('#hm-pop').removeClass('open');});
        $(document).on('click','.hm-pop-edit',function(){self.editPop();});

        // ── Kebab (3-dot) context menu ──
        $(document).on('click','.hm-appt-kebab',function(e){
            e.stopPropagation();e.preventDefault();
            clearTimeout(Cal._hoverTimer);$('#hm-tooltip').hide();$('#hm-pop').removeClass('open');
            $('.hm-ctx-menu').remove();
            var $btn=$(this),aid=parseInt($btn.data('id'));
            var a=Cal.appts.find(function(x){return x._ID===aid||x.id===aid;});
            if(!a)return;
            Cal._popAppt=a;
            var rect=$btn[0].getBoundingClientRect();
            var menuW=190,menuH=220,subW=170;
            var spaceRight=window.innerWidth-rect.left;
            var spaceBelow=window.innerHeight-rect.bottom;
            var left=spaceRight<menuW+subW?rect.right-menuW:rect.left;
            var top=spaceBelow<menuH?rect.top-menuH:rect.bottom+4;
            var flipSub=spaceRight<menuW+subW+10?'hm-ctx-flip':'';
            var m='<div class="hm-ctx-menu '+flipSub+'" style="left:'+left+'px;top:'+top+'px">';
            var aLocked=a.status==='Completed'||!!(a.outcome_name);
            if(aLocked){
                // Locked appointment — view only
                m+='<div class="hm-ctx-item hm-ctx-edit">'+IC.edit+' View Details</div>';
                m+='<div class="hm-ctx-sep"></div>';
                m+='<div class="hm-ctx-item" style="opacity:0.4;cursor:default;font-size:11px;color:#059669">Appointment closed — read only</div>';
            } else {
            // Status submenu
            m+='<div class="hm-ctx-parent">';
            m+='<div class="hm-ctx-item hm-ctx-has-sub">'+IC.clock+' Status <span class="hm-ctx-arrow">›</span></div>';
            m+='<div class="hm-ctx-sub">';
            ['Not Confirmed','Confirmed','Arrived','Late','Rescheduled','Cancelled'].forEach(function(s){
                var active=a.status===s?' hm-ctx-active':'';
                var st=STATUS_MAP[s]||STATUS_MAP['Not Confirmed'];
                m+='<div class="hm-ctx-item hm-ctx-status'+active+'" data-status="'+s+'">';
                m+='<span class="hm-ctx-dot" style="background:'+st.color+'"></span>'+s+'</div>';
            });
            m+='</div></div>';
            // Quick Add Notes
            m+='<div class="hm-ctx-sep"></div>';
            m+='<div class="hm-ctx-item hm-ctx-notes">'+IC.note+' Quick Add Notes</div>';
            // Quick Payment
            m+='<div class="hm-ctx-item hm-ctx-payment" style="opacity:0.5;cursor:default">'+IC.pound+' Quick Payment</div>';
            // Edit Appointment
            m+='<div class="hm-ctx-sep"></div>';
            m+='<div class="hm-ctx-item hm-ctx-edit">'+IC.edit+' Edit Appointment</div>';
            // Delete Appointment — admin / c-level / finance only
            if(HM.is_admin){
                m+='<div class="hm-ctx-sep"></div>';
                m+='<div class="hm-ctx-item hm-ctx-delete" style="color:#dc2626">'+IC.trash+' Delete Appointment</div>';
            }
            }
            m+='</div>';
            $('body').append(m);
        });

        // Context menu → status
        $(document).on('click','.hm-ctx-status',function(e){
            e.stopPropagation();
            var status=$(this).data('status'),a=Cal._popAppt;
            if(!a)return;
            $('.hm-ctx-menu').remove();
            // Statuses that need no prompt
            if(status==='Not Confirmed'||status==='Confirmed'||status==='Arrived'){
                Cal.doStatusChange(a,status,'');
            }
            // Late → prompt note
            else if(status==='Late'){
                Cal.openNoteModal(a,status,'Why is the patient running late?');
            }
            // Rescheduled → note + new date/time
            else if(status==='Rescheduled'){
                Cal.openRescheduleModal(a);
            }
            // Cancelled → prompt note
            else if(status==='Cancelled'){
                Cal.openNoteModal(a,status,'Reason for cancellation:');
            }
        });

        // Context menu → quick notes
        $(document).on('click','.hm-ctx-notes',function(e){
            e.stopPropagation();$('.hm-ctx-menu').remove();
            var a=Cal._popAppt;if(!a)return;
            Cal.openQuickNoteModal(a);
        });

        // Context menu → edit
        $(document).on('click','.hm-ctx-edit',function(e){
            e.stopPropagation();$('.hm-ctx-menu').remove();
            Cal.editPop();
        });

        // Context menu → delete (admin only, permanent)
        $(document).on('click','.hm-ctx-delete',function(e){
            e.stopPropagation();$('.hm-ctx-menu').remove();
            var a=Cal._popAppt;if(!a)return;
            if(!confirm('Permanently delete this appointment for '+a.patient_name+'?\n\nThis action cannot be undone — no record will remain.'))return;
            post('purge_appointment',{appointment_id:a._ID}).then(function(r){
                if(r.success){Cal.toast('Appointment permanently deleted');Cal.refresh();}
                else{alert(r.data&&r.data.message?r.data.message:'Delete failed.');}
            }).fail(function(){alert('Network error.');});
        });

        // Slot double-click
        $(document).on('dblclick','.hm-slot',function(){self.onSlot(this);});

        // Delete exclusion from kebab menu
        $(document).on('click','.hm-excl-del',function(e){
            e.stopPropagation();
            var eid=$(this).data('eid');
            $('.hm-excl-ctx').remove();
            if(!confirm('Delete this exclusion?'))return;
            post('delete_exclusion',{id:eid}).then(function(r){
                if(r.success){self.refresh();}
                else alert(r.data&&r.data.message?r.data.message:'Delete failed.');
            }).fail(function(){alert('Network error.');});
        });

        // Exclusion kebab menu
        $(document).on('click','.hm-excl-kebab',function(e){
            e.stopPropagation();e.preventDefault();
            $('.hm-excl-ctx').remove();
            var $btn=$(this),eid=$btn.data('excl-id');
            var ex=Cal.exclusions.find(function(x){return parseInt(x.id)===parseInt(eid);});
            if(!ex)return;
            var rect=$btn[0].getBoundingClientRect();
            var m='<div class="hm-excl-ctx" style="position:fixed;z-index:9999;background:#fff;border:1px solid #e2e8f0;border-radius:8px;box-shadow:0 8px 24px rgba(0,0,0,0.12);padding:6px 0;min-width:160px;font-size:13px;font-family:var(--hm-font,\'Source Sans 3\',sans-serif);left:'+(rect.right+4)+'px;top:'+rect.top+'px">';
            m+='<div class="hm-excl-ctx-item hm-excl-edit" data-eid="'+eid+'" style="padding:8px 14px;cursor:pointer;display:flex;align-items:center;gap:8px">'+IC.edit+' Edit</div>';
            m+='<div class="hm-excl-ctx-item hm-excl-del" data-eid="'+eid+'" style="padding:8px 14px;cursor:pointer;display:flex;align-items:center;gap:8px;color:#dc2626">'+IC.trash+' Delete</div>';
            m+='</div>';
            $('body').append(m);
            setTimeout(function(){$(document).one('click.exclctx',function(){$('.hm-excl-ctx').remove();});},10);
        });

        // Edit exclusion from kebab menu
        $(document).on('click','.hm-excl-edit',function(e){
            e.stopPropagation();
            var eid=$(this).data('eid');
            $('.hm-excl-ctx').remove();
            var ex=Cal.exclusions.find(function(x){return parseInt(x.id)===parseInt(eid);});
            if(!ex)return;
            Cal.openEditExclusionModal(ex);
        });

        // Popover status actions
        $(document).on('click','.hm-pop-status',function(){
            var status=$(this).data('status'),a=self._popAppt;
            if(!a)return;
            post('update_appointment',{appointment_id:a._ID,status:status}).then(function(r){
                if(r.success){$('#hm-pop').removeClass('open');self.refresh();}
            });
        });

        // Close Off — show outcome selection
        $(document).on('click','.hm-pop-closeoff',function(){
            var sid=$(this).data('sid'),aid=$(this).data('aid');
            $('#hm-pop-actions').hide();
            $('#hm-pop-outcome-area').show();
            self._selectedOutcome=null;
            post('get_outcome_templates',{service_id:sid}).then(function(r){
                if(!r.success||!r.data||!r.data.length){
                    $('#hm-pop-outcome-list').html('<div style="color:#94a3b8;font-size:12px;padding:8px 0">No outcomes configured for this appointment type.<br><span style="font-size:11px">Add outcomes in Admin → Appointment Types → Edit.</span></div>');
                    return;
                }
                var oh='';
                r.data.forEach(function(o){
                    oh+='<button class="hm-outcome-opt" data-oid="'+o.id+'" data-color="'+esc(o.outcome_color)+'" data-name="'+esc(o.outcome_name)+'" data-note="'+(o.requires_note?'1':'0')+'" style="display:flex;align-items:center;gap:8px;padding:6px 10px;border:1.5px solid #e2e8f0;border-radius:6px;background:#fff;cursor:pointer;font-size:12px;font-weight:600;color:#334155;transition:all .15s">';
                    oh+='<span style="width:12px;height:12px;border-radius:3px;background:'+esc(o.outcome_color)+';flex-shrink:0"></span>';
                    oh+=esc(o.outcome_name);
                    if(o.is_invoiceable)oh+='<span style="margin-left:auto;font-size:9px;background:#dbeafe;color:#1e40af;padding:1px 5px;border-radius:3px">£</span>';
                    oh+='</button>';
                });
                $('#hm-pop-outcome-list').html(oh);
            }).fail(function(){
                $('#hm-pop-outcome-list').html('<div style="color:#ef4444;font-size:12px">Failed to load outcomes</div>');
            });
        });

        // Outcome option click
        $(document).on('click','.hm-outcome-opt',function(){
            $('.hm-outcome-opt').css({borderColor:'#e2e8f0',background:'#fff'});
            var $o=$(this);
            $o.css({borderColor:$o.data('color'),background:$o.data('color')+'15'});
            self._selectedOutcome={id:$o.data('oid'),color:$o.data('color'),name:$o.data('name')};
            $('.hm-pop-outcome-save').prop('disabled',false);
            if($o.data('note')==='1'||$o.data('note')===1){$('#hm-pop-outcome-note').show();}else{$('#hm-pop-outcome-note').hide();}
        });

        // Outcome cancel
        $(document).on('click','.hm-pop-outcome-cancel',function(){
            $('#hm-pop-outcome-area').hide();
            $('#hm-pop-actions').show();
            self._selectedOutcome=null;
        });

        // Save outcome
        $(document).on('click','.hm-pop-outcome-save',function(){
            var o=self._selectedOutcome,a=self._popAppt;
            if(!o||!a)return;
            var $btn=$(this);$btn.prop('disabled',true).text('Saving...');
            post('save_appointment_outcome',{
                appointment_id:a._ID,
                outcome_id:o.id,
                outcome_note:$('#hm-outcome-note').val()||''
            }).then(function(r){
                if(r.success){
                    $('#hm-pop').removeClass('open');
                    self.refresh();
                } else {
                    $btn.prop('disabled',false).text('Save Outcome');
                    alert(r.data&&r.data.message?r.data.message:'Failed to save outcome');
                }
            }).fail(function(){$btn.prop('disabled',false).text('Save Outcome');alert('Network error');});
        });

        // Resize
        var rt;$(window).on('resize',function(){clearTimeout(rt);rt=setTimeout(function(){self.refresh();},150);});
        $(document).on('keydown',function(e){if(e.key==='Escape'){$('#hm-pop').removeClass('open');$('#hm-tooltip').hide();$('.hm-ctx-menu').remove();}});
    },

    // ── Data loading (parallel, fault-tolerant) ──
    loadData:function(){
        var self=this;
        $.when(
            this.loadClinics(),
            this.loadDispensers(),
            this.loadServices(),
            this.loadExclusionTypes(),
            this.loadHolidays()
        ).always(function(){ self.refresh(); });
    },
    loadHolidays:function(){
        return post('get_holidays').then(function(r){
            if(r.success) Cal.holidays=r.data||[];
        }).fail(function(){ Cal.holidays=[]; });
    },
    loadExclusions:function(){
        var dates=this.visDates();
        if(!dates.length)return $.Deferred().resolve();
        return post('get_exclusions',{start_date:fmt(dates[0]),end_date:fmt(dates[dates.length-1])}).then(function(r){
            if(r.success) Cal.exclusions=r.data||[];
            else Cal.exclusions=[];
        }).fail(function(){ Cal.exclusions=[]; });
    },
    /* Check if a dispenser is on holiday/unavailable for a given date */
    isDispOnHoliday:function(dispId,date){
        var ds=fmt(date);
        return this.holidays.some(function(h){
            if(parseInt(h.dispenser_id)!==parseInt(dispId)) return false;
            if(h.repeats&&h.repeats!=='no'){
                /* For repeating holidays, check if the day-of-year / week / month matches */
                var sd=new Date(h.start_date+'T00:00:00'), ed=new Date(h.end_date+'T00:00:00');
                if(h.repeats==='yearly'){
                    var m=date.getMonth(), d2=date.getDate();
                    var sm=sd.getMonth(), sd2=sd.getDate(), em=ed.getMonth(), ed2=ed.getDate();
                    return (m>sm||(m===sm&&d2>=sd2))&&(m<em||(m===em&&d2<=ed2));
                }
                if(h.repeats==='weekly'){
                    var dow=date.getDay(), sdow=sd.getDay(), edow=ed.getDay();
                    return dow>=sdow&&dow<=edow;
                }
                if(h.repeats==='monthly'){
                    var dd=date.getDate();
                    return dd>=sd.getDate()&&dd<=ed.getDate();
                }
            }
            return ds>=h.start_date&&ds<=h.end_date;
        });
    },
    loadClinics:function(){
        return post('get_clinics').then(function(r){
            if(!r.success)return;
            Cal.clinics=r.data;
            Cal.renderMultiSelect();
        }).fail(function(){ console.warn('[HearMed] get_clinics failed'); });
    },
    loadDispensers:function(){
        return post('get_dispensers',{clinic:0,date:fmt(this.date)}).then(function(r){
            if(!r.success)return;
            Cal.dispensers=r.data;
            Cal.renderMultiSelect();
        }).fail(function(){ console.warn('[HearMed] get_dispensers failed'); });
    },
    loadServices:function(){
        return post('get_services').then(function(r){
            if(!r.success)return;
            Cal.services=r.data;Cal.svcMap={};
            r.data.forEach(function(s){Cal.svcMap[s.id]=s;});
        }).fail(function(){ console.warn('[HearMed] get_services failed'); });
    },
    loadExclusionTypes:function(){
        return post('get_exclusion_types').then(function(r){
            if(r.success) Cal.exclusionTypes=r.data||[];
        }).fail(function(){ Cal.exclusionTypes=[]; });
    },
    loadAppts:function(){
        var dates=this.visDates();
        return post('get_appointments',{start:fmt(dates[0]),end:fmt(dates[dates.length-1]),clinic:0})
            .then(function(r){
                console.log('[HearMed] get_appointments response:', r.success, 'count:', r.data?r.data.length:0, r.data);
                if(r.success)Cal.appts=r.data;
            });
    },

    // ── Multi-select rendering ──
    renderMultiSelect:function(){
        // Clinics
        var ch='<div class="hm-ms-item'+(this.selClinics.length===0?' on':'')+'" data-id="0" data-group="clinic">All Clinics</div>';
        this.clinics.forEach(function(c){
            var on=Cal.selClinics.indexOf(c.id)>-1;
            ch+='<div class="hm-ms-item'+(on?' on':'')+'" data-id="'+c.id+'" data-group="clinic"><span class="hm-ms-dot" style="background:'+(on?'#fff':c.color)+'"></span>'+esc(c.name)+'</div>';
        });
        $('#hm-clinicMsDrop').html(ch);
        var cLbl=this.selClinics.length===0?'All Clinics':this.selClinics.length===1?this.clinics.find(function(c){return c.id===Cal.selClinics[0];})?.name||'1 selected':this.selClinics.length+' selected';
        $('#hm-clinicMsBtn .hm-ms-lbl').text(cLbl);

        // Dispensers — filtered by selected clinics
        var filtDisp=this.dispensers;
        if(this.selClinics.length){
            filtDisp=this.dispensers.filter(function(d){return Cal.selClinics.indexOf(parseInt(d.clinic_id||d.clinicId))>-1;});
        }
        var dh='<div class="hm-ms-item'+(this.selDisps.length===0?' on':'')+'" data-id="0" data-group="disp">All Assignees</div>';
        filtDisp.forEach(function(d){
            var on=Cal.selDisps.indexOf(parseInt(d.id))>-1;
            dh+='<div class="hm-ms-item'+(on?' on':'')+'" data-id="'+d.id+'" data-group="disp">'+esc(d.initials)+' — '+esc(d.name)+'</div>';
        });
        $('#hm-dispMsDrop').html(dh);
        var dLbl=this.selDisps.length===0?'All Assignees':this.selDisps.length===1?(function(){var dd=Cal.dispensers.find(function(x){return parseInt(x.id)===Cal.selDisps[0];});return dd?dd.name:'1 selected';})():this.selDisps.length+' selected';
        $('#hm-dispMsBtn .hm-ms-lbl').text(dLbl);
    },

    // ── Refresh ──
    refresh:function(){var self=this;$.when(this.loadAppts(),this.loadExclusions()).then(function(){self.renderGrid();self.renderExclusions();self.renderAppts();self.renderNow();});},
    refreshUI:function(){this.renderGrid();this.renderAppts();this.renderNow();this.updateViewBtns();},
    updateViewBtns:function(){$('.hm-view-btn').removeClass('on');$('.hm-view-btn[data-v="'+this.mode+'"]').addClass('on');},
    nav:function(dir){this.date.setDate(this.date.getDate()+(this.mode==='week'?dir*7:dir));$('#hm-pop').removeClass('open');$('#hm-tooltip').hide();this.refresh();},

    visDates:function(){
        var d=new Date(this.date);
        if(this.mode==='day')return[new Date(d)];
        var day=d.getDay();var diff=d.getDate()-day+(day===0?-6:1);
        var mon=new Date(d);mon.setDate(diff);
        var dayMap={mon:1,tue:2,wed:3,thu:4,fri:5,sat:6,sun:0};
        var en=this.cfg.enabledDays.map(function(x){return dayMap[x.trim()]||0;});
        var arr=[];
        for(var i=0;i<7;i++){var dd=new Date(mon);dd.setDate(mon.getDate()+i);if(en.indexOf(dd.getDay())!==-1)arr.push(dd);}
        return arr;
    },
    visDisps:function(){
        var d=this.dispensers;
        if(this.selClinics.length)d=d.filter(function(x){return Cal.selClinics.indexOf(parseInt(x.clinic_id||x.clinicId))>-1;});
        if(this.selDisps.length)d=d.filter(function(x){return Cal.selDisps.indexOf(parseInt(x.id))>-1;});
        return d;
    },
    updateDateLbl:function(dates){
        var s=dates[0],e=dates[dates.length-1];
        var txt=this.mode==='day'?DAYS[s.getDay()]+', '+s.getDate()+' '+MO[s.getMonth()]+' '+s.getFullYear():
            s.getDate()+' '+MO[s.getMonth()]+' – '+e.getDate()+' '+MO[e.getMonth()]+' '+e.getFullYear();
        $('#hm-dateLbl').text(txt);
    },
    // ── GRID ──
    renderGrid:function(){
        var dates=this.visDates(),disps=this.visDisps(),cfg=this.cfg,gw=document.getElementById('hm-gridWrap');
        if(!gw)return;
        this.updateDateLbl(dates);this.updateViewBtns();

        var slotMap={compact:32,regular:40,large:52};
        var slotH=slotMap[cfg.slotHt]||28;
        cfg.slotHpx=slotH;

        if(!disps.length){gw.innerHTML='<div style="text-align:center;padding:80px;color:var(--hm-text-faint);font-size:15px">No dispensers match your filters. Try changing the clinic or assignee filter.</div>';return;}

        var colW=Math.max(80,Math.min(140,Math.floor(900/disps.length)));
        var tc=disps.length*dates.length;

        var h='<div class="hm-grid" style="grid-template-columns:44px repeat('+tc+',minmax('+colW+'px,1fr));--hm-cal-bg:'+(cfg.calBg||'#ffffff')+';--hm-cal-grid:'+(cfg.gridLineColor||'#e2e8f0')+';--hm-cal-today:'+(cfg.todayHighlight||'#e6f7f9')+'">';
        h+='<div class="hm-time-corner"></div>';
        dates.forEach(function(d){
            var td=isToday(d);
            h+='<div class="hm-day-hd'+(td?' today':'')+'" style="grid-column:span '+disps.length+(td?';background:'+cfg.todayHighlight:'')+'">';
            h+='<span class="hm-day-lbl">'+DAYS[d.getDay()]+'</span> <span class="hm-day-num">'+d.getDate()+'</span> <span class="hm-day-lbl">'+MO[d.getMonth()]+'</span>';
            h+='<div class="hm-prov-row">';
            disps.forEach(function(p){
                var lbl=esc(p.initials);
                var onHol=Cal.isDispOnHoliday(p.id,d);
                var dotCls=onHol?'hm-dot hm-dot--red':'hm-dot hm-dot--green';
                h+='<div class="hm-prov-cell"><div class="hm-prov-ini"><span class="'+dotCls+' hm-dot--sm"'+(onHol?' title="On holiday / unavailable"':' title="Available"')+'></span>'+lbl+'</div></div>';
            });
            h+='</div></div>';
        });

        for(var s=0;s<cfg.totalSlots;s++){
            var tm=cfg.startH*60+s*cfg.slotMin;
            var hr=Math.floor(tm/60),mn=tm%60;
            var isHr=mn===0;
            h+='<div class="hm-time-cell'+(isHr?' hr':'')+'">'+(isHr?pad(hr)+':00':'')+'</div>';
            dates.forEach(function(d,di){
                disps.forEach(function(p,pi){
                    var cls='hm-slot'+(isHr?' hr':'')+(pi===disps.length-1?' dl':'');
                    h+='<div class="'+cls+'" data-date="'+fmt(d)+'" data-time="'+pad(hr)+':'+pad(mn)+'" data-disp="'+p.id+'" data-day="'+di+'" data-slot="'+s+'" style="height:'+slotH+'px"></div>';
                });
            });
        }
        h+='</div>';
        gw.innerHTML=h;
    },

    // ── APPOINTMENTS ──
    renderAppts:function(){
        $('.hm-appt').remove();
        var dates=this.visDates(),disps=this.visDisps(),cfg=this.cfg,slotH=cfg.slotHpx;
        if(!disps.length)return;

        var self=this;
        // Collect card metadata for overlap detection
        var cardMeta=[];
        this.appts.forEach(function(a){
            // Filter by multi-select
            if(Cal.selDisps.length&&Cal.selDisps.indexOf(parseInt(a.dispenser_id))===-1)return;
            if(Cal.selClinics.length&&Cal.selClinics.indexOf(parseInt(a.clinic_id))===-1)return;
            // Cancelled/rescheduled are always shown (half-height with overlay text)

            var di=-1;
            for(var i=0;i<dates.length;i++){if(fmt(dates[i])===a.appointment_date){di=i;break;}}
            if(di===-1)return;
            var found=false;
            for(var j=0;j<disps.length;j++){if(parseInt(disps[j].id)===parseInt(a.dispenser_id)){found=true;break;}}
            if(!found)return;

            var tp=a.start_time.split(':');
            var aMn=parseInt(tp[0])*60+parseInt(tp[1]);
            if(aMn<cfg.startH*60||aMn>=cfg.endH*60)return;

            var si=Math.floor((aMn-cfg.startH*60)/cfg.slotMin);
            var off=((aMn-cfg.startH*60)%cfg.slotMin)/cfg.slotMin*slotH;
            var dur=parseInt(a.duration)||parseInt(a.service_duration)||30;
            // Height = number of slots the appointment spans × slot height (px)
            var spanSlots=dur/cfg.slotMin;
            var h=Math.max(slotH*0.8, spanSlots*slotH - 2);

            var $t=$('.hm-slot[data-day="'+di+'"][data-slot="'+si+'"][data-disp="'+a.dispenser_id+'"]');
            if(!$t.length)return;

            var col=a.service_colour||cfg.apptBg||'#3B82F6';
            // Color source: always appointment_type (locked)

            var isCancelled=a.status==='Cancelled';
            var isNoShow=a.status==='No Show';
            var isRescheduled=a.status==='Rescheduled';
            var scs=(isCancelled||isNoShow||isRescheduled)?SCS_MAP[a.status]||SCS_DEFAULTS[a.status]||null:null;
            var stCls=isCancelled?' cancelled':isNoShow?' noshow':isRescheduled?' rescheduled':'';


            var tmLbl=cfg.showTimeInline?(a.start_time.substring(0,5)+' '):'';
            var outcomeColor=a.outcome_banner_colour||'#6b7280';
            var hasOutcome=!!(a.outcome_name);
            var font=cfg.apptFont||'#fff';

            // Card style
            var cs=cfg.cardStyle||'solid';
            var bgStyle='',fontColor=font;
            if(cs==='solid'){bgStyle='background:'+col;}
            else if(cs==='tinted'){
                var r=parseInt(col.slice(1,3),16),g2=parseInt(col.slice(3,5),16),b=parseInt(col.slice(5,7),16);
                var tA=(cfg.tintOpacity||12)/100;
                bgStyle='background:rgba('+r+','+g2+','+b+','+tA+');border-left:3.5px solid '+col;
            }
            else if(cs==='outline'){var bdrCol=cfg.borderColor||col;bgStyle='background:'+cfg.calBg+';border:1.5px solid '+bdrCol+';border-left:3.5px solid '+col;}
            else if(cs==='minimal'){bgStyle='background:transparent;border-left:3px solid '+col;}

            // Banner style
            var bStyle=cfg.bannerStyle||'default';
            var bSize=cfg.bannerSize||'default';
            var bannerHtml='';
            if(bStyle!=='none'&&hasOutcome){
                var bHMap={small:'14px',default:'18px',large:'24px'};
                var bH=bHMap[bSize]||'18px';
                var bannerBg=outcomeColor;
                if(bStyle==='gradient')bannerBg='linear-gradient(90deg,'+outcomeColor+','+outcomeColor+'88)';
                else if(bStyle==='stripe')bannerBg='repeating-linear-gradient(135deg,'+outcomeColor+','+outcomeColor+' 4px,'+outcomeColor+'cc 4px,'+outcomeColor+'cc 8px)';
                bannerHtml='<div class="hm-appt-outcome" style="background:'+bannerBg+';height:'+bH+';font-size:'+cfg.outcomeFontSize+'px;font-weight:'+cfg.outcomeFontWeight+';font-family:\''+cfg.outcomeFontFamily+'\',sans-serif">'+esc(a.outcome_name)+'</div>';
            } else if(bStyle!=='none'&&!hasOutcome){
                // No outcome — show a thin colour banner at top for non-solid styles
            }

            // Cancelled / No Show / Rescheduled — content opacity from SCS settings
            var contentOpStyle='';
            if(scs){contentOpStyle='opacity:'+(scs.contentOpacity/100).toFixed(2)+';';}

            var card='<div class="hm-appt hm-appt--'+cs+stCls+'" data-id="'+a._ID+'" style="'+bgStyle+';height:'+h+'px;top:'+off+'px;color:'+fontColor+'">';
            card+=bannerHtml;
            // Kebab (3-dot) menu button
            card+='<button class="hm-appt-kebab" data-id="'+a._ID+'">'+IC.dots+'</button>';
            card+='<div class="hm-appt-inner" style="'+contentOpStyle+'">';
            var cFF=cfg.cardFontFamily||'Plus Jakarta Sans';
            var cFS=cfg.cardFontSize||11;
            var cFW=cfg.cardFontWeight||600;
            if(cfg.showApptType)card+='<div class="hm-appt-svc" style="color:'+(cfg.apptName||font)+';font-family:'+cFF+',sans-serif;font-size:'+(cFS-1)+'px;font-weight:'+cFW+'">'+esc(a.service_name)+'</div>';
            card+='<div class="hm-appt-pt" style="color:'+fontColor+';font-family:'+cFF+',sans-serif;font-size:'+cFS+'px;font-weight:'+cFW+'">'+tmLbl+esc(a.patient_name||'No patient')+'</div>';
            if(cfg.showTime&&h>36&&!cfg.hideEndTime)card+='<div class="hm-appt-tm" style="color:'+(cfg.apptTime||'#38bdf8')+';font-family:'+cFF+',sans-serif;font-size:'+(cFS-2)+'px;font-weight:'+cFW+'">'+a.start_time.substring(0,5)+' – '+(a.end_time||'').substring(0,5)+'</div>';
            else if(cfg.showTime&&h>36)card+='<div class="hm-appt-tm" style="color:'+(cfg.apptTime||'#38bdf8')+';font-family:'+cFF+',sans-serif;font-size:'+(cFS-2)+'px;font-weight:'+cFW+'">'+a.start_time.substring(0,5)+'</div>';
            // Badges row
            if(cfg.showBadges&&h>44&&!isRescheduled){
                var badges='';
                if(cfg.showStatusBadge){
                    var st2=STATUS_MAP[a.status]||STATUS_MAP['Not Confirmed'];
                    badges+='<span class="hm-appt-badge" style="background:'+st2.bg+';color:'+st2.color+';border:1px solid '+st2.border+'">'+esc(a.status)+'</span>';
                }
                if(cfg.showDispIni){
                    var dd2=Cal.dispensers.find(function(x){return parseInt(x.id)===parseInt(a.dispenser_id);});
                    if(dd2)badges+='<span class="hm-appt-badge hm-appt-badge--ini">'+(dd2.initials||'')+'</span>';
                }
                if(badges)card+='<div class="hm-appt-badges">'+badges+'</div>';
            }
            if(h>50){
                var metaParts=[];
                if(cfg.showClinic)metaParts.push(esc(a.clinic_name||''));
                if(metaParts.length)card+='<div class="hm-appt-meta" style="color:'+(cfg.apptMeta||'#38bdf8')+'">'+metaParts.join(' · ')+'</div>';
            }
            card+='</div>';
            // Dynamic overlay from status card styles
            if(scs&&scs.pattern!=='none'){
                var oC=scs.overlayColor||'#ef4444';
                var oA=((scs.overlayOpacity||10)/100).toFixed(2);
                var rr=parseInt(oC.slice(1,3),16),gg=parseInt(oC.slice(3,5),16),bb=parseInt(oC.slice(5,7),16);
                var rgba='rgba('+rr+','+gg+','+bb+','+oA+')';
                var overlayBg='transparent';
                if(scs.pattern==='striped')overlayBg='repeating-linear-gradient(135deg,'+rgba+','+rgba+' 5px,transparent 5px,transparent 10px)';
                else if(scs.pattern==='crosshatch')overlayBg='repeating-linear-gradient(135deg,'+rgba+','+rgba+' 3px,transparent 3px,transparent 8px),repeating-linear-gradient(45deg,'+rgba+','+rgba+' 3px,transparent 3px,transparent 8px)';
                else if(scs.pattern==='dots')overlayBg='radial-gradient(circle 1.5px at 6px 6px,'+rgba+' 99%,transparent 100%)';
                else if(scs.pattern==='solid')overlayBg=rgba;
                var scsLbl=scs.label||'';
                card+='<div class="hm-appt-overlay" style="background:'+overlayBg+';display:flex;align-items:center;justify-content:center;font-size:'+scs.labelSize+'px;font-weight:700;letter-spacing:.5px;text-transform:uppercase;color:'+scs.labelColor+';text-shadow:0 0 2px rgba(255,255,255,0.9)"><span>'+esc(scsLbl)+'</span></div>';
            }
            card+='</div>';

            var el=$(card);
            // Half-width from SCS settings
            if(scs&&scs.halfWidth){ el.css({right:'calc(50% + 1px)'}); }
            $t.append(el);

            // Track for overlap detection
            cardMeta.push({el:el, di:di, disp:parseInt(a.dispenser_id), startMn:aMn, endMn:aMn+dur});

            // Click / Double-click: use delay so dblclick can cancel single-click popup
            (function(el,a){
                var clickTimer=null;
                el.on('click',function(e){
                    if($(e.target).closest('.hm-appt-kebab').length) return;
                    e.stopPropagation();clearTimeout(Cal._hoverTimer);$('#hm-tooltip').hide();
                    var self=this;
                    clearTimeout(clickTimer);
                    clickTimer=setTimeout(function(){ Cal.showPop(a,self); },280);
                });
                el.on('dblclick',function(e){
                    e.stopPropagation();e.preventDefault();
                    clearTimeout(clickTimer);
                    $('#hm-pop').removeClass('open');
                    Cal._popAppt=a;Cal.openOutcomeModal(a);
                });
            })(el,a);

            // Hover → tooltip after 1 second
            el.on('mouseenter',function(){
                var rect=this.getBoundingClientRect();
                Cal._hoverTimer=setTimeout(function(){Cal.showTooltip(a,rect);},1000);
            });
            el.on('mouseleave',function(){clearTimeout(Cal._hoverTimer);$('#hm-tooltip').hide();});
        });

        // ── Overlap detection: split width for double-booked cards ──
        // Group cards by day+dispenser column
        var groups={};
        cardMeta.forEach(function(m){
            var key=m.di+'_'+m.disp;
            if(!groups[key])groups[key]=[];
            groups[key].push(m);
        });
        // For each group, find overlapping clusters and split widths
        Object.keys(groups).forEach(function(key){
            var cards=groups[key];
            if(cards.length<2)return;
            // Sort by start time
            cards.sort(function(a,b){return a.startMn-b.startMn;});
            // Build overlap clusters using a sweep
            var clusters=[];
            cards.forEach(function(c){
                var placed=false;
                for(var i=0;i<clusters.length;i++){
                    // Check if this card overlaps with any card in the cluster
                    var overlaps=clusters[i].some(function(x){
                        return c.startMn<x.endMn&&c.endMn>x.startMn;
                    });
                    if(overlaps){clusters[i].push(c);placed=true;break;}
                }
                if(!placed)clusters.push([c]);
            });
            // Apply width splitting to clusters with >1 card
            clusters.forEach(function(cl){
                if(cl.length<2)return;
                var n=cl.length;
                var pct=(100/n);
                cl.forEach(function(c,idx){
                    c.el.css({
                        left: (2 + idx*pct)+'%',
                        right: 'auto',
                        width: 'calc('+pct+'% - 3px)'
                    });
                });
            });
        });
    },

    // ── EXCLUSION BLOCKS ──
    renderExclusions:function(){
        $('.hm-excl').remove();$('.hm-excl-pop').remove();
        var dates=this.visDates(),disps=this.visDisps(),cfg=this.cfg,slotH=cfg.slotHpx;
        if(!disps.length||!this.exclusions||!this.exclusions.length)return;
        var self=this;
        this.exclusions.forEach(function(ex){
            var col=ex.color||'#6b7280';
            var textCol=ex.text_color||col;
            var exDate=ex.start_date;

            // Find which day index this exclusion falls on
            var di=-1;
            for(var i=0;i<dates.length;i++){if(fmt(dates[i])===exDate){di=i;break;}}
            if(di===-1)return;

            // Determine which dispensers to render for
            var targetDisps=[];
            if(ex.staff_id&&parseInt(ex.staff_id)){
                // Specific dispenser
                for(var pi=0;pi<disps.length;pi++){
                    if(parseInt(disps[pi].id)===parseInt(ex.staff_id)){targetDisps.push(pi);break;}
                }
            } else {
                // All dispensers
                for(var pi2=0;pi2<disps.length;pi2++){targetDisps.push(pi2);}
            }
            if(!targetDisps.length)return;

            var isFullDay=ex.scope==='full_day'||!ex.start_time||!ex.end_time;

            // Parse colour to rgb
            var r=parseInt(col.slice(1,3),16)||107,g=parseInt(col.slice(3,5),16)||114,b=parseInt(col.slice(5,7),16)||128;

            targetDisps.forEach(function(pi){
                if(isFullDay){
                    // Full day — fill entire column
                    var totalH=cfg.totalSlots*slotH;
                    var $slot=$('.hm-slot[data-day="'+di+'"][data-slot="0"][data-disp="'+disps[pi].id+'"]');
                    if(!$slot.length)return;
                    var block=$('<div class="hm-excl hm-excl--fullday" data-excl-id="'+ex.id+'" style="'+
                        'position:absolute;left:1px;right:1px;top:0;height:'+totalH+'px;z-index:1;pointer-events:none;">'+
                        '<button class="hm-excl-kebab" data-excl-id="'+ex.id+'" style="pointer-events:auto">'+IC.dots+'</button>'+
                        '<span class="hm-excl-label">'+esc(ex.type_name||'Exclusion')+'</span>'+
                    '</div>');
                    block[0].style.setProperty('--excl-color',col);
                    block[0].style.setProperty('--excl-text-color',textCol);
                    block[0].style.setProperty('--excl-r',r);
                    block[0].style.setProperty('--excl-g',g);
                    block[0].style.setProperty('--excl-b',b);
                    $slot.append(block);
                } else {
                    // Custom hours — positioned like an appointment card
                    var stParts=(ex.start_time||'09:00').split(':'),etParts=(ex.end_time||'17:00').split(':');
                    var stMn=parseInt(stParts[0])*60+parseInt(stParts[1]);
                    var etMn=parseInt(etParts[0])*60+parseInt(etParts[1]);
                    var dur=etMn-stMn;if(dur<=0)return;

                    var si=Math.floor((stMn-cfg.startH*60)/cfg.slotMin);
                    var off=((stMn-cfg.startH*60)%cfg.slotMin)/cfg.slotMin*slotH;
                    var h=(dur/cfg.slotMin)*slotH;
                    if(si<0)si=0;

                    var $slot=$('.hm-slot[data-day="'+di+'"][data-slot="'+si+'"][data-disp="'+disps[pi].id+'"]');
                    if(!$slot.length)return;

                    var block=$('<div class="hm-excl hm-excl--hours" data-excl-id="'+ex.id+'" style="'+
                        'position:absolute;left:1px;right:1px;top:'+off+'px;height:'+h+'px;z-index:1;padding:3px 6px;pointer-events:none;">'+
                        '<button class="hm-excl-kebab" data-excl-id="'+ex.id+'" style="pointer-events:auto">'+IC.dots+'</button>'+
                        '<span class="hm-excl-label">'+esc(ex.type_name||'Exclusion')+'</span>'+
                    '</div>');
                    block[0].style.setProperty('--excl-color',col);
                    block[0].style.setProperty('--excl-text-color',textCol);
                    block[0].style.setProperty('--excl-r',r);
                    block[0].style.setProperty('--excl-g',g);
                    block[0].style.setProperty('--excl-b',b);
                    $slot.append(block);
                }
            });
        });
    },
    _isSlotExcluded:function(date,time,dispId){
        if(!this.exclusions||!this.exclusions.length)return null;
        var ds=typeof date==='string'?date:fmt(date);
        var tmn=parseInt(time.split(':')[0])*60+parseInt(time.split(':')[1]);
        for(var i=0;i<this.exclusions.length;i++){
            var ex=this.exclusions[i];
            if(ex.start_date!==ds)continue;
            // Check dispenser match
            if(ex.staff_id&&parseInt(ex.staff_id)&&parseInt(ex.staff_id)!==parseInt(dispId))continue;
            var isFullDay=ex.scope==='full_day'||!ex.start_time||!ex.end_time;
            if(isFullDay)return ex;
            var stParts=(ex.start_time||'00:00').split(':'),etParts=(ex.end_time||'23:59').split(':');
            var sMn=parseInt(stParts[0])*60+parseInt(stParts[1]);
            var eMn=parseInt(etParts[0])*60+parseInt(etParts[1]);
            if(tmn>=sMn&&tmn<eMn)return ex;
        }
        return null;
    },

    // ── NOW LINE ──
    renderNow:function(){
        $('.hm-now').remove();
        var now=new Date(),dates=this.visDates(),disps=this.visDisps(),cfg=this.cfg;
        var di=-1;
        for(var i=0;i<dates.length;i++){if(isToday(dates[i])){di=i;break;}}
        if(di===-1)return;
        var nm=now.getHours()*60+now.getMinutes();
        if(nm<cfg.startH*60||nm>=cfg.endH*60)return;
        var si=Math.floor((nm-cfg.startH*60)/cfg.slotMin);
        var off=((nm-cfg.startH*60)%cfg.slotMin)/cfg.slotMin*cfg.slotHpx;
        disps.forEach(function(p){
            var $t=$('.hm-slot[data-day="'+di+'"][data-slot="'+si+'"][data-disp="'+p.id+'"]');
            if($t.length)$t.append('<div class="hm-now" style="top:'+off+'px;background:'+(cfg.indicatorColor||'#00d59b')+'"><div class="hm-now-dot" style="background:'+(cfg.indicatorColor||'#00d59b')+'"></div></div>');
        });
    },

    // ── HOVER TOOLTIP ──
    showTooltip:function(a,rect){
        var disp=this.dispensers.find(function(d){return parseInt(d.id)===parseInt(a.dispenser_id);});
        var clinic=this.clinics.find(function(c){return parseInt(c.id)===parseInt(a.clinic_id);});
        var h='<div class="hm-tip-name">'+esc(a.patient_name||'—')+'</div>';
        h+='<div class="hm-tip-num">'+esc(a.patient_number||'')+'</div>';
        h+='<div class="hm-tip-rows">';
        h+='<div class="hm-tip-row"><span class="hm-tip-lbl">Service</span><span>'+esc(a.service_name)+'</span></div>';
        h+='<div class="hm-tip-row"><span class="hm-tip-lbl">Time</span><span>'+a.start_time.substring(0,5)+' – '+(a.end_time||'').substring(0,5)+' ('+a.duration+'min)</span></div>';
        h+='<div class="hm-tip-row"><span class="hm-tip-lbl">Status</span><span>'+esc(a.status)+'</span></div>';
        h+='<div class="hm-tip-row"><span class="hm-tip-lbl">Dispenser</span><span>'+esc(disp?disp.name:'—')+'</span></div>';
        h+='<div class="hm-tip-row"><span class="hm-tip-lbl">Clinic</span><span>'+esc(clinic?clinic.name:'—')+'</span></div>';
        if(a.patient_phone)h+='<div class="hm-tip-row"><span class="hm-tip-lbl">Phone</span><span>'+esc(a.patient_phone)+'</span></div>';
        if(a.outcome_name)h+='<div class="hm-tip-row"><span class="hm-tip-lbl">Outcome</span><span>'+esc(a.outcome_name)+'</span></div>';
        h+='</div>';
        var $tip=$('#hm-tooltip');
        $tip.html(h).show();
        var left=Math.min(rect.right+8,window.innerWidth-230);
        var top=Math.min(rect.top,window.innerHeight-$tip.outerHeight()-10);
        $tip.css({left:left,top:top});
    },

    // ── POPOVER ──
    showPop:function(a,el){
        this._popAppt=a;
        var r=el.getBoundingClientRect();
        var col=a.service_colour||'#3B82F6';
        var disp=this.dispensers.find(function(d){return parseInt(d.id)===parseInt(a.dispenser_id);});
        var clinic=this.clinics.find(function(c){return parseInt(c.id)===parseInt(a.clinic_id);});
        var st=STATUS_MAP[a.status]||STATUS_MAP.Confirmed;
        var hasOutcome=!!(a.outcome_name);
        var popOutcomeColor=a.outcome_banner_colour||'#6b7280';
        var isCompleted=a.status==='Completed';
        var isLocked=isCompleted||hasOutcome;

        var h='<div class="hm-pop-bar" style="background:'+col+'"></div>';
        if(hasOutcome){
            h+='<div class="hm-pop-outcome" style="background:linear-gradient(90deg,'+popOutcomeColor+','+popOutcomeColor+'cc)">'+esc(a.outcome_name)+'</div>';
        }
        h+='<div class="hm-pop-body">';
        h+='<div class="hm-pop-hd"><div><div class="hm-pop-name">'+esc(a.patient_name||'No patient')+'</div><div class="hm-pop-num">'+esc(a.patient_number||'')+'</div></div><button class="hm-pop-x">'+IC.x+'</button></div>';
        h+='<div class="hm-pop-status"><span class="hm-status-pill" style="background:'+st.bg+';color:'+st.color+';border:1px solid '+st.border+'">'+esc(a.status)+'</span></div>';
        h+='<div class="hm-pop-details">';
        h+='<div class="hm-pop-row">'+IC.clock+' <span>'+a.start_time.substring(0,5)+' – '+(a.end_time||'').substring(0,5)+' · '+(a.duration||30)+'min</span></div>';
        h+='<div class="hm-pop-row"><span class="hm-pop-svc-dot" style="background:'+col+'"></span> <span>'+esc(a.service_name)+'</span></div>';
        h+='<div class="hm-pop-row">'+IC.user+' <span>'+esc(disp?disp.name:'—')+' · '+esc(clinic?clinic.name:'')+'</span></div>';
        h+='</div>';

        // Outcome selection area (hidden by default, shown when "Close Off" clicked)
        h+='<div class="hm-pop-outcome-area" id="hm-pop-outcome-area" style="display:none">';
        h+='<div style="font-size:11px;font-weight:700;color:#334155;margin-bottom:6px;text-transform:uppercase;letter-spacing:0.3px">Select Outcome</div>';
        h+='<div id="hm-pop-outcome-list" style="display:flex;flex-direction:column;gap:4px"><div style="color:#94a3b8;font-size:12px;padding:8px 0">Loading outcomes...</div></div>';
        h+='<div id="hm-pop-outcome-note" style="display:none;margin-top:8px"><textarea class="hm-inp" id="hm-outcome-note" rows="2" placeholder="Outcome note..." style="font-size:12px"></textarea></div>';
        h+='<div style="margin-top:8px;display:flex;gap:6px;justify-content:flex-end"><button class="hm-pop-act hm-pop-outcome-cancel" style="font-size:11px">Cancel</button><button class="hm-pop-act hm-pop-act--primary hm-pop-outcome-save" style="font-size:11px" disabled>Save Outcome</button></div>';
        h+='</div>';

        h+='<div class="hm-pop-actions" id="hm-pop-actions">';
        if(isLocked){
            h+='<button class="hm-pop-act hm-pop-act--primary hm-pop-edit">View Details</button>';
            h+='<div style="font-size:11px;color:#059669;margin-top:4px;display:flex;align-items:center;gap:4px"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg> Appointment closed — read only</div>';
        } else {
            h+='<button class="hm-pop-act hm-pop-act--primary hm-pop-edit">Edit</button>';
            h+='<button class="hm-pop-act hm-pop-act--teal hm-pop-closeoff" data-sid="'+a.service_id+'" data-aid="'+a._ID+'">Close Off</button>';
        }
        h+='</div>';
        h+='</div>';

        var $p=$('#hm-pop');
        $p.html(h).addClass('open');
        var left=Math.min(r.right+10,window.innerWidth-300);
        var top=Math.min(r.top,window.innerHeight-$p.outerHeight()-10);
        $p.css({left:left,top:top});
    },

    editPop:function(){
        var a=this._popAppt;if(!a)return;
        $('#hm-pop').removeClass('open');
        var self=this;
        // Ensure services & clinics loaded for the edit modal
        var ready=$.Deferred();
        if(!self.services.length||!self.clinics.length){
            $.when(
                self.services.length?null:self.loadServices(),
                self.clinics.length?null:self.loadClinics(),
                self.dispensers.length?null:self.loadDispensers()
            ).always(function(){ready.resolve();});
        } else { ready.resolve(); }
        ready.then(function(){ self._buildEditModal(a); });
    },
    _buildEditModal:function(a){
        var self=this;
        var isLocked=a.status==='Completed'||!!(a.outcome_name);
        var svcOpts=self.services.map(function(s){return'<option value="'+s.id+'"'+(parseInt(s.id)===parseInt(a.service_id)?' selected':'')+'>'+esc(s.name)+'</option>';}).join('');
        var cliOpts=self.clinics.map(function(c){return'<option value="'+c.id+'"'+(parseInt(c.id)===parseInt(a.clinic_id)?' selected':'')+'>'+esc(c.name)+'</option>';}).join('');
        var dispOpts=self.dispensers.map(function(d){return'<option value="'+d.id+'"'+(parseInt(d.id)===parseInt(a.dispenser_id)?' selected':'')+'>'+esc(d.name)+'</option>';}).join('');
        var title=isLocked?'Appointment Details':'Edit Appointment';
        var dis=isLocked?' disabled':'';
        var html='<div class="hm-modal-bg open"><div class="hm-modal hm-modal--md">'+
            '<div class="hm-modal-hd"><h3>'+title+'</h3><button class="hm-close hm-edit-close">'+IC.x+'</button></div>'+
            '<div class="hm-modal-body">';
        if(isLocked){
            html+='<div style="margin-bottom:12px;padding:10px 14px;background:#ecfdf5;border:1px solid #a7f3d0;border-radius:8px;font-size:12px;color:#065f46;display:flex;align-items:center;gap:8px"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg><strong>Closed</strong> — This appointment has been completed with outcome: <strong>'+esc(a.outcome_name||'Completed')+'</strong></div>';
        }
        html+='<div class="hm-row"><div class="hm-fld"><label>Appointment Type</label><select class="hm-inp" id="hme-service"'+dis+'>'+svcOpts+'</select></div>'+
                '<div class="hm-fld"><label>Assignee</label><select class="hm-inp" id="hme-disp"'+dis+'>'+dispOpts+'</select></div></div>'+
                '<div class="hm-row"><div class="hm-fld"><label>Clinic</label><select class="hm-inp" id="hme-clinic"'+dis+'>'+cliOpts+'</select></div>'+
                '<div class="hm-fld"><label>Location</label><select class="hm-inp" id="hme-loc"'+dis+'><option>Clinic</option><option>Home</option></select></div></div>'+
                '<div class="hm-row"><div class="hm-fld"><label>Date</label><input type="date" class="hm-inp" id="hme-date" value="'+a.appointment_date+'"'+dis+'></div>'+
                '<div class="hm-fld"><label>Start Time</label><input type="time" class="hm-inp" id="hme-time" value="'+(a.start_time||'').substring(0,5)+'"'+dis+'></div></div>'+
                '<div class="hm-row"><div class="hm-fld"><label>Status</label><select class="hm-inp" id="hme-status" disabled><option>Not Confirmed</option><option>Confirmed</option><option>Arrived</option><option>In Progress</option><option>Completed</option><option>Late</option><option>No Show</option><option>Cancelled</option><option>Rescheduled</option><option>Pending</option></select></div>'+
                '<div class="hm-fld"></div></div>'+
                '<div class="hm-fld"><label>Notes</label><textarea class="hm-inp" id="hme-notes" rows="3"'+dis+'>'+esc(a.notes||'')+'</textarea></div>'+
            '</div>';
        if(isLocked){
            html+='<div class="hm-modal-ft"><span></span><div class="hm-modal-acts"><button class="hm-btn hm-edit-close">Close</button></div></div>';
        } else {
            html+='<div class="hm-modal-ft"><button class="hm-btn hm-btn--danger hm-edit-del">Delete</button><div class="hm-modal-acts"><button class="hm-btn hm-edit-close">Cancel</button><button class="hm-btn hm-btn--primary hm-edit-save">Save</button></div></div>';
        }
        html+='</div></div>';
        $('body').append(html);
        $('#hme-status').val(a.status||'Not Confirmed');
        $('#hme-loc').val(a.location_type||'Clinic');
        $(document).off('click.editclose').on('click.editclose','.hm-edit-close',function(){$('.hm-modal-bg').remove();$(document).off('.editclose .editsave .editdel');});
        $(document).off('click.editsave').on('click.editsave','.hm-edit-save',function(){
            post('update_appointment',{appointment_id:a._ID,appointment_date:$('#hme-date').val(),start_time:$('#hme-time').val(),status:$('#hme-status').val(),location_type:$('#hme-loc').val(),notes:$('#hme-notes').val(),service_id:$('#hme-service').val(),clinic_id:$('#hme-clinic').val(),dispenser_id:$('#hme-disp').val()})
            .then(function(r){if(r.success){$('.hm-modal-bg').remove();$(document).off('.editclose .editsave .editdel');self.refresh();}else{alert(r.data||'Error');}});
        });
        $(document).off('click.editdel').on('click.editdel','.hm-edit-del',function(){
            if(!confirm('Delete this appointment?'))return;
            var reason=prompt('Reason for cancellation:')||'Deleted';
            post('delete_appointment',{appointment_id:a._ID,reason:reason}).then(function(r){
                if(r.success){$('.hm-modal-bg').remove();$(document).off('.editclose .editsave .editdel');self.refresh();}else{alert(r.data||'Error');}
            });
        });
    },

    // ── STATUS CHANGE with side-effects ──
    doStatusChange:function(a,status,note,extra){
        var data={appointment_id:a._ID,status:status,note:note||''};
        if(extra)$.extend(data,extra);
        var self=this;
        console.log('[HearMed] doStatusChange →',{id:a._ID,status:status,note:note||''});
        post('update_appointment_status',data).then(function(r){
            console.log('[HearMed] update_appointment_status response:',r);
            if(r.success){
                self.refresh();
                // Show a brief toast
                Cal.toast(status==='Confirmed'?'Confirmed — note added to patient file':
                          status==='Arrived'?'Arrived — dispenser notified':
                          status==='Late'?'Running late — dispenser notified':
                          status==='Rescheduled'?'Rescheduled — new appointment created':
                          status==='Cancelled'?'Cancelled — note added to patient file':
                          'Status updated to '+status);
            } else {
                alert(r.data&&r.data.message?r.data.message:'Error updating status');
            }
        }).fail(function(){alert('Network error');});
    },

    // ── Note modal for Late / Cancelled ──
    openNoteModal:function(a,status,prompt){
        var self=this;
        var h='<div class="hm-modal-bg open"><div class="hm-modal hm-modal--sm">';
        h+='<div class="hm-modal-hd"><h3>'+esc(status)+' — '+esc(a.patient_name||'')+'</h3><button class="hm-close hm-note-close">'+IC.x+'</button></div>';
        h+='<div class="hm-modal-body">';
        h+='<div class="hm-fld"><label>'+esc(prompt)+'</label><textarea class="hm-inp" id="hm-status-note" rows="3" placeholder="Add a note..." autofocus></textarea></div>';
        h+='</div>';
        h+='<div class="hm-modal-ft"><span></span><div class="hm-modal-acts"><button class="hm-btn hm-note-close">Cancel</button><button class="hm-btn hm-btn--primary hm-note-save">Save</button></div></div>';
        h+='</div></div>';
        $('body').append(h);
        setTimeout(function(){$('#hm-status-note').focus();},100);
        $(document).off('click.noteclose').on('click.noteclose','.hm-note-close',function(){$('.hm-modal-bg').remove();$(document).off('.noteclose .notesave');});
        $(document).off('click.notesave').on('click.notesave','.hm-note-save',function(){
            var note=$('#hm-status-note').val()||'';
            if(!note&&status==='Late'){alert('Please add a note for why the patient is late.');return;}
            $('.hm-modal-bg').remove();$(document).off('.noteclose .notesave');
            self.doStatusChange(a,status,note);
        });
    },

    // ── Reschedule modal — note + new date/time ──
    openRescheduleModal:function(a){
        var self=this;
        var h='<div class="hm-modal-bg open"><div class="hm-modal hm-modal--sm">';
        h+='<div class="hm-modal-hd"><h3>Reschedule — '+esc(a.patient_name||'')+'</h3><button class="hm-close hm-resched-close">'+IC.x+'</button></div>';
        h+='<div class="hm-modal-body">';
        h+='<div class="hm-fld"><label>Reason for rescheduling</label><textarea class="hm-inp" id="hm-resched-note" rows="2" placeholder="Add a note..."></textarea></div>';
        h+='<div class="hm-row"><div class="hm-fld"><label>New Date</label><input type="date" class="hm-inp" id="hm-resched-date" value=""></div>';
        h+='<div class="hm-fld"><label>New Time</label><input type="time" class="hm-inp" id="hm-resched-time" value="'+(a.start_time||'09:00').substring(0,5)+'"></div></div>';
        h+='</div>';
        h+='<div class="hm-modal-ft"><span></span><div class="hm-modal-acts"><button class="hm-btn hm-resched-close">Cancel</button><button class="hm-btn hm-btn--primary hm-resched-save">Reschedule</button></div></div>';
        h+='</div></div>';
        $('body').append(h);
        $(document).off('click.reschedclose').on('click.reschedclose','.hm-resched-close',function(){$('.hm-modal-bg').remove();$(document).off('.reschedclose .reschedsave');});
        $(document).off('click.reschedsave').on('click.reschedsave','.hm-resched-save',function(){
            var note=$('#hm-resched-note').val()||'';
            var nd=$('#hm-resched-date').val();
            var nt=$('#hm-resched-time').val();
            if(!nd||!nt){alert('Please select a new date and time.');return;}
            $('.hm-modal-bg').remove();$(document).off('.reschedclose .reschedsave');
            self.doStatusChange(a,'Rescheduled',note,{new_date:nd,new_time:nt});
        });
    },

    // ── Quick add notes modal ──
    openQuickNoteModal:function(a){
        if(!a.patient_id){alert('No patient linked to this appointment.');return;}
        var h='<div class="hm-modal-bg open"><div class="hm-modal hm-modal--sm">';
        h+='<div class="hm-modal-hd"><h3>Quick Note — '+esc(a.patient_name||'')+'</h3><button class="hm-close hm-qn-close">'+IC.x+'</button></div>';
        h+='<div class="hm-modal-body">';
        h+='<div class="hm-fld"><label>Note</label><textarea class="hm-inp" id="hm-qn-text" rows="3" placeholder="Type your note..." autofocus></textarea></div>';
        h+='</div>';
        h+='<div class="hm-modal-ft"><span></span><div class="hm-modal-acts"><button class="hm-btn hm-qn-close">Cancel</button><button class="hm-btn hm-btn--primary hm-qn-save">Save Note</button></div></div>';
        h+='</div></div>';
        $('body').append(h);
        setTimeout(function(){$('#hm-qn-text').focus();},100);
        $(document).off('click.qnclose').on('click.qnclose','.hm-qn-close',function(){$('.hm-modal-bg').remove();$(document).off('.qnclose .qnsave');});
        $(document).off('click.qnsave').on('click.qnsave','.hm-qn-save',function(){
            var txt=$('#hm-qn-text').val()||'';
            if(!txt){alert('Please enter a note.');return;}
            var $btn=$(this);$btn.prop('disabled',true).text('Saving...');
            post('save_patient_note',{patient_id:a.patient_id,note_type:'Manual',note_text:txt}).then(function(r){
                if(r.success){$('.hm-modal-bg').remove();$(document).off('.qnclose .qnsave');Cal.toast('Note saved to patient file');}
                else{$btn.prop('disabled',false).text('Save Note');alert('Error saving note');}
            }).fail(function(){$btn.prop('disabled',false).text('Save Note');alert('Network error');});
        });
    },

    // ── Toast notification ──
    toast:function(msg){
        var $t=$('<div class="hm-toast">'+esc(msg)+'</div>');
        $('body').append($t);
        setTimeout(function(){$t.addClass('show');},10);
        setTimeout(function(){$t.removeClass('show');setTimeout(function(){$t.remove();},300);},3000);
    },

    // ═══════════════════════════════════════════════════════
    // OUTCOME MODAL — opened on double-click
    // ═══════════════════════════════════════════════════════
    openOutcomeModal:function(a){
        var self=this;
        // If appointment is locked (completed/has outcome), open view-only modal instead
        if(a.status==='Completed'||!!(a.outcome_name)){
            self._popAppt=a;
            self.editPop();
            return;
        }
        $('#hm-pop').removeClass('open');$('#hm-tooltip').hide();
        var col=a.service_colour||'#3B82F6';
        var disp=this.dispensers.find(function(d){return parseInt(d.id)===parseInt(a.dispenser_id);});
        var clinic=this.clinics.find(function(c){return parseInt(c.id)===parseInt(a.clinic_id);});
        var st=STATUS_MAP[a.status]||STATUS_MAP.Confirmed;

        var h='<div class="hm-modal-bg hm-outcome-modal-bg open"><div class="hm-modal hm-modal--md" style="max-width:520px">';
        h+='<div class="hm-modal-hd" style="background:'+col+';color:#fff;border-radius:12px 12px 0 0;padding:14px 20px">';
        h+='<div><h3 style="margin:0;color:#fff;font-size:16px">Appointment Outcome</h3>';
        h+='<div style="font-size:12px;opacity:.85;margin-top:2px">'+esc(a.patient_name||'No patient')+' — '+esc(a.service_name)+'</div></div>';
        h+='<button class="hm-close hm-outcome-close" style="color:#fff">'+IC.x+'</button></div>';
        h+='<div class="hm-modal-body" style="padding:20px">';

        // Info row
        h+='<div style="display:flex;gap:16px;margin-bottom:16px;font-size:12px;color:#64748b">';
        h+='<span>'+IC.clock+' '+a.start_time.substring(0,5)+' – '+(a.end_time||'').substring(0,5)+'</span>';
        h+='<span>'+IC.user+' '+esc(disp?disp.name:'—')+'</span>';
        h+='<span class="hm-status-pill" style="background:'+st.bg+';color:'+st.color+';border:1px solid '+st.border+';font-size:10px;padding:1px 8px">'+esc(a.status)+'</span>';
        h+='</div>';

        // Outcome selection area
        h+='<div style="font-size:11px;font-weight:700;color:#334155;margin-bottom:8px;text-transform:uppercase;letter-spacing:.3px">Select Outcome</div>';
        h+='<div id="hm-om-list" style="display:flex;flex-direction:column;gap:6px;margin-bottom:16px"><div style="color:#94a3b8;font-size:12px;padding:12px 0;text-align:center">Loading outcomes...</div></div>';

        // Note area (hidden, shown when outcome with requires_note is picked)
        h+='<div id="hm-om-note-area" style="display:none;margin-bottom:16px">';
        h+='<div style="font-size:11px;font-weight:700;color:#334155;margin-bottom:6px;text-transform:uppercase;letter-spacing:.3px">Outcome Note <span style="color:#ef4444">*</span></div>';
        h+='<textarea class="hm-inp" id="hm-om-note" rows="3" placeholder="Enter outcome note..." style="font-size:13px"></textarea>';
        h+='<div id="hm-om-note-err" style="display:none;color:#ef4444;font-size:11px;margin-top:4px">Note is required for this outcome.</div>';
        h+='</div>';

        // Follow-up area (hidden, shown after outcome w/ triggers_followup is saved)
        h+='<div id="hm-om-followup-area" style="display:none;margin-bottom:16px">';
        h+='<div style="font-size:11px;font-weight:700;color:#334155;margin-bottom:6px;text-transform:uppercase;letter-spacing:.3px">Follow-up Appointment</div>';
        h+='<div id="hm-om-followup-types" style="display:flex;flex-direction:column;gap:4px"></div>';
        h+='</div>';

        // Order notice area (hidden, shown for invoiceable outcomes)
        h+='<div id="hm-om-invoice-area" style="display:none;margin-bottom:16px">';
        h+='<div style="padding:12px 16px;background:#fefce8;border:1px solid #fde68a;border-radius:8px;display:flex;align-items:center;gap:10px">';
        h+='<span style="font-size:18px">£</span>';
        h+='<div><div style="font-size:13px;font-weight:600;color:#854d0e">This outcome requires an order</div>';
        h+='<div style="font-size:11px;color:#92400e">A new order form will open after saving.</div></div>';
        h+='</div></div>';

        h+='</div>'; // end modal-body

        // Footer
        h+='<div class="hm-modal-ft" style="padding:12px 20px;border-top:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center">';
        h+='<span id="hm-om-err" style="color:#ef4444;font-size:12px"></span>';
        h+='<div style="display:flex;gap:8px"><button class="hm-btn hm-outcome-close">Cancel</button>';
        h+='<button class="hm-btn hm-btn--primary hm-om-save" disabled>Save Outcome</button></div>';
        h+='</div>';
        h+='</div></div>';

        $('body').append(h);
        self._outcomeData=null;
        self._outcomeFollowupPending=false;
        self._outcomeInvoicePending=false;

        // Load outcome templates for this appointment's service
        post('get_outcome_templates',{service_id:a.service_id}).then(function(r){
            if(!r.success||!r.data||!r.data.length){
                $('#hm-om-list').html('<div style="color:#94a3b8;font-size:12px;padding:12px 0;text-align:center">No outcomes configured for this appointment type.<br><span style="font-size:11px">Add outcomes in Admin → Appointment Types → Edit.</span></div>');
                return;
            }
            var oh='';
            r.data.forEach(function(o){
                oh+='<button class="hm-om-opt" data-oid="'+o.id+'" data-color="'+esc(o.outcome_color)+'" data-name="'+esc(o.outcome_name)+'"';
                oh+=' data-note="'+(o.requires_note?'1':'0')+'" data-invoice="'+(o.is_invoiceable?'1':'0')+'"';
                oh+=' data-followup="'+(o.triggers_followup?'1':'0')+'"';
                oh+=' data-fu-svc="'+esc(JSON.stringify(o.followup_service_ids||[]))+'"';
                oh+=' style="display:flex;align-items:center;gap:10px;padding:10px 14px;border:2px solid #e2e8f0;border-radius:8px;background:#fff;cursor:pointer;font-size:13px;font-weight:600;color:#334155;transition:all .15s;text-align:left;width:100%">';
                oh+='<span style="width:14px;height:14px;border-radius:4px;background:'+esc(o.outcome_color)+';flex-shrink:0"></span>';
                oh+='<span style="flex:1">'+esc(o.outcome_name)+'</span>';
                var badges='';
                if(o.is_invoiceable)badges+='<span style="font-size:9px;background:#dbeafe;color:#1e40af;padding:2px 6px;border-radius:4px;font-weight:700">£ Invoiceable</span>';
                if(o.requires_note)badges+='<span style="font-size:9px;background:#e0f2fe;color:#0369a1;padding:2px 6px;border-radius:4px;font-weight:700">Note Required</span>';
                if(o.triggers_followup)badges+='<span style="font-size:9px;background:#dcfce7;color:#166534;padding:2px 6px;border-radius:4px;font-weight:700">Follow-up</span>';
                if(badges)oh+='<span style="display:flex;gap:4px;flex-shrink:0">'+badges+'</span>';
                oh+='</button>';
            });
            $('#hm-om-list').html(oh);
        }).fail(function(){
            $('#hm-om-list').html('<div style="color:#ef4444;font-size:12px;text-align:center">Failed to load outcomes</div>');
        });

        // Outcome option click
        $(document).off('click.omopt').on('click.omopt','.hm-om-opt',function(){
            $('.hm-om-opt').css({borderColor:'#e2e8f0',background:'#fff'});
            var $o=$(this);
            $o.css({borderColor:$o.data('color'),background:$o.data('color')+'12'});
            var needsNote=$o.data('note')==='1'||$o.data('note')===1;
            var isInvoice=$o.data('invoice')==='1'||$o.data('invoice')===1;
            var needsFollowup=$o.data('followup')==='1'||$o.data('followup')===1;
            var fuSvc=[];
            try{fuSvc=JSON.parse($o.attr('data-fu-svc')||'[]');}catch(e){}

            self._outcomeData={
                id:$o.data('oid'),
                color:$o.data('color'),
                name:$o.data('name'),
                requires_note:needsNote,
                is_invoiceable:isInvoice,
                triggers_followup:needsFollowup,
                followup_service_ids:fuSvc
            };

            // Show/hide note area
            if(needsNote){
                $('#hm-om-note-area').show();
                $('#hm-om-note').focus();
            } else {
                $('#hm-om-note-area').hide();
                $('#hm-om-note').val('');
            }

            // Show/hide invoice notice
            if(isInvoice){
                $('#hm-om-invoice-area').show();
                self._outcomeInvoicePending=true;
            } else {
                $('#hm-om-invoice-area').hide();
                self._outcomeInvoicePending=false;
            }

            // Show/hide follow-up area
            if(needsFollowup && fuSvc.length){
                $('#hm-om-followup-area').show();
                self._outcomeFollowupPending=true;
                // Build follow-up service options (only allowed service types)
                var fh='';
                self.services.forEach(function(s){
                    if(fuSvc.indexOf(parseInt(s.id))>-1){
                        fh+='<label class="hm-om-fu-opt" style="display:flex;align-items:center;gap:8px;padding:8px 12px;border:1.5px solid #e2e8f0;border-radius:6px;cursor:pointer;font-size:12px;transition:all .15s">';
                        fh+='<input type="radio" name="hm-om-fu-svc" value="'+s.id+'" style="accent-color:#0BB4C4"> ';
                        fh+='<span style="width:10px;height:10px;border-radius:3px;background:'+(s.color||s.service_colour||'#3B82F6')+';flex-shrink:0"></span>';
                        fh+=esc(s.name);
                        fh+='</label>';
                    }
                });
                if(!fh)fh='<div style="color:#94a3b8;font-size:12px">No follow-up service types configured.</div>';
                $('#hm-om-followup-types').html(fh);
            } else {
                $('#hm-om-followup-area').hide();
                self._outcomeFollowupPending=false;
            }

            // Enable save button
            $('.hm-om-save').prop('disabled',false);
            $('#hm-om-err').text('');
        });

        // Follow-up radio highlight
        $(document).off('change.omfu').on('change.omfu','input[name="hm-om-fu-svc"]',function(){
            $('.hm-om-fu-opt').css({borderColor:'#e2e8f0',background:'#fff'});
            $(this).closest('.hm-om-fu-opt').css({borderColor:'#0BB4C4',background:'#f0fdfa'});
        });

        // Close
        $(document).off('click.omclose').on('click.omclose','.hm-outcome-close',function(e){
            e.stopPropagation();
            $('.hm-outcome-modal-bg').remove();
            $(document).off('.omopt .omfu .omclose .omsave');
        });

        // Click background to close
        $(document).off('click.ombg').on('click.ombg','.hm-outcome-modal-bg',function(e){
            if($(e.target).hasClass('hm-outcome-modal-bg')){
                $('.hm-outcome-modal-bg').remove();
                $(document).off('.omopt .omfu .omclose .omsave .ombg');
            }
        });

        // Save outcome
        $(document).off('click.omsave').on('click.omsave','.hm-om-save',function(){
            var o=self._outcomeData;
            if(!o){$('#hm-om-err').text('Please select an outcome.');return;}

            // Validate note if required
            var noteVal=$('#hm-om-note').val()||'';
            if(o.requires_note && !noteVal.trim()){
                $('#hm-om-note-err').show();
                $('#hm-om-note').css('borderColor','#ef4444').focus();
                return;
            }

            // Validate follow-up selection if required
            var fuSvcId=0;
            if(o.triggers_followup && o.followup_service_ids.length){
                fuSvcId=parseInt($('input[name="hm-om-fu-svc"]:checked').val()||0);
                if(!fuSvcId){
                    $('#hm-om-err').text('Please select a follow-up appointment type.');
                    return;
                }
            }

            var $btn=$(this);$btn.prop('disabled',true).text('Saving...');

            post('save_appointment_outcome',{
                appointment_id:a._ID,
                outcome_id:o.id,
                outcome_note:noteVal
            }).then(function(r){
                if(!r.success){
                    $btn.prop('disabled',false).text('Save Outcome');
                    $('#hm-om-err').text(r.data&&r.data.message?r.data.message:'Failed to save outcome');
                    return;
                }

                // Close modal
                $('.hm-outcome-modal-bg').remove();
                $(document).off('.omopt .omfu .omclose .omsave .ombg');
                self.refresh();
                self.toast('Outcome saved: '+o.name);

                // Determine order flow based on service flags
                var isSalesOpp=!!a.sales_opportunity;
                var isIncomeBearing=!!a.income_bearing;
                var hasOrderFlow=false;

                if(isSalesOpp && o.is_invoiceable && a.patient_id){
                    // Sales Opportunity → forces new order creation
                    self._openOutcomeOrderModal(a,o);
                    hasOrderFlow=true;
                } else if(isIncomeBearing && a.patient_id){
                    // Income Bearing → order picker (existing orders or create new)
                    self._openOrderSelectModal(a,o);
                    hasOrderFlow=true;
                }

                // Chain: if follow-up required → open follow-up booking
                if(fuSvcId && a.patient_id){
                    setTimeout(function(){
                        self._openFollowUpBooking(a,fuSvcId);
                    }, hasOrderFlow ? 500 : 100);
                }
            }).fail(function(){
                $btn.prop('disabled',false).text('Save Outcome');
                $('#hm-om-err').text('Network error — please try again.');
            });
        });
    },

    // ── Outcome → New Order Modal ──
    _openOutcomeOrderModal:function(a,outcome){
        var self=this;

        // Load all products from DB for cascading dropdowns
        post('get_order_products',{}).then(function(r){
            if(!r.success){self.toast('Failed to load products');return;}
            var allProducts=r.data.products||[];
            var allServices=r.data.services||[];
            var allRanges=r.data.ranges||[];

            // Category map: item_type → display label
            var catMap={product:'Hearing Aid',service:'Service',accessory:'Accessory',consumable:'Consumable',bundled:'Bundled Item'};
            // Get distinct categories from products
            var categories={};
            allProducts.forEach(function(p){
                var cat=p.item_type||'product';
                if(!categories[cat])categories[cat]=catMap[cat]||cat;
            });
            if(allServices.length)categories['service']='Service';

            // ── Build Modal HTML ──
            var h='<div class="hm-modal-bg hm-modal-bg--top open" style="z-index:100000">';
            h+='<div class="hm-modal" style="max-width:640px;border-radius:12px;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,.25)">';

            // Header
            h+='<div class="hm-modal-hd" style="padding:16px 20px;display:flex;justify-content:space-between;align-items:center">';
            h+='<div><h3 style="margin:0;font-size:16px">New Order</h3>';
            h+='<div style="font-size:12px;color:var(--hm-font-sub);margin-top:2px">'+esc(a.patient_name||'')+' — '+esc(outcome.name)+'</div></div>';
            h+='<button class="hm-close hm-oo-close">×</button></div>';

            // Body
            h+='<div style="padding:20px;max-height:70vh;overflow-y:auto;background:#fff">';

            // Hidden fields
            h+='<input type="hidden" id="hm-oo-pid" value="'+a.patient_id+'">';
            h+='<input type="hidden" id="hm-oo-aid" value="'+a._ID+'">';

            // Step 1: Product Type
            h+='<div style="margin-bottom:14px"><label style="font-size:11px;font-weight:700;color:#334155;text-transform:uppercase;letter-spacing:.3px;display:block;margin-bottom:4px">Product Type</label>';
            h+='<select id="hm-oo-cat" class="hm-inp" style="font-size:13px;padding:8px 10px;border-radius:6px;border:1.5px solid #e2e8f0;width:100%"><option value="">— Select Category —</option>';
            Object.keys(categories).forEach(function(k){
                h+='<option value="'+k+'">'+esc(categories[k])+'</option>';
            });
            h+='</select></div>';

            // Step 2: Manufacturer (hearing aids only)
            h+='<div id="hm-oo-mfr-wrap" style="display:none;margin-bottom:14px"><label style="font-size:11px;font-weight:700;color:#334155;text-transform:uppercase;letter-spacing:.3px;display:block;margin-bottom:4px">Manufacturer</label>';
            h+='<select id="hm-oo-mfr" class="hm-inp" style="font-size:13px;padding:8px 10px;border-radius:6px;border:1.5px solid #e2e8f0;width:100%"><option value="">— Select Manufacturer —</option></select></div>';

            // Step 3: Style (hearing aids only)
            h+='<div id="hm-oo-style-wrap" style="display:none;margin-bottom:14px"><label style="font-size:11px;font-weight:700;color:#334155;text-transform:uppercase;letter-spacing:.3px;display:block;margin-bottom:4px">Style</label>';
            h+='<select id="hm-oo-style" class="hm-inp" style="font-size:13px;padding:8px 10px;border-radius:6px;border:1.5px solid #e2e8f0;width:100%"><option value="">— Select Style —</option></select></div>';

            // Step 4: Product Name
            h+='<div id="hm-oo-prod-wrap" style="display:none;margin-bottom:14px"><label style="font-size:11px;font-weight:700;color:#334155;text-transform:uppercase;letter-spacing:.3px;display:block;margin-bottom:4px">Product Name</label>';
            h+='<select id="hm-oo-prod" class="hm-inp" style="font-size:13px;padding:8px 10px;border-radius:6px;border:1.5px solid #e2e8f0;width:100%"><option value="">— Select Product —</option></select></div>';

            // Step 5: Tech Level (auto-shown for hearing aids)
            h+='<div id="hm-oo-tech-wrap" style="display:none;margin-bottom:14px"><label style="font-size:11px;font-weight:700;color:#334155;text-transform:uppercase;letter-spacing:.3px;display:block;margin-bottom:4px">Tech Level</label>';
            h+='<div id="hm-oo-tech" style="font-size:13px;font-weight:600;color:#0f172a;padding:8px 10px;background:#f8fafc;border-radius:6px;border:1px solid #e2e8f0">—</div></div>';

            // Ear Side
            h+='<div id="hm-oo-ear-wrap" style="display:none;margin-bottom:14px"><label style="font-size:11px;font-weight:700;color:#334155;text-transform:uppercase;letter-spacing:.3px;display:block;margin-bottom:4px">Ear</label>';
            h+='<select id="hm-oo-ear" class="hm-inp" style="font-size:13px;padding:8px 10px;border-radius:6px;border:1.5px solid #e2e8f0;width:100%">';
            h+='<option value="">— Select —</option><option value="Left">Left</option><option value="Right">Right</option><option value="Binaural">Binaural (both)</option>';
            h+='</select></div>';

            // Charger option (hearing aids only)
            h+='<div id="hm-oo-charger-wrap" style="display:none;margin-bottom:14px;padding:12px 14px;background:#fffbeb;border:1px solid #fde68a;border-radius:8px">';
            h+='<label style="display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer;color:#92400e;font-weight:600"><input type="checkbox" id="hm-oo-charger"> Include Charger</label></div>';

            // Speaker (hearing aids only) — options from bundled products
            var speakerProds=allProducts.filter(function(p){return p.item_type==='bundled'&&p.bundled_category==='Speaker';});
            var spkSizes={},spkPowers={};
            speakerProds.forEach(function(p){
                if(p.speaker_length!==null&&p.speaker_length!=='')spkSizes[p.speaker_length]=1;
                if(p.speaker_power)spkPowers[p.speaker_power]=1;
            });
            h+='<div id="hm-oo-speaker-wrap" style="display:none;margin-bottom:14px">';
            h+='<div style="display:flex;gap:10px">';
            h+='<div style="flex:1"><label style="font-size:11px;font-weight:700;color:#334155;text-transform:uppercase;letter-spacing:.3px;display:block;margin-bottom:4px">Speaker Size</label>';
            h+='<select id="hm-oo-speaker-size" class="hm-inp" style="font-size:13px;padding:8px 10px;border-radius:6px;border:1.5px solid #e2e8f0;width:100%">';
            h+='<option value="">—</option>';
            Object.keys(spkSizes).sort(function(a,b){return Number(a)-Number(b);}).forEach(function(v){h+='<option value="'+v+'">'+v+'</option>';});
            h+='</select></div>';
            h+='<div style="flex:1"><label style="font-size:11px;font-weight:700;color:#334155;text-transform:uppercase;letter-spacing:.3px;display:block;margin-bottom:4px">Speaker Type</label>';
            h+='<select id="hm-oo-speaker-type" class="hm-inp" style="font-size:13px;padding:8px 10px;border-radius:6px;border:1.5px solid #e2e8f0;width:100%">';
            h+='<option value="">—</option>';
            Object.keys(spkPowers).sort().forEach(function(v){h+='<option value="'+esc(v)+'">'+esc(v)+'</option>';});
            h+='</select></div>';
            h+='</div></div>';

            // Dome (hearing aids only) — options from bundled products
            var domeProds=allProducts.filter(function(p){return p.item_type==='bundled'&&p.bundled_category==='Dome';});
            var dmSizes={},dmTypes={};
            domeProds.forEach(function(p){
                if(p.dome_size)dmSizes[p.dome_size]=1;
                if(p.dome_type)dmTypes[p.dome_type]=1;
            });
            h+='<div id="hm-oo-dome-wrap" style="display:none;margin-bottom:14px">';
            h+='<div style="display:flex;gap:10px">';
            h+='<div style="flex:1"><label style="font-size:11px;font-weight:700;color:#334155;text-transform:uppercase;letter-spacing:.3px;display:block;margin-bottom:4px">Dome Size</label>';
            h+='<select id="hm-oo-dome-size" class="hm-inp" style="font-size:13px;padding:8px 10px;border-radius:6px;border:1.5px solid #e2e8f0;width:100%">';
            h+='<option value="">—</option>';
            Object.keys(dmSizes).sort().forEach(function(v){h+='<option value="'+esc(v)+'">'+esc(v)+'</option>';});
            h+='</select></div>';
            h+='<div style="flex:1"><label style="font-size:11px;font-weight:700;color:#334155;text-transform:uppercase;letter-spacing:.3px;display:block;margin-bottom:4px">Dome Type</label>';
            h+='<select id="hm-oo-dome-type" class="hm-inp" style="font-size:13px;padding:8px 10px;border-radius:6px;border:1.5px solid #e2e8f0;width:100%">';
            h+='<option value="">—</option>';
            Object.keys(dmTypes).sort().forEach(function(v){h+='<option value="'+esc(v)+'">'+esc(v)+'</option>';});
            h+='</select></div>';
            h+='</div></div>';

            // Add Item button
            h+='<div id="hm-oo-add-wrap" style="display:none;margin-bottom:16px;text-align:right">';
            h+='<button type="button" id="hm-oo-add-item" class="hm-btn hm-btn--secondary" style="font-size:12px;padding:6px 14px;border-radius:6px">+ Add to Order</button></div>';

            // Items Table
            h+='<div id="hm-oo-items" style="margin-bottom:14px"></div>';

            // Discount — toggle % / €
            h+='<div id="hm-oo-disc-wrap" style="margin-bottom:14px">';
            h+='<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px">';
            h+='<label style="font-size:11px;font-weight:700;color:#334155;text-transform:uppercase;letter-spacing:.3px">Discount</label>';
            h+='<div style="display:inline-flex;border:1.5px solid #e2e8f0;border-radius:6px;overflow:hidden">';
            h+='<button type="button" class="hm-oo-disc-mode" data-mode="pct" style="padding:4px 12px;font-size:11px;font-weight:700;cursor:pointer;border:none;background:#0f172a;color:#fff">%</button>';
            h+='<button type="button" class="hm-oo-disc-mode" data-mode="eur" style="padding:4px 12px;font-size:11px;font-weight:700;cursor:pointer;border:none;background:#fff;color:#334155">€</button>';
            h+='</div></div>';
            h+='<div style="display:flex;gap:10px;align-items:center">';
            h+='<input type="range" id="hm-oo-disc-slider" min="0" max="100" value="0" step="1" style="flex:1;accent-color:#f59e0b">';
            h+='<input type="number" id="hm-oo-disc" class="hm-inp" value="0" min="0" max="100" step="1" style="width:80px;font-size:13px;padding:6px 8px;border-radius:6px;border:1.5px solid #e2e8f0;text-align:center">';
            h+='<span id="hm-oo-disc-unit" style="font-size:13px;font-weight:600;color:#64748b;min-width:14px">%</span>';
            h+='</div></div>';

            // PRSI Grant
            h+='<div id="hm-oo-prsi-wrap" style="margin-bottom:14px">';
            h+='<div style="padding:12px 14px;background:#f0fdfe;border:1px solid #a5f3fc;border-radius:8px">';
            h+='<label style="font-size:11px;font-weight:700;color:#0e7490;text-transform:uppercase;letter-spacing:.3px;display:block;margin-bottom:8px">PRSI Grant</label>';
            h+='<div style="display:flex;gap:16px;align-items:center;flex-wrap:wrap">';
            h+='<label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer;color:#334155"><input type="checkbox" id="hm-oo-prsi-l"> Left ear — €500</label>';
            h+='<label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer;color:#334155"><input type="checkbox" id="hm-oo-prsi-r"> Right ear — €500</label>';
            h+='</div></div></div>';

            // Totals
            h+='<div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:12px 14px;margin-bottom:14px">';
            h+='<div style="display:flex;justify-content:space-between;font-size:13px;color:#64748b;margin-bottom:4px"><span>Subtotal</span><span id="hm-oo-sub">€0.00</span></div>';
            h+='<div style="display:flex;justify-content:space-between;font-size:13px;color:#64748b;margin-bottom:4px"><span>VAT</span><span id="hm-oo-vat">€0.00</span></div>';
            h+='<div id="hm-oo-disc-row" style="display:none;justify-content:space-between;font-size:13px;color:#dc2626;margin-bottom:4px"><span>Discount</span><span id="hm-oo-disc-amt">−€0.00</span></div>';
            h+='<div id="hm-oo-prsi-row" style="display:none;justify-content:space-between;font-size:13px;color:#059669;margin-bottom:4px"><span>PRSI Deduction</span><span id="hm-oo-prsi-amt">−€0.00</span></div>';
            h+='<div style="display:flex;justify-content:space-between;font-size:15px;font-weight:700;color:#0f172a;border-top:1px solid #e2e8f0;padding-top:8px;margin-top:4px"><span>Patient Pays</span><span id="hm-oo-total">€0.00</span></div>';
            h+='</div>';

            // Notes
            h+='<div style="margin-bottom:14px"><label style="font-size:11px;font-weight:700;color:#334155;text-transform:uppercase;letter-spacing:.3px;display:block;margin-bottom:4px">Order Notes</label>';
            h+='<textarea id="hm-oo-notes" class="hm-inp" rows="2" style="font-size:13px;padding:8px 10px;border-radius:6px;border:1.5px solid #e2e8f0;width:100%;resize:vertical" placeholder="Dome size, speaker requirements..."></textarea></div>';

            h+='</div>'; // end body

            // Footer
            h+='<div style="padding:12px 20px;border-top:1px solid #e2e8f0;background:#fff;display:flex;justify-content:space-between;align-items:center">';
            h+='<span id="hm-oo-err" style="color:#ef4444;font-size:12px"></span>';
            h+='<div style="display:flex;gap:8px">';
            h+='<button class="hm-btn hm-oo-close" style="font-size:13px">Cancel</button>';
            h+='<button class="hm-btn" id="hm-oo-pay" style="font-size:13px;background:#059669;color:#fff;border:none;padding:8px 16px;border-radius:6px;cursor:pointer">Take Payment</button>';
            h+='<button class="hm-btn hm-btn--primary hm-oo-save" style="font-size:13px;padding:8px 16px">Submit Order</button>';
            h+='</div></div>';

            h+='</div></div>';

            $('body').append(h);

            // ── State ──
            var orderItems=[];
            var selectedProduct=null;
            var discountMode='pct';

            // ── Cascading Logic ──

            // Category change
            $(document).off('change.oocat').on('change.oocat','#hm-oo-cat',function(){
                var cat=$(this).val();
                // Reset downstream
                $('#hm-oo-mfr-wrap,#hm-oo-style-wrap,#hm-oo-prod-wrap,#hm-oo-tech-wrap,#hm-oo-ear-wrap,#hm-oo-add-wrap,#hm-oo-charger-wrap,#hm-oo-speaker-wrap,#hm-oo-dome-wrap').hide();
                $('#hm-oo-mfr,#hm-oo-style,#hm-oo-prod').val('');
                $('#hm-oo-charger').prop('checked',false);
                $('#hm-oo-speaker-size,#hm-oo-speaker-type,#hm-oo-dome-size,#hm-oo-dome-type').val('');
                selectedProduct=null;

                if(!cat)return;

                if(cat==='product'){
                    // Hearing Aid: show manufacturer dropdown
                    var mfrs={};
                    allProducts.filter(function(p){return p.item_type==='product';}).forEach(function(p){
                        if(p.manufacturer_id&&p.manufacturer_name)mfrs[p.manufacturer_id]=p.manufacturer_name;
                    });
                    var opts='<option value="">— Select Manufacturer —</option>';
                    Object.keys(mfrs).sort(function(a,b){return mfrs[a].localeCompare(mfrs[b]);}).forEach(function(id){
                        opts+='<option value="'+id+'">'+esc(mfrs[id])+'</option>';
                    });
                    $('#hm-oo-mfr').html(opts);
                    $('#hm-oo-mfr-wrap').show();
                } else if(cat==='service'){
                    // Service: show product name dropdown directly with services
                    var opts='<option value="">— Select Service —</option>';
                    allServices.forEach(function(s){
                        opts+='<option value="svc-'+s.id+'" data-name="'+esc(s.service_name)+'" data-price="'+(s.default_price||0)+'" data-vat="13.5">'+esc(s.service_name)+' — €'+(parseFloat(s.default_price)||0).toFixed(2)+'</option>';
                    });
                    $('#hm-oo-prod').html(opts);
                    $('#hm-oo-prod-wrap').show();
                } else {
                    // Accessory / Consumable / Bundled: show product name dropdown
                    var filtered=allProducts.filter(function(p){return p.item_type===cat;});
                    var opts='<option value="">— Select Product —</option>';
                    filtered.forEach(function(p){
                        var nm=(p.manufacturer_name?p.manufacturer_name+' ':'')+(p.product_name||'');
                        opts+='<option value="'+p.id+'" data-name="'+esc(nm)+'" data-price="'+(p.retail_price||0)+'" data-vat="'+(p.vat_category==='Consumables'?23:0)+'">'+esc(nm)+' — €'+(parseFloat(p.retail_price)||0).toFixed(2)+'</option>';
                    });
                    $('#hm-oo-prod').html(opts);
                    $('#hm-oo-prod-wrap').show();
                }
            });

            // Manufacturer change (hearing aids)
            $(document).off('change.oomfr').on('change.oomfr','#hm-oo-mfr',function(){
                var mfr=$(this).val();
                $('#hm-oo-style-wrap,#hm-oo-prod-wrap,#hm-oo-tech-wrap,#hm-oo-ear-wrap,#hm-oo-add-wrap').hide();
                $('#hm-oo-style,#hm-oo-prod').val('');
                selectedProduct=null;
                if(!mfr)return;

                // Get distinct styles for this manufacturer
                var styles={};
                allProducts.filter(function(p){return p.item_type==='product'&&String(p.manufacturer_id)===String(mfr);}).forEach(function(p){
                    if(p.style)styles[p.style]=1;
                });
                var opts='<option value="">— Select Style —</option>';
                Object.keys(styles).sort().forEach(function(s){
                    opts+='<option value="'+esc(s)+'">'+esc(s)+'</option>';
                });
                $('#hm-oo-style').html(opts);
                $('#hm-oo-style-wrap').show();
            });

            // Style change (hearing aids)
            $(document).off('change.oostyle').on('change.oostyle','#hm-oo-style',function(){
                var style=$(this).val();
                var mfr=$('#hm-oo-mfr').val();
                $('#hm-oo-prod-wrap,#hm-oo-tech-wrap,#hm-oo-ear-wrap,#hm-oo-add-wrap').hide();
                $('#hm-oo-prod').val('');
                selectedProduct=null;
                if(!style)return;

                // Get products matching this manufacturer + style
                var filtered=allProducts.filter(function(p){
                    return p.item_type==='product'&&String(p.manufacturer_id)===String(mfr)&&p.style===style;
                });
                var opts='<option value="">— Select Product —</option>';
                filtered.forEach(function(p){
                    var nm=(p.manufacturer_name?p.manufacturer_name+' ':'')+(p.product_name||'')+' '+(p.style||'')+' ('+(p.tech_level||'—')+')';
                    opts+='<option value="'+p.id+'" data-name="'+esc(nm)+'" data-price="'+(p.retail_price||0)+'" data-tech="'+esc(p.tech_level||'')+'" data-vat="0">'+esc(nm)+' — €'+(parseFloat(p.retail_price)||0).toFixed(2)+'</option>';
                });
                $('#hm-oo-prod').html(opts);
                $('#hm-oo-prod-wrap').show();
            });

            // Product selection
            $(document).off('change.ooprod').on('change.ooprod','#hm-oo-prod',function(){
                var val=$(this).val();
                if(!val){
                    $('#hm-oo-tech-wrap,#hm-oo-ear-wrap,#hm-oo-add-wrap').hide();
                    selectedProduct=null;
                    return;
                }
                var opt=$(this).find('option:selected');
                var cat=$('#hm-oo-cat').val();
                selectedProduct={
                    id:val,
                    type:val.toString().indexOf('svc-')===0?'service':(cat||'product'),
                    name:opt.data('name')||opt.text(),
                    unit_price:parseFloat(opt.data('price'))||0,
                    vat_rate:parseFloat(opt.data('vat'))||0,
                    tech_level:opt.data('tech')||'',
                    ear:''
                };

                // Show tech level for hearing aids
                if(cat==='product'&&selectedProduct.tech_level){
                    $('#hm-oo-tech').text(selectedProduct.tech_level);
                    $('#hm-oo-tech-wrap').show();
                } else {
                    $('#hm-oo-tech-wrap').hide();
                }

                // Show ear selector + bundled options for hearing aids
                if(cat==='product'){
                    $('#hm-oo-ear-wrap,#hm-oo-charger-wrap,#hm-oo-speaker-wrap,#hm-oo-dome-wrap').show();
                } else {
                    $('#hm-oo-ear-wrap,#hm-oo-charger-wrap,#hm-oo-speaker-wrap,#hm-oo-dome-wrap').hide();
                }

                $('#hm-oo-add-wrap').show();
            });

            // Add item button
            $(document).off('click.ooadditem').on('click.ooadditem','#hm-oo-add-item',function(){
                if(!selectedProduct){$('#hm-oo-err').text('Select a product first.');return;}
                var cat=$('#hm-oo-cat').val();
                var ear=$('#hm-oo-ear').val()||'';
                if(cat==='product'&&!ear){$('#hm-oo-err').text('Please select which ear.');return;}
                $('#hm-oo-err').text('');

                var item=$.extend({},selectedProduct);
                item.ear=ear;
                item.qty=cat==='product'&&ear==='Binaural'?2:1;

                // Capture bundled options for hearing aids
                if(cat==='product'){
                    item.speaker_size=$('#hm-oo-speaker-size').val()||'';
                    item.speaker_type=$('#hm-oo-speaker-type').val()||'';
                    item.dome_size=$('#hm-oo-dome-size').val()||'';
                    item.dome_type=$('#hm-oo-dome-type').val()||'';
                }

                item.vat_amount=parseFloat(((item.unit_price*item.qty)*(item.vat_rate/100)).toFixed(2));
                item.line_total=parseFloat(((item.unit_price*item.qty)+item.vat_amount).toFixed(2));
                item._uid=Date.now()+Math.random();
                orderItems.push(item);

                // Auto-add charger if checked
                if(cat==='product'&&$('#hm-oo-charger').is(':checked')){
                    var chargerProd=allProducts.filter(function(p){return p.item_type==='accessory'&&/charger/i.test(p.product_name);})[0];
                    var chargerItem={
                        id:chargerProd?chargerProd.id:'charger',
                        type:'accessory',
                        name:chargerProd?((chargerProd.manufacturer_name?chargerProd.manufacturer_name+' ':'')+chargerProd.product_name):'Charger',
                        unit_price:chargerProd?parseFloat(chargerProd.retail_price)||0:0,
                        vat_rate:0,
                        tech_level:'',
                        ear:ear==='Binaural'?'Both':ear,
                        qty:1,
                        _uid:Date.now()+Math.random()
                    };
                    chargerItem.vat_amount=parseFloat(((chargerItem.unit_price*chargerItem.qty)*(chargerItem.vat_rate/100)).toFixed(2));
                    chargerItem.line_total=parseFloat(((chargerItem.unit_price*chargerItem.qty)+chargerItem.vat_amount).toFixed(2));
                    orderItems.push(chargerItem);
                }

                renderItems();
                updateTotals();

                // Reset selectors for next item
                selectedProduct=null;
                $('#hm-oo-cat').val('').trigger('change.oocat');
            });

            function renderItems(){
                if(!orderItems.length){
                    $('#hm-oo-items').html('<div style="color:#94a3b8;font-size:12px;padding:12px 0;text-align:center">No items added yet.</div>');
                    return;
                }

                var th='<table style="width:100%;font-size:12px;border-collapse:collapse;border:1px solid #e2e8f0;border-radius:6px;overflow:hidden"><thead><tr style="background:#f8fafc;border-bottom:1px solid #e2e8f0">';
                th+='<th style="text-align:left;padding:6px 8px;font-weight:600;color:#334155">Item</th>';
                th+='<th style="text-align:center;padding:6px 8px;font-weight:600;color:#334155">Ear</th>';
                th+='<th style="text-align:center;padding:6px 8px;font-weight:600;color:#334155">Qty</th>';
                th+='<th style="text-align:right;padding:6px 8px;font-weight:600;color:#334155">Price</th>';
                th+='<th style="width:36px"></th></tr></thead><tbody>';
                orderItems.forEach(function(it,idx){
                    var details=[];
                    if(it.speaker_size)details.push('Speaker: '+it.speaker_size+(it.speaker_type?' '+it.speaker_type:''));
                    if(it.dome_size)details.push('Dome: '+it.dome_size+(it.dome_type?' '+it.dome_type:''));
                    th+='<tr style="border-bottom:1px solid #f1f5f9">';
                    th+='<td style="padding:6px 8px;color:#0f172a">'+esc(it.name);
                    if(details.length)th+='<div style="font-size:10px;color:#64748b;margin-top:2px">'+esc(details.join(' | '))+'</div>';
                    th+='</td>';
                    th+='<td style="padding:6px 8px;text-align:center;color:#64748b">'+(it.ear||'—')+'</td>';
                    th+='<td style="padding:6px 8px;text-align:center;color:#64748b">'+it.qty+'</td>';
                    th+='<td style="padding:6px 8px;text-align:right;font-weight:600;color:#0f172a">€'+(it.unit_price*it.qty).toFixed(2)+'</td>';
                    th+='<td style="padding:6px 8px"><button class="hm-oo-rem" data-idx="'+idx+'" style="border:none;background:none;color:#ef4444;cursor:pointer;font-size:14px;font-weight:700">×</button></td>';
                    th+='</tr>';
                });
                th+='</tbody></table>';
                $('#hm-oo-items').html(th);
            }

            function updateTotals(){
                var sub=0,vat=0;
                orderItems.forEach(function(it){sub+=it.unit_price*it.qty;vat+=it.vat_amount;});
                var discVal=parseFloat($('#hm-oo-disc').val())||0;
                var disc=0;
                if(discountMode==='pct'){
                    disc=discVal>0?Math.round(sub*(Math.min(discVal,100)/100)*100)/100:0;
                } else {
                    disc=Math.min(discVal,sub+vat);
                }
                var prsi=($('#hm-oo-prsi-l').is(':checked')?500:0)+($('#hm-oo-prsi-r').is(':checked')?500:0);
                var total=Math.max(0,sub+vat-disc-prsi);
                $('#hm-oo-sub').text('€'+sub.toFixed(2));
                $('#hm-oo-vat').text('€'+vat.toFixed(2));
                $('#hm-oo-disc-amt').text('−€'+disc.toFixed(2));
                if(disc>0){$('#hm-oo-disc-row').css('display','flex');}else{$('#hm-oo-disc-row').hide();}
                if(prsi>0){$('#hm-oo-prsi-row').css('display','flex');}else{$('#hm-oo-prsi-row').hide();}
                $('#hm-oo-prsi-amt').text('−€'+prsi.toFixed(2));
                $('#hm-oo-total').text('€'+total.toFixed(2));
            }

            // Remove item
            $(document).off('click.oorem').on('click.oorem','.hm-oo-rem',function(){
                orderItems.splice(parseInt($(this).data('idx')),1);
                renderItems();updateTotals();
            });

            // PRSI / Discount change → update totals
            $(document).off('change.ooprsi').on('change.ooprsi','#hm-oo-prsi-l,#hm-oo-prsi-r',updateTotals);
            $(document).off('input.oodisc').on('input.oodisc','#hm-oo-disc',function(){$('#hm-oo-disc-slider').val($(this).val());updateTotals();});
            $(document).off('input.ooslider').on('input.ooslider','#hm-oo-disc-slider',function(){$('#hm-oo-disc').val($(this).val());updateTotals();});
            $(document).off('click.oodiscmode').on('click.oodiscmode','.hm-oo-disc-mode',function(){
                var mode=$(this).data('mode');
                discountMode=mode;
                $('.hm-oo-disc-mode').css({background:'#fff',color:'#334155'});
                $(this).css({background:'#0f172a',color:'#fff'});
                if(mode==='pct'){
                    $('#hm-oo-disc-unit').text('%');
                    $('#hm-oo-disc-slider').attr({max:100,step:1});
                    $('#hm-oo-disc').attr({max:100,step:1});
                } else {
                    var sub=0;orderItems.forEach(function(it){sub+=it.unit_price*it.qty;});
                    $('#hm-oo-disc-unit').text('€');
                    $('#hm-oo-disc-slider').attr({max:Math.ceil(sub)||10000,step:10});
                    $('#hm-oo-disc').attr({max:Math.ceil(sub)||10000,step:10});
                }
                $('#hm-oo-disc').val(0);$('#hm-oo-disc-slider').val(0);
                updateTotals();
            });

            // Close
            $(document).off('click.ooclose').on('click.ooclose','.hm-oo-close',function(e){
                e.stopPropagation();$('.hm-modal-bg--top').remove();
                $(document).off('.oocat .oomfr .oostyle .ooprod .ooadditem .oorem .ooclose .oosave .oopay .ooprsi .oodisc .ooslider .oodiscmode');
            });
            // Click-away disabled — use Cancel or × to close

            // ── Submit Order ──
            $(document).off('click.oosave').on('click.oosave','.hm-oo-save',function(){
                if(!orderItems.length){$('#hm-oo-err').text('Please add at least one item.');return;}
                var $btn=$(this);$btn.prop('disabled',true).text('Submitting...');
                var discVal=parseFloat($('#hm-oo-disc').val())||0;
                post('create_outcome_order',{
                    patient_id:$('#hm-oo-pid').val(),
                    appointment_id:$('#hm-oo-aid').val(),
                    items_json:JSON.stringify(orderItems),
                    notes:$('#hm-oo-notes').val()||'',
                    prsi_left:$('#hm-oo-prsi-l').is(':checked')?1:0,
                    prsi_right:$('#hm-oo-prsi-r').is(':checked')?1:0,
                    discount_pct:discountMode==='pct'?discVal:0,
                    discount_euro:discountMode==='eur'?discVal:0,
                    payment_method:''
                }).then(function(r){
                    if(r.success){
                        $('.hm-modal-bg--top').remove();
                        $(document).off('.oocat .oomfr .oostyle .ooprod .ooadditem .oorem .ooclose .oosave .oopay .oobg .ooprsi .oodisc .ooslider .oodiscmode');
                        self.toast('Order '+r.data.order_number+' submitted for approval');
                    } else {
                        $btn.prop('disabled',false).text('Submit Order');
                        $('#hm-oo-err').text(r.data&&r.data.message?r.data.message:'Failed to create order');
                    }
                }).fail(function(){
                    $btn.prop('disabled',false).text('Submit Order');
                    $('#hm-oo-err').text('Network error');
                });
            });

            // ── Take Payment → creates order then shows payment modal ──
            $(document).off('click.oopay').on('click.oopay','#hm-oo-pay',function(){
                if(!orderItems.length){$('#hm-oo-err').text('Please add at least one item.');return;}
                var $btn=$(this);$btn.prop('disabled',true).text('Processing...');
                var discVal=parseFloat($('#hm-oo-disc').val())||0;
                post('create_outcome_order',{
                    patient_id:$('#hm-oo-pid').val(),
                    appointment_id:$('#hm-oo-aid').val(),
                    items_json:JSON.stringify(orderItems),
                    notes:$('#hm-oo-notes').val()||'',
                    prsi_left:$('#hm-oo-prsi-l').is(':checked')?1:0,
                    prsi_right:$('#hm-oo-prsi-r').is(':checked')?1:0,
                    discount_pct:discountMode==='pct'?discVal:0,
                    discount_euro:discountMode==='eur'?discVal:0,
                    payment_method:''
                }).then(function(r){
                    if(r.success){
                        // Close order modal
                        $('.hm-modal-bg--top').remove();
                        $(document).off('.oocat .oomfr .oostyle .ooprod .ooadditem .oorem .ooclose .oosave .oopay .oobg .ooprsi .oodisc .ooslider .oodiscmode');

                        // Open payment modal
                        self._openPaymentModal(r.data.order_id,r.data.order_number,r.data.grand_total,a.patient_name||'');
                    } else {
                        $btn.prop('disabled',false).text('Take Payment');
                        $('#hm-oo-err').text(r.data&&r.data.message?r.data.message:'Failed');
                    }
                }).fail(function(){
                    $btn.prop('disabled',false).text('Take Payment');
                    $('#hm-oo-err').text('Network error');
                });
            });

        }).fail(function(){
            self.toast('Failed to load product data');
        });
    },

    // ── Payment Modal ──
    _openPaymentModal:function(orderId,orderNumber,grandTotal,patientName){
        var self=this;
        var h='<div class="hm-modal-bg hm-modal-bg--top open" style="z-index:100001">';
        h+='<div class="hm-modal" style="max-width:420px;border-radius:12px;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,.25)">';

        h+='<div class="hm-modal-hd" style="padding:16px 20px;display:flex;justify-content:space-between;align-items:center">';
        h+='<div><h3 style="margin:0;font-size:16px">Take Payment</h3>';
        h+='<div style="font-size:12px;color:var(--hm-font-sub);margin-top:2px">'+esc(patientName)+' — '+esc(orderNumber)+'</div></div>';
        h+='<button class="hm-close hm-pay-close">×</button></div>';

        h+='<div style="padding:20px;background:#fff">';
        h+='<div style="text-align:center;margin-bottom:16px"><div style="font-size:12px;color:#64748b;text-transform:uppercase;letter-spacing:.3px">Total Due</div>';
        h+='<div style="font-size:28px;font-weight:700;color:#059669">€'+parseFloat(grandTotal).toFixed(2)+'</div></div>';

        h+='<div style="margin-bottom:14px"><label style="font-size:11px;font-weight:700;color:#334155;text-transform:uppercase;letter-spacing:.3px;display:block;margin-bottom:4px">Amount (€)</label>';
        h+='<input type="number" id="hm-pay-amt" class="hm-inp" step="0.01" min="0" value="'+parseFloat(grandTotal).toFixed(2)+'" style="font-size:15px;padding:10px 12px;border-radius:6px;border:1.5px solid #e2e8f0;width:100%;font-weight:600"></div>';

        h+='<div style="margin-bottom:14px"><label style="font-size:11px;font-weight:700;color:#334155;text-transform:uppercase;letter-spacing:.3px;display:block;margin-bottom:4px">Payment Method</label>';
        h+='<select id="hm-pay-method" class="hm-inp" style="font-size:13px;padding:8px 10px;border-radius:6px;border:1.5px solid #e2e8f0;width:100%">';
        h+='<option value="">— Select —</option><option value="Card">Card</option><option value="Cash">Cash</option>';
        h+='<option value="Bank Transfer">Bank Transfer</option><option value="Cheque">Cheque</option>';
        h+='</select></div>';

        h+='<div id="hm-pay-err" style="color:#ef4444;font-size:12px;margin-bottom:8px"></div>';

        h+='<button id="hm-pay-confirm" class="hm-btn hm-btn--primary" style="width:100%;padding:10px;font-size:14px;font-weight:600;border-radius:6px">Confirm Payment</button>';
        h+='</div></div></div>';

        $('body').append(h);

        $(document).off('click.payclose').on('click.payclose','.hm-pay-close',function(e){
            e.stopPropagation();$('.hm-modal-bg--top').remove();
            $(document).off('.payclose .payconfirm');
            self.toast('Order '+orderNumber+' submitted for approval (no payment taken)');
        });

        $(document).off('click.payconfirm').on('click.payconfirm','#hm-pay-confirm',function(){
            var amt=parseFloat($('#hm-pay-amt').val())||0;
            var method=$('#hm-pay-method').val();
            if(!amt||amt<=0){$('#hm-pay-err').text('Enter a valid amount.');return;}
            if(!method){$('#hm-pay-err').text('Select a payment method.');return;}

            var $btn=$(this);$btn.prop('disabled',true).text('Processing...');
            post('record_order_payment',{
                order_id:orderId,
                amount:amt,
                payment_method:method
            }).then(function(r){
                if(r.success){
                    $('.hm-modal-bg--top').remove();
                    $(document).off('.payclose .payconfirm');
                    self.toast('Payment of €'+amt.toFixed(2)+' recorded on '+orderNumber);
                } else {
                    $btn.prop('disabled',false).text('Confirm Payment');
                    $('#hm-pay-err').text(r.data&&r.data.message?r.data.message:'Failed');
                }
            }).fail(function(){
                $btn.prop('disabled',false).text('Confirm Payment');
                $('#hm-pay-err').text('Network error');
            });
        });
    },

    // ── Order Select Modal (Income-Bearing appointments) ──
    _openOrderSelectModal:function(a,outcome){
        var self=this;

        // Fetch patient's existing orders in pipeline
        post('get_patient_orders',{patient_id:a.patient_id}).then(function(r){
            if(!r.success){self.toast('Failed to load orders');return;}
            var orders=r.data||[];

            var h='<div class="hm-modal-bg hm-modal-bg--top open" style="z-index:100000">';
            h+='<div class="hm-modal" style="max-width:560px;border-radius:12px;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,.25)">';

            // Header
            h+='<div class="hm-modal-hd" style="padding:16px 20px;display:flex;justify-content:space-between;align-items:center">';
            h+='<div><h3 style="margin:0;font-size:16px">Select or Create Order</h3>';
            h+='<div style="font-size:12px;color:var(--hm-font-sub);margin-top:2px">'+esc(a.patient_name||'')+' — '+esc(outcome.name)+'</div></div>';
            h+='<button class="hm-close hm-osel-close">×</button></div>';

            // Body
            h+='<div style="padding:20px;max-height:70vh;overflow-y:auto;background:#fff">';

            if(orders.length){
                h+='<div style="font-size:11px;font-weight:700;color:#334155;text-transform:uppercase;letter-spacing:.3px;margin-bottom:8px">Existing Orders (Awaiting Fitting)</div>';
                orders.forEach(function(ord){
                    var bal=parseFloat(ord.balance_due)||0;
                    h+='<div class="hm-osel-order" data-oid="'+ord.id+'" data-onum="'+esc(ord.order_number)+'" data-total="'+ord.grand_total+'" data-bal="'+bal+'" ';
                    h+='style="border:1.5px solid #e2e8f0;border-radius:8px;padding:12px 14px;margin-bottom:8px;cursor:pointer;transition:all .15s;background:#fff">';
                    h+='<div style="display:flex;justify-content:space-between;align-items:flex-start">';
                    h+='<div>';
                    h+='<div style="font-size:14px;font-weight:700;color:#0f172a">'+esc(ord.order_number)+'</div>';
                    h+='<div style="font-size:11px;color:#64748b;margin-top:2px">'+esc(ord.order_date)+' — <span style="font-weight:600;color:#0369a1">'+esc(ord.status)+'</span></div>';
                    h+='<div style="font-size:12px;color:#475569;margin-top:4px;max-width:320px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">'+esc(ord.items_summary)+'</div>';
                    h+='</div>';
                    h+='<div style="text-align:right;flex-shrink:0">';
                    h+='<div style="font-size:14px;font-weight:700;color:#0f172a">€'+parseFloat(ord.grand_total).toFixed(2)+'</div>';
                    if(parseFloat(ord.deposit)>0){
                        h+='<div style="font-size:11px;color:#059669;margin-top:2px">Paid: €'+parseFloat(ord.deposit).toFixed(2)+'</div>';
                    }
                    h+='<div style="font-size:11px;color:#dc2626;margin-top:1px">Due: €'+bal.toFixed(2)+'</div>';
                    h+='</div></div></div>';
                });
                h+='<div style="border-top:1px solid #e2e8f0;margin:16px 0 12px"></div>';
            } else {
                h+='<div style="text-align:center;padding:20px 0;color:#94a3b8;font-size:13px">No existing orders awaiting fitting for this patient.</div>';
            }

            // Create New button
            h+='<button id="hm-osel-new" class="hm-btn hm-btn--primary" style="width:100%;padding:10px;font-size:13px;font-weight:600;border-radius:6px">+ Create New Order</button>';

            h+='</div>'; // end body

            // Footer
            h+='<div style="padding:12px 20px;border-top:1px solid #e2e8f0;background:#fff;text-align:right">';
            h+='<button class="hm-btn hm-osel-close" style="font-size:13px">Cancel</button>';
            h+='</div>';

            h+='</div></div>';
            $('body').append(h);

            // Hover effect on order cards
            $(document).off('mouseenter.osel mouseleave.osel').on('mouseenter.osel','.hm-osel-order',function(){
                $(this).css({'border-color':'#3b82f6','background':'#eff6ff'});
            }).on('mouseleave.osel','.hm-osel-order',function(){
                $(this).css({'border-color':'#e2e8f0','background':'#fff'});
            });

            // Select existing order → open payment modal
            $(document).off('click.oselorder').on('click.oselorder','.hm-osel-order',function(){
                var $card=$(this);
                var ordId=$card.data('oid');
                var ordNum=$card.data('onum');
                var bal=parseFloat($card.data('bal'))||0;

                // Close this modal
                $('.hm-modal-bg--top').remove();
                $(document).off('.oselorder .oselnew .oselclose .osel');

                // Open payment modal for this existing order
                self._openPaymentModal(ordId,ordNum,bal,a.patient_name||'');
            });

            // Create new order
            $(document).off('click.oselnew').on('click.oselnew','#hm-osel-new',function(){
                // Close this modal
                $('.hm-modal-bg--top').remove();
                $(document).off('.oselorder .oselnew .oselclose .osel');

                // Open the full order creation modal
                self._openOutcomeOrderModal(a,outcome);
            });

            // Close
            $(document).off('click.oselclose').on('click.oselclose','.hm-osel-close',function(e){
                e.stopPropagation();$('.hm-modal-bg--top').remove();
                $(document).off('.oselorder .oselnew .oselclose .osel');
            });
        }).fail(function(){
            self.toast('Failed to load patient orders');
        });
    },

    // ── Outcome → Follow-up Booking ──
    _openFollowUpBooking:function(a,serviceId){
        var self=this;
        // This re-uses the new appointment modal but pre-fills and locks the service
        var ready=$.Deferred();
        if(!self.services.length||!self.clinics.length||!self.dispensers.length){
            $.when(
                self.services.length?null:self.loadServices(),
                self.clinics.length?null:self.loadClinics(),
                self.dispensers.length?null:self.loadDispensers()
            ).always(function(){ready.resolve();});
        } else ready.resolve();

        ready.then(function(){
            var fuSvc=self.services.find(function(s){return parseInt(s.id)===parseInt(serviceId);});
            var svcName=fuSvc?fuSvc.name:'Follow-up';
            var cliOpts=self.clinics.map(function(c){return'<option value="'+c.id+'"'+(parseInt(c.id)===parseInt(a.clinic_id)?' selected':'')+'>'+esc(c.name)+'</option>';}).join('');
            var dispOpts=self.dispensers.map(function(d){return'<option value="'+d.id+'"'+(parseInt(d.id)===parseInt(a.dispenser_id)?' selected':'')+'>'+esc(d.name)+'</option>';}).join('');

            // Calculate default follow-up date (7 days from now)
            var fuDate=new Date();fuDate.setDate(fuDate.getDate()+7);
            var fuDateStr=fmt(fuDate);

            var h='<div class="hm-modal-bg hm-modal-bg--top open"><div class="hm-modal hm-modal--md" style="max-width:520px">';
            h+='<div class="hm-modal-hd" style="background:#22c55e;color:#fff;border-radius:12px 12px 0 0;padding:14px 20px">';
            h+='<div><h3 style="margin:0;color:#fff;font-size:16px">Book Follow-up Appointment</h3>';
            h+='<div style="font-size:12px;opacity:.85;margin-top:2px">'+esc(a.patient_name||'')+' — '+esc(svcName)+'</div></div>';
            h+='<button class="hm-close hm-fu-close" style="color:#fff">'+IC.x+'</button></div>';
            h+='<div class="hm-modal-body" style="padding:20px">';

            h+='<div class="hm-fld"><label>Patient</label><input class="hm-inp" value="'+esc(a.patient_name||'')+'" readonly style="background:#f8fafc"></div>';
            h+='<input type="hidden" id="hm-fu-pid" value="'+a.patient_id+'">';

            // Locked service type
            h+='<div class="hm-fld"><label>Appointment Type</label><input class="hm-inp" value="'+esc(svcName)+'" readonly style="background:#f0fdf4;border-color:#86efac;font-weight:600"></div>';
            h+='<input type="hidden" id="hm-fu-svc" value="'+serviceId+'">';

            h+='<div class="hm-row"><div class="hm-fld"><label>Clinic</label><select class="hm-inp" id="hm-fu-clinic">'+cliOpts+'</select></div>';
            h+='<div class="hm-fld"><label>Assignee</label><select class="hm-inp" id="hm-fu-disp">'+dispOpts+'</select></div></div>';

            h+='<div class="hm-row"><div class="hm-fld"><label>Date</label><input type="date" class="hm-inp" id="hm-fu-date" value="'+fuDateStr+'"></div>';
            h+='<div class="hm-fld"><label>Start Time</label><input type="time" class="hm-inp" id="hm-fu-time" value="'+(a.start_time||'09:00').substring(0,5)+'"></div></div>';

            h+='<div class="hm-row"><div class="hm-fld"><label>Duration</label><select class="hm-inp" id="hm-fu-dur">';
            [15,30,45,60,75,90,105,120].forEach(function(d){
                var sel=(fuSvc&&parseInt(fuSvc.duration)===d)?' selected':'';
                h+='<option value="'+d+'"'+sel+'>'+d+' min</option>';
            });
            h+='</select></div>';
            h+='<div class="hm-fld"><label>Status</label><select class="hm-inp" id="hm-fu-status"><option selected>Not Confirmed</option><option>Confirmed</option><option>Pending</option></select></div></div>';

            h+='<div class="hm-fld"><label>Notes</label><textarea class="hm-inp" id="hm-fu-notes" rows="2" placeholder="Follow-up notes...">Follow-up from appointment on '+a.appointment_date+'</textarea></div>';

            h+='</div>';
            h+='<div class="hm-modal-ft" style="padding:12px 20px;border-top:1px solid #e2e8f0"><span id="hm-fu-err" style="color:#ef4444;font-size:12px"></span><div class="hm-modal-acts"><button class="hm-btn hm-fu-close">Cancel</button><button class="hm-btn hm-btn--primary hm-fu-save">Book Follow-up</button></div></div>';
            h+='</div></div>';

            $('body').append(h);

            // Close
            $(document).off('click.fuclose').on('click.fuclose','.hm-fu-close',function(e){
                e.stopPropagation();$('.hm-modal-bg--top').remove();$(document).off('.fuclose .fusave');
            });

            // Save follow-up
            $(document).off('click.fusave').on('click.fusave','.hm-fu-save',function(){
                var fd=$('#hm-fu-date').val(),ft=$('#hm-fu-time').val();
                if(!fd||!ft){$('#hm-fu-err').text('Please select a date and time.');return;}
                var $btn=$(this);$btn.prop('disabled',true).text('Booking...');
                var fuData={
                    patient_id:$('#hm-fu-pid').val(),
                    service_id:$('#hm-fu-svc').val(),
                    clinic_id:$('#hm-fu-clinic').val(),
                    dispenser_id:$('#hm-fu-disp').val(),
                    status:$('#hm-fu-status').val(),
                    appointment_date:fd,
                    start_time:ft,
                    duration:$('#hm-fu-dur').val(),
                    location_type:'Clinic',
                    notes:$('#hm-fu-notes').val()||''
                };
                var doPost=function(skip){
                    var d=$.extend({},fuData);
                    if(skip)d.skip_double_book_check='1';
                    post('create_appointment',d).then(function(r){
                        if(r.success){
                            $('.hm-modal-bg--top').remove();$(document).off('.fuclose .fusave');
                            self.refresh();
                            self.toast('Follow-up appointment booked');
                            return;
                        }
                        $btn.prop('disabled',false).text('Book Follow-up');
                        if(r.data&&r.data.code==='same_patient_conflict'){
                            self._showDoubleBookAlert(r.data.message);return;
                        }
                        if(r.data&&r.data.code==='double_book_conflict'){
                            var conflicts=r.data.conflicts||[];
                            var msg='There is already a booking in this dispenser\'s diary for this time:\n\n';
                            conflicts.forEach(function(c){msg+='• '+c.patient+' at '+c.time+(c.clinic?' ('+c.clinic+')':'')+'\n';});
                            msg+='\nAre you sure you want to double book?';
                            self._showDoubleBookConfirm(msg,function(){doPost(true);});
                            return;
                        }
                        $('#hm-fu-err').text(r.data&&r.data.message?r.data.message:'Error booking follow-up');
                    }).fail(function(){$btn.prop('disabled',false).text('Book Follow-up');$('#hm-fu-err').text('Network error');});
                };
                doPost(false);
            });
        });
    },

    onSlot:function(el){
        var d=el.dataset;
        this.openNewApptModal(d.date,d.time,parseInt(d.disp));
    },

    openNewApptModal:function(date,time,dispId){
        var self=this;
        // Ensure services & clinics are loaded before building dropdown HTML
        var ready=$.Deferred();
        if(!self.services.length||!self.clinics.length){
            $.when(
                self.services.length?null:self.loadServices(),
                self.clinics.length?null:self.loadClinics(),
                self.dispensers.length?null:self.loadDispensers()
            ).always(function(){ready.resolve();});
        } else { ready.resolve(); }
        ready.then(function(){ self._buildApptModal(date,time,dispId); });
    },
    _buildApptModal:function(date,time,dispId){
        var self=this;
        var svcOpts=self.services.length?self.services.map(function(s){return'<option value="'+s.id+'">'+esc(s.name)+'</option>';}).join(''):'<option value="">No types available</option>';
        var cliOpts=self.clinics.length?self.clinics.map(function(c){return'<option value="'+c.id+'">'+esc(c.name)+'</option>';}).join(''):'<option value="">No clinics</option>';
        var html='<div class="hm-modal-bg open"><div class="hm-modal hm-modal--md">'+
            '<div class="hm-modal-hd"><h3>New Appointment</h3><button class="hm-close hm-new-close">'+IC.x+'</button></div>'+
            '<div class="hm-modal-body">'+
                '<div class="hm-fld" style="position:relative"><label>Patient search</label>'+
                    '<div style="display:flex;gap:8px;align-items:center">'+
                        '<input class="hm-inp" id="hmn-ptsearch" placeholder="Search by name..." autocomplete="off" style="flex:1">'+
                        '<button class="hm-btn hm-btn--sm" id="hmn-quickadd" type="button" title="Quick add patient" style="white-space:nowrap;padding:8px 10px">+ New</button>'+
                    '</div>'+
                    '<div class="hm-pt-results" id="hmn-ptresults"></div>'+
                    '<input type="hidden" id="hmn-patientid" value="0">'+
                    '<input type="hidden" id="hmn-refsource" value="">'+
                '</div>'+
                '<div class="hm-row"><div class="hm-fld"><label>Appointment Type</label><select class="hm-inp" id="hmn-service">'+svcOpts+'</select></div>'+
                '<div class="hm-fld"><label>Clinic</label><select class="hm-inp" id="hmn-clinic">'+cliOpts+'</select></div></div>'+
                '<div class="hm-row"><div class="hm-fld"><label>Assignee</label><select class="hm-inp" id="hmn-disp">'+self.dispensers.map(function(d){return'<option value="'+d.id+'"'+(parseInt(d.id)===dispId?' selected':'')+'>'+esc(d.name)+'</option>';}).join('')+'</select></div>'+
                '<div class="hm-fld"><label>Status</label><select class="hm-inp" id="hmn-status"><option selected>Not Confirmed</option><option>Confirmed</option><option>Pending</option></select></div></div>'+
                '<div class="hm-row"><div class="hm-fld"><label>Date</label><input type="date" class="hm-inp" id="hmn-date" value="'+date+'"></div>'+
                '<div class="hm-fld"><label>Start Time</label><input type="time" class="hm-inp" id="hmn-time" value="'+time+'"></div></div>'+
                '<div class="hm-row"><div class="hm-fld"><label>Duration</label><select class="hm-inp" id="hmn-duration">'+
                    '<option value="15">15 min</option><option value="30" selected>30 min</option><option value="45">45 min</option>'+
                    '<option value="60">60 min</option><option value="75">75 min</option><option value="90">90 min</option>'+
                    '<option value="105">105 min</option><option value="120">120 min</option>'+
                '</select></div>'+
                '<div class="hm-fld"><label>Location</label><select class="hm-inp" id="hmn-loc"><option>Clinic</option><option>Home</option></select></div></div>'+
                '<div class="hm-fld"><label>Referral Source</label><input class="hm-inp" id="hmn-referral" placeholder="Auto-filled from patient" readonly></div>'+
                '<div class="hm-fld"><label>Notes</label><textarea class="hm-inp" id="hmn-notes" placeholder="Optional notes..."></textarea></div>'+
            '</div>'+
            '<div class="hm-modal-ft"><span></span><div class="hm-modal-acts"><button class="hm-btn hm-new-close">Cancel</button><button class="hm-btn hm-btn--primary hm-new-save">Create Appointment</button></div></div>'+
        '</div></div>';
        $('body').append(html);

        // Set default duration from selected service
        var initSvc=self.svcMap[$('#hmn-service').val()];
        if(initSvc&&initSvc.duration)$('#hmn-duration').val(initSvc.duration);

        // Update duration default when appointment type changes
        $(document).on('change.newmodal','#hmn-service',function(){
            var s=self.svcMap[$(this).val()];
            if(s&&s.duration)$('#hmn-duration').val(s.duration);
        });

        var searchTimer;
        $(document).on('input.newmodal','#hmn-ptsearch',function(){
            var q=$(this).val();clearTimeout(searchTimer);
            if(q.length<2){$('#hmn-ptresults').removeClass('open').empty();return;}
            searchTimer=setTimeout(function(){
                post('search_patients',{query:q}).then(function(r){
                    if(!r.success||!r.data||!r.data.length){$('#hmn-ptresults').removeClass('open').html('<div class="hm-pt-item" style="color:#94a3b8;cursor:default">No patients found</div>').addClass('open');return;}
                    var h='';r.data.forEach(function(p){
                        var lbl=p.label||p.name;
                        h+='<div class="hm-pt-item" data-id="'+p.id+'" data-refsource="'+esc(p.referral_source_name||'')+'"><span>'+esc(lbl)+'</span><span class="hm-pt-newtab">Select</span></div>';
                    });
                    $('#hmn-ptresults').html(h).addClass('open');
                }).fail(function(){ $('#hmn-ptresults').removeClass('open').empty(); });
            },300);
        });
        $(document).on('click.newmodal','.hm-pt-item[data-id]',function(){
            var id=$(this).data('id'),name=$(this).find('span:first').text();
            var ref=$(this).data('refsource')||'';
            $('#hmn-ptsearch').val(name);$('#hmn-patientid').val(id);$('#hmn-ptresults').removeClass('open');
            $('#hmn-referral').val(ref);$('#hmn-refsource').val(ref);
        });
        // Quick-add patient inline
        $(document).on('click.newmodal','#hmn-quickadd',function(e){
            e.preventDefault();
            self.openQuickPatientModal(function(id, name){
                $('#hmn-ptsearch').val(name);$('#hmn-patientid').val(id);
            });
        });
        $(document).off('click.newclose').on('click.newclose','.hm-new-close',function(e){e.stopPropagation();$('.hm-modal-bg').remove();$(document).off('.newmodal .newclose');});
        $(document).off('click.newbg').on('click.newbg','.hm-modal-bg',function(e){if($(e.target).hasClass('hm-modal-bg')){$('.hm-modal-bg').remove();$(document).off('.newmodal .newclose .newbg');}});
        $(document).off('click.newsave').on('click.newsave','.hm-new-save',function(){
            var pid=$('#hmn-patientid').val();
            if(!pid||pid==='0'){alert('Please search and select a patient first.');return;}
            var apptData={
                patient_id:pid,service_id:$('#hmn-service').val(),
                clinic_id:$('#hmn-clinic').val(),dispenser_id:$('#hmn-disp').val(),
                status:$('#hmn-status').val(),appointment_date:$('#hmn-date').val(),
                start_time:$('#hmn-time').val(),duration:$('#hmn-duration').val(),
                location_type:$('#hmn-loc').val(),
                referring_source:$('#hmn-refsource').val(),
                notes:$('#hmn-notes').val()
            };
            self._submitNewAppt(apptData,false);
        });
    },

    // ── Submit new appointment with double-book handling ──
    _submitNewAppt:function(data,skipDoubleCheck,skipExclCheck){
        var self=this;
        if(skipDoubleCheck) data.skip_double_book_check='1';
        if(skipExclCheck) data.skip_exclusion_check='1';
        post('create_appointment',data).then(function(r){
            console.log('[HearMed] create_appointment response:',r);
            if(r.success){
                $('.hm-modal-bg').not('.hm-modal-bg--top').remove();
                $(document).off('.newmodal .newclose .newbg .newsave');
                self.refresh();
                return;
            }
            // Same patient conflict — hard block, no override
            if(r.data&&r.data.code==='same_patient_conflict'){
                self._showDoubleBookAlert(r.data.message,false);
                return;
            }
            // Exclusion conflict — soft warning with confirm
            if(r.data&&r.data.code==='exclusion_conflict'){
                self._showExclConflictConfirm(r.data.message,function(){
                    self._submitNewAppt(data,skipDoubleCheck,true);
                });
                return;
            }
            // Dispenser double-book — soft warning with confirm
            if(r.data&&r.data.code==='double_book_conflict'){
                var conflicts=r.data.conflicts||[];
                var msg='There is already a booking in this dispenser\'s diary for this time:\n\n';
                conflicts.forEach(function(c){
                    msg+='• '+c.patient+' at '+c.time+(c.clinic?' ('+c.clinic+')':'')+'\n';
                });
                msg+='\nAre you sure you want to double book?';
                self._showDoubleBookConfirm(msg,function(){
                    self._submitNewAppt(data,true,skipExclCheck);
                });
                return;
            }
            alert(r.data&&r.data.message?r.data.message:'Error creating appointment');
        }).fail(function(xhr){
            console.error('[HearMed] create_appointment AJAX fail:',xhr.status,xhr.responseText);
            alert('Network error — please try again.');
        });
    },

    // ── Double-book alert (hard block — no override) ──
    _showDoubleBookAlert:function(msg){
        var h='<div class="hm-modal-bg hm-modal-bg--top hm-dbl-alert open">';
        h+='<div class="hm-modal" style="max-width:420px">';
        h+='<div class="hm-modal-hd" style="background:#ef4444;color:#fff;border-radius:12px 12px 0 0;padding:14px 20px">';
        h+='<div><h3 style="margin:0;color:#fff;font-size:15px">Cannot Book Appointment</h3></div>';
        h+='<button class="hm-close hm-dbl-close" style="color:#fff">'+IC.x+'</button></div>';
        h+='<div class="hm-modal-body" style="padding:20px">';
        h+='<div style="display:flex;gap:12px;align-items:flex-start">';
        h+='<span style="font-size:28px;flex-shrink:0">&#9888;</span>';
        h+='<div style="font-size:13px;color:#334155;line-height:1.5">'+esc(msg)+'</div></div></div>';
        h+='<div class="hm-modal-ft" style="padding:12px 20px;border-top:1px solid #e2e8f0;display:flex;justify-content:flex-end">';
        h+='<button class="hm-btn hm-btn--primary hm-dbl-close" style="background:#ef4444">OK</button>';
        h+='</div></div></div>';
        $('body').append(h);
        $(document).off('click.dblclose').on('click.dblclose','.hm-dbl-close',function(e){
            e.stopPropagation();$('.hm-dbl-alert').remove();$(document).off('.dblclose');
        });
    },

    // ── Double-book confirm (soft warning — user can override) ──
    _showDoubleBookConfirm:function(msg,onConfirm){
        var h='<div class="hm-modal-bg hm-modal-bg--top hm-dbl-confirm open">';
        h+='<div class="hm-modal" style="max-width:440px">';
        h+='<div class="hm-modal-hd" style="background:#f59e0b;color:#fff;border-radius:12px 12px 0 0;padding:14px 20px">';
        h+='<div><h3 style="margin:0;color:#fff;font-size:15px">Double Booking Warning</h3></div>';
        h+='<button class="hm-close hm-dblc-close" style="color:#fff">'+IC.x+'</button></div>';
        h+='<div class="hm-modal-body" style="padding:20px">';
        h+='<div style="display:flex;gap:12px;align-items:flex-start">';
        h+='<span style="font-size:28px;flex-shrink:0">&#9888;</span>';
        h+='<div style="font-size:13px;color:#334155;line-height:1.5;white-space:pre-line">'+esc(msg)+'</div></div></div>';
        h+='<div class="hm-modal-ft" style="padding:12px 20px;border-top:1px solid #e2e8f0;display:flex;justify-content:flex-end;gap:8px">';
        h+='<button class="hm-btn hm-dblc-close">Cancel</button>';
        h+='<button class="hm-btn hm-btn--primary hm-dblc-yes" style="background:#f59e0b">Yes, Double Book</button>';
        h+='</div></div></div>';
        $('body').append(h);
        $(document).off('click.dblcclose').on('click.dblcclose','.hm-dblc-close',function(e){
            e.stopPropagation();$('.hm-dbl-confirm').remove();$(document).off('.dblcclose .dblcyes');
        });
        $(document).off('click.dblcyes').on('click.dblcyes','.hm-dblc-yes',function(e){
            e.stopPropagation();$('.hm-dbl-confirm').remove();$(document).off('.dblcclose .dblcyes');
            if(onConfirm)onConfirm();
        });
    },

    // ── Exclusion conflict confirm (soft warning — user can override) ──
    _showExclConflictConfirm:function(msg,onConfirm){
        var h='<div class="hm-modal-bg hm-modal-bg--top hm-excl-confirm open">';
        h+='<div class="hm-modal" style="max-width:440px">';
        h+='<div class="hm-modal-hd" style="background:#f59e0b;color:#fff;border-radius:12px 12px 0 0;padding:14px 20px">';
        h+='<div><h3 style="margin:0;color:#fff;font-size:15px">Exclusion Warning</h3></div>';
        h+='<button class="hm-close hm-exclc-close" style="color:#fff">'+IC.x+'</button></div>';
        h+='<div class="hm-modal-body" style="padding:20px">';
        h+='<div style="display:flex;gap:12px;align-items:flex-start">';
        h+='<span style="font-size:28px;flex-shrink:0">&#9888;</span>';
        h+='<div style="font-size:13px;color:#334155;line-height:1.5;white-space:pre-line">'+esc(msg)+'</div></div></div>';
        h+='<div class="hm-modal-ft" style="padding:12px 20px;border-top:1px solid #e2e8f0;display:flex;justify-content:flex-end;gap:8px">';
        h+='<button class="hm-btn hm-exclc-close">Cancel</button>';
        h+='<button class="hm-btn hm-btn--primary hm-exclc-yes" style="background:#f59e0b">Book Anyway</button>';
        h+='</div></div></div>';
        $('body').append(h);
        $(document).off('click.exclcclose').on('click.exclcclose','.hm-exclc-close',function(e){
            e.stopPropagation();$('.hm-excl-confirm').remove();$(document).off('.exclcclose .exclcyes');
        });
        $(document).off('click.exclcyes').on('click.exclcyes','.hm-exclc-yes',function(e){
            e.stopPropagation();$('.hm-excl-confirm').remove();$(document).off('.exclcclose .exclcyes');
            if(onConfirm)onConfirm();
        });
    },

    /* ── New Patient popup (full form) ── */
    openQuickPatientModal:function(onCreated){
        var self=this;
        var h='<div class="hm-modal-bg hm-modal-bg--top open"><div class="hm-modal hm-modal--md">'+
            '<div class="hm-modal-hd"><h3>New Patient</h3><button class="hm-close hm-qp-close">'+IC.x+'</button></div>'+
            '<div class="hm-modal-body" style="max-height:75vh;overflow-y:auto">'+
                /* Row 1: Title, First, Last */
                '<div class="hm-row"><div class="hm-fld" style="flex:0 0 90px"><label>Title</label>'+
                    '<select class="hm-inp" id="hmqp-title"><option value="">—</option><option>Mr</option><option>Mrs</option><option>Ms</option><option>Miss</option><option>Dr</option><option>Other</option></select></div>'+
                '<div class="hm-fld"><label>First name *</label><input class="hm-inp" id="hmqp-fn" autofocus></div>'+
                '<div class="hm-fld"><label>Last name *</label><input class="hm-inp" id="hmqp-ln"></div></div>'+
                /* Row 2: DOB, Phone, Mobile */
                '<div class="hm-row"><div class="hm-fld"><label>Date of birth</label><input type="date" class="hm-inp" id="hmqp-dob"></div>'+
                '<div class="hm-fld"><label>Phone</label><input class="hm-inp" id="hmqp-phone"></div>'+
                '<div class="hm-fld"><label>Mobile</label><input class="hm-inp" id="hmqp-mobile"></div></div>'+
                /* Email */
                '<div class="hm-fld"><label>Email</label><input type="email" class="hm-inp" id="hmqp-email"></div>'+
                /* Address */
                '<div class="hm-fld"><label>Address</label><textarea class="hm-inp" id="hmqp-address" rows="2"></textarea></div>'+
                /* Row 3: Eircode, PPS */
                '<div class="hm-row"><div class="hm-fld"><label>Eircode</label><input class="hm-inp" id="hmqp-eircode"></div>'+
                '<div class="hm-fld"><label>PPS number</label><input class="hm-inp" id="hmqp-pps" placeholder="e.g. 1234567AB"></div></div>'+
                /* Row 4: Referral source, Dispenser */
                '<div class="hm-row"><div class="hm-fld"><label>Referral source</label><select class="hm-inp" id="hmqp-ref"><option value="">— Select —</option></select></div>'+
                '<div class="hm-fld"><label>Dispenser</label><select class="hm-inp" id="hmqp-disp"><option value="">— Select —</option></select></div></div>'+
                /* Clinic */
                '<div class="hm-fld"><label>Clinic</label><select class="hm-inp" id="hmqp-clinic"><option value="">— Select —</option></select></div>'+
                /* Marketing checkboxes */
                '<div style="display:flex;gap:20px;flex-wrap:wrap;margin:10px 0">'+
                    '<label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer"><input type="checkbox" id="hmqp-memail"> Email marketing</label>'+
                    '<label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer"><input type="checkbox" id="hmqp-msms"> SMS marketing</label>'+
                    '<label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer"><input type="checkbox" id="hmqp-mphone"> Phone marketing</label>'+
                '</div>'+
                /* GDPR consent */
                '<div style="margin-top:12px;padding:14px 16px;background:#f0fdfa;border:1px solid var(--hm-teal);border-radius:8px">'+
                    '<p style="margin:0 0 8px;font-size:13px;font-weight:600;color:#151B33">GDPR Consent — Required</p>'+
                    '<label style="display:flex;align-items:flex-start;gap:8px;font-size:13px;cursor:pointer">'+
                        '<input type="checkbox" id="hmqp-gdpr" style="margin-top:2px;flex-shrink:0">'+
                        '<span>I confirm this patient has provided informed consent for HearMed to store and process their personal and health data in accordance with our Privacy Policy.</span>'+
                    '</label>'+
                '</div>'+
            '</div>'+
            '<div class="hm-modal-ft"><span class="hm-qp-err" style="color:#ef4444;font-size:12px"></span><div class="hm-modal-acts"><button class="hm-btn hm-qp-close">Cancel</button><button class="hm-btn hm-btn--primary hm-qp-save" disabled>Create Patient</button></div></div>'+
        '</div></div>';
        $('body').append(h);

        /* Enable save only when GDPR ticked */
        $(document).on('change.qpgdpr','#hmqp-gdpr',function(){$('.hm-qp-save').prop('disabled',!this.checked);});

        /* Populate dropdowns from DB */
        post('get_referral_sources').then(function(r){if(r.success&&r.data)r.data.forEach(function(s){$('#hmqp-ref').append('<option value="'+s.id+'">'+esc(s.name)+'</option>');});});
        post('get_staff_list').then(function(r){if(r.success&&r.data)r.data.forEach(function(d){$('#hmqp-disp').append('<option value="'+d.id+'">'+esc(d.name)+'</option>');});});
        /* Clinics - use already-loaded data if available */
        if(self.clinics.length){self.clinics.forEach(function(c){$('#hmqp-clinic').append('<option value="'+c.id+'">'+esc(c.name)+'</option>');});}
        else{post('get_clinics').then(function(r){if(r.success&&r.data)r.data.forEach(function(c){$('#hmqp-clinic').append('<option value="'+c.id+'">'+esc(c.name)+'</option>');});});}

        $(document).off('click.qpclose').on('click.qpclose','.hm-qp-close',function(e){e.stopPropagation();$('.hm-modal-bg--top').remove();$(document).off('.qpclose .qpsave .qpgdpr');});
        $(document).off('click.qpsave').on('click.qpsave','.hm-qp-save',function(){
            var fn=$('#hmqp-fn').val().trim(),ln=$('#hmqp-ln').val().trim();
            if(!fn||!ln){$('.hm-qp-err').text('First and last name are required.');return;}
            var ph=$('#hmqp-phone').val().trim(),mb=$('#hmqp-mobile').val().trim();
            if(!ph&&!mb){$('.hm-qp-err').text('Phone or mobile number is required.');return;}
            var $btn=$(this);$btn.prop('disabled',true).text('Creating...');
            post('create_patient',{
                patient_title:$('#hmqp-title').val(),
                first_name:fn,last_name:ln,
                dob:$('#hmqp-dob').val(),
                patient_phone:ph,
                patient_mobile:mb,
                patient_email:$('#hmqp-email').val().trim(),
                patient_address:$('#hmqp-address').val().trim(),
                patient_eircode:$('#hmqp-eircode').val().trim(),
                pps_number:$('#hmqp-pps').val().trim(),
                referral_source_id:$('#hmqp-ref').val(),
                assigned_dispenser_id:$('#hmqp-disp').val(),
                assigned_clinic_id:$('#hmqp-clinic').val(),
                marketing_email:$('#hmqp-memail').is(':checked')?'1':'0',
                marketing_sms:$('#hmqp-msms').is(':checked')?'1':'0',
                marketing_phone:$('#hmqp-mphone').is(':checked')?'1':'0',
                gdpr_consent:$('#hmqp-gdpr').is(':checked')?'1':'0'
            }).then(function(r){
                if(r.success){
                    $('.hm-modal-bg--top').remove();$(document).off('.qpclose .qpsave .qpgdpr');
                    if(onCreated)onCreated(r.data.id,fn+' '+ln);
                } else {
                    $btn.prop('disabled',false).text('Create Patient');
                    $('.hm-qp-err').text(r.data&&r.data.message?r.data.message:(typeof r.data==='string'?r.data:'Failed to add patient.'));
                }
            }).fail(function(){ $btn.prop('disabled',false).text('Create Patient');$('.hm-qp-err').text('Network error.'); });
        });
    },

    /* ── Exclusion / Unavailability modal ── */
    openExclusionModal:function(){
        var self=this;
        var types=this.exclusionTypes||[];
        var typeOpts=types.length?types.map(function(t){return'<option value="'+t.id+'" data-color="'+(t.color||'#6b7280')+'">'+esc(t.type_name)+'</option>';}).join(''):'<option value="0">No exclusion types defined</option>';
        var dispOpts=self.dispensers.map(function(d){return'<option value="'+d.id+'">'+esc(d.name)+'</option>';}).join('');
        var h='<div class="hm-modal-bg open"><div class="hm-modal hm-modal--md">'+
            '<div class="hm-modal-hd"><h3>Add Exclusion / Unavailability</h3><button class="hm-close hm-excl-close">'+IC.x+'</button></div>'+
            '<div class="hm-modal-body">'+
                '<div class="hm-row"><div class="hm-fld"><label>Exclusion Type</label><select class="hm-inp" id="hmex-type">'+typeOpts+'</select></div>'+
                '<div class="hm-fld"><label>Assignee</label><select class="hm-inp" id="hmex-disp"><option value="0">All dispensers</option>'+dispOpts+'</select></div></div>'+
                '<div class="hm-fld"><label>Scope</label>'+
                    '<div class="hm-scope-pills"><label class="hm-pill on"><input type="radio" name="hmex-scope" value="day" checked> Full Day</label>'+
                    '<label class="hm-pill"><input type="radio" name="hmex-scope" value="hours"> Custom Hours</label></div>'+
                '</div>'+
                '<div class="hm-fld hm-excl-hours" style="display:none">'+
                    '<div class="hm-row"><div class="hm-fld"><label>Start Time</label><input type="time" class="hm-inp" id="hmex-st" value="09:00"></div>'+
                    '<div class="hm-fld"><label>End Time</label><input type="time" class="hm-inp" id="hmex-et" value="17:00"></div></div>'+
                '</div>'+
                '<div class="hm-row"><div class="hm-fld"><label>Start Date</label><input type="date" class="hm-inp" id="hmex-sd" value="'+fmt(self.date)+'"></div>'+
                '<div class="hm-fld"><label>End Date</label><input type="date" class="hm-inp" id="hmex-ed" value="'+fmt(self.date)+'"></div></div>'+
                '<div class="hm-fld"><label>Reason / Notes</label><input class="hm-inp" id="hmex-reason" placeholder="e.g. Annual leave, Lunch break"></div>'+
                '<div class="hm-fld"><label>Repeat</label>'+
                    '<div class="hm-scope-pills"><label class="hm-pill on"><input type="radio" name="hmex-repeat" value="no" checked> No Repeat</label>'+
                    '<label class="hm-pill"><input type="radio" name="hmex-repeat" value="days"> Repeat on Days</label>'+
                    '<label class="hm-pill"><input type="radio" name="hmex-repeat" value="until"> Until Date</label>'+
                    '<label class="hm-pill"><input type="radio" name="hmex-repeat" value="indefinite"> Indefinitely</label></div>'+
                '</div>'+
                '<div class="hm-fld hm-excl-days" style="display:none"><label>Repeat on</label>'+
                    '<div class="hm-day-pills">'+
                    ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'].map(function(d,i){var v=(i<6)?i+1:0;return'<label class="hm-pill"><input type="checkbox" class="hmex-wd" value="'+v+'"> '+d+'</label>';}).join('')+
                    '</div>'+
                '</div>'+
                '<div class="hm-fld hm-excl-until" style="display:none"><label>Repeat until</label><input type="date" class="hm-inp" id="hmex-untilDate"></div>'+
            '</div>'+
            '<div class="hm-modal-ft"><span class="hm-excl-err" style="color:#ef4444;font-size:12px"></span><div class="hm-modal-acts"><button class="hm-btn hm-excl-close">Cancel</button><button class="hm-btn hm-btn--primary hm-excl-save">Save</button></div></div>'+
        '</div></div>';
        $('body').append(h);

        // Scope toggle
        $(document).on('change.excl','input[name="hmex-scope"]',function(){
            var v=$(this).val();
            $(this).closest('.hm-scope-pills').find('.hm-pill').removeClass('on');
            $(this).closest('.hm-pill').addClass('on');
            $('.hm-excl-hours').toggle(v==='hours');
        });
        // Repeat toggle
        $(document).on('change.excl','input[name="hmex-repeat"]',function(){
            var v=$(this).val();
            $(this).closest('.hm-scope-pills').find('.hm-pill').removeClass('on');
            $(this).closest('.hm-pill').addClass('on');
            $('.hm-excl-days').toggle(v==='days');
            $('.hm-excl-until').toggle(v==='until');
        });
        // Day pill toggle
        $(document).on('change.excl','.hmex-wd',function(){
            $(this).closest('.hm-pill').toggleClass('on',this.checked);
        });

        $(document).off('click.exclclose').on('click.exclclose','.hm-excl-close',function(e){e.stopPropagation();$('.hm-modal-bg').remove();$(document).off('.excl .exclclose .exclsave');});
        $(document).off('click.exclbg').on('click.exclbg','.hm-modal-bg',function(e){if($(e.target).hasClass('hm-modal-bg')){$('.hm-modal-bg').remove();$(document).off('.excl .exclclose .exclsave .exclbg');}});
        $(document).off('click.exclsave').on('click.exclsave','.hm-excl-save',function(){
            var scope=$('input[name="hmex-scope"]:checked').val();
            var repeat=$('input[name="hmex-repeat"]:checked').val();
            var sd=$('#hmex-sd').val(),ed=$('#hmex-ed').val();
            if(!sd){$('.hm-excl-err').text('Start date is required.');return;}
            if(!ed)ed=sd;

            var reasonText=$('#hmex-reason').val().trim();

            var data={
                exclusion_type_id:$('#hmex-type').val()||0,
                staff_id:$('#hmex-disp').val()||0,
                scope:scope==='hours'?'custom_hours':'full_day',
                start_date:sd,
                end_date:ed,
                start_time:scope==='hours'?$('#hmex-st').val():'',
                end_time:scope==='hours'?$('#hmex-et').val():'',
                reason:reasonText,
                repeat_type:repeat==='no'?'none':(repeat==='days'?'days':(repeat==='indefinite'?'indefinite':'until_date')),
            };
            // Repeat days
            if(repeat==='days'){
                var days=[];$('.hmex-wd:checked').each(function(){days.push($(this).val());});
                if(!days.length){$('.hm-excl-err').text('Select at least one day.');return;}
                data.repeat_days=days.join(',');
            }
            // Repeat until date
            if(repeat==='until'){
                var ud=$('#hmex-untilDate').val();
                if(!ud){$('.hm-excl-err').text('Please set a repeat-until date.');return;}
                data.repeat_until=ud;
            }
            if(repeat==='indefinite')data.repeat_until='2099-12-31';

            var $btn=$('.hm-excl-save');$btn.prop('disabled',true).text('Saving...');
            post('save_exclusion',data).then(function(r){
                if(r.success){
                    $('.hm-modal-bg').remove();$(document).off('.excl .exclclose .exclsave .exclbg');
                    self.refresh();
                } else {
                    $btn.prop('disabled',false).text('Save');
                    $('.hm-excl-err').text(r.data&&r.data.message?r.data.message:'Save failed.');
                }
            }).fail(function(){$btn.prop('disabled',false).text('Save');$('.hm-excl-err').text('Network error.');});
        });
    },

    /* ── Edit existing exclusion ── */
    openEditExclusionModal:function(ex){
        var self=this;
        var types=this.exclusionTypes||[];
        var typeOpts=types.length?types.map(function(t){return'<option value="'+t.id+'"'+(parseInt(t.id)===parseInt(ex.exclusion_type_id)?' selected':'')+'>'+esc(t.type_name)+'</option>';}).join(''):'<option value="0">No exclusion types defined</option>';
        var dispOpts=self.dispensers.map(function(d){return'<option value="'+d.id+'"'+(parseInt(d.id)===parseInt(ex.staff_id)?' selected':'')+'>'+esc(d.name)+'</option>';}).join('');
        var isHours=ex.scope==='custom_hours';
        var h='<div class="hm-modal-bg open"><div class="hm-modal hm-modal--md">'+
            '<div class="hm-modal-hd"><h3>Edit Exclusion</h3><button class="hm-close hm-excl-close">'+IC.x+'</button></div>'+
            '<div class="hm-modal-body">'+
                '<div class="hm-row"><div class="hm-fld"><label>Exclusion Type</label><select class="hm-inp" id="hmex-type">'+typeOpts+'</select></div>'+
                '<div class="hm-fld"><label>Assignee</label><select class="hm-inp" id="hmex-disp"><option value="0">All dispensers</option>'+dispOpts+'</select></div></div>'+
                '<div class="hm-fld"><label>Scope</label>'+
                    '<div class="hm-scope-pills"><label class="hm-pill'+(isHours?'':' on')+'"><input type="radio" name="hmex-scope" value="day"'+(isHours?'':' checked')+'> Full Day</label>'+
                    '<label class="hm-pill'+(isHours?' on':'')+'"><input type="radio" name="hmex-scope" value="hours"'+(isHours?' checked':'')+'> Custom Hours</label></div>'+
                '</div>'+
                '<div class="hm-fld hm-excl-hours" style="display:'+(isHours?'block':'none')+'">'+
                    '<div class="hm-row"><div class="hm-fld"><label>Start Time</label><input type="time" class="hm-inp" id="hmex-st" value="'+(ex.start_time||'09:00').substring(0,5)+'"></div>'+
                    '<div class="hm-fld"><label>End Time</label><input type="time" class="hm-inp" id="hmex-et" value="'+(ex.end_time||'17:00').substring(0,5)+'"></div></div>'+
                '</div>'+
                '<div class="hm-row"><div class="hm-fld"><label>Start Date</label><input type="date" class="hm-inp" id="hmex-sd" value="'+ex.start_date+'"></div>'+
                '<div class="hm-fld"><label>End Date</label><input type="date" class="hm-inp" id="hmex-ed" value="'+(ex.end_date||ex.start_date)+'"></div></div>'+
                '<div class="hm-fld"><label>Reason / Notes</label><input class="hm-inp" id="hmex-reason" value="'+esc(ex.reason||'')+'"></div>'+
            '</div>'+
            '<div class="hm-modal-ft"><span class="hm-excl-err" style="color:#ef4444;font-size:12px"></span><div class="hm-modal-acts"><button class="hm-btn hm-excl-close">Cancel</button><button class="hm-btn hm-btn--primary hm-excl-save">Save</button></div></div>'+
        '</div></div>';
        $('body').append(h);

        // Scope toggle
        $(document).on('change.excl','input[name="hmex-scope"]',function(){
            var v=$(this).val();
            $(this).closest('.hm-scope-pills').find('.hm-pill').removeClass('on');
            $(this).closest('.hm-pill').addClass('on');
            $('.hm-excl-hours').toggle(v==='hours');
        });

        $(document).off('click.exclclose').on('click.exclclose','.hm-excl-close',function(e){e.stopPropagation();$('.hm-modal-bg').remove();$(document).off('.excl .exclclose .exclsave');});
        $(document).off('click.exclsave').on('click.exclsave','.hm-excl-save',function(){
            var scope=$('input[name="hmex-scope"]:checked').val();
            var sd=$('#hmex-sd').val(),ed=$('#hmex-ed').val();
            if(!sd){$('.hm-excl-err').text('Start date is required.');return;}
            if(!ed)ed=sd;
            var data={
                id:ex.id,
                exclusion_type_id:$('#hmex-type').val()||0,
                staff_id:$('#hmex-disp').val()||0,
                scope:scope==='hours'?'custom_hours':'full_day',
                start_date:sd,
                end_date:ed,
                start_time:scope==='hours'?$('#hmex-st').val():'',
                end_time:scope==='hours'?$('#hmex-et').val():'',
                reason:$('#hmex-reason').val().trim(),
                repeat_type:'none',
            };
            var $btn=$('.hm-excl-save');$btn.prop('disabled',true).text('Saving...');
            post('save_exclusion',data).then(function(r){
                if(r.success){
                    $('.hm-modal-bg').remove();$(document).off('.excl .exclclose .exclsave');
                    self.refresh();
                } else {
                    $btn.prop('disabled',false).text('Save');
                    $('.hm-excl-err').text(r.data&&r.data.message?r.data.message:'Save failed.');
                }
            }).fail(function(){$btn.prop('disabled',false).text('Save');$('.hm-excl-err').text('Network error.');});
        });
    },

    onPlusAction:function(act){
        if(act==='appointment')this.openNewApptModal(fmt(this.date),pad(this.cfg.startH)+':00',this.dispensers.length?parseInt(this.dispensers[0].id):0);
        else if(act==='patient')this.openQuickPatientModal();
        else if(act==='holiday')this.openExclusionModal();
    },
};

// ═══════════════════════════════════════
// SETTINGS VIEW
// ═══════════════════════════════════════
// Settings UI is rendered server-side by admin-calendar-settings.php
// Save/preview JS handled by hearmed-calendar-settings.js
// This object is kept as a no-op so App.init() routing doesn't error.
var Settings={
    $el:null,
    init:function($el){
        this.$el=$el;
        // PHP template already rendered the settings form — nothing to do here
    },
};

// ═══════════════════════════════════════
// BLOCKOUTS VIEW (unchanged)
// ═══════════════════════════════════════
var Blockouts={
    $el:null,data:[],services:[],dispensers:[],
    init:function($el){this.$el=$el;this.load();},
    load:function(){
        var self=this;
        $.when(post('get_blockouts'),post('get_services'),post('get_dispensers'))
        .then(function(r1,r2,r3){
            self.data=r1[0].success?r1[0].data:[];
            self.services=r2[0].success?r2[0].data:[];
            self.dispensers=r3[0].success?r3[0].data:[];
            self.render();
        });
    },
    render:function(){
        var self=this;
        var h='<div class="hm-admin"><div class="hm-admin-hd"><h2>Appointment Type Blockouts</h2><button class="hm-btn hm-btn--primary" id="hbl-add">+ Add Blockout</button></div>';
        h+='<table class="hm-table"><thead><tr><th>Appointment Type</th><th>Assignee</th><th>Dates</th><th>Time</th><th style="width:80px"></th></tr></thead><tbody>';
        if(!this.data.length)h+='<tr><td colspan="5" class="hm-no-data">No blockouts configured</td></tr>';
        else this.data.forEach(function(b){
            h+='<tr><td><strong>'+esc(b.service_name)+'</strong></td><td>'+esc(b.dispenser_name)+'</td><td>'+b.start_date+' → '+b.end_date+'</td><td>'+(b.start_time||'—')+' – '+(b.end_time||'—')+'</td><td class="hm-table-acts"><button class="hm-act-btn hm-act-edit hbl-edit" data-id="'+b._ID+'">Edit</button><button class="hm-act-btn hm-act-del hbl-del" data-id="'+b._ID+'">Del</button></td></tr>';
        });
        h+='</tbody></table></div>';
        this.$el.html(h);
        $(document).on('click','#hbl-add',function(){self.openForm(null);});
        $(document).on('click','.hbl-edit',function(){var id=$(this).data('id');var b=self.data.find(function(x){return x._ID==id;});if(b)self.openForm(b);});
        $(document).on('click','.hbl-del',function(){if(confirm('Delete this blockout?'))post('delete_blockout',{_ID:$(this).data('id')}).then(function(){self.load();});});
    },
    openForm:function(bo){
        var isEdit=!!bo,self=this;
        var h='<div class="hm-modal-bg open"><div class="hm-modal"><div class="hm-modal-hd"><h3>'+(isEdit?'Edit':'New')+' Blockout</h3><button class="hm-close hbl-close">'+IC.x+'</button></div><div class="hm-modal-body">';
        h+='<div class="hm-fld"><label>Appointment Type</label><select class="hm-inp" id="hblf-svc">'+self.services.map(function(s){return'<option value="'+s.id+'"'+(bo&&bo.service_id==s.id?' selected':'')+'>'+esc(s.name)+'</option>';}).join('')+'</select></div>';
        h+='<div class="hm-fld"><label>Assignee</label><select class="hm-inp" id="hblf-disp"><option value="0">All</option>'+self.dispensers.map(function(d){return'<option value="'+d.id+'"'+(bo&&bo.dispenser_id==d.id?' selected':'')+'>'+esc(d.name)+'</option>';}).join('')+'</select></div>';
        h+='<div class="hm-row"><div class="hm-fld"><label>Start Date</label><input type="date" class="hm-inp" id="hblf-sd" value="'+(bo?bo.start_date:'')+'"></div><div class="hm-fld"><label>End Date</label><input type="date" class="hm-inp" id="hblf-ed" value="'+(bo?bo.end_date:'')+'"></div></div>';
        h+='<div class="hm-row"><div class="hm-fld"><label>Start Time</label><input type="time" class="hm-inp" id="hblf-st" value="'+(bo?bo.start_time:'09:00')+'"></div><div class="hm-fld"><label>End Time</label><input type="time" class="hm-inp" id="hblf-et" value="'+(bo?bo.end_time:'17:00')+'"></div></div>';
        h+='</div><div class="hm-modal-ft"><span></span><div class="hm-modal-acts"><button class="hm-btn hbl-close">Cancel</button><button class="hm-btn hm-btn--primary hbl-save" data-id="'+(bo?bo._ID:0)+'">Save</button></div></div></div></div>';
        $('body').append(h);
        $(document).on('click','.hbl-close',function(){$('.hm-modal-bg').remove();});
        $(document).on('click','.hbl-save',function(){
            post('save_blockout',{_ID:$(this).data('id'),service_id:$('#hblf-svc').val(),dispenser_id:$('#hblf-disp').val(),start_date:$('#hblf-sd').val(),end_date:$('#hblf-ed').val(),start_time:$('#hblf-st').val(),end_time:$('#hblf-et').val()})
            .then(function(r){if(r.success){$('.hm-modal-bg').remove();self.load();}else alert('Error');});
        });
    }
};

// ═══════════════════════════════════════
// HOLIDAYS VIEW (unchanged)
// ═══════════════════════════════════════
var Holidays={
    $el:null,data:[],dispensers:[],
    init:function($el){this.$el=$el;this.load();},
    load:function(){
        var self=this;
        $.when(post('get_holidays'),post('get_dispensers'))
        .then(function(r1,r2){
            self.data=r1[0].success?r1[0].data:[];
            self.dispensers=r2[0].success?r2[0].data:[];
            self.render();
        });
    },
    render:function(){
        var self=this;
        var h='<div class="hm-admin"><div class="hm-admin-hd"><h2>Holidays &amp; Unavailability</h2><button class="hm-btn hm-btn--primary" id="hhl-add">+ Add New</button></div>';
        h+='<table class="hm-table"><thead><tr><th>Assignee</th><th>Reason</th><th>Repeats</th><th>Dates</th><th>Time</th><th style="width:80px"></th></tr></thead><tbody>';
        if(!this.data.length)h+='<tr><td colspan="6" class="hm-no-data">No holidays or unavailability configured</td></tr>';
        else this.data.forEach(function(ho){
            h+='<tr><td><strong>'+esc(ho.dispenser_name)+'</strong></td><td>'+esc(ho.reason)+'</td><td>'+(ho.repeats==='no'?'—':ho.repeats)+'</td><td>'+ho.start_date+' → '+ho.end_date+'</td><td>'+(ho.start_time||'—')+' – '+(ho.end_time||'—')+'</td><td class="hm-table-acts"><button class="hm-act-btn hm-act-edit hhl-edit" data-id="'+ho._ID+'">Edit</button><button class="hm-act-btn hm-act-del hhl-del" data-id="'+ho._ID+'">Del</button></td></tr>';
        });
        h+='</tbody></table></div>';
        this.$el.html(h);
        $(document).on('click','#hhl-add',function(){self.openForm(null);});
        $(document).on('click','.hhl-edit',function(){var id=$(this).data('id');var ho=self.data.find(function(x){return x._ID==id;});if(ho)self.openForm(ho);});
        $(document).on('click','.hhl-del',function(){if(confirm('Delete?'))post('delete_holiday',{_ID:$(this).data('id')}).then(function(){self.load();});});
    },
    openForm:function(ho){
        var isEdit=!!ho,self=this;
        var h='<div class="hm-modal-bg open"><div class="hm-modal"><div class="hm-modal-hd"><h3>'+(isEdit?'Edit':'New')+' Holiday / Unavailability</h3><button class="hm-close hhl-close">'+IC.x+'</button></div><div class="hm-modal-body">';
        h+='<div class="hm-fld"><label>Assignee</label><select class="hm-inp" id="hhlf-disp"><option value="">Select...</option>';
        this.dispensers.forEach(function(d){h+='<option value="'+d.id+'"'+(ho&&ho.dispenser_id==d.id?' selected':'')+'>'+esc(d.name)+'</option>';});
        h+='</select></div>';
        h+='<div class="hm-fld"><label>Reason</label><input class="hm-inp" id="hhlf-reason" value="'+esc(ho?ho.reason:'')+'" placeholder="e.g. Annual leave"></div>';
        h+='<div class="hm-row"><div class="hm-fld"><label>Start Date</label><input type="date" class="hm-inp" id="hhlf-sd" value="'+(ho?ho.start_date:'')+'"></div><div class="hm-fld"><label>End Date</label><input type="date" class="hm-inp" id="hhlf-ed" value="'+(ho?ho.end_date:'')+'"></div></div>';
        h+='<div class="hm-row"><div class="hm-fld"><label>Start Time</label><input type="time" class="hm-inp" id="hhlf-st" value="'+(ho?ho.start_time:'09:00')+'"></div><div class="hm-fld"><label>End Time</label><input type="time" class="hm-inp" id="hhlf-et" value="'+(ho?ho.end_time:'17:00')+'"></div></div>';
        h+='<div class="hm-fld"><label>Repeats</label><select class="hm-inp" id="hhlf-rep"><option value="no"'+(ho&&ho.repeats!=='no'?'':' selected')+'>No</option><option value="weekly"'+(ho&&ho.repeats==='weekly'?' selected':'')+'>Weekly</option><option value="monthly"'+(ho&&ho.repeats==='monthly'?' selected':'')+'>Monthly</option><option value="yearly"'+(ho&&ho.repeats==='yearly'?' selected':'')+'>Yearly</option></select></div>';
        h+='</div><div class="hm-modal-ft"><span></span><div class="hm-modal-acts"><button class="hm-btn hhl-close">Cancel</button><button class="hm-btn hm-btn--primary hhl-save" data-id="'+(ho?ho._ID:0)+'">Save</button></div></div></div></div>';
        $('body').append(h);
        $(document).on('click','.hhl-close',function(){$('.hm-modal-bg').remove();});
        $(document).on('click','.hhl-save',function(){
            post('save_holiday',{_ID:$(this).data('id'),dispenser_id:$('#hhlf-disp').val(),reason:$('#hhlf-reason').val(),start_date:$('#hhlf-sd').val(),end_date:$('#hhlf-ed').val(),start_time:$('#hhlf-st').val(),end_time:$('#hhlf-et').val(),repeats:$('#hhlf-rep').val()})
            .then(function(r){if(r.success){$('.hm-modal-bg').remove();self.load();}else alert('Error');});
        });
    }
};

// ═══ BOOT ═══
$(function(){if($('#hm-app').length)App.init();});

})(jQuery);

