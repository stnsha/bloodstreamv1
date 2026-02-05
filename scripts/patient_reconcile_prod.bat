@echo off
REM ============================================
REM Patient Reconciliation Batch Job
REM Run via Task Scheduler every 10 minutes
REM PRODUCTION VERSION - DO NOT RUN LOCALLY
REM ============================================

REM Set working directory
cd /d "C:\xampp\htdocs\staging"

REM Log start time
echo [%date% %time%] Starting patient reconciliation batch >> storage\logs\reconcile_batch.log

REM Run the reconciliation command
REM --batch-size=10 : Process 10 patients per run
REM --lab-code=INN : Filter by Innoquest lab code
php artisan patients:find-mismatches --lab-code=INN --batch-size=10 --max-batches=1 --stop-on-mismatch=100 >> storage\logs\reconcile_batch.log 2>&1

REM Log end time
echo [%date% %time%] Completed patient reconciliation batch >> storage\logs\reconcile_batch.log
echo. >> storage\logs\reconcile_batch.log
