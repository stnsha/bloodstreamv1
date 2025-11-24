@echo off
REM ============================================
REM Blood Stream Queue Worker
REM Processes queued jobs including AI Review
REM Designed to run via Windows Task Scheduler
REM ============================================

REM Set the project directory
cd /d "C:\xampp\htdocs\production"

REM Prevent double instances
wmic process where "CommandLine like '%%queue:work%%'" get ProcessId >nul 2>&1 && (
    echo Worker already running.
    exit /b
)

REM Set log file path with timestamp
set "LOGFILE=storage\logs\queue_worker.log"

REM Display start message
echo ============================================ >> "%LOGFILE%"
echo Queue Worker Started: %date% %time% >> "%LOGFILE%"
echo ============================================ >> "%LOGFILE%"

echo Starting Laravel Queue Worker... >> "%LOGFILE%"
echo [%date% %time%] Worker starting >> "%LOGFILE%"

REM Run the queue worker
REM --tries=3: Retry failed jobs up to 3 times
REM --timeout=1800: 30 minutes timeout per job (matches ProcessAIReviewJob)
REM --sleep=3: Wait 3 seconds when no jobs available
REM --max-jobs=100: Restart worker after 100 jobs to prevent memory leaks
REM --max-time=600: Run for maximum 10 minutes then exit cleanly
php artisan queue:work --tries=3 --timeout=1800 --sleep=3 --max-jobs=100 --max-time=600 >> "%LOGFILE%" 2>&1

REM Check exit status
if %errorlevel% neq 0 (
    echo [ERROR] Queue worker stopped with error level %errorlevel% at %time% >> "%LOGFILE%"
    echo Waiting 15 seconds before exit... >> "%LOGFILE%"
    timeout /t 15 /nobreak > nul
) else (
    echo [INFO] Queue worker completed successfully at %time% >> "%LOGFILE%"
    echo Waiting 15 seconds before exit... >> "%LOGFILE%"
    timeout /t 15 /nobreak > nul
)

echo ============================================ >> "%LOGFILE%"
echo Queue Worker Finished: %date% %time% >> "%LOGFILE%"
echo ============================================ >> "%LOGFILE%"

REM Exit cleanly - Task Scheduler will restart based on schedule
exit /b %errorlevel%
