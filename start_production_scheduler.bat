@echo off
REM Production Scheduler Başlatıcı
REM SEQUENTIAL PRODUCTION: Tek seferde 1 video üretimi

echo.
echo ========================================================
echo   Production Scheduler - SEQUENTIAL MODE
echo ========================================================
echo   - One video at a time
echo   - Queue-based processing  
echo   - NO PARALLEL PRODUCTION!
echo ========================================================
echo.

cd /d "%~dp0"

REM Python kontrolü
python --version >nul 2>&1
if %errorlevel% neq 0 (
    echo HATA: Python bulunamadi!
    echo Lutfen Python 3.9+ yukleyin.
    pause
    exit /b 1
)

echo Production Scheduler calisiyor...
echo Videolar SIRALI olarak uretilecek (1 video/seferde)
echo.
echo Durdurmak icin: Ctrl+C
echo.

python python\scheduler\production_scheduler.py

pause
