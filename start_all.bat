@echo off
chcp 65001 >nul
REM Video_Kur - Tüm Servisleri Başlat

echo.
echo ========================================
echo   VIDEO_KUR - PROJE BAŞLATILIYOR
echo ========================================
echo.

cd /d "%~dp0"

REM XAMPP PHP kontrol et
if not exist "C:\xampp\php\php.exe" (
    echo HATA: XAMPP bulunamadi!
    echo Lutfen XAMPP'yi C:\xampp\ alt dizinine kurunuz.
    pause
    exit /b 1
)

echo [1/3] PHP Sunucusu Baslatiliyor (localhost:8000)...
start "PHP Server - Video_Kur" C:\xampp\php\php.exe -S localhost:8000 router.php

REM Python scheduler'ı başlat (eğer gerekiyse)
REM Python kontrolü
python --version >nul 2>&1
if %errorlevel% equ 0 (
    echo [2/3] Python Scheduler Baslatiliyor...
    REM start "Production Scheduler" cmd /k python python/scheduler/production.py
    echo [2/3] Python scheduler istemlediginde baslatilabilir.
) else (
    echo [2/3] UYARI: Python bulunamadi. Manuel olarak baslatabilisiniz.
)

echo.
echo [3/3] Dashboard Aciliyor...
timeout /t 2 /nobreak
start "" "http://localhost:8000"

echo.
echo ========================================
echo   HAZIR!
echo ========================================
echo.
echo Web Adresi..: http://localhost:8000
echo API Adresi...: http://localhost:8000/api/
echo.
echo NOTLAR:
echo - PHP sunucusu penceresi kapali kalirsaniz hizmet durur
echo - Output klasoru icinde olusan videolari buradan yonetebilirsiniz
echo.
pause
