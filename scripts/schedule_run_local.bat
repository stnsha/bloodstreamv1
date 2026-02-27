@echo off
REM Set working directory
cd /d "C:\laragon\www\blood-stream-v1"

REM Run the Laravel scheduler
php artisan schedule:run >> storage\logs\scheduler.log 2>&1

exit /b 0
