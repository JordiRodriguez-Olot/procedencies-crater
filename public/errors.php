<?php
require __DIR__ . '/../config/config.php';
require __DIR__ . '/../src/Auth.php';
Auth::requerirLogin();
?>
<?php renderCapcalera("Gestió d'errors", usuariActual()); ?>
<style>
  :root { --lava:#c0392b; --lava-dark:#7b241c; --ember:#e67e22; --ash:#2c3e50; --bg:#f5f3f0; --green:#1e8449; }
  .panel { background:#fff; border:none; border-radius:16px; box-shadow:0 4px 20px rgba(0,0,0,.06); }

  .cat { border-radius:14px; padding:1rem 1.1rem; cursor:pointer; border:2px solid transparent; transition:.15s; background:#fff; box-shadow:0 3px 14px rgba(0,0,0,.05); height:100%; }
  .cat:hover { transform:translateY(-2px); box-shadow:0 6px 20px rgba(0,0,0,.10); }
  .cat.actiu { border-color:var(--lava); }
  .cat .titol { font-weight:600; font-size:.9rem; color:var(--ash); }
  .cat .num { font-size:1.7rem; font-weight:800; line-height:1.1; }
  .cat .pax { font-size:.76rem; color:#8a8a8a; }
  .cat.ok .num { color:#27ae60; }
  .cat.err .num { color:var(--lava); }

  .btn-lava { background:var(--lava); color:#fff; font-weight:600; border:none; }
  .btn-lava:hover { background:var(--lava-dark); color:#fff; }
  .btn-sug { background:var(--green); color:#fff; font-weight:600; border:none; }
  .btn-sug:hover { background:#166638; color:#fff; }
  .taula-err { font-size:.9rem; }
  .taula-err thead th { background:#f3f1ee; position:sticky; top:0; z-index:2; font-size:.82rem; text-transform:uppercase; letter-spacing:.3px; color:#6b6b6b; }
  .taula-err td { vertical-align:middle; }
  .badge-cp { font-family:monospace; font-size:.95rem; font-weight:600; }
  .pill-info { background:#fdece8; color:var(--lava-dark); border-radius:999px; padding:.15rem .7rem; font-size:.8rem; font-weight:600; display:inline-block; }
  .sug-txt { color:var(--green); font-weight:600; }
  .sug-ref { color:#8a8a8a; font-size:.8rem; font-weight:400; }
  .dup-alerta { background:#fff8e1; border-left:4px solid var(--ember); border-radius:8px; padding:.45rem .7rem; font-size:.84rem; }
  .fila-manual td { background:#faf7f4; }
  .arrow { color:var(--ember); font-weight:700; }
</style>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
  <div class="titol-pagina m-0"><h1>Gestió d'errors</h1></div>
  <div class="d-flex gap-2 flex-wrap align-items-center">
      <button id="btnRederivar" class="btn btn-outline-primary btn-sm fw-semibold" title="Torna a calcular província i comarca de totes les entrades">↻ Re-derivar tot</button>
      <a href="revisio_massiva.php" class="btn btn-outline-primary btn-sm fw-semibold">🚀 Revisió massiva</a>
  </div>
</div>


<div class="container-fluid px-0 pb-5">

  <div class="panel p-3 mb-4">
    <p class="mb-0 text-muted" style="font-size:.9rem">
      Cada categoria mostra només el que cal per resoldre-la. Quan el sistema pot deduir el valor correcte,
      el proposa i n'hi ha prou amb <b>un clic verd</b> per aplicar-lo.
    </p>
  </div>

  <div class="row g-3 mb-4" id="categories"></div>

  <!-- Injecció manual -->
  <div class="panel p-4 mb-4">
    <div class="d-flex justify-content-between align-items-center mb-2" style="cursor:pointer" id="toggleManual">
      <h5 class="mb-0">➕ Afegir entrada manual <span class="text-muted fw-normal" style="font-size:.82rem">(casos excepcionals, quan les dades no quadren i no hi ha error detectable)</span></h5>
      <span id="fletxaManual">▾</span>
    </div>
    <div id="cosManual" style="display:none">
      <hr>
      <div class="row g-3">
        <div class="col-md-2">
          <label class="form-label fw-bold" style="font-size:.8rem">Canal</label>
          <select id="mCanal" class="form-select form-select-sm"></select>
        </div>
        <div class="col-md-3">
          <label class="form-label fw-bold" style="font-size:.8rem">Producte</label>
          <input list="llistaProductes" id="mProducte" class="form-control form-control-sm" placeholder="Selecciona o escriu-ne un de nou">
          <datalist id="llistaProductes"></datalist>
        </div>
        <div class="col-md-2">
          <label class="form-label fw-bold" style="font-size:.8rem">Pax</label>
          <input type="number" min="1" id="mPax" class="form-control form-control-sm">
        </div>
        <div class="col-md-2">
          <label class="form-label fw-bold" style="font-size:.8rem">Any</label>
          <input type="number" id="mAny" class="form-control form-control-sm" value="2026">
        </div>
        <div class="col-md-3">
          <label class="form-label fw-bold" style="font-size:.8rem">Mes</label>
          <select id="mMes" class="form-select form-select-sm"></select>
        </div>

        <div class="col-md-3">
          <label class="form-label fw-bold" style="font-size:.8rem">País <span class="text-muted fw-normal">(opcional)</span></label>
          <input type="text" id="mPais" class="form-control form-control-sm" placeholder="ex: España">
        </div>
        <div class="col-md-2">
          <label class="form-label fw-bold" style="font-size:.8rem">CP <span class="text-muted fw-normal">(opcional)</span></label>
          <input type="text" id="mCp" maxlength="5" class="form-control form-control-sm" placeholder="ex: 17800">
        </div>
        <div class="col-md-3">
          <label class="form-label fw-bold" style="font-size:.8rem">Nom d'entitat/grup <span class="text-muted fw-normal">(opcional, mai dades personals)</span></label>
          <input type="text" id="mNomClient" class="form-control form-control-sm" placeholder="ex: Escola X (no noms de persones)">
        </div>
        <div class="col-md-4">
          <label class="form-label fw-bold" style="font-size:.8rem">Motiu <span class="text-danger">(obligatori)</span></label>
          <input type="text" id="mNota" class="form-control form-control-sm" placeholder="ex: Grup no registrat a Clorian, confirmat per correu">
        </div>

        <div class="col-12">
          <button id="btnAfegirManual" class="btn btn-lava btn-sm">Afegir entrada</button>
          <span id="manualMissatge" class="ms-2" style="font-size:.85rem"></span>
        </div>
      </div>

      <hr>
      <h6 class="mb-2" style="font-size:.9rem">Entrades manuals afegides</h6>
      <div class="table-responsive" style="max-height:30vh; overflow:auto">
        <table class="table table-sm taula-err mb-0">
          <thead><tr><th>Data visita</th><th>Canal</th><th>Producte</th><th>Referència</th><th>País/CP</th><th class="text-end">Pax</th><th>Motiu</th><th></th></tr></thead>
          <tbody id="cosManuals"></tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="panel p-4" id="zonaDetall" style="display:none">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h5 class="mb-0" id="detallTitol">Detall</h5>
      <span class="pill-info" id="detallComptador"></span>
    </div>
    <div class="table-responsive" style="max-height:60vh; overflow:auto">
      <table class="table table-hover taula-err align-middle">
        <thead id="detallCap"></thead>
        <tbody id="detallCos"></tbody>
      </table>
    </div>
  </div>

</div>

<?php renderPeu([], false); ?>
<script>
const CATS = {
  pais_buit:    { titol:'País buit',              ajuda:'Entrades sense procedència' },
  cp_buit:      { titol:'CP buit (Espanya)',      ajuda:'Entrades espanyoles sense codi postal' },
  cp_format:    { titol:'CP mal format',          ajuda:'El codi postal no té 5 dígits' },
  provincia_no: { titol:'Província no assignada', ajuda:'CP vàlid sense província' },
  comarca_no:   { titol:'Comarca no assignada',   ajuda:'CP català sense comarca' },
  dup_escolar:  { titol:'Possibles duplicats escolars', ajuda:'Grups probablement comptats dues vegades' },
};

let catalegs = { provincies: [], comarques: [], productes: [], canals: [] };
let tipusActiu = null;

async function json(url, opts) { return (await fetch(url, opts)).json(); }
const nf = n => Number(n).toLocaleString('ca');
const MESOS = ['Gen','Feb','Mar','Abr','Mai','Jun','Jul','Ago','Set','Oct','Nov','Des'];

async function post(params) {
  return json('api_errors.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:new URLSearchParams(params) });
}

function opcions(llista, actual) {
  return '<option value="">—</option>' + llista.map(v => `<option value="${v}"${v===actual?' selected':''}>${v}</option>`).join('');
}

function animarITreure(tr) {
  tr.style.transition = '.4s'; tr.style.background = '#d4f5dd';
  setTimeout(() => { tr.remove(); carregarResum(); }, 450);
}

async function carregarResum() {
  const resum = await json('api_errors.php?accio=resum');
  const cont = document.getElementById('categories');
  cont.innerHTML = '';
  for (const [tipus, info] of Object.entries(CATS)) {
    const d = resum[tipus] || { files:0, pax:0 };
    const ok = d.files === 0;
    const dup = tipus === 'dup_escolar';
    const col = document.createElement('div');
    col.className = 'col-6 col-lg-2';
    col.innerHTML = `
      <div class="cat ${ok?'ok':'err'}" data-tipus="${tipus}">
        <div class="titol">${info.titol}</div>
        <div class="num">${d.files}</div>
        <div class="pax">${ok ? 'cap cas ✓' : (dup ? '~'+nf(d.pax)+' pax en dubte' : nf(d.pax)+' pax afectats')}</div>
      </div>`;
    col.querySelector('.cat').addEventListener('click', () => obrirDetall(tipus));
    cont.appendChild(col);
  }
}

async function obrirDetall(tipus) {
  tipusActiu = tipus;
  document.querySelectorAll('.cat').forEach(c => c.classList.toggle('actiu', c.dataset.tipus===tipus));
  document.getElementById('zonaDetall').style.display = 'block';
  document.getElementById('detallTitol').textContent = CATS[tipus].titol + ' · ' + CATS[tipus].ajuda;

  const files = await json('api_errors.php?accio=detall&tipus=' + tipus);
  document.getElementById('detallComptador').textContent = files.length + (tipus==='dup_escolar' ? ' casos' : ' a revisar');

  const buida = (missatge) => {
    document.getElementById('detallCos').innerHTML = `<tr><td colspan="9" class="text-center text-success py-4">${missatge}</td></tr>`;
  };

  if (tipus === 'dup_escolar') return vistaDuplicats(files, buida);
  if (tipus === 'comarca_no' || tipus === 'provincia_no') return vistaGeografica(tipus, files, buida);
  if (tipus === 'cp_buit' || tipus === 'cp_format') return vistaCp(tipus, files, buida);
  return vistaPais(files, buida);
}

// ---------- VISTA: comarca / província no assignada (suggeriment 1-clic) ----------
function vistaGeografica(tipus, files, buida) {
  const esComarca = tipus === 'comarca_no';
  document.getElementById('detallCap').innerHTML = `
    <tr>
      <th>Codi postal</th><th>Pax</th>
      <th>${esComarca ? 'Comarca suggerida' : 'Província suggerida'}</th>
      <th style="width:130px"></th>
      <th style="width:230px">Assignar manualment</th>
    </tr>`;
  const cos = document.getElementById('detallCos'); cos.innerHTML = '';
  if (!files.length) return buida('Cap cas en aquesta categoria ✓');

  files.forEach(f => {
    const valorSug = esComarca ? f.sug_comarca : f.sug_provincia;
    const refTxt = esComarca && f.sug_cp_ref ? `basat en CP ${f.sug_cp_ref}${f.sug_municipi ? ' ('+f.sug_municipi+')' : ''}` : (f.sug_provincia ? 'segons prefix del CP' : '');
    const tr = document.createElement('tr');

    let cellaSug;
    if (valorSug) {
      cellaSug = `<span class="sug-txt">💡 ${valorSug}</span><br><span class="sug-ref">${refTxt}</span>
        <button class="btn btn-sug btn-sm mt-1" data-aplicar>Aplicar ✓</button>`;
    } else if (!esComarca && f.sug_pais_estranger) {
      cellaSug = `<span class="sug-txt">🇫🇷 Sembla un CP francès</span><br>
        <span class="sug-ref">Departament de ${f.sug_departament_fr} — probablement el país no és Espanya</span>
        <button class="btn btn-sug btn-sm mt-1" data-aplicar-pais>Marcar com a França ✓</button>`;
    } else {
      cellaSug = '<span class="text-muted">sense suggeriment</span>';
    }

    tr.innerHTML = `
      <td><span class="badge-cp">${f.codi_postal}</span></td>
      <td class="fw-semibold">${nf(f.pax)}</td>
      <td>${cellaSug}</td>
      <td></td>
      <td>
        <div class="input-group input-group-sm">
          <select class="form-select" data-manual>${esComarca ? opcions(catalegs.comarques,'') : opcions(catalegs.provincies,'')}</select>
          <button class="btn btn-outline-secondary" data-desar-manual>OK</button>
        </div>
      </td>`;

    const desar = async (valor) => {
      const params = { accio:'corregir', cp_orig:f.codi_postal ?? '', pais_orig:f.pais ?? '', nou_cp:'', nou_pais:'', nova_provincia:'', nova_comarca:'', persistir:'1' };
      if (esComarca) { params.nova_comarca = valor; params.nova_provincia = f.sug_provincia || ''; }
      else params.nova_provincia = valor;
      const res = await post(params);
      if (res.ok) animarITreure(tr); else alert('Error: ' + (res.error||'?'));
    };

    if (valorSug) tr.querySelector('[data-aplicar]').addEventListener('click', () => desar(valorSug));
    if (!valorSug && f.sug_pais_estranger) {
      tr.querySelector('[data-aplicar-pais]').addEventListener('click', async () => {
        const res = await post({ accio:'corregir', cp_orig:f.codi_postal ?? '', pais_orig:f.pais ?? '', nou_cp:'', nou_pais:'Francia', nova_provincia:'', nova_comarca:'', persistir:'0' });
        if (res.ok) animarITreure(tr); else alert('Error: ' + (res.error||'?'));
      });
    }
    tr.querySelector('[data-desar-manual]').addEventListener('click', () => {
      const v = tr.querySelector('[data-manual]').value;
      if (!v) return alert('Tria un valor.');
      desar(v);
    });
    cos.appendChild(tr);
  });
}

// ---------- VISTA: CP buit / CP mal format ----------
function vistaCp(tipus, files, buida) {
  document.getElementById('detallCap').innerHTML = `
    <tr>
      <th>Codi postal actual</th><th>País</th><th>Pax</th>
      <th style="width:340px">Introduir codi postal correcte</th>
    </tr>`;
  const cos = document.getElementById('detallCos'); cos.innerHTML = '';
  if (!files.length) return buida('Cap cas en aquesta categoria ✓');

  files.forEach(f => {
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td>${f.codi_postal ? `<span class="badge-cp">${f.codi_postal}</span>` : '<span class="text-danger">(buit)</span>'}</td>
      <td>${f.pais ?? '—'}</td>
      <td class="fw-semibold">${nf(f.pax)}</td>
      <td>
        <div class="input-group input-group-sm">
          <span class="input-group-text">CP</span>
          <input type="text" class="form-control" maxlength="5" placeholder="ex: 17800" data-cp>
          <button class="btn btn-lava" data-desar>Desar i derivar</button>
        </div>
        <div class="sug-ref mt-1">En desar, es calcularan província i comarca automàticament.</div>
      </td>`;

    tr.querySelector('[data-desar]').addEventListener('click', async () => {
      const cp = tr.querySelector('[data-cp]').value.trim();
      if (!/^\d{5}$/.test(cp)) return alert('El codi postal ha de tenir 5 dígits.');
      const res = await post({ accio:'corregir', cp_orig:f.codi_postal ?? '', pais_orig:f.pais ?? '', nou_cp:cp, nou_pais:'', nova_provincia:'', nova_comarca:'', persistir:'0' });
      if (res.ok) animarITreure(tr); else alert('Error: ' + (res.error||'?'));
    });
    cos.appendChild(tr);
  });
}

// ---------- VISTA: país buit ----------
function vistaPais(files, buida) {
  document.getElementById('detallCap').innerHTML = `
    <tr><th>Codi postal</th><th>Pax</th><th style="width:340px">Assignar país</th></tr>`;
  const cos = document.getElementById('detallCos'); cos.innerHTML = '';
  if (!files.length) return buida('Cap cas en aquesta categoria ✓');

  files.forEach(f => {
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td>${f.codi_postal ? `<span class="badge-cp">${f.codi_postal}</span>` : '—'}</td>
      <td class="fw-semibold">${nf(f.pax)}</td>
      <td>
        <div class="input-group input-group-sm">
          <input type="text" class="form-control" placeholder="ex: España, Francia…" data-pais>
          <button class="btn btn-lava" data-desar>Desar</button>
        </div>
      </td>`;
    tr.querySelector('[data-desar]').addEventListener('click', async () => {
      const pais = tr.querySelector('[data-pais]').value.trim();
      if (!pais) return alert('Escriu un país.');
      const res = await post({ accio:'corregir', cp_orig:f.codi_postal ?? '', pais_orig:f.pais ?? '', nou_cp:'', nou_pais:pais, nova_provincia:'', nova_comarca:'', persistir:'0' });
      if (res.ok) animarITreure(tr); else alert('Error: ' + (res.error||'?'));
    });
    cos.appendChild(tr);
  });
}

// ---------- VISTA: duplicats escolars ----------
function vistaDuplicats(casos, buida) {
  document.getElementById('detallCap').innerHTML = `
    <tr>
      <th>Escola</th><th>Període</th>
      <th>Activitats</th>
      <th style="width:300px">Correcció suggerida</th>
    </tr>`;
  const cos = document.getElementById('detallCos'); cos.innerHTML = '';
  if (!casos.length) return buida('Cap duplicat sospitós ✓');

  casos.forEach(c => {
    const total = c.principal_pax + c.montsacopa_pax;
    const grupReal = Math.max(c.principal_pax, c.montsacopa_pax);
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td class="fw-semibold">${c.escola}</td>
      <td>${MESOS[c.mes-1] || c.mes}/${c.any}</td>
      <td style="font-size:.84rem">
        ${c.principal_prod} <span class="badge bg-secondary">${c.principal_pax}</span>
        <span class="arrow"> + </span>
        ${c.montsacopa_prod} <span class="badge bg-warning text-dark">${c.montsacopa_pax}</span>
      </td>
      <td>
        <div class="dup-alerta mb-2">Total: <b>${total}</b> pax. Grup real probable: <b>${grupReal}</b>.</div>
        <div class="d-flex gap-2">
          <button class="btn btn-sug btn-sm flex-grow-1" data-corregir-auto>Deixar Montsacopa a 1 ✓</button>
          <div class="input-group input-group-sm" style="width:120px">
            <input type="number" min="0" class="form-control" value="1" data-nou-pax>
            <button class="btn btn-outline-secondary" data-corregir-manual>OK</button>
          </div>
        </div>
      </td>`;

    const corregir = async (nouPax) => {
      const res = await post({ accio:'editar_pax', entrada_id:c.montsacopa_id, nou_pax:nouPax });
      if (res.ok) animarITreure(tr); else alert('Error: ' + (res.error||'?'));
    };
    tr.querySelector('[data-corregir-auto]').addEventListener('click', () => corregir(1));
    tr.querySelector('[data-corregir-manual]').addEventListener('click', () => corregir(tr.querySelector('[data-nou-pax]').value));
    cos.appendChild(tr);
  });
}

document.getElementById('btnRederivar').addEventListener('click', async () => {
  if (!confirm('Es tornarà a calcular la província i la comarca de totes les entrades. Continuar?')) return;
  const btn = document.getElementById('btnRederivar'); btn.disabled = true; btn.textContent = 'Recalculant…';
  const res = await post({ accio:'rederivar' });
  btn.disabled = false; btn.textContent = '↻ Re-derivar tot';
  if (res.ok) { carregarResum(); if (tipusActiu) obrirDetall(tipusActiu); } else alert('Error: ' + (res.error||'?'));
});

// ---------- Injecció manual ----------
document.getElementById('toggleManual').addEventListener('click', () => {
  const cos = document.getElementById('cosManual');
  const oberta = cos.style.display !== 'none';
  cos.style.display = oberta ? 'none' : 'block';
  document.getElementById('fletxaManual').textContent = oberta ? '▾' : '▴';
  if (!oberta) carregarManuals();
});

function omplirFormulariManual() {
  document.getElementById('mCanal').innerHTML = catalegs.canals.map(c => `<option value="${c.codi}">${c.nom}</option>`).join('');
  document.getElementById('llistaProductes').innerHTML = catalegs.productes.map(p => `<option value="${p}">`).join('');
  document.getElementById('mMes').innerHTML = MESOS.map((m,i) => `<option value="${i+1}">${m}</option>`).join('');
}

document.getElementById('btnAfegirManual').addEventListener('click', async () => {
  const missatge = document.getElementById('manualMissatge');
  missatge.textContent = ''; missatge.className = 'ms-2';

  const dades = {
    accio: 'afegir_manual',
    canal: document.getElementById('mCanal').value,
    producte: document.getElementById('mProducte').value.trim(),
    pax: document.getElementById('mPax').value,
    any: document.getElementById('mAny').value,
    mes: document.getElementById('mMes').value,
    pais: document.getElementById('mPais').value.trim(),
    codi_postal: document.getElementById('mCp').value.trim(),
    nom_client: document.getElementById('mNomClient').value.trim(),
    nota: document.getElementById('mNota').value.trim(),
  };

  const btn = document.getElementById('btnAfegirManual');
  btn.disabled = true; btn.textContent = 'Afegint…';
  const res = await post(dades);
  btn.disabled = false; btn.textContent = 'Afegir entrada';

  if (res.ok) {
    missatge.textContent = '✓ Entrada afegida correctament.';
    missatge.className = 'ms-2 text-success fw-semibold';
    ['mProducte','mPax','mPais','mCp','mNomClient','mNota'].forEach(id => document.getElementById(id).value = '');
    carregarManuals();
    carregarResum();
  } else {
    missatge.textContent = '✗ ' + (res.error || 'Error desconegut');
    missatge.className = 'ms-2 text-danger fw-semibold';
  }
});

async function carregarManuals() {
  const files = await json('api_errors.php?accio=llistar_manuals');
  const cos = document.getElementById('cosManuals');
  if (!files.length) { cos.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-3">Encara no s\'ha afegit cap entrada manual.</td></tr>'; return; }
  cos.innerHTML = files.map(f => `
    <tr>
      <td>${MESOS[f.mes_visita-1] || f.mes_visita}/${f.any_visita}</td>
      <td>${f.canal ?? '—'}</td>
      <td>${f.producte ?? '—'}</td>
      <td>${f.nom_client ?? '—'}</td>
      <td>${[f.pais, f.codi_postal].filter(Boolean).join(' · ') || '—'}</td>
      <td class="text-end fw-semibold">${nf(f.pax)}</td>
      <td style="font-size:.82rem" class="text-muted">${f.nota_manual ?? ''}</td>
      <td><button class="btn btn-outline-danger btn-sm" data-eliminar="${f.id}">Eliminar</button></td>
    </tr>`).join('');

  cos.querySelectorAll('[data-eliminar]').forEach(btn => btn.addEventListener('click', async () => {
    if (!confirm('Eliminar aquesta entrada manual?')) return;
    const res = await post({ accio:'eliminar_manual', id: btn.dataset.eliminar });
    if (res.ok) { carregarManuals(); carregarResum(); }
    else alert('Error: ' + (res.error||'?'));
  }));
}

(async () => { catalegs = await json('api_errors.php?accio=catalegs'); omplirFormulariManual(); await carregarResum(); })();
</script>
</body>
</html>
