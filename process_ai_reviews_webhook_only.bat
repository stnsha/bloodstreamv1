@echo off
REM ============================================
REM Blood Stream AI Review - Webhook Queue Processing Only
REM Process webhook response queue jobs already dispatched
REM Can run more frequently (every 5-15 minutes)
REM ============================================

REM Set the working directory - UPDATE PATH FOR STAGING/PRODUCTION!
cd /d "C:\laragon\www\blood-stream-v1"

REM Note: Instance locking is handled by the command's cache lock mechanism

REM Set PHP memory and execution limits
set PHP_MEMORY_LIMIT=512M
set PHP_MAX_EXECUTION_TIME=900

REM ============================================
REM STEP 1: Process webhook queue jobs
REM ============================================
echo [%DATE% %TIME%] Step 1: Processing webhook queue jobs >> ai_review_webhook_process.log 2>&1
start /B /LOW /WAIT php -d memory_limit=%PHP_MEMORY_LIMIT% -d max_execution_time=%PHP_MAX_EXECUTION_TIME% artisan queue:work database --timeout=900 --memory=512 --max-time=300 --sleep=3 --tries=1 >> ai_review_webhook_process.log 2>&1

REM Capture exit code
set EXIT_CODE=%ERRORLEVEL%

REM Log completion
if %EXIT_CODE% neq 0 (
    echo [%DATE% %TIME%] Queue processing completed with errors - Exit code: %EXIT_CODE% >> ai_review_webhook_process.log 2>&1
) else (
    echo [%DATE% %TIME%] Queue processing completed successfully >> ai_review_webhook_process.log 2>&1
)

REM Clean exit
exit /b %EXIT_CODE%
