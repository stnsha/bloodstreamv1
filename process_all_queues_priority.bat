@echo off
REM Set the working directory - UPDATE PATH FOR STAGING/PRODUCTION!
cd /d "C:\xampp\htdocs\production"

REM Unified queue worker with priority-based processing
REM Processes queues in order: ai-webhooks > ai-reviews > migration
REM This ensures time-sensitive jobs are processed first

set PHP_MEMORY_LIMIT=1024M
set MAX_EXECUTION_TIME=600

echo Starting priority-based queue worker at %date% %time%
echo Queue priority: ai-webhooks ^> ai-reviews ^> migration

REM Start worker with priority queue order
php -d memory_limit=%PHP_MEMORY_LIMIT% -d max_execution_time=%MAX_EXECUTION_TIME% artisan queue:work database --queue=ai-webhooks,ai-reviews,migration --timeout=180 --memory=1024 --max-jobs=100 --sleep=3 --tries=3 --rest=1 >> storage\logs\queue_worker.log 2>&1

REM Log completion
echo Queue worker completed at %date% %time% >> storage\logs\queue_worker.log

exit /b 0
