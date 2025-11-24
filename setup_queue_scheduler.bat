@echo off
REM ============================================
REM Setup Windows Task Scheduler for Queue Worker
REM This configures the queue worker to run every 12 minutes
REM ============================================

echo.
echo ============================================
echo Blood Stream Queue Worker Scheduler Setup
echo ============================================
echo.

REM Check for administrator privileges
net session >nul 2>&1
if %errorlevel% neq 0 (
    echo ERROR: This script requires administrator privileges.
    echo Please right-click and select "Run as administrator"
    echo.
    pause
    exit /b 1
)

REM Set variables
set "TASK_NAME=BloodStreamQueueWorker"
set "SCRIPT_PATH=%~dp0queue_worker.bat"

echo Configuration:
echo - Task Name: %TASK_NAME%
echo - Script Path: %SCRIPT_PATH%
echo - Schedule: Every 12 minutes
echo - Max Runtime: 10 minutes per execution
echo - Delay Between Runs: 15 seconds minimum
echo.

REM Delete existing task if it exists
schtasks /Query /TN "%TASK_NAME%" >nul 2>&1
if %errorlevel% equ 0 (
    echo Removing existing task...
    schtasks /Delete /TN "%TASK_NAME%" /F >nul 2>&1
)

echo Creating new scheduled task...

REM Create the scheduled task
REM /SC MINUTE /MO 12 - Run every 12 minutes
REM /ST 00:00 - Start time (will run continuously every 12 min from boot)
REM /RU SYSTEM - Run as SYSTEM account (no login required)
REM /RL HIGHEST - Run with highest privileges
REM /F - Force create (overwrite if exists)
schtasks /Create /TN "%TASK_NAME%" ^
    /TR "\"%SCRIPT_PATH%\"" ^
    /SC MINUTE /MO 12 ^
    /ST 00:00 ^
    /RU SYSTEM ^
    /RL HIGHEST ^
    /F

if %errorlevel% equ 0 (
    echo.
    echo ============================================
    echo SUCCESS! Task created successfully.
    echo ============================================
    echo.
    echo The queue worker will now run every 12 minutes automatically.
    echo Each run will process jobs for up to 10 minutes, then wait
    echo 15 seconds before exiting, allowing the next scheduled run.
    echo.
    echo Management commands:
    echo - Start worker: schtasks /Run /TN "%TASK_NAME%"
    echo - Stop worker:  schtasks /End /TN "%TASK_NAME%"
    echo - Disable:      schtasks /Change /TN "%TASK_NAME%" /DISABLE
    echo - Enable:       schtasks /Change /TN "%TASK_NAME%" /ENABLE
    echo - View task:    schtasks /Query /TN "%TASK_NAME%" /V /FO LIST
    echo.
    echo Or use the helper scripts:
    echo - start_queue_worker.bat
    echo - stop_queue_worker.bat
    echo.
) else (
    echo.
    echo ============================================
    echo ERROR! Failed to create scheduled task.
    echo ============================================
    echo.
    echo Please check:
    echo 1. You have administrator privileges
    echo 2. The queue_worker.bat file exists at: %SCRIPT_PATH%
    echo 3. Windows Task Scheduler service is running
    echo.
)

pause
