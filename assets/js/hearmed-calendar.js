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
    $el:null,date:new Date(),mode:'week',viewMode:'people',calViewMode:'clinic',
    dispensers:[],services:[],clinics:[],appts:[],holidays:[],blockouts:[],exclusionTypes:[],referralSources:[],
    selClinics:[],selDisps:[],svcMap:{},cfg:{},clinicHasSchedules:false,clinicCoverage:{},
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
                '<div class="hm-tb-center">'+
                    '<div class="hm-view-tog" id="hm-calViewTog"><button class="hm-cview-btn on" data-cv="clinic">Clinic View</button><button class="hm-cview-btn" data-cv="dispenser">Dispenser View</button></div>'+
                '</div>'+
                '<div class="hm-tb-right">'+
                    '<div class="hm-view-tog"><button class="hm-view-btn" data-v="day">Day</button><button class="hm-view-btn" data-v="week">Week</button></div>'+
                    '<button class="hm-icon-btn" onclick="window.print()" title="Print">'+IC.print+'</button>'+
                    '<div class="hm-sep"></div>'+
                    /* Multi-select clinic */
                    '<div class="hm-ms hm-ms-disabled" id="hm-clinicMs">'+
                        '<button class="hm-ms-btn" id="hm-clinicMsBtn"><span class="hm-ms-lbl">Select Clinic</span><span class="hm-ms-chev">'+IC.chevDown+'</span></button>'+
                        '<div class="hm-ms-drop" id="hm-clinicMsDrop"></div>'+
                    '</div>'+
                    /* Multi-select dispenser */
                    '<div class="hm-ms hm-ms-disabled" id="hm-dispMs">'+
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

        // Clinic View / Dispenser View toggle
        $(document).on('click','.hm-cview-btn',function(){
            var cv=$(this).data('cv');
            if(cv===self.calViewMode)return;
            self.calViewMode=cv;
            $('.hm-cview-btn').removeClass('on');$(this).addClass('on');
            self.onCalViewChange();
        });

        // Multi-select toggles
        $(document).on('click','#hm-clinicMsBtn',function(e){e.stopPropagation();if($('#hm-clinicMs').hasClass('hm-ms-disabled'))return;$('#hm-clinicMsDrop').toggleClass('open');$('#hm-dispMsDrop').removeClass('open');});
        $(document).on('click','#hm-dispMsBtn',function(e){e.stopPropagation();if($('#hm-dispMs').hasClass('hm-ms-disabled'))return;$('#hm-dispMsDrop').toggleClass('open');$('#hm-clinicMsDrop').removeClass('open');});
        $(document).on('click','.hm-ms-item',function(e){
            e.stopPropagation();
            var $t=$(this),id=parseInt($t.data('id')),group=$t.data('group');
            if(id===0){
                // "All" clicked — clear dispenser selection only
                if(group==='disp'){self.selDisps=[];}
            } else if(group==='clinic'){
                // Single-select for clinics: always select one (can't deselect to none)
                self.selClinics=[id];
            } else {
                var arr=self.selDisps;
                var idx=arr.indexOf(id);
                if(idx>-1)arr.splice(idx,1); else arr.push(id);
                self.selDisps=arr;
            }
            self.renderMultiSelect();
            if(group==='clinic'){$('#hm-clinicMsDrop').removeClass('open');self.loadDispensers().then(function(){self.refresh();});}
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
            var canAdminReopen=!!(HM&&HM.is_admin);
            if(aLocked&&!canAdminReopen){
                // Locked appointment — view only
                m+='<div class="hm-ctx-item hm-ctx-edit">'+IC.edit+' View Details / Add Note</div>';
                m+='<div class="hm-ctx-sep"></div>';
                m+='<div class="hm-ctx-item hm-ctx-notes">'+IC.note+' Quick Add Notes</div>';
                m+='<div class="hm-ctx-sep"></div>';
                m+='<div class="hm-ctx-item" style="opacity:0.4;cursor:default;font-size:11px;color:#059669">Appointment closed — notes can still be added</div>';
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
                    if(o.triggers_order)oh+='<span style="margin-left:auto;font-size:9px;background:#fef3c7;color:#92400e;padding:1px 5px;border-radius:3px">Order</span>';
                    else if(o.triggers_invoice)oh+='<span style="margin-left:auto;font-size:9px;background:#dbeafe;color:#1e40af;padding:1px 5px;border-radius:3px">Invoice</span>';
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
            this.loadHolidays(),
            this.loadClinicCoverage()
        ).always(function(){ self.onCalViewChange(); });
    },
    onCalViewChange:function(){
        var self=this;
        if(this.calViewMode==='clinic'){
            $('#hm-clinicMs').removeClass('hm-ms-disabled');
            $('#hm-dispMs').addClass('hm-ms-disabled');
            this.selDisps=[];
            // Auto-select first clinic if none selected
            if(!this.selClinics.length&&this.clinics.length){
                this.selClinics=[this.clinics[0].id];
            }
            this.renderMultiSelect();
            this.loadClinicCoverage().then(function(){self.refresh();});
        } else {
            $('#hm-clinicMs,#hm-dispMs').removeClass('hm-ms-disabled');
            this.renderMultiSelect();
            // Auto-select first clinic if none selected
            if(!this.selClinics.length&&this.clinics.length){
                this.selClinics=[this.clinics[0].id];
                this.renderMultiSelect();
            }
            this.loadDispensers().then(function(){self.refresh();});
        }
    },
    loadClinicCoverage:function(){
        return post('get_clinic_coverage').then(function(r){
            if(r.success&&r.data&&r.data.coverage){Cal.clinicCoverage=r.data.coverage;}
            else{Cal.clinicCoverage={};}
        }).fail(function(){Cal.clinicCoverage={};});
    },
    visClinics:function(){
        var active=this.clinics.filter(function(c){return c.is_active!==false&&c.is_active!=='f';});
        if(this.selClinics.length){
            return active.filter(function(c){return Cal.selClinics.indexOf(c.id)>-1;});
        }
        return active;
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
        var cid=this.selClinics.length===1?this.selClinics[0]:0;
        return post('get_dispensers',{clinic:cid,date:fmt(this.date)}).then(function(r){
            if(!r.success)return;
            // Response may be { dispensers: [...], has_schedules: bool } or flat array (backwards compat)
            if(r.data&&r.data.dispensers){
                Cal.dispensers=r.data.dispensers;
                Cal.clinicHasSchedules=!!r.data.has_schedules;
            } else {
                Cal.dispensers=r.data;
                Cal.clinicHasSchedules=false;
            }
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
    loadReferralSources:function(){
        if(Cal.referralSources&&Cal.referralSources.length)return $.Deferred().resolve();
        return post('get_referral_sources').then(function(r){
            if(r.success) Cal.referralSources=r.data||[];
        }).fail(function(){ Cal.referralSources=[]; });
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
        // Clinics — no "All Clinics" option; require selecting one
        var ch='';
        this.clinics.forEach(function(c){
            var on=Cal.selClinics.indexOf(c.id)>-1;
            ch+='<div class="hm-ms-item'+(on?' on':'')+'" data-id="'+c.id+'" data-group="clinic"><span class="hm-ms-dot" style="background:'+(on?'#fff':c.color)+'"></span>'+esc(c.name)+'</div>';
        });
        $('#hm-clinicMsDrop').html(ch);
        var cLbl=this.selClinics.length===0?'Select Clinic':this.selClinics.length===1?this.clinics.find(function(c){return c.id===Cal.selClinics[0];})?.name||'1 selected':this.selClinics.length+' selected';
        $('#hm-clinicMsBtn .hm-ms-lbl').text(cLbl);

        // Dispensers — filtered by selected clinics
        var filtDisp=this.dispensers;
        if(this.selClinics.length){
            filtDisp=this.dispensers.filter(function(d){
                if(d.clinic_ids&&d.clinic_ids.length){
                    for(var i=0;i<d.clinic_ids.length;i++){if(Cal.selClinics.indexOf(parseInt(d.clinic_ids[i]))>-1)return true;}
                    return false;
                }
                return Cal.selClinics.indexOf(parseInt(d.clinic_id||d.clinicId))>-1;
            });
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
        if(this.selClinics.length)d=d.filter(function(x){
            // Check clinic_ids array first (all assigned clinics), fall back to clinic_id
            if(x.clinic_ids&&x.clinic_ids.length){
                for(var i=0;i<x.clinic_ids.length;i++){if(Cal.selClinics.indexOf(parseInt(x.clinic_ids[i]))>-1)return true;}
                return false;
            }
            return Cal.selClinics.indexOf(parseInt(x.clinic_id||x.clinicId))>-1;
        });
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
        if(this.calViewMode==='clinic') return this.renderGridClinicView();
        return this.renderGridDispenserView();
    },

    // ── CLINIC VIEW GRID ──
    renderGridClinicView:function(){
        var dates=this.visDates(),clinics=this.visClinics(),cfg=this.cfg,gw=document.getElementById('hm-gridWrap');
        if(!gw)return;
        this.updateDateLbl(dates);this.updateViewBtns();

        var slotMap={compact:32,regular:40,large:52};
        var slotH=slotMap[cfg.slotHt]||28;
        cfg.slotHpx=slotH;

        if(!clinics.length){gw.innerHTML='<div style="text-align:center;padding:80px;color:var(--hm-text-faint);font-size:15px">No clinics found.</div>';return;}

        var colW=Math.max(100,Math.min(180,Math.floor(900/clinics.length)));
        var tc=clinics.length*dates.length;
        var cov=this.clinicCoverage||{};

        function hexToRgba(hex,a){hex=(hex||'#ccc').replace('#','');if(hex.length===3)hex=hex[0]+hex[0]+hex[1]+hex[1]+hex[2]+hex[2];var r=parseInt(hex.substring(0,2),16),g=parseInt(hex.substring(2,4),16),b=parseInt(hex.substring(4,6),16);return 'rgba('+r+','+g+','+b+','+a+')';}

        // Build per-clinic per-dayOfWeek info
        var clinicDayOff={}; // key: clinicId+'-'+dayOfWeek => true if no staff scheduled
        var clinicDayStaff={}; // key: clinicId+'-'+dayOfWeek => [{name,initials,color}]
        clinics.forEach(function(c){
            var cc=cov[String(c.id)]||{};
            for(var wd=0;wd<7;wd++){
                var staff=cc[String(wd)]||[];
                var key=c.id+'-'+wd;
                clinicDayStaff[key]=staff;
                if(!staff.length)clinicDayOff[key]=true;
            }
        });

        var h='<div class="hm-grid" style="grid-template-columns:44px repeat('+tc+',minmax('+colW+'px,1fr));--hm-cal-bg:'+(cfg.calBg||'#ffffff')+';--hm-cal-grid:'+(cfg.gridLineColor||'#e2e8f0')+';--hm-cal-today:'+(cfg.todayHighlight||'#e6f7f9')+'">';
        h+='<div class="hm-time-corner"></div>';
        dates.forEach(function(d){
            var td=isToday(d);
            h+='<div class="hm-day-hd'+(td?' today':'')+'" style="grid-column:span '+clinics.length+(td?';background:'+cfg.todayHighlight:'')+'">';
            h+='<span class="hm-day-lbl">'+DAYS[d.getDay()]+'</span> <span class="hm-day-num">'+d.getDate()+'</span> <span class="hm-day-lbl">'+MO[d.getMonth()]+'</span>';
            h+='<div class="hm-prov-row">';
            clinics.forEach(function(c){
                var key=c.id+'-'+d.getDay();
                var staff=clinicDayStaff[key]||[];
                var isOff=!!clinicDayOff[key];
                var clColor=c.clinic_colour||c.color||'#94a3b8';
                var dotCls=isOff?'hm-dot hm-dot--grey':'hm-dot hm-dot--green';
                var provBg=isOff?';background:#d4d4d4;opacity:0.55':';background:'+hexToRgba(clColor,0.15);
                var ttl=isOff?'No staff scheduled':staff.map(function(s){return s.name;}).join(', ');
                h+='<div class="hm-prov-cell" style="border-radius:4px'+provBg+'" title="'+esc(ttl)+'"><div class="hm-prov-ini"><span class="'+dotCls+' hm-dot--sm"></span>'+esc(c.name.substring(0,3).toUpperCase())+'</div></div>';
            });
            h+='</div></div>';
        });

        for(var s=0;s<cfg.totalSlots;s++){
            var tm=cfg.startH*60+s*cfg.slotMin;
            var hr=Math.floor(tm/60),mn=tm%60;
            var isHr=mn===0;
            h+='<div class="hm-time-cell'+(isHr?' hr':'')+'">'+(isHr?pad(hr)+':00':'')+'</div>';
            dates.forEach(function(d,di){
                clinics.forEach(function(c,ci){
                    var key=c.id+'-'+d.getDay();
                    var isOff=!!clinicDayOff[key];
                    var cls='hm-slot'+(isHr?' hr':'')+(ci===clinics.length-1?' dl':'')+(isOff?' hm-slot-off':'');
                    var clColor=c.clinic_colour||c.color||'#94a3b8';
                    var slotBg=isOff?';background:rgba(212,212,212,0.55)':';background:'+hexToRgba(clColor,0.06);
                    var staff=clinicDayStaff[key]||[];
                    var slotTtl=staff.length?(' title="'+esc(staff.map(function(s){return s.name;}).join(', '))+'"'):(isOff?' title="No staff scheduled — double-click to add anyway"':'');
                    h+='<div class="'+cls+'"'+slotTtl+' data-date="'+fmt(d)+'" data-time="'+pad(hr)+':'+pad(mn)+'" data-clinic="'+c.id+'" data-day="'+di+'" data-slot="'+s+'"'+(isOff?' data-off="1"':'')+' style="height:'+slotH+'px'+slotBg+'"></div>';
                });
            });
        }
        h+='</div>';
        gw.innerHTML=h;
    },

    // ── DISPENSER VIEW GRID (original) ──
    renderGridDispenserView:function(){
        var dates=this.visDates(),cfg=this.cfg,gw=document.getElementById('hm-gridWrap');
        if(!gw)return;
        this.updateDateLbl(dates);this.updateViewBtns();

        if(!this.selClinics.length){
            gw.innerHTML='<div style="text-align:center;padding:80px;color:var(--hm-text-faint);font-size:15px">Select a clinic from the dropdown above to view dispenser schedules.</div>';return;
        }

        var disps=this.visDisps();
        var slotMap={compact:32,regular:40,large:52};
        var slotH=slotMap[cfg.slotHt]||28;
        cfg.slotHpx=slotH;

        if(!disps.length){
            var noMsg=Cal.clinicHasSchedules
                ?'No staff are scheduled for this clinic. Add dispenser schedules in Admin → Dispenser Schedules.'
                :'No dispensers match your filters. Try changing the clinic or assignee filter.';
            gw.innerHTML='<div style="text-align:center;padding:80px;color:var(--hm-text-faint);font-size:15px">'+noMsg+'</div>';return;
        }

        var colW=Math.max(80,Math.min(140,Math.floor(900/disps.length)));
        var tc=disps.length*dates.length;

        // Build a clinic-color lookup for each dispenser
        var dispClinicColor={};
        disps.forEach(function(p){
            var cid=parseInt(p.clinic_id||p.clinicId||0);
            // If a single clinic is selected, use that; otherwise use the dispenser's primary clinic
            if(Cal.selClinics.length===1){cid=Cal.selClinics[0];}
            if(cid&&Cal.clinics){
                var cl=Cal.clinics.find(function(c){return c.id===cid;});
                if(cl&&(cl.clinic_colour||cl.color)){dispClinicColor[p.id]=cl.clinic_colour||cl.color;}
            }
        });
        // hex to rgba helper
        function hexToRgba(hex,a){hex=hex.replace('#','');if(hex.length===3)hex=hex[0]+hex[0]+hex[1]+hex[1]+hex[2]+hex[2];var r=parseInt(hex.substring(0,2),16),g=parseInt(hex.substring(2,4),16),b=parseInt(hex.substring(4,6),16);return 'rgba('+r+','+g+','+b+','+a+')';}

        // Build a clinic-name lookup for each dispenser (for tooltips)
        var dispClinicName={};
        disps.forEach(function(p){
            var cid=parseInt(p.clinic_id||p.clinicId||0);
            if(Cal.selClinics.length===1){cid=Cal.selClinics[0];}
            if(cid&&Cal.clinics){
                var cl=Cal.clinics.find(function(c){return c.id===cid;});
                if(cl)dispClinicName[p.id]=cl.name;
            }
        });

        // Build a set of which dispenser+day combos are NOT scheduled
        // p.scheduled_days = [0,1,2,...6] (JS day-of-week).
        // When clinicHasSchedules is true, empty scheduled_days means OFF all days.
        // When false (legacy / no schedules configured), empty means available all days.
        var dispOffDay={}; // key: dispId+'-'+jsDay  => true means OFF
        if(Cal.selClinics.length===1){
            disps.forEach(function(p){
                var sd=p.scheduled_days;
                if(sd&&sd.length){
                    for(var wd=0;wd<7;wd++){if(sd.indexOf(wd)===-1)dispOffDay[p.id+'-'+wd]=true;}
                } else if(Cal.clinicHasSchedules){
                    // Clinic uses schedules but this dispenser has none — mark off all days
                    for(var wd=0;wd<7;wd++){dispOffDay[p.id+'-'+wd]=true;}
                }
            });
        }
        // Build set of days where ALL dispensers are off (entire day greyed out)
        var dayAllOff={};
        if(Cal.clinicHasSchedules&&Cal.selClinics.length===1&&disps.length){
            for(var wd=0;wd<7;wd++){
                var allOff=true;
                for(var di2=0;di2<disps.length;di2++){
                    if(!dispOffDay[disps[di2].id+'-'+wd]){allOff=false;break;}
                }
                if(allOff)dayAllOff[wd]=true;
            }
        }

        var h='<div class="hm-grid" style="grid-template-columns:44px repeat('+tc+',minmax('+colW+'px,1fr));--hm-cal-bg:'+(cfg.calBg||'#ffffff')+';--hm-cal-grid:'+(cfg.gridLineColor||'#e2e8f0')+';--hm-cal-today:'+(cfg.todayHighlight||'#e6f7f9')+'">';
        h+='<div class="hm-time-corner"></div>';
        dates.forEach(function(d){
            var td=isToday(d);
            var dOff=!!dayAllOff[d.getDay()];
            var dayHdStyle='grid-column:span '+disps.length+(td&&!dOff?';background:'+cfg.todayHighlight:'')+(dOff?';background:#e0e0e0;opacity:0.6':'');
            h+='<div class="hm-day-hd'+(td?' today':'')+(dOff?' hm-day-off':'')+'" style="'+dayHdStyle+'">';
            h+='<span class="hm-day-lbl">'+DAYS[d.getDay()]+'</span> <span class="hm-day-num">'+d.getDate()+'</span> <span class="hm-day-lbl">'+MO[d.getMonth()]+'</span>';
            if(dOff){h+=' <span class="hm-day-lbl" style="font-size:10px;color:#888;margin-left:4px">(No staff scheduled)</span>';}
            h+='<div class="hm-prov-row">';
            disps.forEach(function(p){
                var lbl=esc(p.initials);
                var onHol=Cal.isDispOnHoliday(p.id,d);
                var isOff=!!dispOffDay[p.id+'-'+d.getDay()];
                var dotCls=onHol?'hm-dot hm-dot--red':isOff?'hm-dot hm-dot--grey':'hm-dot hm-dot--green';
                var provBg=isOff?';background:#d4d4d4;opacity:0.55':(dispClinicColor[p.id]?';background:'+hexToRgba(dispClinicColor[p.id],0.15):'');
                var ttl=onHol?'On holiday / unavailable':isOff?'Not scheduled this day':(dispClinicName[p.id]||'Available');
                h+='<div class="hm-prov-cell" style="border-radius:4px'+provBg+'" title="'+ttl+'"><div class="hm-prov-ini"><span class="'+dotCls+' hm-dot--sm" title="'+ttl+'"></span>'+lbl+'</div></div>';
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
                    var isOff=!!dispOffDay[p.id+'-'+d.getDay()];
                    var slotCls=cls+(isOff?' hm-slot-off':'');
                    var slotBg=isOff?';background:rgba(212,212,212,0.55)':(dispClinicColor[p.id]?';background:'+hexToRgba(dispClinicColor[p.id],0.06):'');
                    var slotTtl=isOff?' title="Not scheduled — double-click to add anyway"':(dispClinicName[p.id]?(' title="'+dispClinicName[p.id]+'"'):'');
                    h+='<div class="'+slotCls+'"'+slotTtl+' data-date="'+fmt(d)+'" data-time="'+pad(hr)+':'+pad(mn)+'" data-disp="'+p.id+'" data-day="'+di+'" data-slot="'+s+'"'+(isOff?' data-off="1"':'')+' style="height:'+slotH+'px'+slotBg+'"></div>';
                });
            });
        }
        h+='</div>';
        gw.innerHTML=h;
    },

    // ── APPOINTMENTS ──
    renderAppts:function(){
        $('.hm-appt').remove();
        var dates=this.visDates(),cfg=this.cfg,slotH=cfg.slotHpx;
        var isClinicView=this.calViewMode==='clinic';
        var disps=isClinicView?[]:this.visDisps();
        var clinics=isClinicView?this.visClinics():[];
        if(!isClinicView&&!disps.length)return;
        if(isClinicView&&!clinics.length)return;

        var self=this;
        // Collect card metadata for overlap detection
        var cardMeta=[];
        this.appts.forEach(function(a){
            // Filter by multi-select (dispenser view only)
            if(!isClinicView){
                if(Cal.selDisps.length&&Cal.selDisps.indexOf(parseInt(a.dispenser_id))===-1)return;
                if(Cal.selClinics.length&&Cal.selClinics.indexOf(parseInt(a.clinic_id))===-1)return;
            }

            var di=-1;
            for(var i=0;i<dates.length;i++){if(fmt(dates[i])===a.appointment_date){di=i;break;}}
            if(di===-1)return;

            if(isClinicView){
                // In clinic view, check clinic column exists
                var clinicFound=false;
                for(var j=0;j<clinics.length;j++){if(parseInt(clinics[j].id)===parseInt(a.clinic_id)){clinicFound=true;break;}}
                if(!clinicFound)return;
            } else {
                var found=false;
                for(var j=0;j<disps.length;j++){if(parseInt(disps[j].id)===parseInt(a.dispenser_id)){found=true;break;}}
                if(!found)return;
            }

            var tp=a.start_time.split(':');
            var aMn=parseInt(tp[0])*60+parseInt(tp[1]);
            if(aMn<cfg.startH*60||aMn>=cfg.endH*60)return;

            var si=Math.floor((aMn-cfg.startH*60)/cfg.slotMin);
            var off=((aMn-cfg.startH*60)%cfg.slotMin)/cfg.slotMin*slotH;
            var dur=parseInt(a.duration)||parseInt(a.service_duration)||30;
            // Height = number of slots the appointment spans × slot height (px)
            var spanSlots=dur/cfg.slotMin;
            var h=Math.max(slotH*0.8, spanSlots*slotH - 2);

            var $t;
            if(isClinicView){
                $t=$('.hm-slot[data-day="'+di+'"][data-slot="'+si+'"][data-clinic="'+a.clinic_id+'"]');
            } else {
                $t=$('.hm-slot[data-day="'+di+'"][data-slot="'+si+'"][data-disp="'+a.dispenser_id+'"]');
            }
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
            var colKey=isClinicView?parseInt(a.clinic_id):parseInt(a.dispenser_id);
            cardMeta.push({el:el, di:di, disp:colKey, startMn:aMn, endMn:aMn+dur});

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
        if(this.calViewMode==='clinic')return; // Exclusions only render in dispenser view
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
        var now=new Date(),dates=this.visDates(),cfg=this.cfg;
        var isClinicView=this.calViewMode==='clinic';
        var columns=isClinicView?this.visClinics():this.visDisps();
        var di=-1;
        for(var i=0;i<dates.length;i++){if(isToday(dates[i])){di=i;break;}}
        if(di===-1)return;
        var nm=now.getHours()*60+now.getMinutes();
        if(nm<cfg.startH*60||nm>=cfg.endH*60)return;
        var si=Math.floor((nm-cfg.startH*60)/cfg.slotMin);
        var off=((nm-cfg.startH*60)%cfg.slotMin)/cfg.slotMin*cfg.slotHpx;
        var dataAttr=isClinicView?'data-clinic':'data-disp';
        columns.forEach(function(p){
            var $t=$('.hm-slot[data-day="'+di+'"][data-slot="'+si+'"]['+dataAttr+'="'+p.id+'"]');
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
        var canAdminReopen=!!(HM&&HM.is_admin);

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
        if(isLocked&&!canAdminReopen){
            h+='<button class="hm-pop-act hm-pop-act--primary hm-pop-edit">View Details / Add Note</button>';
            h+='<div style="font-size:11px;color:#059669;margin-top:4px;display:flex;align-items:center;gap:4px"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg> Appointment closed — notes can still be added</div>';
        } else {
            h+='<button class="hm-pop-act hm-pop-act--primary hm-pop-edit">Edit</button>';
            if(!isLocked){
                h+='<button class="hm-pop-act hm-pop-act--teal hm-pop-closeoff" data-sid="'+a.service_id+'" data-aid="'+a._ID+'">Close Off</button>';
            }
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
        // Ensure services, clinics & referral sources loaded for the edit modal
        var ready=$.Deferred();
        if(!self.services.length||!self.clinics.length){
            $.when(
                self.services.length?null:self.loadServices(),
                self.clinics.length?null:self.loadClinics(),
                self.dispensers.length?null:self.loadDispensers(),
                self.loadReferralSources()
            ).always(function(){ready.resolve();});
        } else {
            self.loadReferralSources().always(function(){ready.resolve();});
        }
        ready.then(function(){ self._buildEditModal(a); });
    },
    _buildEditModal:function(a){
        var self=this;
        var isLocked=a.status==='Completed'||!!(a.outcome_name);
        var canAdminReopen=!!(HM&&HM.is_admin);
        var closedNotesOnly=isLocked&&!canAdminReopen;
        var svcOpts=self.services.map(function(s){return'<option value="'+s.id+'"'+(parseInt(s.id)===parseInt(a.service_id)?' selected':'')+'>'+esc(s.name)+'</option>';}).join('');
        var cliOpts=self.clinics.map(function(c){return'<option value="'+c.id+'"'+(parseInt(c.id)===parseInt(a.clinic_id)?' selected':'')+'>'+esc(c.name)+'</option>';}).join('');
        var dispOpts=self.dispensers.map(function(d){return'<option value="'+d.id+'"'+(parseInt(d.id)===parseInt(a.dispenser_id)?' selected':'')+'>'+esc(d.name)+'</option>';}).join('');
        var refOpts='<option value="">— Select Referral Type —</option>';
        if(self.referralSources&&self.referralSources.length){refOpts+=self.referralSources.map(function(rs){return'<option value="'+rs.id+'"'+(parseInt(rs.id)===parseInt(a.referral_source_id||0)?' selected':'')+'>'+esc(rs.name)+'</option>';}).join('');}
        var curSmsHrs=parseInt(a.sms_reminder_hours||0);
        var title=isLocked?(canAdminReopen?'Reopen / Edit Appointment':'Appointment Details'):'Edit Appointment';
        var dis=closedNotesOnly?' disabled':'';
        var statusDis=(isLocked&&canAdminReopen)?'':' disabled';
        var html='<div class="hm-modal-bg open"><div class="hm-modal hm-modal--md">'+
            '<div class="hm-modal-hd"><h3>'+title+'</h3><button class="hm-close hm-edit-close">'+IC.x+'</button></div>'+
            '<div class="hm-modal-body">';
        if(isLocked){
            html+='<div style="margin-bottom:12px;padding:10px 14px;background:#ecfdf5;border:1px solid #a7f3d0;border-radius:8px;font-size:12px;color:#065f46;display:flex;align-items:center;gap:8px"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg><strong>Closed</strong> — This appointment has been completed with outcome: <strong>'+esc(a.outcome_name||'Completed')+'</strong>'+(canAdminReopen?' · You can reopen/edit this appointment.':' · You can still add notes.')+'</div>';
        }
        html+='<div class="hm-row"><div class="hm-fld"><label>Appointment Type</label><select class="hm-inp" id="hme-service"'+dis+'>'+svcOpts+'</select></div>'+
                '<div class="hm-fld"><label>Assignee</label><select class="hm-inp" id="hme-disp"'+dis+'>'+dispOpts+'</select></div></div>'+
                '<div class="hm-row"><div class="hm-fld"><label>Clinic</label><select class="hm-inp" id="hme-clinic"'+dis+'>'+cliOpts+'</select></div>'+
                '<div class="hm-fld"><label>Location</label><select class="hm-inp" id="hme-loc"'+dis+'><option>Clinic</option><option>Home</option></select></div></div>'+
                '<div class="hm-row"><div class="hm-fld"><label>Date</label><input type="date" class="hm-inp" id="hme-date" value="'+a.appointment_date+'"'+dis+'></div>'+
                '<div class="hm-fld"><label>Start Time</label><input type="time" class="hm-inp" id="hme-time" value="'+(a.start_time||'').substring(0,5)+'"'+dis+'></div></div>'+
                '<div class="hm-row"><div class="hm-fld"><label>Status</label><select class="hm-inp" id="hme-status"'+statusDis+'><option>Not Confirmed</option><option>Confirmed</option><option>Arrived</option><option>In Progress</option><option>Completed</option><option>Late</option><option>No Show</option><option>Cancelled</option><option>Rescheduled</option><option>Pending</option></select></div>'+
                '<div class="hm-fld"><label>Referral Type <span style="color:#ef4444">*</span></label><select class="hm-inp" id="hme-referral-source"'+dis+'>'+refOpts+'</select></div></div>'+
                '<div style="margin:12px 0;padding:12px 14px;background:#f0f9ff;border:1px solid #bae6fd;border-radius:8px">'+
                    '<div style="font-size:11px;font-weight:700;color:#0369a1;text-transform:uppercase;letter-spacing:.3px;margin-bottom:8px">Confirmation &amp; Reminders</div>'+
                    '<div class="hm-fld" style="margin:0"><label style="font-size:12px">SMS Reminder</label><select class="hm-inp" id="hme-sms-reminder"'+dis+'>'+
                        '<option value="0"'+(curSmsHrs===0?' selected':'')+'>No reminder</option>'+
                        '<option value="24"'+(curSmsHrs===24?' selected':'')+'>24 hours before</option>'+
                        '<option value="48"'+(curSmsHrs===48?' selected':'')+'>48 hours before</option>'+
                        '<option value="72"'+(curSmsHrs===72?' selected':'')+'>72 hours before</option>'+
                    '</select></div>'+
                '</div>'+
                '<div class="hm-fld"><label>Notes</label><textarea class="hm-inp" id="hme-notes" rows="3">'+esc(a.notes||'')+'</textarea></div>'+
            '</div>';
        if(closedNotesOnly){
            html+='<div class="hm-modal-ft"><span></span><div class="hm-modal-acts"><button class="hm-btn hm-edit-close">Close</button><button class="hm-btn hm-btn--primary hm-edit-save">Save Note</button></div></div>';
        } else {
            html+='<div class="hm-modal-ft"><button class="hm-btn hm-btn--danger hm-edit-del">Delete</button><div class="hm-modal-acts"><button class="hm-btn hm-edit-close">Cancel</button><button class="hm-btn hm-btn--primary hm-edit-save">Save</button></div></div>';
        }
        html+='</div></div>';
        $('body').append(html);
        $('#hme-status').val(a.status||'Not Confirmed');
        $('#hme-loc').val(a.location_type||'Clinic');
        $(document).off('click.editclose').on('click.editclose','.hm-edit-close',function(){$('.hm-modal-bg').remove();$(document).off('.editclose .editsave .editdel');});
        $(document).off('click.editsave').on('click.editsave','.hm-edit-save',function(){
            var payload={appointment_id:a._ID,notes:$('#hme-notes').val()};
            if(!closedNotesOnly){
                var refSrcId=$('#hme-referral-source').val();
                if(!refSrcId){alert('Please select a Referral Type.');$('#hme-referral-source').focus();return;}
                payload.appointment_date=$('#hme-date').val();
                payload.start_time=$('#hme-time').val();
                payload.status=$('#hme-status').val();
                payload.location_type=$('#hme-loc').val();
                payload.service_id=$('#hme-service').val();
                payload.clinic_id=$('#hme-clinic').val();
                payload.dispenser_id=$('#hme-disp').val();
                payload.referral_source_id=refSrcId;
                payload.sms_reminder_hours=$('#hme-sms-reminder').val();
            }
            post('update_appointment',payload)
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
        if((a.status==='Completed'||!!(a.outcome_name))&&!(HM&&HM.is_admin)){
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
                oh+=' data-note="'+(o.requires_note?'1':'0')+'" data-order="'+(o.triggers_order?'1':'0')+'" data-invoice="'+(o.triggers_invoice?'1':'0')+'"';
                oh+=' data-followup="'+(o.triggers_followup?'1':'0')+'"';
                oh+=' data-fu-svc="'+esc(JSON.stringify(o.followup_service_ids||[]))+'"';
                oh+=' style="display:flex;align-items:center;gap:10px;padding:10px 14px;border:2px solid #e2e8f0;border-radius:8px;background:#fff;cursor:pointer;font-size:13px;font-weight:600;color:#334155;transition:all .15s;text-align:left;width:100%">';
                oh+='<span style="width:14px;height:14px;border-radius:4px;background:'+esc(o.outcome_color)+';flex-shrink:0"></span>';
                oh+='<span style="flex:1">'+esc(o.outcome_name)+'</span>';
                var badges='';
                if(o.triggers_order)badges+='<span style="font-size:9px;background:#fef3c7;color:#92400e;padding:2px 6px;border-radius:4px;font-weight:700">Order</span>';
                if(o.triggers_invoice)badges+='<span style="font-size:9px;background:#dbeafe;color:#1e40af;padding:2px 6px;border-radius:4px;font-weight:700">Invoice</span>';
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
            var triggersOrder=$o.data('order')==='1'||$o.data('order')===1;
            var triggersInvoice=$o.data('invoice')==='1'||$o.data('invoice')===1;
            var needsFollowup=$o.data('followup')==='1'||$o.data('followup')===1;
            var fuSvc=[];
            try{fuSvc=JSON.parse($o.attr('data-fu-svc')||'[]');}catch(e){}

            self._outcomeData={
                id:$o.data('oid'),
                color:$o.data('color'),
                name:$o.data('name'),
                requires_note:needsNote,
                triggers_order:triggersOrder,
                triggers_invoice:triggersInvoice,
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

            // Show/hide order/invoice notice
            if(triggersOrder){
                $('#hm-om-invoice-area').show().find('div > div:first-child').text('This outcome creates a new order');
                $('#hm-om-invoice-area').find('div > div:last-child').text('A new order form will open after saving.');
                self._outcomeInvoicePending=true;
            } else if(triggersInvoice){
                $('#hm-om-invoice-area').show().find('div > div:first-child').text('This outcome triggers invoice flow');
                $('#hm-om-invoice-area').find('div > div:last-child').text('You can select an existing order or create a new one.');
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

                // Determine flows needed
                var needsOrder=(o.triggers_order||o.triggers_invoice)&&a.patient_id;
                var needsFollowUp=fuSvcId&&a.patient_id;

                if(needsFollowUp&&needsOrder){
                    // Follow-up first, then open order page
                    setTimeout(function(){
                        self._openFollowUpBooking(a,fuSvcId,function(){
                            self._openOrderPage(a,o);
                        });
                    },100);
                } else if(needsFollowUp){
                    setTimeout(function(){self._openFollowUpBooking(a,fuSvcId);},100);
                } else if(needsOrder){
                    self._openOrderPage(a,o);
                }
            }).fail(function(){
                $btn.prop('disabled',false).text('Save Outcome');
                $('#hm-om-err').text('Network error — please try again.');
            });
        });
    },

    // ── Full-Screen Order Page ──
    _openOrderPage:function(a,outcome){
        var self=this;
        var _backLabel=(window._hmOrderOpts&&window._hmOrderOpts.backLabel)||'Calendar';
        // Hide existing page content, show order page inside main container
        // .hm-main (Elementor class) → #hm-app (shortcode wrapper) → body
        var $hmMain=$('.hm-main').first();
        if(!$hmMain.length) $hmMain=$('#hm-app').first();
        if(!$hmMain.length) $hmMain=$('body');
        $hmMain.children().not('#hm-op,#hm-op-loading').hide();
        $hmMain.css({display:'flex',flexDirection:'column',minHeight:'calc(100vh - 60px)'});
        $hmMain.append('<div id="hm-op-loading" style="display:flex;align-items:center;justify-content:center;min-height:400px"><div style="text-align:center;color:var(--hm-text-muted,#94a3b8);font-family:var(--hm-font)"><div style="font-size:16px;font-weight:600;margin-bottom:6px">Loading Order Page</div><div style="font-size:13px;opacity:.6">'+esc(a.patient_name||'')+'</div></div></div>');

        post('get_order_products',{}).then(function(pR){
            if(!pR.success){$('#hm-op-loading').remove();$hmMain.css({display:'',flexDirection:''});$hmMain.children().show();self.toast('Failed to load products');return;}
            var allProducts=pR.data.products||[];
            var allSvcs=pR.data.services||[];
            var allRanges=pR.data.ranges||[];

            // Derived data
            var catMap={product:'Hearing Aid',service:'Service',accessory:'Accessory',consumable:'Consumable',bundled:'Bundled Item'};
            var categories={};
            allProducts.forEach(function(p){var c=p.item_type||'product';if(!categories[c])categories[c]=catMap[c]||c;});
            if(allSvcs.length)categories['service']='Service';

            var speakerProds=allProducts.filter(function(p){return p.item_type==='bundled'&&p.bundled_category==='Speaker';});
            var spkSizes={},spkPowers={};
            speakerProds.forEach(function(p){if(p.speaker_length!==null&&p.speaker_length!=='')spkSizes[p.speaker_length]=1;if(p.speaker_power)spkPowers[p.speaker_power]=1;});
            var domeProds=allProducts.filter(function(p){return p.item_type==='bundled'&&p.bundled_category==='Dome';});
            var dmSizes={},dmTypes={};
            domeProds.forEach(function(p){if(p.dome_size)dmSizes[p.dome_size]=1;if(p.dome_type)dmTypes[p.dome_type]=1;});

            function build(existOrders){
                $('#hm-op-loading').remove();
                var svcCol=a.service_colour||outcome.color||'#3B82F6';
                var LS='margin-bottom:14px';
                var LB='font-size:11px;font-weight:700;color:var(--hm-text,#334155);text-transform:uppercase;letter-spacing:.3px;display:block;margin-bottom:5px';
                var INP='font-size:13px;padding:9px 12px;border-radius:8px;border:1.5px solid var(--hm-border,#e2e8f0);width:100%;background:#fff;transition:border-color .15s;box-sizing:border-box';

                // ═══════ BUILD HTML ═══════
                var h='<div id="hm-op" style="display:flex;flex-direction:column;font-family:var(--hm-font,\'Source Sans 3\',sans-serif);color:var(--hm-text,#334155);-webkit-font-smoothing:antialiased;animation:hmOpIn .25s ease;flex:1;width:100%;min-height:0">';

                // ── Top bar ──
                h+='<div style="background:var(--hm-teal,#0BB4C4);color:#fff;display:flex;align-items:center;justify-content:space-between;padding:0 24px;height:50px;flex-shrink:0">';
                h+='<button id="hm-op-back" style="background:none;border:1px solid rgba(255,255,255,.2);color:#fff;font-size:13px;font-weight:600;cursor:pointer;padding:6px 14px;border-radius:6px;font-family:var(--hm-font-btn);display:flex;align-items:center;gap:6px;transition:all .15s">';
                h+='<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 8H1M8 15L1 8l7-7"/></svg> '+esc(_backLabel)+'</button>';
                h+='<div style="text-align:center"><div style="font-family:var(--hm-font-title,\'Cormorant Garamond\',serif);font-size:20px;font-weight:700;letter-spacing:-.3px">New Order</div>';
                h+='<div style="font-size:11px;opacity:.7;margin-top:1px">'+esc(a.patient_name||'Patient')+' — '+esc(outcome.name||'')+'</div></div>';
                h+='<div style="min-width:90px;text-align:right"><span style="font-size:11px;opacity:.5">'+esc(a.service_name||'')+'</span></div></div>';

                // ── Split panels ──
                h+='<div style="display:flex;flex:1;overflow:hidden;min-height:0">';

                // ═════ LEFT PANEL ═════
                h+='<div id="hm-op-left" style="flex:0 0 40%;max-width:40%;overflow-y:auto;padding:24px 28px;background:#fff;border-right:1px solid var(--hm-border,#e2e8f0)">';

                // Existing orders
                if(existOrders.length){
                    h+='<div style="margin-bottom:24px"><div style="font-size:12px;font-weight:700;color:var(--hm-text-light,#64748b);text-transform:uppercase;letter-spacing:.5px;margin-bottom:10px;display:flex;align-items:center;gap:8px">';
                    h+='<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="18" rx="2"/><path d="M8 7h8M8 11h6M8 15h4"/></svg> Active Orders for '+esc(a.patient_name||'Patient')+'</div>';
                    existOrders.forEach(function(ord){
                        var bal=parseFloat(ord.balance_due)||0;
                        h+='<div class="hm-op-ex-card" data-oid="'+ord.id+'" data-onum="'+esc(ord.order_number)+'" data-total="'+ord.grand_total+'" data-bal="'+bal.toFixed(2)+'" data-prsi="'+(ord.has_prsi?'1':'0')+'" ';
                        h+='style="border:1.5px solid var(--hm-border,#e2e8f0);border-radius:10px;padding:14px 16px;margin-bottom:8px;cursor:pointer;transition:all .15s;background:#fff;display:flex;align-items:center;gap:12px">';
                        h+='<div style="flex:1;min-width:0">';
                        h+='<div style="font-size:14px;font-weight:700;color:var(--hm-navy,#151B33)">'+esc(ord.order_number)+'</div>';
                        h+='<div style="font-size:11px;color:var(--hm-text-light,#64748b);margin-top:2px">'+esc(ord.order_date)+' — <span style="font-weight:600;color:#0369a1">'+esc(ord.status)+'</span></div>';
                        if(ord.items_summary)h+='<div style="font-size:12px;color:var(--hm-text,#334155);margin-top:3px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:320px">'+esc(ord.items_summary)+'</div>';
                        h+='</div>';
                        h+='<div style="text-align:right;flex-shrink:0">';
                        h+='<div style="font-size:15px;font-weight:700;color:var(--hm-navy,#151B33)">€'+parseFloat(ord.grand_total).toFixed(2)+'</div>';
                        if(parseFloat(ord.deposit)>0)h+='<div style="font-size:11px;color:#059669;margin-top:2px">Paid: €'+parseFloat(ord.deposit).toFixed(2)+'</div>';
                        h+='<div style="font-size:11px;color:#dc2626;margin-top:1px">Due: €'+bal.toFixed(2)+'</div>';
                        h+='</div>';
                        h+='<svg width="18" height="18" fill="none" stroke="#94a3b8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0"><path d="M7 3l6 6-6 6"/></svg>';
                        h+='</div>';
                    });
                    h+='<div style="border-bottom:1px solid var(--hm-border,#e2e8f0);margin:16px 0 20px"></div></div>';
                }

                // Product selection
                h+='<div><div style="font-size:12px;font-weight:700;color:var(--hm-text-light,#64748b);text-transform:uppercase;letter-spacing:.5px;margin-bottom:14px;display:flex;align-items:center;gap:8px">';
                h+='<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 002 1.61h9.72a2 2 0 002-1.61L23 6H6"/></svg> Add Products</div>';
                h+='<input type="hidden" id="hm-op-pid" value="'+a.patient_id+'">';
                h+='<input type="hidden" id="hm-op-aid" value="'+a._ID+'">';

                // Pick from Stock button
                h+='<div style="margin-bottom:14px">';
                h+='<button type="button" id="hm-op-stock-btn" style="width:100%;padding:10px 14px;font-size:12px;font-weight:700;border-radius:8px;border:1.5px dashed var(--hm-teal,#0BB4C4);background:#f0fdfa;color:var(--hm-teal,#0BB4C4);cursor:pointer;font-family:var(--hm-font-btn);transition:all .15s;display:flex;align-items:center;justify-content:center;gap:8px">';
                h+='<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/><path d="M3.27 6.96L12 12.01l8.73-5.05M12 22.08V12"/></svg>';
                h+='Pick from Stock</button></div>';

                // Stock picker panel (hidden by default)
                h+='<div id="hm-op-stock-panel" style="display:none;background:#f8fafc;border:1.5px solid var(--hm-border,#e2e8f0);border-radius:10px;padding:14px 16px;margin-bottom:14px">';
                h+='<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px">';
                h+='<span style="font-size:12px;font-weight:700;color:var(--hm-navy,#151B33);text-transform:uppercase;letter-spacing:.5px">Available Stock</span>';
                h+='<button type="button" id="hm-op-stock-close" style="border:none;background:none;font-size:16px;cursor:pointer;color:var(--hm-text-light,#64748b);padding:2px 6px">×</button></div>';
                h+='<input type="text" id="hm-op-stock-search" placeholder="Search by model, serial or manufacturer…" style="'+INP+';margin-bottom:10px;font-size:12px">';
                h+='<div id="hm-op-stock-list" style="max-height:280px;overflow-y:auto"></div>';
                h+='</div>';

                // Category
                h+='<div style="'+LS+'"><label style="'+LB+'">Product Type</label>';
                h+='<select id="hm-op-cat" style="'+INP+'"><option value="">— Select Category —</option>';
                Object.keys(categories).forEach(function(k){h+='<option value="'+k+'">'+esc(categories[k])+'</option>';});
                h+='</select></div>';

                // Browse mode toggle
                h+='<div id="hm-op-browse-toggle" style="display:none;'+LS+'">';
                h+='<div style="display:flex;gap:0;border-radius:8px;overflow:hidden;border:1.5px solid var(--hm-navy,#151B33)">';
                h+='<button type="button" class="hm-op-browse-btn" data-mode="range" style="flex:1;padding:8px 0;font-size:12px;font-weight:700;cursor:pointer;border:none;background:var(--hm-navy,#151B33);color:#fff;font-family:var(--hm-font-btn);transition:all .15s">HearMed Range</button>';
                h+='<button type="button" class="hm-op-browse-btn" data-mode="product" style="flex:1;padding:8px 0;font-size:12px;font-weight:700;cursor:pointer;border:none;background:#fff;color:var(--hm-navy,#151B33);font-family:var(--hm-font-btn);transition:all .15s">Browse by Manufacturer</button>';
                h+='</div></div>';

                // Range
                h+='<div id="hm-op-range-wrap" style="display:none;'+LS+'"><label style="'+LB+'">HearMed Range</label>';
                h+='<select id="hm-op-range" style="'+INP+'"><option value="">— Select Range —</option></select></div>';

                // Manufacturer
                h+='<div id="hm-op-mfr-wrap" style="display:none;'+LS+'"><label style="'+LB+'">Manufacturer</label>';
                h+='<select id="hm-op-mfr" style="'+INP+'"><option value="">— Select Manufacturer —</option></select></div>';

                // Style
                h+='<div id="hm-op-style-wrap" style="display:none;'+LS+'"><label style="'+LB+'">Style</label>';
                h+='<select id="hm-op-style" style="'+INP+'"><option value="">— Select Style —</option></select></div>';

                // Product
                h+='<div id="hm-op-prod-wrap" style="display:none;'+LS+'"><label style="'+LB+'">Product</label>';
                h+='<select id="hm-op-prod" style="'+INP+'"><option value="">— Select Product —</option></select></div>';

                // Tech level (read-only)
                h+='<div id="hm-op-tech-wrap" style="display:none;'+LS+'"><label style="'+LB+'">Tech Level</label>';
                h+='<div id="hm-op-tech" style="font-size:13px;font-weight:600;color:#0f172a;padding:9px 12px;background:var(--hm-bg-alt,#f8fafc);border-radius:8px;border:1px solid var(--hm-border,#e2e8f0)">—</div></div>';

                // Ear
                h+='<div id="hm-op-ear-wrap" style="display:none;'+LS+'"><label style="'+LB+'">Ear</label>';
                h+='<select id="hm-op-ear" style="'+INP+'">';
                h+='<option value="">— Select —</option><option value="Left">Left</option><option value="Right">Right</option><option value="Binaural">Binaural (both)</option>';
                h+='</select></div>';

                // Charger
                h+='<div id="hm-op-charger-wrap" style="display:none;'+LS+';padding:12px 14px;background:#fffbeb;border:1px solid #fde68a;border-radius:8px">';
                h+='<label style="display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer;color:#92400e;font-weight:600"><input type="checkbox" id="hm-op-charger" style="accent-color:#f59e0b"> Include Charger</label></div>';

                // Speaker
                h+='<div id="hm-op-speaker-wrap" style="display:none;'+LS+'">';
                h+='<div style="display:flex;gap:10px">';
                h+='<div style="flex:1"><label style="'+LB+'">Speaker Size</label>';
                h+='<select id="hm-op-speaker-size" style="'+INP+'"><option value="">—</option>';
                Object.keys(spkSizes).sort(function(x,y){return Number(x)-Number(y);}).forEach(function(v){h+='<option value="'+v+'">'+v+'</option>';});
                h+='</select></div>';
                h+='<div style="flex:1"><label style="'+LB+'">Speaker Type</label>';
                h+='<select id="hm-op-speaker-type" style="'+INP+'"><option value="">—</option>';
                Object.keys(spkPowers).sort().forEach(function(v){h+='<option value="'+esc(v)+'">'+esc(v)+'</option>';});
                h+='</select></div></div></div>';

                // Dome
                h+='<div id="hm-op-dome-wrap" style="display:none;'+LS+'">';
                h+='<div style="display:flex;gap:10px">';
                h+='<div style="flex:1"><label style="'+LB+'">Dome Size</label>';
                h+='<select id="hm-op-dome-size" style="'+INP+'"><option value="">—</option>';
                Object.keys(dmSizes).sort().forEach(function(v){h+='<option value="'+esc(v)+'">'+esc(v)+'</option>';});
                h+='</select></div>';
                h+='<div style="flex:1"><label style="'+LB+'">Dome Type</label>';
                h+='<select id="hm-op-dome-type" style="'+INP+'"><option value="">—</option>';
                Object.keys(dmTypes).sort().forEach(function(v){h+='<option value="'+esc(v)+'">'+esc(v)+'</option>';});
                h+='</select></div></div></div>';

                // Add to order button
                h+='<div id="hm-op-add-wrap" style="display:none;'+LS+';text-align:right">';
                h+='<button type="button" id="hm-op-add-item" style="font-size:13px;font-weight:600;padding:8px 20px;border-radius:8px;border:none;background:var(--hm-teal,#0BB4C4);color:#fff;cursor:pointer;font-family:var(--hm-font-btn);transition:all .15s">+ Add to Order</button></div>';

                h+='</div>'; // end product selection
                h+='</div>'; // end left panel

                // ═════ RIGHT PANEL ═════
                h+='<div id="hm-op-right" style="flex:0 0 60%;max-width:60%;display:flex;flex-direction:column;padding:28px 32px;background:#fff;border-left:1px solid var(--hm-border,#e2e8f0)">';

                // ── Pay existing order view (hidden by default) ──
                h+='<div id="hm-op-payex" style="display:none">';
                h+='<div style="display:flex;align-items:center;gap:10px;margin-bottom:16px">';
                h+='<button id="hm-op-payex-back" style="background:none;border:1px solid var(--hm-border,#e2e8f0);border-radius:6px;padding:5px 10px;cursor:pointer;font-size:11px;color:var(--hm-text-light,#64748b);font-family:var(--hm-font)">← Back</button>';
                h+='<span style="font-size:12px;font-weight:700;color:var(--hm-text-light,#64748b);text-transform:uppercase;letter-spacing:.5px">Pay Existing Order</span></div>';
                h+='<div id="hm-op-payex-detail" style="background:#fff;border-radius:10px;border:1px solid var(--hm-border,#e2e8f0);padding:16px;margin-bottom:16px"></div>';
                h+='<div id="hm-op-payex-form"></div>';
                h+='</div>';

                // ── New order summary (default view) ── INVOICE STYLE
                h+='<div id="hm-op-neworder" style="display:flex;flex-direction:column;height:100%">';

                // Invoice header
                h+='<div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:20px">';
                h+='<div>';
                h+='<div style="font-size:22px;font-weight:700;font-family:var(--hm-font-title,Cormorant Garamond,serif);color:var(--hm-navy,#151B33);letter-spacing:1px">INVOICE</div>';
                h+='<div style="font-size:11px;color:var(--hm-text-muted,#94a3b8);margin-top:2px">Draft &bull; '+(new Date().toLocaleDateString('en-IE',{day:'numeric',month:'short',year:'numeric'}))+'</div>';
                h+='</div>';
                h+='<div style="text-align:right">';
                h+='<div style="font-size:12px;font-weight:600;color:var(--hm-navy,#151B33)">'+esc(a.patient_name||'Patient')+'</div>';
                h+='<div style="font-size:11px;color:var(--hm-text-light,#64748b)">'+esc(outcome||'')+'</div>';
                h+='</div></div>';

                // Line items table
                h+='<div style="flex:1;overflow-y:auto;margin-bottom:16px">';
                h+='<table style="width:100%;border-collapse:collapse;font-family:var(--hm-font)">';
                h+='<thead><tr style="border-bottom:2px solid var(--hm-navy,#151B33)">';
                h+='<th style="text-align:left;padding:6px 0;font-size:10px;font-weight:700;color:var(--hm-text-light,#64748b);text-transform:uppercase;letter-spacing:.8px">Item</th>';
                h+='<th style="text-align:center;padding:6px 8px;font-size:10px;font-weight:700;color:var(--hm-text-light,#64748b);text-transform:uppercase;letter-spacing:.8px;width:40px">Qty</th>';
                h+='<th style="text-align:right;padding:6px 8px;font-size:10px;font-weight:700;color:var(--hm-text-light,#64748b);text-transform:uppercase;letter-spacing:.8px;width:80px">Price</th>';
                h+='<th style="text-align:right;padding:6px 0;font-size:10px;font-weight:700;color:var(--hm-text-light,#64748b);text-transform:uppercase;letter-spacing:.8px;width:80px">Total</th>';
                h+='<th style="width:28px"></th>';
                h+='</tr></thead>';
                h+='<tbody id="hm-op-items"><tr><td colspan="5" style="text-align:center;padding:24px 0;color:var(--hm-text-muted,#94a3b8);font-size:13px;font-style:italic">No items added yet</td></tr></tbody>';
                h+='</table></div>';

                // ─── Totals block ───
                h+='<div style="border-top:1px solid var(--hm-border,#e2e8f0);padding-top:12px">';
                var TR='display:flex;justify-content:space-between;align-items:center;font-size:13px;padding:3px 0';

                // Subtotal & VAT
                h+='<div style="'+TR+';color:var(--hm-text-light,#64748b)"><span>Subtotal (excl. VAT)</span><span id="hm-op-sub" style="font-variant-numeric:tabular-nums">€0.00</span></div>';
                h+='<div style="'+TR+';color:var(--hm-text-light,#64748b)"><span>VAT</span><span id="hm-op-vat" style="font-variant-numeric:tabular-nums">€0.00</span></div>';

                // Discount row
                h+='<div style="'+TR+';margin-top:6px;padding-top:8px;border-top:1px dashed var(--hm-border-light,#e2e8f0)">';
                h+='<div style="display:flex;align-items:center;gap:6px">';
                h+='<span style="font-size:13px;color:var(--hm-text,#334155)">Discount</span>';
                h+='<div style="display:inline-flex;border:1px solid var(--hm-border,#e2e8f0);border-radius:4px;overflow:hidden">';
                h+='<button type="button" class="hm-op-disc-mode" data-mode="pct" style="padding:2px 8px;font-size:10px;font-weight:700;cursor:pointer;border:none;background:var(--hm-navy,#151B33);color:#fff">%</button>';
                h+='<button type="button" class="hm-op-disc-mode" data-mode="eur" style="padding:2px 8px;font-size:10px;font-weight:700;cursor:pointer;border:none;background:#fff;color:var(--hm-text,#334155)">€</button>';
                h+='</div>';
                h+='<input type="number" id="hm-op-disc" value="0" min="0" max="100" step="1" style="width:60px;font-size:12px;padding:3px 6px;border-radius:4px;border:1px solid var(--hm-border,#e2e8f0);text-align:right;font-variant-numeric:tabular-nums;font-family:var(--hm-font)">';
                h+='<span id="hm-op-disc-unit" style="font-size:11px;font-weight:600;color:var(--hm-text-light,#64748b)">%</span>';
                h+='</div>';
                h+='<span id="hm-op-disc-amt" style="font-size:13px;color:#dc2626;font-variant-numeric:tabular-nums">−€0.00</span></div>';
                h+='<div id="hm-op-disc-row" style="display:none"></div>';

                // PRSI deductions
                h+='<div id="hm-op-prsi-wrap" style="margin-top:6px;padding-top:8px;border-top:1px dashed var(--hm-border-light,#e2e8f0)">';
                h+='<div style="'+TR+'">';
                h+='<label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:13px;color:var(--hm-text,#334155)">';
                h+='<input type="checkbox" id="hm-op-prsi-l" style="accent-color:#0e7490;width:14px;height:14px"> PRSI Grant — Left ear</label>';
                h+='<span style="font-size:13px;color:#059669;font-variant-numeric:tabular-nums">−€500.00</span></div>';
                h+='<div style="'+TR+'">';
                h+='<label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:13px;color:var(--hm-text,#334155)">';
                h+='<input type="checkbox" id="hm-op-prsi-r" style="accent-color:#0e7490;width:14px;height:14px"> PRSI Grant — Right ear</label>';
                h+='<span style="font-size:13px;color:#059669;font-variant-numeric:tabular-nums">−€500.00</span></div>';
                h+='<div id="hm-op-prsi-row" style="display:none"></div>';
                h+='</div>';

                // Grand total
                h+='<div style="display:flex;justify-content:space-between;align-items:baseline;margin-top:10px;padding-top:10px;border-top:2px solid var(--hm-navy,#151B33)">';
                h+='<span style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:var(--hm-navy,#151B33)">Total Due</span>';
                h+='<span id="hm-op-total" style="font-size:22px;font-weight:700;color:var(--hm-navy,#151B33);font-variant-numeric:tabular-nums">€0.00</span></div>';
                h+='</div>'; // end totals block

                // Notes
                h+='<div style="margin-top:14px"><textarea id="hm-op-notes" rows="2" placeholder="Order notes..." style="'+INP+';resize:vertical;font-size:12px;background:var(--hm-bg-alt,#f8fafc);border:1px solid var(--hm-border,#e2e8f0)"></textarea></div>';

                // Error
                h+='<div id="hm-op-err" style="color:#ef4444;font-size:12px;margin-top:6px"></div>';

                // Action buttons
                h+='<div id="hm-op-actions" style="display:flex;gap:10px;margin-top:14px">';
                h+='<button id="hm-op-submit" style="flex:1;padding:12px;font-size:13px;font-weight:700;border-radius:6px;border:1px solid var(--hm-border,#e2e8f0);background:#fff;color:var(--hm-navy,#151B33);cursor:pointer;font-family:var(--hm-font-btn);transition:all .15s">Submit Order</button>';
                h+='<button id="hm-op-pay-btn" style="flex:1;padding:12px;font-size:13px;font-weight:700;border-radius:6px;border:none;background:#059669;color:#fff;cursor:pointer;font-family:var(--hm-font-btn);transition:all .15s">Take Payment</button>';
                h+='</div>';

                // ── Payment section (hidden until Take Payment clicked) ──
                h+='<div id="hm-op-paysec" style="display:none;border-top:1px solid var(--hm-border,#e2e8f0);padding-top:16px;margin-top:12px">';
                h+='<div style="display:flex;justify-content:space-between;align-items:baseline;margin-bottom:14px">';
                h+='<span style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:#059669">Payment</span>';
                h+='<span style="font-size:11px;color:var(--hm-text-light,#64748b)">Total: <strong id="hm-op-pay-due" style="color:#059669;font-size:13px">€0.00</strong></span></div>';
                h+='<input type="hidden" id="hm-op-pay-amt" value="0">';

                // Credit section (populated dynamically when credits found)
                h+='<div id="hm-op-credit-section-new"></div>';

                // Payment methods container
                h+='<div id="hm-op-pay-methods">';
                h+='<div class="hm-op-pay-row" style="display:flex;gap:8px;align-items:center;margin-bottom:8px">';
                h+='<select class="hm-op-pay-row-method" style="'+INP+';flex:1"><option value="">— Method —</option><option value="Card">Card</option><option value="Cash">Cash</option><option value="Bank Transfer">Bank Transfer</option><option value="Cheque">Cheque</option></select>';
                h+='<div style="position:relative;width:110px;flex-shrink:0"><span style="position:absolute;left:10px;top:50%;transform:translateY(-50%);font-size:12px;color:var(--hm-text-light,#64748b);pointer-events:none">€</span>';
                h+='<input type="number" class="hm-op-pay-row-amt" step="0.01" min="0" style="'+INP+';padding-left:22px;text-align:right;font-weight:600;font-variant-numeric:tabular-nums"></div>';
                h+='</div></div>';

                h+='<button type="button" id="hm-op-pay-add-method" style="width:100%;margin-bottom:10px;padding:7px;font-size:11px;border:1px dashed var(--hm-border,#e2e8f0);background:transparent;border-radius:4px;cursor:pointer;color:var(--hm-text-light,#64748b);font-family:var(--hm-font);transition:all .15s">+ Add Payment Method</button>';

                // Allocation bar
                h+='<div id="hm-op-pay-alloc-bar" style="margin-bottom:10px">';
                h+='<div style="display:flex;justify-content:space-between;font-size:11px;color:var(--hm-text-light,#64748b);margin-bottom:3px"><span>Allocated</span><span id="hm-op-pay-alloc-text">€0.00 / €0.00</span></div>';
                h+='<div style="height:4px;background:var(--hm-border-light,#f1f5f9);border-radius:2px;overflow:hidden"><div id="hm-op-pay-alloc-fill" style="height:100%;background:#059669;border-radius:2px;width:0%;transition:width .2s"></div></div>';
                h+='<div id="hm-op-pay-alloc-warn" style="display:none;font-size:11px;color:#dc2626;margin-top:3px">⚠ Allocated amount exceeds total</div>';
                h+='</div>';

                h+='<div id="hm-op-pay-err" style="color:#ef4444;font-size:12px;margin-bottom:8px"></div>';

                h+='<div style="display:flex;gap:8px">';
                h+='<button id="hm-op-pay-cancel" style="flex:0 0 auto;padding:10px 16px;font-size:12px;font-weight:600;border-radius:6px;border:1px solid var(--hm-border,#e2e8f0);background:#fff;color:var(--hm-text,#334155);cursor:pointer;font-family:var(--hm-font-btn)">Cancel</button>';
                h+='<button id="hm-op-pay-confirm" style="flex:1;padding:10px;font-size:13px;font-weight:700;border-radius:6px;border:none;background:#059669;color:#fff;cursor:pointer;font-family:var(--hm-font-btn);transition:all .15s">Confirm Payment</button>';
                h+='</div></div>'; // end payment section

                h+='</div>'; // end #hm-op-neworder
                h+='</div>'; // end right panel
                h+='</div>'; // end split
                h+='<style>@keyframes hmOpIn{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}</style>';
                h+='</div>'; // end #hm-op

                $hmMain.append(h);

                // ═══════ STATE ═══════
                var orderItems=[];
                var selectedProduct=null;
                var discountMode='pct';
                var browseMode='range';
                var selectedRangeId=null;
                var pendingCredits=[]; // credits user intends to apply to this new order
                var _cleanupNs='.opback .opexcard .opexhover .opcancelex .opexconfirm .opcat .opbrowse .oprange .opmfr .opstyle .opprod .opadd .oprem .opdisc .opdiscmode .opprsi .opsubmit .oppaybtn .oppaycancel .oppayconfirm .opaddmethod .opremmethod .oppayrowamt .oppayexback .opstockbtn .opstockclose .opstocksearch .opstockpick .opapplycreditnew .oprefundcredit';

                function cleanupEvents(){$(document).off(_cleanupNs);$('#hm-op').remove();$('#hm-op-loading').remove();$hmMain.css({display:'',flexDirection:''});$hmMain.children().show();}

                // ═══════ HELPERS ═══════
                function updateQuickpayState(){
                    var allSvc=orderItems.length>0&&orderItems.every(function(it){return it.type==='service';});
                    if(allSvc){$('#hm-op-submit').hide();$('#hm-op-pay-btn').text('Create Invoice & Pay').css('flex','1');$('#hm-op-prsi-wrap').hide();}
                    else{$('#hm-op-submit').show();$('#hm-op-pay-btn').text('Take Payment').css('flex','1');$('#hm-op-prsi-wrap').show();}
                }

                function renderItems(){
                    if(!orderItems.length){$('#hm-op-items').html('<tr><td colspan="5" style="text-align:center;padding:24px 0;color:var(--hm-text-muted,#94a3b8);font-size:13px;font-style:italic">No items added yet</td></tr>');return;}
                    var t='';
                    orderItems.forEach(function(it,idx){
                        var details=[];
                        if(it.ear)details.push(it.ear);
                        if(it.speaker_size)details.push('Speaker: '+it.speaker_size+(it.speaker_type?' '+it.speaker_type:''));
                        if(it.dome_size)details.push('Dome: '+it.dome_size+(it.dome_type?' '+it.dome_type:''));
                        t+='<tr style="border-bottom:1px solid var(--hm-border-light,#f1f5f9)">';
                        t+='<td style="padding:10px 0;vertical-align:top">';
                        t+='<div style="font-size:13px;font-weight:600;color:var(--hm-navy,#151B33)">'+esc(it.name)+'</div>';
                        if(details.length)t+='<div style="font-size:11px;color:var(--hm-text-light,#64748b);margin-top:2px">'+esc(details.join(' · '))+'</div>';
                        t+='</td>';
                        t+='<td style="text-align:center;padding:10px 8px;font-size:13px;color:var(--hm-text,#334155);vertical-align:top;font-variant-numeric:tabular-nums">'+it.qty+'</td>';
                        t+='<td style="text-align:right;padding:10px 8px;font-size:13px;color:var(--hm-text,#334155);vertical-align:top;font-variant-numeric:tabular-nums">€'+it.unit_price.toFixed(2)+'</td>';
                        t+='<td style="text-align:right;padding:10px 0;font-size:13px;font-weight:600;color:var(--hm-navy,#151B33);vertical-align:top;font-variant-numeric:tabular-nums">€'+(it.unit_price*it.qty).toFixed(2)+'</td>';
                        t+='<td style="padding:10px 0 10px 6px;vertical-align:top"><button class="hm-op-rem" data-idx="'+idx+'" style="border:none;background:none;color:#b91c1c;width:22px;height:22px;cursor:pointer;font-size:14px;font-weight:700;display:flex;align-items:center;justify-content:center;opacity:.5;transition:opacity .15s" onmouseenter="this.style.opacity=1" onmouseleave="this.style.opacity=.5">×</button></td>';
                        t+='</tr>';
                    });
                    $('#hm-op-items').html(t);
                }

                function updateTotals(){
                    var net=0,vat=0,grossSum=0;
                    orderItems.forEach(function(it){var g=it.unit_price*it.qty;grossSum+=g;vat+=it.vat_amount;net+=g-it.vat_amount;});
                    var discVal=parseFloat($('#hm-op-disc').val())||0;
                    var disc=0;
                    if(discountMode==='pct'){disc=discVal>0?Math.round(grossSum*(Math.min(discVal,100)/100)*100)/100:0;}
                    else{disc=Math.min(discVal,grossSum);}
                    var prsi=($('#hm-op-prsi-l').is(':checked')?500:0)+($('#hm-op-prsi-r').is(':checked')?500:0);
                    var total=Math.max(0,grossSum-disc-prsi);
                    $('#hm-op-sub').text('€'+net.toFixed(2));
                    $('#hm-op-vat').text('€'+vat.toFixed(2));
                    $('#hm-op-disc-amt').text('−€'+disc.toFixed(2));
                    if(disc>0){$('#hm-op-disc-row').css('display','flex');}else{$('#hm-op-disc-row').hide();}
                    if(prsi>0){$('#hm-op-prsi-row').css('display','flex');}else{$('#hm-op-prsi-row').hide();}
                    $('#hm-op-prsi-amt').text('−€'+prsi.toFixed(2));
                    $('#hm-op-total').text('€'+total.toFixed(2));
                    $('#hm-op-pay-due').text('€'+total.toFixed(2));
                    $('#hm-op-pay-amt').val(total.toFixed(2));
                }

                function _populateRangeDropdown(){
                    var sel=$('#hm-op-range');sel.find('option:not(:first)').remove();
                    allRanges.forEach(function(r){
                        if(r.is_active==='f'||r.is_active===false||r.is_active===0)return;
                        var pt=parseFloat(r.price_total||0),pe=parseFloat(r.price_ex_prsi||0);
                        sel.append('<option value="'+r.id+'">'+esc(r.range_name)+' — €'+pt.toLocaleString(undefined,{minimumFractionDigits:0})+' (€'+pe.toLocaleString(undefined,{minimumFractionDigits:0})+' after PRSI)</option>');
                    });
                }

                function _populateManufacturerDropdown(){
                    var sel=$('#hm-op-mfr');sel.find('option:not(:first)').remove();
                    var valid={};
                    allProducts.filter(function(p){return p.item_type==='product';}).forEach(function(p){
                        if(selectedRangeId&&parseInt(p.hearmed_range_id)!==selectedRangeId)return;
                        if(p.manufacturer_id&&p.manufacturer_name)valid[p.manufacturer_id]=p.manufacturer_name;
                    });
                    Object.keys(valid).sort(function(x,y){return valid[x].localeCompare(valid[y]);}).forEach(function(id){
                        sel.append('<option value="'+id+'">'+esc(valid[id])+'</option>');
                    });
                }

                // ═══════ EVENT HANDLERS ═══════

                // ── Back button ──
                $(document).off('click.opback').on('click.opback','#hm-op-back',function(){cleanupEvents();});

                // ── Existing order card click ──
                $(document).off('click.opexcard').on('click.opexcard','.hm-op-ex-card',function(){
                    var $c=$(this);
                    var ordId=parseInt($c.attr('data-oid'),10);
                    var ordNum=$c.data('onum');
                    var bal=parseFloat($c.attr('data-bal'))||0;
                    var ordTotal=parseFloat($c.attr('data-total'))||0;
                    var hasPrsiEx=$c.attr('data-prsi')==='1';
                    var patientId=a.patient_id||$('#hm-op-pid').val()||0;
                    // Switch right panel to pay-existing mode
                    $('#hm-op-neworder').hide();
                    $('#hm-op-payex').show();
                    var d='<div style="font-size:16px;font-weight:700;color:var(--hm-navy,#151B33);margin-bottom:4px">'+esc(ordNum)+'</div>';
                    d+='<div style="font-size:13px;color:var(--hm-text-light,#64748b);margin-bottom:8px">Order Total: €'+ordTotal.toFixed(2)+'</div>';
                    if(ordTotal-bal>0.01)d+='<div style="font-size:13px;color:#059669;margin-bottom:4px">Already Paid: €'+(ordTotal-bal).toFixed(2)+'</div>';
                    d+='<div id="hm-op-ex-bal-display" style="font-size:18px;font-weight:700;color:#dc2626">Balance Due: €'+bal.toFixed(2)+'</div>';
                    $('#hm-op-payex-detail').html(d);

                    // Build payment form with credit section placeholder
                    var pf='<div id="hm-op-credit-section"></div>';
                    pf+='<div style="background:#fff;border-radius:10px;border:2px solid #059669;padding:16px">';
                    pf+='<div style="font-size:12px;font-weight:700;color:#059669;text-transform:uppercase;letter-spacing:.5px;margin-bottom:12px">Payment</div>';
                    pf+='<div style="'+LS+'"><label style="'+LB+'">Amount (€)</label>';
                    pf+='<input type="number" id="hm-op-ex-amt" step="0.01" min="0" value="'+bal.toFixed(2)+'" style="'+INP+';font-size:15px;font-weight:600"></div>';
                    pf+='<div style="'+LS+'"><label style="'+LB+'">Payment Method</label>';
                    pf+='<select id="hm-op-ex-method" style="'+INP+'">';
                    pf+='<option value="">— Select —</option><option value="Card">Card</option><option value="Cash">Cash</option>';
                    pf+='<option value="Bank Transfer">Bank Transfer</option><option value="Cheque">Cheque</option></select></div>';
                    pf+='<div id="hm-op-ex-err" style="color:#ef4444;font-size:12px;margin-bottom:8px"></div>';
                    pf+='<button id="hm-op-ex-confirm" data-oid="'+ordId+'" data-onum="'+esc(ordNum)+'" data-bal="'+bal.toFixed(2)+'" data-prsi="'+(hasPrsiEx?'1':'0')+'" style="width:100%;padding:12px;font-size:14px;font-weight:700;border-radius:8px;border:none;background:#059669;color:#fff;cursor:pointer;font-family:var(--hm-font-btn)">Confirm Payment</button>';
                    pf+='</div>';
                    $('#hm-op-payex-form').html(pf);

                    // ── Fetch patient credits ──
                    if(patientId){
                        $.post(HM.ajax_url,{action:'hm_get_patient_credits',nonce:HM.nonce,patient_id:patientId},function(r){
                            if(!r||!r.success)return;
                            var credits=(r.data||[]).filter(function(c){
                                return c.status==='active'&&(parseFloat(c.amount)-parseFloat(c.used_amount))>0.01;
                            });
                            if(!credits.length)return;

                            var totalCredit=0;
                            credits.forEach(function(c){totalCredit+=parseFloat(c.remaining||c.amount)-parseFloat(c.used_amount||0);});

                            var cs='<div style="background:#f0fdf4;border:2px solid #bbf7d0;border-radius:10px;padding:14px 16px;margin-bottom:14px">';
                            cs+='<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px">';
                            cs+='<div style="display:flex;align-items:center;gap:8px">';
                            cs+='<svg width="18" height="18" fill="none" stroke="#059669" stroke-width="2"><rect x="2" y="5" width="14" height="10" rx="2"/><path d="M2 9h14"/></svg>';
                            cs+='<span style="font-size:12px;font-weight:700;color:#059669;text-transform:uppercase;letter-spacing:.5px">Patient Credit Available</span></div>';
                            cs+='<span style="font-size:16px;font-weight:700;color:#059669">€'+totalCredit.toFixed(2)+'</span></div>';

                            credits.forEach(function(c){
                                var rem=parseFloat(c.remaining||0);
                                if(rem<=0)rem=parseFloat(c.amount)-parseFloat(c.used_amount);
                                cs+='<div style="display:flex;align-items:center;justify-content:space-between;padding:6px 0;border-top:1px solid #dcfce7">';
                                cs+='<div style="font-size:12px;color:#334155">'+(c.credit_note_number||'Credit')+'<span style="color:#94a3b8;margin-left:6px">€'+rem.toFixed(2)+' remaining</span></div>';
                                cs+='<button class="hm-op-apply-credit" data-cid="'+c.id+'" data-rem="'+rem.toFixed(2)+'" style="font-size:11px;font-weight:700;padding:4px 12px;border-radius:6px;border:1px solid #059669;background:#fff;color:#059669;cursor:pointer;font-family:var(--hm-font-btn)">Apply</button>';
                                cs+='</div>';
                            });
                            cs+='</div>';
                            $('#hm-op-credit-section').html(cs);

                            // ── Apply credit click ──
                            $(document).off('click.opapplycredit').on('click.opapplycredit','.hm-op-apply-credit',function(){
                                var $btn=$(this);
                                var creditId=parseInt($btn.data('cid'),10);
                                var creditRem=parseFloat($btn.data('rem'))||0;
                                var currentBal=parseFloat($('#hm-op-ex-confirm').attr('data-bal'))||0;
                                var applyAmt=Math.min(creditRem,currentBal);
                                if(applyAmt<=0){self.toast('No balance to apply credit to');return;}

                                $btn.prop('disabled',true).text('Applying…');
                                $.post(HM.ajax_url,{action:'hm_apply_credit_to_invoice',nonce:HM.nonce,
                                    credit_id:creditId,order_id:ordId,amount:applyAmt
                                },function(cr){
                                    if(cr&&cr.success){
                                        var newBal=parseFloat(cr.data.invoice_balance)||0;
                                        // Update balance display
                                        $('#hm-op-ex-bal-display').html('Balance Due: €'+newBal.toFixed(2));
                                        $('#hm-op-ex-amt').val(newBal.toFixed(2));
                                        $('#hm-op-ex-confirm').attr('data-bal',newBal.toFixed(2));
                                        $btn.css({background:'#059669',color:'#fff',border:'none'}).text('Applied €'+applyAmt.toFixed(2)+' ✓');

                                        // Update credit remaining display
                                        var newCreditRem=parseFloat(cr.data.credit_remaining)||0;
                                        $btn.closest('div').find('span:last').text('€'+newCreditRem.toFixed(2)+' remaining');

                                        self.toast('€'+applyAmt.toFixed(2)+' credit applied to '+ordNum);
                                        if(newBal<=0.01){
                                            // Fully paid — auto-close
                                            self.toast(ordNum+' fully paid via credit');
                                            cleanupEvents();self.refresh();
                                        }
                                    } else {
                                        $btn.prop('disabled',false).text('Apply');
                                        self.toast(cr&&cr.data?cr.data:'Failed to apply credit','error');
                                    }
                                });
                            });
                        });
                    }
                });

                // ── Hover on existing order cards ──
                $(document).off('mouseenter.opexhover mouseleave.opexhover').on('mouseenter.opexhover','.hm-op-ex-card',function(){
                    $(this).css({borderColor:'var(--hm-teal,#0BB4C4)',background:'#f0fdfa'});
                }).on('mouseleave.opexhover','.hm-op-ex-card',function(){
                    $(this).css({borderColor:'var(--hm-border,#e2e8f0)',background:'#fff'});
                });

                // ── Back from pay-existing ──
                $(document).off('click.oppayexback').on('click.oppayexback','#hm-op-payex-back',function(){
                    $('#hm-op-payex').hide();$('#hm-op-neworder').show();
                });

                // ── Pay existing confirm ──
                $(document).off('click.opexconfirm').on('click.opexconfirm','#hm-op-ex-confirm',function(){
                    var $btn=$(this);
                    var ordId=parseInt($btn.attr('data-oid'),10);
                    var ordNum=$btn.attr('data-onum');
                    var maxBal=parseFloat($btn.attr('data-bal'))||0;
                    var hasPrsiEx=$btn.attr('data-prsi')==='1';
                    var amt=parseFloat($('#hm-op-ex-amt').val())||0;
                    var method=$('#hm-op-ex-method').val();
                    if(!amt||amt<=0){$('#hm-op-ex-err').text('Enter a valid amount.');return;}
                    if(amt>maxBal){$('#hm-op-ex-err').text('Amount cannot exceed €'+maxBal.toFixed(2));return;}
                    if(!method){$('#hm-op-ex-err').text('Select a payment method.');return;}
                    $btn.prop('disabled',true).text('Processing...');
                    post('record_order_payment',{
                        order_id:ordId,order_number:ordNum,amount:amt,payment_method:method,split_payments_json:'[]'
                    }).then(function(r){
                        if(r.success){
                            // Check PRSI form for existing orders
                            if(hasPrsiEx){
                                _checkPrsiForm(ordId,ordNum,a.dispenser_id||0,a.patient_name||'',function(){
                                    cleanupEvents();self.toast('Payment of €'+amt.toFixed(2)+' recorded on '+ordNum);self.refresh();
                                });
                            } else {
                                cleanupEvents();self.toast('Payment of €'+amt.toFixed(2)+' recorded on '+ordNum);self.refresh();
                            }
                        }
                        else{
                            $btn.prop('disabled',false).text('Confirm Payment');
                            if(r.data&&r.data.code==='serials_required'){
                                var serialItems=(r.data&&Array.isArray(r.data.serial_items))?r.data.serial_items:[];
                                if(!serialItems.length){$('#hm-op-ex-err').text('Serial numbers required.');return;}
                                _showSerialModal(serialItems,ordId,function(){
                                    // Retry payment after serials saved
                                    $btn.prop('disabled',true).text('Processing...');
                                    post('record_order_payment',{
                                        order_id:ordId,order_number:ordNum,amount:amt,payment_method:method,split_payments_json:'[]'
                                    }).then(function(r2){
                                        if(r2.success){
                                            if(hasPrsiEx){
                                                _checkPrsiForm(ordId,ordNum,a.dispenser_id||0,a.patient_name||'',function(){
                                                    cleanupEvents();self.toast('Payment of €'+amt.toFixed(2)+' recorded on '+ordNum);self.refresh();
                                                });
                                            } else {
                                                cleanupEvents();self.toast('Payment of €'+amt.toFixed(2)+' recorded on '+ordNum);self.refresh();
                                            }
                                        }
                                        else{$btn.prop('disabled',false).text('Confirm Payment');$('#hm-op-ex-err').css('color','#ef4444').text(r2.data&&r2.data.message?r2.data.message:'Payment failed after serials.');}
                                    }).fail(function(){$btn.prop('disabled',false).text('Confirm Payment');$('#hm-op-ex-err').text('Network error');});
                                });
                                return;
                            }
                            $('#hm-op-ex-err').text(r.data&&r.data.message?r.data.message:'Failed');
                        }
                    }).fail(function(){$btn.prop('disabled',false).text('Confirm Payment');$('#hm-op-ex-err').text('Network error');});
                });

                // ── Category change ──
                $(document).off('change.opcat').on('change.opcat','#hm-op-cat',function(){
                    var cat=$(this).val();
                    $('#hm-op-mfr-wrap,#hm-op-style-wrap,#hm-op-prod-wrap,#hm-op-tech-wrap,#hm-op-ear-wrap,#hm-op-add-wrap,#hm-op-charger-wrap,#hm-op-speaker-wrap,#hm-op-dome-wrap').hide();
                    $('#hm-op-mfr,#hm-op-style,#hm-op-prod').val('');
                    $('#hm-op-charger').prop('checked',false);
                    $('#hm-op-speaker-size,#hm-op-speaker-type,#hm-op-dome-size,#hm-op-dome-type').val('');
                    selectedProduct=null;selectedRangeId=null;
                    if(!cat){$('#hm-op-browse-toggle,#hm-op-range-wrap').hide();return;}
                    if(cat==='product'){
                        $('#hm-op-browse-toggle').show();
                        if(browseMode==='range'){$('#hm-op-range-wrap').show();_populateRangeDropdown();}
                        else{$('#hm-op-range-wrap').hide();}
                        _populateManufacturerDropdown();$('#hm-op-mfr-wrap').show();
                    } else {
                        $('#hm-op-browse-toggle,#hm-op-range-wrap').hide();selectedRangeId=null;
                        if(cat==='service'){
                            var opts='<option value="">— Select Service —</option>';
                            allSvcs.forEach(function(s){
                                opts+='<option value="svc-'+s.id+'" data-name="'+esc(s.service_name)+'" data-price="'+(s.default_price||0)+'" data-vat="13.5">'+esc(s.service_name)+' — €'+(parseFloat(s.default_price)||0).toFixed(2)+'</option>';
                            });
                            $('#hm-op-prod').html(opts);$('#hm-op-prod-wrap').show();
                        } else {
                            var filtered=allProducts.filter(function(p){return p.item_type===cat;});
                            var opts2='<option value="">— Select Product —</option>';
                            filtered.forEach(function(p){
                                var nm=(p.manufacturer_name?p.manufacturer_name+' ':'')+(p.product_name||'');
                                opts2+='<option value="'+p.id+'" data-name="'+esc(nm)+'" data-price="'+(p.retail_price||0)+'" data-vat="'+(p.vat_category==='Consumables'?23:0)+'">'+esc(nm)+' — €'+(parseFloat(p.retail_price)||0).toFixed(2)+'</option>';
                            });
                            $('#hm-op-prod').html(opts2);$('#hm-op-prod-wrap').show();
                        }
                    }
                });

                // ── Browse mode toggle ──
                $(document).off('click.opbrowse').on('click.opbrowse','.hm-op-browse-btn',function(){
                    browseMode=$(this).data('mode');selectedRangeId=null;
                    $('.hm-op-browse-btn').css({background:'#fff',color:'var(--hm-navy,#151B33)'});
                    $(this).css({background:'var(--hm-navy,#151B33)',color:'#fff'});
                    if(browseMode==='range'){$('#hm-op-range-wrap').show();_populateRangeDropdown();}
                    else{$('#hm-op-range-wrap').hide();$('#hm-op-range').val('');}
                    _populateManufacturerDropdown();
                    $('#hm-op-style-wrap,#hm-op-prod-wrap,#hm-op-tech-wrap,#hm-op-ear-wrap,#hm-op-add-wrap').hide();
                    $('#hm-op-style,#hm-op-prod').val('');selectedProduct=null;
                });

                // ── Range change ──
                $(document).off('change.oprange').on('change.oprange','#hm-op-range',function(){
                    selectedRangeId=$(this).val()?parseInt($(this).val()):null;
                    _populateManufacturerDropdown();
                    $('#hm-op-style-wrap,#hm-op-prod-wrap,#hm-op-tech-wrap,#hm-op-ear-wrap,#hm-op-add-wrap').hide();
                    $('#hm-op-style,#hm-op-prod').val('');selectedProduct=null;
                });

                // ── Manufacturer change ──
                $(document).off('change.opmfr').on('change.opmfr','#hm-op-mfr',function(){
                    var mfr=$(this).val();
                    $('#hm-op-style-wrap,#hm-op-prod-wrap,#hm-op-tech-wrap,#hm-op-ear-wrap,#hm-op-add-wrap').hide();
                    $('#hm-op-style,#hm-op-prod').val('');selectedProduct=null;
                    if(!mfr)return;
                    var styles={};
                    allProducts.filter(function(p){
                        if(p.item_type!=='product')return false;
                        if(String(p.manufacturer_id)!==String(mfr))return false;
                        if(selectedRangeId&&parseInt(p.hearmed_range_id)!==selectedRangeId)return false;
                        return true;
                    }).forEach(function(p){if(p.style)styles[p.style]=1;});
                    var opts='<option value="">— Select Style —</option>';
                    Object.keys(styles).sort().forEach(function(s){opts+='<option value="'+esc(s)+'">'+esc(s)+'</option>';});
                    $('#hm-op-style').html(opts);$('#hm-op-style-wrap').show();
                });

                // ── Style change ──
                $(document).off('change.opstyle').on('change.opstyle','#hm-op-style',function(){
                    var style=$(this).val(),mfr=$('#hm-op-mfr').val();
                    $('#hm-op-prod-wrap,#hm-op-tech-wrap,#hm-op-ear-wrap,#hm-op-add-wrap').hide();
                    $('#hm-op-prod').val('');selectedProduct=null;
                    if(!style)return;
                    var filtered=allProducts.filter(function(p){
                        if(p.item_type!=='product')return false;
                        if(String(p.manufacturer_id)!==String(mfr))return false;
                        if(p.style!==style)return false;
                        if(selectedRangeId&&parseInt(p.hearmed_range_id)!==selectedRangeId)return false;
                        return true;
                    });
                    var opts='<option value="">— Select Product —</option>';
                    filtered.forEach(function(p){
                        var nm=(p.manufacturer_name?p.manufacturer_name+' ':'')+(p.product_name||'')+' '+(p.style||'')+' ('+(p.tech_level||'—')+')';
                        opts+='<option value="'+p.id+'" data-name="'+esc(nm)+'" data-price="'+(p.retail_price||0)+'" data-tech="'+esc(p.tech_level||'')+'" data-vat="0">'+esc(nm)+' — €'+(parseFloat(p.retail_price)||0).toFixed(2)+'</option>';
                    });
                    $('#hm-op-prod').html(opts);$('#hm-op-prod-wrap').show();
                });

                // ── Product select ──
                $(document).off('change.opprod').on('change.opprod','#hm-op-prod',function(){
                    var val=$(this).val();
                    if(!val){$('#hm-op-tech-wrap,#hm-op-ear-wrap,#hm-op-add-wrap').hide();selectedProduct=null;return;}
                    var opt=$(this).find('option:selected');
                    var cat=$('#hm-op-cat').val();
                    selectedProduct={id:val,type:val.toString().indexOf('svc-')===0?'service':(cat||'product'),
                        name:opt.data('name')||opt.text(),unit_price:parseFloat(opt.data('price'))||0,
                        vat_rate:parseFloat(opt.data('vat'))||0,tech_level:opt.data('tech')||'',ear:''};
                    if(cat==='product'&&selectedProduct.tech_level){$('#hm-op-tech').text(selectedProduct.tech_level);$('#hm-op-tech-wrap').show();}
                    else{$('#hm-op-tech-wrap').hide();}
                    if(cat==='product'){$('#hm-op-ear-wrap,#hm-op-charger-wrap,#hm-op-speaker-wrap,#hm-op-dome-wrap').show();}
                    else{$('#hm-op-ear-wrap,#hm-op-charger-wrap,#hm-op-speaker-wrap,#hm-op-dome-wrap').hide();}
                    $('#hm-op-add-wrap').show();
                });

                // ── Add to order ──
                $(document).off('click.opadd').on('click.opadd','#hm-op-add-item',function(){
                    if(!selectedProduct){$('#hm-op-err').text('Select a product first.');return;}
                    var cat=$('#hm-op-cat').val();
                    var ear=$('#hm-op-ear').val()||'';
                    if(cat==='product'&&!ear){$('#hm-op-err').text('Please select which ear.');return;}
                    $('#hm-op-err').text('');
                    var item=$.extend({},selectedProduct);
                    item.ear=ear;item.qty=cat==='product'&&ear==='Binaural'?2:1;
                    if(cat==='product'){
                        item.speaker_size=$('#hm-op-speaker-size').val()||'';
                        item.speaker_type=$('#hm-op-speaker-type').val()||'';
                        item.dome_size=$('#hm-op-dome-size').val()||'';
                        item.dome_type=$('#hm-op-dome-type').val()||'';
                    }
                    var gross=item.unit_price*item.qty;
                    item.vat_amount=item.vat_rate>0?parseFloat((gross-(gross/(1+item.vat_rate/100))).toFixed(2)):0;
                    item.line_total=parseFloat(gross.toFixed(2));
                    item._uid=Date.now()+Math.random();
                    orderItems.push(item);
                    if(cat==='product'&&$('#hm-op-charger').is(':checked')){
                        var cp=allProducts.filter(function(p){return p.item_type==='accessory'&&/charger/i.test(p.product_name);})[0];
                        var ci={id:cp?cp.id:'charger',type:'accessory',
                            name:cp?((cp.manufacturer_name?cp.manufacturer_name+' ':'')+cp.product_name):'Charger',
                            unit_price:cp?parseFloat(cp.retail_price)||0:0,vat_rate:0,tech_level:'',
                            ear:ear==='Binaural'?'Both':ear,qty:1,_uid:Date.now()+Math.random()};
                        var cg=ci.unit_price*ci.qty;
                        ci.vat_amount=ci.vat_rate>0?parseFloat((cg-(cg/(1+ci.vat_rate/100))).toFixed(2)):0;
                        ci.line_total=parseFloat(cg.toFixed(2));
                        orderItems.push(ci);
                    }
                    renderItems();updateTotals();updateQuickpayState();
                    selectedProduct=null;
                    $('#hm-op-cat').val('').trigger('change.opcat');
                });

                // ── Remove item ──
                $(document).off('click.oprem').on('click.oprem','.hm-op-rem',function(){
                    orderItems.splice(parseInt($(this).data('idx')),1);
                    renderItems();updateTotals();updateQuickpayState();
                });

                // ═══ PICK FROM STOCK ═══
                function _renderStockItems(items){
                    var html='';
                    items.forEach(function(si){
                        html+='<div class="hm-stock-item" data-sid="'+si._ID+'" data-serial="'+esc(si.serial_number||'')+'" data-model="'+esc(si.model_name||'')+'" data-mfr="'+(si.manufacturer_id||'')+'" data-mfr-name="'+esc(si.manufacturer_name||'')+'" data-style="'+esc(si.style||'')+'" data-tech="'+esc(si.technology_level||'')+'" style="display:flex;align-items:center;justify-content:space-between;padding:10px 12px;border:1px solid var(--hm-border,#e2e8f0);border-radius:8px;margin-bottom:6px;background:#fff;transition:border-color .15s" onmouseenter="this.style.borderColor=\'var(--hm-teal,#0BB4C4)\'" onmouseleave="this.style.borderColor=\'var(--hm-border,#e2e8f0)\'">';
                        html+='<div style="flex:1;min-width:0">';
                        html+='<div style="font-size:13px;font-weight:600;color:var(--hm-navy,#151B33)">'+esc(si.manufacturer_name||'')+' '+esc(si.model_name||'')+'</div>';
                        html+='<div style="font-size:11px;color:var(--hm-text-light,#64748b);margin-top:2px">'+esc(si.style||'—')+' · '+esc(si.technology_level||'—')+' · SN: '+esc(si.serial_number||'N/A')+'</div>';
                        html+='</div>';
                        html+='<button type="button" class="hm-op-stock-add" style="flex-shrink:0;font-size:11px;font-weight:700;padding:5px 14px;border-radius:6px;border:1.5px solid var(--hm-teal,#0BB4C4);background:#f0fdfa;color:var(--hm-teal,#0BB4C4);cursor:pointer;font-family:var(--hm-font-btn);white-space:nowrap;transition:all .15s">Add</button>';
                        html+='</div>';
                    });
                    if(!html)html='<div style="text-align:center;padding:20px;color:var(--hm-text-muted,#94a3b8);font-size:12px">No available stock found</div>';
                    $('#hm-op-stock-list').html(html);
                }

                $(document).off('click.opstockbtn').on('click.opstockbtn','#hm-op-stock-btn',function(){
                    var $panel=$('#hm-op-stock-panel');
                    if($panel.is(':visible')){$panel.slideUp(200);return;}
                    $panel.slideDown(200);
                    var $list=$('#hm-op-stock-list');
                    $list.html('<div style="text-align:center;padding:20px;color:var(--hm-text-muted,#94a3b8);font-size:12px">Loading stock…</div>');
                    $.post(HM.ajax_url,{action:'hm_stock_hearing_aids',nonce:HM.nonce,status:'Available',clinic_id:a.clinic_id||''},function(r){
                        if(!r||!r.success){$list.html('<div style="text-align:center;padding:20px;color:#ef4444;font-size:12px">Failed to load stock</div>');return;}
                        var items=r.data||[];
                        _renderStockItems(items);
                    });
                });

                $(document).off('click.opstockclose').on('click.opstockclose','#hm-op-stock-close',function(){
                    $('#hm-op-stock-panel').slideUp(200);
                });

                $(document).off('input.opstocksearch').on('input.opstocksearch','#hm-op-stock-search',function(){
                    var q=$(this).val().toLowerCase();
                    $('#hm-op-stock-list .hm-stock-item').each(function(){
                        $(this).toggle($(this).text().toLowerCase().indexOf(q)>-1);
                    });
                });

                $(document).off('click.opstockpick').on('click.opstockpick','.hm-op-stock-add',function(e){
                    e.stopPropagation();
                    var $card=$(this).closest('.hm-stock-item');
                    var stockId=$card.data('sid');
                    var serial=String($card.data('serial')||'');
                    var modelName=String($card.data('model')||'');
                    var mfrId=String($card.data('mfr')||'');
                    var mfrName=String($card.data('mfr-name')||'');
                    var style=String($card.data('style')||'');
                    var tech=String($card.data('tech')||'');

                    // Match to product in catalog for pricing
                    var matched=allProducts.filter(function(p){
                        if(p.item_type!=='product')return false;
                        if(mfrId&&String(p.manufacturer_id)!==mfrId)return false;
                        var pn=(p.product_name||'').toLowerCase(),mn=modelName.toLowerCase();
                        return pn===mn||pn.indexOf(mn)>-1||mn.indexOf(pn)>-1;
                    });
                    if(matched.length>1&&style){var bs=matched.filter(function(p){return(p.style||'').toLowerCase()===style.toLowerCase();});if(bs.length)matched=bs;}
                    if(matched.length>1&&tech){var bt=matched.filter(function(p){return(p.tech_level||'').toLowerCase()===tech.toLowerCase();});if(bt.length)matched=bt;}
                    var product=matched[0]||null;
                    var price=product?parseFloat(product.retail_price)||0:0;
                    var name=mfrName+(mfrName?' ':'')+modelName+(style?' '+style:'')+(tech?' ('+tech+')':'');

                    if(!product){self.toast('No matching product found in catalog — price set to €0','warning');}

                    var item={id:product?product.id:'stock-'+stockId,type:'product',name:name,
                        unit_price:price,vat_rate:product?parseFloat(product.vat_rate)||0:0,
                        tech_level:tech,ear:'',qty:1,stock_id:stockId,serial_number:serial,from_stock:true,
                        _uid:Date.now()+Math.random()};
                    var gross=item.unit_price*item.qty;
                    item.vat_amount=item.vat_rate>0?parseFloat((gross-(gross/(1+item.vat_rate/100))).toFixed(2)):0;
                    item.line_total=parseFloat(gross.toFixed(2));
                    orderItems.push(item);
                    renderItems();updateTotals();updateQuickpayState();

                    // Visual feedback
                    var $btn=$(this);
                    $btn.css({background:'var(--hm-teal,#0BB4C4)',color:'#fff',borderColor:'var(--hm-teal,#0BB4C4)'}).text('Added ✓');
                    setTimeout(function(){$btn.css({background:'#f0fdfa',color:'var(--hm-teal,#0BB4C4)',borderColor:'var(--hm-teal,#0BB4C4)'}).text('Add');},1500);
                    self.toast(esc(name)+' added from stock');
                });

                // ── PRSI changes ──
                $(document).off('change.opprsi').on('change.opprsi','#hm-op-prsi-l,#hm-op-prsi-r',updateTotals);

                // ── Discount input ──
                $(document).off('input.opdisc').on('input.opdisc','#hm-op-disc',function(){updateTotals();});
                $(document).off('click.opdiscmode').on('click.opdiscmode','.hm-op-disc-mode',function(){
                    var mode=$(this).data('mode');discountMode=mode;
                    $('.hm-op-disc-mode').css({background:'#fff',color:'var(--hm-text,#334155)'});
                    $(this).css({background:'var(--hm-navy,#151B33)',color:'#fff'});
                    if(mode==='pct'){$('#hm-op-disc-unit').text('%');$('#hm-op-disc').attr({max:100,step:1});}
                    else{var sub=0;orderItems.forEach(function(it){sub+=it.unit_price*it.qty;});$('#hm-op-disc-unit').text('€');$('#hm-op-disc').attr({max:Math.ceil(sub)||10000,step:10});}
                    $('#hm-op-disc').val(0);updateTotals();
                });

                // ── Submit Order ──
                $(document).off('click.opsubmit').on('click.opsubmit','#hm-op-submit',function(){
                    if(!orderItems.length){$('#hm-op-err').text('Please add at least one item.');return;}
                    var $btn=$(this);$btn.prop('disabled',true).text('Submitting...');
                    var discVal=parseFloat($('#hm-op-disc').val())||0;
                    post('create_outcome_order',{
                        patient_id:$('#hm-op-pid').val(),appointment_id:$('#hm-op-aid').val(),
                        items_json:JSON.stringify(orderItems),notes:$('#hm-op-notes').val()||'',
                        prsi_left:$('#hm-op-prsi-l').is(':checked')?1:0,prsi_right:$('#hm-op-prsi-r').is(':checked')?1:0,
                        discount_pct:discountMode==='pct'?discVal:0,discount_euro:discountMode==='eur'?discVal:0,payment_method:''
                    }).then(function(r){
                        if(r.success){cleanupEvents();self.toast('Order '+r.data.order_number+' submitted for approval');self.refresh();}
                        else{$btn.prop('disabled',false).text('Submit Order');$('#hm-op-err').text(r.data&&r.data.message?r.data.message:'Failed to create order');}
                    }).fail(function(){$btn.prop('disabled',false).text('Submit Order');$('#hm-op-err').text('Network error');});
                });

                // ── Take Payment → show payment section ──
                $(document).off('click.oppaybtn').on('click.oppaybtn','#hm-op-pay-btn',function(){
                    if(!orderItems.length){$('#hm-op-err').text('Please add at least one item.');return;}
                    $('#hm-op-err').text('');
                    pendingCredits=[];
                    var total=parseFloat($('#hm-op-total').text().replace('€',''))||0;
                    $('#hm-op-pay-due').text('€'+total.toFixed(2));
                    $('#hm-op-pay-amt').val(total.toFixed(2));
                    $('#hm-op-pay-methods .hm-op-pay-row').first().find('.hm-op-pay-row-amt').val(total.toFixed(2));
                    _updatePayAlloc();
                    $('#hm-op-paysec').slideDown(200);
                    $('#hm-op-actions').hide();
                    $('#hm-op-credit-section-new').empty();
                    setTimeout(function(){$('#hm-op-paysec')[0].scrollIntoView({behavior:'smooth',block:'center'});},250);

                    // ── Fetch patient credits for new order ──
                    var patientId=$('#hm-op-pid').val()||0;
                    if(patientId){
                        $.post(HM.ajax_url,{action:'hm_get_patient_credits',nonce:HM.nonce,patient_id:patientId},function(r){
                            if(!r||!r.success)return;
                            var credits=(r.data||[]).filter(function(c){
                                return c.status==='active'&&(parseFloat(c.amount)-parseFloat(c.used_amount))>0.01;
                            });
                            if(!credits.length)return;

                            var totalCredit=0;
                            credits.forEach(function(c){
                                var rem=parseFloat(c.remaining||0);
                                if(rem<=0)rem=parseFloat(c.amount)-parseFloat(c.used_amount);
                                c._rem=rem;totalCredit+=rem;
                            });

                            var cs='<div style="background:#f0fdf4;border:2px solid #bbf7d0;border-radius:10px;padding:14px 16px;margin-bottom:14px">';
                            cs+='<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px">';
                            cs+='<div style="display:flex;align-items:center;gap:8px">';
                            cs+='<svg width="18" height="18" fill="none" stroke="#059669" stroke-width="2"><rect x="2" y="5" width="14" height="10" rx="2"/><path d="M2 9h14"/></svg>';
                            cs+='<span style="font-size:12px;font-weight:700;color:#059669;text-transform:uppercase;letter-spacing:.5px">Patient Credit Available</span></div>';
                            cs+='<span style="font-size:16px;font-weight:700;color:#059669">€'+totalCredit.toFixed(2)+'</span></div>';

                            credits.forEach(function(c){
                                cs+='<div style="display:flex;align-items:center;justify-content:space-between;padding:6px 0;border-top:1px solid #dcfce7">';
                                cs+='<div style="font-size:12px;color:#334155">'+(c.credit_note_number||'Credit')+'<span style="color:#94a3b8;margin-left:6px">€'+c._rem.toFixed(2)+' remaining</span></div>';
                                cs+='<button class="hm-op-apply-credit-new" data-cid="'+c.id+'" data-rem="'+c._rem.toFixed(2)+'" style="font-size:11px;font-weight:700;padding:4px 12px;border-radius:6px;border:1px solid #059669;background:#fff;color:#059669;cursor:pointer;font-family:var(--hm-font-btn)">Apply</button>';
                                cs+='</div>';
                            });
                            cs+='</div>';
                            $('#hm-op-credit-section-new').html(cs);
                        });
                    }
                });

                // ── Apply credit to new order (pending — applied after order creation) ──
                $(document).off('click.opapplycreditnew').on('click.opapplycreditnew','.hm-op-apply-credit-new',function(){
                    var $btn=$(this);
                    var creditId=parseInt($btn.data('cid'),10);
                    var creditRem=parseFloat($btn.data('rem'))||0;
                    var currentTotal=parseFloat($('#hm-op-pay-amt').val())||0;
                    var applyAmt=Math.min(creditRem,currentTotal);
                    if(applyAmt<=0){self.toast('No balance to apply credit to');return;}

                    // Track pending credit
                    pendingCredits.push({credit_id:creditId,amount:applyAmt});

                    // Reduce payment amount
                    var newTotal=Math.round((currentTotal-applyAmt)*100)/100;
                    $('#hm-op-pay-amt').val(newTotal.toFixed(2));
                    $('#hm-op-pay-due').html('€'+newTotal.toFixed(2)+' <span style="font-size:11px;color:#059669;font-weight:400">(€'+applyAmt.toFixed(2)+' credit applied)</span>');
                    $('#hm-op-pay-methods .hm-op-pay-row').first().find('.hm-op-pay-row-amt').val(newTotal.toFixed(2));
                    _updatePayAlloc();

                    // Update button state
                    $btn.css({background:'#059669',color:'#fff',border:'none'}).text('Applied €'+applyAmt.toFixed(2)+' ✓').prop('disabled',true);

                    // If credit fully covers order (or more) — show refund prompt or auto-confirm
                    if(newTotal<=0.01){
                        var surplus=Math.round((applyAmt-currentTotal)*100)/100;
                        // Hide payment methods — not needed
                        $('#hm-op-pay-methods,#hm-op-pay-add-method,#hm-op-pay-alloc-bar').hide();
                        if(surplus>0.01){
                            // Credit exceeds total → offer refund
                            var rp='<div id="hm-op-credit-surplus" style="background:#fffbeb;border:2px solid #fde68a;border-radius:10px;padding:14px 16px;margin-bottom:14px">';
                            rp+='<div style="font-size:12px;font-weight:700;color:#92400e;text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px">Remaining Credit: €'+surplus.toFixed(2)+'</div>';
                            rp+='<div style="font-size:13px;color:#334155;margin-bottom:10px">This order is fully covered by credit. The patient has €'+surplus.toFixed(2)+' remaining. Create a credit note and refund?</div>';
                            rp+='<div style="display:flex;gap:8px">';
                            rp+='<button id="hm-op-refund-no" class="hm-op-refund-credit" data-refund="0" style="flex:1;padding:8px;font-size:12px;font-weight:600;border-radius:6px;border:1px solid var(--hm-border,#e2e8f0);background:#fff;color:var(--hm-text,#334155);cursor:pointer;font-family:var(--hm-font-btn)">No, Keep as Credit</button>';
                            rp+='<button id="hm-op-refund-yes" class="hm-op-refund-credit" data-refund="1" style="flex:1;padding:8px;font-size:12px;font-weight:700;border-radius:6px;border:none;background:#f59e0b;color:#fff;cursor:pointer;font-family:var(--hm-font-btn)">Yes, Refund €'+surplus.toFixed(2)+'</button>';
                            rp+='</div></div>';
                            $('#hm-op-pay-err').before(rp);
                        }
                        // Change confirm button text
                        $('#hm-op-pay-confirm').text('Confirm — Paid via Credit');
                    }

                    self.toast('€'+applyAmt.toFixed(2)+' credit will be applied');
                });

                // ── Handle refund choice ──
                $(document).off('click.oprefundcredit').on('click.oprefundcredit','.hm-op-refund-credit',function(){
                    var doRefund=$(this).data('refund')==1;
                    pendingCredits[pendingCredits.length-1].refund=doRefund;
                    $(this).closest('#hm-op-credit-surplus').html(
                        doRefund
                        ? '<div style="font-size:12px;font-weight:600;color:#059669">✓ Refund will be issued after order is created</div>'
                        : '<div style="font-size:12px;font-weight:600;color:#334155">✓ Remaining credit will stay on patient account</div>'
                    );
                });

                // ── Cancel payment ──
                $(document).off('click.oppaycancel').on('click.oppaycancel','#hm-op-pay-cancel',function(){
                    $('#hm-op-paysec').slideUp(200);$('#hm-op-actions').show();
                    pendingCredits=[];
                    $('#hm-op-credit-section-new').empty();
                    $('#hm-op-credit-surplus').remove();
                    $('#hm-op-pay-methods,#hm-op-pay-add-method,#hm-op-pay-alloc-bar').show();
                    $('#hm-op-pay-confirm').text('Confirm Payment');
                    // Reset to single row
                    var $methods=$('#hm-op-pay-methods');
                    $methods.find('.hm-op-pay-row').slice(1).remove();
                    $methods.find('.hm-op-pay-row').first().find('.hm-op-pay-rem-row').remove();
                    $methods.find('.hm-op-pay-row-method').val('');
                    $methods.find('.hm-op-pay-row-amt').val('');
                });

                // ── Allocation recalc helper ──
                function _updatePayAlloc(){
                    var total=parseFloat($('#hm-op-pay-amt').val())||0;
                    var alloc=0;
                    $('#hm-op-pay-methods .hm-op-pay-row-amt').each(function(){alloc+=parseFloat($(this).val())||0;});
                    alloc=Math.round(alloc*100)/100;
                    var pct=total>0?Math.min(100,Math.round(alloc/total*100)):0;
                    $('#hm-op-pay-alloc-text').text('€'+alloc.toFixed(2)+' / €'+total.toFixed(2));
                    $('#hm-op-pay-alloc-fill').css('width',pct+'%').css('background',alloc>total+0.01?'#dc2626':'#059669');
                    if(alloc>total+0.01){$('#hm-op-pay-alloc-warn').show();}else{$('#hm-op-pay-alloc-warn').hide();}
                }

                // ── Auto-calc: when user types in any amount, recalc remaining on last row ──
                $(document).off('input.oppayrowamt').on('input.oppayrowamt','.hm-op-pay-row-amt',function(){
                    var $rows=$('#hm-op-pay-methods .hm-op-pay-row');
                    if($rows.length<2){_updatePayAlloc();return;}
                    var $this=$(this);
                    var $lastRow=$rows.last();
                    var $lastAmt=$lastRow.find('.hm-op-pay-row-amt');
                    // Only auto-fill if user is NOT typing in the last row
                    if(!$this.is($lastAmt)){
                        var total=parseFloat($('#hm-op-pay-amt').val())||0;
                        var allocated=0;
                        $rows.each(function(){
                            var $r=$(this);
                            if(!$r.is($lastRow)){allocated+=parseFloat($r.find('.hm-op-pay-row-amt').val())||0;}
                        });
                        var rem=Math.round((total-allocated)*100)/100;
                        $lastAmt.val(rem>0?rem.toFixed(2):'0.00');
                    }
                    // Cap: never allow any single field to exceed total
                    var total2=parseFloat($('#hm-op-pay-amt').val())||0;
                    var v=parseFloat($this.val())||0;
                    if(v>total2){$this.val(total2.toFixed(2));}
                    _updatePayAlloc();
                });

                // ── Add payment method row ──
                $(document).off('click.opaddmethod').on('click.opaddmethod','#hm-op-pay-add-method',function(){
                    var total=parseFloat($('#hm-op-pay-amt').val())||0;
                    var allocated=0;
                    $('#hm-op-pay-methods .hm-op-pay-row-amt').each(function(){allocated+=parseFloat($(this).val())||0;});
                    var rem=Math.round((total-allocated)*100)/100;
                    var row='<div class="hm-op-pay-row" style="display:flex;gap:8px;align-items:center;margin-bottom:8px">';
                    row+='<select class="hm-op-pay-row-method" style="'+INP+';flex:1"><option value="">— Method —</option><option value="Card">Card</option><option value="Cash">Cash</option><option value="Bank Transfer">Bank Transfer</option><option value="Cheque">Cheque</option></select>';
                    row+='<div style="position:relative;width:110px;flex-shrink:0"><span style="position:absolute;left:10px;top:50%;transform:translateY(-50%);font-size:12px;color:var(--hm-text-light,#64748b);pointer-events:none">€</span>';
                    row+='<input type="number" class="hm-op-pay-row-amt" step="0.01" min="0" value="'+(rem>0?rem.toFixed(2):'0.00')+'" style="'+INP+';padding-left:22px;text-align:right;font-weight:600;font-variant-numeric:tabular-nums"></div>';
                    row+='<button type="button" class="hm-op-pay-rem-row" style="border:none;background:none;color:#b91c1c;font-size:16px;cursor:pointer;padding:4px;opacity:.5;transition:opacity .15s" onmouseenter="this.style.opacity=1" onmouseleave="this.style.opacity=.5">×</button>';
                    row+='</div>';
                    $('#hm-op-pay-methods').append(row);
                    // Add remove button to first row if not present
                    if(!$('#hm-op-pay-methods .hm-op-pay-row').first().find('.hm-op-pay-rem-row').length){
                        $('#hm-op-pay-methods .hm-op-pay-row').first().append('<button type="button" class="hm-op-pay-rem-row" style="border:none;background:none;color:#b91c1c;font-size:16px;cursor:pointer;padding:4px;opacity:.5;transition:opacity .15s" onmouseenter="this.style.opacity=1" onmouseleave="this.style.opacity=.5">×</button>');
                    }
                    _updatePayAlloc();
                });

                // ── Remove payment method row ──
                $(document).off('click.opremmethod').on('click.opremmethod','.hm-op-pay-rem-row',function(){
                    var $rows=$('#hm-op-pay-methods .hm-op-pay-row');
                    if($rows.length<=1)return;
                    $(this).closest('.hm-op-pay-row').remove();
                    $rows=$('#hm-op-pay-methods .hm-op-pay-row');
                    if($rows.length===1){$rows.find('.hm-op-pay-rem-row').remove();}
                    // Recalc: fill last row with remainder
                    var total=parseFloat($('#hm-op-pay-amt').val())||0;
                    if($rows.length===1){$rows.find('.hm-op-pay-row-amt').val(total.toFixed(2));}
                    _updatePayAlloc();
                });

                // ═══ SERIAL NUMBER MODAL ═══
                function _showSerialModal(serialItems,orderId,onDone){
                    var recDef=new Date().toISOString().slice(0,10);
                    var ov='<div id="hm-op-serial-overlay" style="position:fixed;inset:0;z-index:100000;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,.45);animation:hmOpIn .2s">';
                    ov+='<div style="background:#fff;border-radius:10px;box-shadow:0 10px 40px rgba(0,0,0,.2);width:440px;max-width:92vw;max-height:85vh;overflow-y:auto;font-family:var(--hm-font)">';
                    ov+='<div style="padding:20px 24px 16px;border-bottom:1px solid var(--hm-border,#e2e8f0)">';
                    ov+='<div style="font-size:16px;font-weight:700;color:var(--hm-navy,#151B33)">Serial Numbers Required</div>';
                    ov+='<div style="font-size:12px;color:var(--hm-text-light,#64748b);margin-top:4px">Enter the receipt date and serial numbers for each device before payment can proceed.</div>';
                    ov+='</div>';
                    ov+='<div style="padding:20px 24px">';
                    ov+='<div style="'+LS+'"><label style="'+LB+'">Date of Receipt</label>';
                    ov+='<input type="date" id="hm-op-serial-date" value="'+recDef+'" style="'+INP+'"></div>';
                    serialItems.forEach(function(it,idx){
                        var lbl=String(it.item_description||('Product #'+String(it.product_id||'')));
                        var ear=String(it.ear_side||'Unknown').toLowerCase();
                        ov+='<div style="background:var(--hm-bg-alt,#f8fafc);border:1px solid var(--hm-border,#e2e8f0);border-radius:8px;padding:12px 14px;margin-bottom:10px">';
                        ov+='<div style="font-size:13px;font-weight:600;color:var(--hm-navy,#151B33);margin-bottom:8px">'+esc(lbl)+' <span style="font-size:11px;color:var(--hm-text-light,#64748b);font-weight:400">('+esc(it.ear_side||'Unknown')+')</span></div>';
                        if(ear==='left'||ear==='binaural'||ear==='unknown'){
                            ov+='<div style="'+LS+'"><label style="'+LB+'">Serial — Left ear</label>';
                            ov+='<input type="text" class="hm-op-serial-input" data-idx="'+idx+'" data-ear="left" data-pid="'+(it.product_id||0)+'" placeholder="Enter serial number" style="'+INP+'"></div>';
                        }
                        if(ear==='right'||ear==='binaural'){
                            ov+='<div style="'+LS+'"><label style="'+LB+'">Serial — Right ear</label>';
                            ov+='<input type="text" class="hm-op-serial-input" data-idx="'+idx+'" data-ear="right" data-pid="'+(it.product_id||0)+'" placeholder="Enter serial number" style="'+INP+'"></div>';
                        }
                        if(ear==='unknown'){
                            // Already handled above as left
                        }
                        ov+='</div>';
                    });
                    ov+='<div id="hm-op-serial-err" style="color:#ef4444;font-size:12px;margin-bottom:8px"></div>';
                    ov+='<div style="display:flex;gap:8px">';
                    ov+='<button id="hm-op-serial-cancel" style="flex:0 0 auto;padding:10px 16px;font-size:12px;font-weight:600;border-radius:6px;border:1px solid var(--hm-border,#e2e8f0);background:#fff;color:var(--hm-text,#334155);cursor:pointer;font-family:var(--hm-font-btn)">Cancel</button>';
                    ov+='<button id="hm-op-serial-save" style="flex:1;padding:10px;font-size:13px;font-weight:700;border-radius:6px;border:none;background:var(--hm-teal,#0BB4C4);color:#fff;cursor:pointer;font-family:var(--hm-font-btn)">Save Serials & Continue</button>';
                    ov+='</div></div></div></div>';
                    $('body').append(ov);

                    $('#hm-op-serial-cancel').off('click').on('click',function(){$('#hm-op-serial-overlay').remove();});
                    $('#hm-op-serial-save').off('click').on('click',function(){
                        var recDate=$('#hm-op-serial-date').val();
                        if(!recDate){$('#hm-op-serial-err').text('Please enter the receipt date.');return;}
                        var entries=[];
                        $('.hm-op-serial-input').each(function(){
                            var v=$(this).val().trim();
                            if(v)entries.push({product_id:parseInt($(this).data('pid'),10)||0,ear:$(this).data('ear'),serial:v});
                        });
                        if(!entries.length){$('#hm-op-serial-err').text('Please enter at least one serial number.');return;}
                        var $btn=$('#hm-op-serial-save');$btn.prop('disabled',true).text('Saving...');
                        post('save_order_serials_from_payment',{order_id:orderId,received_date:recDate,serials_json:JSON.stringify(entries)}).then(function(sv){
                            if(sv.success){$('#hm-op-serial-overlay').remove();onDone();}
                            else{$btn.prop('disabled',false).text('Save Serials & Continue');$('#hm-op-serial-err').text(sv.data&&sv.data.message?sv.data.message:'Failed to save serials.');}
                        }).fail(function(){$btn.prop('disabled',false).text('Save Serials & Continue');$('#hm-op-serial-err').text('Network error saving serials.');});
                    });
                }

                // ═══ PRSI FORM CHECK ═══
                function _checkPrsiForm(orderId,orderNum,dispenserId,patientName,onDone){
                    var hasPrsi=$('#hm-op-prsi-l').is(':checked')||$('#hm-op-prsi-r').is(':checked');
                    if(!hasPrsi){onDone();return;}
                    var ov='<div id="hm-op-prsi-overlay" style="position:fixed;inset:0;z-index:100000;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,.45);animation:hmOpIn .2s">';
                    ov+='<div style="background:#fff;border-radius:10px;box-shadow:0 10px 40px rgba(0,0,0,.2);width:400px;max-width:92vw;font-family:var(--hm-font)">';
                    ov+='<div style="padding:20px 24px 16px;border-bottom:1px solid var(--hm-border,#e2e8f0)">';
                    ov+='<div style="font-size:16px;font-weight:700;color:var(--hm-navy,#151B33)">PRSI Grant Form</div>';
                    ov+='<div style="font-size:12px;color:var(--hm-text-light,#64748b);margin-top:4px">This order includes a PRSI grant deduction.</div>';
                    ov+='</div>';
                    ov+='<div style="padding:20px 24px">';
                    ov+='<div style="font-size:13px;color:var(--hm-text,#334155);margin-bottom:16px">Have you received the signed PRSI form from the patient?</div>';
                    ov+='<div style="display:flex;gap:10px">';
                    ov+='<button id="hm-op-prsi-no" style="flex:1;padding:12px;font-size:13px;font-weight:700;border-radius:6px;border:1px solid #dc2626;background:#fff;color:#dc2626;cursor:pointer;font-family:var(--hm-font-btn)">No, Not Yet</button>';
                    ov+='<button id="hm-op-prsi-yes" style="flex:1;padding:12px;font-size:13px;font-weight:700;border-radius:6px;border:none;background:#059669;color:#fff;cursor:pointer;font-family:var(--hm-font-btn)">Yes, Received</button>';
                    ov+='</div></div></div></div>';
                    $('body').append(ov);

                    $('#hm-op-prsi-yes').off('click').on('click',function(){$('#hm-op-prsi-overlay').remove();onDone();});
                    $('#hm-op-prsi-no').off('click').on('click',function(){
                        var $noBtn=$(this);$noBtn.prop('disabled',true).text('Sending reminder...');
                        post('create_prsi_form_reminder',{
                            order_id:orderId,order_number:orderNum,
                            dispenser_id:dispenserId||HM.staff_id||0,
                            patient_name:patientName||''
                        }).then(function(nr){
                            $('#hm-op-prsi-overlay').remove();
                            if(nr.success){self.toast('PRSI form reminder sent to dispenser\'s notifications');}
                            onDone();
                        }).fail(function(){$('#hm-op-prsi-overlay').remove();onDone();});
                    });
                }

                // ── Confirm Payment ──
                $(document).off('click.oppayconfirm').on('click.oppayconfirm','#hm-op-pay-confirm',function(){
                    if(!orderItems.length){$('#hm-op-pay-err').text('Add items first.');return;}
                    var totalDue=parseFloat($('#hm-op-pay-amt').val())||0;
                    var hasPendingCredits=pendingCredits.length>0;
                    var creditOnly=hasPendingCredits&&totalDue<=0.01;

                    // Validate payment methods (skip if fully paid by credit)
                    var $rows=$('#hm-op-pay-methods .hm-op-pay-row');
                    var splitPayload=[];
                    var allocated=0;
                    var firstMethod='';
                    var valid=true;

                    if(!creditOnly){
                        $rows.each(function(i){
                            var m=$(this).find('.hm-op-pay-row-method').val();
                            var ea=parseFloat($(this).find('.hm-op-pay-row-amt').val())||0;
                            if(!m){$('#hm-op-pay-err').text('Select a payment method for row '+(i+1)+'.');valid=false;return false;}
                            if(ea<=0){$('#hm-op-pay-err').text('Enter a valid amount for row '+(i+1)+'.');valid=false;return false;}
                            if(i===0)firstMethod=m;
                            splitPayload.push({method:m,amount:ea});
                            allocated+=ea;
                        });
                        if(!valid)return;
                        allocated=Math.round(allocated*100)/100;
                        if(allocated>totalDue+0.01){$('#hm-op-pay-err').text('Total payments (€'+allocated.toFixed(2)+') exceed order total (€'+totalDue.toFixed(2)+').');return;}
                        if(Math.abs(allocated-totalDue)>0.01){$('#hm-op-pay-err').text('Payments must equal the total due (€'+totalDue.toFixed(2)+').');return;}
                    }

                    var $btn=$(this);$btn.prop('disabled',true).text('Processing...');
                    var discVal=parseFloat($('#hm-op-disc').val())||0;
                    var sendSplitJson=$rows.length>1&&!creditOnly?JSON.stringify(splitPayload):'[]';

                    // Helper: apply pending credits sequentially, then callback
                    function _applyPendingCredits(orderId,orderNum,idx,onAllDone){
                        if(idx>=pendingCredits.length){onAllDone();return;}
                        var pc=pendingCredits[idx];
                        $.post(HM.ajax_url,{action:'hm_apply_credit_to_invoice',nonce:HM.nonce,
                            credit_id:pc.credit_id,order_id:orderId,amount:pc.amount
                        },function(cr){
                            if(cr&&cr.success){
                                self.toast('€'+pc.amount.toFixed(2)+' credit applied to '+orderNum);
                            }
                            _applyPendingCredits(orderId,orderNum,idx+1,onAllDone);
                        }).fail(function(){
                            self.toast('Failed to apply credit #'+pc.credit_id,'error');
                            _applyPendingCredits(orderId,orderNum,idx+1,onAllDone);
                        });
                    }

                    // Step 1: Create order
                    post('create_outcome_order',{
                        patient_id:$('#hm-op-pid').val(),appointment_id:$('#hm-op-aid').val(),
                        items_json:JSON.stringify(orderItems),notes:$('#hm-op-notes').val()||'',
                        prsi_left:$('#hm-op-prsi-l').is(':checked')?1:0,prsi_right:$('#hm-op-prsi-r').is(':checked')?1:0,
                        discount_pct:discountMode==='pct'?discVal:0,discount_euro:discountMode==='eur'?discVal:0,payment_method:''
                    }).then(function(r){
                        if(!r.success){$btn.prop('disabled',false).text('Confirm Payment');$('#hm-op-pay-err').text(r.data&&r.data.message?r.data.message:'Failed to create order');return;}
                        var newOrderId=r.data.order_id;
                        var newOrderNum=r.data.order_number;

                        // Step 2: Apply pending credits (if any)
                        _applyPendingCredits(newOrderId,newOrderNum,0,function(){

                            // Step 3: If fully paid by credit, done — no payment to record
                            if(creditOnly){
                                cleanupEvents();self.toast(newOrderNum+' fully paid via credit');self.refresh();
                                return;
                            }

                            // Step 4: Record remaining payment
                            post('record_order_payment',{
                                order_id:newOrderId,order_number:newOrderNum,amount:totalDue,payment_method:firstMethod,
                                split_payments_json:sendSplitJson
                            }).then(function(pr){
                                if(pr.success){
                                    cleanupEvents();self.toast('Payment of €'+totalDue.toFixed(2)+' recorded on '+newOrderNum);self.refresh();
                                }
                                else{
                                    $btn.prop('disabled',false).text('Confirm Payment');
                                    if(pr.data&&pr.data.code==='serials_required'){
                                        var serialItems=(pr.data&&Array.isArray(pr.data.serial_items))?pr.data.serial_items:[];
                                        if(!serialItems.length){$('#hm-op-pay-err').text('Serial numbers required but no items returned.');return;}
                                        _showSerialModal(serialItems,newOrderId,function(){
                                            $btn.prop('disabled',true).text('Processing...');
                                            post('record_order_payment',{
                                                order_id:newOrderId,order_number:newOrderNum,amount:totalDue,payment_method:firstMethod,
                                                split_payments_json:sendSplitJson
                                            }).then(function(pr2){
                                                if(pr2.success){cleanupEvents();self.toast('Payment of €'+totalDue.toFixed(2)+' recorded on '+newOrderNum);self.refresh();}
                                                else{$btn.prop('disabled',false).text('Confirm Payment');$('#hm-op-pay-err').css('color','#ef4444').text(pr2.data&&pr2.data.message?pr2.data.message:'Payment failed after serials.');}
                                            }).fail(function(){$btn.prop('disabled',false).text('Confirm Payment');$('#hm-op-pay-err').text('Network error');});
                                        });
                                        return;
                                    }
                                    var msg=(pr.data&&pr.data.message)?pr.data.message:'Failed';
                                    if(pr.data&&pr.data.debug){
                                        var d=pr.data.debug;
                                        var recent=Array.isArray(d.recent_orders)?d.recent_orders.map(function(o){return(o.order_number||'')+' (#'+(o.id||'')+')';}).join(', '):'';
                                        msg+=' [debug id='+String(d.received_order_id||0)+', number='+String(d.received_order_number||'')+', lookup='+String(d.used_lookup||'')+(recent?(', recent='+recent):'')+']';
                                    }
                                    $('#hm-op-pay-err').css('color','#ef4444').text(msg);
                                }
                            }).fail(function(){$btn.prop('disabled',false).text('Confirm Payment');$('#hm-op-pay-err').text('Network error');});
                        });
                    }).fail(function(){$btn.prop('disabled',false).text('Confirm Payment');$('#hm-op-pay-err').text('Network error');});
                });

            } // end build()

            // Fetch existing orders then build
            post('get_patient_pipeline_orders',{patient_id:a.patient_id,appointment_id:(a._ID||a.id||0)}).then(function(oR){
                var pl=oR.data||[];
                build(oR.success?(Array.isArray(pl)?pl:(pl.orders||[])):[]);
            }).fail(function(){
                build([]);
            });

        }).fail(function(){
            $('#hm-op-loading').remove();
            $hmMain.css({display:'',flexDirection:''});
            $hmMain.children().show();
            self.toast('Failed to load product data');
        });
    },


    // ── Outcome → Follow-up Booking ──
    _openFollowUpBooking:function(a,serviceId,onComplete){
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
                if(typeof onComplete==='function')onComplete();
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
                    referral_source_id:a.referral_source_id||'',
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
                            if(typeof onComplete==='function')onComplete();
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
        if(d.off==='1'){
            if(!confirm('⚠ No staff is scheduled for this slot.\n\nAre you sure you want to create an appointment here?'))return;
        }
        if(this.calViewMode==='clinic'){
            // Clinic view: open appointment modal with clinic pre-selected, no dispenser
            this.openNewApptModal(d.date,d.time,0,parseInt(d.clinic));
        } else {
            this.openNewApptModal(d.date,d.time,parseInt(d.disp));
        }
    },

    openNewApptModal:function(date,time,dispId,clinicId){
        var self=this;
        // Ensure services, clinics & referral sources are loaded before building dropdown HTML
        var ready=$.Deferred();
        if(!self.services.length||!self.clinics.length||!self.referralSources||!self.referralSources.length){
            $.when(
                self.services.length?null:self.loadServices(),
                self.clinics.length?null:self.loadClinics(),
                self.dispensers.length?null:self.loadDispensers(),
                self.loadReferralSources()
            ).always(function(){ready.resolve();});
        } else { ready.resolve(); }
        ready.then(function(){ self._buildApptModal(date,time,dispId,clinicId); });
    },
    _buildApptModal:function(date,time,dispId,clinicId){
        var self=this;
        var svcOpts=self.services.length?self.services.map(function(s){return'<option value="'+s.id+'">'+esc(s.name)+'</option>';}).join(''):'<option value="">No types available</option>';
        var cliOpts=self.clinics.length?self.clinics.map(function(c){return'<option value="'+c.id+'"'+(clinicId&&parseInt(c.id)===clinicId?' selected':'')+'>'+esc(c.name)+'</option>';}).join(''):'<option value="">No clinics</option>';
        var refOpts='<option value="">— Select Referral Type —</option>';
        if(self.referralSources&&self.referralSources.length){refOpts+=self.referralSources.map(function(rs){return'<option value="'+rs.id+'">'+esc(rs.name)+'</option>';}).join('');}
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
                '<div class="hm-fld"><label>Referral Type <span style="color:#ef4444">*</span></label><select class="hm-inp" id="hmn-referral-source" required>'+refOpts+'</select></div>'+
                '<div style="margin-top:12px;padding:12px 14px;background:#f0f9ff;border:1px solid #bae6fd;border-radius:8px">'+
                    '<div style="font-size:11px;font-weight:700;color:#0369a1;text-transform:uppercase;letter-spacing:.3px;margin-bottom:8px">Confirmation &amp; Reminders</div>'+
                    '<div class="hm-fld" style="margin:0"><label style="font-size:12px">SMS Reminder</label><select class="hm-inp" id="hmn-sms-reminder">'+
                        '<option value="0">No reminder</option><option value="24">24 hours before</option><option value="48">48 hours before</option><option value="72">72 hours before</option>'+
                    '</select></div>'+
                    '<div id="hmn-sms-gdpr-warn" style="display:none;margin-top:6px;padding:6px 10px;background:#fef2f2;border:1px solid #fecaca;border-radius:6px;font-size:11px;color:#991b1b">'+
                        '⚠ This patient has not consented to SMS. Reminder will not be sent.'+
                    '</div>'+
                '</div>'+
                '<div class="hm-fld" style="margin-top:12px"><label>Notes</label><textarea class="hm-inp" id="hmn-notes" placeholder="Optional notes..."></textarea></div>'+
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
                        h+='<div class="hm-pt-item" data-id="'+p.id+'" data-refsource="'+esc(p.referral_source_name||'')+'" data-refsourceid="'+(p.referral_source_id||0)+'" data-smsok="'+(p.marketing_sms?'1':'0')+'"><span>'+esc(lbl)+'</span><span class="hm-pt-newtab">Select</span></div>';
                    });
                    $('#hmn-ptresults').html(h).addClass('open');
                }).fail(function(){ $('#hmn-ptresults').removeClass('open').empty(); });
            },300);
        });
        $(document).on('click.newmodal','.hm-pt-item[data-id]',function(){
            var id=$(this).data('id'),name=$(this).find('span:first').text();
            var ref=$(this).data('refsource')||'';
            var refId=$(this).data('refsourceid')||0;
            var smsOk=$(this).data('smsok');
            $('#hmn-ptsearch').val(name);$('#hmn-patientid').val(id);$('#hmn-ptresults').removeClass('open');
            $('#hmn-refsource').val(ref);
            // Auto-select referral source if patient has one
            if(refId&&$('#hmn-referral-source option[value="'+refId+'"]').length){
                $('#hmn-referral-source').val(refId);
            }
            // Show GDPR warning if patient hasn't consented to SMS
            if(smsOk==='1'||smsOk===1){$('#hmn-sms-gdpr-warn').hide();}
            else{$('#hmn-sms-gdpr-warn').show();}
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
            var refSrcId=$('#hmn-referral-source').val();
            if(!refSrcId){alert('Please select a Referral Type.');$('#hmn-referral-source').focus();return;}
            var apptData={
                patient_id:pid,service_id:$('#hmn-service').val(),
                clinic_id:$('#hmn-clinic').val(),dispenser_id:$('#hmn-disp').val(),
                status:$('#hmn-status').val(),appointment_date:$('#hmn-date').val(),
                start_time:$('#hmn-time').val(),duration:$('#hmn-duration').val(),
                location_type:$('#hmn-loc').val(),
                referring_source:$('#hmn-refsource').val(),
                referral_source_id:refSrcId,
                sms_reminder_hours:$('#hmn-sms-reminder').val(),
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
            post('create_patient_calendar',{
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

// ═══ EXPOSE ORDER PAGE GLOBALLY ═══
window.HM_openOrderPage=function(opts){
    window._hmOrderOpts=opts;
    var ctx={
        toast:function(msg){
            var $t=$('<div class="hm-toast">'+esc(msg)+'</div>');
            $('body').append($t);
            setTimeout(function(){$t.addClass('show');},10);
            setTimeout(function(){$t.removeClass('show');setTimeout(function(){$t.remove();},300);},3000);
        },
        refresh:function(){
            window._hmOrderOpts=null;
            if(typeof opts.onDone==='function')opts.onDone();
        }
    };
    Cal._openOrderPage.call(ctx,{
        patient_id:opts.patient_id,
        patient_name:opts.patient_name||'',
        _ID:opts.appointment_id||0,
        service_colour:opts.service_colour||'',
        service_name:opts.service_name||'',
        clinic_id:opts.clinic_id||'',
        dispenser_id:opts.dispenser_id||0
    },opts.outcome||{name:'',color:'#3B82F6'});
};

// ═══ BOOT ═══
$(function(){if($('#hm-app').length)App.init();});

})(jQuery);

