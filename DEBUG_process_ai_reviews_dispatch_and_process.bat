@echo off
REM ============================================
REM Blood Stream AI Review - Dispatch Only
REM Dispatches unreviewed results to ai-reviews queue
REM DEBUG VERSION - Comprehensive logging
REM ============================================

setlocal enabledelayedexpansion

REM Set the working directory - UPDATE PATH FOR STAGING/PRODUCTION!
cd /d "C:\xampp\htdocs\production"

REM Verify we're in the right directory
if not exist "artisan" (
    echo [%DATE% %TIME%] ERROR: Not in Laravel directory. Current: %CD% >> storage\logs\ai_dispatch.log
    exit /b 1
)

REM Set PHP memory and execution limits
set PHP_MEMORY_LIMIT=512M
set LOG_FILE=storage\logs\ai_dispatch.log

REM Create logs directory if it doesn't exist
if not exist "storage\logs" mkdir storage\logs

REM Log start
echo. >> %LOG_FILE%
echo ============================================ >> %LOG_FILE%
echo [%DATE% %TIME%] DISPATCH COMMAND STARTED >> %LOG_FILE%
echo Current Directory: %CD% >> %LOG_FILE%
echo PHP Memory Limit: %PHP_MEMORY_LIMIT% >> %LOG_FILE%
echo ============================================ >> %LOG_FILE%

REM Check database connectivity
echo [%DATE% %TIME%] Checking database connectivity... >> %LOG_FILE%
php -d memory_limit=%PHP_MEMORY_LIMIT% artisan tinker --execute="echo 'Database OK';" 2>&1 >> %LOG_FILE%
if %ERRORLEVEL% NEQ 0 (
    echo [%DATE% %TIME%] ERROR: Database connection failed - Exit code: %ERRORLEVEL% >> %LOG_FILE%
)

REM Run dispatch command with comprehensive output
echo [%DATE% %TIME%] Running ai:dispatch-unreviewed-async command... >> %LOG_FILE%
php -d memory_limit=%PHP_MEMORY_LIMIT% artisan ai:dispatch-unreviewed-async 2>&1 >> %LOG_FILE%
set DISPATCH_EXIT_CODE=%ERRORLEVEL%

REM Log exit code and results
echo [%DATE% %TIME%] Command exit code: %DISPATCH_EXIT_CODE% >> %LOG_FILE%

if %DISPATCH_EXIT_CODE% EQU 0 (
    echo [%DATE% %TIME%] SUCCESS: Dispatch command completed successfully >> %LOG_FILE%
) else (
    echo [%DATE% %TIME%] ERROR: Dispatch command failed with exit code %DISPATCH_EXIT_CODE% >> %LOG_FILE%
)

REM Check if jobs were queued
echo [%DATE% %TIME%] Checking jobs table... >> %LOG_FILE%
php -d memory_limit=%PHP_MEMORY_LIMIT% artisan tinker --execute="echo App\Models\Job::where('created_at', '>=', now()->subHour())->count() . ' jobs in last hour';" 2>&1 >> %LOG_FILE%

REM Log end
echo [%DATE% %TIME%] DISPATCH COMMAND ENDED >> %LOG_FILE%
echo ============================================ >> %LOG_FILE%
echo. >> %LOG_FILE%

exit /b %DISPATCH_EXIT_CODE%
