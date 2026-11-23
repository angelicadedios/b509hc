(function () {
    'use strict';

    const MONTHS = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
    const pad = n => String(n).padStart(2, '0');
    const ymd = d => d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate());

    function setOptions(select, placeholder, slots) {
        select.innerHTML = '';
        select.add(new Option(placeholder, ''));
        (slots || []).forEach(s => {
            const o = new Option(s.label + (s.available ? '' : '  (not available)'), s.time);
            o.disabled = !s.available;
            select.add(o);
        });
        renderSlotButtons(select, placeholder, slots);
    }

    function renderSlotButtons(select, message, slots) {
        const grid = select._grid;
        if (!grid) return;
        grid.innerHTML = '';
        if (!slots || !slots.length) {
            const p = document.createElement('p');
            p.className = 'slot-msg';
            p.textContent = message;
            grid.appendChild(p);
            return;
        }
        const groups = [['Morning', slots.filter(s => s.time < '12:00')], ['Afternoon', slots.filter(s => s.time >= '12:00')]];
        groups.forEach(([title, list]) => {
            if (!list.length) return;
            const h = document.createElement('h4');
            h.className = 'slot-group';
            h.textContent = title;
            const wrap = document.createElement('div');
            wrap.className = 'slot-buttons';
            wrap.setAttribute('role', 'group');
            wrap.setAttribute('aria-label', title + ' time slots');
            list.forEach(s => {
                const b = document.createElement('button');
                b.type = 'button';
                b.className = 'slot-btn' + (s.available ? '' : ' taken') + (select.value === s.time ? ' on' : '');
                b.disabled = !s.available;
                b.textContent = s.label.split(' – ')[0];
                b.setAttribute('aria-pressed', select.value === s.time ? 'true' : 'false');
                b.setAttribute('aria-label', s.label + (s.available ? '' : ', not available'));
                b.addEventListener('click', () => {
                    select.value = s.time;
                    select.dispatchEvent(new Event('change', { bubbles: true }));
                });
                wrap.appendChild(b);
            });
            grid.append(h, wrap);
        });
        if (!slots.some(s => s.available)) {
            const p = document.createElement('p');
            p.className = 'slot-msg';
            p.textContent = 'All slots on this date are taken. Please choose another day.';
            grid.prepend(p);
        }
    }

    function loadSlots(select, date) {
        setOptions(select, 'Loading time slots...');
        fetch((window.APP_BASE || '') + '/ajax/slots.php?date=' + encodeURIComponent(date), { credentials: 'same-origin' })
            .then(r => r.json())
            .then(d => {
                if (!d.ok) { setOptions(select, d.error || 'No slots'); return; }
                const free = d.slots.filter(s => s.available);
                const want = select.dataset.selected;
                select.dataset.selected = '';
                setOptions(select, free.length ? '-- Select time slot --' : 'No available slots on this date', d.slots);
                if (want && free.some(s => s.time === want)) {
                    select.value = want;
                    select.dispatchEvent(new Event('change', { bubbles: true }));
                }
                select.dispatchEvent(new Event('slotsloaded'));
            })
            .catch(() => setOptions(select, 'Could not load slots. Please try again.'));
    }

    document.querySelectorAll('select[data-slots-for]').forEach(select => {
        const input = document.getElementById(select.dataset.slotsFor);
        if (!input) return;

        const grid = document.createElement('div');
        grid.className = 'slot-grid';
        select.after(grid);
        select.classList.add('native-hidden');
        select._grid = grid;
        select._proxy = grid;
        renderSlotButtons(select, 'Pick a date first to see the open time slots.', null);
        select.addEventListener('change', () => {
            grid.querySelectorAll('.slot-btn').forEach(b => {
                const on = b.getAttribute('aria-label').indexOf(slotLabelFor(select)) === 0 && select.value !== '';
                b.classList.toggle('on', on);
                b.setAttribute('aria-pressed', on ? 'true' : 'false');
            });
        });
        input.addEventListener('change', () => {
            if (input.value) loadSlots(select, input.value);
            else setOptions(select, 'Pick a date first to see the open time slots.');
        });
    });

    function slotLabelFor(select) {
        const o = select.options[select.selectedIndex];
        return o ? o.text.replace('  (not available)', '') : '';
    }

    const monthCache = {};
    function monthAvailability(ym) {
        if (!monthCache[ym]) {
            monthCache[ym] = fetch((window.APP_BASE || '') + '/ajax/slots.php?month=' + ym, { credentials: 'same-origin' })
                .then(r => r.json()).then(d => (d.ok ? d.days : {})).catch(() => ({}));
        }
        return monthCache[ym];
    }

    function DatePicker(el) {
        this.el = el;
        this.input = document.getElementById(el.dataset.input);
        this.min = el.dataset.min;
        this.max = el.dataset.max;
        const start = this.input.value || this.min;
        this.view = new Date(start + 'T00:00:00');
        this.view.setDate(1);
        this.render();
        if (this.input.value) this.input.dispatchEvent(new Event('change', { bubbles: true }));

        const form = el.closest('form');
        if (form) form.addEventListener('reset', () => {
            this.input.value = '';
            this.view = new Date(this.min + 'T00:00:00');
            this.view.setDate(1);
            this.render();
            this.input.dispatchEvent(new Event('change', { bubbles: true }));
        });
    }

    DatePicker.prototype.render = function () {
        const y = this.view.getFullYear(), m = this.view.getMonth();
        const minMonth = this.min.slice(0, 7), maxMonth = this.max.slice(0, 7);
        const thisMonth = y + '-' + pad(m + 1);
        const el = this.el;
        el.innerHTML = '';

        const head = document.createElement('div');
        head.className = 'dp-head';
        const prev = document.createElement('button');
        prev.type = 'button'; prev.className = 'dp-nav'; prev.innerHTML = '&lsaquo;'; prev.setAttribute('aria-label', 'Previous month');
        prev.disabled = thisMonth <= minMonth;
        const next = document.createElement('button');
        next.type = 'button'; next.className = 'dp-nav'; next.innerHTML = '&rsaquo;'; next.setAttribute('aria-label', 'Next month');
        next.disabled = thisMonth >= maxMonth;
        const title = document.createElement('strong');
        title.textContent = MONTHS[m] + ' ' + y;
        prev.onclick = () => { this.view.setMonth(m - 1); this.render(); };
        next.onclick = () => { this.view.setMonth(m + 1); this.render(); };
        head.append(prev, title, next);
        el.appendChild(head);

        const grid = document.createElement('div');
        grid.className = 'dp-grid';
        ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'].forEach(w => {
            const c = document.createElement('span'); c.className = 'dp-wd'; c.textContent = w; grid.appendChild(c);
        });
        const firstDow = new Date(y, m, 1).getDay();
        for (let i = 0; i < firstDow; i++) grid.appendChild(document.createElement('span'));
        const days = new Date(y, m + 1, 0).getDate();
        const today = ymd(new Date());
        for (let d = 1; d <= days; d++) {
            const date = new Date(y, m, d);
            const val = ymd(date);
            const b = document.createElement('button');
            b.type = 'button';
            b.className = 'dp-day';
            b.textContent = d;
            b.dataset.date = val;
            const sunday = date.getDay() === 0;
            b.disabled = sunday || val < this.min || val > this.max;
            if (sunday) { b.classList.add('sun'); b.title = 'Closed on Sundays'; }
            if (val === today) b.classList.add('today');
            if (val === this.input.value) b.classList.add('selected');
            b.onclick = () => {
                this.input.value = val;
                this.render();
                this.input.dispatchEvent(new Event('change', { bubbles: true }));
            };
            grid.appendChild(b);
        }
        el.appendChild(grid);

        monthAvailability(thisMonth).then(days => {
            grid.querySelectorAll('.dp-day').forEach(b => {
                const free = days[b.dataset.date];
                if (free === undefined || b.disabled) return;
                const dot = document.createElement('span');
                dot.className = 'dp-dot ' + (free === 0 ? 'full' : free < 8 ? 'few' : 'many');
                b.appendChild(dot);
                if (free === 0 && b.dataset.date !== this.input.value) { b.disabled = true; b.title = 'Fully booked'; }
            });
        });

        const legend = document.createElement('div');
        legend.className = 'dp-legend';
        legend.innerHTML = '<span><i class="dp-dot many"></i>Many slots</span><span><i class="dp-dot few"></i>Few left</span><span><i class="dp-dot full"></i>Full</span>';
        el.appendChild(legend);

        const foot = document.createElement('div');
        foot.className = 'dp-foot';
        foot.textContent = this.input.value
            ? 'Selected: ' + new Date(this.input.value + 'T00:00:00').toLocaleDateString('en-PH', { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' })
            : 'No date selected';
        el.appendChild(foot);
    };

    document.querySelectorAll('.datepicker').forEach(el => { el._picker = new DatePicker(el); });
})();
