@echo off
REM Queue worker: ai-webhooks
REM Runs for 55 seconds, then exits. Windows Task Scheduler restarts it every minute.
REM Set working directory
cd /d "C:\laragon\www\blood-stream-v1"

REM Run the queue worker
php artisan queue:work database --queue=ai-webhooks --timeout=300 --max-jobs=50 --max-time=55 --tries=3

exit /b 0
