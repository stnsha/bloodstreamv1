@echo off
REM ============================================
REM Blood Stream AI Review Processor (Custom)
REM System Scheduler Task: process_ai_review
REM With custom batch size and max results
REM ============================================

REM Set the project directory
cd /d "C:\laragon\www\blood-stream-v1"

REM Set custom parameters (adjust as needed)
set BATCH_SIZE=20
set MAX_RESULTS=300

REM Set log file path with timestamp
set "LOGFILE=storage\logs\scheduler_ai_review_%date:~-4,4%%date:~-7,2%%date:~-10,2%.log"

REM Display start message
echo ============================================ >> "%LOGFILE%"
echo AI Review Process Started: %date% %time% >> "%LOGFILE%"
echo Configuration: batch-size=%BATCH_SIZE%, max-results=%MAX_RESULTS% >> "%LOGFILE%"
echo ============================================ >> "%LOGFILE%"

REM Run the Laravel artisan command with custom parameters
php artisan bloodstream:process-results --batch-size=%BATCH_SIZE% --max-results=%MAX_RESULTS% >> "%LOGFILE%" 2>&1

REM Check if the command was successful
if %errorlevel% equ 0 (
    echo [SUCCESS] AI Review job dispatched successfully at %time% >> "%LOGFILE%"
) else (
    echo [ERROR] AI Review job failed with error level %errorlevel% at %time% >> "%LOGFILE%"
)

REM Display end message
echo ============================================ >> "%LOGFILE%"
echo AI Review Process Ended: %date% %time% >> "%LOGFILE%"
echo ============================================ >> "%LOGFILE%"
echo. >> "%LOGFILE%"

REM Exit with the same error level as the artisan command
exit /b %errorlevel%
