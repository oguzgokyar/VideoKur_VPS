@echo off
echo ========================================
echo   Social Media Scheduler Baslatiliyor
echo ========================================
echo.

cd /d "%~dp0"

echo Kontrol ediliyor...

REM Check Python
python --version >nul 2>&1
if errorlevel 1 (
    echo [HATA] Python bulunamadi!
    pause
    exit /b 1
)

echo [OK] Python mevcut

REM Check required packages
python -c "import requests" >nul 2>&1
if errorlevel 1 (
    echo [!] requests paketi yukluyor...
    pip install requests
)

echo.
echo Scheduler baslatiliyor...
echo (Durdurmak icin Ctrl+C)
echo.

python python/scheduler/social_scheduler.py --interval 60

pause
