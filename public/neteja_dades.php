<?php
require __DIR__ . '/../config/config.php';
require __DIR__ . '/../src/Auth.php';
Auth::requerirLogin();
?>
<!doctype html>
<html lang="ca">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Neteja de dades · Espai Cràter</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
  :root { --lava:#c0392b; --lava-dark:#7b241c; --ember:#e67e22; --ash:#2c3e50; --bg:#f5f3f0; --green:#1e8449; }
  * { font-family:'Inter',system-ui,sans-serif; }
  body { background:var(--bg); color:#2b2b2b; }
  .topbar { background:linear-gradient(120deg,var(--lava-dark),var(--lava) 60%,var(--ember)); color:#fff; box-shadow:0 2px 12px rgba(0,0,0,.15); }
  .panel { background:#fff; border:none; border-radius:16px; box-shadow:0 4px 20px rgba(0,0,0,.06); }
  .panel h5 { font-weight:700; color:var(--ash); margin:0 0 .3rem 0; display:flex; align-items:center; gap:.5rem; }
  .panel h5::before { content:""; width:6px; height:20px; border-radius:3px; background:var(--lava); display:inline-block; }
  .panel-perill { border:2px solid #dc3545; }
  .panel-perill h5::before { background:#dc3545; }
  .btn-lava { background:var(--lava); color:#fff; font-weight:600; border:none; }
  .btn-lava:hover { background:var(--lava-dark); color:#fff; }
  .btn-perill { background:#dc3545; color:#fff; font-weight:700; border:none; }
  .btn-perill:hover { background:#a52834; color:#fff; }
  .btn-perill:disabled { background:#e79a9a; }
  .previsualitzacio { background:#fff8e1; border-left:4px solid var(--ember); border-radius:8px; padding:.9rem 1.1rem; font-size:.92rem; }
  .previsualitzacio-perill { background:#fdecea; border-left:4px solid #dc3545; border-radius:8px; padding:.9rem 1.1rem; font-size:.92rem; }
  .previsualitzacio b, .previsualitzacio-perill b { font-size:1.1rem; }
  .taula-historial { font-size:.85rem; }
  .taula-historial thead th { background:#f3f1ee; font-size:.75rem; text-transform:uppercase; color:#6b6b6b; }
  .badge-tipus-parcial { background:#e8eef2; color:var(--ash); font-weight:600; }
  .badge-tipus-total { background:#fdecea; color:#dc3545; font-weight:700; }
  .form-label.fw-bold { color:var(--ash); font-size:.8rem; text-transform:uppercase; letter-spacing:.4px; }
</style>
</head>
<body>

<nav class="topbar mb-4">
  <div class="container-fluid py-3 px-4 d-flex justify-content-between align-items-center">
    <span class="brand h4 mb-0 d-flex align-items-center gap-2">
      <img src="assets/logo-icona.png" alt="" style="height:30px; filter:brightness(0) invert(1)">
      Espai Cràter · Neteja de dades
    </span>
    <a href="index.php" class="btn btn-light btn-sm fw-semibold">← Dashboard</a>
  </div>
</nav>

<div class="container-fluid px-4 pb-5" style="max-width:980px">

  <div class="panel p-3 mb-4">
    <p class="mb-0 text-muted" style="font-size:.9rem">
      Serveix per corregir errors d'importació (per exemple, un mes carregat malament) sense
      haver de repassar-lo fila a fila. Totes les accions queden registrades a l'historial de
      sota, amb qui les ha fetes.
    </p>
  </div>

  <!-- Neteja parcial -->
  <div class="panel p-4 mb-4">
    <h5>Neteja parcial</h5>
    <p class="text-muted mb-3" style="font-size:.85rem">
      Tria almenys un criteri. Com més criteris marquis, més concret serà l'esborrat.
    </p>

    <div class="row g-3 mb-3">
      <div class="col-md-3">
        <label class="form-label fw-bold">Any</label>
        <select id="fAny" class="form-select form-select-sm"><option value="">Tots</option></select>
      </div>
      <div class="col-md-3">
        <label class="form-label fw-bold">Mes</label>
        <select id="fMes" class="form-select form-select-sm"><option value="">Tots</option></select>
      </div>
      <div class="col-md-3">
        <label class="form-label fw-bold">Canal</label>
        <select id="fCanal" class="form-select form-select-sm"><option value="">Tots</option></select>
      </div>
      <div class="col-md-3">
        <label class="form-label fw-bold">Producte</label>
        <select id="fGrup" class="form-select form-select-sm"><option value="">Tots</option></select>
      </div>
    </div>

    <div class="form-check mb-3">
      <input type="checkbox" class="form-check-input" id="fManual">
      <label class="form-check-label" for="fManual" style="font-size:.88rem">
        Incloure també les entrades manuals (per defecte es protegeixen i no s'esborren)
      </label>
    </div>

    <button id="btnPrevisualitzar" class="btn btn-outline-secondary btn-sm mb-3">🔍 Calcular abast</button>

    <div id="resultatPrevisualitzacio"></div>

    <div id="zonaConfirmacioParcial" class="mt-3" style="display:none">
      <label class="form-label fw-bold">Escriu <code>ELIMINAR</code> per confirmar</label>
      <div class="d-flex gap-2">
        <input type="text" id="confirmacioParcial" class="form-control form-control-sm" style="max-width:220px">
        <button id="btnEliminarParcial" class="btn btn-perill btn-sm" disabled>🗑️ Eliminar definitivament</button>
      </div>
    </div>
    <div id="missatgeParcial" class="mt-2" style="font-size:.85rem"></div>
  </div>

<?php if (Auth::esAdmin()): ?>
  <!-- Zona de perill: esborrat total -->
  <div class="panel panel-perill p-4 mb-4">
    <h5 class="text-danger">⚠ Zona de perill — Esborrar TOTES les dades</h5>
    <p class="text-muted mb-3" style="font-size:.85rem">
      Esborra totes les entrades i tot l'historial d'importacions (manté usuaris, catàlegs de
      productes, províncies i comarques). <b>Fes-ho servir només en cas d'incidència greu</b>,
      quan calgui tornar a bolcar totes les dades des de zero. Abans de continuar, assegura't que
      tens una còpia recent (exporta un Excel/PDF des del dashboard, o demana una còpia de
      seguretat de la base de dades).
    </p>

    <button id="btnPrevisualitzarTotal" class="btn btn-outline-danger btn-sm mb-3">🔍 Veure què s'esborraria</button>
    <div id="resultatPrevisualitzacioTotal"></div>

    <div id="zonaConfirmacioTotal" class="mt-3" style="display:none">
      <label class="form-label fw-bold text-danger">Escriu exactament <code>ESBORRAR TOTES LES DADES</code></label>
      <div class="d-flex gap-2">
        <input type="text" id="confirmacioTotal" class="form-control form-control-sm" style="max-width:280px">
        <button id="btnEliminarTotal" class="btn btn-perill btn-sm" disabled>🗑️ Esborrar-ho tot definitivament</button>
      </div>
    </div>
    <div id="missatgeTotal" class="mt-2" style="font-size:.85rem"></div>
  </div>
  <?php endif; ?>

  <!-- Historial -->
  <div class="panel p-4">
    <h5>Historial de neteges <span class="text-muted fw-normal" style="font-size:.8rem">(informe de qui ha eliminat què, per poder refer-ho si cal)</span></h5>
    <div class="table-responsive mt-2">
      <table class="table table-sm taula-historial mb-0">
        <thead><tr><th>Data</th><th>Usuari</th><th>Tipus</th><th>Criteris</th><th class="text-end">Files</th><th class="text-end">Pax</th></tr></thead>
        <tbody id="cosHistorial"></tbody>
      </table>
    </div>
  </div>

</div>

<script>
async function json(url, opts) { return (await fetch(url, opts)).json(); }
async function post(url, params) {
  return json(url, { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:new URLSearchParams(params) });
}
const nf = n => Number(n).toLocaleString('ca');
const MESOS = ['Gen','Feb','Mar','Abr','Mai','Jun','Jul','Ago','Set','Oct','Nov','Des'];

async function carregarFiltres() {
  const periodes = await json('api_dades.php?accio=periodes_disponibles');
  const anys = [...new Set(periodes.map(p => p.any_))].sort((a,b) => b-a);
  const mesos = [...new Set(periodes.map(p => p.mes))].sort((a,b) => a-b);
  anys.forEach(a => document.getElementById('fAny').add(new Option(a, a)));
  mesos.forEach(m => document.getElementById('fMes').add(new Option(MESOS[m-1], m)));

  const catalegs = await json('api_errors.php?accio=catalegs');
  catalegs.canals.forEach(c => document.getElementById('fCanal').add(new Option(c.nom, c.codi)));

  const grups = await json('api_dades.php?accio=productes_disponibles');
  grups.forEach(g => document.getElementById('fGrup').add(new Option(g.grup, g.grup)));
}

function filtresActuals() {
  return {
    any: document.getElementById('fAny').value,
    mes: document.getElementById('fMes').value,
    canal: document.getElementById('fCanal').value,
    grup: document.getElementById('fGrup').value,
    incloure_manual: document.getElementById('fManual').checked ? '1' : '',
  };
}

document.getElementById('btnPrevisualitzar').addEventListener('click', async () => {
  const f = filtresActuals();
  const q = new URLSearchParams(f);
  const res = await json('api_neteja.php?accio=previsualitzar_parcial&' + q);
  const cont = document.getElementById('resultatPrevisualitzacio');
  const zona = document.getElementById('zonaConfirmacioParcial');

  if (res.error) {
    cont.innerHTML = `<div class="previsualitzacio text-danger">✗ ${res.error}</div>`;
    zona.style.display = 'none';
    return;
  }

  if (res.files === 0) {
    cont.innerHTML = `<div class="previsualitzacio">No hi ha cap entrada que coincideixi amb aquests criteris (${res.descripcio}). No cal esborrar res.</div>`;
    zona.style.display = 'none';
    return;
  }

  cont.innerHTML = `<div class="previsualitzacio">
    Això eliminarà <b>${nf(res.files)} files</b> (<b>${nf(res.pax)} pax</b>) corresponents a: ${res.descripcio}.
  </div>`;
  zona.style.display = 'block';
  window._ultimFiltreParcial = f;
});

document.getElementById('confirmacioParcial').addEventListener('input', (e) => {
  document.getElementById('btnEliminarParcial').disabled = e.target.value.trim() !== 'ELIMINAR';
});

document.getElementById('btnEliminarParcial').addEventListener('click', async () => {
  const btn = document.getElementById('btnEliminarParcial');
  const missatge = document.getElementById('missatgeParcial');
  btn.disabled = true; btn.textContent = 'Eliminant…';

  const params = { ...window._ultimFiltreParcial, confirmacio: document.getElementById('confirmacioParcial').value.trim() };
  const res = await post('api_neteja.php', params);

  btn.textContent = '🗑️ Eliminar definitivament';
  if (res.ok) {
    missatge.className = 'mt-2 text-success fw-semibold';
    missatge.textContent = `✓ Eliminades ${nf(res.files_eliminades)} files (${nf(res.pax_eliminats)} pax).`;
    document.getElementById('resultatPrevisualitzacio').innerHTML = '';
    document.getElementById('zonaConfirmacioParcial').style.display = 'none';
    document.getElementById('confirmacioParcial').value = '';
    carregarHistorial();
  } else {
    btn.disabled = false;
    missatge.className = 'mt-2 text-danger fw-semibold';
    missatge.textContent = '✗ ' + (res.error || 'Error desconegut');
  }
});

// Aquest bloc només existeix al DOM per a usuaris admin — es protegeix
// amb aquest guard perquè la resta de la pàgina no falli per a la resta
// d'usuaris (que no tenen accés a la zona de perill).
const btnPrevTotal = document.getElementById('btnPrevisualitzarTotal');
if (btnPrevTotal) {
  btnPrevTotal.addEventListener('click', async () => {
    const res = await json('api_neteja.php?accio=previsualitzar_total');
    const cont = document.getElementById('resultatPrevisualitzacioTotal');
    const zona = document.getElementById('zonaConfirmacioTotal');

    if (res.files === 0) {
      cont.innerHTML = `<div class="previsualitzacio-perill">No hi ha cap entrada a la base de dades. No cal esborrar res.</div>`;
      zona.style.display = 'none';
      return;
    }

    cont.innerHTML = `<div class="previsualitzacio-perill">
      Això eliminarà <b>TOTES</b> les entrades: <b>${nf(res.files)} files</b> (<b>${nf(res.pax)} pax</b>) i tot l'historial d'importacions.
    </div>`;
    zona.style.display = 'block';
  });

  document.getElementById('confirmacioTotal').addEventListener('input', (e) => {
    document.getElementById('btnEliminarTotal').disabled = e.target.value.trim() !== 'ESBORRAR TOTES LES DADES';
  });

  document.getElementById('btnEliminarTotal').addEventListener('click', async () => {
    if (!confirm('Última confirmació: s\'esborraran TOTES les dades de visitants. Aquesta acció NO es pot desfer. Continuar?')) return;

    const btn = document.getElementById('btnEliminarTotal');
    const missatge = document.getElementById('missatgeTotal');
    btn.disabled = true; btn.textContent = 'Esborrant…';

    const res = await post('api_neteja.php', { accio:'eliminar_total', confirmacio: document.getElementById('confirmacioTotal').value.trim() });

    btn.textContent = '🗑️ Esborrar-ho tot definitivament';
    if (res.ok) {
      missatge.className = 'mt-2 text-success fw-semibold';
      missatge.textContent = `✓ Eliminades ${nf(res.files_eliminades)} files (${nf(res.pax_eliminats)} pax). Base de dades neta.`;
      document.getElementById('resultatPrevisualitzacioTotal').innerHTML = '';
      document.getElementById('zonaConfirmacioTotal').style.display = 'none';
      document.getElementById('confirmacioTotal').value = '';
      carregarHistorial();
    } else {
      btn.disabled = false;
      missatge.className = 'mt-2 text-danger fw-semibold';
      missatge.textContent = '✗ ' + (res.error || 'Error desconegut');
    }
  });
}

async function carregarHistorial() {
  const files = await json('api_neteja.php?accio=historial');
  const cos = document.getElementById('cosHistorial');
  if (!files.length) { cos.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-3">Encara no s\'ha fet cap neteja.</td></tr>'; return; }
  cos.innerHTML = files.map(f => `
    <tr>
      <td>${new Date(f.data_eliminacio).toLocaleString('ca')}</td>
      <td>${f.nom_usuari ?? '—'}</td>
      <td><span class="badge badge-tipus-${f.tipus}">${f.tipus === 'total' ? 'TOTAL' : 'Parcial'}</span></td>
      <td style="font-size:.82rem">${f.criteris ?? ''}</td>
      <td class="text-end">${nf(f.files_eliminades)}</td>
      <td class="text-end">${nf(f.pax_eliminats)}</td>
    </tr>`).join('');
}

(async () => {
  await carregarFiltres();
  await carregarHistorial();
})();
</script>
</body>
</html>
