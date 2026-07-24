@echo off
REM YouTube Scheduler Starter
REM Bu script scheduler'ı arka planda başlatır

echo ========================================
echo   YouTube Upload Scheduler
echo ========================================
echo.

cd python

echo Scheduler başlatılıyor...
echo.
echo ⏱️  Kontrol aralığı: 60 saniye
echo 📅 Kuyruğu her dakika kontrol edecek
echo.
echo Durdurmak için: Ctrl+C
echo.
echo ========================================
echo.

python scheduler\scheduler.py --interval 60

pause
