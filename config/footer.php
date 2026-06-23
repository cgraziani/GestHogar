<?php
?>
</main>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>

<script>
$(document).ready(function () {
    $('table.datatable').each(function () {
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
    var msg  = params.get('toast');
    var tipo = params.get('tipo') || 'ok';
    if (msg) mostrarToast(decodeURIComponent(msg), tipo);
})();
</script>

<style>
#modal-confirm-overlay {
    display: none;
    position: fixed; top:0; left:0; width:100%; height:100%;
    background: rgba(0,0,0,.5);
    z-index: 9000;
    align-items: center; justify-content: center;
}
#modal-confirm-overlay.open { display: flex; }
#modal-confirm-box {
    background: var(--bg-surface);
    color: var(--text-main);
    border-radius: 12px;
    padding: 28px 28px 24px;
    width: 100%; max-width: 420px;
    box-shadow: 0 8px 32px rgba(0,0,0,.22);
    animation: confirmIn .18s ease;
}
@keyframes confirmIn {
    from { opacity:0; transform: scale(.95) translateY(8px); }
    to   { opacity:1; transform: scale(1)   translateY(0);   }
}
#modal-confirm-icon {
    font-size: 36px; text-align: center; margin-bottom: 12px;
}
#modal-confirm-msg {
    font-size: 15px; line-height: 1.6;
    text-align: center; margin-bottom: 24px;
}
#modal-confirm-btns {
    display: flex; gap: 10px; justify-content: flex-end;
}
#modal-confirm-btns button {
    padding: 9px 22px; border-radius: 7px; border: none;
    font-size: 14px; font-weight: 600; cursor: pointer;
}
#btn-confirm-cancel {
    background: var(--bg-surface);
    color: var(--text-main);
    border: 1.5px solid var(--border-color, #ddd) !important;
}
#btn-confirm-cancel:hover { opacity: .8; }
#btn-confirm-ok { color: #fff; }
#btn-confirm-ok.red    { background: #E05C5C; }
#btn-confirm-ok.orange { background: #E8A838; }
#btn-confirm-ok.green  { background: #2D9B8A; }
</style>

<div id="modal-confirm-overlay">
    <div id="modal-confirm-box">
        <div id="modal-confirm-icon"></div>
        <div id="modal-confirm-msg"></div>
        <div id="modal-confirm-btns">
            <button id="btn-confirm-cancel">Cancelar</button>
            <button id="btn-confirm-ok" class="red">Confirmar</button>
        </div>
    </div>
</div>

<script>
(function() {
    var _callback = null;

    var overlay  = document.getElementById('modal-confirm-overlay');
    var msgEl    = document.getElementById('modal-confirm-msg');
    var iconEl   = document.getElementById('modal-confirm-icon');
    var btnOk    = document.getElementById('btn-confirm-ok');
    var btnCancel= document.getElementById('btn-confirm-cancel');

    window.confirmarAccion = function(mensaje, labelOk, color, callback) {
        msgEl.textContent       = mensaje;
        btnOk.textContent       = labelOk || 'Confirmar';
        btnOk.className         = color   || 'red';
        iconEl.textContent      = '';
        _callback               = callback;
        overlay.classList.add('open');
    };

    window.confirmarLink = function(el, mensaje, labelOk, color) {
        var href = el.href;
        confirmarAccion(mensaje, labelOk, color, function() {
            window.location.href = href;
        });
        return false;
    };

    btnOk.addEventListener('click', function() {
        overlay.classList.remove('open');
        if (typeof _callback === 'function') _callback();
        _callback = null;
    });

    btnCancel.addEventListener('click', function() {
        overlay.classList.remove('open');
        _callback = null;
    });

    overlay.addEventListener('click', function(e) {
        if (e.target === overlay) {
            overlay.classList.remove('open');
            _callback = null;
        }
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && overlay.classList.contains('open')) {
            overlay.classList.remove('open');
            _callback = null;
        }
    });
})();
</script>

