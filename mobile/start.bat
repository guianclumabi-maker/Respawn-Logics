@echo off
cd /d "%~dp0"

where node >nul 2>nul
if errorlevel 1 (
  echo Node.js is not installed. Please install it first from https://nodejs.org
  echo Then run this file again.
  pause
  exit /b 1
)

if not exist node_modules (
  echo Installing dependencies - first run only, this takes a few minutes...
  call npm install
)

echo.
echo Starting Expo. Scan the QR code below with your iPhone camera.
echo Requirements: Expo Go installed on the iPhone, phone on the SAME Wi-Fi as this PC.
echo.
call npx expo start

pause
