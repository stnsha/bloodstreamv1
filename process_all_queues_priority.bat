@echo off
REM Set the working directory - UPDATE PATH FOR STAGING/PRODUCTION!
cd /d "C:\xampp\htdocs\production"

REM Unified queue worker with priority-based processing
REM Processes queues in order: panel > ai-webhooks > ai-reviews > migration
REM This ensures time-sensitive jobs are processed first

set PHP_MEMORY_LIMIT=1024M

REM Start worker with priority queue order (errors only logged)
php -d memory_limit=%PHP_MEMORY_LIMIT% -d max_execution_time=900 artisan queue:work database --queue=panel,ai-webhooks,ai-reviews,migration --timeout=300 --memory=1024 --max-jobs=50 --max-time=600 --sleep=3 --tries=3 --rest=1 2>&1 | findstr /C:"error" /C:"Error" /C:"ERROR" /C:"warning" /C:"Warning" /C:"WARNING" /C:"exception" /C:"Exception" /C:"failed" /C:"Failed" >> storage\logs\queue_worker.log

exit /b 0
