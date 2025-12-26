@echo off
REM ============================================
REM ODB Migration Queue Worker (Laravel Side)
REM Process migration jobs for 5 minutes then exit
REM Runs hourly via Splinterware
REM ============================================

REM Set working directory
cd /d "C:\xampp\htdocs\production"

REM Configuration
set PHP_MEMORY_LIMIT=1024M
set PHP_MAX_EXECUTION_TIME=600
set LOG_FILE=storage\logs\migration_queue_worker.log

echo ============================================ >> "%LOG_FILE%" 2>&1
echo ODB Migration Queue Worker >> "%LOG_FILE%" 2>&1
echo Started: %DATE% %TIME% >> "%LOG_FILE%" 2>&1
echo ============================================ >> "%LOG_FILE%" 2>&1

REM ============================================
REM Process migration queue jobs (time-limited)
REM ============================================
echo. >> "%LOG_FILE%" 2>&1
echo [%DATE% %TIME%] Processing migration queue jobs... >> "%LOG_FILE%" 2>&1

REM Queue worker parameters:
REM --queue=default      : Process default queue
REM --timeout=120        : Kill job after 2 minutes
REM --memory=1024        : Restart worker if memory exceeds 1024MB
REM --max-time=300       : Run for max 5 minutes then exit
REM --sleep=3            : Sleep 3 seconds when no jobs available
REM --tries=1            : Process jobs once (retry handled by job itself)
REM --rest=1             : Rest 1 second between jobs (throttling)

start /B /LOW /WAIT php -d memory_limit=%PHP_MEMORY_LIMIT% -d max_execution_time=%PHP_MAX_EXECUTION_TIME% artisan queue:work database --queue=default --timeout=120 --memory=1024 --max-time=300 --sleep=3 --tries=1 --rest=1 >> "%LOG_FILE%" 2>&1

set EXIT_CODE=%ERRORLEVEL%

echo. >> "%LOG_FILE%" 2>&1
if %EXIT_CODE% neq 0 (
    echo [%DATE% %TIME%] Queue worker completed with errors - Exit code: %EXIT_CODE% >> "%LOG_FILE%" 2>&1
) else (
    echo [%DATE% %TIME%] Queue worker completed successfully >> "%LOG_FILE%" 2>&1
)

echo ============================================ >> "%LOG_FILE%" 2>&1
echo Completed: %DATE% %TIME% >> "%LOG_FILE%" 2>&1
echo ============================================ >> "%LOG_FILE%" 2>&1

REM Clean exit (always successful for scheduler)
exit /b 0
