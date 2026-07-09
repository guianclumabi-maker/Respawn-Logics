@echo off
cd /d "%~dp0"
if not exist node_modules (
  echo Installing dependencies (first run only)...
  call npm install
)
echo.
echo Starting Expo. Scan the QR code with your iPhone camera (Expo Go must be installed).
echo Your phone must be on the same Wi-Fi as this PC.
echo.
call npx expo start
pause
