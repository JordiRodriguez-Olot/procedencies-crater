/*
 * Comportaments comuns, activats per atributs data-* (la CSP no permet JS
 * en línia: res de onclick="" ni <script> dins les pàgines).
 *
 *  data-confirma="Missatge"      en un <form> o botó: demana confirmació abans d'enviar.
 *  data-omple-form="#id"         en un botó que obre un modal: omple el formulari
 *    data-dades='{"camp":"valor"}'  amb aquestes dades (inputs per name).
 *  data-obre-modal               en un .modal: s'obre en carregar (p. ex. després d'un error).
 *  data-pestanya-desa="#input"   en unes .nav-tabs: desa la pestanya activa a un input ocult.
 *  data-percent="42"             en un .progress-bar: n'estableix l'amplada.
 *  data-mostra-si="#check"       es mostra només si la casella indicada està marcada.
 *  data-bs-toggle="tooltip"      tooltips de Bootstrap.
 */
(function () {
    'use strict';

    // --- Confirmació abans d'enviar ---
    document.addEventListener('submit', function (event) {
        var form = event.target;
        var boto = event.submitter;
        var missatge = (boto && boto.getAttribute('data-confirma')) || form.getAttribute('data-confirma');
        if (missatge && !window.confirm(missatge)) {
            event.preventDefault();
        }
    });

    // --- Omplir un formulari (normalment d'un modal) amb dades JSON ---
    function omplirFormulari(form, dades) {
        Object.keys(dades).forEach(function (nom) {
            var valor = dades[nom];
            var camps = form.querySelectorAll('[name="' + nom + '"], [name="' + nom + '[]"]');
            camps.forEach(function (camp) {
                if (camp.type === 'checkbox' || camp.type === 'radio') {
                    camp.checked = Array.isArray(valor)
                        ? valor.map(String).indexOf(camp.value) !== -1
                        : (camp.type === 'checkbox' && camp.value === 'on' ? !!valor : String(valor) === camp.value);
                } else if (camp.tagName === 'SELECT' && camp.multiple) {
                    var valors = (Array.isArray(valor) ? valor : [valor]).map(String);
                    Array.prototype.forEach.call(camp.options, function (opcio) {
                        opcio.selected = valors.indexOf(opcio.value) !== -1;
                    });
                } else {
                    camp.value = valor === null || valor === undefined ? '' : valor;
                }
                camp.dispatchEvent(new Event('change', { bubbles: true }));
            });
            form.querySelectorAll('[data-text="' + nom + '"]').forEach(function (el) {
                el.textContent = valor === null || valor === undefined ? '' : valor;
            });
        });
    }

    document.addEventListener('click', function (event) {
        var boto = event.target.closest('[data-omple-form]');
        if (!boto) {
            return;
        }
        var form = document.querySelector(boto.getAttribute('data-omple-form'));
        if (form) {
            omplirFormulari(form, JSON.parse(boto.getAttribute('data-dades') || '{}'));
        }
    });

    // --- Mostrar/amagar segons una casella ---
    function actualitzaDependents() {
        document.querySelectorAll('[data-mostra-si]').forEach(function (el) {
            var casella = document.querySelector(el.getAttribute('data-mostra-si'));
            el.hidden = !(casella && casella.checked);
        });
    }
    document.addEventListener('change', actualitzaDependents);

    document.addEventListener('DOMContentLoaded', function () {
        actualitzaDependents();

        document.querySelectorAll('.modal[data-obre-modal]').forEach(function (modal) {
            bootstrap.Modal.getOrCreateInstance(modal).show();
        });

        document.querySelectorAll('[data-pestanya-desa]').forEach(function (pestanyes) {
            var input = document.querySelector(pestanyes.getAttribute('data-pestanya-desa'));
            pestanyes.addEventListener('shown.bs.tab', function (event) {
                var clau = event.target.getAttribute('data-pestanya');
                if (input && clau) {
                    input.value = clau;
                }
                if (clau) {
                    var url = new URL(window.location.href);
                    url.searchParams.set('pestanya', clau);
                    window.history.replaceState(null, '', url);
                }
            });
        });

        document.querySelectorAll('.progress-bar[data-percent]').forEach(function (barra) {
            var percent = Math.max(0, Math.min(100, parseFloat(barra.getAttribute('data-percent')) || 0));
            barra.style.width = percent + '%';
        });

        document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
            bootstrap.Tooltip.getOrCreateInstance(el);
        });
    });
})();
