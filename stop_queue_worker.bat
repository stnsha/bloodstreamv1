@echo off
REM ============================================
REM Stop Blood Stream Queue Worker
REM Disables the scheduled task and stops any running instance
REM ============================================

echo.
echo ============================================
echo Stopping Blood Stream Queue Worker
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

echo Stopping any running instance...
schtasks /End /TN "%TASK_NAME%" >nul 2>&1

echo Disabling scheduled task...
schtasks /Change /TN "%TASK_NAME%" /DISABLE >nul 2>&1

if %errorlevel% equ 0 (
    echo.
    echo ============================================
    echo SUCCESS! Queue worker stopped and disabled.
    echo ============================================
    echo.
    echo The worker will no longer run automatically.
    echo To start it again, run: start_queue_worker.bat
    echo.
) else (
    echo.
    echo ============================================
    echo ERROR! Failed to disable scheduled task.
    echo ============================================
    echo.
    echo You may need administrator privileges.
    echo Try running this script as administrator.
    echo.
)

pause
