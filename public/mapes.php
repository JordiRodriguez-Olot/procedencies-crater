<?php
require __DIR__ . '/../config/config.php';
require __DIR__ . '/../src/Auth.php';
Auth::requerirLogin();
?>
<?php renderCapcalera('Mapes de procedència', usuariActual()); ?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<style>
  :root { --lava:#c0392b; --lava-dark:#7b241c; --ember:#e67e22; --ash:#2c3e50; --bg:#f5f3f0; }
  .panel { background:#fff; border:none; border-radius:16px; box-shadow:0 4px 20px rgba(0,0,0,.06); }
  .form-label.fw-bold { color:var(--ash); font-size:.85rem; text-transform:uppercase; letter-spacing:.4px; }
  .btn-lava { background:var(--lava); color:#fff; font-weight:600; border:none; }
  .btn-lava:hover { background:var(--lava-dark); color:#fff; }
  .muted-hint { font-size:.8rem; color:#8a8a8a; text-transform:none; letter-spacing:0; font-weight:400; }

  #mapa { height: 66vh; border-radius: 14px; background:#eceae7; }
  .nav-pills .nav-link { color:var(--ash); font-weight:600; font-size:.9rem; }
  .nav-pills .nav-link.active { background:var(--lava); }

  .llegenda { background:#fff; padding:.6rem .8rem; border-radius:10px; box-shadow:0 2px 10px rgba(0,0,0,.15); font-size:.8rem; line-height:1.5; }
  .llegenda i { width:16px; height:12px; float:left; margin-right:6px; opacity:.9; border-radius:2px; }

  .estat { font-size:.85rem; }
  .sense-dada { color:#8a8a8a; }
  .avis-carrega { background:#fff8e1; border-left:4px solid var(--ember); border-radius:8px; padding:.7rem 1rem; font-size:.85rem; }
</style>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
  <div class="titol-pagina m-0"><h1>Mapes de procedència</h1></div>
  <div class="d-flex gap-2 flex-wrap align-items-center">
      
  </div>
</div>


<div class="container-fluid px-0 pb-5">

  <div class="panel p-4 mb-4">
    <div class="row g-3 align-items-end">
      <div class="col-md-5">
        <label class="form-label fw-bold">Producte <span class="muted-hint">(Ctrl per triar-ne diversos)</span></label>
        <select id="filtreProductes" class="form-select" multiple size="4"></select>
      </div>
      <div class="col-md-2">
        <label class="form-label fw-bold">Any</label>
        <select id="filtreAnys" class="form-select" multiple size="4"></select>
      </div>
      <div class="col-md-2">
        <label class="form-label fw-bold">Mes</label>
        <select id="filtreMesos" class="form-select" multiple size="4"></select>
      </div>
      <div class="col-md-3 d-grid gap-2">
        <button id="btnAplicar" class="btn btn-lava">Aplicar filtres</button>
        <button id="btnNetejar" class="btn btn-outline-secondary btn-sm">Netejar</button>
      </div>
    </div>
  </div>

  <div class="panel p-4">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
      <ul class="nav nav-pills" id="pestanyes">
        <li class="nav-item"><button class="nav-link active" data-vista="comarca">🏔️ Catalunya (comarques)</button></li>
        <li class="nav-item"><button class="nav-link" data-vista="provincia">🇪🇸 Espanya (províncies)</button></li>
        <li class="nav-item"><button class="nav-link" data-vista="pais">🌍 Món (països)</button></li>
      </ul>
      <div class="estat text-muted" id="estat"></div>
    </div>

    <div id="avisCarrega" class="avis-carrega mb-3" style="display:none"></div>
    <div id="detallSenseUbicar" class="mb-2" style="font-size:.82rem"></div>
    <div id="mapa"></div>

    <div class="mt-3 text-muted" style="font-size:.82rem">
      El color indica el nombre de visitants (pax). Passa el ratolí per damunt d'una zona
      per veure'n el detall. Les zones sense visitants apareixen en gris.
    </div>
  </div>
</div>

<?php renderPeu([], false); ?>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
// ---------------- Configuració ----------------
const NOMS_MESOS = ['Gen','Feb','Mar','Abr','Mai','Jun','Jul','Ago','Set','Oct','Nov','Des'];

const FONTS = {
  comarca: [
    'geo/comarques.geojson',
    'https://cdn.jsdelivr.net/gh/aariste/GeoJSON-Mapas@master/comarques-compressed.geojson',
    'https://raw.githubusercontent.com/aariste/GeoJSON-Mapas/master/comarques-compressed.geojson',
  ],
  provincia: [
    'geo/provincies.geojson',
    'https://cdn.jsdelivr.net/gh/codeforgermany/click_that_hood@main/public/data/spain-provinces.geojson',
    'https://raw.githubusercontent.com/codeforgermany/click_that_hood/main/public/data/spain-provinces.geojson',
  ],
  pais: [
    'geo/paisos.geojson',
    'https://cdn.jsdelivr.net/gh/johan/world.geo.json@master/countries.geo.json',
    'https://raw.githubusercontent.com/johan/world.geo.json/master/countries.geo.json',
  ],
};

const DESCARREGA_MANUAL = {
  comarca: 'https://github.com/aariste/GeoJSON-Mapas/blob/master/comarques-compressed.geojson',
  provincia: 'https://github.com/codeforgermany/click_that_hood/blob/main/public/data/spain-provinces.geojson',
  pais: 'https://github.com/johan/world.geo.json/blob/master/countries.geo.json',
};

// Països en castellà (Clorian) -> codi ISO 3166-1 alfa-3. Mètode principal
// de coincidència per a països: molt més fiable que comparar noms, ja que
// no depèn de l'idioma que faci servir cada mapa concret.
const PAIS_ES_ISO3 = {
  'espana':'ESP','francia':'FRA','alemania':'DEU','italia':'ITA','portugal':'PRT',
  'reino unido':'GBR','paises bajos':'NLD','holanda':'NLD','belgica':'BEL',
  'suiza':'CHE','austria':'AUT','irlanda':'IRL','luxemburgo':'LUX','malta':'MLT',
  'islandia':'ISL','monaco':'MCO','andorra':'AND','liechtenstein':'LIE',
  'san marino':'SMR','ciudad del vaticano':'VAT',
  'suecia':'SWE','noruega':'NOR','dinamarca':'DNK','finlandia':'FIN',
  'polonia':'POL','republica checa':'CZE','chequia':'CZE','eslovaquia':'SVK',
  'hungria':'HUN','rumania':'ROU','bulgaria':'BGR','eslovenia':'SVN',
  'croacia':'HRV','serbia':'SRB','bosnia':'BIH','bosnia y herzegovina':'BIH',
  'montenegro':'MNE','macedonia del norte':'MKD','macedonia':'MKD',
  'albania':'ALB','kosovo':'XKX','lituania':'LTU','letonia':'LVA',
  'estonia':'EST','ucrania':'UKR','bielorrusia':'BLR','moldavia':'MDA',
  'rusia':'RUS','grecia':'GRC','turquia':'TUR','chipre':'CYP',
  'estados unidos':'USA','eeuu':'USA','canada':'CAN','mexico':'MEX',
  'guatemala':'GTM','belice':'BLZ','honduras':'HND','el salvador':'SLV',
  'nicaragua':'NIC','costa rica':'CRI','panama':'PAN','cuba':'CUB',
  'republica dominicana':'DOM','haiti':'HTI','jamaica':'JAM',
  'trinidad y tobago':'TTO','bahamas':'BHS','barbados':'BRB',
  'argentina':'ARG','brasil':'BRA','chile':'CHL','colombia':'COL',
  'peru':'PER','uruguay':'URY','paraguay':'PRY','bolivia':'BOL',
  'ecuador':'ECU','venezuela':'VEN','guyana':'GUY','surinam':'SUR',
  'japon':'JPN','china':'CHN','india':'IND','australia':'AUS',
  'nueva zelanda':'NZL','corea del sur':'KOR','corea del norte':'PRK',
  'singapur':'SGP','vietnam':'VNM','tailandia':'THA','filipinas':'PHL',
  'indonesia':'IDN','malasia':'MYS','taiwan':'TWN','hong kong':'HKG',
  'macao':'MAC','camboya':'KHM','laos':'LAO','myanmar':'MMR','birmania':'MMR',
  'mongolia':'MNG','kazajistan':'KAZ','uzbekistan':'UZB','turkmenistan':'TKM',
  'kirguistan':'KGZ','tayikistan':'TJK','afganistan':'AFG','pakistan':'PAK',
  'bangladesh':'BGD','banglades':'BGD','sri lanka':'LKA','nepal':'NPL',
  'butan':'BTN','maldivas':'MDV',
  'marruecos':'MAR','argelia':'DZA','tunez':'TUN','libia':'LBY','egipto':'EGY',
  'sudafrica':'ZAF','nigeria':'NGA','kenia':'KEN','etiopia':'ETH',
  'ghana':'GHA','senegal':'SEN','costa de marfil':'CIV','camerun':'CMR',
  'tanzania':'TZA','uganda':'UGA',
  'israel':'ISR','jordania':'JOR','libano':'LBN','siria':'SYR','irak':'IRQ',
  'iran':'IRN','arabia saudita':'SAU','emiratos arabes unidos':'ARE',
  'catar':'QAT','kuwait':'KWT','baréin':'BHR','bahrein':'BHR','oman':'OMN',
  'yemen':'YEM','georgia':'GEO','armenia':'ARM','azerbaiyan':'AZE',
};

// ---------------- Reserva: traducció al nom en anglès ----------------
// Només s'usa si el mapa concret no porta cap codi ISO a les seves dades.
const PAIS_ES_EN = {
  'espana':'spain', 'francia':'france', 'alemania':'germany', 'italia':'italy',
  'reino unido':'united kingdom', 'paises bajos':'netherlands', 'holanda':'netherlands',
  'belgica':'belgium', 'suiza':'switzerland', 'austria':'austria', 'portugal':'portugal',
  'estados unidos':'united states of america', 'eeuu':'united states of america',
  'canada':'canada', 'mexico':'mexico', 'argentina':'argentina', 'brasil':'brazil',
  'chile':'chile', 'colombia':'colombia', 'peru':'peru', 'uruguay':'uruguay',
  'japon':'japan', 'china':'china', 'india':'india', 'australia':'australia',
  'nueva zelanda':'new zealand', 'rusia':'russia', 'polonia':'poland',
  'suecia':'sweden', 'noruega':'norway', 'dinamarca':'denmark', 'finlandia':'finland',
  'irlanda':'ireland', 'grecia':'greece', 'turquia':'turkey', 'marruecos':'morocco',
  'israel':'israel', 'sudafrica':'south africa', 'corea del sur':'south korea',
  'republica checa':'czech republic', 'hungria':'hungary', 'rumania':'romania',
  'lituania':'lithuania', 'letonia':'latvia', 'estonia':'estonia', 'ucrania':'ukraine',
  'singapur':'singapore', 'vietnam':'vietnam', 'tailandia':'thailand',
  'eslovenia':'slovenia', 'eslovaquia':'slovakia', 'croacia':'croatia',
  'serbia':'serbia', 'bulgaria':'bulgaria', 'islandia':'iceland', 'luxemburgo':'luxembourg',
  'malta':'malta', 'chipre':'cyprus', 'ecuador':'ecuador', 'venezuela':'venezuela',
  'costa rica':'costa rica', 'panama':'panama', 'cuba':'cuba', 'filipinas':'philippines',
  'indonesia':'indonesia', 'egipto':'egypt', 'tunez':'tunisia', 'argelia':'algeria',
  'bosnia':'bosnia and herzegovina', 'macedonia del norte':'macedonia',
  'malasia':'malaysia', 'emiratos arabes unidos':'united arab emirates',
  'arabia saudita':'saudi arabia', 'catar':'qatar', 'kuwait':'kuwait',
  'georgia':'georgia', 'armenia':'armenia', 'azerbaiyan':'azerbaijan',
  'kazajistan':'kazakhstan', 'taiwan':'taiwan', 'hong kong':'hong kong s.a.r.',
  'sri lanka':'sri lanka', 'nepal':'nepal', 'banglades':'bangladesh',
  'nigeria':'nigeria', 'kenia':'kenya', 'senegal':'senegal',
  'jordania':'jordan', 'libano':'lebanon', 'iran':'iran', 'irak':'iraq',
};

// Algunes províncies tenen diverses formes de nom en circulació (castellà
// clàssic, forma cooficial basca, forma catalana...). En lloc d'endevinar
// quina fa servir cada mapa concret, registrem TOTES com a àlies vàlids.
const PROVINCIA_VARIANTS = {
  'alava': ['araba/alava', 'araba', 'alaba'],
  'guipuzcoa': ['gipuzkoa', 'guipuscoa'],
  'vizcaya': ['bizkaia', 'biscaia'],
  'baleares': ['illes balears'], 'islas baleares': ['illes balears'],
  'coruna': ['a coruna'], 'gerona': ['girona'], 'lerida': ['lleida'],
  'orense': ['ourense'],
  'alicante': ['alacant', 'alacantalicante', 'alicantealacant'],
  'castellon': ['castello', 'castellocastello', 'castellocastellon'],
};

// Alguns països minúsculs (Singapur, Malta, Maldives...) no existeixen
// com a polígon al mapa mundial de baixa resolució que fem servir — hi
// són massa petits per dibuixar-los, no és un problema de nom. Per
// aquests, en lloc de pintar una regió els mostrem com un punt/cercle,
// igual que la resta però amb una icona rodona al seu centre geogràfic.
const PAISOS_PETITS_CENTRE = {
  'SGP': [1.3521, 103.8198],   // Singapur
  'MDV': [3.2028, 73.2207],    // Maldives
  'MLT': [35.9375, 14.3754],   // Malta
  'MCO': [43.7384, 7.4246],    // Mònaco
  'AND': [42.5462, 1.6016],    // Andorra
  'SMR': [43.9424, 12.4578],   // San Marino
  'VAT': [41.9029, 12.4534],   // Vaticà
  'LIE': [47.1660, 9.5554],    // Liechtenstein
  'BHR': [26.0667, 50.5577],   // Bahrain
  'HKG': [22.3193, 114.1694],  // Hong Kong
  'MAC': [22.1987, 113.5439],  // Macao
  'BRB': [13.1939, -59.5432],  // Barbados
};

// ---------------- Utilitats ----------------
const nf = n => Number(n).toLocaleString('ca');

function norm(s) {
  if (!s) return '';
  let t = String(s).toLowerCase().trim();
  t = t.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
  t = t.replace(/['`´’]/g, ' ');
  t = t.replace(/^(el |la |l |les |els |les |the )/, '');
  t = t.replace(/[^a-z0-9 ]/g, '').replace(/\s+/g, ' ').trim();
  return t;
}

/** Distància de Levenshtein per a coincidències aproximades */
function levenshtein(a, b) {
  if (a === b) return 0;
  const m = a.length, n = b.length;
  if (!m) return n;
  if (!n) return m;
  let prev = Array.from({length: n + 1}, (_, i) => i);
  for (let i = 1; i <= m; i++) {
    const curr = [i];
    for (let j = 1; j <= n; j++) {
      curr[j] = a[i-1] === b[j-1]
        ? prev[j-1]
        : 1 + Math.min(prev[j-1], prev[j], curr[j-1]);
    }
    prev = curr;
  }
  return prev[n];
}

function millorCoincidencia(objectiu, candidats) {
  if (!objectiu) return -1;
  let millorIdx = -1, millorPunts = Infinity;
  candidats.forEach((c, i) => {
    if (!c.disponible) return;
    let punts;
    if (c.nom === objectiu) punts = 0;
    else if (c.nom.includes(objectiu) || objectiu.includes(c.nom)) punts = 0.1;
    else punts = levenshtein(objectiu, c.nom) / Math.max(objectiu.length, c.nom.length, 1);
    if (punts < millorPunts) { millorPunts = punts; millorIdx = i; }
  });
  return millorPunts <= 0.32 ? millorIdx : -1;
}

function nomFeature(props) {
  const claus = ['name', 'NAME', 'nom_comar', 'NOMCOMAR', 'NOMCOMARCA', 'comarca', 'Comarca',
                 'nombre', 'provincia', 'Provincia', 'NAME_1', 'NAME_2', 'nom', 'Nom', 'NOM',
                 'admin', 'ADMIN', 'NOMBRE', 'name_ca', 'name_es', 'label', 'LABEL'];
  for (const k of claus) {
    if (props && props[k]) return props[k];
  }
  for (const k in props) {
    if (typeof props[k] === 'string' && props[k].length > 1 && props[k].length < 60) return props[k];
  }
  return '';
}

async function json(url) { return (await fetch(url)).json(); }

async function carregarGeo(vista) {
  for (const url of FONTS[vista]) {
    try {
      const r = await fetch(url);
      if (!r.ok) continue;
      const g = await r.json();
      if (g && g.features && g.features.length) return g;
    } catch (e) { /* provem la següent font */ }
  }
  return null;
}

// ---------------- Filtres ----------------
function seleccionats(id) { return Array.from(document.getElementById(id).selectedOptions).map(o => o.value); }

function construirQuery() {
  const p = new URLSearchParams();
  seleccionats('filtreProductes').forEach(v => p.append('grups[]', v));
  seleccionats('filtreAnys').forEach(v => p.append('anys[]', v));
  seleccionats('filtreMesos').forEach(v => p.append('mesos[]', v));
  return p;
}

async function carregarFiltres() {
  const productes = await json('api_dades.php?accio=productes_disponibles');
  const selProd = document.getElementById('filtreProductes');
  productes.forEach(p => selProd.add(new Option(p.grup, p.grup)));

  const periodes = await json('api_dades.php?accio=periodes_disponibles');
  const anys = [...new Set(periodes.map(p => p.any_))].sort((a,b) => b-a);
  const mesos = [...new Set(periodes.map(p => p.mes))].sort((a,b) => a-b);
  const selAny = document.getElementById('filtreAnys');
  anys.forEach(a => selAny.add(new Option(a, a)));
  const selMes = document.getElementById('filtreMesos');
  mesos.forEach(m => selMes.add(new Option(NOMS_MESOS[m-1], m)));
}

// ---------------- Mapa ----------------
let mapa = null;
let capa = null;
let capaPunts = null;
let llegenda = null;
let vistaActual = 'comarca';

const ACCIONS = {
  comarca:   { accio: 'usuaris_per_comarca',   centre: [41.82, 1.72], zoom: 7,   titol: 'comarques' },
  provincia: { accio: 'usuaris_per_provincia', centre: [40.2, -3.5],  zoom: 5,   titol: 'províncies' },
  pais:      { accio: 'usuaris_per_pais',      centre: [25, 10],      zoom: 2,   titol: 'països' },
};

function inicialitzarMapa() {
  mapa = L.map('mapa', { scrollWheelZoom: true, attributionControl: false });
  L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
    maxZoom: 12, opacity: .55,
  }).addTo(mapa);
}

function color(valor, max) {
  if (!valor) return '#e4e1dd';
  const t = Math.sqrt(valor / max);
  if (t > .85) return '#7b241c';
  if (t > .65) return '#a5281f';
  if (t > .45) return '#c0392b';
  if (t > .28) return '#d9604f';
  if (t > .14) return '#e79180';
  return '#f5c9c0';
}

function dibuixarLlegenda(max) {
  if (llegenda) mapa.removeControl(llegenda);
  llegenda = L.control({ position: 'bottomright' });
  llegenda.onAdd = () => {
    const div = L.DomUtil.create('div', 'llegenda');
    const trams = [0, .14, .28, .45, .65, .85];
    let html = '<b>Visitants (pax)</b><br>';
    for (let i = trams.length - 1; i >= 0; i--) {
      const desde = Math.round(Math.pow(trams[i], 2) * max);
      const fins = i < trams.length - 1 ? Math.round(Math.pow(trams[i+1], 2) * max) : null;
      html += `<i style="background:${color(Math.max(desde,1), max)}"></i> ${nf(desde)}${fins ? '–' + nf(fins) : '+'}<br>`;
    }
    html += `<i style="background:#e4e1dd"></i> <span class="sense-dada">sense visitants</span>`;
    div.innerHTML = html;
    return div;
  };
  llegenda.addTo(mapa);
}

async function dibuixarMapa(vista) {
  vistaActual = vista;
  const cfg = ACCIONS[vista];
  const estat = document.getElementById('estat');
  const avis = document.getElementById('avisCarrega');
  avis.style.display = 'none';
  estat.textContent = 'Carregant…';

  const q = construirQuery();
  const files = await json('api_dades.php?accio=' + cfg.accio + '&' + q);

  const dades = {};
  const etiquetaOriginal = {};
  const alies = {};

  files.forEach(f => {
    if (!f.etiqueta) return;
    const clauBase = norm(f.etiqueta);
    let canonica, clauNomTraduit;

    if (vista === 'pais') {
      const iso = PAIS_ES_ISO3[clauBase];
      clauNomTraduit = norm(PAIS_ES_EN[clauBase] || f.etiqueta);
      canonica = iso ? ('iso:' + iso.toLowerCase()) : ('name:' + clauNomTraduit);
      if (iso) alies['iso:' + iso.toLowerCase()] = canonica;
      alies['name:' + clauNomTraduit] = canonica;
    } else if (vista === 'provincia') {
      canonica = 'name:' + clauBase;
      const variants = PROVINCIA_VARIANTS[clauBase] || [];
      variants.forEach(v => { alies['name:' + norm(v)] = canonica; });
    } else {
      canonica = 'name:' + clauBase;
    }
    alies['name:' + clauBase] = canonica;

    dades[canonica] = (dades[canonica] || 0) + Number(f.total);
    etiquetaOriginal[canonica] = f.etiqueta;
  });
  const max = Math.max(...Object.values(dades), 1);

  function extreureIso(f) {
    const candidats = [f.id, f.properties?.iso_a3, f.properties?.ISO_A3,
                        f.properties?.ISO3, f.properties?.adm0_a3, f.properties?.brk_a3];
    for (const c of candidats) {
      if (typeof c === 'string' && c.length === 3 && c !== '-99') return c.toUpperCase();
    }
    return null;
  }

  function clauDeFeature(f) {
    if (vista === 'pais') {
      const iso = extreureIso(f);
      if (iso && alies['iso:' + iso.toLowerCase()]) return alies['iso:' + iso.toLowerCase()];
    }
    const nomFeat = norm(nomFeature(f.properties));
    return alies['name:' + nomFeat] || null;
  }

  const geo = await carregarGeo(vista);
  if (!geo) {
    estat.textContent = '';
    avis.style.display = 'block';
    avis.innerHTML = `⚠ No s'han pogut carregar les geometries del mapa de ${cfg.titol}.
      Comprova la connexió a internet, o
      <a href="${DESCARREGA_MANUAL[vista]}" target="_blank" rel="noopener">descarrega aquí el fitxer</a>
      (botó "Download raw file") i posa'l a <code>public/geo/</code> amb el nom indicat al README d'aquella carpeta.`;
    if (capa) { mapa.removeLayer(capa); capa = null; }
    return;
  }

  const assignacio = new Map();
  const clausTrobades = new Set();

  geo.features.forEach(f => {
    const clau = clauDeFeature(f);
    if (clau) { assignacio.set(f, clau); clausTrobades.add(clau); }
  });

  const clausPendents = Object.keys(dades).filter(k => !clausTrobades.has(k));
  if (clausPendents.length) {
    const candidatsFuzzy = geo.features
      .filter(f => !assignacio.has(f))
      .map(f => ({ f, nom: norm(nomFeature(f.properties)), disponible: true }));

    clausPendents.forEach(clau => {
      const objectiu = norm(etiquetaOriginal[clau]);
      const idx = millorCoincidencia(objectiu, candidatsFuzzy);
      if (idx >= 0) {
        const cand = candidatsFuzzy[idx];
        assignacio.set(cand.f, clau);
        clausTrobades.add(clau);
        cand.disponible = false;
      }
    });
  }

  if (capa) mapa.removeLayer(capa);

  capa = L.geoJSON(geo, {
    style: (f) => {
      const clau = assignacio.get(f);
      const valor = clau ? (dades[clau] || 0) : 0;
      return {
        fillColor: color(valor, max),
        weight: 1, color: '#fff', fillOpacity: valor ? .85 : .5,
      };
    },
    onEachFeature: (f, layer) => {
      const nomOriginal = nomFeature(f.properties);
      const clau = assignacio.get(f);
      const valor = clau ? (dades[clau] || 0) : 0;
      const pct = valor ? ((100 * valor / Object.values(dades).reduce((a,b)=>a+b,0)).toFixed(1) + '%') : '—';
      layer.bindTooltip(
        `<b>${nomOriginal}</b><br>${valor ? nf(valor) + ' pax · ' + pct : '<span style="color:#999">sense visitants</span>'}`,
        { sticky: true }
      );
      layer.on({
        mouseover: e => e.target.setStyle({ weight: 2.5, color: '#2c3e50' }),
        mouseout: e => capa.resetStyle(e.target),
      });
    },
  }).addTo(mapa);

  mapa.setView(cfg.centre, cfg.zoom);
  dibuixarLlegenda(max);

  // Països minúsculs sense polígon al mapa: es dibuixen com a punt
  if (capaPunts) { mapa.removeLayer(capaPunts); capaPunts = null; }
  if (vista === 'pais') {
    const marques = [];
    Object.keys(dades).forEach(clau => {
      if (clausTrobades.has(clau)) return;
      if (!clau.startsWith('iso:')) return;
      const iso3 = clau.slice(4).toUpperCase();
      const centre = PAISOS_PETITS_CENTRE[iso3];
      if (!centre) return;
      clausTrobades.add(clau);
      const valor = dades[clau];
      marques.push(
        L.circleMarker(centre, {
          radius: 7 + 6 * Math.sqrt(valor / max),
          fillColor: color(valor, max), color: '#fff', weight: 1.5, fillOpacity: .9,
        }).bindTooltip(
          `<b>${etiquetaOriginal[clau]}</b><br>${nf(valor)} pax · <span style="color:#999">(país massa petit per al mapa; mostrat com a punt)</span>`,
          { sticky: true }
        )
      );
    });
    if (marques.length) capaPunts = L.layerGroup(marques).addTo(mapa);
  }

  const totalPax = Object.values(dades).reduce((a,b)=>a+b,0);
  const clausSenseUbicar = Object.keys(dades).filter(k => !clausTrobades.has(k));
  const noms = clausSenseUbicar.map(k => `${etiquetaOriginal[k]} (${nf(dades[k])} pax)`);

  estat.innerHTML = `${nf(totalPax)} pax · ${clausTrobades.size} ${cfg.titol} al mapa` +
    (noms.length > 0 ? ` · <span class="text-warning">${noms.length} sense ubicar</span>` : '');

  const contDetall = document.getElementById('detallSenseUbicar');
  if (noms.length > 0) {
    contDetall.innerHTML = `<details><summary class="text-muted" style="cursor:pointer">
      ⓘ Veure quins ${noms.length} noms no s'han pogut situar al mapa</summary>
      <ul class="mb-0 mt-1">${noms.map(n => `<li>${n}</li>`).join('')}</ul></details>`;
  } else {
    contDetall.innerHTML = '';
  }
}

document.querySelectorAll('#pestanyes .nav-link').forEach(btn => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('#pestanyes .nav-link').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    dibuixarMapa(btn.dataset.vista);
  });
});

document.getElementById('btnAplicar').addEventListener('click', () => dibuixarMapa(vistaActual));
document.getElementById('btnNetejar').addEventListener('click', () => {
  ['filtreProductes','filtreAnys','filtreMesos'].forEach(id =>
    Array.from(document.getElementById(id).options).forEach(o => o.selected = false));
  dibuixarMapa(vistaActual);
});

(async () => {
  inicialitzarMapa();
  await carregarFiltres();
  await dibuixarMapa('comarca');
})();
</script>
</body>
</html>
