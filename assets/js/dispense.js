/* "Medicine given" rows on the staff appointment popup */
(function () {
    'use strict';
    const tpl = document.getElementById('dispRowTpl');
    if (!tpl) return;
    const stock = JSON.parse((document.getElementById('medStock') || {}).textContent || '{}');
    let pending = null;

    function renumber(form) {
        form.querySelectorAll('.disp-row').forEach((row, i) => {
            row.querySelectorAll('[data-name]').forEach(el => { el.name = 'meds[' + i + '][' + el.dataset.name + ']'; });
        });
        form.querySelector('[data-disp-submit]').hidden = !form.querySelector('.disp-row');
    }

    function addRow(form) {
        const rows = form.querySelector('.disp-rows');
        const prev = rows.lastElementChild;
        const row = tpl.content.firstElementChild.cloneNode(true);
        rows.appendChild(row);
        // Same doctor as the previous row, to save clicks
        if (prev) row.querySelector('[data-name=doctor_id]').value = prev.querySelector('[data-name=doctor_id]').value;
        renumber(form);
        row.querySelector('[data-name=name]').focus();
    }

    function check(row) {
        const name = row.querySelector('[data-name=name]');
        const qty = row.querySelector('[data-name=qty]');
        const doc = row.querySelector('[data-name=doctor_id]');
        let ok = true;
        const set = (el, msg) => { window.App.setError(el, msg); if (msg) ok = false; };

        const n = name.value.trim();
        if (!n) set(name, 'Choose a medicine.');
        else if (!(n in stock)) set(name, 'Pick a medicine from the list.');
        else set(name, '');

        const q = qty.value.trim();
        if (!/^\d{1,6}$/.test(q) || +q < 1) set(qty, 'Whole number from 1.');
        else if (n in stock && +q > +stock[n]) set(qty, 'Only ' + stock[n] + ' in stock.');
        else set(qty, '');

        set(doc, doc.value ? '' : 'Choose the doctor.');
        return ok;
    }

    document.addEventListener('click', e => {
        const add = e.target.closest('[data-add-row]');
        if (add) { addRow(add.closest('form')); return; }
        const rm = e.target.closest('.disp-remove');
        if (rm) { const form = rm.closest('form'); rm.closest('.disp-row').remove(); renumber(form); }
    });

    document.querySelectorAll('.dispense-form').forEach(form => {
        form.addEventListener('submit', e => {
            e.preventDefault();
            const rows = Array.from(form.querySelectorAll('.disp-row'));
            if (!rows.length) return;
            let ok = true;
            rows.forEach(r => { if (!check(r)) ok = false; });
            // The same medicine twice must still fit the stock
            const totals = {};
            rows.forEach(r => {
                const n = r.querySelector('[data-name=name]').value.trim();
                totals[n] = (totals[n] || 0) + (+r.querySelector('[data-name=qty]').value || 0);
            });
            Object.keys(totals).forEach(n => {
                if (n in stock && totals[n] > +stock[n]) {
                    ok = false;
                    rows.filter(r => r.querySelector('[data-name=name]').value.trim() === n)
                        .forEach(r => window.App.setError(r.querySelector('[data-name=qty]'), 'Total for this medicine is more than the ' + stock[n] + ' in stock.'));
                }
            });
            if (!ok) return;

            const tbody = document.getElementById('dispSummary');
            tbody.innerHTML = '';
            rows.forEach(r => {
                const tr = document.createElement('tr');
                const doc = r.querySelector('[data-name=doctor_id]');
                [r.querySelector('[data-name=name]').value, r.querySelector('[data-name=qty]').value, doc.options[doc.selectedIndex].text]
                    .forEach((t, i) => { const td = document.createElement('td'); td.textContent = t; if (i === 1) td.className = 'right mono'; tr.appendChild(td); });
                tbody.appendChild(tr);
            });
            pending = form;
            window.App.openModal('dispenseConfirm', { patient: form.dataset.patient });
        });
    });

    document.getElementById('dispYes').addEventListener('click', function () {
        if (!pending) return;
        this.disabled = true;
        this.textContent = 'Saving...';
        pending.submit();
    });
})();
