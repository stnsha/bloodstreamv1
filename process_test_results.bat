@echo off
REM Batch file for Splinterware System Scheduler
REM Process blood test results for AI analysis

REM Set the working directory to your Laravel project - DIFFERENT PATH FOR STAGING AND PRODUCTION!
cd /d "C:\laragon\www\blood-stream-v1" 

REM Log the start time
echo [%DATE% %TIME%] Starting blood test results processing >> scheduler.log

REM Run the Laravel artisan command
php artisan bloodstream:process-results --batch-size=15 --max-results=200 >> scheduler.log 2>&1

REM Check if the command was successful
if %ERRORLEVEL% EQU 0 (
    echo [%DATE% %TIME%] Blood test results processing job dispatched successfully >> scheduler.log
) else (
    echo [%DATE% %TIME%] ERROR: Blood test results processing failed with error level %ERRORLEVEL% >> scheduler.log
)

REM Run the queue worker to process the jobs (run for 5 minutes then exit)
echo [%DATE% %TIME%] Starting queue worker >> scheduler.log
php artisan queue:work database --timeout=900 --memory=512 --max-time=300 >> scheduler.log 2>&1

echo [%DATE% %TIME%] Queue worker finished >> scheduler.log