@echo off
REM Set the working directory - UPDATE PATH FOR STAGING/PRODUCTION!
cd /d "C:\xampp\htdocs\production"

REM Unified queue dispatcher and worker
REM Step 1: Dispatch unreviewed test results to ai-reviews queue
REM Step 2: Process all queues with priority (ai-webhooks > ai-reviews > migration)

set PHP_MEMORY_LIMIT=1024M
set MAX_EXECUTION_TIME=600

echo Starting queue dispatch and worker at %date% %time%

REM Step 1: Dispatch AI review jobs to queue
echo Dispatching unreviewed test results to queue...
php -d memory_limit=%PHP_MEMORY_LIMIT% artisan ai:dispatch-unreviewed-async >> storage\logs\queue_worker.log 2>&1

REM Step 2: Process all queues with priority order
echo Processing queues with priority: ai-webhooks ^> ai-reviews ^> migration
php -d memory_limit=%PHP_MEMORY_LIMIT% -d max_execution_time=%MAX_EXECUTION_TIME% artisan queue:work database --queue=ai-webhooks,ai-reviews,migration --timeout=180 --memory=1024 --max-jobs=100 --sleep=3 --tries=3 --rest=1 >> storage\logs\queue_worker.log 2>&1

REM Log completion
echo Queue worker completed at %date% %time% >> storage\logs\queue_worker.log

exit /b 0
