@echo off
cd /d C:\xampp\htdocs\staging

echo Running AI Review Queue Worker... >> ai_review_log.txt
echo Started at %date% %time% >> ai_review_log.txt

C:\laragon\bin\php\php8.2\php.exe artisan queue:work --queue=ai-review,default --verbose --tries=2 --timeout=1800 --sleep=10 >> ai_review_log.txt 2>&1

echo Worker stopped at %date% %time% >> ai_review_log.txt
exit