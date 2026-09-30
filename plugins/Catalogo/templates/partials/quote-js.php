<!-- Agregar a cotización sin recargar la página (el formulario funciona igual sin JS) -->
<div id="aj-toast" class="hidden fixed bottom-24 left-1/2 -translate-x-1/2 z-50 items-center gap-3 bg-gray-900 text-white text-sm px-5 py-3 rounded-full shadow-xl">
    <?= ajm_icon('check-circle', 'w-5 h-5 text-aj-olive-light') ?>
    <span data-toast-text></span>
    <a href="/catalogo/cotizacion" class="font-semibold text-aj-olive-light hover:underline whitespace-nowrap">Ver cotización</a>
</div>
<script>
(function () {
    if (window.__ajQuoteBound) return;
    window.__ajQuoteBound = true;
    var toast = document.getElementById('aj-toast');
    var timer;

    function showToast(text) {
        if (!toast) return;
        toast.querySelector('[data-toast-text]').textContent = text;
        toast.classList.remove('hidden');
        toast.classList.add('flex');
        clearTimeout(timer);
        timer = setTimeout(function () { toast.classList.add('hidden'); toast.classList.remove('flex'); }, 3500);
    }

    document.addEventListener('submit', function (e) {
        var form = e.target.closest('.js-add-quote');
        if (!form || !window.fetch) return;
        e.preventDefault();
        var btn = form.querySelector('button[type=submit]');
        if (btn) btn.disabled = true;
        fetch(form.action, { method: 'POST', body: new FormData(form), headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                showToast(data.message);
                if (data.success) {
                    document.querySelectorAll('[data-quote-count]').forEach(function (el) {
                        el.textContent = data.count;
                        el.classList.remove('hidden');
                        el.classList.add('flex');
                    });
                }
            })
            .catch(function () { form.submit(); })
            .finally(function () { if (btn) btn.disabled = false; });
    });
})();
</script>
