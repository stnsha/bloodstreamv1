@echo off
REM ============================================
REM Blood Stream AI Review - Dispatch Only
REM Dispatches unreviewed results to ai-reviews queue
REM Runs hourly via Task Scheduler
REM Queue processing handled by process_all_queues_priority.bat (runs every 5 min)
REM ============================================

REM Set the working directory - UPDATE PATH FOR STAGING/PRODUCTION!
cd /d "C:\xampp\htdocs\production"

REM Note: Instance locking is handled by the command's cache lock mechanism

REM Set PHP memory and execution limits
set PHP_MEMORY_LIMIT=512M

REM ============================================
REM Dispatch AI review jobs to queue
REM ============================================
echo [%DATE% %TIME%] Dispatching unreviewed test results to ai-reviews queue >> storage\logs\ai_dispatch.log 2>&1
php -d memory_limit=%PHP_MEMORY_LIMIT% artisan ai:dispatch-unreviewed-async >> storage\logs\ai_dispatch.log 2>&1

if %ERRORLEVEL% neq 0 (
    echo [%DATE% %TIME%] ERROR: Failed to dispatch jobs - Exit code: %ERRORLEVEL% >> storage\logs\ai_dispatch.log 2>&1
    exit /b %ERRORLEVEL%
)

echo [%DATE% %TIME%] Jobs dispatched successfully to ai-reviews queue >> storage\logs\ai_dispatch.log 2>&1

REM Clean exit
exit /b 0
