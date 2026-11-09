# Espai Cràter · Control de procedències

Aplicació web (PHP + MySQL + Bootstrap) per substituir el seguiment en
Excel de la procedència de visitants del museu, amb dades importades de
Clorian (Taquilla, Online, CGR).

## Requisits

- XAMPP (Windows) amb PHP 8.1+ i MySQL/MariaDB
- Extensions PHP: `pdo_mysql`, `zip`, `simplexml`, `openssl`, `mbstring` i,
  per al login amb Active Directory, `ldap` (a XAMPP cal descomentar
  `extension=ldap` a `C:\xampp\php\php.ini` i reiniciar Apache). Les
  primeres van incloses per
  defecte a XAMPP)
- **No cal Composer** — el lector d'Excel (`SimpleXlsxReader`) és natiu,
  pensat per evitar els problemes de SSL del proxy de l'ajuntament.

## Instal·lació (primer cop)

1. Copia la carpeta a `C:\xampp\htdocs\crater_app`.
2. Crea la base de dades i carrega l'esquema:
   ```
   mysql -u root --default-character-set=utf8mb4 -e "CREATE DATABASE crater_procedencies CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
   mysql -u root --default-character-set=utf8mb4 crater_procedencies < database/schema.sql
   mysql -u root --default-character-set=utf8mb4 crater_procedencies < database/seed_provincies.sql
   mysql -u root --default-character-set=utf8mb4 crater_procedencies < database/seed_comarques.sql
   mysql -u root --default-character-set=utf8mb4 crater_procedencies < database/seed_cp_municipis.sql
   mysql -u root --default-character-set=utf8mb4 crater_procedencies < database/seed_grups_productes.sql
   ```
