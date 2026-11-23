(function () {
    'use strict';

    const form = document.getElementById('bookForm');
    if (!form) return;

    const steps = Array.from(form.querySelectorAll('.step'));
    const stepper = Array.from(document.querySelectorAll('#stepper li'));
    const prefill = JSON.parse(document.getElementById('prefillData').textContent || '{}');
    const depPicker = document.getElementById('dependentPicker');
    const depSelect = document.getElementById('dependent_id');
    const relRow = document.getElementById('relationshipRow');
    const priority = document.getElementById('priority');
    const idWrap = document.getElementById('idUploadWrap');
    const mobile = document.getElementById('mobile');
    const mobileMirror = document.getElementById('mobileMirror');
    const PATIENT_FIELDS = ['relationship', 'philhealth_no', 'first_name', 'middle_name', 'last_name', 'suffix', 'sex', 'birth_date', 'mobile',
        'district', 'zone', 'street'];
    let current = parseInt(form.dataset.startStep, 10) || 1;

    function show(n) {
        current = n;
        steps.forEach(s => { s.hidden = +s.dataset.step !== n; });
        stepper.forEach(li => {
            const k = +li.dataset.step;
            li.classList.toggle('active', k === n);
            li.classList.toggle('done', k < n);
        });
        if (n === 4) buildReview();
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    const bookedFor = () => (form.querySelector('input[name=booked_for]:checked') || {}).value || '';

    function toggleWho() {
        const dep = bookedFor() === 'dependent';
        depPicker.hidden = !dep;
        if (relRow) relRow.hidden = !dep;
    }

    function fillPatient(data) {
        data = data || {};
        PATIENT_FIELDS.forEach(k => {
            const el = form.elements[k];
            if (el) { el.value = data[k] || ''; window.App.setError(el, ''); }
        });
        const group = form.querySelector('.address-group');
        if (group && group._picker) {
            group._picker.set({ region: data.region, province: data.province, municipality: data.municipality, barangay: data.barangay });
        }
        mobileMirror.value = mobile.value;
    }

    form.querySelectorAll('input[name=booked_for]').forEach(r => r.addEventListener('change', () => {
        toggleWho();
        if (bookedFor() === 'self') fillPatient(prefill.self);
        else { depSelect.value = ''; fillPatient({}); }
    }));

    depSelect.addEventListener('change', () => {
        const v = depSelect.value;
        if (v === 'new' || v === '') fillPatient({});
        else fillPatient(prefill.deps[v]);
    });

    function toggleIdUpload() {
        idWrap.hidden = !['Senior Citizen', 'PWD'].includes(priority.value);
    }
    priority.addEventListener('change', toggleIdUpload);
    mobile.addEventListener('input', () => { mobileMirror.value = mobile.value; });

    function crossChecks(n) {
        if (n === 1 && !bookedFor()) {
            alertBox(steps[0], 'Please choose who this booking is for.');
            return false;
        }
        if (n === 3) {
            const bd = form.elements.birth_date.value, sex = form.elements.sex.value;
            if (priority.value === 'Senior Citizen' && bd && window.App.ageOf(bd) < 60) {
                window.App.setError(priority, 'Senior Citizen priority is only for patients 60 years old and above.');
                return false;
            }
            if (priority.value === 'Pregnant' && sex !== 'Female') {
                window.App.setError(priority, 'Pregnant priority is only for female patients.');
                return false;
            }
            window.App.setError(priority, '');
        }
        return true;
    }

    function alertBox(section, msg) {
        let box = section.querySelector('.field-error.step-msg');
        if (!box) {
            box = document.createElement('small');
            box.className = 'field-error step-msg';
            section.querySelector('.choice-grid').after(box);
        }
        box.textContent = msg;
    }

    function stepValid(n) {
        const section = steps[n - 1];
        return window.App.validateContainer(section) && crossChecks(n);
    }

    form.addEventListener('click', e => {
        if (e.target.closest('[data-next]')) {
            if (stepValid(current)) show(current + 1);
        } else if (e.target.closest('[data-prev]')) {
            show(current - 1);
        }
    });

    form.addEventListener('keydown', e => {
        if (e.key === 'Enter' && e.target.tagName !== 'TEXTAREA') e.preventDefault();
    });

    document.getElementById('finalSubmit').addEventListener('click', function () {
        for (let n = 1; n <= 3; n++) {
            if (!stepValid(n)) {
                window.App.closeModal(document.getElementById('confirmModal'));
                show(n);
                return;
            }
        }
        this.disabled = true;
        this.textContent = 'Booking...';
        form.submit();
    });

    function val(name) { const el = form.elements[name]; return el ? (el.value || '').trim() : ''; }
    function selectedText(name) { const el = form.elements[name]; return el && el.selectedIndex > 0 ? el.options[el.selectedIndex].text : ''; }
    function prettyDate(v) {
        return v ? new Date(v + 'T00:00:00').toLocaleDateString('en-PH', { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' }) : '';
    }

    function buildReview() {
        const box = document.getElementById('reviewBox');
        box.innerHTML = '';
        const name = [val('first_name'), val('middle_name'), val('last_name'), val('suffix')].filter(Boolean).join(' ');
        const address = [val('street'), val('zone') ? 'Zone ' + val('zone') : '', val('barangay'), val('district'),
            val('municipality'), val('province'), val('region')].filter(Boolean).join(', ');
        const resultFile = form.elements.result_file.files[0];
        const idFile = form.elements.id_file.files[0];

        const sections = [
            ['Booking for', [
                ['For', bookedFor() === 'self' ? 'Myself' : 'Someone else (' + val('relationship') + ')']
            ]],
            ['Patient information', [
                ['Full name', name],
                ...(bookedFor() === 'dependent' ? [['PhilHealth / YAKAP no.', val('philhealth_no') || 'None (uses your number + .D#)']] : []),
                ['Sex', val('sex')],
                ['Birth date', prettyDate(val('birth_date')) + (val('birth_date') ? ' (' + window.App.ageOf(val('birth_date')) + ' yrs old)' : '')],
                ['Mobile number', val('mobile')],
                ['Address', address]
            ]],
            ['Booking details', [
                ['Visit type', val('visit_type')],
                ['Service', val('service')],
                ['Date', prettyDate(val('appt_date'))],
                ['Time slot', selectedText('slot_time')],
                ['Priority', val('priority') || 'Regular'],
                ['Document', resultFile ? resultFile.name : 'None'],
                ...(idWrap.hidden ? [] : [['Senior/PWD ID', idFile ? idFile.name : 'None (bring it on your visit)']]),
                ['Notes', val('notes') || 'None']
            ]]
        ];

        sections.forEach(([title, rows]) => {
            const sec = document.createElement('div');
            sec.className = 'review-section';
            const h = document.createElement('h3');
            h.textContent = title;
            const dl = document.createElement('dl');
            dl.className = 'summary-list compact';
            rows.forEach(([k, v]) => {
                const dt = document.createElement('dt'); dt.textContent = k;
                const dd = document.createElement('dd'); dd.textContent = v;
                dl.append(dt, dd);
            });
            sec.append(h, dl);
            box.appendChild(sec);
        });
    }

    const selSummary = document.getElementById('selSummary');
    function updateSelection() {
        if (!selSummary) return;
        const d = val('appt_date'), slot = selectedText('slot_time').replace('  (not available)', '');
        if (!d) { selSummary.textContent = 'Pick a date and time slot'; return; }
        const day = new Date(d + 'T00:00:00').toLocaleDateString('en-PH', { weekday: 'short', month: 'short', day: 'numeric' });
        selSummary.textContent = day + (slot ? ', ' + slot : ', pick a time slot') + ', ' + (val('priority') || 'Regular');
    }
    ['appt_date', 'slot_time', 'priority'].forEach(n => form.elements[n].addEventListener('change', updateSelection));
    document.getElementById('slot_time').addEventListener('slotsloaded', updateSelection);

    toggleWho();
    toggleIdUpload();
    updateSelection();
    show(current);
})();
