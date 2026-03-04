@echo off
REM PRODUCTION VERSION -- DO NOT RUN LOCALLY
REM Queue worker: panel, ai-reviews
REM Runs for 55 seconds, then exits. Windows Task Scheduler restarts it every minute.
REM Set working directory
cd /d "C:\xampp\htdocs\production"

REM Run the queue worker
php artisan queue:work redis --queue=panel,ai-reviews --timeout=300 --max-jobs=50 --max-time=55 --tries=3

exit /b 0
