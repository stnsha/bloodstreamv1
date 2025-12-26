@echo off
REM ============================================
REM ODB Migration - Complete Monitoring
REM Runs dashboard + auto-fix stuck batches
REM Run via Splinterware hourly
REM ============================================

REM Set working directory - PRODUCTION
cd /d "C:\xampp\htdocs\production"

REM Configuration
set LOG_FILE=storage\logs\migration_monitoring.log
set TIMESTAMP=%date:~-4%-%date:~-7,2%-%date:~-10,2% %time:~0,2%:%time:~3,2%:%time:~6,2%
set TIMESTAMP=%TIMESTAMP: =0%

REM Add separator with timestamp
echo. >> "%LOG_FILE%" 2>&1
echo ============================================ >> "%LOG_FILE%" 2>&1
echo Complete Migration Monitoring Run >> "%LOG_FILE%" 2>&1
echo Timestamp: %TIMESTAMP% >> "%LOG_FILE%" 2>&1
echo ============================================ >> "%LOG_FILE%" 2>&1
echo. >> "%LOG_FILE%" 2>&1

REM ============================================
REM Step 1: Run Dashboard (Last 24 Hours)
REM ============================================
echo [%TIMESTAMP%] Running migration dashboard... >> "%LOG_FILE%" 2>&1
echo. >> "%LOG_FILE%" 2>&1

start /B /LOW /WAIT php artisan migration:dashboard >> "%LOG_FILE%" 2>&1

echo. >> "%LOG_FILE%" 2>&1
echo -------------------------------------------- >> "%LOG_FILE%" 2>&1
echo. >> "%LOG_FILE%" 2>&1

REM ============================================
REM Step 2: Auto-Fix Stuck Batches
REM ============================================
echo [%TIMESTAMP%] Checking for stuck batches... >> "%LOG_FILE%" 2>&1
echo. >> "%LOG_FILE%" 2>&1

start /B /LOW /WAIT php artisan migration:detect-stuck --fix >> "%LOG_FILE%" 2>&1

echo. >> "%LOG_FILE%" 2>&1
echo ============================================ >> "%LOG_FILE%" 2>&1
echo Monitoring Complete >> "%LOG_FILE%" 2>&1
echo ============================================ >> "%LOG_FILE%" 2>&1
echo. >> "%LOG_FILE%" 2>&1

REM Clean exit
exit /b 0
