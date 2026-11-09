<?php
require __DIR__ . '/../config/config.php';
require __DIR__ . '/../src/Auth.php';
Auth::requerirLogin();
?>
<?php renderCapcalera("Informes d'activitat", usuariActual()); ?>
<style>
  :root { --lava:#c0392b; --lava-dark:#7b241c; --ember:#e67e22; --ash:#2c3e50; --bg:#f5f3f0; }
  .panel { background:#fff; border:none; border-radius:16px; box-shadow:0 4px 20px rgba(0,0,0,.06); }
  .panel h5 { font-weight:700; color:var(--ash); margin:0 0 1rem 0; display:flex; align-items:center; gap:.5rem; }
  .panel h5::before { content:""; width:6px; height:20px; border-radius:3px; background:var(--lava); display:inline-block; }
  .taula-informes { font-size:.87rem; }
  .taula-informes thead th { background:#f3f1ee; font-size:.75rem; text-transform:uppercase; color:#6b6b6b; position:sticky; top:0; }
  .badge-tipus { font-size:.72rem; font-weight:600; padding:.25rem .55rem; border-radius:999px; white-space:nowrap; }
  .badge-neteja_total { background:#fdecea; color:#dc3545; }
  .badge-neteja_parcial { background:#fff3cd; color:#8a6400; }
  .badge-altres { background:#e8eef2; color:var(--ash); }
  .form-label.fw-bold { color:var(--ash); font-size:.8rem; text-transform:uppercase; letter-spacing:.4px; }
</style>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
  <div class="titol-pagina m-0"><h1>Informes d'activitat</h1></div>
  <div class="d-flex gap-2 flex-wrap align-items-center">
      
  </div>
</div>


<div class="container-fluid px-0 pb-5">

  <div class="panel p-3 mb-4">
    <p class="mb-0 text-muted" style="font-size:.9rem">
      Tot el que s'ha canviat a l'aplicació: correccions de codis postals, entrades manuals,
      suggeriments massius aplicats, gestió de productes i usuaris, i neteges de dades — amb qui
      ho ha fet i quan. Útil per saber què ha passat si cal refer alguna cosa.
    </p>
  </div>

  <div class="panel p-4">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
      <h5 class="mb-0">Historial complet</h5>
      <select id="filtreTipus" class="form-select form-select-sm" style="width:auto"><option value="">Tots els tipus</option></select>
    </div>
    <div class="table-responsive" style="max-height:70vh; overflow:auto">
      <table class="table table-sm table-hover taula-informes mb-0">
        <thead><tr><th>Data</th><th>Usuari</th><th>Tipus</th><th>Detall</th></tr></thead>
        <tbody id="cosInformes"></tbody>
      </table>
    </div>
  </div>

</div>

<?php renderPeu([], false); ?>
<script>
async function json(url) { return (await fetch(url)).json(); }

const ETIQUETES_TIPUS = {
  correccio_cp: 'Correcció de CP',
  entrada_manual_afegida: 'Entrada manual afegida',
  entrada_manual_eliminada: 'Entrada manual eliminada',
  suggeriments_massius: 'Suggeriments massius',
  neteja_cp_format: 'Neteja de CP mal format',
  reclassificacio_fr: 'Reclassificació CP francesos',
  error_descartat: 'Error descartat',
  descart_restaurat: 'Descart restaurat',
  rederivar_tot: 'Re-derivació completa',
  pax_editat: 'Pax editat (duplicat escolar)',
  importacio_desfeta: 'Importació desfeta',
  producte_assignat: 'Producte assignat a grup',
  producte_desassignat: 'Producte tret d\'un grup',
  grups_fusionats: 'Grups de productes fusionats',
  usuari_creat: 'Usuari creat',
  rol_canviat: 'Rol d\'usuari canviat',
  usuari_actiu_canviat: 'Usuari activat/desactivat',
  contrasenya_reiniciada: 'Contrasenya reiniciada',
  neteja_parcial: '🧹 Neteja parcial de dades',
  neteja_total: '🧹 Neteja TOTAL de dades',
};

function etiquetaTipus(t) {
  return ETIQUETES_TIPUS[t] || t;
}

function badgeClasse(t) {
  if (t === 'neteja_total') return 'badge-neteja_total';
  if (t === 'neteja_parcial') return 'badge-neteja_parcial';
  return 'badge-altres';
}

async function carregarFiltreTipus() {
  const tipus = await json('api_informes.php?accio=tipus_disponibles');
  const sel = document.getElementById('filtreTipus');
  tipus.forEach(t => sel.add(new Option(etiquetaTipus(t), t)));
}

async function carregarHistorial() {
  const tipus = document.getElementById('filtreTipus').value;
  const files = await json('api_informes.php?accio=historial&tipus=' + encodeURIComponent(tipus));
  const cos = document.getElementById('cosInformes');
  if (!files.length) { cos.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-4">Encara no hi ha cap activitat registrada.</td></tr>'; return; }
  cos.innerHTML = files.map(f => `
    <tr>
      <td style="white-space:nowrap">${new Date(f.data).toLocaleString('ca')}</td>
      <td>${f.nom_usuari ?? '—'}</td>
      <td><span class="badge-tipus ${badgeClasse(f.tipus)}">${etiquetaTipus(f.tipus)}</span></td>
      <td style="font-size:.85rem">${f.detall ?? ''}</td>
    </tr>`).join('');
}

document.getElementById('filtreTipus').addEventListener('change', carregarHistorial);

(async () => {
  await carregarFiltreTipus();
  await carregarHistorial();
})();
</script>
</body>
</html>
