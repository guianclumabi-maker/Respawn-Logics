@echo off
REM One-shot instrumented test run for the 401 investigation.
REM Runs the full suite once, captures everything to files, prints the debug log.
cd /d C:\xampp\htdocs\respawn-logics

del /q debug_login.log 2>nul
del /q test_output.txt 2>nul

echo Running PHPUnit (full suite, one pass)...
C:\xampp\php\php.exe vendor\bin\phpunit --testdox > test_output.txt 2>&1

echo.
echo ================= TEST OUTPUT (tail) =================
more +0 test_output.txt
echo.
echo ================= debug_login.log ====================
if exist debug_login.log (type debug_login.log) else (echo debug_login.log was not created)
echo.
echo Done. Results saved to test_output.txt and debug_login.log
pause
