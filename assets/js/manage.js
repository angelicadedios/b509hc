(function () {
    'use strict';

    const UPPER = 'ABCDEFGHJKLMNPQRSTUVWXYZ', LOWER = 'abcdefghijkmnpqrstuvwxyz', DIGITS = '23456789';

    function pick(set) {
        const r = new Uint32Array(1);
        crypto.getRandomValues(r);
        return set[r[0] % set.length];
    }

    function generate() {
        const all = UPPER + LOWER + DIGITS;
        const chars = [pick(UPPER), pick(LOWER), pick(DIGITS)];
        while (chars.length < 10) chars.push(pick(all));
        for (let i = chars.length - 1; i > 0; i--) {
            const r = new Uint32Array(1); crypto.getRandomValues(r);
            const j = r[0] % (i + 1);
            [chars[i], chars[j]] = [chars[j], chars[i]];
        }
        return chars.join('');
    }

    document.querySelectorAll('[data-generate]').forEach(btn => {
        btn.addEventListener('click', () => {
            const input = document.getElementById(btn.dataset.generate);
            input.value = generate();
            window.App.validateField(input);
            input.select();
        });
    });
})();
