@echo off
REM Batch file for Splinterware System Scheduler
REM Process ODB migration jobs

REM Set the working directory to your Laravel project - DIFFERENT PATH FOR STAGING AND PRODUCTION!
cd /d "C:\xampp\htdocs\production"

REM Log the start time
echo [%DATE% %TIME%] Starting migration queue workers >> migration_scheduler.log

REM Run the queue worker to process the migration jobs (run for 5 minutes then exit)
echo [%DATE% %TIME%] Starting queue worker for migration processing >> migration_scheduler.log
php artisan queue:work database --timeout=900 --memory=512 --max-time=300 >> migration_scheduler.log 2>&1

echo [%DATE% %TIME%] Migration queue worker finished >> migration_scheduler.log
