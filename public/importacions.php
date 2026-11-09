<?php
require __DIR__ . '/../config/config.php';
require __DIR__ . '/../src/Auth.php';
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
</style>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
  <div class="titol-pagina m-0"><h1>Historial d'importacions</h1></div>
  <div class="d-flex gap-2 flex-wrap align-items-center">
      <a href="import.php" class="btn btn-outline-primary btn-sm fw-semibold">+ Importar Excel</a>
  </div>
</div>


<div class="container-fluid px-0 pb-5">
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
</script>
</body>
</html>
