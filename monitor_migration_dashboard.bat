@echo off
REM ============================================
REM ODB Migration - Dashboard Monitor
REM Automatically logs migration statistics
REM Run via Splinterware every hour or daily
REM ============================================

REM Set working directory - PRODUCTION
cd /d "C:\xampp\htdocs\production"

REM Configuration
set LOG_FILE=storage\logs\migration_dashboard.log
set TIMESTAMP=%date:~-4%-%date:~-7,2%-%date:~-10,2% %time:~0,2%:%time:~3,2%:%time:~6,2%
set TIMESTAMP=%TIMESTAMP: =0%

REM Add separator with timestamp
echo. >> "%LOG_FILE%" 2>&1
echo ============================================ >> "%LOG_FILE%" 2>&1
echo Migration Dashboard Report >> "%LOG_FILE%" 2>&1
echo Timestamp: %TIMESTAMP% >> "%LOG_FILE%" 2>&1
echo ============================================ >> "%LOG_FILE%" 2>&1
echo. >> "%LOG_FILE%" 2>&1

REM Run dashboard command (last 24 hours)
php artisan migration:dashboard >> "%LOG_FILE%" 2>&1

REM Add footer
echo. >> "%LOG_FILE%" 2>&1
echo -------------------------------------------- >> "%LOG_FILE%" 2>&1
echo. >> "%LOG_FILE%" 2>&1

REM Clean exit
exit /b 0
