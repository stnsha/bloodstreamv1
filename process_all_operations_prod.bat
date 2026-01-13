@echo off
REM ============================================
REM Blood Stream - Master Queue & Recovery Orchestrator
REM Consolidates all AI operations into one file
REM
REM Scheduling: Run every 5 minutes via Task Scheduler
REM - Always runs: Queue worker (highest priority)
REM - Hourly runs: Reconciliation and Recovery (at :00-:04)
REM ============================================

REM Set working directory
cd /d "C:\xampp\htdocs\production"

REM Create logs directory if it doesn't exist
if not exist "storage\logs" mkdir storage\logs

REM Set PHP configuration
set PHP_MEMORY_LIMIT=1024M

REM Get current time for conditional logic
for /f "tokens=1-2 delims=/:" %%a in ('echo %TIME%') do (
    set HOUR=%%a
    set MINUTE=%%b
)

REM Remove leading zero from hour and minute for numeric comparison
setlocal enabledelayedexpansion
set "HOUR_NUM=!HOUR:0=!"
set "MINUTE_NUM=!MINUTE:0=!"
if "!HOUR_NUM!"=="" set "HOUR_NUM=0"
if "!MINUTE_NUM!"=="" set "MINUTE_NUM=0"

echo [%DATE% %TIME%] ============================================ >> storage\logs\operations_master.log
echo [%DATE% %TIME%] Master Orchestrator started (Hour: !HOUR_NUM!, Minute: !MINUTE_NUM!) >> storage\logs\operations_master.log

REM ============================================
REM PHASE 1: CRITICAL - Process all queued jobs (Every 5 minutes)
REM ============================================
echo [%DATE% %TIME%] PHASE 1: Starting queue worker... >> storage\logs\operations_master.log

php -d memory_limit=%PHP_MEMORY_LIMIT% artisan queue:work database --queue=panel,ai-webhooks,ai-reviews,migration --timeout=300 --max-jobs=50 --max-time=600 --tries=3 2>&1 | findstr /C:"error" /C:"Error" /C:"ERROR" /C:"warning" /C:"Warning" /C:"WARNING" /C:"exception" /C:"Exception" /C:"failed" /C:"Failed" /C:"Processing Job" >> storage\logs\operations_master.log

if %ERRORLEVEL% GTR 1 (
    echo [%DATE% %TIME%] ERROR: Queue worker failed - Exit code: %ERRORLEVEL% >> storage\logs\operations_master.log
) else (
    echo [%DATE% %TIME%] PHASE 1: Queue worker completed successfully >> storage\logs\operations_master.log
)

REM ============================================
REM PHASE 2: Check if it's time for hourly operations (Runs at :00-:04 of each hour)
REM ============================================
if !MINUTE_NUM! LEQ 4 (
    echo [%DATE% %TIME%] PHASE 2: Hourly operations window detected - proceeding with recovery... >> storage\logs\operations_master.log

    REM ============================================
    REM PHASE 2A: Reconcile orphaned reviews
    REM ============================================
    echo [%DATE% %TIME%] PHASE 2A: Starting orphaned review reconciliation... >> storage\logs\operations_master.log

    php -d memory_limit=%PHP_MEMORY_LIMIT% artisan ai:reconcile-reviews --hours=6 --limit=200 2>&1 | findstr /C:"error" /C:"Error" /C:"ERROR" /C:"warning" /C:"Warning" /C:"WARNING" /C:"exception" /C:"Exception" /C:"failed" /C:"Failed" >> storage\logs\operations_master.log

    if %ERRORLEVEL% GTR 1 (
        echo [%DATE% %TIME%] ERROR: Reconciliation failed - Exit code: %ERRORLEVEL% >> storage\logs\operations_master.log
    ) else (
        echo [%DATE% %TIME%] PHASE 2A: Reconciliation completed successfully >> storage\logs\operations_master.log
    )

    REM ============================================
    REM PHASE 2B: Retry failed reviews
    REM ============================================
    echo [%DATE% %TIME%] PHASE 2B: Starting retry of failed AI reviews... >> storage\logs\operations_master.log

    php -d memory_limit=%PHP_MEMORY_LIMIT% artisan ai:retry-failed-reviews --hours=12 --limit=50 2>&1 | findstr /C:"error" /C:"Error" /C:"ERROR" /C:"warning" /C:"Warning" /C:"WARNING" /C:"exception" /C:"Exception" /C:"failed" /C:"Failed" >> storage\logs\operations_master.log

    if %ERRORLEVEL% GTR 1 (
        echo [%DATE% %TIME%] ERROR: Retry failed - Exit code: %ERRORLEVEL% >> storage\logs\operations_master.log
    ) else (
        echo [%DATE% %TIME%] PHASE 2B: Retry completed successfully >> storage\logs\operations_master.log
    )

    REM ============================================
    REM PHASE 2C: Dispatch unreviewed results to AI
    REM ============================================
    echo [%DATE% %TIME%] PHASE 2C: Starting dispatch of unreviewed results to AI... >> storage\logs\operations_master.log

    php -d memory_limit=%PHP_MEMORY_LIMIT% artisan ai:dispatch-unreviewed-async 2>&1 | findstr /C:"error" /C:"Error" /C:"ERROR" /C:"warning" /C:"Warning" /C:"WARNING" /C:"exception" /C:"Exception" /C:"failed" /C:"Failed" >> storage\logs\operations_master.log

    if %ERRORLEVEL% GTR 1 (
        echo [%DATE% %TIME%] ERROR: Dispatch unreviewed async failed - Exit code: %ERRORLEVEL% >> storage\logs\operations_master.log
    ) else (
        echo [%DATE% %TIME%] PHASE 2C: Dispatch unreviewed async completed successfully >> storage\logs\operations_master.log
    )

    echo [%DATE% %TIME%] PHASE 2: All hourly operations completed >> storage\logs\operations_master.log
) else (
    echo [%DATE% %TIME%] Skipping hourly operations (next window at :00-:04) >> storage\logs\operations_master.log
)

REM ============================================
REM Execution complete
REM ============================================
echo [%DATE% %TIME%] Master orchestrator cycle completed >> storage\logs\operations_master.log
echo [%DATE% %TIME%] ============================================ >> storage\logs\operations_master.log

exit /b 0
