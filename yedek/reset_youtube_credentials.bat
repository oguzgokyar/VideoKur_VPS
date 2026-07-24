@echo off
REM YouTube Credentials Reset Script (Windows Batch)
REM Revoke olunan veya expired token'ları temizler

echo.
echo ========================================
echo YouTube Credentials Reset Tool
echo ========================================
echo.

REM Python scriptini çalıştır
python reset_youtube_credentials.py

if %ERRORLEVEL% equ 0 (
    echo.
    echo ✅ Tamamlandı!
    echo.
    pause
) else (
    echo.
    echo ❌ Hata oluştu
    echo.
    pause
    exit /b 1
)
