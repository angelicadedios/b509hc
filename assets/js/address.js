(function () {
    'use strict';

    const API = 'https://psgc.gitlab.io/api';
    const cache = {};

    function getJSON(path) {
        if (!cache[path]) {
            cache[path] = fetch(API + path).then(r => {
                if (!r.ok) throw new Error('HTTP ' + r.status);
                return r.json();
            });
            cache[path].catch(() => { delete cache[path]; });
        }
        return cache[path];
    }
    const byName = (a, b) => a.name.localeCompare(b.name, undefined, { numeric: true });
    const regionLabel = r => (r.regionName && r.regionName !== r.name) ? r.regionName + ' - ' + r.name : r.name;
    const plain = x => x.name;

    function AddressPicker(group) {
        this.g = group;
        this.token = 0;
        this.fallen = false;
        this.s = {};
        ['region', 'province', 'municipality', 'barangay'].forEach(k => { this.s[k] = group.querySelector('[data-addr="' + k + '"]'); });
        this.bind();
        this.set({
            region: group.dataset.region, province: group.dataset.province,
            municipality: group.dataset.municipality, barangay: group.dataset.barangay
        });
    }

    AddressPicker.prototype.fill = function (select, items, label, placeholder, wanted) {
        select.innerHTML = '';
        select.add(new Option(placeholder, ''));
        items.forEach(it => {
            const text = label(it);
            const o = new Option(text, text);
            o.dataset.code = it.code;
            select.add(o);
        });
        if (wanted) select.value = wanted;
        if (select.value !== (wanted || '')) select.value = '';
        const opt = select.options[select.selectedIndex];
        return opt && opt.dataset.code ? opt.dataset.code : '';
    };

    AddressPicker.prototype.clear = function (keys, text) {
        keys.forEach(k => { this.s[k].innerHTML = ''; this.s[k].add(new Option(text || '-- Select --', '')); });
    };

    AddressPicker.prototype.set = function (w) {
        w = w || {};
        if (this.fallen) {
            ['region', 'province', 'municipality', 'barangay'].forEach(k => { this.s[k].value = w[k] || ''; });
            return;
        }
        const t = ++this.token;
        this.clear(['region'], 'Loading regions...');
        getJSON('/regions/')
            .then(regions => {
                if (t !== this.token) return;
                const code = this.fill(this.s.region, regions.slice().sort(byName), regionLabel, '-- Select region --', w.region);
                return this.afterRegion(code, w, t);
            })
            .catch(() => this.fallback(w));
    };

    AddressPicker.prototype.afterRegion = function (code, w, t) {
        this.clear(['province', 'municipality', 'barangay']);
        if (!code) return Promise.resolve();
        this.clear(['province'], 'Loading...');
        return getJSON('/regions/' + code + '/provinces/').then(provs => {
            if (t !== this.token) return;
            if (provs.length === 0) {

                this.s.province.innerHTML = '';
                this.s.province.add(new Option('Metro Manila', 'Metro Manila'));
                this.s.province.value = 'Metro Manila';
                return getJSON('/regions/' + code + '/cities-municipalities/').then(cities => {
                    if (t !== this.token) return;
                    const c = this.fill(this.s.municipality, cities.slice().sort(byName), plain, '-- Select city --', w.municipality);
                    return this.afterCity(c, w, t);
                });
            }
            const p = this.fill(this.s.province, provs.slice().sort(byName), plain, '-- Select province --', w.province);
            return this.afterProvince(p, w, t);
        });
    };

    AddressPicker.prototype.afterProvince = function (code, w, t) {
        this.clear(['municipality', 'barangay']);
        if (!code) return Promise.resolve();
        this.clear(['municipality'], 'Loading...');
        return getJSON('/provinces/' + code + '/cities-municipalities/').then(list => {
            if (t !== this.token) return;
            const c = this.fill(this.s.municipality, list.slice().sort(byName), plain, '-- Select city / municipality --', w.municipality);
            return this.afterCity(c, w, t);
        });
    };

    AddressPicker.prototype.afterCity = function (code, w, t) {
        this.clear(['barangay']);
        if (!code) return Promise.resolve();
        this.clear(['barangay'], 'Loading...');
        return getJSON('/cities-municipalities/' + code + '/barangays/').then(list => {
            if (t !== this.token) return;
            if (!list.length) throw new Error('no barangays');
            this.fill(this.s.barangay, list.slice().sort(byName), plain, '-- Select barangay --', w.barangay);
        });
    };

    AddressPicker.prototype.code = function (k) {
        const o = this.s[k].options[this.s[k].selectedIndex];
        return o && o.dataset.code ? o.dataset.code : '';
    };

    AddressPicker.prototype.bind = function () {
        const fail = () => this.fallback({
            region: this.s.region.value, province: this.s.province.value,
            municipality: this.s.municipality.value, barangay: ''
        });
        this.s.region.addEventListener('change', () => { const t = ++this.token; this.afterRegion(this.code('region'), {}, t).catch(fail); });
        this.s.province.addEventListener('change', () => { const t = ++this.token; this.afterProvince(this.code('province'), {}, t).catch(fail); });
        this.s.municipality.addEventListener('change', () => { const t = ++this.token; this.afterCity(this.code('municipality'), {}, t).catch(fail); });
    };

    AddressPicker.prototype.fallback = function (w) {
        if (this.fallen) return;
        this.fallen = true;
        const placeholders = { region: 'e.g. NCR', province: 'e.g. Metro Manila', municipality: 'e.g. City of Manila', barangay: 'e.g. Barangay 509' };
        ['region', 'province', 'municipality', 'barangay'].forEach(k => {
            const sel = this.s[k];
            const inp = document.createElement('input');
            inp.type = 'text';
            inp.name = sel.name;
            inp.maxLength = 100;
            inp.placeholder = placeholders[k];
            inp.value = (w && w[k]) || '';
            inp.dataset.validate = 'required|max:100';
            inp.dataset.addr = k;
            inp.className = sel.className;
            sel.replaceWith(inp);
            this.s[k] = inp;
        });
        const note = document.createElement('small');
        note.className = 'hint';
        note.textContent = 'The address list could not be loaded, please type your address instead.';
        this.g.appendChild(note);
    };

    document.querySelectorAll('.address-group').forEach(g => { g._picker = new AddressPicker(g); });
})();
