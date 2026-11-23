(function () {
    'use strict';
    let pending = null;
    const fmt = n => Number(n).toLocaleString('en-PH');

    document.querySelectorAll('.restock-form').forEach(form => {
        form.addEventListener('submit', e => {
            e.preventDefault();
            if (!window.App.validateContainer(form)) return;
            const qty = parseInt(form.elements.qty.value, 10);
            const old = parseInt(form.dataset.stock, 10);
            pending = form;
            window.App.openModal('restockModal', {
                name: form.dataset.name, qty: fmt(qty), old: fmt(old), new: fmt(old + qty)
            });
        });
    });

    document.getElementById('restockYes').addEventListener('click', function () {
        if (!pending) return;
        this.disabled = true;
        pending.submit();
    });
})();