<style>
#upload-progress-overlay {
    display: none;
    position: fixed; top:0; left:0; width:100%; height:100%;
    background: rgba(0,0,0,.6);
    z-index: 9500;
    align-items: center; justify-content: center;
}
#upload-progress-overlay.open { display: flex; }
#upload-progress-box {
    background: var(--bg-surface);
    color: var(--text-main);
    border-radius: 12px;
    padding: 28px 32px;
    width: 100%; max-width: 380px;
    text-align: center;
    box-shadow: 0 8px 32px rgba(0,0,0,.25);
}
#upload-progress-box h3 {
    margin: 0 0 6px;
    font-size: 16px;
    font-weight: 600;
}
#upload-progress-box p {
    margin: 0 0 18px;
    font-size: 13px;
    color: var(--text-muted, #888);
}
#upload-progress-track {
    background: var(--border-color, #e5e7eb);
    border-radius: 99px;
    height: 10px;
    overflow: hidden;
    margin-bottom: 10px;
}
#upload-progress-bar {
    height: 100%;
    background: #2D9B8A;
    border-radius: 99px;
    width: 0%;
    transition: width .2s ease;
}
#upload-progress-pct {
    font-size: 22px;
    font-weight: 700;
    color: #2D9B8A;
    margin-bottom: 4px;
}
#upload-progress-label {
    font-size: 12px;
    color: var(--text-muted, #888);
}
</style>

<div id="upload-progress-overlay">
    <div id="upload-progress-box">
        <h3>Subiendo archivo...</h3>
        <p>Por favor no cerrés esta ventana.</p>
        <div id="upload-progress-track">
            <div id="upload-progress-bar"></div>
        </div>
        <div id="upload-progress-pct">0%</div>
        <div id="upload-progress-label">Preparando...</div>
    </div>
</div>

<script>
(function() {
    function activarProgressEnForm(form) {
        form.addEventListener('submit', function(e) {
            var fileInputs = form.querySelectorAll('input[type="file"]');
            var tieneArchivo = false;
            fileInputs.forEach(function(fi) { if (fi.files && fi.files.length > 0) tieneArchivo = true; });
            if (!tieneArchivo) return;

            e.preventDefault();

            var overlay = document.getElementById('upload-progress-overlay');
            var bar     = document.getElementById('upload-progress-bar');
            var pct     = document.getElementById('upload-progress-pct');
            var label   = document.getElementById('upload-progress-label');

            overlay.classList.add('open');
            bar.style.width = '0%';
            pct.textContent = '0%';
            label.textContent = 'Iniciando subida...';

            var formData = new FormData(form);
            var xhr      = new XMLHttpRequest();

            xhr.upload.addEventListener('progress', function(ev) {
                if (ev.lengthComputable) {
                    var porcentaje = Math.round((ev.loaded / ev.total) * 100);
                    bar.style.width  = porcentaje + '%';
                    pct.textContent  = porcentaje + '%';
                    label.textContent = porcentaje < 100
                        ? 'Subiendo... ' + porcentaje + '%'
                        : 'Procesando...';
                }
            });

            xhr.addEventListener('load', function() {
                bar.style.width  = '100%';
                pct.textContent  = '100%';
                label.textContent = '¡Listo! Redirigiendo...';
                if (xhr.responseURL && xhr.responseURL !== window.location.href) {
                    setTimeout(function() {
                        window.location.href = xhr.responseURL;
                    }, 600);
                } else {
                    setTimeout(function() {
                        window.location.reload();
                    }, 600);
                }
            });

            xhr.addEventListener('error', function() {
                overlay.classList.remove('open');
                mostrarToast('Error al subir el archivo. Intentá de nuevo.', 'error');
            });

            xhr.open(form.method || 'POST', form.action || window.location.href);
            xhr.send(formData);
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('form[enctype="multipart/form-data"]').forEach(activarProgressEnForm);
    });

    var observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(m) {
            m.addedNodes.forEach(function(node) {
                if (node.nodeType === 1) {
                    var forms = node.querySelectorAll
                        ? node.querySelectorAll('form[enctype="multipart/form-data"]')
                        : [];
                    forms.forEach(activarProgressEnForm);
                    if (node.matches && node.matches('form[enctype="multipart/form-data"]'))
                        activarProgressEnForm(node);
                }
            });
        });
    });
    observer.observe(document.body, { childList: true, subtree: true });
})();
</script>

