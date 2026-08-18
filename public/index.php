<?php
require __DIR__ . '/../config/config.php';
require __DIR__ . '/../src/Auth.php';
Auth::requerirLogin();
?>
<!doctype html>
<html lang="ca">
  <!-- TEST DESPLEGAMENT 2026-08-18 -->
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Espai Cràter · Procedències</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jspdf-autotable@3.8.2/dist/jspdf.plugin.autotable.min.js"></script>
<style>
  :root {
    --lava:#c0392b; --lava-dark:#7b241c; --ember:#e67e22;
    --ash:#2c3e50; --smoke:#34495e; --bg:#f5f3f0; --card:#ffffff;
  }
  * { font-family:'Inter',system-ui,sans-serif; }
  body { background:var(--bg); color:#2b2b2b; }

  .topbar {
    background:linear-gradient(120deg,var(--lava-dark),var(--lava) 60%,var(--ember));
    color:#fff; box-shadow:0 2px 12px rgba(0,0,0,.15);
  }
  .topbar .brand { font-weight:800; letter-spacing:.3px; }

  .panel { background:var(--card); border:none; border-radius:16px; box-shadow:0 4px 20px rgba(0,0,0,.06); }
  .panel h5 { font-weight:700; color:var(--ash); margin:0; display:flex; align-items:center; gap:.5rem; }
  .panel h5::before { content:""; width:6px; height:20px; border-radius:3px; background:var(--lava); display:inline-block; }
  .panel-head { display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem; }

  .kpi { border-radius:16px; color:#fff; padding:1.1rem 1.3rem; box-shadow:0 4px 16px rgba(0,0,0,.10); }
  .kpi .label { font-size:.8rem; opacity:.9; text-transform:uppercase; letter-spacing:.5px; }
  .kpi .value { font-size:1.9rem; font-weight:800; line-height:1.1; }
  .kpi.lava  { background:linear-gradient(135deg,var(--lava),var(--lava-dark)); }
  .kpi.ember { background:linear-gradient(135deg,var(--ember),#ca6510); }
  .kpi.ash   { background:linear-gradient(135deg,var(--smoke),var(--ash)); }
  .kpi.green { background:linear-gradient(135deg,#27ae60,#1e8449); }

  .form-label.fw-bold { color:var(--ash); font-size:.85rem; text-transform:uppercase; letter-spacing:.4px; }
  select.form-select:focus { border-color:var(--lava); box-shadow:0 0 0 .2rem rgba(192,57,43,.15); }
  .btn-lava { background:var(--lava); color:#fff; font-weight:600; border:none; }
  .btn-lava:hover { background:var(--lava-dark); color:#fff; }
  .btn-detall { font-size:.78rem; font-weight:600; color:var(--lava); border:1px solid var(--lava); border-radius:8px; padding:.2rem .7rem; background:#fff; }
  .btn-detall:hover { background:var(--lava); color:#fff; }

  .chart-box { position:relative; }
  .chart-box-sm { position:relative; height:150px; }
  .muted-hint { font-size:.8rem; color:#8a8a8a; text-transform:none; letter-spacing:0; font-weight:400; }

  .modal-detall .modal-content { border:none; border-radius:16px; overflow:hidden; }
  .modal-detall .modal-header { background:linear-gradient(120deg,var(--lava-dark),var(--lava)); color:#fff; }
  .taula-detall thead th { cursor:pointer; user-select:none; background:#f3f1ee; position:sticky; top:0; }
  .taula-detall thead th:hover { background:#e9e6e2; }
  .taula-detall .barra-mini { height:8px; border-radius:4px; background:var(--lava); }
  .scroll-taula { max-height:52vh; overflow:auto; }

  .avis-filtre {
    position: fixed; top: 78px; left: 50%; transform: translateX(-50%); z-index: 1050;
    background: linear-gradient(120deg, var(--ember), var(--lava)); color: #fff;
    padding: .55rem 1rem; border-radius: 999px; box-shadow: 0 6px 20px rgba(192,57,43,.35);
    display: flex; align-items: center; gap: .6rem; font-size: .88rem; font-weight: 600;
    max-width: 90vw; animation: apareixer .25s ease-out;
  }
  .avis-filtre .avis-icona { font-size: 1rem; }
  .avis-filtre #avisFiltreText { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .avis-netejar { background: rgba(255,255,255,.22); border: none; color: #fff; font-size: .78rem; font-weight: 600; border-radius: 999px; padding: .15rem .6rem; flex-shrink: 0; }
  .avis-netejar:hover { background: rgba(255,255,255,.35); }
  @keyframes apareixer {
    from { opacity: 0; transform: translateX(-50%) translateY(-8px); }
    to   { opacity: 1; transform: translateX(-50%) translateY(0); }
  }

  .qualitat-pill {
    display: flex; align-items: center; gap: .7rem;
    background: #fff8e1; border-left: 4px solid var(--ember);
    border-radius: 12px; padding: .7rem 1.1rem;
    font-size: .88rem; color: #6b5000;
  }
  .qualitat-pill a { color: var(--lava-dark); font-weight: 700; text-decoration: none; }
  .qualitat-pill a:hover { text-decoration: underline; }

  .comp-card { background:#faf8f6; border-radius:12px; padding:.9rem 1.1rem; height:100%; border:1px solid #eee7e2; }
  .comp-card .comp-label { font-size:.75rem; text-transform:uppercase; letter-spacing:.4px; color:#8a8a8a; font-weight:600; }
  .comp-card .comp-valors { display:flex; align-items:baseline; gap:.5rem; margin-top:.2rem; }
  .comp-card .comp-a { font-size:1.5rem; font-weight:800; color:var(--ash); }
  .comp-card .comp-b { font-size:.9rem; color:#9a9a9a; }
  .comp-delta { font-size:.85rem; font-weight:700; margin-top:.3rem; display:inline-block; padding:.1rem .5rem; border-radius:999px; }
  .comp-delta.puja { background:#e8f8ee; color:#1e8449; }
  .comp-delta.baixa { background:#fdecea; color:var(--lava); }
  .comp-delta.igual { background:#f0f0f0; color:#7a7a7a; }
</style>
</head>
<body>

<nav class="topbar mb-4">
  <div class="container-fluid py-3 px-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
    <span class="brand h4 mb-0 d-flex align-items-center gap-2">
      <img src="assets/logo-icona.png" alt="" style="height:34px; filter:brightness(0) invert(1)">
      Espai Cràter · Procedències
    </span>
    <div class="d-flex gap-2 flex-wrap align-items-center">
      <div class="dropdown">
        <button class="btn btn-light btn-sm fw-semibold dropdown-toggle" data-bs-toggle="dropdown">⬇ Exportar</button>
        <ul class="dropdown-menu dropdown-menu-end">
          <li><a class="dropdown-item" href="#" id="exportarExcel">📊 Excel (.xls)</a></li>
          <li><a class="dropdown-item" href="#" id="exportarPdf">📄 PDF</a></li>
        </ul>
      </div>
      <a href="productes.php" class="btn btn-light btn-sm fw-semibold">🏷️ Productes</a>
      <a href="mapes.php" class="btn btn-light btn-sm fw-semibold">🗺️ Mapes</a>
      <a href="errors.php" class="btn btn-light btn-sm fw-semibold">⚠ Gestió d'errors</a>
      <a href="importacions.php" class="btn btn-light btn-sm fw-semibold">📋 Importacions</a>
      <a href="import.php" class="btn btn-light btn-sm fw-semibold">+ Importar Excel</a>
      <a href="neteja_dades.php" class="btn btn-light btn-sm fw-semibold">🧹 Neteja de dades</a>
      <a href="informes.php" class="btn btn-light btn-sm fw-semibold">📈 Informes</a>
      <?php if (Auth::esAdmin()): ?>
        <a href="usuaris.php" class="btn btn-light btn-sm fw-semibold">👥 Usuaris</a>
      <?php endif; ?>
      <span class="text-white ms-2" style="font-size:.85rem">
        <?= htmlspecialchars(Auth::nomUsuari()) ?> (<?= htmlspecialchars(Auth::rol()) ?>)
      </span>
      <a href="logout.php" class="btn btn-outline-light btn-sm">Sortir</a>
    </div>
  </div>
</nav>

<div id="avisFiltre" class="avis-filtre d-none">
  <span class="avis-icona">🔎</span>
  <span id="avisFiltreText">Filtrant dades…</span>
  <button id="avisFiltreNetejar" class="avis-netejar" title="Treure tots els filtres">Netejar ✕</button>
</div>

<div class="container-fluid px-4 pb-5">

  <div class="row g-3 mb-4">
    <div class="col-6 col-lg-3"><div class="kpi lava"><div class="label">Total visitants</div><div class="value" id="kpiTotal">–</div></div></div>
    <div class="col-6 col-lg-3"><div class="kpi ember"><div class="label">Països diferents</div><div class="value" id="kpiPaisos">–</div></div></div>
    <div class="col-6 col-lg-3"><div class="kpi ash"><div class="label">Comarques catalanes</div><div class="value" id="kpiComarques">–</div></div></div>
    <div class="col-6 col-lg-3"><div class="kpi green"><div class="label">% internacional</div><div class="value" id="kpiInternacional">–</div></div></div>
  </div>

  <div id="qualitatDades" class="mb-4" style="display:none"></div>

  <!-- Comparativa entre períodes -->
  <div class="panel p-4 mb-4">
    <div class="panel-head mb-3">
      <h5>Comparativa entre períodes</h5>
      <div class="d-flex gap-2 align-items-center flex-wrap">
        <div class="btn-group btn-group-sm" role="group">
          <input type="radio" class="btn-check" name="modeComp" id="modeMes" value="mes" checked>
          <label class="btn btn-outline-secondary" for="modeMes">Mes vs mes</label>
          <input type="radio" class="btn-check" name="modeComp" id="modeAny" value="any">
          <label class="btn btn-outline-secondary" for="modeAny">Any complet vs any complet</label>
        </div>
        <span id="selectorsMes" class="d-flex gap-2 align-items-center">
          <select id="compA" class="form-select form-select-sm" style="width:auto"></select>
          <span class="text-muted fw-bold">vs</span>
          <select id="compB" class="form-select form-select-sm" style="width:auto"></select>
        </span>
        <span id="selectorsAny" class="d-flex gap-2 align-items-center" style="display:none">
          <select id="compAnyA" class="form-select form-select-sm" style="width:auto"></select>
          <span class="text-muted fw-bold">vs</span>
          <select id="compAnyB" class="form-select form-select-sm" style="width:auto"></select>
        </span>
        <button id="btnComparar" class="btn btn-lava btn-sm">Comparar</button>
      </div>
    </div>
    <div id="compResultat" class="row g-3">
      <div class="col-12 text-muted" style="font-size:.88rem">
        Tria dos períodes per veure'n l'evolució. Respecta el filtre de producte que tinguis aplicat.
      </div>
    </div>
  </div>

  <div class="panel p-4 mb-4">
    <div class="row g-3 align-items-end">
      <div class="col-md-5">
        <label class="form-label fw-bold">Producte <span class="muted-hint">(Ctrl per triar-ne diversos)</span></label>
        <select id="filtreProductes" class="form-select" multiple size="4"></select>
      </div>
      <div class="col-md-3">
        <label class="form-label fw-bold">Any</label>
        <select id="filtreAnys" class="form-select" multiple size="4"></select>
      </div>
      <div class="col-md-3">
        <label class="form-label fw-bold">Mes</label>
        <select id="filtreMesos" class="form-select" multiple size="4"></select>
      </div>
      <div class="col-md-1 d-grid gap-2">
        <button id="btnAplicar" class="btn btn-lava">Filtrar</button>
        <button id="btnNetejar" class="btn btn-outline-secondary btn-sm">Netejar</button>
      </div>
    </div>
  </div>

  <div class="panel p-4 mb-4">
    <div class="panel-head"><h5>Distribució per canal de venda</h5></div>
    <div class="chart-box-sm"><canvas id="chartCanals"></canvas></div>
  </div>

  <div class="panel p-4 mb-4">
    <div class="panel-head"><h5>Evolució mensual de visitants</h5></div>
    <div class="chart-box" style="height:280px"><canvas id="chartEvolucio"></canvas></div>
  </div>

  <div class="row g-4">
    <div class="col-lg-6">
      <div class="panel p-4">
        <div class="panel-head">
          <h5>Usuaris per país</h5>
          <button class="btn-detall" data-detall="usuaris_per_pais" data-titol="Usuaris per país" data-color="#c0392b">Veure detall ⤢</button>
        </div>
        <div class="chart-box"><canvas id="chartPais"></canvas></div>
      </div>
    </div>
    <div class="col-lg-6">
      <div class="panel p-4">
        <div class="panel-head">
          <h5>Usuaris per país (sense Espanya)</h5>
          <button class="btn-detall" data-detall="usuaris_per_pais_sense_espanya" data-titol="Usuaris per país (sense Espanya)" data-color="#e67e22">Veure detall ⤢</button>
        </div>
        <div class="chart-box"><canvas id="chartPaisSenseEspanya"></canvas></div>
      </div>
    </div>
    <div class="col-lg-6">
      <div class="panel p-4">
        <div class="panel-head">
          <h5>Usuaris per província</h5>
          <button class="btn-detall" data-detall="usuaris_per_provincia" data-titol="Usuaris per província" data-color="#2c3e50">Veure detall ⤢</button>
        </div>
        <div class="chart-box"><canvas id="chartProvincia"></canvas></div>
      </div>
    </div>
    <div class="col-lg-6">
      <div class="panel p-4">
        <div class="panel-head">
          <h5>Usuaris per comarca</h5>
          <button class="btn-detall" data-detall="usuaris_per_comarca" data-titol="Usuaris per comarca" data-color="#16a085">Veure detall ⤢</button>
        </div>
        <div class="chart-box"><canvas id="chartComarca"></canvas></div>
      </div>
    </div>
  </div>
</div>

<!-- Modal de detall -->
<div class="modal modal-detall fade" id="modalDetall" tabindex="-1">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalTitol">Detall</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="row g-4">
          <div class="col-lg-6">
            <input type="text" id="cercadorDetall" class="form-control mb-2" placeholder="🔍 Cercar procedència…">
            <div class="scroll-taula">
              <table class="table table-sm table-hover taula-detall mb-0">
                <thead>
                  <tr>
                    <th data-sort="rank" style="width:48px">#</th>
                    <th data-sort="etiqueta">Procedència</th>
                    <th data-sort="total" class="text-end">Pax</th>
                    <th data-sort="pct" class="text-end">%</th>
                    <th style="width:22%"></th>
                  </tr>
                </thead>
                <tbody id="cosTaulaDetall"></tbody>
              </table>
            </div>
          </div>
          <div class="col-lg-6">
            <div style="position:relative; height:60vh"><canvas id="chartDetall"></canvas></div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const charts = {};
const TOP_N = 12;
const PALETA = ['#c0392b','#e67e22','#2c3e50','#27ae60','#8e44ad','#16a085','#d35400','#2980b9'];

function degradat(ctx, color) {
    const g = ctx.createLinearGradient(0, 0, 0, ctx.canvas.height);
    g.addColorStop(0, color); g.addColorStop(1, color + '99');
    return g;
}

function barres(id, dades, colorBase, limit) {
    let files = dades;
    if (limit && dades.length > limit) files = dades.slice(0, limit);
    const labels = files.map(r => r.etiqueta);
    const valors = files.map(r => Number(r.total));

    const canvas = document.getElementById(id);
    const box = canvas.parentElement;
    box.style.height = Math.max(300, labels.length * 30 + 40) + 'px';

    const ctx = canvas.getContext('2d');
    if (charts[id]) charts[id].destroy();
    charts[id] = new Chart(ctx, {
        type: 'bar',
        data: { labels, datasets: [{
            label:'Pax', data:valors,
            backgroundColor: degradat(ctx, colorBase || '#c0392b'),
            borderRadius:5, borderSkipped:false, maxBarThickness:24
        }]},
        options: {
            indexAxis:'y', responsive:true, maintainAspectRatio:false,
            plugins:{
                legend:{ display:false },
                tooltip:{ callbacks:{ label:c => ' ' + c.parsed.x.toLocaleString('ca') + ' pax' } }
            },
            scales:{
                x:{ grid:{ color:'#eee' }, ticks:{ color:'#888' } },
                y:{ grid:{ display:false }, ticks:{ color:'#444', font:{ weight:'500' }, autoSkip:false } }
            }
        }
    });
}

function dona(id, labels, data) {
    const ctx = document.getElementById(id).getContext('2d');
    if (charts[id]) charts[id].destroy();
    charts[id] = new Chart(ctx, {
        type:'doughnut',
        data:{ labels, datasets:[{ data, backgroundColor:PALETA, borderWidth:3, borderColor:'#fff' }] },
        options:{ cutout:'62%', responsive:true, maintainAspectRatio:false,
            plugins:{ legend:{ position:'right', labels:{ boxWidth:14, padding:14, font:{ size:13 } } } } }
    });
}

function linia(id, files) {
    const labels = files.map(f => {
        const s = String(f.anymes);
        return NOMS_MESOS[parseInt(s.slice(4,6),10)-1] + ' ' + s.slice(2,4);
    });
    const valors = files.map(f => Number(f.total));
    const ctx = document.getElementById(id).getContext('2d');
    if (charts[id]) charts[id].destroy();
    const g = ctx.createLinearGradient(0,0,0,260);
    g.addColorStop(0, 'rgba(192,57,43,.25)'); g.addColorStop(1, 'rgba(192,57,43,0)');
    charts[id] = new Chart(ctx, {
        type:'line',
        data:{ labels, datasets:[{
            data:valors, borderColor:'#c0392b', backgroundColor:g, fill:true,
            tension:.3, pointBackgroundColor:'#c0392b', pointRadius:4, pointHoverRadius:6,
        }]},
        options:{ responsive:true, maintainAspectRatio:false,
            plugins:{ legend:{ display:false },
                tooltip:{ callbacks:{ label:c => ' ' + c.parsed.y.toLocaleString('ca') + ' pax' } } },
            scales:{ x:{ grid:{ display:false } }, y:{ grid:{ color:'#eee' }, beginAtZero:true } } }
    });
}

function seleccionats(id) { return Array.from(document.getElementById(id).selectedOptions).map(o => o.value); }

function construirQuery() {
    const p = new URLSearchParams();
    seleccionats('filtreProductes').forEach(v => p.append('grups[]', v));
    seleccionats('filtreAnys').forEach(v => p.append('anys[]', v));
    seleccionats('filtreMesos').forEach(v => p.append('mesos[]', v));
    return p;
}

const NOMS_MESOS = ['Gen','Feb','Mar','Abr','Mai','Jun','Jul','Ago','Set','Oct','Nov','Des'];

function actualitzarAvisFiltre() {
    const productes = seleccionats('filtreProductes');
    const anys = seleccionats('filtreAnys');
    const mesos = seleccionats('filtreMesos');
    const avis = document.getElementById('avisFiltre');
    const total = productes.length + anys.length + mesos.length;

    if (total === 0) {
        avis.classList.add('d-none');
        return;
    }

    const parts = [];
    if (productes.length) {
        parts.push(productes.length === 1
            ? productes[0]
            : `${productes.length} productes`);
    }
    if (anys.length) parts.push(anys.sort().join('/'));
    if (mesos.length) parts.push(mesos.sort((a,b)=>a-b).map(m => NOMS_MESOS[m-1]).join('/'));

    document.getElementById('avisFiltreText').textContent = 'Filtrant per: ' + parts.join(' · ');
    avis.classList.remove('d-none');
}

document.getElementById('avisFiltreNetejar').addEventListener('click', () => document.getElementById('btnNetejar').click());

async function json(url) { return (await fetch(url)).json(); }

async function carregarFiltres() {
    const productes = await json('api_dades.php?accio=productes_disponibles');
    const selProd = document.getElementById('filtreProductes');
    productes.forEach(p => selProd.add(new Option(p.grup, p.grup)));

    const periodes = await json('api_dades.php?accio=periodes_disponibles');
    const anys  = [...new Set(periodes.map(p => p.any_))].sort((a,b) => b-a);
    const mesos = [...new Set(periodes.map(p => p.mes))].sort((a,b) => a-b);
    const selAny = document.getElementById('filtreAnys');
    anys.forEach(a => selAny.add(new Option(a, a)));
    const selMes = document.getElementById('filtreMesos');
    mesos.forEach(m => selMes.add(new Option(NOMS_MESOS[m-1], m)));
}

let ultimesDades = { canals: [], pais: [], paisSense: [], provincia: [], comarca: [], evolucio: [] };

async function carregarGrafics() {
    actualitzarAvisFiltre();
    const q = construirQuery();

    const canals = await json('api_dades.php?accio=percentatge_canals&' + q);
    const totalCanals = canals.reduce((s, r) => s + Number(r.total), 0) || 1;
    dona('chartCanals',
        canals.map(r => `${r.etiqueta} · ${(100 * r.total / totalCanals).toFixed(1)}%`),
        canals.map(r => r.total));

    const evolucio = await json('api_dades.php?accio=evolucio_temporal&' + q);
    linia('chartEvolucio', evolucio);

    const pais = await json('api_dades.php?accio=usuaris_per_pais&' + q);
    barres('chartPais', pais, '#c0392b', TOP_N);

    const paisSense = await json('api_dades.php?accio=usuaris_per_pais_sense_espanya&' + q);
    barres('chartPaisSenseEspanya', paisSense, '#e67e22', TOP_N);

    const provincia = await json('api_dades.php?accio=usuaris_per_provincia&' + q);
    barres('chartProvincia', provincia, '#2c3e50', TOP_N);

    const comarca = await json('api_dades.php?accio=usuaris_per_comarca&' + q);
    barres('chartComarca', comarca, '#16a085', TOP_N);

    ultimesDades = { canals, pais, paisSense, provincia, comarca, evolucio };

    // El total real inclou TOTES les entrades (fins i tot amb país buit),
    // per això es calcula a partir del total per canal, no de la llista
    // de països (que exclou les que no tenen país assignat).
    const total = totalCanals;
    const totalSense = paisSense.reduce((s, r) => s + Number(r.total), 0);
    document.getElementById('kpiTotal').textContent = total.toLocaleString('ca');
    document.getElementById('kpiPaisos').textContent = pais.length;
    document.getElementById('kpiComarques').textContent = comarca.length;
    document.getElementById('kpiInternacional').textContent =
        (total ? (100 * totalSense / total).toFixed(1) : 0) + '%';
}

document.getElementById('btnAplicar').addEventListener('click', carregarGrafics);
document.getElementById('btnNetejar').addEventListener('click', () => {
    ['filtreProductes','filtreAnys','filtreMesos'].forEach(id =>
        Array.from(document.getElementById(id).options).forEach(o => o.selected = false));
    carregarGrafics();
});

// ---------- Comparativa entre períodes ----------
function etiquetaPeriode(anymes) {
    const s = String(anymes);
    return NOMS_MESOS[parseInt(s.slice(4,6),10)-1] + ' ' + s.slice(0,4);
}

async function omplirSelectorsComparativa() {
    const periodes = await json('api_dades.php?accio=periodes_disponibles');

    // --- Selectors de mes concret ---
    const llistaMesos = periodes
        .map(p => p.any_ * 100 + p.mes)
        .sort((a,b) => b - a);
    if (llistaMesos.length) {
        const selA = document.getElementById('compA');
        const selB = document.getElementById('compB');
        llistaMesos.forEach(am => {
            selA.add(new Option(etiquetaPeriode(am), am));
            selB.add(new Option(etiquetaPeriode(am), am));
        });
        selA.value = llistaMesos[0];
        const mateixMesAnyAnterior = llistaMesos[0] - 100;
        selB.value = llistaMesos.includes(mateixMesAnyAnterior)
            ? mateixMesAnyAnterior
            : (llistaMesos[1] ?? llistaMesos[0]);
    }

    // --- Selectors d'any complet ---
    const llistaAnys = [...new Set(periodes.map(p => p.any_))].sort((a,b) => b - a);
    if (llistaAnys.length) {
        const selAnyA = document.getElementById('compAnyA');
        const selAnyB = document.getElementById('compAnyB');
        llistaAnys.forEach(a => {
            selAnyA.add(new Option(a, a));
            selAnyB.add(new Option(a, a));
        });
        selAnyA.value = llistaAnys[0];
        selAnyB.value = llistaAnys[1] ?? llistaAnys[0];
    }
}

function modeComparativaActual() {
    return document.querySelector('input[name="modeComp"]:checked').value;
}

document.querySelectorAll('input[name="modeComp"]').forEach(radio => {
    radio.addEventListener('change', () => {
        const esAny = modeComparativaActual() === 'any';
        document.getElementById('selectorsMes').style.display = esAny ? 'none' : 'flex';
        document.getElementById('selectorsAny').style.display = esAny ? 'flex' : 'none';
        carregarComparativa();
    });
});

async function carregarComparativa() {
    const mode = modeComparativaActual();
    const q = construirQuery();
    let res, etiquetaA, etiquetaB;

    if (mode === 'any') {
        const anyA = document.getElementById('compAnyA').value;
        const anyB = document.getElementById('compAnyB').value;
        if (!anyA || !anyB) return;
        res = await json(`api_dades.php?accio=comparativa_any&any_a=${anyA}&any_b=${anyB}&${q}`);
        etiquetaA = anyA; etiquetaB = anyB;
    } else {
        const a = document.getElementById('compA').value;
        const b = document.getElementById('compB').value;
        if (!a || !b) return;
        res = await json(`api_dades.php?accio=comparativa&anymes_a=${a}&anymes_b=${b}&${q}`);
        etiquetaA = etiquetaPeriode(a); etiquetaB = etiquetaPeriode(b);
    }
    if (res.error) return;

    const metriques = [
        { clau: 'total', titol: 'Total visitants' },
        { clau: 'internacional', titol: 'Visitants internacionals' },
        { clau: 'paisos', titol: 'Països diferents' },
        { clau: 'comarques', titol: 'Comarques diferents' },
    ];

    document.getElementById('compResultat').innerHTML = `
      <div class="col-12">
        <span class="text-muted" style="font-size:.85rem">Comparant: <b>${etiquetaA}</b> vs <b>${etiquetaB}</b></span>
      </div>
    ` + metriques.map(m => {
        const va = Number(res.a[m.clau] || 0);
        const vb = Number(res.b[m.clau] || 0);
        let delta, classe, fletxa;
        if (vb === 0) {
            delta = va > 0 ? 'nou' : '—';
            classe = va > 0 ? 'puja' : 'igual';
            fletxa = '';
        } else {
            const pct = 100 * (va - vb) / vb;
            const signe = pct > 0 ? '+' : '';
            delta = `${signe}${pct.toFixed(1)}%`;
            classe = pct > 0.5 ? 'puja' : (pct < -0.5 ? 'baixa' : 'igual');
            fletxa = pct > 0.5 ? '▲ ' : (pct < -0.5 ? '▼ ' : '= ');
        }
        return `
          <div class="col-6 col-lg-3">
            <div class="comp-card">
              <div class="comp-label">${m.titol}</div>
              <div class="comp-valors">
                <span class="comp-a">${va.toLocaleString('ca')}</span>
                <span class="comp-b">vs ${vb.toLocaleString('ca')}</span>
              </div>
              <span class="comp-delta ${classe}">${fletxa}${delta}</span>
            </div>
          </div>`;
    }).join('');
}

document.getElementById('btnComparar').addEventListener('click', carregarComparativa);

async function carregarQualitatDades() {
    try {
        const resum = await json('api_errors.php?accio=resum');
        const cont = document.getElementById('qualitatDades');
        const categories = {
            pais_buit: 'país buit', cp_buit: 'CP buit', cp_format: 'CP mal format',
            provincia_no: 'província no assignada', comarca_no: 'comarca no assignada',
            dup_escolar: 'possible duplicat escolar',
        };
        const problemes = Object.entries(resum).filter(([,d]) => d.files > 0);
        if (!problemes.length) { cont.style.display = 'none'; return; }

        const totalFiles = problemes.reduce((s,[,d]) => s + d.files, 0);
        const resumTxt = problemes.map(([k,d]) => `${d.files} ${categories[k] || k}`).join(' · ');
        cont.innerHTML = `
          <div class="qualitat-pill">
            <span>⚠</span>
            <span><b>${totalFiles} incidències</b> pendents de revisar: ${resumTxt}.
            <a href="errors.php">Anar a Gestió d'errors →</a></span>
          </div>`;
        cont.style.display = 'block';
    } catch (e) { /* silenciós */ }
}

// ---------- Modal de detall ----------
let detallDades = [];
let detallColor = '#c0392b';
let ordreCol = 'total', ordreAsc = false;
const modalDetall = new bootstrap.Modal(document.getElementById('modalDetall'));

document.querySelectorAll('.btn-detall').forEach(btn => {
    btn.addEventListener('click', async () => {
        const accio = btn.dataset.detall;
        detallColor = btn.dataset.color;
        document.getElementById('modalTitol').textContent = btn.dataset.titol + ' · rànquing complet';
        document.getElementById('cercadorDetall').value = '';
        const q = construirQuery();
        detallDades = await json('api_dades.php?accio=' + accio + '&' + q);
        detallDades = detallDades.map(r => ({ etiqueta: r.etiqueta, total: Number(r.total) }));
        ordreCol = 'total'; ordreAsc = false;
        modalDetall.show();
        renderitzarDetall();
    });
});

function renderitzarDetall() {
    const filtre = document.getElementById('cercadorDetall').value.toLowerCase();
    let files = detallDades.filter(r => (r.etiqueta || '').toLowerCase().includes(filtre));

    const totalGlobal = detallDades.reduce((s, r) => s + r.total, 0) || 1;
    files = files.map(r => ({ ...r, pct: 100 * r.total / totalGlobal }));

    files.sort((a, b) => {
        let va = a[ordreCol], vb = b[ordreCol];
        if (ordreCol === 'etiqueta') { va = (va||'').toLowerCase(); vb = (vb||'').toLowerCase(); }
        if (va < vb) return ordreAsc ? -1 : 1;
        if (va > vb) return ordreAsc ? 1 : -1;
        return 0;
    });

    const maxVal = Math.max(...files.map(r => r.total), 1);
    const cos = document.getElementById('cosTaulaDetall');
    cos.innerHTML = files.map((r, i) => `
        <tr>
          <td class="text-muted">${i + 1}</td>
          <td>${r.etiqueta ?? '(sense dada)'}</td>
          <td class="text-end fw-semibold">${r.total.toLocaleString('ca')}</td>
          <td class="text-end text-muted">${r.pct.toFixed(1)}%</td>
          <td><div class="barra-mini" style="width:${100 * r.total / maxVal}%; background:${detallColor}"></div></td>
        </tr>`).join('');

    const canvas = document.getElementById('chartDetall');
    if (charts['chartDetall']) charts['chartDetall'].destroy();
    const ctx = canvas.getContext('2d');
    charts['chartDetall'] = new Chart(ctx, {
        type:'bar',
        data:{ labels: files.map(r => r.etiqueta), datasets:[{
            data: files.map(r => r.total),
            backgroundColor: detallColor, borderRadius:4, maxBarThickness:18
        }]},
        options:{ indexAxis:'y', responsive:true, maintainAspectRatio:false,
            plugins:{ legend:{ display:false },
                tooltip:{ callbacks:{ label:c => ' ' + c.parsed.x.toLocaleString('ca') + ' pax' } } },
            scales:{ x:{ grid:{ color:'#eee' } }, y:{ grid:{ display:false }, ticks:{ autoSkip:false, font:{ size:11 } } } } }
    });
}

document.getElementById('cercadorDetall').addEventListener('input', renderitzarDetall);
document.querySelectorAll('.taula-detall thead th[data-sort]').forEach(th => {
    th.addEventListener('click', () => {
        const col = th.dataset.sort === 'rank' ? 'total' : th.dataset.sort;
        if (ordreCol === col) ordreAsc = !ordreAsc; else { ordreCol = col; ordreAsc = false; }
        renderitzarDetall();
    });
});

// ---------- Exportació ----------
function descripcioFiltres() {
    const productes = seleccionats('filtreProductes');
    const anys = seleccionats('filtreAnys');
    const mesos = seleccionats('filtreMesos');
    const parts = [];
    if (productes.length) parts.push('Producte: ' + productes.join(', '));
    if (anys.length) parts.push('Any: ' + anys.sort().join(', '));
    if (mesos.length) parts.push('Mes: ' + mesos.sort((a,b)=>a-b).map(m => NOMS_MESOS[m-1]).join(', '));
    return parts.length ? parts.join('  ·  ') : 'Sense filtres (totes les dades)';
}

function taulaHtml(titol, files) {
    const totalReal = files.reduce((s, r) => s + Number(r.total), 0);
    const totalCol = totalReal || 1;
    let html = `<h3>${titol}</h3><table border="1" cellpadding="4" style="border-collapse:collapse">
        <tr style="background:#c0392b;color:#fff"><th>Procedència</th><th>Pax</th><th>%</th></tr>`;
    files.forEach(r => {
        const pct = (100 * Number(r.total) / totalCol).toFixed(1);
        html += `<tr><td>${r.etiqueta ?? '(sense dada)'}</td><td>${r.total}</td><td>${pct}%</td></tr>`;
    });
    html += `<tr style="background:#f3f1ee;font-weight:bold"><td>TOTAL</td><td>${totalReal}</td><td>100.0%</td></tr>`;
    html += `</table><br>`;
    return html;
}

document.getElementById('exportarExcel').addEventListener('click', (e) => {
    e.preventDefault();
    const ara = new Date().toLocaleString('ca');
    let html = `
        <html><head><meta charset="UTF-8"></head><body>
        <h2>Espai Cràter · Procedències</h2>
        <p><b>Generat:</b> ${ara}<br><b>Filtres:</b> ${descripcioFiltres()}</p>
        ${taulaHtml('% per canal', ultimesDades.canals)}
        ${taulaHtml('Evolució mensual de visitants', ultimesDades.evolucio.map(f => ({ etiqueta: etiquetaPeriode(f.anymes), total: f.total })))}
        ${taulaHtml('Usuaris per país', ultimesDades.pais)}
        ${taulaHtml('Usuaris per país (sense Espanya)', ultimesDades.paisSense)}
        ${taulaHtml('Usuaris per província', ultimesDades.provincia)}
        ${taulaHtml('Usuaris per comarca', ultimesDades.comarca)}
        </body></html>`;

    const bom = '\uFEFF';
    const blob = new Blob([bom + html], { type: 'application/vnd.ms-excel;charset=utf-8;' });
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = `procedencies_${new Date().toISOString().slice(0,10)}.xls`;
    a.click();
});

document.getElementById('exportarPdf').addEventListener('click', async (e) => {
    e.preventDefault();
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF({ orientation: 'p', unit: 'pt', format: 'a4' });
    const marge = 40;
    let y = marge;

    doc.setFontSize(18); doc.setTextColor(192, 57, 43);
    doc.text('Espai Cràter · Procedències', marge, y); y += 22;
    doc.setFontSize(10); doc.setTextColor(90, 90, 90);
    doc.text('Generat: ' + new Date().toLocaleString('ca'), marge, y); y += 14;
    doc.text('Filtres: ' + descripcioFiltres(), marge, y); y += 24;

    const seccions = [
        { titol: '% d\'entrades per canal', canvasId: 'chartCanals', dades: ultimesDades.canals },
        { titol: 'Evolució mensual de visitants', canvasId: 'chartEvolucio',
          dades: ultimesDades.evolucio.map(f => ({ etiqueta: etiquetaPeriode(f.anymes), total: f.total })) },
        { titol: 'Usuaris per país', canvasId: 'chartPais', dades: ultimesDades.pais },
        { titol: 'Usuaris per país (sense Espanya)', canvasId: 'chartPaisSenseEspanya', dades: ultimesDades.paisSense },
        { titol: 'Usuaris per província', canvasId: 'chartProvincia', dades: ultimesDades.provincia },
        { titol: 'Usuaris per comarca', canvasId: 'chartComarca', dades: ultimesDades.comarca },
    ];

    seccions.forEach((sec, idx) => {
        if (idx > 0) { doc.addPage(); y = marge; }
        doc.setFontSize(13); doc.setTextColor(44, 62, 80);
        doc.text(sec.titol, marge, y); y += 12;

        const canvas = document.getElementById(sec.canvasId);
        const img = canvas.toDataURL('image/png', 1.0);
        const amplada = 515;
        const alcada = amplada * (canvas.height / canvas.width);
        const alcadaMax = 260;
        const alcadaFinal = Math.min(alcada, alcadaMax);
        const ampladaFinal = alcadaFinal === alcada ? amplada : amplada * (alcadaFinal / alcada);
        doc.addImage(img, 'PNG', marge, y, ampladaFinal, alcadaFinal);
        y += alcadaFinal + 16;

        const totalReal = sec.dades.reduce((s, r) => s + Number(r.total), 0);
        const totalCol = totalReal || 1;
        const cosTaula = sec.dades.map(r => [r.etiqueta ?? '(sense dada)', r.total, (100*Number(r.total)/totalCol).toFixed(1)+'%']);
        cosTaula.push(['TOTAL', totalReal, '100.0%']);

        doc.autoTable({
            startY: y,
            margin: { left: marge, right: marge },
            head: [['Procedència', 'Pax', '%']],
            body: cosTaula,
            styles: { fontSize: 8, cellPadding: 3 },
            headStyles: { fillColor: [192, 57, 43] },
            theme: 'striped',
            didParseCell: (data) => {
                if (data.row.index === cosTaula.length - 1 && data.section === 'body') {
                    data.cell.styles.fontStyle = 'bold';
                    data.cell.styles.fillColor = [243, 241, 238];
                    data.cell.styles.textColor = [44, 62, 80];
                }
            }
        });
    });

    doc.save(`procedencies_${new Date().toISOString().slice(0,10)}.pdf`);
});

(async () => {
    await carregarFiltres();
    await carregarGrafics();
    carregarQualitatDades();
    await omplirSelectorsComparativa();
    carregarComparativa();
})();
</script>
</body>
</html>