3. Revisa `config/config.php` si el teu usuari/contrasenya de MySQL no és
   el `root` sense contrasenya per defecte de XAMPP. Copia
   `config/local.example.php` com a `config/local.php` i hi poses una
   `clau_xifratge` pròpia (la fa servir la pàgina Configuració per xifrar
   la contrasenya del compte de consulta de l'AD).
4. Obre `http://crater.localhost/crear_primer_admin.php` (VirtualHost, vegeu sota) al navegador i
   crea el primer usuari administrador. **Un cop creat, esborra aquest
   fitxer o restringeix-hi l'accés** (deixar de banda de seguretat).
5. Ja pots iniciar sessió a `http://crater.localhost/login.php`.

### VirtualHost a XAMPP

`C:\xampp\apache\conf\extra\httpd-vhosts.conf`:
```
<VirtualHost *:80>
    ServerName crater.localhost
    DocumentRoot "C:/xampp/htdocs/crater_app/public"
    <Directory "C:/xampp/htdocs/crater_app/public">
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```
(`*.localhost` ja resol a 127.0.0.1 als navegadors moderns; si no, afegeix-lo a `hosts`.)

### Active Directory

El login accepta comptes **locals** i comptes **del domini** (columna
`usuaris.origen`). La integració ve de la plantilla d'apps corporatives
(`lib/ldap.php`, `lib/auth.php`):

1. Aplica la migració: `mysql -u root --default-character-set=utf8mb4 crater_procedencies < database/migracio_ad.sql`
   (idempotent; els usuaris actuals queden com a locals).
2. Entra com a administrador i ves a **Configuració** (menú d'usuari, a dalt
   a la dreta): servidor, seguretat, domini UPN, DN base, compte de consulta
   i, opcionalment, grup d'administradors. Fes **Prova la connexió**.
3. A **Gestió d'usuaris → Cerca a l'Active Directory** dona d'alta els
   comptes. Entren amb la contrasenya del domini; no tenen contrasenya local.

Atenció: l'AD configurat en local és el de producció. No facis logins
fallits repetits amb un usuari real (pot bloquejar-ne el compte).

### Actualitzant una instal·lació existent (afegir el login)

Si ja tenies l'aplicació instal·lada sense sistema de login, només cal
aplicar aquesta migració:
```
mysql -u root --default-character-set=utf8mb4 crater_procedencies < database/migracio_login.sql
```
I després fer el pas 4 de dalt (`crear_primer_admin.php`) per crear el
primer compte.

## Sistema de login i rols

- Pensat per a un ús **intern, dins la intranet de l'ajuntament** — no hi
  ha cap connexió a servidor de correu, ni recuperació de contrasenya per
  email.
- Dos rols: `admin` i `usuari`. La majoria de l'aplicació no en distingeix
  (excepte "Gestió d'usuaris" i l'esborrat total de dades, exclusius
  d'`admin`); es deixen creats per si en el futur cal diferenciar més
  permisos.
- **Bloqueig temporal per intents fallits**: després de 5 intents de
  login fallits amb el mateix usuari en 15 minuts, es bloqueja
  temporalment (el missatge indica quants minuts falten). Es registren
  tots els intents (èxit i fallida) a la taula `intents_login`.
- **Caducitat de sessió per inactivitat**: al cap d'1 hora sense fer cap
  acció, la sessió es tanca sola i cal tornar a iniciar sessió (amb un
  avís explicant que ha estat per inactivitat, no per cap error).
- Si algú oblida la contrasenya, un administrador la reinicia
  manualment des de `usuaris.php` ("Gestió d'usuaris" al menú superior)
  i l'hi comunica en persona — no hi ha cap flux automàtic per correu.
- El primer compte (`crear_primer_admin.php`) només es pot crear un cop:
  la pàgina es desactiva sola en quant hi ha almenys un usuari a la
  taula `usuaris`. Tot i així, es recomana esborrar-la o protegir-la un
  cop feta servir.

## Migracions disponibles

Si vinguessis d'una instal·lació molt antiga, aquestes són totes les
migracions incrementals disponibles (per a instal·lacions noves, ja
n'hi ha prou amb `schema.sql`):

- `migracio_errors.sql` — taula de correccions persistents per CP
- `migracio_manual.sql` — injecció d'entrades manuals
- `migracio_rgpd.sql` — eliminació de dades personals innecessàries
- `migracio_cp_llarg.sql` — amplia el camp de codi postal
- `migracio_revisio_massiva.sql` — taula d'errors descartats
- `migracio_login.sql` — sistema d'usuaris i rols
- `migracio_neteja.sql` — neteja de dades (parcial/total) + auditoria
- `migracio_login_intents.sql` — bloqueig per intents de login fallits
- `migracio_registre_activitat.sql` — registre general d'activitat/auditoria
- `migracio_ad.sql` — login amb Active Directory (columnes nom/correu/origen,
  taula `configuracio`)
- `migracio_completa.sql` — combina TOTES les migracions anteriors en
  un sol fitxer (útil per posar al dia una instal·lació molt antiga
  d'un cop; per a instal·lacions noves, `schema.sql` ja ho porta tot)

## Funcionalitats principals

- **Importació**: fitxers normals de Clorian (un canal + un mes) o un
  fitxer general consolidat amb tot l'històric de cop. Detecta
  duplicats (mateix canal + període) i permet triar substituir, afegir
  o cancel·lar.
- **Dashboard**: KPIs, gràfic de canals, evolució mensual, 4 rànquings
  geogràfics (país, país sense Espanya, província, comarca) amb detall
  complet en modal, filtres per producte/any/mes, comparativa entre
  dos períodes (mes concret vs mes concret, o any complet vs any
  complet, útil per a informes anuals).
- **Mapes**: Catalunya (comarques), Espanya (províncies) i Món
  (països), amb coincidència per codi ISO, variants de nom i
  aproximació per distància de text, més punts per a països massa
  petits per al mapa mundial de baixa resolució.
- **Gestió d'errors**: detecta país buit, CP buit, CP mal format,
  província/comarca no assignades, i possibles duplicats de grups
  escolars (canal CGR). Suggereix correccions amb un clic quan és
  possible.
- **CP mal format** — botó "Netejar CPs automàticament": elimina
  caràcters no numèrics (punts, símbols...) de cada codi postal; si el
  resultat té exactament 5 dígits, s'aplica directament. Si en queden 6
  (típic d'un dígit de més per error de picatge), prova de suprimir
  cada posició una a una i comprova si el resultat és un prefix de
  província real — si només una de les supressions dona un prefix
  vàlid, l'aplica sola. La resta (massa ambigus) es deixen intactes per
  revisar-los a mà.
- **Província no assignada** — a més del suggeriment habitual, quan el
  prefix del CP no existeix a cap província espanyola però sí que
  coincideix amb un **departament francès real** (per exemple 66 =
  Pyrénées-Orientales, 75 = París), l'aplicació ho detecta i suggereix
  que probablement el visitant és francès i es va marcar "Espanya" per
  error. Hi ha un botó de revisió massiva "🇫🇷 Reclassificar CPs
  francesos com a estrangers" que ho corregeix de cop per a tots els
  casos que coincideixin amb aquest patró — molt habitual en una zona
  fronterera com la Garrotxa.
- **Revisió massiva**: aplica suggeriments de província/comarca a tots
  els casos pendents alhora, neteja CPs en bloc, reclassifica CPs
  francesos, i permet descartar (donar per bons) casos que no calgui
  corregir.
- **Neteja de dades**: permet esborrar entrades de manera parcial (per
  any, mes, canal i/o producte — les entrades manuals queden protegides
  per defecte), accessible a qualsevol usuari amb sessió iniciada. Hi ha
  també un **esborrat total** (entrades + historial d'importacions
  sencer), exclusiu d'administradors i pensat només per a incidències
  greus on cal tornar a bolcar tot des de zero. Totes dues vies exigeixen
  una previsualització prèvia i escriure un text de confirmació exacte
  abans d'executar-se, i queden registrades a un historial (visible a la
  mateixa pàgina) amb qui ho ha fet, quan, i quantes files/pax ha afectat.
- **Informes d'activitat**: un sol lloc amb tot l'historial de canvis
  (no només els esborrats) — correccions de CP, entrades manuals
  afegides/eliminades, suggeriments massius aplicats, gestió de
  productes i d'usuaris, i les neteges de dades — filtrable per tipus,
  amb qui ho ha fet i quan. Accessible a tothom amb sessió iniciada, per
  transparència de l'equip.
- **Gestió de productes**: assigna cada producte de Clorian a un grup,
  fusiona grups, i permet preparar mapatges "previstos" per a productes
  que encara no han aparegut en cap importació.
- **Historial d'importacions**: llista totes les importacions fetes,
  amb opció de desfer-ne qualsevol (esborra només les entrades
  associades a aquella importació concreta).
- **Exportació**: Excel i PDF amb totes les taules (incloent-hi
  l'evolució mensual) i una fila de TOTAL a cada taula.
- **RGPD**: no es guarda mai el correu electrònic; el nom de client
  només es conserva per al canal CGR (habitualment noms d'entitats/
  escoles, no de persones físiques), i únicament per detectar
  duplicats de grups escolars.

## Estructura de carpetes

```
crater-app/
├── config/config.php          Connexió a la base de dades (gitignored)
├── config/app.php             Configuració de l'app: menú, rols, CSP (versionat)
├── config/local.php           Clau de xifratge de l'entorn (gitignored)
├── lib/                       Mòduls de la plantilla (AD, sessió, capçalera/peu, config)
├── database/                  Esquema i migracions SQL
├── src/                       Classes PHP (importadors, lector d'Excel, Auth)
├── storage/tmp/                Fitxers temporals durant la importació (no accessible via web)
└── public/                    Totes les pàgines i endpoints (arrel del servidor web)
    └── geo/                   GeoJSON dels mapes (opcional, veure geo/README.md)
```