<script>
(function() {
    var formModificado = false;
    var submitando     = false;

    document.addEventListener('input',  function(e) { if (e.target.form) formModificado = true; });
    document.addEventListener('change', function(e) { if (e.target.form) formModificado = true; });

    document.addEventListener('submit', function() {
        submitando = true;
    });

    window.addEventListener('beforeunload', function(e) {
        if (formModificado && !submitando) {
            e.preventDefault();
            e.returnValue = '¿Salir sin guardar los cambios?';
            return e.returnValue;
        }
    });

    document.addEventListener('click', function(e) {
        if (!formModificado || submitando) return;

        var link = e.target.closest('a[href]');
        if (!link) return;

        var href = link.getAttribute('href');
        if (!href || href === '#' || href.startsWith('javascript') || href.startsWith('?accion') ) return;
        if (link.getAttribute('onclick') && link.getAttribute('onclick').includes('confirmarLink')) return;

        e.preventDefault();
        confirmarAccion(
            '¿Salir sin guardar? Los cambios que hiciste se perderán.',
            'Salir sin guardar',
            'red',
            function() {
                formModificado = false;
                window.location.href = href;
            }
        );
    });
})();
</script>


<style>
.field-error {
    border-color: #E05C5C !important;
    box-shadow: 0 0 0 2px rgba(224,92,92,.15) !important;
}
.field-ok {
    border-color: #2D9B8A !important;
    box-shadow: 0 0 0 2px rgba(45,155,138,.12) !important;
}
.field-msg {
    font-size: 11px;
    margin-top: 3px;
    display: block;
    min-height: 14px;
    line-height: 1.4;
}
.field-msg.error { color: #E05C5C; }
.field-msg.ok    { color: #2D9B8A; }
</style>

<script>
(function() {

function getMensaje(input) {
    var siguiente = input.nextElementSibling;
    if (siguiente && siguiente.classList.contains('field-msg')) return siguiente;
    var msg = document.createElement('span');
    msg.className = 'field-msg';
    input.parentNode.insertBefore(msg, input.nextSibling);
    return msg;
}

function marcarError(input, texto) {
    input.classList.add('field-error');
    input.classList.remove('field-ok');
    var msg = getMensaje(input);
    msg.textContent = texto;
    msg.className = 'field-msg error';
}

function marcarOk(input, texto) {
    input.classList.remove('field-error');
    input.classList.add('field-ok');
    var msg = getMensaje(input);
    msg.textContent = texto || '';
    msg.className = 'field-msg ok';
}

function limpiar(input) {
    input.classList.remove('field-error', 'field-ok');
    var msg = getMensaje(input);
    msg.textContent = '';
    msg.className = 'field-msg';
}

function validarCampo(input) {
    var val  = input.value.trim();
    var name = (input.name || '').toLowerCase();
    var type = (input.type || 'text').toLowerCase();

    if (['checkbox','radio','hidden','color','file','submit','button'].indexOf(type) !== -1) return;
    if (!input.name) return;

    if (!val && !input.required) { limpiar(input); return; }

    if (!val && input.required) {
        marcarError(input, 'Este campo es obligatorio.');
        return;
    }

    if (type === 'email' || name.indexOf('email') !== -1) {
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val)) {
            marcarError(input, 'Ingresá un email válido (ej: nombre@dominio.com).');
        } else {
            marcarOk(input, 'Email válido');
        }
        return;
    }

    if (name === 'dni') {
        if (!/^[0-9]{7,8}$/.test(val)) {
            marcarError(input, 'El DNI debe tener entre 7 y 8 dígitos numéricos.');
        } else {
            marcarOk(input, 'DNI válido');
        }
        return;
    }

    if (name.indexOf('telefon') !== -1) {
        if (val.length < 7) {
            marcarError(input, 'El teléfono parece muy corto.');
        } else {
            marcarOk(input);
        }
        return;
    }

    if (type === 'password' && name === 'password') {
        if (val.length < 6) {
            marcarError(input, 'La contraseña debe tener al menos 6 caracteres. (' + val.length + '/6)');
        } else {
            marcarOk(input, 'Contraseña válida');
        }
        return;
    }

    if (name === 'monto' || name === 'capacidad' || name === 'precio') {
        var num = parseFloat(val);
        if (isNaN(num) || num < 0) {
            marcarError(input, 'Ingresá un número válido mayor o igual a 0.');
        } else {
            marcarOk(input);
        }
        return;
    }

    if (name === 'fecha_nacimiento') {
        var fecha = new Date(val);
        var hoy   = new Date();
        if (fecha > hoy) {
            marcarError(input, 'La fecha no puede ser futura.');
        } else {
            var edad = hoy.getFullYear() - fecha.getFullYear();
            if (edad < 18) {
                marcarError(input, 'La fecha indica menos de 18 años (' + edad + '). Verificá.');
            } else {
                marcarOk(input, 'Fecha válida');
            }
        }
        return;
    }

    if (name === 'fecha_ingreso') {
        var fi = new Date(val);
        if (fi > new Date()) {
            marcarError(input, 'La fecha de ingreso no puede ser futura.');
        } else {
            marcarOk(input);
        }
        return;
    }

    if (input.minLength && input.minLength > 0 && val.length < input.minLength) {
        marcarError(input, 'Mínimo ' + input.minLength + ' caracteres. Llevas ' + val.length + '.');
        return;
    }

    if (input.required && val) {
        marcarOk(input);
    }
}

function activarValidacion(form) {
    var campos = form.querySelectorAll('input, textarea, select');
    campos.forEach(function(input) {
        if (input.type === 'number' ||
            (input.name || '').toLowerCase() === 'monto' ||
            (input.name || '').toLowerCase() === 'capacidad') {
            if (!input.getAttribute('inputmode')) {
                input.setAttribute('inputmode', 'decimal');
            }
        }
        if ((input.name || '').toLowerCase() === 'dni' ||
            (input.name || '').toLowerCase() === 'capacidad') {
            if (!input.getAttribute('inputmode')) {
                input.setAttribute('inputmode', 'numeric');
            }
        }

        input.addEventListener('input',  function() { validarCampo(this); });
        input.addEventListener('blur',   function() { validarCampo(this); });
        input.addEventListener('change', function() { validarCampo(this); });
    });

    form.addEventListener('submit', function(e) {
        var hayError = false;
        campos.forEach(function(input) {
            validarCampo(input);
            if (input.classList.contains('field-error')) hayError = true;
        });
        if (hayError) {
            e.preventDefault();
            var primerError = form.querySelector('.field-error');
            if (primerError) primerError.scrollIntoView({ behavior: 'smooth', block: 'center' });
            mostrarToast('Hay errores en el formulario. Revisá los campos marcados.', 'error');
        }
    });
}

document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('form').forEach(function(form) {
        activarValidacion(form);
    });
});

