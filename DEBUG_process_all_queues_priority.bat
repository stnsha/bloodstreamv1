@echo off
REM ============================================
REM Unified Queue Worker - Priority Processing
REM DEBUG VERSION - Comprehensive logging
REM ============================================

setlocal enabledelayedexpansion

REM Set the working directory - UPDATE PATH FOR STAGING/PRODUCTION!
cd /d "C:\xampp\htdocs\production"

REM Verify we're in the right directory
if not exist "artisan" (
    echo [%DATE% %TIME%] ERROR: Not in Laravel directory. Current: %CD%
    exit /b 1
)

REM Set PHP memory and execution limits
set PHP_MEMORY_LIMIT=1024M
set LOG_FILE=storage\logs\queue_worker.log

REM Create logs directory if it doesn't exist
if not exist "storage\logs" mkdir storage\logs

REM Log start
echo. >> %LOG_FILE%
echo ============================================ >> %LOG_FILE%
echo [%DATE% %TIME%] QUEUE WORKER STARTED >> %LOG_FILE%
echo Current Directory: %CD% >> %LOG_FILE%
echo PHP Memory Limit: %PHP_MEMORY_LIMIT% >> %LOG_FILE%
echo Queue Order: panel, ai-webhooks, ai-reviews, migration >> %LOG_FILE%
echo ============================================ >> %LOG_FILE%

REM Check database connectivity
echo [%DATE% %TIME%] Checking database connectivity... >> %LOG_FILE%
php -d memory_limit=%PHP_MEMORY_LIMIT% artisan tinker --execute="echo 'Database OK';" 2>&1 >> %LOG_FILE%
if %ERRORLEVEL% NEQ 0 (
    echo [%DATE% %TIME%] ERROR: Database connection failed - Exit code: %ERRORLEVEL% >> %LOG_FILE%
    exit /b 1
)

REM Check pending jobs count
echo [%DATE% %TIME%] Checking pending jobs... >> %LOG_FILE%
php -d memory_limit=%PHP_MEMORY_LIMIT% artisan tinker --execute="$count = DB::table('jobs')->count(); echo 'Total jobs pending: ' . $count;" 2>&1 >> %LOG_FILE%

REM Check ai_reviews queue status
echo [%DATE% %TIME%] Checking ai_reviews queue status... >> %LOG_FILE%
php -d memory_limit=%PHP_MEMORY_LIMIT% artisan tinker --execute="$count = DB::table('jobs')->where('queue', 'ai-reviews')->count(); echo 'AI Reviews queue pending: ' . $count;" 2>&1 >> %LOG_FILE%

REM Start worker - log ALL output (success + error)
echo [%DATE% %TIME%] Starting queue:work command... >> %LOG_FILE%
php -d memory_limit=%PHP_MEMORY_LIMIT% -d max_execution_time=900 artisan queue:work database ^
 --queue=panel,ai-webhooks,ai-reviews,migration ^
 --timeout=300 ^
 --memory=1024 ^
 --max-jobs=50 ^
 --max-time=600 ^
 --sleep=3 ^
 --tries=3 ^
 --rest=1 2>&1 >> %LOG_FILE%

set WORKER_EXIT_CODE=%ERRORLEVEL%

REM Log stop time and final status
echo [%DATE% %TIME%] Queue worker stopped with exit code: %WORKER_EXIT_CODE% >> %LOG_FILE%

REM Check remaining jobs
echo [%DATE% %TIME%] Checking remaining jobs after worker stopped... >> %LOG_FILE%
php -d memory_limit=%PHP_MEMORY_LIMIT% artisan tinker --execute="$count = DB::table('jobs')->count(); $aiCount = DB::table('jobs')->where('queue', 'ai-reviews')->count(); echo 'Total remaining: ' . $count . ', AI reviews queue: ' . $aiCount;" 2>&1 >> %LOG_FILE%

REM Check failed jobs
echo [%DATE% %TIME%] Checking failed jobs... >> %LOG_FILE%
php -d memory_limit=%PHP_MEMORY_LIMIT% artisan tinker --execute="$count = DB::table('failed_jobs')->where('failed_at', '>=', now()->subHour())->count(); echo 'Failed jobs in last hour: ' . $count;" 2>&1 >> %LOG_FILE%

REM Check ai_reviews table status
echo [%DATE% %TIME%] Checking ai_reviews processing status... >> %LOG_FILE%
php -d memory_limit=%PHP_MEMORY_LIMIT% artisan tinker --execute="$statuses = DB::table('ai_reviews')->where('created_at', '>=', now()->subHour())->selectRaw('processing_status, count(*) as cnt')->groupBy('processing_status')->get(); foreach($statuses as $s) { echo $s->processing_status . ': ' . $s->cnt . ' | '; }" 2>&1 >> %LOG_FILE%

echo [%DATE% %TIME%] QUEUE WORKER SESSION ENDED >> %LOG_FILE%
echo ============================================ >> %LOG_FILE%
echo. >> %LOG_FILE%

exit /b 0
