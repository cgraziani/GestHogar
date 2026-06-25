<?php
?>
</main>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>

<script>
$(document).ready(function () {
    $('table-datatable').each(function () {
        if ($(this).find('tbody tr').length > 0) {
            $(this).DataTable({
                language: {
                    url: 'https://cdn.datatables.net/plug-ins/1.13.8/i18n/es-ES.json'
                },
                pageLength: 10,
                order: [],
                responsive: true
            });
        }
    });
});
</script>

<style>
#toast-container {
    position: fixed; bottom: 24px; right: 24px; z-index: 9999;
    display: flex; flex-direction: column; gap: 10px; pointer-events: none;
}
.toast {
    padding: 14px 20px; border-radius: 8px; color: white;
    font-size: 14px; font-family: sans-serif;
    box-shadow: 0 4px 16px rgba(0,0,0,.18);
    opacity: 0; transform: translateY(16px);
    transition: opacity .3s ease, transform .3s ease;
    pointer-events: auto; max-width: 340px; line-height: 1.5;
}
.toast.show  { opacity: 1; transform: translateY(0); }
.toast.ok    { background: #2D9B8A; }
.toast.error { background: #E05C5C; }
.toast.info  { background: #4A7BC4; }
.toast.warn  { background: #E8A838; }
</style>

<div id="toast-container"></div>

<script>
function mostrarToast(mensaje, tipo) {
    tipo = tipo || 'ok';
    var contenedor = document.getElementById('toast-container');
    var el = document.createElement('div');
    el.className = 'toast ' + tipo;
    el.textContent = mensaje;
    contenedor.appendChild(el);
    setTimeout(function () { el.classList.add('show'); }, 10);
    setTimeout(function () {
        el.classList.remove('show');
        setTimeout(function () { el.remove(); }, 350);
    }, 3500);
}

(function () {
    var params = new URLSearchParams(window.location.search);
    var msg = params.get('toast');
    var tipo = params.get('tipo') || 'ok';
    if (msg) mostrarToast(decodeURIComponent(msg), tipo);
})();
</script>

</body>
</html>
