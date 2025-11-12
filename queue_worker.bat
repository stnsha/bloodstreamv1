@echo off
REM ============================================
REM Blood Stream Queue Worker
REM Processes queued jobs including AI Review
REM Should run continuously
REM ============================================

REM Set the project directory
cd /d "C:\xampp\htdocs\production"

REM Set log file path with timestamp
set "LOGFILE=storage\logs\queue_worker_%date:~-4,4%%date:~-7,2%%date:~-10,2%.log"

REM Display start message
echo ============================================ >> "%LOGFILE%"
echo Queue Worker Started: %date% %time% >> "%LOGFILE%"
echo ============================================ >> "%LOGFILE%"

:start_worker
echo Starting Laravel Queue Worker... >> "%LOGFILE%"
echo [%date% %time%] Worker starting >> "%LOGFILE%"

REM Run the queue worker
REM --tries=3: Retry failed jobs up to 3 times
REM --timeout=1800: 30 minutes timeout per job (matches ProcessAIReviewJob)
REM --sleep=3: Wait 3 seconds when no jobs available
REM --max-jobs=100: Restart worker after 100 jobs to prevent memory leaks
php artisan queue:work --tries=3 --timeout=1800 --sleep=3 --max-jobs=100 >> "%LOGFILE%" 2>&1

REM Check exit status
if %errorlevel% neq 0 (
    echo [ERROR] Queue worker stopped with error level %errorlevel% at %time% >> "%LOGFILE%"
    echo Waiting 10 seconds before restart... >> "%LOGFILE%"
    timeout /t 10 /nobreak > nul
) else (
    echo [INFO] Queue worker completed max jobs at %time% >> "%LOGFILE%"
    echo Restarting immediately... >> "%LOGFILE%"
)

REM Restart the worker
echo ============================================ >> "%LOGFILE%"
goto start_worker
