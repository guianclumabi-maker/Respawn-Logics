@echo off
setlocal enabledelayedexpansion

echo === PHP SYNTAX CHECK ===
for /R "C:\xampp\htdocs\respawn-logics" %%f in (*.php) do (
    echo %%f | findstr /i "node_modules vendor .git" >nul 2>&1
    if errorlevel 1 (
        C:\xampp\php\php.exe -l "%%f" 2>&1 | findstr /v "No syntax errors" >nul 2>&1
        if not errorlevel 1 (
            echo SYNTAX ERROR: %%f
            C:\xampp\php\php.exe -l "%%f" 2>&1
        )
    )
)
echo === DONE ===
