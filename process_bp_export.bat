@echo off
REM Batch file for BP Export Queue Processing
REM Process BP export jobs

REM Set the working directory to your Laravel project - DIFFERENT PATH FOR STAGING AND PRODUCTION!
cd /d "C:\xampp\htdocs\production"

REM Log the start time
echo [%DATE% %TIME%] Starting BP export queue worker >> bp-export.log

REM Run the queue worker to process BP export jobs (run for 15 minutes then exit)
php artisan queue:work database --timeout=900 --memory=1024 --max-time=900 >> bp-export.log 2>&1

REM Check if the command was successful
if %ERRORLEVEL% EQU 0 (
    echo [%DATE% %TIME%] BP export queue worker completed successfully >> bp-export.log
) else (
    echo [%DATE% %TIME%] ERROR: BP export queue worker failed with error level %ERRORLEVEL% >> bp-export.log
)

echo [%DATE% %TIME%] BP export queue worker finished >> bp-export.log
