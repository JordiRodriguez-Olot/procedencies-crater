@echo off
setlocal EnableDelayedExpansion
chcp 65001 >nul
REM ============================================================
REM Importa el dump de producció al MySQL/MariaDB de XAMPP (prova local).
REM  - Dump esperat: database\local\crater_procedencies_prod.sql (gitignored)
REM  - Recrea la base de dades crater_procedencies (ESBORRA la que hi hagi)
REM  - Aplica migracio_neteja.sql i migracio_ad.sql
REM Ús: bin\importa_local.bat           (demana confirmació)
REM     bin\importa_local.bat /si        (sense preguntar)
REM Variable opcional: MYSQL_BIN (per defecte C:\xampp\mysql\bin)
REM ============================================================

if "%MYSQL_BIN%"=="" set "MYSQL_BIN=C:\xampp\mysql\bin"
set "ARREL=%~dp0.."
set "DUMP=%ARREL%\database\local\crater_procedencies_prod.sql"
set "BD=crater_procedencies"
set "MYSQL=%MYSQL_BIN%\mysql.exe"
set "OPC=-u root --default-character-set=utf8mb4"

if not exist "%MYSQL%" ( echo [ERROR] No trobo %MYSQL% & exit /b 1 )
if not exist "%DUMP%" ( echo [ERROR] No trobo el dump: %DUMP% & exit /b 1 )

"%MYSQL_BIN%\mysqladmin.exe" -u root ping >nul 2>&1
if errorlevel 1 ( echo [ERROR] MySQL no respon. Engega'l des del tauler de XAMPP ^(i para el de Laragon si hi és^). & exit /b 1 )

if /i not "%~1"=="/si" (
  echo Aixo ESBORRARA la base de dades "%BD%" local i la recrearà amb el dump de producció.
  set /p RESP=Continuar? [s/N]: 
  if /i not "!RESP!"=="s" ( echo Cancel·lat. & exit /b 0 )
)

REM Rutes amb "/" per a la comanda source de mysql
set "DUMP_S=%DUMP:\=/%"
set "NETEJA_S=%ARREL:\=/%/database/migracio_neteja.sql"
set "AD_S=%ARREL:\=/%/database/migracio_ad.sql"

echo [1/4] Recreant %BD% ...
"%MYSQL%" %OPC% -e "DROP DATABASE IF EXISTS %BD%; CREATE DATABASE %BD% CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" || goto :error

echo [2/4] Important el dump ^(pot trigar uns segons^) ...
"%MYSQL%" %OPC% %BD% -e "source %DUMP_S%" || goto :error

echo [3/4] Migracio neteja ^(taula registre_neteja^) ...
"%MYSQL%" %OPC% %BD% -e "source %NETEJA_S%" || goto :error

echo [4/4] Migracio AD ^(usuaris: nom/correu/origen, taula configuracio^) ...
"%MYSQL%" %OPC% %BD% -e "source %AD_S%" || goto :error

echo.
echo === Verificacio ===
"%MYSQL%" %OPC% %BD% -e "SELECT 'usuaris' t, COUNT(*) n FROM usuaris UNION ALL SELECT 'entrades', COUNT(*) FROM entrades UNION ALL SELECT 'importacions', COUNT(*) FROM importacions UNION ALL SELECT 'registre_neteja', COUNT(*) FROM registre_neteja UNION ALL SELECT 'configuracio', COUNT(*) FROM configuracio; SELECT id, nom_usuari, rol, origen, actiu FROM usuaris;"
echo.
echo Fet. Obre http://localhost/crater_app/public/login.php
exit /b 0

:error
echo.
echo [ERROR] Ha fallat l'ultim pas ^(codi %errorlevel%^). Revisa el missatge de dalt.
exit /b 1
