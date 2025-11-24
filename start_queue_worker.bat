@echo off
REM ============================================
REM Start Blood Stream Queue Worker
REM Enables the scheduled task and optionally runs it immediately
REM ============================================

echo.
echo ============================================
echo Starting Blood Stream Queue Worker
echo ============================================
echo.

set "TASK_NAME=BloodStreamQueueWorker"

REM Check if task exists
schtasks /Query /TN "%TASK_NAME%" >nul 2>&1
if %errorlevel% neq 0 (
    echo ERROR: Task "%TASK_NAME%" not found.
    echo Please run setup_queue_scheduler.bat first.
    echo.
    pause
    exit /b 1
)

echo Enabling scheduled task...
schtasks /Change /TN "%TASK_NAME%" /ENABLE >nul 2>&1

if %errorlevel% neq 0 (
    echo.
    echo ============================================
    echo ERROR! Failed to enable scheduled task.
    echo ============================================
    echo.
    echo You may need administrator privileges.
    echo Try running this script as administrator.
    echo.
    pause
    exit /b 1
)

echo.
echo ============================================
echo SUCCESS! Queue worker enabled.
echo ============================================
echo.
echo The worker will run every 12 minutes automatically.
echo.

REM Ask if user wants to run it immediately
set /p "RUN_NOW=Do you want to run the worker immediately? (Y/N): "
if /i "%RUN_NOW%"=="Y" (
    echo.
    echo Starting worker now...
    schtasks /Run /TN "%TASK_NAME%" >nul 2>&1

    if %errorlevel% equ 0 (
        echo Worker started successfully!
        echo Check the log file at: storage\logs\queue_worker_*.log
    ) else (
        echo Failed to start worker immediately.
        echo It will start automatically based on the schedule.
    )
)

echo.
echo To stop the worker, run: stop_queue_worker.bat
echo.

pause
