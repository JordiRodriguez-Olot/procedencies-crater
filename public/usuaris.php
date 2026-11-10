<?php
require __DIR__ . '/../config/config.php';
require __DIR__ . '/../src/Auth.php';
Auth::requerirAdmin();
?>
<?php renderCapcalera("Gestió d'usuaris", usuariActual()); ?>
<style>
  :root { --lava:#c0392b; --lava-dark:#7b241c; --ember:#e67e22; --ash:#2c3e50; --bg:#f5f3f0; }
  .panel { background:#fff; border:none; border-radius:16px; box-shadow:0 4px 20px rgba(0,0,0,.06); }
  .panel h5 { font-weight:700; color:var(--ash); margin:0 0 1rem 0; display:flex; align-items:center; gap:.5rem; }
  .panel h5::before { content:""; width:6px; height:20px; border-radius:3px; background:var(--lava); display:inline-block; }
  .btn-lava { background:var(--lava); color:#fff; font-weight:600; border:none; }
  .btn-lava:hover { background:var(--lava-dark); color:#fff; }
  .taula-u { font-size:.9rem; }
  .taula-u thead th { background:#f3f1ee; font-size:.78rem; text-transform:uppercase; color:#6b6b6b; }
  .badge-rol-admin { background:#fdece8; color:var(--lava-dark); font-weight:600; }
  .badge-rol-usuari { background:#e8eef2; color:var(--ash); font-weight:600; }
  .badge-actiu { background:#e8f8ee; color:#1e8449; }
  .badge-inactiu { background:#f0f0f0; color:#8a8a8a; }
</style>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
  <div class="titol-pagina m-0"><h1>Gestió d'usuaris</h1></div>
  <div class="d-flex gap-2 flex-wrap align-items-center">
      
  </div>
</div>


<div class="container-fluid px-0 pb-5">

  <div class="panel p-4 mb-4">
    <h5>Crear nou usuari</h5>
    <div class="row g-3 align-items-end">
      <div class="col-md-3">
        <label class="form-label fw-bold" style="font-size:.8rem">Nom d'usuari</label>
        <input type="text" id="nouUsuari" class="form-control form-control-sm" minlength="3" autocomplete="off" autocapitalize="off" spellcheck="false">
      </div>
      <div class="col-md-3">
        <label class="form-label fw-bold" style="font-size:.8rem">Contrasenya</label>
        <input type="password" id="novaContrasenya" class="form-control form-control-sm" minlength="6" autocomplete="new-password">
      </div>
      <div class="col-md-2">
        <label class="form-label fw-bold" style="font-size:.8rem">Rol</label>
        <select id="nouRol" class="form-select form-select-sm">
          <option value="usuari">Usuari</option>
          <option value="admin">Admin</option>
        </select>
      </div>
      <div class="col-md-2">
        <button id="btnCrear" class="btn btn-lava btn-sm w-100">+ Crear usuari</button>
      </div>
    </div>
    <div id="missatgeCrear" class="mt-2" style="font-size:.85rem"></div>
    <p class="text-muted mt-2 mb-0" style="font-size:.78rem">
      No hi ha recuperació de contrasenya per correu. Si algú l'oblida, reinicia-la-hi manualment
      des de la taula de sota i comunica-l'hi en persona.
    </p>
  </div>

  <div class="panel p-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
      <h5 class="mb-0">Usuaris existents</h5>
      <a href="usuaris_ad.php" class="btn btn-outline-primary btn-sm">Cerca a l'Active Directory</a>
    </div>
    <div class="table-responsive">
      <table class="table table-hover taula-u align-middle">
        <thead>
          <tr><th>Usuari</th><th>Origen</th><th>Rol</th><th>Estat</th><th>Creat</th><th>Últim accés</th><th style="min-width:280px">Accions</th></tr>
        </thead>
        <tbody id="cosUsuaris"></tbody>
      </table>
    </div>
  </div>

</div>

<?php renderPeu([], false); ?>
<script>
const esc = s => String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
async function json(url, opts) { return (await fetch(url, opts)).json(); }
async function post(params) {
  return json('api_usuaris.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:new URLSearchParams(params) });
}

async function carregarUsuaris() {
  const files = await json('api_usuaris.php?accio=llistar');
  const cos = document.getElementById('cosUsuaris');
  cos.innerHTML = files.map(f => `
    <tr data-id="${f.id}">
      <td class="fw-semibold">${esc(f.nom_usuari)}${f.nom && f.nom !== f.nom_usuari ? '<div class="text-muted fw-normal" style="font-size:.78rem">' + esc(f.nom) + '</div>' : ''}</td>
      <td><span class="badge ${f.origen === 'ad' ? 'badge-rol-admin' : 'badge-rol-usuari'}">${f.origen === 'ad' ? 'Domini' : 'Local'}</span></td>
      <td>
        <select class="form-select form-select-sm d-inline-block w-auto" data-rol>
          <option value="usuari" ${f.rol==='usuari'?'selected':''}>Usuari</option>
          <option value="admin" ${f.rol==='admin'?'selected':''}>Admin</option>
        </select>
      </td>
      <td><span class="badge ${f.actiu ? 'badge-actiu' : 'badge-inactiu'}">${f.actiu ? 'Actiu' : 'Inactiu'}</span></td>
      <td style="font-size:.8rem">${new Date(f.data_creacio).toLocaleDateString('ca')}</td>
      <td style="font-size:.8rem">${f.ultim_acces ? new Date(f.ultim_acces).toLocaleString('ca') : '—'}</td>
      <td>
        <div class="d-flex gap-1 flex-wrap">
          ${f.origen === 'ad' ? '' : `<input type="password" class="form-control form-control-sm" data-nova-contrasenya placeholder="nova contrasenya" style="width:150px">
          <button class="btn btn-outline-secondary btn-sm" data-reset>Reiniciar</button>`}
          <button class="btn btn-outline-${f.actiu ? 'danger' : 'success'} btn-sm" data-toggle-actiu>${f.actiu ? 'Desactivar' : 'Activar'}</button>
          <button class="btn btn-danger btn-sm" data-eliminar>Eliminar</button>
        </div>
      </td>
    </tr>`).join('');

  cos.querySelectorAll('tr').forEach(tr => {
    const id = tr.dataset.id;

    tr.querySelector('[data-rol]').addEventListener('change', async (e) => {
      const res = await post({ accio:'canviar_rol', id, rol:e.target.value });
      if (!res.ok) { alert('Error: ' + (res.error||'?')); carregarUsuaris(); }
    });

    tr.querySelector('[data-reset]')?.addEventListener('click', async () => {
      const contrasenya = tr.querySelector('[data-nova-contrasenya]').value;
      if (!contrasenya) return alert('Escriu la nova contrasenya.');
      const res = await post({ accio:'resetejar_contrasenya', id, contrasenya });
      if (res.ok) { alert('Contrasenya actualitzada.'); tr.querySelector('[data-nova-contrasenya]').value=''; }
      else alert('Error: ' + (res.error||'?'));
    });

    tr.querySelector('[data-toggle-actiu]').addEventListener('click', async () => {
      const actiuActual = tr.querySelector('.badge-actiu') !== null;
      const res = await post({ accio:'canviar_actiu', id, actiu: actiuActual ? '0' : '1' });
      if (res.ok) carregarUsuaris();
      else alert('Error: ' + (res.error||'?'));
    });

    tr.querySelector('[data-eliminar]').addEventListener('click', async () => {
      const nom = tr.querySelector('td').firstChild.textContent.trim();
      if (!confirm('Eliminar definitivament l\'usuari "' + nom + '"?\n\nAquesta acció no es pot desfer. Si només vols impedir-li l\'accés, és millor desactivar-lo.')) return;
      const res = await post({ accio:'eliminar', id });
      if (res.ok) carregarUsuaris();
      else alert('Error: ' + (res.error||'?'));
    });
  });
}

document.getElementById('btnCrear').addEventListener('click', async () => {
  const usuari = document.getElementById('nouUsuari').value.trim();
  const contrasenya = document.getElementById('novaContrasenya').value;
  const rol = document.getElementById('nouRol').value;
  const missatge = document.getElementById('missatgeCrear');

  const res = await post({ accio:'crear', usuari, contrasenya, rol });
  if (res.ok) {
    missatge.className = 'text-success mt-2'; missatge.textContent = '✓ Usuari creat correctament.';
    document.getElementById('nouUsuari').value = '';
    document.getElementById('novaContrasenya').value = '';
    carregarUsuaris();
  } else {
    missatge.className = 'text-danger mt-2'; missatge.textContent = '✗ ' + (res.error || 'Error desconegut');
  }
});

carregarUsuaris();
</script>
</body>
</html>
