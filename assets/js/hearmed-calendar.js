/**
 * HearMed Calendar v3.1
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
};

// Status pill colours
var STATUS_MAP={
    Confirmed:{bg:'#eff6ff',color:'#1e40af',border:'#bfdbfe'},
    Arrived:{bg:'#ecfdf5',color:'#065f46',border:'#a7f3d0'},
    'In Progress':{bg:'#fff7ed',color:'#9a3412',border:'#fed7aa'},
    Completed:{bg:'#f9fafb',color:'#6b7280',border:'#e5e7eb'},
    'No Show':{bg:'#fef2f2',color:'#991b1b',border:'#fecaca'},
    Late:{bg:'#fffbeb',color:'#92400e',border:'#fde68a'},
    Pending:{bg:'#f5f3ff',color:'#5b21b6',border:'#ddd6fe'},
    Cancelled:{bg:'#fef2f2',color:'#991b1b',border:'#fecaca'},
};

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
    dispensers:[],services:[],clinics:[],appts:[],holidays:[],blockouts:[],
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
            colorSource:s.color_source||'appointment_type',
            // Card content toggles
            showApptType:bv(s.show_appointment_type,true),
            showTime:bv(s.show_time,true),
            showClinic:bv(s.show_clinic,false),
            showDispIni:bv(s.show_dispenser_initials,true),
            showStatusBadge:bv(s.show_status_badge,true),
            // Card colours
            apptBg:s.appt_bg_color||'#0BB4C4',
            apptFont:s.appt_font_color||'#ffffff',
            apptBadge:s.appt_badge_color||'#3b82f6',
            apptBadgeFont:s.appt_badge_font_color||'#ffffff',
            apptMeta:s.appt_meta_color||'#38bdf8',
            // Calendar theme
            indicatorColor:s.indicator_color||'#00d59b',
            todayHighlight:s.today_highlight_color||'#e6f7f9',
            gridLineColor:s.grid_line_color||'#e2e8f0',
            calBg:s.cal_bg_color||'#ffffff',
            workingDays:(s.working_days||'1,2,3,4,5').split(',').map(function(x){return parseInt(x.trim());}),
        };
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
        $(document).on('click',function(){$('.hm-ms-drop').removeClass('open');$('#hm-plusMenu').removeClass('open');});
        $(document).on('click','#hm-plusBtn',function(e){e.stopPropagation();$('#hm-plusMenu').toggleClass('open');$('.hm-ms-drop').removeClass('open');});
        $(document).on('click','.hm-plus-item',function(){$('#hm-plusMenu').removeClass('open');self.onPlusAction($(this).data('act'));});

        // Popover close
        $(document).on('click',function(e){if(!$(e.target).closest('.hm-pop,.hm-appt').length)$('#hm-pop').removeClass('open');});
        $(document).on('click','.hm-pop-x',function(){$('#hm-pop').removeClass('open');});
        $(document).on('click','.hm-pop-edit',function(){self.editPop();});

        // Slot double-click
        $(document).on('dblclick','.hm-slot',function(){self.onSlot(this);});

        // Popover status actions
        $(document).on('click','.hm-pop-status',function(){
            var status=$(this).data('status'),a=self._popAppt;
            if(!a)return;
            post('update_appointment',{appointment_id:a._ID,status:status}).then(function(r){
                if(r.success){$('#hm-pop').removeClass('open');self.refresh();}
            });
        });

        // Resize
        var rt;$(window).on('resize',function(){clearTimeout(rt);rt=setTimeout(function(){self.refresh();},150);});
        $(document).on('keydown',function(e){if(e.key==='Escape'){$('#hm-pop').removeClass('open');$('#hm-tooltip').hide();}});
    },

    // ── Data loading ──
    loadData:function(){
        var self=this;
        this.loadClinics().then(function(){return self.loadDispensers();}).then(function(){return self.loadServices();}).then(function(){self.refresh();});
    },
    loadClinics:function(){
        return post('get_clinics').then(function(r){
            if(!r.success)return;
            Cal.clinics=r.data;
            Cal.renderMultiSelect();
        });
    },
    loadDispensers:function(){
        return post('get_dispensers',{clinic:0,date:fmt(this.date)}).then(function(r){
            if(!r.success)return;
            Cal.dispensers=r.data;
            Cal.renderMultiSelect();
        });
    },
    loadServices:function(){
        return post('get_services').then(function(r){
            if(!r.success)return;
            Cal.services=r.data;Cal.svcMap={};
            r.data.forEach(function(s){Cal.svcMap[s.id]=s;});
        });
    },
    loadAppts:function(){
        var dates=this.visDates();
        return post('get_appointments',{start:fmt(dates[0]),end:fmt(dates[dates.length-1]),clinic:0})
            .then(function(r){if(r.success)Cal.appts=r.data;});
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
    refresh:function(){var self=this;this.loadAppts().then(function(){self.renderGrid();self.renderAppts();self.renderNow();});},
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
        // Sort by clinic for grouped display
        d=d.slice().sort(function(a,b){return parseInt(a.clinic_id||a.clinicId||0)-parseInt(b.clinic_id||b.clinicId||0);});
        return d;
    },
    visClinics:function(){
        var disps=this.visDisps();
        var clinicIds=[];
        disps.forEach(function(d){var cid=parseInt(d.clinic_id||d.clinicId);if(clinicIds.indexOf(cid)===-1)clinicIds.push(cid);});
        return this.clinics.filter(function(c){return clinicIds.indexOf(c.id)>-1;});
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

        // Build clinic grouping info
        var visClinics=Cal.visClinics();
        var clinicDisps={};
        visClinics.forEach(function(c){clinicDisps[c.id]=[];});
        disps.forEach(function(d){var cid=parseInt(d.clinic_id||d.clinicId);if(clinicDisps[cid])clinicDisps[cid].push(d);});
        var activeClinics=visClinics.filter(function(c){return clinicDisps[c.id].length>0;});
        var multiClinic=activeClinics.length>1;

        // Build list of sections: multi-clinic = one section per clinic, single = one section with all disps
        var sections=[];
        if(multiClinic){
            activeClinics.forEach(function(c){sections.push({clinic:c,disps:clinicDisps[c.id]});});
        } else {
            sections.push({clinic:activeClinics[0]||null,disps:disps});
        }

        var wrapHtml='';
        sections.forEach(function(sec){
            var sDisps=sec.disps;
            var colW=Math.max(80,Math.min(140,Math.floor(900/sDisps.length)));
            var tc=sDisps.length*dates.length;

            // Clinic section header (only when multi-clinic)
            if(multiClinic&&sec.clinic){
                wrapHtml+='<div class="hm-clinic-section-hd" style="border-left:4px solid '+(sec.clinic.color||'#94a3b8')+'">'+esc(sec.clinic.name)+'</div>';
            }

            wrapHtml+='<div class="hm-grid" style="grid-template-columns:44px repeat('+tc+',minmax('+colW+'px,1fr));--hm-cal-bg:'+(cfg.calBg||'#ffffff')+';--hm-cal-grid:'+(cfg.gridLineColor||'#e2e8f0')+';--hm-cal-today:'+(cfg.todayHighlight||'#e6f7f9')+'">';

            // Day headers
            wrapHtml+='<div class="hm-time-corner"></div>';
            dates.forEach(function(d){
                var td=isToday(d);
                wrapHtml+='<div class="hm-day-hd'+(td?' today':'')+'" style="grid-column:span '+sDisps.length+(td?';background:'+cfg.todayHighlight:'')+'">';
                wrapHtml+='<span class="hm-day-lbl">'+DAYS[d.getDay()]+'</span> <span class="hm-day-num">'+d.getDate()+'</span> <span class="hm-day-lbl">'+MO[d.getMonth()]+'</span>';
                if(!multiClinic&&sec.clinic){
                    wrapHtml+='<div class="hm-clinic-row"><div class="hm-clinic-hd" style="flex:1;border-bottom:3px solid '+(sec.clinic.color||'#94a3b8')+'">'+esc(sec.clinic.name)+'</div></div>';
                }
                wrapHtml+='<div class="hm-prov-row">';
                sDisps.forEach(function(p){
                    var lbl=Cal.cfg.displayFull?esc(p.name):esc(p.initials);
                    wrapHtml+='<div class="hm-prov-cell"><div class="hm-prov-ini">'+lbl+'</div></div>';
                });
                wrapHtml+='</div></div>';
            });

            // Time slots
            for(var s=0;s<cfg.totalSlots;s++){
                var tm=cfg.startH*60+s*cfg.slotMin;
                var hr=Math.floor(tm/60),mn=tm%60;
                var isHr=mn===0;
                wrapHtml+='<div class="hm-time-cell'+(isHr?' hr':'')+'">'+(isHr?pad(hr)+':00':'')+'</div>';
                dates.forEach(function(d,di){
                    sDisps.forEach(function(p,pi){
                        var cls='hm-slot'+(isHr?' hr':'')+(pi===sDisps.length-1?' dl':'');
                        wrapHtml+='<div class="'+cls+'" data-date="'+fmt(d)+'" data-time="'+pad(hr)+':'+pad(mn)+'" data-disp="'+p.id+'" data-day="'+di+'" data-slot="'+s+'" style="height:'+slotH+'px"></div>';
                    });
                });
            }
            wrapHtml+='</div>'; // close .hm-grid
        });

        gw.innerHTML=wrapHtml;
    },

    // ── APPOINTMENTS ──
    renderAppts:function(){
        $('.hm-appt').remove();
        var dates=this.visDates(),disps=this.visDisps(),cfg=this.cfg,slotH=cfg.slotHpx;
        if(!disps.length)return;

        var self=this;
        this.appts.forEach(function(a){
            // Filter by multi-select
            if(Cal.selDisps.length&&Cal.selDisps.indexOf(parseInt(a.dispenser_id))===-1)return;
            if(Cal.selClinics.length&&Cal.selClinics.indexOf(parseInt(a.clinic_id))===-1)return;
            if(cfg.hideCancelled&&a.status==='Cancelled')return;

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
            var dur=parseInt(a.duration)||30;
            var h=Math.max(slotH*0.7-2,(dur/cfg.slotMin)*slotH*0.7-2);

            var $t=$('.hm-slot[data-day="'+di+'"][data-slot="'+si+'"][data-disp="'+a.dispenser_id+'"]');
            if(!$t.length)return;

            var col=a.service_colour||'#3B82F6';
            // Color source: which colour drives the card?
            if(cfg.colorSource==='clinic'){col=a.clinic_colour||col;}
            else if(cfg.colorSource==='dispenser'){
                var dd=Cal.dispensers.find(function(x){return parseInt(x.id)===parseInt(a.dispenser_id);});
                col=(dd&&(dd.staff_color||dd.color))?(dd.staff_color||dd.color):col;
            }else if(cfg.colorSource==='global'){col=cfg.apptBg||'#0BB4C4';}
            // else appointment_type — already set from service_colour

            var stCls=a.status==='Cancelled'?' cancelled':a.status==='No Show'?' noshow':'';
            var tmLbl=cfg.showTimeInline?(a.start_time.substring(0,5)+' '):'';
            var hasOutcome=a.outcome_banner_colour&&a.outcome_name;
            var font=cfg.apptFont||'#fff';

            // Card style
            var cs=cfg.cardStyle||'solid';
            var bgStyle='',borderStyle='',fontColor=font;
            if(cs==='solid'){bgStyle='background:'+col;fontColor=font;}
            else if(cs==='tinted'){
                // 12% opacity tint
                var r=parseInt(col.slice(1,3),16),g2=parseInt(col.slice(3,5),16),b=parseInt(col.slice(5,7),16);
                bgStyle='background:rgba('+r+','+g2+','+b+',0.12);border-left:3.5px solid '+col;
                fontColor=col;
            }
            else if(cs==='outline'){bgStyle='background:'+cfg.calBg+';border:1.5px solid '+col+';border-left:3.5px solid '+col;fontColor=col;}
            else if(cs==='minimal'){bgStyle='background:transparent;border-left:3px solid '+col;fontColor='var(--hm-text)';}

            var card='<div class="hm-appt hm-appt--'+cs+stCls+'" data-id="'+a._ID+'" style="'+bgStyle+';height:'+h+'px;top:'+off+'px;color:'+fontColor+'">';
            // Outcome banner
            if(hasOutcome){
                card+='<div class="hm-appt-outcome" style="background:linear-gradient(90deg,'+a.outcome_banner_colour+','+a.outcome_banner_colour+'cc)">'+esc(a.outcome_name)+'</div>';
            }
            card+='<div class="hm-appt-inner">';
            if(cfg.showApptType)card+='<div class="hm-appt-svc" style="color:'+(cs==='solid'?font:col)+'">'+esc(a.service_name)+'</div>';
            card+='<div class="hm-appt-pt" style="color:'+fontColor+'">'+tmLbl+esc(a.patient_name||'No patient')+'</div>';
            if(cfg.showTime&&h>36&&!cfg.hideEndTime)card+='<div class="hm-appt-tm" style="color:'+(cs==='solid'?(cfg.apptMeta||'#38bdf8'):col)+'">'+a.start_time.substring(0,5)+' – '+(a.end_time||'').substring(0,5)+'</div>';
            else if(cfg.showTime&&h>36)card+='<div class="hm-appt-tm" style="color:'+(cs==='solid'?(cfg.apptMeta||'#38bdf8'):col)+'">'+a.start_time.substring(0,5)+'</div>';
            if(h>50){
                var metaParts=[];
                if(cfg.showApptType)metaParts.push(esc(a.service_name));
                if(cfg.showClinic)metaParts.push(esc(a.clinic_name||''));
                if(metaParts.length)card+='<div class="hm-appt-meta" style="color:'+(cs==='solid'?(cfg.apptMeta||'#38bdf8'):'var(--hm-text-muted)')+'">'+metaParts.join(' · ')+'</div>';
            }
            card+='</div></div>';

            var el=$(card);
            $t.append(el);

            // Click → popover
            el.on('click',function(e){e.stopPropagation();clearTimeout(Cal._hoverTimer);$('#hm-tooltip').hide();Cal.showPop(a,this);});

            // Hover → tooltip after 1 second
            el.on('mouseenter',function(){
                var rect=this.getBoundingClientRect();
                Cal._hoverTimer=setTimeout(function(){Cal.showTooltip(a,rect);},1000);
            });
            el.on('mouseleave',function(){clearTimeout(Cal._hoverTimer);$('#hm-tooltip').hide();});
        });
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
        var hasOutcome=a.outcome_banner_colour&&a.outcome_name;

        var h='<div class="hm-pop-bar" style="background:'+col+'"></div>';
        if(hasOutcome){
            h+='<div class="hm-pop-outcome" style="background:linear-gradient(90deg,'+a.outcome_banner_colour+','+a.outcome_banner_colour+'cc)">'+esc(a.outcome_name)+'</div>';
        }
        h+='<div class="hm-pop-body">';
        h+='<div class="hm-pop-hd"><div><div class="hm-pop-name">'+esc(a.patient_name||'No patient')+'</div><div class="hm-pop-num">'+esc(a.patient_number||'')+'</div></div><button class="hm-pop-x">'+IC.x+'</button></div>';
        h+='<div class="hm-pop-status"><span class="hm-status-pill" style="background:'+st.bg+';color:'+st.color+';border:1px solid '+st.border+'">'+esc(a.status)+'</span></div>';
        h+='<div class="hm-pop-details">';
        h+='<div class="hm-pop-row">'+IC.clock+' <span>'+a.start_time.substring(0,5)+' – '+(a.end_time||'').substring(0,5)+' · '+(a.duration||30)+'min</span></div>';
        h+='<div class="hm-pop-row"><span class="hm-pop-svc-dot" style="background:'+col+'"></span> <span>'+esc(a.service_name)+'</span></div>';
        h+='<div class="hm-pop-row">'+IC.user+' <span>'+esc(disp?disp.name:'—')+' · '+esc(clinic?clinic.name:'')+'</span></div>';
        h+='</div>';
        h+='<div class="hm-pop-actions">';
        h+='<button class="hm-pop-act hm-pop-act--primary hm-pop-edit">Edit</button>';
        h+='<button class="hm-pop-act hm-pop-act--green hm-pop-status" data-status="Arrived">Arrived</button>';
        h+='<button class="hm-pop-act hm-pop-act--red hm-pop-status" data-status="No Show">No Show</button>';
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
        var html='<div class="hm-modal-bg open"><div class="hm-modal hm-modal--md">'+
            '<div class="hm-modal-hd"><h3>Edit Appointment</h3><button class="hm-close hm-edit-close">'+IC.x+'</button></div>'+
            '<div class="hm-modal-body">'+
                '<div class="hm-row"><div class="hm-fld"><label>Date</label><input type="date" class="hm-inp" id="hme-date" value="'+a.appointment_date+'"></div>'+
                '<div class="hm-fld"><label>Start Time</label><input type="time" class="hm-inp" id="hme-time" value="'+(a.start_time||'').substring(0,5)+'"></div></div>'+
                '<div class="hm-row"><div class="hm-fld"><label>Status</label><select class="hm-inp" id="hme-status"><option>Confirmed</option><option>Arrived</option><option>In Progress</option><option>Completed</option><option>Late</option><option>No Show</option><option>Cancelled</option><option>Pending</option></select></div>'+
                '<div class="hm-fld"><label>Location</label><select class="hm-inp" id="hme-loc"><option>Clinic</option><option>Home</option></select></div></div>'+
                '<div class="hm-fld"><label>Notes</label><textarea class="hm-inp" id="hme-notes" rows="3">'+esc(a.notes||'')+'</textarea></div>'+
            '</div>'+
            '<div class="hm-modal-ft"><button class="hm-btn hm-btn--danger hm-edit-del">Delete</button><div class="hm-modal-acts"><button class="hm-btn hm-edit-close">Cancel</button><button class="hm-btn hm-btn--primary hm-edit-save">Save</button></div></div>'+
        '</div></div>';
        $('body').append(html);
        $('#hme-status').val(a.status);
        $('#hme-loc').val(a.location_type||'Clinic');
        $(document).off('click.editclose').on('click.editclose','.hm-edit-close',function(){$('.hm-modal-bg').remove();$(document).off('.editclose .editsave .editdel');});
        $(document).off('click.editsave').on('click.editsave','.hm-edit-save',function(){
            post('update_appointment',{appointment_id:a._ID,appointment_date:$('#hme-date').val(),start_time:$('#hme-time').val(),status:$('#hme-status').val(),location_type:$('#hme-loc').val(),notes:$('#hme-notes').val()})
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

    onSlot:function(el){var d=el.dataset;this.openNewApptModal(d.date,d.time,parseInt(d.disp));},

    openNewApptModal:function(date,time,dispId){
        var self=this;
        var html='<div class="hm-modal-bg open"><div class="hm-modal hm-modal--md">'+
            '<div class="hm-modal-hd"><h3>New Appointment</h3><button class="hm-close hm-new-close">'+IC.x+'</button></div>'+
            '<div class="hm-modal-body">'+
                '<div class="hm-fld"><label>Patient search</label><input class="hm-inp" id="hmn-ptsearch" placeholder="Search by name..." autocomplete="off"><div class="hm-pt-results" id="hmn-ptresults"></div><input type="hidden" id="hmn-patientid" value="0"></div>'+
                '<div class="hm-row"><div class="hm-fld"><label>Appointment Type</label><select class="hm-inp" id="hmn-service">'+self.services.map(function(s){return'<option value="'+s.id+'">'+esc(s.name)+'</option>';}).join('')+'</select></div>'+
                '<div class="hm-fld"><label>Clinic</label><select class="hm-inp" id="hmn-clinic">'+self.clinics.map(function(c){return'<option value="'+c.id+'">'+esc(c.name)+'</option>';}).join('')+'</select></div></div>'+
                '<div class="hm-row"><div class="hm-fld"><label>Assignee</label><select class="hm-inp" id="hmn-disp">'+self.dispensers.map(function(d){return'<option value="'+d.id+'"'+(parseInt(d.id)===dispId?' selected':'')+'>'+esc(d.name)+'</option>';}).join('')+'</select></div>'+
                '<div class="hm-fld"><label>Status</label><select class="hm-inp" id="hmn-status"><option>Confirmed</option><option>Pending</option></select></div></div>'+
                '<div class="hm-row"><div class="hm-fld"><label>Date</label><input type="date" class="hm-inp" id="hmn-date" value="'+date+'"></div>'+
                '<div class="hm-fld"><label>Start Time</label><input type="time" class="hm-inp" id="hmn-time" value="'+time+'"></div></div>'+
                '<div class="hm-fld"><label>Location</label><select class="hm-inp" id="hmn-loc"><option>Clinic</option><option>Home</option></select></div>'+
                '<div class="hm-fld"><label>Notes</label><textarea class="hm-inp" id="hmn-notes" placeholder="Optional notes..."></textarea></div>'+
            '</div>'+
            '<div class="hm-modal-ft"><span></span><div class="hm-modal-acts"><button class="hm-btn hm-new-close">Cancel</button><button class="hm-btn hm-btn--primary hm-new-save">Create Appointment</button></div></div>'+
        '</div></div>';
        $('body').append(html);
        var searchTimer;
        $(document).on('input.newmodal','#hmn-ptsearch',function(){
            var q=$(this).val();clearTimeout(searchTimer);
            if(q.length<2){$('#hmn-ptresults').removeClass('open').empty();return;}
            searchTimer=setTimeout(function(){
                post('search_patients',{query:q}).then(function(r){
                    if(!r.success||!r.data.length){$('#hmn-ptresults').removeClass('open').empty();return;}
                    var h='';r.data.forEach(function(p){h+='<div class="hm-pt-item" data-id="'+p.id+'"><span>'+esc(p.name)+'</span><span class="hm-pt-newtab">Select</span></div>';});
                    $('#hmn-ptresults').html(h).addClass('open');
                });
            },300);
        });
        $(document).on('click.newmodal','.hm-pt-item',function(){
            var id=$(this).data('id'),name=$(this).find('span:first').text();
            $('#hmn-ptsearch').val(name);$('#hmn-patientid').val(id);$('#hmn-ptresults').removeClass('open');
        });
        $(document).off('click.newclose').on('click.newclose','.hm-new-close',function(e){e.stopPropagation();$('.hm-modal-bg').remove();$(document).off('.newmodal .newclose');});
        $(document).off('click.newbg').on('click.newbg','.hm-modal-bg',function(e){if($(e.target).hasClass('hm-modal-bg')){$('.hm-modal-bg').remove();$(document).off('.newmodal .newclose .newbg');}});
        $(document).off('click.newsave').on('click.newsave','.hm-new-save',function(){
            post('create_appointment',{
                patient_id:$('#hmn-patientid').val(),service_id:$('#hmn-service').val(),
                clinic_id:$('#hmn-clinic').val(),dispenser_id:$('#hmn-disp').val(),
                status:$('#hmn-status').val(),appointment_date:$('#hmn-date').val(),
                start_time:$('#hmn-time').val(),location_type:$('#hmn-loc').val(),
                notes:$('#hmn-notes').val()
            }).then(function(r){
                if(r.success){$('.hm-modal-bg').remove();$(document).off('.newmodal .newclose .newbg .newsave');self.refresh();}
                else{alert('Error creating appointment');}
            });
        });
    },

    onPlusAction:function(act){
        if(act==='appointment')this.openNewApptModal(fmt(this.date),pad(this.cfg.startH)+':00',this.dispensers.length?parseInt(this.dispensers[0].id):0);
        else if(act==='patient')alert('Navigate to your patient admin page to add a new patient');
        else if(act==='holiday')window.location.href='/adminconsole/holidays';
    },
};

// ═══════════════════════════════════════
// SETTINGS VIEW (unchanged)
// ═══════════════════════════════════════
var Settings={
    $el:null,data:{},dispensers:[],
    init:function($el){this.$el=$el;this.load();},
    load:function(){
        var self=this;
        post('get_settings').then(function(r){
            self.data=r.success?r.data:{};
            post('get_dispensers').then(function(r2){
                self.dispensers=r2.success?r2.data:[];
                self.render();self.bind();
            });
        });
    },
    render:function(){
        var d=this.data,v=function(k,def){return(d[k]!==undefined&&d[k]!=='')?d[k]:def;};
        var vb=function(k,def){var val=d[k];if(val===true||val==='1'||val==='yes'||val==='t')return true;if(val===false||val==='0'||val==='no'||val==='f'||val===null)return false;return def;};
        var h='<div class="hm-settings">';
        h+='<div class="hm-admin-hd"><div><h2>Calendar Settings</h2><div class="hm-admin-subtitle">Adjust your scheduling, display and appearance preferences.</div></div></div>';
        h+='<div class="hm-settings-two" style="display:grid;grid-template-columns:1fr 380px;gap:16px;margin-top:12px">';

        // ═══ LEFT COLUMN ═══
        h+='<div class="hs-left">';

        // ── Time & View ──
        h+='<div class="hm-card"><div class="hm-card-hd"><span class="hm-card-hd-icon">🕐</span><h3>Time &amp; View</h3></div><div class="hm-card-body">';
        h+=this.row('Start time','<input type="time" class="hm-inp" id="hs-start" value="'+esc(v('start_time','09:00'))+'" style="width:130px">');
        h+=this.row('End time','<input type="time" class="hm-inp" id="hs-end" value="'+esc(v('end_time','18:00'))+'" style="width:130px">');
        h+=this.row('Time interval','<select class="hm-dd" id="hs-interval">'+[15,20,30,45,60].map(function(m){return'<option value="'+m+'"'+(parseInt(v('time_interval_minutes',30))===m?' selected':'')+'>'+m+' minutes</option>';}).join('')+'</select>');
        h+=this.row('Slot height','<select class="hm-dd" id="hs-slotH">'+['compact','regular','large'].map(function(s){return'<option value="'+s+'"'+(v('slot_height','regular')===s?' selected':'')+'>'+s.charAt(0).toUpperCase()+s.slice(1)+'</option>';}).join('')+'</select>');
        h+=this.row('Default timeframe','<select class="hm-dd" id="hs-view">'+['day','week'].map(function(s){return'<option value="'+s+'"'+(v('default_view','week')===s?' selected':'')+'>'+s.charAt(0).toUpperCase()+s.slice(1)+'</option>';}).join('')+'</select>');
        h+='</div></div>';

        // ── Card Appearance ──
        h+='<div class="hm-card"><div class="hm-card-hd"><span class="hm-card-hd-icon">🎨</span><h3>Card Appearance</h3></div><div class="hm-card-body">';
        h+=this.row('Card style','<select class="hm-dd" id="hs-cardStyle">'+['solid','tinted','outline','minimal'].map(function(s){return'<option value="'+s+'"'+(v('card_style','solid')===s?' selected':'')+'>'+s.charAt(0).toUpperCase()+s.slice(1)+'</option>';}).join('')+'</select>');
        h+=this.row('Colour source','<select class="hm-dd" id="hs-colorSource">'+[['appointment_type','Appointment Type'],['clinic','Clinic'],['dispenser','Dispenser'],['global','Global (single colour)']].map(function(s){return'<option value="'+s[0]+'"'+(v('color_source','appointment_type')===s[0]?' selected':'')+'>'+s[1]+'</option>';}).join('')+'</select>');
        h+='<div style="font-size:11px;color:var(--hm-text-muted);margin-top:6px;padding:0 2px"><strong>Solid:</strong> filled colour, white text. <strong>Tinted:</strong> light colour wash + accent bar. <strong>Outline:</strong> border only. <strong>Minimal:</strong> left bar only.</div>';
        h+='</div></div>';

        // ── Rules & Safety ──
        h+='<div class="hm-card"><div class="hm-card-hd"><span class="hm-card-hd-icon">🛡</span><h3>Rules &amp; Safety</h3></div><div class="hm-card-body">';
        h+=this.tog('Require cancellation reason','hs-cancelReason',vb('require_cancel_reason',true));
        h+=this.tog('Hide cancelled appointments','hs-hideCancelled',vb('hide_cancelled',true));
        h+=this.tog('Require reschedule note','hs-reschedNote',vb('require_reschedule_note',false));
        h+=this.tog('Prevent mismatched location bookings','hs-locMismatch',vb('prevent_location_mismatch',false));
        h+='</div></div>';

        // ── Working Days & Availability ──
        h+='<div class="hm-card"><div class="hm-card-hd"><span class="hm-card-hd-icon">📅</span><h3>Working Days</h3></div><div class="hm-card-body">';
        var wd=(v('working_days','1,2,3,4,5')).split(',').map(function(x){return x.trim();});
        h+='<div class="hm-srow" style="flex-direction:column;align-items:stretch"><span class="hm-slbl" style="margin-bottom:8px">Enabled days</span><div class="hm-day-checks">';
        [['1','Mon'],['2','Tue'],['3','Wed'],['4','Thu'],['5','Fri'],['6','Sat'],['0','Sun']].forEach(function(dd){h+='<label><input type="checkbox" class="hs-wd" value="'+dd[0]+'"'+(wd.indexOf(dd[0])!==-1?' checked':'')+'>'+dd[1]+'</label>';});
        h+='</div></div>';
        h+=this.tog('Apply clinic colour to working times','hs-clinicColour',vb('apply_clinic_colour',false));
        h+='</div></div>';

        // ── Calendar Order ──
        h+='<div class="hm-card"><div class="hm-card-hd"><span class="hm-card-hd-icon">⠿</span><h3>Calendar Order</h3></div><div class="hm-card-body">';
        h+='<div style="font-size:12px;color:#94a3b8;margin-bottom:10px">Drag to reorder how dispensers appear on the calendar.</div>';
        h+='<ul class="hm-sort-list" id="hs-sortList">';
        this.dispensers.forEach(function(dd){var ini=esc(dd.initials||'');h+='<li class="hm-sort-item" data-id="'+dd.id+'"><span class="hm-sort-grip">⠿</span><span class="hm-sort-avatar">'+ini+'</span><span class="hm-sort-info"><span class="hm-sort-name">'+esc(dd.name)+'</span><span class="hm-sort-role">'+ini+' · '+(esc(dd.role_type)||'Dispenser')+'</span></span></li>';});
        h+='</ul></div></div>';

        h+='</div>'; // end left

        // ═══ RIGHT COLUMN ═══
        h+='<div class="hs-right">';

        // ── Card Content ──
        h+='<div class="hm-card"><div class="hm-card-hd"><span class="hm-card-hd-icon">👁</span><h3>Card Content</h3></div><div class="hm-card-body">';
        h+=this.tog('Show appointment type','hs-showApptType',vb('show_appointment_type',true));
        h+=this.tog('Show time on card','hs-showTime',vb('show_time',true));
        h+=this.tog('Show clinic name','hs-showClinic',vb('show_clinic',false));
        h+=this.tog('Show dispenser initials','hs-showDispIni',vb('show_dispenser_initials',true));
        h+=this.tog('Show status badge','hs-showStatusBadge',vb('show_status_badge',true));
        h+=this.tog('Display full name (vs first name only)','hs-fullName',vb('display_full_name',false));
        h+=this.tog('Display time inline with patient name','hs-timeInline',vb('show_time_inline',false));
        h+=this.tog('Hide appointment end time','hs-hideEnd',vb('hide_end_time',true));
        h+='<div class="hm-srow" style="flex-direction:column;align-items:stretch"><span class="hm-slbl">Outcome style</span><div class="hm-radio-grp" style="margin-top:8px">';
        ['default','small','tag','popover'].forEach(function(ss){h+='<label><input type="radio" name="hs-outcome" value="'+ss+'"'+(v('outcome_style','default')===ss?' checked':'')+'>'+ss.charAt(0).toUpperCase()+ss.slice(1)+'</label>';});
        h+='</div></div>';
        h+='</div></div>';

        // ── Card Colours ──
        h+='<div class="hm-card"><div class="hm-card-hd"><span class="hm-card-hd-icon">🎭</span><h3>Card Colours</h3></div><div class="hm-card-body">';
        h+='<div style="font-size:11px;color:var(--hm-text-muted);margin-bottom:10px">These apply when colour source is <strong>Global</strong>, or as fallback colours.</div>';
        h+=this.colorRow('Card background','hs-apptBg',v('appt_bg_color','#0BB4C4'));
        h+=this.colorRow('Patient name','hs-apptFont',v('appt_font_color','#ffffff'));
        h+=this.colorRow('Badge colour','hs-apptBadge',v('appt_badge_color','#3b82f6'));
        h+=this.colorRow('Badge text','hs-apptBadgeFont',v('appt_badge_font_color','#ffffff'));
        h+=this.colorRow('Meta text','hs-apptMeta',v('appt_meta_color','#38bdf8'));
        h+='</div></div>';

        // ── Calendar Theme ──
        h+='<div class="hm-card"><div class="hm-card-hd"><span class="hm-card-hd-icon">🖌</span><h3>Calendar Theme</h3></div><div class="hm-card-body">';
        h+=this.colorRow('Time indicator','hs-indicator',v('indicator_color','#00d59b'));
        h+=this.colorRow('Today highlight','hs-todayHl',v('today_highlight_color','#e6f7f9'));
        h+=this.colorRow('Grid lines','hs-gridLine',v('grid_line_color','#e2e8f0'));
        h+=this.colorRow('Calendar background','hs-calBg',v('cal_bg_color','#ffffff'));
        h+='</div></div>';

        // ── Save ──
        h+='<div class="hm-card"><div class="hm-card-body" style="display:flex;justify-content:flex-end;gap:8px"><button class="hm-btn hm-btn--primary" id="hs-save">Save Changes</button></div></div>';

        h+='</div></div>'; // end right, end two-column
        this.$el.html(h);
        try{$('#hs-sortList').sortable({handle:'.hm-sort-grip'});}catch(e){}
    },
    bind:function(){var self=this;$(document).on('click','#hs-save',function(){self.save();});$(document).on('input','.hm-color-inp',function(){var id=$(this).attr('id');$('.hm-color-hex[data-for="'+id+'"]').text($(this).val());});},
    save:function(){
        var wd=[];$('.hs-wd:checked').each(function(){wd.push($(this).val());});
        var order=[];$('#hs-sortList .hm-sort-item').each(function(){order.push($(this).data('id'));});
        var $btn=$('#hs-save');$btn.text('Saving...').prop('disabled',true);
        var chk=function(id){return $('#'+id).is(':checked')?1:0;};
        post('save_settings',{
            // Time & View
            start_time:$('#hs-start').val(),end_time:$('#hs-end').val(),
            time_interval:$('#hs-interval').val(),slot_height:$('#hs-slotH').val(),
            default_view:$('#hs-view').val(),default_mode:'people',
            // Card Appearance
            card_style:$('#hs-cardStyle').val(),
            color_source:$('#hs-colorSource').val(),
            // Card Content
            show_appointment_type:chk('hs-showApptType'),
            show_time:chk('hs-showTime'),
            show_clinic:chk('hs-showClinic'),
            show_dispenser_initials:chk('hs-showDispIni'),
            show_status_badge:chk('hs-showStatusBadge'),
            display_full_name:chk('hs-fullName'),
            show_time_inline:chk('hs-timeInline'),
            hide_end_time:chk('hs-hideEnd'),
            outcome_style:$('input[name="hs-outcome"]:checked').val()||'default',
            // Card Colours
            appt_bg_color:$('#hs-apptBg').val(),
            appt_font_color:$('#hs-apptFont').val(),
            appt_badge_color:$('#hs-apptBadge').val(),
            appt_badge_font_color:$('#hs-apptBadgeFont').val(),
            appt_meta_color:$('#hs-apptMeta').val(),
            // Calendar Theme
            indicator_color:$('#hs-indicator').val(),
            today_highlight_color:$('#hs-todayHl').val(),
            grid_line_color:$('#hs-gridLine').val(),
            cal_bg_color:$('#hs-calBg').val(),
            // Rules & Safety
            require_cancel_reason:chk('hs-cancelReason'),
            hide_cancelled:chk('hs-hideCancelled'),
            require_reschedule_note:chk('hs-reschedNote'),
            prevent_location_mismatch:chk('hs-locMismatch'),
            apply_clinic_colour:chk('hs-clinicColour'),
            // Working Days
            working_days:wd.join(','),
            enabled_days:wd.map(function(n){return['sun','mon','tue','wed','thu','fri','sat'][parseInt(n)]||n;}).join(','),
            calendar_order:JSON.stringify(order),
        }).then(function(r){
            if(r.success){
                post('save_dispenser_order',{order:JSON.stringify(order)});
                $btn.text('✓ Saved').prop('disabled',false);
                setTimeout(function(){$btn.text('Save Changes');},2000);
            }else{alert('Error saving');$btn.text('Save Changes').prop('disabled',false);}
        });
    },
    row:function(lbl,ctrl){return'<div class="hm-srow"><span class="hm-slbl">'+lbl+'</span><div class="hm-sval">'+ctrl+'</div></div>';},
    tog:function(lbl,id,on,hint){
        var h='<div class="hm-srow"><span class="hm-slbl">'+lbl;
        if(hint)h+='<span class="hm-slbl-hint">'+hint+'</span>';
        h+='</span><label class="hm-tog"><input type="checkbox" id="'+id+'"'+(on?' checked':'')+'><span class="hm-tog-track"></span><span class="hm-tog-thumb"></span></label></div>';
        return h;
    },
    colorRow:function(lbl,id,val){
        return'<div class="hm-srow"><span class="hm-slbl">'+lbl+'</span><div class="hm-sval hm-color-pick"><input type="color" id="'+id+'" value="'+esc(val)+'" class="hm-color-inp"><span class="hm-color-hex" data-for="'+id+'">'+esc(val)+'</span></div></div>';
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
            h+='<tr><td><strong>'+esc(b.service_name)+'</strong></td><td>'+esc(b.dispenser_name)+'</td><td>'+b.start_date+' → '+b.end_date+'</td><td>'+(b.start_time||'—')+' – '+(b.end_time||'—')+'</td><td class="hm-table-acts"><button class="hm-act-btn hm-act-edit hbl-edit" data-id="'+b._ID+'">✏️</button><button class="hm-act-btn hm-act-del hbl-del" data-id="'+b._ID+'">🗑️</button></td></tr>';
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
            h+='<tr><td><strong>'+esc(ho.dispenser_name)+'</strong></td><td>'+esc(ho.reason)+'</td><td>'+(ho.repeats==='no'?'—':ho.repeats)+'</td><td>'+ho.start_date+' → '+ho.end_date+'</td><td>'+(ho.start_time||'—')+' – '+(ho.end_time||'—')+'</td><td class="hm-table-acts"><button class="hm-act-btn hm-act-edit hhl-edit" data-id="'+ho._ID+'">✏️</button><button class="hm-act-btn hm-act-del hhl-del" data-id="'+ho._ID+'">🗑️</button></td></tr>';
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

