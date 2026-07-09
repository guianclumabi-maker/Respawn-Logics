@echo off
REM ============================================================
REM  Respawn Logics - manual database backup (free-trial friendly)
REM  Dumps the live Railway MySQL database to a timestamped .sql
REM  file on your PC using XAMPP's mysqldump. Costs nothing.
REM
REM  DO NOT COMMIT this file to git after filling in real values.
REM ============================================================

REM --- 1. Fill these in from Railway -> MySQL service -> Connect tab -> "Public Network" ---
REM     (Use the PUBLIC host/port, not the internal mysql.railway.internal one.)
set DB_HOST=reseau.proxy.rlwy.net
set DB_PORT=19932
set DB_USER=root
set DB_NAME=railway

REM --- 2. Build a timestamped file inside a backups\ folder (created if missing) ---
if not exist "%~dp0backups" mkdir "%~dp0backups"
set TS=%date:~-4%-%date:~4,2%-%date:~7,2%_%time:~0,2%%time:~3,2%
set TS=%TS: =0%
set OUTFILE=%~dp0backups\respawn_%TS%.sql

echo.
echo Backing up "%DB_NAME%" from %DB_HOST%:%DB_PORT% ...
echo (You will be prompted for the database password - it's DB_PASS in Railway Variables.)
echo.

"C:\xampp\mysql\bin\mysqldump.exe" -h %DB_HOST% -P %DB_PORT% -u %DB_USER% -p --single-transaction --routines --triggers --databases %DB_NAME% > "%OUTFILE%"

if %errorlevel%==0 (
  echo.
  echo SUCCESS - backup saved to: %OUTFILE%
  echo Keep a copy somewhere safe ^(cloud drive / external disk^).
) else (
  echo.
  echo BACKUP FAILED - check your host/port/password and that mysqldump.exe exists at C:\xampp\mysql\bin\
)
echo.
pause
