/*
 * Mode clar / fosc / automàtic. Es carrega de manera síncrona al <head>
 * perquè el tema s'apliqui abans de pintar la pàgina (sense parpelleig).
 * Preferència desada a localStorage ('tema'): light | dark | auto.
 * Qualsevol element amb data-tema-valor="light|dark|auto" fa de selector.
 * Emet l'esdeveniment 'tema:canvi' al document (els gràfics l'escolten).
 */
(function () {
    'use strict';

    var CLAU = 'tema';
    var consultaFosc = window.matchMedia('(prefers-color-scheme: dark)');

    function preferencia() {
        try {
            var valor = localStorage.getItem(CLAU);
            return valor === 'light' || valor === 'dark' ? valor : 'auto';
        } catch (e) {
            return 'auto';
        }
    }

    function resol(tema) {
        if (tema === 'auto') {
            return consultaFosc.matches ? 'dark' : 'light';
        }
        return tema;
    }

    function marcaSelectors() {
        var actual = preferencia();
        document.querySelectorAll('[data-tema-valor]').forEach(function (boto) {
            var actiu = boto.getAttribute('data-tema-valor') === actual;
            boto.classList.toggle('active', actiu);
            boto.setAttribute('aria-pressed', actiu ? 'true' : 'false');
        });
    }

    function aplica(tema) {
        var resolt = resol(tema);
        document.documentElement.setAttribute('data-bs-theme', resolt);
        document.dispatchEvent(new CustomEvent('tema:canvi', { detail: { tema: resolt } }));
    }

    window.Tema = {
        estableix: function (tema) {
            try {
                localStorage.setItem(CLAU, tema);
            } catch (e) { /* navegació privada: només per a aquesta pàgina */ }
            aplica(tema);
            marcaSelectors();
        },
        actual: function () {
            return resol(preferencia());
        }
    };

    aplica(preferencia());

    consultaFosc.addEventListener('change', function () {
        if (preferencia() === 'auto') {
            aplica('auto');
        }
    });

    document.addEventListener('DOMContentLoaded', function () {
        marcaSelectors();
        document.addEventListener('click', function (event) {
            var boto = event.target.closest('[data-tema-valor]');
            if (boto) {
                window.Tema.estableix(boto.getAttribute('data-tema-valor'));
            }
        });
    });
})();
