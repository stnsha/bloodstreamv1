@echo off
REM ============================================
REM Blood Stream AI Review Job Dispatcher
REM Dispatches unreviewed test results to queue
REM Runs hourly via Splinterware System Scheduler
REM ============================================

REM Prevent window from appearing (silent operation)
if not "%1"=="silent" (
    start /min "" cmd /c "%~f0" silent
    exit /b
)

REM Set the working directory - UPDATE PATH FOR STAGING/PRODUCTION!
cd /d "C:\xampp\htdocs\production"

REM Check if another instance is running
tasklist /FI "WINDOWTITLE eq AI Dispatch Process*" 2>NUL | find /I /N "cmd.exe">NUL
if "%ERRORLEVEL%"=="0" (
    echo [%DATE% %TIME%] Another instance is already running >> ai_dispatch_scheduler.log
    exit /b 0
)

REM Set window title for instance checking
title AI Dispatch Process - %DATE% %TIME%

REM Set PHP memory and execution limits (lower than sync version)
set PHP_MEMORY_LIMIT=128M
set PHP_MAX_EXECUTION_TIME=120

REM Log the start time
echo [%DATE% %TIME%] Starting AI review job dispatch >> ai_dispatch_scheduler.log 2>&1

REM Run the Artisan command with LOW priority
REM /B = Don't create new window
REM /LOW = Run at low priority
REM /WAIT = Wait for command to complete
start /B /LOW /WAIT php -d memory_limit=%PHP_MEMORY_LIMIT% -d max_execution_time=%PHP_MAX_EXECUTION_TIME% artisan ai:dispatch-unreviewed >> ai_dispatch_scheduler.log 2>&1

REM Capture exit code
set EXIT_CODE=%ERRORLEVEL%

REM Log completion
if %EXIT_CODE% neq 0 (
    echo [%DATE% %TIME%] AI review job dispatch completed with errors - Exit code: %EXIT_CODE% >> ai_dispatch_scheduler.log 2>&1
) else (
    echo [%DATE% %TIME%] AI review job dispatch completed successfully >> ai_dispatch_scheduler.log 2>&1
)

REM Clean exit
exit /b %EXIT_CODE%
