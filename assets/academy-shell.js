/* Progressive enhancement only: no storage, requests, grading or auth data. */
(function () {
    'use strict';
    function init() {
        function revealCurrentStep() {
            document.querySelectorAll('.lw-stepnav').forEach(function (nav) {
                var current = nav.querySelector('[aria-current="step"], [aria-current="page"]');
                if (!current || nav.scrollWidth <= nav.clientWidth) { return; }
                var frame = nav.getBoundingClientRect();
                var item = current.getBoundingClientRect();
                // Move only the horizontal rail, never the page or keyboard focus.
                if (item.left < frame.left) { nav.scrollLeft += item.left - frame.left; }
                else if (item.right > frame.right) { nav.scrollLeft += item.right - frame.right; }
            });
        }
        revealCurrentStep();
        window.addEventListener('resize', revealCurrentStep);
        document.querySelectorAll('form[data-ac-final-form], form#ac-day-current-test').forEach(function (form) {
            var fields = Array.from(form.querySelectorAll('textarea:not([readonly]):not([disabled]), input[type="radio"]:not([disabled])'));
            if (!fields.length) { return; }
            var hint = form.querySelector('[data-ac-unsaved-hint]');
            function snapshot() {
                return JSON.stringify(fields.map(function (field) {
                    return field.type === 'radio' ? field.checked : field.value;
                }));
            }
            var baseline = snapshot();
            var rejected = form.getAttribute('data-ac-unsaved') === 'true';
            var dirty = false;
            var submitting = false;
            function warn(event) {
                if (!dirty || submitting) { return; }
                event.preventDefault();
                event.returnValue = '';
            }
            function update() {
                var changed = rejected || snapshot() !== baseline;
                if (changed !== dirty) {
                    dirty = changed;
                    if (dirty) { window.addEventListener('beforeunload', warn); }
                    else { window.removeEventListener('beforeunload', warn); }
                }
                if (hint) { hint.hidden = !dirty; }
            }
            function edited() { submitting = false; update(); }
            form.addEventListener('input', edited);
            form.addEventListener('change', edited);
            // Native validation fires before submit; an invalid exam stays guarded.
            form.addEventListener('submit', function (event) {
                if (!event.defaultPrevented) { submitting = true; }
            });
            window.addEventListener('pageshow', function () { submitting = false; update(); });
            update();
        });
    }
    if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', init); }
    else { init(); }
})();
