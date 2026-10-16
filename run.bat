@echo off
title Findora AI - Campus Lost and Found Launcher
echo ========================================================
echo    Launching Findora AI College Lost and Found...
echo ========================================================

:: Check if Apache is running, start if needed
tasklist /FI "IMAGENAME eq httpd.exe" 2>NUL | find /I /N "httpd.exe">NUL
if "%ERRORLEVEL%"=="1" (
    echo Starting Apache Web Server...
    start "" "C:\xampp\apache_start.bat"
) else (
    echo Apache is already running.
)

:: Check if MySQL is running, start if needed
tasklist /FI "IMAGENAME eq mysqld.exe" 2>NUL | find /I /N "mysqld.exe">NUL
if "%ERRORLEVEL%"=="1" (
    echo Starting MySQL Database Server...
    start "" "C:\xampp\mysql_start.bat"
) else (
    echo MySQL is already running.
)

:: Wait 2 seconds for services to be ready
timeout /t 2 /nobreak >nul

:: Open the application in default web browser
echo Opening Findora AI in your default browser...
start "" "http://localhost/college-lost-found/"

echo.
echo ========================================================
echo   Findora AI is running at:
echo   http://localhost/college-lost-found/
echo ========================================================
echo.
timeout /t 3
exit
