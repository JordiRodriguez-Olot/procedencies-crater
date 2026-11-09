# Desplegament a producció (Plesk) — integració amb la plantilla

Canvis d'aquesta versió: login amb Active Directory + comptes locals, capçalera/menú/peu
corporatius amb el logotip de l'Espai Cràter, pàgina de Configuració i migracions SQL.

**Ordre important: primer la base de dades, després el codi.** El codi nou llegeix columnes
(`origen`, `nom`, `correu`) que han d'existir abans de fer push. Les migracions són
compatibles amb el codi antic i es poden repetir sense efectes.

## 0. Abans de començar
- Còpia de seguretat de la BD de producció (phpMyAdmin → Exporta).
- Comprova que el docroot del domini a Plesk és la carpeta `public/`.

## 1. Base de dades (phpMyAdmin de Plesk, pestanya SQL o Importa)
1. `database/migracio_neteja.sql` — crea la taula `registre_neteja` (a producció no hi era;
   la fan servir Neteja de dades i Informes).
2. `database/migracio_ad.sql` — afegeix `nom`, `correu`, `origen` a `usuaris`, deixa
   `contrasenya_hash` com a NULL-able i crea la taula `configuracio`.
3. Comprova: `SELECT id, nom_usuari, rol, origen FROM usuaris;` → `admin` i `sau.crater`, origen `local`.

## 2. Codi
```
git add -A
git commit
git push origin main      # Plesk desplega en rebre el push
```
`config/config.php` i `config/local.php` són a `.gitignore`: el desplegament no els toca.

## 3. Després del desplegament
- Tothom haurà de tornar a iniciar sessió (la cookie de sessió ara és `CRATER_SESSID`).
- Prova: login amb `admin`, Procedències, Mapes, Informes, Neteja de dades, Gestió d'usuaris,
  una importació petita i Sortir.
- L'AD queda **desactivat** fins que s'activi a Configuració: no cal res més per funcionar com abans.

## 4. Quan es vulgui activar l'AD (opcional, més endavant)
1. Plesk → PHP → extensions: que hi sigui `ldap` (i `openssl`).
2. Crear `config/local.php` al servidor (WinSCP / gestor de fitxers de Plesk) a partir de
   `config/local.example.php`, amb una `clau_xifratge` pròpia:
   `php -r "echo base64_encode(random_bytes(32));"`. Mai la de local.
3. Configuració → Active Directory → desar → Prova la connexió → donar d'alta usuaris des de
   Gestió d'usuaris → Cerca a l'Active Directory.

## Tornar enrere
`git revert <commit>` i push. Els canvis de BD són additius i el codi antic els ignora,
així que no cal desfer-los.
