@echo off
REM Set the working directory - UPDATE PATH FOR STAGING/PRODUCTION!
cd /d "C:\xampp\htdocs\production"

REM Unified queue worker with priority-based processing
REM Processes queues in order: panel > ai-webhooks > ai-reviews > migration

set PHP_MEMORY_LIMIT=1024M

REM Log start time
echo [%DATE% %TIME%] Queue worker started >> storage\logs\queue_worker.log

REM Start worker - log ALL output (success + error)
php -d memory_limit=%PHP_MEMORY_LIMIT% -d max_execution_time=900 artisan queue:work database ^
 --queue=panel,ai-webhooks,ai-reviews,migration ^
 --timeout=300 ^
 --memory=1024 ^
 --max-jobs=50 ^
 --max-time=600 ^
 --sleep=3 ^
 --tries=3 ^
 --rest=1 >> storage\logs\queue_worker.log 2>&1

REM Log stop time
echo [%DATE% %TIME%] Queue worker stopped >> storage\logs\queue_worker.log

exit /b 0
