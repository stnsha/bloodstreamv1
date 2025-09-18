# System Scheduler Setup Guide

## Overview
This guide explains how to set up automated processing of blood test results using Splinterware System Scheduler and the created batch file.

## Prerequisites

### 1. Redis Server
- Must be running on localhost:6379
- See `REDIS_SETUP_NOTES.md` for Redis installation

### 2. Laravel Configuration
Update your `.env` file:
```env
CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_DB=0
```

### 3. Composer Dependencies
Ensure predis is installed:
```bash
composer require predis/predis
```

## Batch File Setup

### Location
The batch file is located at: `C:\laragon\www\blood-stream-v1\process_test_results.bat`

### What it does:
1. Changes to Laravel project directory
2. Logs start time to `scheduler.log`
3. Runs the artisan command to dispatch jobs
4. Starts a queue worker for 5 minutes to process jobs
5. Logs completion status

### Manual Testing
Before setting up the scheduler, test the batch file manually:
```bash
# Open Command Prompt as Administrator
cd C:\laragon\www\blood-stream-v1
process_test_results.bat
```

## Splinterware System Scheduler Configuration

### 1. Download & Install
- Download from: https://www.splinterware.com/products/scheduler.html
- Install and run as Administrator

### 2. Create New Task
1. Click "Add" to create new task
2. **Task Name**: "Blood Test Results Processing"
3. **Command**: `C:\laragon\www\blood-stream-v1\process_test_results.bat`
4. **Working Directory**: `C:\laragon\www\blood-stream-v1`

### 3. Schedule Settings
1. **Frequency**: Every 1 hour
2. **Start Time**: Choose appropriate time (e.g., every hour at :00)
3. **Run As**: Select "Run as Administrator" if needed
4. **Logging**: Enable logging to track execution

### 4. Advanced Options
1. **Timeout**: Set to 45 minutes (2700 seconds)
2. **Max Runtime**: 45 minutes
3. **Kill if running**: Enabled (prevents overlap)
4. **Start if missed**: Enabled

## Laravel Artisan Commands

### Manual Commands for Testing

```bash
# Test with dry run (shows what would be processed)
php artisan bloodstream:process-results --dry-run

# Process with custom batch size
php artisan bloodstream:process-results --batch-size=10 --max-results=100

# Force refresh API token
php artisan bloodstream:process-results --force-token-refresh

# Clear all caches
php artisan bloodstream:process-results --clear-cache

# Full command with all options
php artisan bloodstream:process-results --batch-size=15 --max-results=200 --force-token-refresh
```

### Queue Management

```bash
# Start queue worker manually
php artisan queue:work redis

# Monitor queue status
php artisan queue:monitor redis

# Clear failed jobs
php artisan queue:flush

# Retry failed jobs
php artisan queue:retry all
```

## Monitoring & Logs

### Log Files
1. **Scheduler Log**: `C:\laragon\www\blood-stream-v1\scheduler.log`
2. **Laravel Log**: `C:\laragon\www\blood-stream-v1\storage\logs\laravel.log`
3. **Failed Jobs**: Check `failed_jobs` table in database

### What to Monitor
1. **Job Dispatch**: Check if jobs are being created
2. **API Token**: Ensure token is being cached and refreshed
3. **Rate Limiting**: Monitor API call frequency
4. **Memory Usage**: Watch for memory leaks
5. **Processing Time**: Track batch processing duration

### Monitoring Commands
```bash
# Watch Laravel logs in real-time
tail -f storage/logs/laravel.log

# Watch scheduler logs
tail -f scheduler.log

# Check Redis keys
redis-cli keys "*"

# Monitor Redis memory
redis-cli info memory
```

## Troubleshooting

### Common Issues

**1. "Queue connection failed"**
- Check Redis server is running
- Verify `.env` configuration
- Test: `redis-cli ping`

**2. "No valid API token"**
- Run with `--force-token-refresh`
- Check external API accessibility
- Verify credentials in `ApiTokenService`

**3. "Jobs not processing"**
- Start queue worker: `php artisan queue:work redis`
- Check failed jobs table
- Review Laravel logs

**4. "Batch file not running"**
- Run as Administrator
- Check file paths in batch file
- Verify Laravel project location

**5. "Memory exceeded"**
- Reduce batch size
- Clear caches regularly
- Monitor Redis memory usage

### Performance Optimization

**1. Batch Size Tuning**
- Start with 15, adjust based on performance
- Smaller batches = more jobs, less memory
- Larger batches = fewer jobs, more memory

**2. Cache Management**
- Patient data cached for 1 hour
- API token cached for 30 days
- Clear caches if memory issues occur

**3. Rate Limiting**
- Currently 5 API calls per second
- Adjust in `ProcessTestResultBatchJob.php`
- Monitor external API response times

## Security Notes

1. **API Credentials**: Stored in `ApiTokenService.php`
2. **Database Access**: Uses Laravel's database configuration
3. **File Permissions**: Ensure proper permissions on batch file
4. **Logs**: May contain sensitive data, secure appropriately

## Maintenance

### Daily Tasks
- Monitor log files for errors
- Check queue status
- Verify processed results

### Weekly Tasks
- Clear old log files
- Monitor Redis memory usage
- Review failed job statistics

### Monthly Tasks
- Update API credentials if needed
- Review performance metrics
- Optimize batch sizes if necessary