@echo off
REM PRODUCTION VERSION -- DO NOT RUN LOCALLY
REM Set working directory
cd /d "C:\xampp\htdocs\production"

REM Run the Laravel scheduler
php artisan schedule:run >> storage\logs\schedule_run.log 2>&1

exit /b 0
