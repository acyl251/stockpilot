@echo off
title StockPilot - Demarrage

echo Demarrage du backend Laravel...
start "StockPilot - Backend" cmd /k "cd /d "%~dp0backend" && php -d max_execution_time=0 artisan serve --port=8000"

timeout /t 2 /nobreak >nul

echo Demarrage du frontend Vue...
start "StockPilot - Frontend" cmd /k "cd /d "%~dp0frontend" && npm run dev"

timeout /t 3 /nobreak >nul

echo Ouverture du navigateur...
start "" "http://localhost:5173"

echo.
echo Les deux serveurs sont en cours de demarrage.
echo Backend  : http://localhost:8000
echo Frontend : http://localhost:5173
echo.
pause