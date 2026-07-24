@echo off
echo ========================================
echo Content Scheduler Baslat
echo ========================================
echo.
echo RSS feed'ler her 30 dakikada kontrol edilecek
echo Durdurmak icin Ctrl+C basin
echo.
echo ========================================
echo.

cd /d "%~dp0"
python python/content/scheduler.py

pause
