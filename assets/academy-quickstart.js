/* Consent and persistence adapter. Rendering, spotlight, positioning and focus: Moodle User Tours. */
(function () {
    'use strict';
    function init() {
        var node = document.getElementById('academy-quickstart-config');
        if (!node || node.dataset.initialized) { return; }
        node.dataset.initialized = '1';
        var cfg; try { cfg = JSON.parse(node.textContent); } catch (_) { return; }
        var state = cfg.state, chain = Promise.resolve(), current = null, finish = 'skip', trigger = null;
        var failed = false, busy = false, card = document.querySelector('[data-aq-card]');
        var txt = cfg.strings;
        function element(tag, text, attrs) {
            var e = document.createElement(tag); if (text) { e.textContent = text; }
            Object.keys(attrs || {}).forEach(function (key) { e.setAttribute(key, attrs[key]); }); return e;
        }
        if (!card && (cfg.manual || state.status === 'new' || state.status === 'active')) {
            var main = document.querySelector('#workspace-main, .lw-main');
            if (!main) { return; }
            card = element('section', '', {'class':'aq-card aq-context', 'data-aq-card':''});
            var invite = element('div', '', {'data-aq-invite':''});
            invite.appendChild(element('strong', txt.title));
            var actions = element('div', '', {'class':'aq-actions'});
            actions.appendChild(element('button', state.status === 'active' ? txt.resume : txt.start, {'type':'button','class':'aq-link','data-aq-start':''}));
            actions.appendChild(element('button', txt.later, {'type':'button','class':'aq-link','data-aq-skip':''}));
            invite.appendChild(actions); card.appendChild(invite);
            card.appendChild(element('p','',{'class':'aq-status','role':'status','aria-live':'polite','data-aq-status':''}));
            var h1 = main.querySelector('h1'); if (h1 && h1.parentNode === main) { h1.after(card); } else { main.prepend(card); }
        }
        if (!card) { return; }
        var invite = card.querySelector('[data-aq-invite]');
        if (cfg.manual && invite) { invite.hidden = false; var restart = invite.querySelector('[data-aq-start]'); if(restart){restart.textContent=txt.start;} }
        function status(message) { var p = card.querySelector('[data-aq-status]'); if (p) { p.textContent = message; } }
        function updateInvite() {
            if (invite && !['new','active'].includes(state.status)) { invite.hidden = true; }
            var b = card.querySelector('[data-aq-start]'); if (b) { b.textContent = state.status === 'active' ? txt.resume : txt.start; }
        }
        function save(action, step, manual) {
            var result = chain.then(async function () {
                if (failed) { throw new Error('persistence stopped'); }
                var data = new URLSearchParams({sesskey:cfg.sesskey, role:cfg.role, segment:cfg.segment,
                    revision:String(state.revision), action:action, step:String(step || 0), manual:manual ? '1' : '0'});
                var response = await fetch(cfg.endpoint, {method:'POST',credentials:'same-origin',body:data,
                    headers:{'Accept':'application/json'},cache:'no-store'});
                if (!response.ok) { throw new Error('save rejected'); }
                var body = await response.json(); if (!body.ok || !body.state) { throw new Error('save rejected'); }
                state = body.state; return state;
            });
            chain = result.catch(function () { failed = true; status(txt.error); });
            return result;
        }
        function template() {
            var modal = element('div','',{'class':'modal aq-tour'});
            var dialog = element('div','',{'class':'modal-dialog','data-role':'flexitour-step'});
            var content = element('div','',{'class':'modal-content'});
            content.appendChild(element('div','',{'class':'tooltip-arrow','data-role':'arrow'}));
            var header = element('div','',{'class':'modal-header'});
            header.appendChild(element('h2','',{'class':'modal-title','data-placeholder':'title'}));
            header.appendChild(element('button','×',{'type':'button','class':'aq-close','data-aq-end':'','aria-label':txt.close}));
            content.appendChild(header); content.appendChild(element('div','',{'class':'modal-body','data-placeholder':'body'}));
            var footer = element('div','',{'class':'modal-footer'});
            footer.appendChild(element('span','',{'class':'aq-counter','data-aq-counter':''}));
            footer.appendChild(element('button',txt.previous,{'type':'button','class':'btn btn-secondary','data-aq-prev':''}));
            footer.appendChild(element('button',txt.next,{'type':'button','class':'btn btn-primary','data-role':'next'}));
            footer.appendChild(element('button',txt.done,{'type':'button','class':'btn btn-primary','data-aq-done':'','hidden':''}));
            content.appendChild(footer); dialog.appendChild(content); modal.appendChild(dialog); return modal.outerHTML;
        }
        function forOurTour(event) { return current && event.detail && event.detail.tour === current; }
        document.addEventListener('tool_usertours/stepRendered', function (event) {
            if (!forOurTour(event)) { return; }
            var i = current.getCurrentStepNumber(), step = current.getStepConfig(i);
            document.querySelectorAll('.aq-tour').forEach(function (popup) {
                var counter = popup.querySelector('[data-aq-counter]'); if (counter) { counter.textContent = txt.progress.replace('{current}',String(i + 1)).replace('{total}',String(current.steps.length)); }
                var prev = popup.querySelector('[data-aq-prev]'); if (prev) { prev.disabled = i === 0; }
                var next = popup.querySelector('[data-role="next"]'); if (next) { next.textContent = i === current.steps.length - 1 ? txt.done : txt.next; }
                var done = popup.querySelector('[data-aq-done]'); if (done) { done.hidden = i !== current.steps.length - 1; }
            });
            save('move', step.sourceindex).catch(function () {});
        });
        document.addEventListener('tool_usertours/tourEnded', function (event) {
            if (!forOurTour(event)) { return; }
            var step = current.getStepConfig(current.getCurrentStepNumber());
            current = null; busy = false;
            save(finish, step ? step.sourceindex : 0).then(function () {
                status(finish === 'complete' ? txt.finished : txt.skipped); updateInvite();
            }).catch(function () {});
            document.body.classList.remove('aq-tour-running');
            if (trigger && trigger.isConnected && trigger.getClientRects().length) { trigger.focus(); }
        });
        document.addEventListener('click', function (event) {
            if (!current) { return; }
            var prev = event.target.closest('.aq-tour [data-aq-prev]');
            if (prev) { event.preventDefault(); current.previous(); return; }
            if (event.target.closest('.aq-tour [data-role="next"]')) { finish = current.getCurrentStepNumber() === current.steps.length - 1 ? 'complete' : 'skip'; }
            if (event.target.closest('.aq-tour [data-aq-end]')) { finish = 'skip'; current.endTour(); }
            if (event.target.closest('.aq-tour [data-aq-done]')) { finish = 'complete'; current.endTour(); }
        }, true);
        document.addEventListener('keydown', function (event) { if (current && event.key === 'Escape') { finish = 'skip'; } }, true);
        card.addEventListener('click', async function (event) {
            var skip = event.target.closest('[data-aq-skip]'), start = event.target.closest('[data-aq-start]');
            if ((!skip && !start) || busy) { return; }
            busy = true; trigger = start || skip; trigger.disabled = true; status('');
            try {
                if (skip) { await save('skip',state.step); updateInvite(); status(txt.skipped); return; }
                var steps = cfg.tour.steps.map(function (s, i) {
                    return Object.assign({},s,{target:s.element,body:s.content,sourceindex:i,moveOnClick:false});
                }).filter(function (s) {
                    try { var e = document.querySelector(s.target); return !!e && e.getClientRects().length > 0; } catch (_) { return false; }
                });
                if (!steps.length) { status(txt.unavailable); return; }
                // Load the actual Moodle renderer, not a second tooltip engine or an external CDN.
                var NativeTour = await new Promise(function (resolve,reject) { require(['tool_usertours/tour'],function (mod) { resolve(mod.default || mod); },reject); });
                await save('start',state.step,cfg.manual || ['skipped','completed'].includes(state.status));
                finish = 'skip';
                current = new NativeTour({tourName:'stars-'+cfg.role+'-'+cfg.segment+'-v1',template:template(),steps:steps,displaystepnumbers:false});
                document.body.classList.add('aq-tour-running');
                var at = steps.findIndex(function (s) { return s.sourceindex >= state.step; });
                await current.startTour(Math.max(0,at));
            } catch (_) { if (!failed) { status(txt.unavailable); } }
            finally { busy = false; trigger.disabled = false; }
        });
    }
    if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded',init); } else { init(); }
})();
