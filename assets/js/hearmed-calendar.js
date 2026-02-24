/**
 * HearMed Calendar v2.1
 * Renders views based on #hm-app[data-view]
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
    plus:'<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 5v14M5 12h14"/></svg>',
    cog:'<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/></svg>',
    print:'<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>',
    cal:'<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>',
    user:'<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>',
    clock:'<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>',
    x:'<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12"/></svg>',
};

// ═══ ROUTER ═══
var App={
    init:function(){
        var $el=$('#hm-app');
        if(!$el.length)return;
        var v=$el.data('view')||'';
        if(v==='calendar')Cal.init($el);
        else if(v==='settings')Settings.init($el);
        else if(v==='appointment-types')ApptTypes.init($el);
        else if(v==='blockouts')Blockouts.init($el);
        else if(v==='holidays')Holidays.init($el);
    }
};

// ═══════════════════════════════════════
// CALENDAR VIEW
// ═══════════════════════════════════════
var Cal={
    $el:null,date:new Date(),mode:'week',viewMode:'people',
    dispensers:[],services:[],clinics:[],appts:[],holidays:[],blockouts:[],
    selClinic:0,selDisp:0,svcMap:{},cfg:{},

    init:function($el){
        this.$el=$el;
        var s=HM.settings||{};
        this.cfg={
            slotMin:parseInt(s.time_interval)||30,
            startH:parseInt((s.start_time||'09:00').split(':')[0]),
            endH:parseInt((s.end_time||'18:00').split(':')[0]),
            slotHt:s.slot_height||'regular',
            showTimeInline:s.show_time_inline==='yes',
            hideEndTime:s.hide_end_time!=='no',
            outcomeStyle:s.outcome_style||'default',
            hideCancelled:s.hide_cancelled!=='no',
            displayFull:s.display_full_name==='yes',
            enabledDays:(s.enabled_days||'mon,tue,wed,thu,fri').split(','),
        };
        this.mode=s.default_view||'week';
        this.viewMode=s.default_mode||'people';
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
                    '<button class="hm-icon-btn" id="hm-cog" title="Settings">'+IC.cog+'</button>'+
                    '<button class="hm-icon-btn" onclick="window.print()" title="Print">'+IC.print+'</button>'+
                    '<div class="hm-sep"></div>'+
                    '<select class="hm-dd" id="hm-clinicF"><option value="">All Clinics</option></select>'+
                    '<select class="hm-dd" id="hm-dispF"><option value="">All Assignees</option></select>'+
                    '<div style="position:relative"><button class="hm-plus-btn" id="hm-plusBtn">'+IC.plus+'</button>'+
                        '<div class="hm-plus-menu" id="hm-plusMenu">'+
                            '<div class="hm-plus-item" data-act="appointment">'+IC.cal+' Appointment</div>'+
                            '<div class="hm-plus-item" data-act="patient">'+IC.user+' Patient</div>'+
                            '<div class="hm-plus-item" data-act="holiday">'+IC.clock+' Holiday / Unavailability</div>'+
                        '</div>'+
                    '</div>'+
                '</div>'+
            '</div>'+
            '<div class="hm-grid-wrap" id="hm-gridWrap"><div class="hm-grid" id="hm-grid"></div></div>'+
        '</div>'+
        '<div class="hm-pop" id="hm-pop"></div>'+
        '<div class="hm-cog-panel" id="hm-cogPanel">'+
            '<h3>Settings <button class="hm-cog-x" id="hm-cogX">'+IC.x+'</button></h3>'+
            '<div class="hm-cog-section"><div class="hm-cog-label">Mode</div><div class="hm-cog-tog" id="hm-cogMode"><button class="hm-cog-tog-btn" data-v="people">People</button><button class="hm-cog-tog-btn" data-v="clinics">Clinics</button></div></div>'+
            '<div class="hm-cog-section"><div class="hm-cog-label">Timeframe</div><div class="hm-cog-tog" id="hm-cogTime"><button class="hm-cog-tog-btn" data-v="day">Day</button><button class="hm-cog-tog-btn" data-v="week">Week</button></div></div>'+
            '<div class="hm-cog-ft"><button class="hm-btn" id="hm-cogCancel">Cancel</button><button class="hm-btn hm-btn-teal" id="hm-cogSave">Save</button></div>'+
        '</div>'
        );
    },

    bind:function(){
        var self=this;
        $(document).on('click','#hm-prev',function(){self.nav(-1);});
        $(document).on('click','#hm-next',function(){self.nav(1);});
        $(document).on('click','#hm-dateBox',function(){var dp=$('#hm-datePick');dp[0].showPicker?dp[0].showPicker():dp.trigger('click');});
        $(document).on('change','#hm-datePick',function(){var v=$(this).val();if(v){self.date=new Date(v+'T12:00:00');self.refresh();}});
        $(document).on('click','.hm-view-btn',function(){self.mode=$(this).data('v');self.refreshUI();});
        $(document).on('change','#hm-clinicF',function(){self.selClinic=parseInt($(this).val())||0;self.loadDispensers().then(function(){self.refresh();});});
        $(document).on('change','#hm-dispF',function(){self.selDisp=parseInt($(this).val())||0;self.refresh();});
        $(document).on('click','#hm-plusBtn',function(e){e.stopPropagation();$('#hm-plusMenu').toggleClass('open');});
        $(document).on('click',function(){$('#hm-plusMenu').removeClass('open');});
        $(document).on('click','.hm-plus-item',function(){$('#hm-plusMenu').removeClass('open');self.onPlusAction($(this).data('act'));});
        $(document).on('click','#hm-cog',function(){$('#hm-cogPanel').toggleClass('open');self.updateCogUI();});
        $(document).on('click','#hm-cogX, #hm-cogCancel',function(){$('#hm-cogPanel').removeClass('open');});
        $(document).on('click','#hm-cogSave',function(){$('#hm-cogPanel').removeClass('open');self.refresh();});
        $(document).on('click','#hm-cogMode .hm-cog-tog-btn',function(){self.viewMode=$(this).data('v');self.updateCogUI();self.refresh();});
        $(document).on('click','#hm-cogTime .hm-cog-tog-btn',function(){self.mode=$(this).data('v');self.updateCogUI();self.refreshUI();});
        $(document).on('click',function(e){if(!$(e.target).closest('.hm-pop,.hm-appt').length)$('#hm-pop').removeClass('open');});
        $(document).on('click','.hm-pop-x',function(){$('#hm-pop').removeClass('open');});
        $(document).on('click','.hm-pop-edit',function(){self.editPop();});
        $(document).on('dblclick','.hm-slot',function(){self.onSlot(this);});
        var rt;$(window).on('resize',function(){clearTimeout(rt);rt=setTimeout(function(){self.refresh();},150);});
        $(document).on('keydown',function(e){if(e.key==='Escape'){$('#hm-pop').removeClass('open');$('#hm-cogPanel').removeClass('open');}});
    },

    loadData:function(){
        var self=this;
        this.loadClinics().then(function(){return self.loadDispensers();}).then(function(){return self.loadServices();}).then(function(){self.refresh();});
    },
    loadClinics:function(){
        return post('get_clinics').then(function(r){
            if(!r.success)return;
            Cal.clinics=r.data;
            var $f=$('#hm-clinicF');$f.find('option:not(:first)').remove();
            r.data.forEach(function(c){$f.append('<option value="'+c.id+'">'+esc(c.name)+'</option>');});
        });
    },
    loadDispensers:function(){
        return post('get_dispensers',{clinic:this.selClinic,date:fmt(this.date)}).then(function(r){
            if(!r.success)return;
            Cal.dispensers=r.data;
            var $f=$('#hm-dispF');$f.find('option:not(:first)').remove();
            r.data.forEach(function(d){$f.append('<option value="'+d.id+'">'+esc(d.initials)+' — '+esc(d.name)+'</option>');});
            if(Cal.selDisp)$f.val(Cal.selDisp);
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
        return post('get_appointments',{start:fmt(dates[0]),end:fmt(dates[dates.length-1]),clinic:this.selClinic})
            .then(function(r){if(r.success)Cal.appts=r.data;});
    },

    refresh:function(){var self=this;this.loadAppts().then(function(){self.renderGrid();self.renderAppts();self.renderNow();});},
    refreshUI:function(){this.renderGrid();this.renderAppts();this.renderNow();this.updateViewBtns();},

    updateViewBtns:function(){$('.hm-view-btn').removeClass('on');$('.hm-view-btn[data-v="'+this.mode+'"]').addClass('on');},
    updateCogUI:function(){
        $('#hm-cogMode .hm-cog-tog-btn').removeClass('on');$('#hm-cogMode .hm-cog-tog-btn[data-v="'+this.viewMode+'"]').addClass('on');
        $('#hm-cogTime .hm-cog-tog-btn').removeClass('on');$('#hm-cogTime .hm-cog-tog-btn[data-v="'+this.mode+'"]').addClass('on');
    },
    nav:function(dir){
        this.date.setDate(this.date.getDate()+dir*(this.mode==='week'?7:1));
        $('#hm-pop').removeClass('open');
        var self=this;
        this.loadDispensers().then(function(){self.refresh();});
    },

    renderGrid:function(){
        var g=document.getElementById('hm-grid');if(!g)return;
        var dates=this.visDates(),disps=this.visDisps(),cfg=this.cfg;
        this.updateDateLbl(dates);this.updateViewBtns();

        var wrap=document.getElementById('hm-gridWrap');
	// Fixed slot heights based on setting
	var slotMap = {
    		compact: 20,
   		regular: 28,
    		large: 38
	};

var slotH = slotMap[cfg.slotHt] || 28;
cfg.slotHpx = slotH;

        if(!disps.length){g.innerHTML='<div style="text-align:center;padding:80px;color:var(--hm-text-faint);font-size:15px">No dispensers found. Add dispensers in your Dispenser post type.</div>';g.style.gridTemplateColumns='';return;}

        var colW=Math.max(80,Math.min(140,Math.floor(900/disps.length)));
        var tc=disps.length*dates.length;
        g.style.gridTemplateColumns='44px repeat('+tc+',minmax('+colW+'px,1fr))';

        var h='<div class="hm-time-corner"></div>';

        dates.forEach(function(d){
            var td=isToday(d);
            h+='<div class="hm-day-hd'+(td?' today':'')+'" style="grid-column:span '+disps.length+'">';
            h+='<span class="hm-day-lbl">'+DAYS[d.getDay()]+'</span> <span class="hm-day-num">'+d.getDate()+'</span> <span class="hm-day-lbl">'+MO[d.getMonth()]+'</span>';
            h+='<div class="hm-prov-row">';
            disps.forEach(function(p){
                var lbl=Cal.cfg.displayFull?esc(p.name):esc(p.initials);
                h+='<div class="hm-prov-cell"><div class="hm-prov-ini">'+lbl+'</div></div>';
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
        g.innerHTML=h;
    },

    renderAppts:function(){
        $('.hm-appt,.hm-lunch').remove();
        var dates=this.visDates(),disps=this.visDisps(),cfg=this.cfg,slotH=cfg.slotHpx;
        if(!disps.length)return;

        // Lunch default 13:00 - 13:30
        var lStart=13*60,lDur=30;
        var lH=(lDur/cfg.slotMin)*slotH;
        var lSlot=Math.floor((lStart-cfg.startH*60)/cfg.slotMin);
        if(lSlot>=0&&lSlot<cfg.totalSlots){
            dates.forEach(function(d,di){
                disps.forEach(function(p){
                    var $t=$('.hm-slot[data-day="'+di+'"][data-slot="'+lSlot+'"][data-disp="'+p.id+'"]');
                    if($t.length)$t.append('<div class="hm-lunch" style="top:0;height:'+lH+'px">Lunch</div>');
                });
            });
        }

        this.appts.forEach(function(a){
            if(Cal.selDisp&&parseInt(a.dispenser_id)!==Cal.selDisp)return;
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
            var stCls=a.status==='Pending'?' pending':a.status==='Cancelled'?' cancelled':'';
            var tmLbl=cfg.showTimeInline?(a.start_time.substring(0,5)+' '):'';

            var el=$('<div class="hm-appt'+stCls+'" data-id="'+a._ID+'" style="background:'+col+';height:'+h+'px;top:'+off+'px">'+
                '<div class="hm-appt-svc">'+esc(a.service_name)+'</div>'+
                '<div class="hm-appt-pt">'+tmLbl+esc(a.patient_name||'No patient')+'</div>'+
                (h>36&&!cfg.hideEndTime?'<div class="hm-appt-tm">'+a.start_time.substring(0,5)+' – '+(a.end_time||'').substring(0,5)+'</div>':
                 h>36?'<div class="hm-appt-tm">'+a.start_time.substring(0,5)+'</div>':'')+
            '</div>');
            $t.append(el);
            el.on('click',function(e){e.stopPropagation();Cal.showPop(a,this);});
        });
    },

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
            if($t.length)$t.append('<div class="hm-now" style="top:'+off+'px"><div class="hm-now-dot"></div></div>');
        });
    },

    showPop:function(a,el){
        this._popAppt=a;
        var r=el.getBoundingClientRect();
        var col=a.service_colour||'#3B82F6';
        var html='<div class="hm-pop-bar" style="background:'+col+'"></div>'+
            '<div class="hm-pop-hd"><span>'+esc(a.service_name||'Appointment')+'</span><button class="hm-pop-x">'+IC.x+'</button></div>'+
            '<div class="hm-pop-body">'+
                '<div><span class="hm-pop-lbl">Patient</span><span>'+esc(a.patient_name||'—')+'</span></div>'+
                '<div><span class="hm-pop-lbl">Time</span><span>'+(a.start_time||'').substring(0,5)+' – '+(a.end_time||'').substring(0,5)+'</span></div>'+
                '<div><span class="hm-pop-lbl">Assignee</span><span>'+esc(a.dispenser_name||'—')+'</span></div>'+
                '<div><span class="hm-pop-lbl">Clinic</span><span>'+esc(a.clinic_name||'—')+'</span></div>'+
                '<div><span class="hm-pop-lbl">Status</span><span>'+esc(a.status||'—')+'</span></div>'+
                (a.notes?'<div><span class="hm-pop-lbl">Notes</span><span>'+esc(a.notes)+'</span></div>':'')+
            '</div>'+
            '<div class="hm-pop-ft"><button class="hm-btn hm-btn-teal hm-btn-sm hm-pop-edit">Edit</button></div>';

        var $pop=$('#hm-pop');
        $pop.html(html);
        var l=r.right+10,t=r.top;
        if(l+290>window.innerWidth)l=r.left-290;
        if(t+260>window.innerHeight)t=window.innerHeight-270;
        if(t<10)t=10;
        $pop.css({left:l,top:t}).addClass('open');
    },

    editPop:function(){
        if(!this._popAppt)return;
        Cal.openEditModal(this._popAppt);
        $('#hm-pop').removeClass('open');
    },

    openEditModal:function(a){
        var self=this;
        var html='<div class="hm-modal-bg open"><div class="hm-modal">'+
            '<div class="hm-modal-hd"><h3>Edit Appointment</h3><button class="hm-modal-x hm-edit-close">'+IC.x+'</button></div>'+
            '<div class="hm-modal-body">'+
                '<div class="hm-fld"><label>Patient</label><input class="hm-inp" id="hme-patient" value="'+esc(a.patient_name||'')+'" readonly></div>'+
                '<div class="hm-row">'+
                    '<div class="hm-fld"><label>Appointment Type</label><select class="hm-inp" id="hme-service">'+self.services.map(function(s){return'<option value="'+s.id+'"'+(s.id==a.service_id?' selected':'')+'>'+esc(s.name)+'</option>';}).join('')+'</select></div>'+
                    '<div class="hm-fld"><label>Clinic</label><select class="hm-inp" id="hme-clinic">'+self.clinics.map(function(c){return'<option value="'+c.id+'"'+(c.id==a.clinic_id?' selected':'')+'>'+esc(c.name)+'</option>';}).join('')+'</select></div>'+
                '</div>'+
                '<div class="hm-row">'+
                    '<div class="hm-fld"><label>Assignee</label><select class="hm-inp" id="hme-disp">'+self.dispensers.map(function(d){return'<option value="'+d.id+'"'+(d.id==a.dispenser_id?' selected':'')+'>'+esc(d.name)+'</option>';}).join('')+'</select></div>'+
                    '<div class="hm-fld"><label>Status</label><select class="hm-inp" id="hme-status">'+['Confirmed','Pending','Completed','Cancelled','No Show','Rescheduled'].map(function(s){return'<option'+(s===a.status?' selected':'')+'>'+s+'</option>';}).join('')+'</select></div>'+
                '</div>'+
                '<div class="hm-row">'+
                    '<div class="hm-fld"><label>Date</label><input type="date" class="hm-inp" id="hme-date" value="'+a.appointment_date+'"></div>'+
                    '<div class="hm-fld"><label>Start Time</label><input type="time" class="hm-inp" id="hme-time" value="'+(a.start_time||'').substring(0,5)+'"></div>'+
                '</div>'+
                '<div class="hm-fld"><label>Notes</label><textarea class="hm-inp" id="hme-notes">'+esc(a.notes||'')+'</textarea></div>'+
            '</div>'+
            '<div class="hm-modal-ft">'+
                '<button class="hm-btn hm-btn-red hm-edit-del">Delete</button>'+
                '<div class="hm-modal-acts"><button class="hm-btn hm-edit-close">Cancel</button><button class="hm-btn hm-btn-teal hm-edit-save">Save</button></div>'+
            '</div>'+
        '</div></div>';
        $('body').append(html);

        $(document).off('click.editclose').on('click.editclose','.hm-edit-close',function(e){
            e.stopPropagation();
            $('.hm-modal-bg').remove();$(document).off('.editmodal .editclose');
        });
        $(document).off('.editmodal').on('click.editmodal','.hm-modal-bg',function(e){
            if($(e.target).hasClass('hm-modal-bg')){$('.hm-modal-bg').remove();$(document).off('.editmodal .editclose');}
        });
        $(document).off('click.editsave').on('click.editsave','.hm-edit-save',function(){
            post('update_appointment',{
                appointment_id:a._ID,patient_id:a.patient_id,
                service_id:$('#hme-service').val(),clinic_id:$('#hme-clinic').val(),
                dispenser_id:$('#hme-disp').val(),status:$('#hme-status').val(),
                appointment_date:$('#hme-date').val(),start_time:$('#hme-time').val(),
                notes:$('#hme-notes').val()
            }).then(function(r){if(r.success){$('.hm-modal-bg').remove();$(document).off('.editmodal .editclose .editsave .editdel');self.refresh();}else{alert('Error saving');}});
        });
        $(document).off('click.editdel').on('click.editdel','.hm-edit-del',function(){
            if(!confirm('Delete this appointment?'))return;
            var reason=prompt('Reason for cancellation:')||'Deleted';
            post('delete_appointment',{appointment_id:a._ID,reason:reason}).then(function(r){
                if(r.success){$('.hm-modal-bg').remove();$(document).off('.editmodal .editclose .editsave .editdel');self.refresh();}else{alert(r.data||'Error');}
            });
        });
    },

    onSlot:function(el){
        var d=el.dataset;
        this.openNewApptModal(d.date,d.time,parseInt(d.disp));
    },

    openNewApptModal:function(date,time,dispId){
        var self=this;
        var html='<div class="hm-modal-bg open"><div class="hm-modal" style="width:540px">'+
            '<div class="hm-modal-hd"><h3>New Appointment</h3><button class="hm-modal-x hm-new-close">'+IC.x+'</button></div>'+
            '<div class="hm-modal-body">'+
                '<div class="hm-fld"><label>Patient search</label><input class="hm-inp" id="hmn-ptsearch" placeholder="Search by name..." autocomplete="off"><div class="hm-pt-results" id="hmn-ptresults"></div><input type="hidden" id="hmn-patientid" value="0"></div>'+
                '<div class="hm-row">'+
                    '<div class="hm-fld"><label>Appointment Type</label><select class="hm-inp" id="hmn-service">'+self.services.map(function(s){return'<option value="'+s.id+'">'+esc(s.name)+'</option>';}).join('')+'</select></div>'+
                    '<div class="hm-fld"><label>Clinic</label><select class="hm-inp" id="hmn-clinic">'+self.clinics.map(function(c){return'<option value="'+c.id+'">'+esc(c.name)+'</option>';}).join('')+'</select></div>'+
                '</div>'+
                '<div class="hm-row">'+
                    '<div class="hm-fld"><label>Assignee</label><select class="hm-inp" id="hmn-disp">'+self.dispensers.map(function(d){return'<option value="'+d.id+'"'+(d.id===dispId?' selected':'')+'>'+esc(d.name)+'</option>';}).join('')+'</select></div>'+
                    '<div class="hm-fld"><label>Status</label><select class="hm-inp" id="hmn-status"><option>Confirmed</option><option>Pending</option></select></div>'+
                '</div>'+
                '<div class="hm-row">'+
                    '<div class="hm-fld"><label>Date</label><input type="date" class="hm-inp" id="hmn-date" value="'+date+'"></div>'+
                    '<div class="hm-fld"><label>Start Time</label><input type="time" class="hm-inp" id="hmn-time" value="'+time+'"></div>'+
                '</div>'+
                '<div class="hm-fld"><label>Location</label><select class="hm-inp" id="hmn-loc"><option>Clinic</option><option>Home</option></select></div>'+
                '<div class="hm-fld"><label>Notes</label><textarea class="hm-inp" id="hmn-notes" placeholder="Optional notes..."></textarea></div>'+
            '</div>'+
            '<div class="hm-modal-ft"><span></span><div class="hm-modal-acts"><button class="hm-btn hm-new-close">Cancel</button><button class="hm-btn hm-btn-teal hm-new-save">Create Appointment</button></div></div>'+
        '</div></div>';
        $('body').append(html);

        // Patient search
        var searchTimer;
        $(document).on('input.newmodal','#hmn-ptsearch',function(){
            var q=$(this).val();
            clearTimeout(searchTimer);
            if(q.length<2){$('#hmn-ptresults').removeClass('open').empty();return;}
            searchTimer=setTimeout(function(){
                post('search_patients',{query:q}).then(function(r){
                    if(!r.success||!r.data.length){$('#hmn-ptresults').removeClass('open').empty();return;}
                    var h='';
                    r.data.forEach(function(p){
                        h+='<div class="hm-pt-item" data-id="'+p.id+'"><span>'+esc(p.name)+'</span><span class="hm-pt-newtab">Select</span></div>';
                    });
                    $('#hmn-ptresults').html(h).addClass('open');
                });
            },300);
        });
        $(document).on('click.newmodal','.hm-pt-item',function(){
            var id=$(this).data('id'),name=$(this).find('span:first').text();
            $('#hmn-ptsearch').val(name);$('#hmn-patientid').val(id);$('#hmn-ptresults').removeClass('open');
        });

        $(document).off('click.newclose').on('click.newclose','.hm-new-close',function(e){
            e.stopPropagation();
            $('.hm-modal-bg').remove();$(document).off('.newmodal .newclose');
        });
        $(document).off('click.newbg').on('click.newbg','.hm-modal-bg',function(e){
            if($(e.target).hasClass('hm-modal-bg')){
                $('.hm-modal-bg').remove();$(document).off('.newmodal .newclose .newbg');
            }
        });
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
        if(act==='appointment')this.openNewApptModal(fmt(this.date),pad(this.cfg.startH)+':00',this.dispensers.length?this.dispensers[0].id:0);
        else if(act==='patient')alert('Navigate to your patient admin page to add a new patient');
        else if(act==='holiday')window.location.href='/adminconsole/holidays';
    },

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
        if(this.selDisp)d=d.filter(function(x){return parseInt(x.id)===Cal.selDisp;});
        return d;
    },
    updateDateLbl:function(dates){
        var s=dates[0],e=dates[dates.length-1];
        var txt=this.mode==='day'?DAYS[s.getDay()]+', '+s.getDate()+' '+MO[s.getMonth()]+' '+s.getFullYear():
            s.getDate()+' '+MO[s.getMonth()]+' – '+e.getDate()+' '+MO[e.getMonth()]+' '+e.getFullYear();
        $('#hm-dateLbl').text(txt);
    },
};

// ═══════════════════════════════════════
// SETTINGS VIEW
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
        var h='<div class="hm-settings">';

        // Header
        h+='<div class="hm-admin-hd"><div><h2>Calendar Settings</h2><div class="hm-admin-subtitle">Adjust your scheduling and display preferences.</div></div></div>';

        // Card grid
        h+='<div class="hm-card-grid">';

        // ── Card 1: Time & View ──
        h+='<div class="hm-card">';
        h+='<div class="hm-card-hd"><span class="hm-card-hd-icon">🕐</span><h3>Time &amp; View</h3></div>';
        h+='<div class="hm-card-body">';
        h+=this.row('Start time','<input type="time" class="hm-inp" id="hs-start" value="'+esc(v('start_time','09:00'))+'" style="width:130px">');
        h+=this.row('End time','<input type="time" class="hm-inp" id="hs-end" value="'+esc(v('end_time','18:00'))+'" style="width:130px">');
        h+=this.row('Time interval','<select class="hm-dd" id="hs-interval">'+[15,20,30,45,60].map(function(m){return'<option value="'+m+'"'+(parseInt(v('time_interval',30))===m?' selected':'')+'>'+m+' minutes</option>';}).join('')+'</select>');
        h+=this.row('Slot height','<select class="hm-dd" id="hs-slotH">'+['compact','regular','large'].map(function(s){return'<option value="'+s+'"'+(v('slot_height','regular')===s?' selected':'')+'>'+s.charAt(0).toUpperCase()+s.slice(1)+'</option>';}).join('')+'</select>');
        h+=this.row('Default timeframe','<select class="hm-dd" id="hs-view">'+['day','week'].map(function(s){return'<option value="'+s+'"'+(v('default_view','week')===s?' selected':'')+'>'+s.charAt(0).toUpperCase()+s.slice(1)+'</option>';}).join('')+'</select>');
        h+='</div></div>';

        // ── Card 2: Display Preferences ──
        h+='<div class="hm-card">';
        h+='<div class="hm-card-hd"><span class="hm-card-hd-icon">👁</span><h3>Display Preferences</h3></div>';
        h+='<div class="hm-card-body">';
        h+=this.tog('Display time inline with patient name','hs-timeInline',v('show_time_inline','no')==='yes');
        h+=this.tog('Hide appointment end time','hs-hideEnd',v('hide_end_time','yes')==='yes');
        h+='<div class="hm-srow" style="flex-direction:column;align-items:stretch"><span class="hm-slbl">Outcome style</span><div class="hm-radio-grp" style="margin-top:8px">';
        ['default','small','tag','popover'].forEach(function(s){h+='<label><input type="radio" name="hs-outcome" value="'+s+'"'+(v('outcome_style','default')===s?' checked':'')+'>'+s.charAt(0).toUpperCase()+s.slice(1)+'</label>';});
        h+='</div></div>';
        h+=this.tog('Display full resource name','hs-fullName',v('display_full_name','no')==='yes');
        h+='</div></div>';

        // ── Card 3: Rules & Safety ──
        h+='<div class="hm-card">';
        h+='<div class="hm-card-hd"><span class="hm-card-hd-icon">🛡</span><h3>Rules &amp; Safety</h3></div>';
        h+='<div class="hm-card-body">';
        h+=this.tog('Require cancellation reason','hs-cancelReason',v('require_cancel_reason','yes')==='yes','Patients will be prompted when cancelling online.');
        h+=this.tog('Hide cancelled appointments','hs-hideCancelled',v('hide_cancelled','yes')==='yes');
        h+=this.tog('Require reschedule note','hs-reschedNote',v('require_reschedule_note','no')==='yes');
        h+=this.tog('Prevent mismatched location bookings','hs-locMismatch',v('prevent_location_mismatch','no')==='yes');
        h+='</div></div>';

        // ── Card 4: Availability ──
        h+='<div class="hm-card">';
        h+='<div class="hm-card-hd"><span class="hm-card-hd-icon">📅</span><h3>Availability</h3></div>';
        h+='<div class="hm-card-body">';
        var enDays=(v('enabled_days','mon,tue,wed,thu,fri')).split(',');
        h+='<div class="hm-srow" style="flex-direction:column;align-items:stretch"><span class="hm-slbl" style="margin-bottom:8px">Enabled days</span><div class="hm-day-checks">';
        ['mon','tue','wed','thu','fri','sat','sun'].forEach(function(d){h+='<label><input type="checkbox" class="hs-day" value="'+d+'"'+(enDays.indexOf(d)!==-1?' checked':'')+'>'+d.charAt(0).toUpperCase()+d.slice(1)+'</label>';});
        h+='</div></div>';
        h+=this.tog('Apply clinic colour to working times','hs-clinicColour',v('apply_clinic_colour','no')==='yes');
        h+='</div></div>';

        // ── Card 5: Calendar Order (full width) ──
        h+='<div class="hm-card hm-card-grid-full">';
        h+='<div class="hm-card-hd"><span class="hm-card-hd-icon">⠿</span><h3>Calendar Order</h3></div>';
        h+='<div class="hm-card-body">';
        h+='<div style="font-size:12px;color:#94a3b8;margin-bottom:10px">Drag to reorder how dispensers appear on the calendar.</div>';
        h+='<ul class="hm-sort-list" id="hs-sortList">';
        this.dispensers.forEach(function(d){
            var ini=esc(d.initials||'');
            h+='<li class="hm-sort-item" data-id="'+d.id+'">';
            h+='<span class="hm-sort-grip">⠿</span>';
            h+='<span class="hm-sort-avatar">'+ini+'</span>';
            h+='<span class="hm-sort-info"><span class="hm-sort-name">'+esc(d.name)+'</span><span class="hm-sort-role">'+ini+' · '+(esc(d.role_type)||'Dispenser')+'</span></span>';
            h+='</li>';
        });
        h+='</ul></div></div>';

        // Close grid
        // Preview area (appointment block preview)
        h+='<div class="hm-card hm-card-grid-full" style="margin-top:18px">';
        h+='<div class="hm-card-hd"><h3>Preview</h3></div>';
        h+='<div class="hm-card-body"><div id="hs-preview" class="hs-preview-container"></div></div>';
        h+='</div>';

        // Save area
        h+='<div class="hm-save-area"><span class="hm-toast" id="hs-toast" style="display:none"><span class="hm-toast-icon">✓</span> Calendar updated successfully</span><button class="hm-btn hm-btn-teal" id="hs-save">Save Changes</button></div>';

        h+='</div>';
        this.$el.html(h);
        $('#hs-sortList').sortable({handle:'.hm-sort-grip'});
    },
    bind:function(){var self=this;$(document).on('click','#hs-save',function(){self.save();});
        // update preview when inputs change
        $(document).on('change', '#hs-start,#hs-end,#hs-interval,#hs-slotH,#hs-view, input[name="hs-outcome"], #hs-fullName, .hs-day, #hs-timeInline, #hs-hideEnd, #hs-clinicColour', function(){
            try{ self.updatePreview(); }catch(e){console.error(e);} 
        });
        // also update when sort list changes (order)
        $(document).on('sortupdate', '#hs-sortList', function(){ try{ self.updatePreview(); }catch(e){console.error(e);} });
    },

    updatePreview:function(){
        var data=this.data||{};
        var start=$('#hs-start').val()||data.start_time||'09:00';
        var name='Joe Bloggs';
        var svc='Follow up';
        var clinic='Cosgrove\'s Pharmacy';
        var outcome=$('input[name="hs-outcome"]:checked').val()||data.outcome_style||'default';
        var fullName = $('#hs-fullName').is(':checked') || (data.display_full_name==='yes');
        var html='';
        html += '<div class="hm-appt-preview-wrap">';
        html += '<div class="hm-appt-preview-card">';
        html += '<div class="hm-appt-outcome">Outcome</div>';
        html += '<div class="hm-appt-body">';
        html += '<div class="hm-appt-name">'+name+'</div>';
        html += '<div class="hm-appt-badges">';
        html += '<span class="hm-badge hm-badge-c">C</span>';
        html += '<span class="hm-badge hm-badge-r">R</span>';
        html += '<span class="hm-badge hm-badge-v">VM</span>';
        html += '</div>';
        html += '<div class="hm-appt-time">'+start+'</div>';
        html += '<div class="hm-appt-meta">'+svc+' · '+clinic+'</div>';
        html += '</div>'; // body
        html += '</div>'; // card
        html += '</div>'; // wrap
        $('#hs-preview').html(html);
        // style variations
        var $card=$('#hs-preview .hm-appt-preview');
        // also support new card class
        var $newCard = $('#hs-preview .hm-appt-preview-card');
        $newCard.removeClass('outcome-default outcome-small outcome-tag outcome-popover');
        $newCard.addClass('outcome-'+(outcome||'default'));
    },
    save:function(){
        var days=[];$('.hs-day:checked').each(function(){days.push($(this).val());});
        var order=[];$('#hs-sortList .hm-sort-item').each(function(){order.push($(this).data('id'));});
        var $btn=$('#hs-save');
        $btn.text('Saving...').prop('disabled',true);
        post('save_settings',{
            start_time:$('#hs-start').val(),end_time:$('#hs-end').val(),
            time_interval:$('#hs-interval').val(),slot_height:$('#hs-slotH').val(),
            default_view:$('#hs-view').val(),default_mode:'people',
            show_time_inline:$('#hs-timeInline').is(':checked')?'yes':'no',
            hide_end_time:$('#hs-hideEnd').is(':checked')?'yes':'no',
            outcome_style:$('input[name="hs-outcome"]:checked').val()||'default',
            require_cancel_reason:$('#hs-cancelReason').is(':checked')?'yes':'no',
            hide_cancelled:$('#hs-hideCancelled').is(':checked')?'yes':'no',
            require_reschedule_note:$('#hs-reschedNote').is(':checked')?'yes':'no',
            apply_clinic_colour:$('#hs-clinicColour').is(':checked')?'yes':'no',
            display_full_name:$('#hs-fullName').is(':checked')?'yes':'no',
            prevent_location_mismatch:$('#hs-locMismatch').is(':checked')?'yes':'no',
            enabled_days:days.join(','),calendar_order:JSON.stringify(order),
        }).then(function(r){
            if(r.success){
                post('save_dispenser_order',{order:JSON.stringify(order)});
                $btn.text('Save Changes').prop('disabled',false);
                $('#hs-toast').fadeIn(200);setTimeout(function(){$('#hs-toast').fadeOut(400);},3000);
            } else {
                alert('Error saving');
                $btn.text('Save Changes').prop('disabled',false);
            }
        });
    },
    row:function(lbl,ctrl){return'<div class="hm-srow"><span class="hm-slbl">'+lbl+'</span><div class="hm-sval">'+ctrl+'</div></div>';},
    tog:function(lbl,id,on,hint){
        var h='<div class="hm-srow"><span class="hm-slbl">'+lbl;
        if(hint)h+='<span class="hm-slbl-hint">'+hint+'</span>';
        h+='</span><label class="hm-tog"><input type="checkbox" id="'+id+'"'+(on?' checked':'')+'><span class="hm-tog-track"></span><span class="hm-tog-thumb"></span></label></div>';
        return h;
    },
};

// ═══════════════════════════════════════
// APPOINTMENT TYPES VIEW
// ═══════════════════════════════════════
var ApptTypes={
    $el:null,data:[],
    init:function($el){this.$el=$el;this.load();},
    load:function(){var self=this;post('get_services').then(function(r){self.data=r.success?r.data:[];self.render();});},
    render:function(){
        var h='<div class="hm-admin"><div class="hm-admin-hd"><h2>Appointment Types</h2><button class="hm-btn hm-btn-teal" id="hat-add">+ Add Type</button></div>';
        h+='<div class="hm-filter-bar"><div class="hm-filter-row"><input class="hm-inp" id="hat-search" placeholder="Filter by name..." style="max-width:260px"></div></div>';
        h+='<table class="hm-table"><thead><tr><th>Name</th><th>Colour</th><th>Duration</th><th>Sales Opp.</th><th>Reminders</th><th>Confirmation</th><th style="width:60px"></th></tr></thead><tbody id="hat-body">';
        if(!this.data.length)h+='<tr><td colspan="7" class="hm-no-data">No appointment types found</td></tr>';
        else this.data.forEach(function(s){
            h+='<tr><td><strong>'+esc(s.name)+'</strong></td><td><span class="hm-colour-dot" style="background:'+s.colour+'"></span> <span style="color:var(--hm-text-light)">'+s.colour+'</span></td><td>'+s.duration+' min</td><td>'+(s.sales_opportunity==='yes'?'<span style="color:var(--hm-teal);font-weight:600">Yes</span>':'No')+'</td><td>'+s.reminders+'</td><td>'+(s.confirmation==='yes'?'<span style="color:var(--hm-teal);font-weight:600">Yes</span>':'No')+'</td><td><button class="hm-act-btn hm-act-edit hat-edit" data-id="'+s.id+'">✏️</button></td></tr>';
        });
        h+='</tbody></table></div>';
        this.$el.html(h);this.bind();
    },
    bind:function(){
        var self=this;
        $(document).on('click','#hat-add',function(){self.openForm(null);});
        $(document).on('click','.hat-edit',function(){var id=$(this).data('id');var s=self.data.find(function(x){return x.id==id;});if(s)self.openForm(s);});
        $(document).on('input','#hat-search',function(){var q=$(this).val().toLowerCase();$('#hat-body tr').each(function(){$(this).toggle($(this).text().toLowerCase().includes(q));});});
    },
    openForm:function(svc){
        var isEdit=!!svc,self=this;
        var h='<div class="hm-modal-bg open"><div class="hm-modal"><div class="hm-modal-hd"><h3>'+(isEdit?'Edit':'New')+' Appointment Type</h3><button class="hm-modal-x hat-close">'+IC.x+'</button></div><div class="hm-modal-body">';
        h+='<div class="hm-fld"><label>Name</label><input class="hm-inp" id="hatf-name" value="'+esc(svc?svc.name:'')+'" placeholder="e.g. Hearing Test"></div>';
        h+='<div class="hm-row"><div class="hm-fld"><label>Colour</label><div style="display:flex;align-items:center;gap:10px"><input type="color" id="hatf-colour" value="'+(svc?svc.colour:'#3B82F6')+'" style="width:48px;height:40px;border:1px solid var(--hm-border);border-radius:var(--hm-radius-sm);cursor:pointer;padding:2px"><span id="hatf-colval" style="color:var(--hm-text-light);font-size:13px">'+(svc?svc.colour:'#3B82F6')+'</span></div></div>';
        h+='<div class="hm-fld"><label>Duration (minutes)</label><input type="number" class="hm-inp" id="hatf-dur" value="'+(svc?svc.duration:30)+'" min="5" step="5"></div></div>';
        h+='<div class="hm-row"><div class="hm-fld"><label>Sales opportunity</label><select class="hm-inp" id="hatf-sales"><option value="no"'+(svc&&svc.sales_opportunity==='yes'?'':' selected')+'>No</option><option value="yes"'+(svc&&svc.sales_opportunity==='yes'?' selected':'')+'>Yes</option></select></div>';
        h+='<div class="hm-fld"><label>Reminders</label><input type="number" class="hm-inp" id="hatf-remind" value="'+(svc?svc.reminders:0)+'" min="0"></div></div>';
        h+='<div class="hm-fld"><label>Send confirmation</label><select class="hm-inp" id="hatf-confirm"><option value="no"'+(svc&&svc.confirmation==='yes'?'':' selected')+'>No</option><option value="yes"'+(svc&&svc.confirmation==='yes'?' selected':'')+'>Yes</option></select></div>';
        h+='</div><div class="hm-modal-ft">'+(isEdit?'<button class="hm-btn hm-btn-red hat-del" data-id="'+svc.id+'">Delete</button>':'<span></span>')+'<div class="hm-modal-acts"><button class="hm-btn hat-close">Cancel</button><button class="hm-btn hm-btn-teal hat-save" data-id="'+(svc?svc.id:0)+'">Save</button></div></div></div></div>';
        $('body').append(h);
        $('#hatf-colour').on('input',function(){$('#hatf-colval').text($(this).val());});
        $(document).on('click','.hat-close',function(){$('.hm-modal-bg').remove();});
        $(document).on('click','.hat-save',function(){
            post('save_service',{id:$(this).data('id'),name:$('#hatf-name').val(),colour:$('#hatf-colour').val(),duration:$('#hatf-dur').val(),sales_opportunity:$('#hatf-sales').val(),reminders:$('#hatf-remind').val(),confirmation:$('#hatf-confirm').val()})
            .then(function(r){if(r.success){$('.hm-modal-bg').remove();self.load();}else alert('Error');});
        });
        $(document).on('click','.hat-del',function(){
            if(!confirm('Delete this appointment type?'))return;
            post('delete_service',{id:$(this).data('id')}).then(function(r){if(r.success){$('.hm-modal-bg').remove();self.load();}});
        });
    }
};

// ═══════════════════════════════════════
// BLOCKOUTS VIEW
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
        var h='<div class="hm-admin"><div class="hm-admin-hd"><h2>Appointment Type Blockouts</h2><button class="hm-btn hm-btn-teal" id="hbl-add">+ Add Blockout</button></div>';
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
        var h='<div class="hm-modal-bg open"><div class="hm-modal"><div class="hm-modal-hd"><h3>'+(isEdit?'Edit':'New')+' Blockout</h3><button class="hm-modal-x hbl-close">'+IC.x+'</button></div><div class="hm-modal-body">';
        h+='<div class="hm-fld"><label>Appointment Type</label><select class="hm-inp" id="hblf-svc">'+self.services.map(function(s){return'<option value="'+s.id+'"'+(bo&&bo.service_id==s.id?' selected':'')+'>'+esc(s.name)+'</option>';}).join('')+'</select></div>';
        h+='<div class="hm-fld"><label>Assignee</label><select class="hm-inp" id="hblf-disp"><option value="0">All</option>'+self.dispensers.map(function(d){return'<option value="'+d.id+'"'+(bo&&bo.dispenser_id==d.id?' selected':'')+'>'+esc(d.name)+'</option>';}).join('')+'</select></div>';
        h+='<div class="hm-row"><div class="hm-fld"><label>Start Date</label><input type="date" class="hm-inp" id="hblf-sd" value="'+(bo?bo.start_date:'')+'"></div><div class="hm-fld"><label>End Date</label><input type="date" class="hm-inp" id="hblf-ed" value="'+(bo?bo.end_date:'')+'"></div></div>';
        h+='<div class="hm-row"><div class="hm-fld"><label>Start Time</label><input type="time" class="hm-inp" id="hblf-st" value="'+(bo?bo.start_time:'09:00')+'"></div><div class="hm-fld"><label>End Time</label><input type="time" class="hm-inp" id="hblf-et" value="'+(bo?bo.end_time:'17:00')+'"></div></div>';
        h+='</div><div class="hm-modal-ft"><span></span><div class="hm-modal-acts"><button class="hm-btn hbl-close">Cancel</button><button class="hm-btn hm-btn-teal hbl-save" data-id="'+(bo?bo._ID:0)+'">Save</button></div></div></div></div>';
        $('body').append(h);
        $(document).on('click','.hbl-close',function(){$('.hm-modal-bg').remove();});
        $(document).on('click','.hbl-save',function(){
            post('save_blockout',{_ID:$(this).data('id'),service_id:$('#hblf-svc').val(),dispenser_id:$('#hblf-disp').val(),start_date:$('#hblf-sd').val(),end_date:$('#hblf-ed').val(),start_time:$('#hblf-st').val(),end_time:$('#hblf-et').val()})
            .then(function(r){if(r.success){$('.hm-modal-bg').remove();self.load();}else alert('Error');});
        });
    }
};

// ═══════════════════════════════════════
// HOLIDAYS VIEW
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
        var h='<div class="hm-admin"><div class="hm-admin-hd"><h2>Holidays &amp; Unavailability</h2><button class="hm-btn hm-btn-teal" id="hhl-add">+ Add New</button></div>';
        h+='<div class="hm-filter-bar"><div class="hm-filter-row"><select class="hm-dd" id="hhl-dispF"><option value="">All Assignees</option>';
        this.dispensers.forEach(function(d){h+='<option value="'+d.id+'">'+esc(d.name)+'</option>';});
        h+='</select></div></div>';
        h+='<table class="hm-table"><thead><tr><th>Assignee</th><th>Reason</th><th>Repeats</th><th>Dates</th><th>Time</th><th style="width:80px"></th></tr></thead><tbody id="hhl-body">';
        if(!this.data.length)h+='<tr><td colspan="6" class="hm-no-data">No holidays or unavailability configured</td></tr>';
        else this.data.forEach(function(ho){
            h+='<tr><td><strong>'+esc(ho.dispenser_name)+'</strong></td><td>'+esc(ho.reason)+'</td><td>'+(ho.repeats==='no'?'—':ho.repeats)+'</td><td>'+ho.start_date+' → '+ho.end_date+'</td><td>'+(ho.start_time||'—')+' – '+(ho.end_time||'—')+'</td><td class="hm-table-acts"><button class="hm-act-btn hm-act-edit hhl-edit" data-id="'+ho._ID+'">✏️</button><button class="hm-act-btn hm-act-del hhl-del" data-id="'+ho._ID+'">🗑️</button></td></tr>';
        });
        h+='</tbody></table></div>';
        this.$el.html(h);
        $(document).on('click','#hhl-add',function(){self.openForm(null);});
        $(document).on('click','.hhl-edit',function(){var id=$(this).data('id');var ho=self.data.find(function(x){return x._ID==id;});if(ho)self.openForm(ho);});
        $(document).on('click','.hhl-del',function(){if(confirm('Delete?'))post('delete_holiday',{_ID:$(this).data('id')}).then(function(){self.load();});});
        $(document).on('change','#hhl-dispF',function(){
            var did=parseInt($(this).val())||0;
            post('get_holidays',{dispenser_id:did}).then(function(r){self.data=r.success?r.data:[];self.render();});
        });
    },
    openForm:function(ho){
        var isEdit=!!ho,self=this;
        var h='<div class="hm-modal-bg open"><div class="hm-modal"><div class="hm-modal-hd"><h3>'+(isEdit?'Edit':'New')+' Holiday / Unavailability</h3><button class="hm-modal-x hhl-close">'+IC.x+'</button></div><div class="hm-modal-body">';
        h+='<div class="hm-fld"><label>Assignee</label><select class="hm-inp" id="hhlf-disp"><option value="">Select...</option>';
        this.dispensers.forEach(function(d){h+='<option value="'+d.id+'"'+(ho&&ho.dispenser_id==d.id?' selected':'')+'>'+esc(d.name)+'</option>';});
        h+='</select></div>';
        h+='<div class="hm-fld"><label>Reason</label><input class="hm-inp" id="hhlf-reason" value="'+esc(ho?ho.reason:'')+'" placeholder="e.g. Annual leave, Pharmacy admin"></div>';
        h+='<div class="hm-row"><div class="hm-fld"><label>Start Date</label><input type="date" class="hm-inp" id="hhlf-sd" value="'+(ho?ho.start_date:'')+'"></div><div class="hm-fld"><label>End Date</label><input type="date" class="hm-inp" id="hhlf-ed" value="'+(ho?ho.end_date:'')+'"></div></div>';
        h+='<div class="hm-row"><div class="hm-fld"><label>Start Time</label><input type="time" class="hm-inp" id="hhlf-st" value="'+(ho?ho.start_time:'09:00')+'"></div><div class="hm-fld"><label>End Time</label><input type="time" class="hm-inp" id="hhlf-et" value="'+(ho?ho.end_time:'17:00')+'"></div></div>';
        h+='<div class="hm-fld"><label>Repeats</label><select class="hm-inp" id="hhlf-rep"><option value="no"'+(ho&&ho.repeats!=='no'?'':' selected')+'>No</option><option value="weekly"'+(ho&&ho.repeats==='weekly'?' selected':'')+'>Weekly</option><option value="monthly"'+(ho&&ho.repeats==='monthly'?' selected':'')+'>Monthly</option><option value="yearly"'+(ho&&ho.repeats==='yearly'?' selected':'')+'>Yearly</option></select></div>';
        h+='</div><div class="hm-modal-ft"><span></span><div class="hm-modal-acts"><button class="hm-btn hhl-close">Cancel</button><button class="hm-btn hm-btn-teal hhl-save" data-id="'+(ho?ho._ID:0)+'">Save</button></div></div></div></div>';
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