var observer = new MutationObserver(function(mutations) {
    mutations.forEach(function(m) {
        m.addedNodes.forEach(function(node) {
            if (node.nodeType !== 1) return;
            var forms = node.querySelectorAll ? node.querySelectorAll('form') : [];
            forms.forEach(activarValidacion);
            if (node.matches && node.matches('form')) activarValidacion(node);
        });
    });
});
observer.observe(document.body, { childList: true, subtree: true });

})();
</script>


<script>
$(document).ready(function() {
    function aplicarTemaDataTables() {
        var isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        if (!isDark) return;

        document.querySelectorAll('.dataTables_wrapper select, .dataTables_wrapper input[type="search"]').forEach(function(el) {
            el.style.setProperty('background', '#161f2c', 'important');
            el.style.setProperty('color', '#e7ecf2', 'important');
            el.style.setProperty('border', '1px solid #3a4757', 'important');
            el.style.setProperty('border-radius', '8px', 'important');
            el.style.setProperty('padding', '6px 10px', 'important');
        });

        document.querySelectorAll('.dataTables_length label, .dataTables_filter label, .dataTables_info').forEach(function(el) {
            el.style.setProperty('color', '#e7ecf2', 'important');
        });

        document.querySelectorAll('.dataTables_paginate .paginate_button').forEach(function(el) {
            el.style.setProperty('background', '#1c2733', 'important');
            el.style.setProperty('color', '#e7ecf2', 'important');
            el.style.setProperty('border', '1px solid #2a3645', 'important');
            el.style.setProperty('border-radius', '6px', 'important');

            if (el.classList.contains('current')) {
                el.style.setProperty('background', '#20a89c', 'important');
                el.style.setProperty('color', '#fff', 'important');
                el.style.setProperty('border-color', '#20a89c', 'important');
            }
            if (el.classList.contains('disabled')) {
                el.style.setProperty('opacity', '0.4', 'important');
                el.style.setProperty('color', '#9aa7b8', 'important');
            }
        });
    }

    aplicarTemaDataTables();

    $(document).on('draw.dt', function() {
        aplicarTemaDataTables();
    });

    var observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(m) {
            if (m.attributeName === 'data-theme') {
                aplicarTemaDataTables();
            }
        });
    });
    observer.observe(document.documentElement, { attributes: true });
});
</script>

</body>
</html>

