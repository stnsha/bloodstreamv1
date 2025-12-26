@echo off
REM ============================================
REM ODB Migration - Auto-Fix Stuck Batches
REM Automatically detects and fixes stuck batches
REM Run via Splinterware every 30 minutes
REM ============================================

REM Set working directory - PRODUCTION
cd /d "C:\xampp\htdocs\production"

REM Configuration
set LOG_FILE=storage\logs\stuck_batches_autofix.log
set TIMESTAMP=%date:~-4%-%date:~-7,2%-%date:~-10,2% %time:~0,2%:%time:~3,2%:%time:~6,2%
set TIMESTAMP=%TIMESTAMP: =0%

REM Add separator with timestamp
echo. >> "%LOG_FILE%" 2>&1
echo ============================================ >> "%LOG_FILE%" 2>&1
echo Stuck Batch Auto-Fix Run >> "%LOG_FILE%" 2>&1
echo Timestamp: %TIMESTAMP% >> "%LOG_FILE%" 2>&1
echo ============================================ >> "%LOG_FILE%" 2>&1
echo. >> "%LOG_FILE%" 2>&1

REM Run auto-fix command
php artisan migration:detect-stuck --fix >> "%LOG_FILE%" 2>&1

REM Add footer
echo. >> "%LOG_FILE%" 2>&1
echo -------------------------------------------- >> "%LOG_FILE%" 2>&1
echo. >> "%LOG_FILE%" 2>&1

REM Clean exit
exit /b 0
