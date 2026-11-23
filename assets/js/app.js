(function () {
    'use strict';

    const NAME_RE = /^[\p{L} .'\-]{2,50}$/u;

    function pad(n) { return String(n).padStart(2, '0'); }
    function today() {
        const d = new Date();
        return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate());
    }
    function ageOf(ymd) {
        const b = new Date(ymd + 'T00:00:00'), n = new Date();
        let age = n.getFullYear() - b.getFullYear();
        if (n.getMonth() < b.getMonth() || (n.getMonth() === b.getMonth() && n.getDate() < b.getDate())) age--;
        return age;
    }

    const RULES = {
        required: (el, v) => {
            if (el.type === 'file') return el.files && el.files.length ? null : 'Please choose a file.';
            return v !== '' ? null : (el.tagName === 'SELECT' || el.type === 'hidden' ? 'Please make a selection.' : 'This field is required.');
        },
        name: (el, v) => v === '' || NAME_RE.test(v) ? null : "Use letters, spaces, . ' - only (2-50 characters).",
        email: (el, v) => v === '' || /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(v) ? null : 'Enter a valid email address.',
        mobile: (el, v) => v === '' || /^09\d{9}$/.test(v) ? null : 'Enter an 11-digit number starting with 09 (e.g. 09171234567).',
        min: (el, v, n) => v === '' || v.length >= +n ? null : 'Must be at least ' + n + ' characters.',
        max: (el, v, n) => v.length <= +n ? null : 'Must be ' + n + ' characters or less.',
        zone: (el, v) => v === '' || /^[A-Za-z0-9 \-]{1,20}$/.test(v) ? null : 'Letters, numbers and dashes only (max 20).',
        birthdate: (el, v) => {
            if (v === '') return null;
            if (!/^\d{4}-\d{2}-\d{2}$/.test(v)) return 'Invalid date.';
            if (v > today()) return 'Birth date cannot be in the future.';
            if (ageOf(v) > 120) return 'Please check the birth year.';
            return null;
        },
        bookdate: (el, v) => {
            if (v === '') return null;
            if (!/^\d{4}-\d{2}-\d{2}$/.test(v)) return 'Invalid date.';
            if (v < today()) return 'Date cannot be in the past.';
            if (new Date(v + 'T00:00:00').getDay() === 0) return 'The health center is closed on Sundays.';
            return null;
        },
        philhealth: (el, v) => v === '' || /^\d{2}-\d{9}-\d$/.test(v) ? null : 'Enter the 12-digit number, e.g. 12-345678901-2.',
        posint: (el, v) => v === '' || (/^\d{1,6}$/.test(v) && +v >= 1 && +v <= 100000) ? null : 'Whole number from 1 to 100,000 only.',
        wholenum: (el, v) => v === '' || (/^\d{1,6}$/.test(v) && +v <= 100000) ? null : 'Whole number from 0 to 100,000 only.',
        password: (el, v) => v === '' || (v.length >= 8 && /[A-Z]/.test(v) && /[a-z]/.test(v) && /\d/.test(v))
            ? null : 'At least 8 characters with an uppercase letter, a lowercase letter and a number.',
        same: (el, v, id) => { const o = document.getElementById(id); return !o || o.value === v ? null : 'Passwords do not match.'; },
        after: (el, v, id) => { const o = document.getElementById(id); return !v || !o || !o.value || v >= o.value ? null : '"To" date must be on or after the "From" date.'; },
        file: (el, v, exts) => {
            const f = el.files && el.files[0];
            if (!f) return null;
            const ext = f.name.split('.').pop().toLowerCase();
            return exts.split(',').includes(ext) ? null : 'Allowed file types: ' + exts.toUpperCase().split(',').join(', ') + '.';
        },
        maxmb: (el, v, mb) => { const f = el.files && el.files[0]; return !f || f.size <= mb * 1048576 ? null : 'File is too large (max ' + mb + ' MB).'; }
    };

    function isSkipped(el, root) {
        if (el.disabled) return true;
        let n = el.parentElement;
        while (n && n !== root) {
            if (n.hidden) return true;
            n = n.parentElement;
        }
        return false;
    }

    function errorBox(el) {
        const field = el.closest('.field') || el.parentElement;
        let box = field.querySelector('.field-error');
        if (!box) {
            box = document.createElement('small');
            box.className = 'field-error';
            field.appendChild(box);
        }
        return box;
    }

    function setError(el, msg) {
        el.classList.toggle('invalid', !!msg);
        errorBox(el).textContent = msg || '';
        const picker = el.type === 'hidden' ? document.querySelector('.datepicker[data-input="' + el.id + '"]') : null;
        if (picker) picker.classList.toggle('invalid', !!msg);
    }

    function validateField(el) {
        const v = (el.value || '').trim();
        const rules = (el.dataset.validate || '').split('|').filter(Boolean);
        for (const r of rules) {
            const [name, param] = r.split(':');
            const fn = RULES[name];
            if (!fn) continue;
            const msg = fn(el, v, param);
            if (msg) { setError(el, msg); return false; }
        }
        setError(el, '');
        return true;
    }

    function validateContainer(root) {
        let ok = true, first = null;
        root.querySelectorAll('[data-validate]').forEach(el => {
            if (isSkipped(el, root)) return;
            if (!validateField(el)) { ok = false; first = first || el; }
        });
        if (first) {
            const target = first._proxy || (first.type === 'hidden' ? document.querySelector('.datepicker[data-input="' + first.id + '"]') : first);
            if (target) { target.scrollIntoView({ behavior: 'smooth', block: 'center' }); if (target.focus) target.focus({ preventScroll: true }); }
        }
        return ok;
    }

    document.addEventListener('focusout', e => {
        const el = e.target;
        if (el.matches && el.matches('[data-validate]') && el.type !== 'file' && el.value !== '') validateField(el);
    });
    document.addEventListener('input', e => {
        const el = e.target;
        if (el.matches && el.matches('[inputmode="numeric"]')) el.value = el.value.replace(/\D/g, '');
        if (el.matches && el.matches('[data-philhealth]')) {
            const d = el.value.slice(0, 12);
            el.value = d.length > 11 ? d.slice(0, 2) + '-' + d.slice(2, 11) + '-' + d.slice(11)
                     : d.length > 2 ? d.slice(0, 2) + '-' + d.slice(2) : d;
        }
        if (el.matches && el.matches('[data-validate].invalid')) validateField(el);
    });
    document.addEventListener('change', e => {
        const el = e.target;
        if (el.matches && el.matches('[data-validate]')) validateField(el);
    });

    document.querySelectorAll('form[data-validate-form]').forEach(form => {
        form.addEventListener('submit', e => {
            if (!validateContainer(form)) e.preventDefault();
        });
    });

    function openModal(id, fill) {
        const m = document.getElementById(id);
        if (!m) return;
        if (fill) {
            m.querySelectorAll('form').forEach(f => {
                f.reset();
                f.querySelectorAll('.invalid').forEach(x => x.classList.remove('invalid'));
                f.querySelectorAll('.field-error').forEach(x => { x.textContent = ''; });
            });
            Object.keys(fill).forEach(k => {
                m.querySelectorAll('[data-slot="' + k + '"]').forEach(s => {
                    if (s.tagName === 'INPUT') s.value = fill[k]; else s.textContent = fill[k];
                });
            });
        }
        m.hidden = false;
        document.body.classList.add('modal-open');
        const focusable = m.querySelector('select, textarea, input:not([type=hidden]), button');
        if (focusable) setTimeout(() => focusable.focus(), 30);
    }
    function closeModal(m) {
        m.hidden = true;
        if (!document.querySelector('.modal:not([hidden])')) document.body.classList.remove('modal-open');
    }

    document.addEventListener('click', e => {
        const closer = e.target.closest('[data-close]');
        const opener = e.target.closest('[data-open]');
        if (closer) { const m = closer.closest('.modal'); if (m) closeModal(m); }
        if (opener) {
            e.preventDefault();
            openModal(opener.dataset.open, opener.dataset.fill ? JSON.parse(opener.dataset.fill) : null);
        }
        if (e.target.classList && e.target.classList.contains('modal')) closeModal(e.target);
    });
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') document.querySelectorAll('.modal:not([hidden])').forEach(closeModal);
    });
    document.querySelectorAll('.modal[data-autoopen]').forEach(m => openModal(m.id));

    let pendingForm = null;
    document.querySelectorAll('[data-confirm]').forEach(btn => {
        btn.addEventListener('click', e => {
            e.preventDefault();
            const form = btn.closest('form');
            if (!validateContainer(form)) return;
            pendingForm = form;
            openModal(btn.dataset.confirm);
        });
    });
    document.querySelectorAll('[data-confirm-yes]').forEach(btn => {
        btn.addEventListener('click', () => {
            if (!pendingForm) return;
            btn.disabled = true;
            btn.textContent = 'Please wait...';
            pendingForm.submit();
        });
    });

    document.querySelectorAll('textarea[data-counter]').forEach(t => {
        const c = document.createElement('small');
        c.className = 'counter';
        const upd = () => { c.textContent = t.value.length + ' / ' + t.maxLength; };
        t.after(c);
        t.addEventListener('input', upd);
        upd();
    });

    document.addEventListener('submit', e => {
        if (e.defaultPrevented) return;
        const b = e.target.querySelector('button[type=submit]');
        if (b) setTimeout(() => { b.disabled = true; }, 0);
    });

    document.querySelectorAll('select[data-chips]').forEach(select => {
        const wrap = document.createElement('div');
        wrap.className = 'chip-choices';
        wrap.setAttribute('role', 'radiogroup');
        const label = select.closest('.field') && select.closest('.field').querySelector('label');
        if (label) wrap.setAttribute('aria-label', label.textContent.trim());
        Array.from(select.options).forEach(o => {
            if (o.value === '') return;
            const b = document.createElement('button');
            b.type = 'button';
            b.className = 'choice-chip cc-' + o.value.toLowerCase().replace(/[^a-z]+/g, '-');
            b.textContent = o.text;
            b.setAttribute('role', 'radio');
            b.addEventListener('click', () => {
                select.value = o.value;
                select.dispatchEvent(new Event('change', { bubbles: true }));
            });
            wrap.appendChild(b);
        });
        const sync = () => wrap.querySelectorAll('.choice-chip').forEach((b, i) => {
            const on = select.options[i + (select.options[0].value === '' ? 1 : 0)].value === select.value;
            b.classList.toggle('on', on);
            b.setAttribute('aria-checked', on ? 'true' : 'false');
        });
        select.after(wrap);
        select.classList.add('native-hidden');
        select._proxy = wrap;
        select.addEventListener('change', sync);
        sync();
    });

    document.querySelectorAll('[data-tabs]').forEach(box => {
        const tabs = Array.from(box.querySelectorAll('[data-tab]'));
        tabs.forEach(tab => tab.addEventListener('click', () => {
            tabs.forEach(t => {
                const on = t === tab;
                t.setAttribute('aria-selected', on ? 'true' : 'false');
                document.getElementById(t.dataset.tab).hidden = !on;
            });
        }));
    });

    document.querySelectorAll('[data-show-password]').forEach(btn => {
        btn.addEventListener('click', () => {
            const input = document.getElementById(btn.dataset.showPassword);
            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            btn.textContent = show ? 'Hide' : 'Show';
            btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
        });
    });

    /* Shrink big photos before upload (Vercel accepts about 4.5 MB per request). */
    const MAX_SIDE = 1600;
    function shrinkImage(file) {
        return new Promise(resolve => {
            if (!/^image\/(jpeg|png)$/.test(file.type) || file.size < 400 * 1024) return resolve(file);
            const img = new Image();
            const src = URL.createObjectURL(file);
            img.onload = () => {
                const scale = Math.min(1, MAX_SIDE / Math.max(img.width, img.height));
                const c = document.createElement('canvas');
                c.width = Math.round(img.width * scale);
                c.height = Math.round(img.height * scale);
                const ctx = c.getContext('2d');
                ctx.fillStyle = '#fff';
                ctx.fillRect(0, 0, c.width, c.height);
                ctx.drawImage(img, 0, 0, c.width, c.height);
                URL.revokeObjectURL(src);
                c.toBlob(b => {
                    if (!b || b.size >= file.size) return resolve(file);
                    resolve(new File([b], file.name.replace(/\.[^.]+$/, '') + '.jpg', { type: 'image/jpeg' }));
                }, 'image/jpeg', 0.82);
            };
            img.onerror = () => { URL.revokeObjectURL(src); resolve(file); };
            img.src = src;
        });
    }
    document.addEventListener('change', async e => {
        const el = e.target;
        if (!el.matches || !el.matches('input[type=file][data-compress]') || !el.files || !el.files[0]) return;
        if (el._shrinking) return;
        const original = el.files[0];
        const small = await shrinkImage(original);
        if (small !== original && window.DataTransfer) {
            const dt = new DataTransfer();
            dt.items.add(small);
            el._shrinking = true;
            el.files = dt.files;
            el.dispatchEvent(new Event('change', { bubbles: true }));
            el._shrinking = false;
        }
    }, true);

    window.App = { validateField, validateContainer, openModal, closeModal, setError, today, ageOf };
})();
