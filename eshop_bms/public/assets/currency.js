(function () {
    const sel = document.getElementById('currency-select');
    if (!sel) return;

    const cfg = window.currencyConfig || {};
    const rates = cfg.rates || {};
    const postUrl = cfg.postUrl || sel.dataset.setUrl;
    const csrf = cfg.csrf || sel.dataset.csrf;

    const LS_KEY = 'currency';

    /**
     * Reads the base numeric amount from element attributes/dataset.
     *
     * @param {HTMLElement} el - Target element.
     * @returns {number} Parsed base amount (fallback 0).
     */
    function readBase(el) {
        const v =
            el.getAttribute('data-raw')
            ?? el.getAttribute('data-base')
            ?? el.dataset.amount
            ?? '0';

        const n = parseFloat(v);
        return Number.isFinite(n) ? n : 0;
    }

    /**
     * Redraws all money elements using the given currency code.
     *
     * @param {string} code - Currency code.
     */
    function redrawAll(code) {
        const r = Number(rates[code] ?? 1);

        document.querySelectorAll('.money').forEach((el) => {
            const base = readBase(el);
            el.textContent = `${(base * r).toFixed(2)} ${code}`;
        });
    }

    /**
     * Syncs selected currency to server if endpoint and CSRF token are provided.
     *
     * @param {string} code - Currency code.
     * @returns {Promise<void>} Fetch promise (resolved on missing config).
     */
    function syncToServer(code) {
        if (!postUrl || !csrf) return Promise.resolve();

        const body = new URLSearchParams();
        body.set('currency', code);
        body.set('_token', csrf);

        return fetch(postUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString(),
        }).catch(() => {});
    }

    const stored = localStorage.getItem(LS_KEY);
    if (stored && stored !== sel.value) {
        sel.value = stored;
    }

    redrawAll(sel.value);
    syncToServer(sel.value);

    sel.addEventListener('change', () => {
        const code = sel.value;
        localStorage.setItem(LS_KEY, code);
        redrawAll(code);
        syncToServer(code);
    });
}());