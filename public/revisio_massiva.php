<?php
require __DIR__ . '/../config/config.php';
require __DIR__ . '/../src/Auth.php';
Auth::requerirLogin();
?>
<?php renderCapcalera('Revisió massiva', usuariActual()); ?>
<style>
  :root { --lava:#c0392b; --lava-dark:#7b241c; --ember:#e67e22; --ash:#2c3e50; --bg:#f5f3f0; --green:#1e8449; }
  .panel { background:#fff; border:none; border-radius:16px; box-shadow:0 4px 20px rgba(0,0,0,.06); }
  .panel h5 { font-weight:700; color:var(--ash); margin:0 0 .3rem 0; display:flex; align-items:center; gap:.5rem; }
  .panel h5::before { content:""; width:6px; height:20px; border-radius:3px; background:var(--lava); display:inline-block; }
  .btn-lava { background:var(--lava); color:#fff; font-weight:600; border:none; }
  .btn-lava:hover { background:var(--lava-dark); color:#fff; }
  .btn-green { background:var(--green); color:#fff; font-weight:600; border:none; }
  .btn-green:hover { background:#166638; color:#fff; }
  .num { font-size:2rem; font-weight:800; color:var(--lava); }
  .num.ok { color:var(--green); }
  .pax-afectat { font-size:.85rem; color:#8a8a8a; }
  .taula-mini { font-size:.83rem; }
  .taula-mini thead th { background:#f3f1ee; font-size:.75rem; text-transform:uppercase; color:#6b6b6b; }
  .resultat-accio { font-size:.85rem; padding:.5rem .8rem; border-radius:8px; margin-top:.6rem; }
  .resultat-accio.ok { background:#e8f8ee; color:var(--green); }
  .resultat-accio.err { background:#fdecea; color:var(--lava); }
  .scroll-taula { max-height:260px; overflow:auto; }
  details summary { cursor:pointer; color:var(--ash); font-weight:600; font-size:.85rem; }
</style>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
  <div class="titol-pagina m-0"><h1>Revisió massiva</h1></div>
  <div class="d-flex gap-2 flex-wrap align-items-center">
      <a href="errors.php" class="btn btn-outline-primary btn-sm fw-semibold">Revisió fila a fila</a>
  </div>
</div>


<div class="container-fluid px-0 pb-5">

  <div class="panel p-3 mb-4">
    <p class="mb-0 text-muted" style="font-size:.9rem">
      Pensat per quan hi ha molts casos a revisar de cop (per exemple després d'una importació
      d'històric). Cada bloc ofereix una acció que resol <b>molts casos alhora</b>. El que quedi
      pendent després es pot revisar un a un a la
      <a href="errors.php">pantalla de gestió d'errors habitual</a>, o descartar-se aquí si decideixes
      que ja està bé tal com és.
    </p>
  </div>

  <div class="row g-4">

    <!-- Comarca no assignada -->
    <div class="col-lg-6">
      <div class="panel p-4">
        <h5>Comarca no assignada</h5>
        <div class="d-flex align-items-baseline gap-2 mb-2">
          <span class="num" id="numComarca">–</span>
          <span class="pax-afectat" id="paxComarca"></span>
        </div>
        <p class="text-muted" style="font-size:.85rem">
          Aplica el suggeriment automàtic (comarca del CP de referència més proper) a tots els
          casos pendents que en tinguin un.
        </p>
        <button class="btn btn-green btn-sm" data-suggeriments="comarca_no">⚡ Aplicar tots els suggeriments</button>
        <div id="resultatComarca"></div>
        <details class="mt-3"><summary>Veure casos pendents</summary>
          <div class="scroll-taula mt-2"><table class="table table-sm taula-mini mb-0">
            <thead><tr><th>CP</th><th class="text-end">Pax</th><th>Suggeriment</th><th></th></tr></thead>
            <tbody id="cosComarca"></tbody>
          </table></div>
        </details>
      </div>
    </div>

    <!-- Província no assignada -->
    <div class="col-lg-6">
      <div class="panel p-4">
        <h5>Província no assignada</h5>
        <div class="d-flex align-items-baseline gap-2 mb-2">
          <span class="num" id="numProvincia">–</span>
          <span class="pax-afectat" id="paxProvincia"></span>
        </div>
        <p class="text-muted" style="font-size:.85rem">
          Aplica el suggeriment automàtic (província pel prefix del CP) a tots els casos pendents.
        </p>
        <button class="btn btn-green btn-sm" data-suggeriments="provincia_no">⚡ Aplicar tots els suggeriments</button>
        <button class="btn btn-outline-secondary btn-sm" id="btnReclassificarFr">🇫🇷 Reclassificar CPs francesos com a estrangers</button>
        <div id="resultatProvincia"></div>
        <details class="mt-3"><summary>Veure casos pendents</summary>
          <div class="scroll-taula mt-2"><table class="table table-sm taula-mini mb-0">
            <thead><tr><th>CP</th><th class="text-end">Pax</th><th>Suggeriment</th><th></th></tr></thead>
            <tbody id="cosProvincia"></tbody>
          </table></div>
        </details>
      </div>
    </div>

    <!-- CP mal format -->
    <div class="col-lg-6">
      <div class="panel p-4">
        <h5>CP mal format</h5>
        <div class="d-flex align-items-baseline gap-2 mb-2">
          <span class="num" id="numCpFormat">–</span>
          <span class="pax-afectat" id="paxCpFormat"></span>
        </div>
        <p class="text-muted" style="font-size:.85rem">
          Neteja automàticament els CPs amb caràcters estranys (punts, símbols...). Si en treure'ls
          queden exactament 5 dígits, s'aplica directament. Si en queden 6, prova de suprimir cada
          dígit i valida el resultat contra els prefixos reals de província; si només un encaixa,
          també l'aplica sol.
        </p>
        <button class="btn btn-green btn-sm" id="btnNetejarCp">🧹 Netejar CPs automàticament</button>
        <div id="resultatCpFormat"></div>
        <details class="mt-3"><summary>Veure casos pendents</summary>
          <div class="scroll-taula mt-2"><table class="table table-sm taula-mini mb-0">
            <thead><tr><th>CP actual</th><th class="text-end">Pax</th><th></th></tr></thead>
            <tbody id="cosCpFormat"></tbody>
          </table></div>
        </details>
      </div>
    </div>

    <!-- País buit / CP buit: sense fix automàtic, però amb descart en bloc -->
    <div class="col-lg-6">
      <div class="panel p-4">
        <h5>País buit / CP buit</h5>
        <div class="d-flex align-items-baseline gap-2 mb-2">
          <span class="num" id="numSenseDada">–</span>
          <span class="pax-afectat" id="paxSenseDada"></span>
        </div>
        <p class="text-muted" style="font-size:.85rem">
          No hi ha cap valor a deduir aquí (el visitant genuïnament no va donar aquesta dada).
          Si ja ho has revisat i vols deixar de veure-ho com a pendent, pots descartar cada cas
          individualment des de la llista.
        </p>
        <details class="mt-3" open><summary>Veure casos pendents</summary>
          <div class="scroll-taula mt-2"><table class="table table-sm taula-mini mb-0">
            <thead><tr><th>Tipus</th><th>CP / País</th><th class="text-end">Pax</th><th></th></tr></thead>
            <tbody id="cosSenseDada"></tbody>
          </table></div>
        </details>
      </div>
    </div>

  </div>

  <!-- Descartats -->
  <div class="panel p-4 mt-4">
    <h5>Casos descartats <span class="text-muted fw-normal" style="font-size:.8rem">(marcats com a revisats, no compten com a pendents)</span></h5>
    <div class="table-responsive mt-2">
      <table class="table table-sm taula-mini mb-0">
        <thead><tr><th>Tipus</th><th>CP</th><th>País</th><th>Motiu</th><th>Data</th><th></th></tr></thead>
        <tbody id="cosDescartats"></tbody>
      </table>
    </div>
  </div>

</div>

<?php renderPeu([], false); ?>
<script>
const nf = n => Number(n).toLocaleString('ca');
async function json(url, opts) { return (await fetch(url, opts)).json(); }
async function post(params) {
  return json('api_errors.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:new URLSearchParams(params) });
}

const NOMS_TIPUS = { pais_buit:'País buit', cp_buit:'CP buit', cp_format:'CP mal format' };

function mostrarResultat(id, ok, text) {
  document.getElementById(id).innerHTML = `<div class="resultat-accio ${ok ? 'ok' : 'err'}">${text}</div>`;
}

async function carregarResum() {
  const resum = await json('api_errors.php?accio=resum');
  document.getElementById('numComarca').textContent = resum.comarca_no.files;
  document.getElementById('numComarca').className = 'num' + (resum.comarca_no.files === 0 ? ' ok' : '');
  document.getElementById('paxComarca').textContent = resum.comarca_no.files ? nf(resum.comarca_no.pax) + ' pax afectats' : 'cap cas ✓';

  document.getElementById('numProvincia').textContent = resum.provincia_no.files;
  document.getElementById('numProvincia').className = 'num' + (resum.provincia_no.files === 0 ? ' ok' : '');
  document.getElementById('paxProvincia').textContent = resum.provincia_no.files ? nf(resum.provincia_no.pax) + ' pax afectats' : 'cap cas ✓';

  document.getElementById('numCpFormat').textContent = resum.cp_format.files;
  document.getElementById('numCpFormat').className = 'num' + (resum.cp_format.files === 0 ? ' ok' : '');
  document.getElementById('paxCpFormat').textContent = resum.cp_format.files ? nf(resum.cp_format.pax) + ' pax afectats' : 'cap cas ✓';

  const totalSenseDada = resum.pais_buit.files + resum.cp_buit.files;
  const paxSenseDada = resum.pais_buit.pax + resum.cp_buit.pax;
  document.getElementById('numSenseDada').textContent = totalSenseDada;
  document.getElementById('numSenseDada').className = 'num' + (totalSenseDada === 0 ? ' ok' : '');
  document.getElementById('paxSenseDada').textContent = totalSenseDada ? nf(paxSenseDada) + ' pax afectats' : 'cap cas ✓';
}

async function carregarDetallGeografic(tipus, cosId) {
  const files = await json('api_errors.php?accio=detall&tipus=' + tipus);
  const cos = document.getElementById(cosId);
  if (!files.length) { cos.innerHTML = '<tr><td colspan="4" class="text-center text-success py-2">Cap cas ✓</td></tr>'; return; }
  cos.innerHTML = files.map(f => {
    const sug = tipus === 'comarca_no' ? f.sug_comarca : f.sug_provincia;
    const sugTxt = sug ? '💡 ' + sug : (f.sug_pais_estranger ? `🇫🇷 CP francès (${f.sug_departament_fr})` : '<span class="text-muted">—</span>');
    return `<tr>
      <td>${f.codi_postal ?? '—'}</td>
      <td class="text-end">${nf(f.pax)}</td>
      <td>${sugTxt}</td>
      <td><button class="btn btn-outline-secondary btn-sm" data-descartar='${JSON.stringify({tipus, codi_postal:f.codi_postal, pais:f.pais})}'>Descartar</button></td>
    </tr>`;
  }).join('');
  lligarBotonsDescartar(cos);
}

async function carregarCpFormat() {
  const files = await json('api_errors.php?accio=detall&tipus=cp_format');
  const cos = document.getElementById('cosCpFormat');
  if (!files.length) { cos.innerHTML = '<tr><td colspan="3" class="text-center text-success py-2">Cap cas ✓</td></tr>'; return; }
  cos.innerHTML = files.map(f => `
    <tr>
      <td><code>${f.codi_postal}</code></td>
      <td class="text-end">${nf(f.pax)}</td>
      <td><button class="btn btn-outline-secondary btn-sm" data-descartar='${JSON.stringify({tipus:'cp_format', codi_postal:f.codi_postal, pais:f.pais})}'>Descartar</button></td>
    </tr>`).join('');
  lligarBotonsDescartar(cos);
}

async function carregarSenseDada() {
  const [pais, cp] = await Promise.all([
    json('api_errors.php?accio=detall&tipus=pais_buit'),
    json('api_errors.php?accio=detall&tipus=cp_buit'),
  ]);
  const cos = document.getElementById('cosSenseDada');
  const files = [
    ...pais.map(f => ({...f, tipus:'pais_buit'})),
    ...cp.map(f => ({...f, tipus:'cp_buit'})),
  ];
  if (!files.length) { cos.innerHTML = '<tr><td colspan="4" class="text-center text-success py-2">Cap cas ✓</td></tr>'; return; }
  cos.innerHTML = files.map(f => `
    <tr>
      <td>${NOMS_TIPUS[f.tipus]}</td>
      <td>${f.codi_postal ?? '—'} ${f.pais ? '· ' + f.pais : ''}</td>
      <td class="text-end">${nf(f.pax)}</td>
      <td><button class="btn btn-outline-secondary btn-sm" data-descartar='${JSON.stringify({tipus:f.tipus, codi_postal:f.codi_postal, pais:f.pais})}'>Descartar</button></td>
    </tr>`).join('');
  lligarBotonsDescartar(cos);
}

function lligarBotonsDescartar(cos) {
  cos.querySelectorAll('[data-descartar]').forEach(btn => btn.addEventListener('click', async () => {
    const d = JSON.parse(btn.dataset.descartar);
    const motiu = prompt('Motiu del descart (opcional):', 'Revisat, es dona per bo tal com és') || '';
    const res = await post({ accio:'descartar', tipus:d.tipus, codi_postal:d.codi_postal ?? '', pais:d.pais ?? '', motiu });
    if (res.ok) { await carregarTot(); } else alert('Error: ' + (res.error || '?'));
  }));
}

async function carregarDescartats() {
  const files = await json('api_errors.php?accio=llistar_descartats');
  const cos = document.getElementById('cosDescartats');
  if (!files.length) { cos.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-3">Cap cas descartat encara.</td></tr>'; return; }
  cos.innerHTML = files.map(f => `
    <tr>
      <td>${NOMS_TIPUS[f.tipus] ?? f.tipus}</td>
      <td>${f.codi_postal ?? '—'}</td>
      <td>${f.pais ?? '—'}</td>
      <td class="text-muted">${f.motiu ?? ''}</td>
      <td>${new Date(f.data_descart).toLocaleDateString('ca')}</td>
      <td><button class="btn btn-outline-secondary btn-sm" data-restaurar="${f.id}">Restaurar</button></td>
    </tr>`).join('');
  cos.querySelectorAll('[data-restaurar]').forEach(btn => btn.addEventListener('click', async () => {
    const res = await post({ accio:'restaurar_descartat', id: btn.dataset.restaurar });
    if (res.ok) await carregarTot();
  }));
}

document.querySelectorAll('[data-suggeriments]').forEach(btn => {
  btn.addEventListener('click', async () => {
    const tipus = btn.dataset.suggeriments;
    const resultatId = tipus === 'comarca_no' ? 'resultatComarca' : 'resultatProvincia';
    btn.disabled = true; btn.textContent = 'Aplicant…';
    const res = await post({ accio:'aplicar_tots_suggeriments', tipus });
    btn.disabled = false; btn.textContent = '⚡ Aplicar tots els suggeriments';
    if (res.ok) {
      mostrarResultat(resultatId, true,
        `✓ Aplicats ${res.aplicats} casos. ${res.sense_suggeriment ? res.sense_suggeriment + ' sense suggeriment disponible (caldrà revisar-los a mà).' : 'Tots resolts!'}`);
      await carregarTot();
    } else {
      mostrarResultat(resultatId, false, '✗ ' + (res.error || 'Error desconegut'));
    }
  });
});

document.getElementById('btnReclassificarFr').addEventListener('click', async () => {
  const btn = document.getElementById('btnReclassificarFr');
  btn.disabled = true; btn.textContent = 'Reclassificant…';
  const res = await post({ accio:'reclassificar_cp_estrangers' });
  btn.disabled = false; btn.textContent = '🇫🇷 Reclassificar CPs francesos com a estrangers';
  if (res.ok) {
    mostrarResultat('resultatProvincia', true, `✓ Reclassificats ${res.reclassificats} casos com a Francia (CP amb prefix de departament francès, marcats erròniament com a Espanya).`);
    await carregarTot();
  } else {
    mostrarResultat('resultatProvincia', false, '✗ ' + (res.error || 'Error desconegut'));
  }
});

document.getElementById('btnNetejarCp').addEventListener('click', async () => {
  const btn = document.getElementById('btnNetejarCp');
  btn.disabled = true; btn.textContent = 'Netejant…';
  const res = await post({ accio:'netejar_cp_automatic' });
  btn.disabled = false; btn.textContent = '🧹 Netejar CPs automàticament';
  if (res.ok) {
    mostrarResultat('resultatCpFormat', true,
      `✓ Nets i corregits ${res.netejats} CPs. ${res.ambigus ? res.ambigus + ' ambigus (massa o massa pocs dígits), cal revisar-los a mà.' : 'Tots resolts!'}`);
    await carregarTot();
  } else {
    mostrarResultat('resultatCpFormat', false, '✗ ' + (res.error || 'Error desconegut'));
  }
});

async function carregarTot() {
  await carregarResum();
  await carregarDetallGeografic('comarca_no', 'cosComarca');
  await carregarDetallGeografic('provincia_no', 'cosProvincia');
  await carregarCpFormat();
  await carregarSenseDada();
  await carregarDescartats();
}

carregarTot();
</script>
</body>
</html>
