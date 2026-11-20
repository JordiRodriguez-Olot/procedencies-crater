<?php
require __DIR__ . '/../config/config.php';
require __DIR__ . '/../src/Auth.php';
require __DIR__ . '/../src/Ajuda.php';
Auth::requerirLogin();
?>
<?php renderCapcalera("Historial d'importacions", usuariActual()); ?>
<style>
  :root { --lava:#c0392b; --lava-dark:#7b241c; --ember:#e67e22; --ash:#2c3e50; --bg:#f5f3f0; }
  .panel { background:#fff; border:none; border-radius:16px; box-shadow:0 4px 20px rgba(0,0,0,.06); }
  .taula thead th { background:#f3f1ee; font-size:.8rem; text-transform:uppercase; letter-spacing:.3px; color:#6b6b6b; }
  .badge-canal { font-size:.75rem; font-weight:600; padding:.3rem .6rem; border-radius:999px; }
  .badge-TAQUILLA { background:#fdece8; color:var(--lava-dark); }
  .badge-ONLINE { background:#fdf1e3; color:#a35a0e; }
  .badge-CGR { background:#e8eef2; color:var(--ash); }
  .alerta-orfe { color:#b8860b; font-size:.78rem; }
  .ctl-pills { display:flex; gap:.5rem; flex-wrap:wrap; }
  .ctl-pill { border-radius:999px; padding:.25rem .7rem; font-size:.82rem; font-weight:600; background:#f3f1ee; }
  .ctl-taula td, .ctl-taula th { white-space:nowrap; }
  .ctl-taula td.cel { text-align:right; }
  .cel .pax { font-variant-numeric:tabular-nums; }
  .cel .marca { display:inline-block; min-width:1.2rem; text-align:center; }
  .e-ok .marca { color:#15803d; }
  .e-falta { background:#fef2f2; } .e-falta .marca { color:#dc2626; }
  .e-repetit { background:#fef2f2; } .e-repetit .marca { color:#dc2626; }
  .e-multiple, .e-desviacio { background:#fffbeb; } .e-multiple .marca, .e-desviacio .marca { color:#b45309; }
  .e-pendent { color:#9ca3af; }
  .cel .desv { font-size:.75rem; color:#6b7280; margin-left:.25rem; }
</style>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
  <div class="titol-pagina m-0"><h1>Historial d'importacions</h1></div>
  <div class="d-flex gap-2 flex-wrap align-items-center">
      <a href="import.php" class="btn btn-outline-primary btn-sm fw-semibold">+ Importar Excel</a>
  </div>
</div>


<?php renderMissatges(); ?>

<?php renderAjuda('importacions'); ?>

<div class="container-fluid px-0 pb-5">
  <div class="panel p-4 mb-4" id="control">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
      <div>
        <h2 class="h5 mb-1">Control de càrrega mensual</h2>
        <p class="text-muted mb-0" style="font-size:.85rem">Pax de cada mes i canal. Avisa si falta un mes, si un mes es desvia més d'un <span id="llindar">50</span>% del mateix mes de l'any anterior, o si s'ha pujat dues vegades.</p>
      </div>
      <div class="form-check form-switch">
        <input class="form-check-input" type="checkbox" id="nomesAvisos">
        <label class="form-check-label" for="nomesAvisos" style="font-size:.85rem">Només mesos amb avisos</label>
      </div>
    </div>
    <div class="ctl-pills mb-3" id="ctlResum"></div>
    <div class="table-responsive" style="max-height:60vh; overflow:auto">
      <table class="table table-sm ctl-taula align-middle mb-0">
        <thead id="ctlCap"></thead>
        <tbody id="ctlCos"><tr><td class="text-muted">Carregant…</td></tr></tbody>
      </table>
    </div>
    <button class="btn btn-link btn-sm px-0 mt-2" id="ctlTot" hidden>Mostrar tots els mesos</button>
    <p class="text-muted mt-2 mb-0" style="font-size:.8rem">
      <b>Llegenda:</b> ✓ correcte · ✕ falta importar · ⧉ el mateix fitxer pujat dos cops · ! diverses importacions al mateix mes ·
      ↕ desviació gran respecte l'any anterior (pot ser real) · … mes en curs. Passa el ratolí per sobre d'una casella per veure'n el detall.
    </p>
  </div>

  <div class="panel p-3 mb-4">
    <p class="mb-0 text-muted" style="font-size:.9rem">
      Totes les importacions fetes fins ara. Si vas importar un fitxer per error, pots desfer-la:
      s'esborraran totes les entrades associades a aquella importació concreta.
    </p>
  </div>

  <div class="panel p-4">
    <div class="table-responsive">
      <table class="table table-hover taula align-middle">
        <thead>
          <tr>
            <th>Data importació</th><th>Canal</th><th>Fitxer</th>
            <th>Període</th><th class="text-end">Files</th><th class="text-end">Pax</th><th></th>
          </tr>
        </thead>
        <tbody id="cos"></tbody>
      </table>
    </div>
  </div>
</div>

<?php renderPeu([], false); ?>
<script>
const MESOS = ['','Gen','Feb','Mar','Abr','Mai','Jun','Jul','Ago','Set','Oct','Nov','Des'];
const nf = n => Number(n).toLocaleString('ca');

async function json(url, opts) { return (await fetch(url, opts)).json(); }
async function post(params) {
  return json('api_importacions.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:new URLSearchParams(params) });
}

function formatPeriode(anymes) {
  if (!anymes) return '<span class="text-muted">—</span>';
  const s = String(anymes);
  const any = s.slice(0,4), mes = parseInt(s.slice(4,6),10);
  return `${MESOS[mes]} ${any}`;
}

async function carregar() {
  const files = await json('api_importacions.php?accio=llistar');
  const cos = document.getElementById('cos');
  if (!files.length) {
    cos.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">Encara no s\'ha fet cap importació.</td></tr>';
    return;
  }
  cos.innerHTML = files.map(f => `
    <tr data-id="${f.id}">
      <td>${new Date(f.data_importacio).toLocaleString('ca')}</td>
      <td><span class="badge-canal badge-${f.canal_codi}">${f.canal}</span></td>
      <td style="max-width:280px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap" title="${f.nom_fitxer}">${f.nom_fitxer}</td>
      <td>${formatPeriode(f.anymes)}</td>
      <td class="text-end fw-semibold">${nf(f.files_actuals)}</td>
      <td class="text-end">${nf(f.pax_actuals)}</td>
      <td>
        ${Number(f.files_actuals) === 0 ? '<span class="alerta-orfe">⚠ sense entrades</span> ' : ''}
        <button class="btn btn-outline-danger btn-sm" data-desfer="${f.id}">Desfer</button>
      </td>
    </tr>`).join('');

  cos.querySelectorAll('[data-desfer]').forEach(btn => btn.addEventListener('click', async () => {
    if (!confirm('Es desfarà aquesta importació i s\'esborraran totes les entrades associades. Continuar?')) return;
    btn.disabled = true; btn.textContent = '…';
    const res = await post({ accio:'desfer', id: btn.dataset.desfer });
    if (res.ok) carregar();
    else { btn.disabled = false; btn.textContent = 'Desfer'; alert('Error: ' + (res.error||'?')); }
  }));
}

carregar();

// ---------- Control de càrrega mensual ----------
const ESTATS = {
  ok:        { marca:'✓', text:'Correcte' },
  falta:     { marca:'✕', text:'Falta' },
  repetit:   { marca:'⧉', text:'Pujat dos cops' },
  multiple:  { marca:'!', text:'Diverses importacions' },
  desviacio: { marca:'↕', text:'Desviació gran' },
  pendent:   { marca:'…', text:'Mes en curs' },
};
const esc = s => String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
let ctl = null, mostrarTot = false;

function pintarControl() {
  const nomesAvisos = document.getElementById('nomesAvisos').checked;
  document.getElementById('ctlCap').innerHTML =
    '<tr><th>Mes</th>' + ctl.canals.map(c => `<th class="text-end">${esc(c)}</th>`).join('') + '</tr>';
  let mesos = ctl.mesos;
  if (nomesAvisos) mesos = mesos.filter(m => Object.values(m.canals).some(c => !['ok','pendent'].includes(c.estat)));
  const limit = 24;
  const btnTot = document.getElementById('ctlTot');
  btnTot.hidden = nomesAvisos || mostrarTot || mesos.length <= limit;
  if (!nomesAvisos && !mostrarTot) mesos = mesos.slice(0, limit);
  document.getElementById('ctlCos').innerHTML = mesos.length ? mesos.map(m => `
    <tr><td class="fw-semibold">${formatPeriode(m.anymes)}</td>${ctl.canals.map(codi => {
      const c = m.canals[codi], est = ESTATS[c.estat];
      const desv = c.desviacio === null ? '' : `<span class="desv">${c.desviacio > 0 ? '+' : ''}${Math.round(c.desviacio*100)}%</span>`;
      const titol = [est.text, ...c.avisos].join('\n');
      return `<td class="cel e-${c.estat}" title="${esc(titol)}"><span class="pax">${c.files ? nf(c.pax) : '—'}</span>${c.estat === 'desviacio' || c.estat === 'multiple' ? desv : ''} <span class="marca" aria-label="${esc(est.text)}">${est.marca}</span></td>`;
    }).join('')}</tr>`).join('')
    : `<tr><td colspan="${ctl.canals.length + 1}" class="text-center py-3" style="color:#15803d">Cap mes amb avisos.</td></tr>`;
}

async function carregarControl() {
  ctl = await json('api_importacions.php?accio=control');
  document.getElementById('llindar').textContent = Math.round(ctl.llindar_desviacio * 100);
  const r = ctl.resum, pills = [
    ['falta', 'mesos que falten', '#dc2626'], ['repetit', 'pujats dos cops', '#dc2626'],
    ['multiple', 'amb diverses importacions', '#b45309'], ['desviacio', 'amb desviació gran', '#b45309'], ['ok', 'correctes', '#15803d'],
  ];
  document.getElementById('ctlResum').innerHTML = pills.filter(([k]) => r[k] > 0 || k === 'ok')
    .map(([k, t, col]) => `<span class="ctl-pill" style="color:${col}">${r[k]} ${t}</span>`).join('');
  document.getElementById('nomesAvisos').checked = (r.falta + r.repetit + r.multiple + r.desviacio) > 0;
  pintarControl();
}
document.getElementById('nomesAvisos').addEventListener('change', pintarControl);
document.getElementById('ctlTot').addEventListener('click', () => { mostrarTot = true; pintarControl(); });
carregarControl();
</script>
</body>
</html>
