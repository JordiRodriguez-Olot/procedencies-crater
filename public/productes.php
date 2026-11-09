<?php
require __DIR__ . '/../config/config.php';
require __DIR__ . '/../src/Auth.php';
Auth::requerirLogin();
?>
<?php renderCapcalera('Gestió de productes', usuariActual()); ?>
<style>
  :root { --lava:#c0392b; --lava-dark:#7b241c; --ember:#e67e22; --ash:#2c3e50; --bg:#f5f3f0; --green:#1e8449; }
  .panel { background:#fff; border:none; border-radius:16px; box-shadow:0 4px 20px rgba(0,0,0,.06); }
  .panel h5 { font-weight:700; color:var(--ash); margin:0 0 1rem 0; display:flex; align-items:center; gap:.5rem; }
  .panel h5::before { content:""; width:6px; height:20px; border-radius:3px; background:var(--lava); display:inline-block; }
  .btn-lava { background:var(--lava); color:#fff; font-weight:600; border:none; }
  .btn-lava:hover { background:var(--lava-dark); color:#fff; }
  .taula-prod { font-size:.9rem; }
  .taula-prod thead th { background:#f3f1ee; font-size:.78rem; text-transform:uppercase; color:#6b6b6b; }
  .fila-sense-grup { background:#fff8e1; }
  .badge-previst { background:#e8eef2; color:var(--ash); font-size:.75rem; font-weight:600; padding:.2rem .55rem; border-radius:999px; }
  .muted-hint { font-size:.8rem; color:#8a8a8a; font-weight:400; }

  .nav-pills .nav-link { color:var(--ash); font-weight:600; font-size:.9rem; }
  .nav-pills .nav-link.active { background:var(--lava); }

  .targeta-grup { border:1px solid #eee7e2; border-radius:12px; margin-bottom:1rem; overflow:hidden; }
  .targeta-grup summary { cursor:pointer; list-style:none; padding:.9rem 1.2rem; background:#faf8f6;
    display:flex; justify-content:space-between; align-items:center; gap:1rem; }
  .targeta-grup summary::-webkit-details-marker { display:none; }
  .targeta-grup summary .fletxa { transition:transform .15s; color:#9a9a9a; }
  .targeta-grup[open] summary .fletxa { transform:rotate(90deg); }
  .targeta-grup .nom-grup { font-weight:700; color:var(--ash); font-size:1rem; }
  .targeta-grup .stats-grup { font-size:.82rem; color:#8a8a8a; white-space:nowrap; }
  .targeta-grup .cos-productes { padding:.3rem 1.2rem 1rem 1.2rem; }
  .fila-producte-grup {
    display:flex; justify-content:space-between; align-items:center; gap:1rem;
    padding:.5rem 0; border-bottom:1px solid #f3f1ee; font-size:.88rem;
  }
  .fila-producte-grup:last-child { border-bottom:none; }
  .fila-producte-grup .nom-prod { flex:1; min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
  .fila-producte-grup .pax-prod { color:#8a8a8a; font-size:.82rem; white-space:nowrap; }
  .btn-treure-prod { background:none; border:none; color:#c9c2ba; font-size:1rem; line-height:1;
    padding:.15rem .3rem; border-radius:6px; }
  .btn-treure-prod:hover { color:#dc3545; background:#fdecea; }
  .btn-afegir-prod { background:none; border:1px dashed #c9c2ba; color:var(--lava); font-weight:600;
    font-size:.85rem; border-radius:8px; padding:.4rem .8rem; width:100%; text-align:left; margin-top:.5rem; }
  .btn-afegir-prod:hover { background:#fdf5f2; border-color:var(--lava); }
  .fila-afegir-prod { display:flex; gap:.5rem; margin-top:.5rem; align-items:center; }
  .targeta-sense-grup summary { background:#fff8e1; }
  .targeta-sense-grup .nom-grup { color:#8a6400; }
</style>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
  <div class="titol-pagina m-0"><h1>Gestió de productes</h1></div>
  <div class="d-flex gap-2 flex-wrap align-items-center">
      
  </div>
</div>


<div class="container-fluid px-0 pb-5">

  <div class="panel p-3 mb-4">
    <p class="mb-0 text-muted" style="font-size:.9rem">
      Assigna cada producte de Clorian a un grup, per poder-los filtrar conjuntament al dashboard.
      Els productes sense grup assignat surten destacats. També pots preparar el mapatge d'un
      producte <b>abans</b> que arribi a cap importació (secció de baix).
    </p>
  </div>

  <div class="panel p-4 mb-4">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
      <ul class="nav nav-pills" id="pestanyesVista">
        <li class="nav-item"><button class="nav-link active" data-vista="productes">📋 Vista per productes</button></li>
        <li class="nav-item"><button class="nav-link" data-vista="grups">🗂️ Vista per grups</button></li>
      </ul>
    </div>

    <div id="vistaProductes">
      <h5 class="d-none">Productes importats</h5>
      <div class="table-responsive">
        <table class="table table-hover taula-prod align-middle">
          <thead>
            <tr><th>Producte (nom original)</th><th class="text-end">Files</th><th class="text-end">Pax</th>
                <th style="min-width:260px">Grup</th><th style="width:110px"></th></tr>
          </thead>
          <tbody id="cosProductes"></tbody>
        </table>
      </div>
    </div>

    <div id="vistaGrups" style="display:none">
      <p class="text-muted mb-3" style="font-size:.85rem">
        Cada targeta és un grup, amb els productes que hi ha agrupats a dins. Pots reassignar
        qualsevol producte a un altre grup directament des d'aquí.
      </p>
      <div id="cosGrupsVisual"></div>
    </div>
  </div>

  <div class="panel p-4 mb-4">
    <h5>Mapatges previstos <span class="muted-hint">(productes que encara no han aparegut en cap importació)</span></h5>
    <div class="row g-3 align-items-end mb-3">
      <div class="col-md-5">
        <label class="form-label fw-bold" style="font-size:.8rem">Nom exacte del producte a Clorian</label>
        <input type="text" id="previstNom" class="form-control form-control-sm" placeholder="ex: Visita Guiada Apropa Cultura">
      </div>
      <div class="col-md-4">
        <label class="form-label fw-bold" style="font-size:.8rem">Grup</label>
        <input list="llistaGrups" id="previstGrup" class="form-control form-control-sm" placeholder="Selecciona o escriu-ne un de nou">
        <datalist id="llistaGrups"></datalist>
      </div>
      <div class="col-md-3">
        <button id="btnAfegirPrevist" class="btn btn-lava btn-sm w-100">+ Afegir mapatge previst</button>
      </div>
    </div>
    <div id="missatgePrevist" class="mb-2" style="font-size:.85rem"></div>
    <div class="table-responsive">
      <table class="table table-sm taula-prod mb-0">
        <thead><tr><th>Producte</th><th>Grup assignat</th><th style="width:90px"></th></tr></thead>
        <tbody id="cosPrevistos"></tbody>
      </table>
    </div>
  </div>

  <div class="panel p-4">
    <h5>Fusionar dos grups <span class="muted-hint">(si vas crear-ne dos pel mateix concepte per error)</span></h5>
    <div class="row g-3 align-items-end">
      <div class="col-md-4">
        <label class="form-label fw-bold" style="font-size:.8rem">Grup origen (desapareixerà)</label>
        <select id="fusioOrigen" class="form-select form-select-sm"></select>
      </div>
      <div class="col-md-1 text-center fw-bold text-muted">→</div>
      <div class="col-md-4">
        <label class="form-label fw-bold" style="font-size:.8rem">Grup destí (es conservarà)</label>
        <select id="fusioDesti" class="form-select form-select-sm"></select>
      </div>
      <div class="col-md-3">
        <button id="btnFusionar" class="btn btn-outline-danger btn-sm w-100">Fusionar grups</button>
      </div>
    </div>
    <div id="missatgeFusio" class="mt-2" style="font-size:.85rem"></div>
  </div>

</div>

<?php renderPeu([], false); ?>
<script>
const nf = n => Number(n).toLocaleString('ca');
async function json(url, opts) { return (await fetch(url, opts)).json(); }
async function post(params) {
  return json('api_productes.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:new URLSearchParams(params) });
}

let grupsExistents = [];

function esc(s) {
  return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

async function carregarVistaGrups() {
  const files = await json('api_productes.php?accio=llistar');
  const grups = {};
  files.forEach(f => {
    const clau = f.grup || '(Sense grup assignat)';
    if (!grups[clau]) grups[clau] = { productes: [], files: 0, pax: 0 };
    grups[clau].productes.push(f);
    grups[clau].files += Number(f.files);
    grups[clau].pax += Number(f.pax);
  });

  const clausOrdenades = Object.keys(grups).sort((a, b) => {
    if (a === '(Sense grup assignat)') return 1;
    if (b === '(Sense grup assignat)') return -1;
    return grups[b].pax - grups[a].pax;
  });

  const cont = document.getElementById('cosGrupsVisual');
  cont.innerHTML = '';

  if (!clausOrdenades.length) {
    cont.innerHTML = '<p class="text-muted text-center py-4">Encara no hi ha cap producte importat.</p>';
    return;
  }

  clausOrdenades.forEach((clau, idx) => {
    const g = grups[clau];
    const senseGrup = clau === '(Sense grup assignat)';
    const details = document.createElement('details');
    details.className = 'targeta-grup' + (senseGrup ? ' targeta-sense-grup' : '');
    if (idx === 0 && !senseGrup) details.open = true;
    details.innerHTML = `
      <summary>
        <span class="d-flex align-items-center gap-2">
          <span class="fletxa">▶</span>
          <span class="nom-grup">${esc(clau)}</span>
        </span>
        <span class="stats-grup">${g.productes.length} producte${g.productes.length===1?'':'s'} · ${nf(g.files)} files · <b>${nf(g.pax)} pax</b></span>
      </summary>
      <div class="cos-productes"></div>`;

    const cosProductes = details.querySelector('.cos-productes');

    // Fila neta: nom + pax, sense cap selector — el producte simplement
    // "hi és" dins la targeta, sense donar la sensació que està a punt de marxar.
    // Un botó discret "✕" permet treure'l d'aquest grup (torna a "sense grup").
    [...g.productes].sort((a,b) => b.pax - a.pax).forEach(p => {
      const fila = document.createElement('div');
      fila.className = 'fila-producte-grup';
      fila.innerHTML = `
        <span class="nom-prod" title="${esc(p.nom_original)}">${esc(p.nom_original)}</span>
        <span class="pax-prod">${nf(p.pax)} pax</span>
        ${senseGrup ? '' : '<button class="btn-treure-prod" type="button" title="Treure d\'aquest grup">✕</button>'}`;

      if (!senseGrup) {
        fila.querySelector('.btn-treure-prod').addEventListener('click', async () => {
          if (!confirm(`Treure "${p.nom_original}" del grup "${clau}"? Tornarà a quedar sense grup assignat.`)) return;
          const res = await post({ accio:'eliminar_previst', nom_original: p.nom_original });
          if (res.ok) { await carregarGrups(); carregarVistaGrups(); carregarProductes(); }
          else alert('Error: ' + (res.error || '?'));
        });
      }
      cosProductes.appendChild(fila);
    });

    // Botó "+ Afegir producte" — nomes per a grups reals (no per "Sense
    // grup assignat", que no és un grup de destí vàlid).
    if (!senseGrup) {
      const zonaAfegir = document.createElement('div');
      zonaAfegir.innerHTML = `<button class="btn-afegir-prod" type="button">+ Afegir producte a aquest grup</button>`;
      const btnAfegir = zonaAfegir.querySelector('button');

      btnAfegir.addEventListener('click', () => {
        const disponibles = files.filter(f => (f.grup || '(Sense grup assignat)') !== clau);
        if (!disponibles.length) { alert('Tots els productes ja són en aquest grup.'); return; }

        zonaAfegir.innerHTML = `
          <div class="fila-afegir-prod">
            <select class="form-select form-select-sm" style="flex:1"><option value="">Tria un producte…</option></select>
            <button class="btn btn-lava btn-sm" data-confirmar>Afegir</button>
            <button class="btn btn-outline-secondary btn-sm" data-cancelar>✕</button>
          </div>`;
        const sel = zonaAfegir.querySelector('select');
        disponibles
          .sort((a,b) => a.nom_original.localeCompare(b.nom_original))
          .forEach(f => sel.add(new Option(`${f.nom_original} (ara: ${f.grup || 'sense grup'})`, f.nom_original)));

        zonaAfegir.querySelector('[data-cancelar]').addEventListener('click', () => {
          zonaAfegir.innerHTML = `<button class="btn-afegir-prod" type="button">+ Afegir producte a aquest grup</button>`;
          zonaAfegir.querySelector('button').addEventListener('click', () => btnAfegir.click());
        });

        zonaAfegir.querySelector('[data-confirmar]').addEventListener('click', async () => {
          const nomOriginal = sel.value;
          if (!nomOriginal) return alert('Tria un producte.');
          const btn = zonaAfegir.querySelector('[data-confirmar]');
          btn.disabled = true; btn.textContent = '…';
          const res = await post({ accio:'assignar', nom_original: nomOriginal, grup: clau });
          if (res.ok) { await carregarGrups(); carregarVistaGrups(); carregarProductes(); }
          else { btn.disabled = false; btn.textContent = 'Afegir'; alert('Error: ' + (res.error || '?')); }
        });
      });

      cosProductes.appendChild(zonaAfegir);
    }

    cont.appendChild(details);
  });
}

document.querySelectorAll('#pestanyesVista .nav-link').forEach(btn => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('#pestanyesVista .nav-link').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    const esGrups = btn.dataset.vista === 'grups';
    document.getElementById('vistaProductes').style.display = esGrups ? 'none' : 'block';
    document.getElementById('vistaGrups').style.display = esGrups ? 'block' : 'none';
    if (esGrups) carregarVistaGrups();
  });
});

async function carregarGrups() {
  grupsExistents = await json('api_productes.php?accio=grups_existents');
  document.getElementById('llistaGrups').innerHTML = grupsExistents.map(g => `<option value="${g}">`).join('');
  const selO = document.getElementById('fusioOrigen'), selD = document.getElementById('fusioDesti');
  selO.innerHTML = grupsExistents.map(g => `<option value="${g}">${g}</option>`).join('');
  selD.innerHTML = selO.innerHTML;
}

async function carregarProductes() {
  const files = await json('api_productes.php?accio=llistar');
  const cos = document.getElementById('cosProductes');
  cos.innerHTML = '';
  files.forEach(f => {
    const tr = document.createElement('tr');
    if (!f.grup) tr.classList.add('fila-sense-grup');
    tr.innerHTML = `
      <td>${f.nom_original}${!f.grup ? ' <span class="badge bg-warning text-dark" style="font-size:.7rem">sense grup</span>' : ''}</td>
      <td class="text-end">${nf(f.files)}</td>
      <td class="text-end fw-semibold">${nf(f.pax)}</td>
      <td>
        <div class="input-group input-group-sm">
          <input list="llistaGrups" class="form-control" data-grup value="${f.grup ?? ''}" placeholder="Assigna un grup...">
        </div>
      </td>
      <td><button class="btn btn-lava btn-sm w-100" data-desar>Desar</button></td>`;

    tr.querySelector('[data-desar]').addEventListener('click', async () => {
      const grup = tr.querySelector('[data-grup]').value.trim();
      if (!grup) return alert('Escriu un nom de grup.');
      const btn = tr.querySelector('[data-desar]'); btn.disabled = true; btn.textContent = '…';
      const res = await post({ accio:'assignar', nom_original: f.nom_original, grup });
      btn.disabled = false; btn.textContent = 'Desar';
      if (res.ok) { await carregarGrups(); carregarProductes(); }
      else alert('Error: ' + (res.error || '?'));
    });
    cos.appendChild(tr);
  });
}

async function carregarPrevistos() {
  const files = await json('api_productes.php?accio=previstos');
  const cos = document.getElementById('cosPrevistos');
  if (!files.length) {
    cos.innerHTML = '<tr><td colspan="3" class="text-center text-muted py-3">Cap mapatge previst pendent.</td></tr>';
    return;
  }
  cos.innerHTML = files.map(f => `
    <tr data-nom="${f.nom_original}">
      <td>${f.nom_original}</td>
      <td><span class="badge-previst">${f.grup}</span></td>
      <td><button class="btn btn-outline-danger btn-sm" data-eliminar>Eliminar</button></td>
    </tr>`).join('');

  cos.querySelectorAll('[data-eliminar]').forEach(btn => btn.addEventListener('click', async (e) => {
    const tr = e.target.closest('tr');
    if (!confirm('Eliminar aquest mapatge previst?')) return;
    const res = await post({ accio:'eliminar_previst', nom_original: tr.dataset.nom });
    if (res.ok) { await carregarGrups(); carregarPrevistos(); }
    else alert('Error: ' + (res.error || '?'));
  }));
}

document.getElementById('btnAfegirPrevist').addEventListener('click', async () => {
  const nom = document.getElementById('previstNom').value.trim();
  const grup = document.getElementById('previstGrup').value.trim();
  const missatge = document.getElementById('missatgePrevist');
  if (!nom || !grup) { missatge.className = 'text-danger mb-2'; missatge.textContent = 'Cal indicar el nom del producte i el grup.'; return; }

  const res = await post({ accio:'assignar', nom_original: nom, grup });
  if (res.ok) {
    missatge.className = 'text-success mb-2';
    missatge.textContent = `✓ Mapatge desat: "${nom}" → grup "${grup}". Quan aquest producte aparegui en una importació, ja s'assignarà automàticament.`;
    document.getElementById('previstNom').value = '';
    document.getElementById('previstGrup').value = '';
    await carregarGrups();
    carregarPrevistos();
    carregarProductes();
  } else {
    missatge.className = 'text-danger mb-2';
    missatge.textContent = 'Error: ' + (res.error || '?');
  }
});

document.getElementById('btnFusionar').addEventListener('click', async () => {
  const origen = document.getElementById('fusioOrigen').value;
  const desti = document.getElementById('fusioDesti').value;
  const missatge = document.getElementById('missatgeFusio');
  if (origen === desti) { missatge.className = 'text-danger'; missatge.textContent = 'Tria dos grups diferents.'; return; }
  if (!confirm(`Tots els productes del grup "${origen}" passaran a "${desti}". Continuar?`)) return;

  const res = await post({ accio:'fusionar', grup_origen: origen, grup_desti: desti });
  if (res.ok) {
    missatge.className = 'text-success';
    missatge.textContent = `✓ Fusionats ${res.afectats} productes de "${origen}" cap a "${desti}".`;
    await carregarGrups();
    carregarProductes();
    carregarPrevistos();
  } else {
    missatge.className = 'text-danger';
    missatge.textContent = 'Error: ' + (res.error || '?');
  }
});

(async () => {
  await carregarGrups();
  await carregarProductes();
  await carregarPrevistos();
})();
</script>
</body>
</html>
