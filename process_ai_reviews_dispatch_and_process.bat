@echo off
REM ============================================
REM Blood Stream AI Review - Async Dispatch & Process
REM 1. Dispatches unreviewed results to queue (async webhook flow)
REM 2. Processes the queue jobs
REM Runs hourly via Splinterware System Scheduler
REM ============================================

REM Set the working directory - UPDATE PATH FOR STAGING/PRODUCTION!
cd /d "C:\laragon\www\blood-stream-v1"

REM Note: Instance locking is handled by the command's cache lock mechanism

REM Set PHP memory and execution limits
set PHP_MEMORY_LIMIT=512M
set PHP_MAX_EXECUTION_TIME=900

REM ============================================
REM STEP 0: Clear cache (for testing)
REM ============================================
echo [%DATE% %TIME%] Step 0: Clearing cache for testing >> ai_review_dispatch_process.log 2>&1
php artisan cache:clear >> ai_review_dispatch_process.log 2>&1

REM ============================================
REM STEP 1: Dispatch jobs to queue (async)
REM ============================================
echo [%DATE% %TIME%] Step 1: Dispatching async jobs to queue >> ai_review_dispatch_process.log 2>&1
php -d memory_limit=%PHP_MEMORY_LIMIT% artisan ai:dispatch-unreviewed-async >> ai_review_dispatch_process.log 2>&1

if %ERRORLEVEL% neq 0 (
    echo [%DATE% %TIME%] ERROR: Failed to dispatch async jobs - Exit code: %ERRORLEVEL% >> ai_review_dispatch_process.log 2>&1
    exit /b %ERRORLEVEL%
)

echo [%DATE% %TIME%] Async jobs dispatched successfully >> ai_review_dispatch_process.log 2>&1

REM ============================================
REM STEP 2: Process queue jobs
REM ============================================
echo [%DATE% %TIME%] Step 2: Processing queue jobs >> ai_review_dispatch_process.log 2>&1
start /B /LOW /WAIT php -d memory_limit=%PHP_MEMORY_LIMIT% -d max_execution_time=%PHP_MAX_EXECUTION_TIME% artisan queue:work database --timeout=900 --memory=512 --max-time=300 --sleep=3 --tries=1 >> ai_review_dispatch_process.log 2>&1

REM Capture exit code
set EXIT_CODE=%ERRORLEVEL%

REM Log completion
if %EXIT_CODE% neq 0 (
    echo [%DATE% %TIME%] Queue processing completed with errors - Exit code: %EXIT_CODE% >> ai_review_dispatch_process.log 2>&1
) else (
    echo [%DATE% %TIME%] Queue processing completed successfully >> ai_review_dispatch_process.log 2>&1
)

REM Clean exit
exit /b %EXIT_CODE%
