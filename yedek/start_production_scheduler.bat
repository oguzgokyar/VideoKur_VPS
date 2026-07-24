@echo off
REM Production Scheduler Başlatıcı
REM Video üretim kuyruğunu sırayla işler (serial production)

echo.
echo ========================================
echo   Production Scheduler Baslatiliyor
echo ========================================
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

python python\scheduler\production_scheduler.py --interval 30

pause
