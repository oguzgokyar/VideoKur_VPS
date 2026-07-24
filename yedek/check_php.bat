@echo off
cd /d "c:\Users\user\Documents\GitHub\Antigravity\Video_Kur"
echo === PHP Modules ===
php -m
echo.
echo === PHP.INI Location ===
php -r "echo php_ini_loaded_file();"
echo.
echo === Config File ===
type data\config.json
echo.
echo === PHP Processes ===
tasklist | findstr /i php
