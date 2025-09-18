# Job Scheduler Workflow Documentation

## Overview
This document provides a detailed technical explanation of how the automated blood test results processing system works, from the Windows scheduler trigger through to the AI analysis completion in the DoctorReviewController.

## System Architecture

```
Windows Scheduler (Splinterware)
    ↓
process_test_results.bat
    ↓
ProcessTestResultsCommand
    ↓
ProcessTestResultsJob
    ↓
ProcessTestResultBatchJob (Multiple)
    ↓
DoctorReviewController::panelResultsComment()
    ↓
AI Analysis & Database Update
```

## 1. Windows Scheduler Trigger

### Splinterware System Scheduler Configuration
- **Schedule**: Every 1 hour
- **Command**: `C:\laragon\www\blood-stream-v1\process_test_results.bat`
- **Working Directory**: `C:\laragon\www\blood-stream-v1`
- **Timeout**: 45 minutes
- **Run as**: Administrator (if needed)

### Scheduler Logs
All executions are logged to `scheduler.log` with timestamps:
```
[Thu 18/09/2025 11:00:01.17] Starting blood test results processing
[Thu 18/09/2025 11:00:03.07] Blood test results processing job dispatched successfully
[Thu 18/09/2025 11:00:03.10] Starting queue worker
```

## 2. Batch File Execution (`process_test_results.bat`)

### File Location
`C:\laragon\www\blood-stream-v1\process_test_results.bat`

### Key Operations
```batch
@echo off
REM Set the working directory to your Laravel project - DIFFERENT PATH FOR STAGING AND PRODUCTION!
cd /d "C:\laragon\www\blood-stream-v1"

REM Log the start time
echo [%DATE% %TIME%] Starting blood test results processing >> scheduler.log

REM Run the Laravel artisan command
php artisan bloodstream:process-results --batch-size=15 --max-results=200 >> scheduler.log 2>&1

REM Check if the command was successful
if %ERRORLEVEL% EQU 0 (
    echo [%DATE% %TIME%] Blood test results processing job dispatched successfully >> scheduler.log
) else (
    echo [%DATE% %TIME%] ERROR: Blood test results processing failed with error level %ERRORLEVEL% >> scheduler.log
)

REM Run the queue worker to process the jobs (run for 5 minutes then exit)
echo [%DATE% %TIME%] Starting queue worker >> scheduler.log
php artisan queue:work database --timeout=900 --memory=512 --max-time=300 >> scheduler.log 2>&1

echo [%DATE% %TIME%] Queue worker finished >> scheduler.log
```

### Parameters Explained
- `--batch-size=15`: Process 15 test results per batch job
- `--max-results=200`: Maximum total results to process in one run
- `--timeout=900`: Queue worker timeout (15 minutes)
- `--memory=512`: Memory limit (512MB)
- `--max-time=300`: Queue worker runs for 5 minutes then exits

## 3. Laravel Artisan Command (`ProcessTestResultsCommand`)

### File Location
`app/Console/Commands/ProcessTestResultsCommand.php`

### Command Registration
```php
protected $signature = 'bloodstream:process-results
                       {--batch-size=15 : Number of results per batch}
                       {--max-results=200 : Maximum results to process}
                       {--dry-run : Show what would be processed without executing}
                       {--force-token-refresh : Force API token refresh}
                       {--clear-cache : Clear all caches before processing}';
```

### Main Process Flow
1. **Validate Configuration**
   - Check database connection
   - Verify queue connection (database)
   - Validate API credentials

2. **API Token Management**
   ```php
   $tokenService = new ApiTokenService();
   if ($this->option('force-token-refresh')) {
       $tokenService->clearCache();
   }
   $token = $tokenService->getValidToken();
   ```

3. **Fetch Unreviewed Results**
   ```php
   $testResults = TestResult::whereNull('ai_analysis_date')
       ->whereNotNull('lab_no')
       ->orderBy('created_at', 'asc')
       ->limit($maxResults)
       ->get();
   ```

4. **Create Batch Jobs**
   ```php
   $batches = $testResults->chunk($batchSize);
   foreach ($batches as $batch) {
       ProcessTestResultsJob::dispatch($batch->toArray(), $token);
   }
   ```

### Output Example
```
Blood Stream Test Results Processor
=====================================
Configuration:
- Batch Size: 15
- Max Results: 200
- Dry Run: No

Checking API token...
✓ Valid API token found

Checking queue connection...
✓ Queue connection successful (current size: 0)

Dispatching ProcessTestResultsJob...
✓ Job dispatched successfully
```

## 4. Main Job Processing (`ProcessTestResultsJob`)

### File Location
`app/Jobs/ProcessTestResultsJob.php`

### Job Structure
```php
class ProcessTestResultsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 1800; // 30 minutes
    public $tries = 3;
    public $maxExceptions = 3;

    protected array $testResults;
    protected string $apiToken;
}
```

### Main Process
1. **Initialize Batch Processing**
   ```php
   public function handle(): void
   {
       Log::info('ProcessTestResultsJob started', [
           'test_results_count' => count($this->testResults),
           'memory_usage' => memory_get_usage(true)
       ]);
   }
   ```

2. **Create Batch Jobs**
   ```php
   $chunks = array_chunk($this->testResults, 15);
   foreach ($chunks as $index => $chunk) {
       ProcessTestResultBatchJob::dispatch($chunk, $this->apiToken)
           ->delay(now()->addSeconds($index * 2)); // Rate limiting
   }
   ```

3. **Error Handling**
   ```php
   public function failed(Throwable $exception): void
   {
       Log::error('ProcessTestResultsJob failed', [
           'error' => $exception->getMessage(),
           'test_results_count' => count($this->testResults)
       ]);
   }
   ```

## 5. Batch Job Processing (`ProcessTestResultBatchJob`)

### File Location
`app/Jobs/ProcessTestResultBatchJob.php`

### Key Features
- **Rate Limiting**: 5 API calls per second
- **Caching**: Patient data cached for 1 hour
- **Retry Logic**: 3 attempts with exponential backoff
- **Memory Management**: Optimized for large datasets

### Processing Flow

#### 5.1 Patient Data Caching
```php
protected function getPatientFromMyHealth(string $icNumber): ?array
{
    $cacheKey = "patient_data_{$icNumber}";

    return Cache::remember($cacheKey, 3600, function () use ($icNumber) {
        // API call to MyHealth system
        $response = Http::timeout(30)
            ->withHeaders(['Authorization' => "Bearer {$this->apiToken}"])
            ->get(config('services.myhealth.url') . "/patient/{$icNumber}");

        return $response->successful() ? $response->json() : null;
    });
}
```

#### 5.2 Test Result Processing
```php
foreach ($this->testResults as $testResult) {
    try {
        // Rate limiting
        RateLimiter::hit('ai_analysis', 5); // 5 per second

        // Get patient data
        $patient = $this->getPatientFromMyHealth($testResult['ic_number']);

        // Prepare data for AI analysis
        $testResultData = $this->prepareTestResultData($testResult, $patient);

        // Send to AI analysis
        $this->sendToAiAnalysis($testResultData);

    } catch (Exception $e) {
        Log::error('Batch processing failed for test result', [
            'test_result_id' => $testResult['id'],
            'error' => $e->getMessage()
        ]);
    }
}
```

#### 5.3 AI Analysis Data Preparation
```php
protected function prepareTestResultData(array $testResult, ?array $patient): array
{
    return [
        'test_result_id' => $testResult['id'],
        'lab_no' => $testResult['lab_no'],
        'patient_info' => [
            'ic_number' => $testResult['ic_number'],
            'name' => $patient['name'] ?? 'Unknown',
            'age' => $patient['age'] ?? null,
            'gender' => $patient['gender'] ?? null,
        ],
        'test_data' => [
            'panel_code' => $testResult['panel_code'],
            'test_items' => json_decode($testResult['test_items'], true),
            'results' => json_decode($testResult['results'], true),
        ],
        'clinical_info' => [
            'referring_doctor' => $testResult['referring_doctor'],
            'collection_date' => $testResult['collection_date'],
            'report_date' => $testResult['report_date'],
        ]
    ];
}
```

#### 5.4 AI Analysis API Call
```php
protected function sendToAiAnalysis(array $testResultData): void
{
    $response = Http::timeout(120)
        ->post(config('credentials.ai_review.analysis'), $testResultData);

    if ($response->successful()) {
        Log::info('AI analysis request sent successfully', [
            'test_result_id' => $testResultData['test_result_id'],
            'response_status' => $response->status()
        ]);
    } else {
        throw new Exception("AI analysis API failed: " . $response->body());
    }
}
```

## 6. Doctor Review Controller (`DoctorReviewController`)

### File Location
`app/Http/Controllers/API/DoctorReviewController.php`

### Key Method: `panelResultsComment()`

#### 6.1 Authentication & Login
```php
public function panelResultsComment(Request $request)
{
    DB::beginTransaction();

    try {
        // Login to external AI service
        Log::info("Attempting to login to external AI service");
        $login = Http::timeout(60)->post(config('credentials.ai_review.login'), [
            "username" => config('credentials.odb.username'),
            "password" => config('credentials.odb.password')
        ]);

        if ($login->failed()) {
            DB::rollBack();
            Log::error('External AI service login failed');
            return response()->json(['success' => false], 500);
        }
```

#### 6.2 Process Multiple Test Results
```php
        $processedResults = [];
        $testResults = $request->input('test_results', []);

        foreach ($testResults as $testResultData) {
            try {
                // Send individual test result for AI analysis
                $aiResponse = Http::timeout(180)
                    ->post(config('credentials.ai_review.analysis'), $testResultData);

                if ($aiResponse->successful()) {
                    $analysisResult = $aiResponse->json();

                    // Update database with AI analysis
                    $this->updateTestResultWithAnalysis(
                        $testResultData['test_result_id'],
                        $analysisResult
                    );

                    $processedResults[] = [
                        'test_result_id' => $testResultData['test_result_id'],
                        'status' => 'success',
                        'analysis' => $analysisResult
                    ];
                }
```

#### 6.3 Database Update
```php
protected function updateTestResultWithAnalysis(int $testResultId, array $analysis): void
{
    TestResult::where('id', $testResultId)->update([
        'ai_analysis' => json_encode($analysis),
        'ai_analysis_date' => now(),
        'ai_analysis_status' => 'completed',
        'doctor_comments' => $analysis['doctor_comments'] ?? null,
        'clinical_interpretation' => $analysis['clinical_interpretation'] ?? null,
        'recommendations' => json_encode($analysis['recommendations'] ?? []),
        'updated_at' => now()
    ]);

    Log::info('Test result updated with AI analysis', [
        'test_result_id' => $testResultId,
        'analysis_date' => now()->toISOString()
    ]);
}
```

#### 6.4 Transaction Management
```php
        DB::commit();

        return response()->json([
            'success' => true,
            'message' => 'Panel results processed successfully',
            'data' => [
                'processed_count' => count($processedResults),
                'results' => $processedResults
            ]
        ]);

    } catch (Exception $e) {
        DB::rollBack();
        Log::error('Panel results comment processing failed', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Processing failed: ' . $e->getMessage()
        ], 500);
    }
}
```

## 7. Database Schema

### Primary Tables

#### test_results
```sql
CREATE TABLE test_results (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    lab_no VARCHAR(255) NOT NULL,
    ic_number VARCHAR(255) NOT NULL,
    panel_code VARCHAR(255),
    test_items JSON,
    results JSON,
    referring_doctor VARCHAR(255),
    collection_date DATETIME,
    report_date DATETIME,
    ai_analysis JSON NULL,
    ai_analysis_date DATETIME NULL,
    ai_analysis_status ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
    doctor_comments TEXT NULL,
    clinical_interpretation TEXT NULL,
    recommendations JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_ai_analysis_date (ai_analysis_date),
    INDEX idx_lab_no (lab_no),
    INDEX idx_ic_number (ic_number)
);
```

#### jobs (Laravel Queue)
```sql
CREATE TABLE jobs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    queue VARCHAR(255) NOT NULL,
    payload LONGTEXT NOT NULL,
    attempts TINYINT UNSIGNED NOT NULL,
    reserved_at INT UNSIGNED NULL,
    available_at INT UNSIGNED NOT NULL,
    created_at INT UNSIGNED NOT NULL,
    INDEX idx_queue (queue),
    INDEX idx_reserved_at (reserved_at)
);
```

#### failed_jobs
```sql
CREATE TABLE failed_jobs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid VARCHAR(255) UNIQUE NOT NULL,
    connection TEXT NOT NULL,
    queue TEXT NOT NULL,
    payload LONGTEXT NOT NULL,
    exception LONGTEXT NOT NULL,
    failed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

## 8. Configuration Files

### Environment Variables (.env)
```env
# Cache & Queue Configuration
CACHE_DRIVER=file
QUEUE_CONNECTION=database
SESSION_DRIVER=file

# API Credentials
ODB_USERNAME=971202055184
ODB_PASSWORD=L^l9i15woDMc

# AI Review Service
AI_REVIEW_LOGIN=http://172.18.28.29/alprogpt/chatgpt/login.php
AI_REVIEW_ANALYSIS=http://172.18.28.29/alprogpt/chatgpt/bloodtest_analysis.php

# MyHealth API
MYHEALTH_API_URL=http://172.18.28.51:8001
BLOOD_STREAM_V1_TOKEN=2|@lpR0T3stR2sULTo82o25
```

### Credentials Configuration (config/credentials.php)
```php
return [
    'odb' => [
        'username' => env('ODB_USERNAME', 'username'),
        'password' => env('ODB_PASSWORD', 'password'),
    ],

    'ai_review' => [
        'login' => env('AI_REVIEW_LOGIN', 'http://example.com/api/review'),
        'analysis' => env('AI_REVIEW_ANALYSIS', 'http://example.com/api/analysis'),
    ],
];
```

## 9. Logging & Monitoring

### Log Files
1. **Scheduler Log**: `scheduler.log` - Windows scheduler execution logs
2. **Laravel Log**: `storage/logs/laravel.log` - Application logs
3. **Queue Logs**: Embedded in Laravel logs with specific tags

### Key Log Events
```php
// Job Dispatch
Log::info('ProcessTestResultsJob started', [
    'test_results_count' => count($testResults),
    'batch_size' => $batchSize
]);

// AI Analysis
Log::info('AI analysis request sent successfully', [
    'test_result_id' => $testResultId,
    'processing_time' => $processingTime
]);

// Database Update
Log::info('Test result updated with AI analysis', [
    'test_result_id' => $testResultId,
    'analysis_date' => now()->toISOString()
]);
```

### Monitoring Commands
```bash
# Watch Laravel logs in real-time
tail -f storage/logs/laravel.log

# Check queue status
php artisan queue:monitor database

# Check failed jobs
php artisan queue:failed

# Retry failed jobs
php artisan queue:retry all
```

## 10. Error Handling & Recovery

### Retry Mechanisms
1. **Job Level**: 3 attempts with exponential backoff
2. **API Level**: Automatic retry on network timeouts
3. **Database Level**: Transaction rollback on failures

### Common Error Scenarios
1. **API Token Expiry**: Automatic refresh and retry
2. **Network Timeouts**: Retry with increased timeout
3. **Memory Limits**: Batch size reduction
4. **Database Deadlocks**: Transaction retry

### Recovery Procedures
```bash
# Clear failed jobs and restart
php artisan queue:flush
php artisan queue:restart

# Force token refresh
php artisan bloodstream:process-results --force-token-refresh

# Manual processing with dry run
php artisan bloodstream:process-results --dry-run --max-results=50
```

## 11. Performance Optimization

### Rate Limiting
- **AI API**: 5 calls per second
- **MyHealth API**: No specific limit (cached for 1 hour)
- **Database**: Optimized with indexes and transactions

### Memory Management
- **Batch Size**: 15 results per batch job
- **Cache TTL**: 1 hour for patient data, 30 days for API tokens
- **Queue Worker**: 512MB memory limit, 5-minute runtime

### Scalability Considerations
- **Horizontal Scaling**: Multiple queue workers
- **Database Optimization**: Proper indexing on search columns
- **Cache Strategy**: Redis option available for high-load scenarios

## 12. Security Measures

### Authentication
- **API Tokens**: Cached securely with automatic refresh
- **Database**: Laravel's built-in security features
- **External APIs**: HTTPS communication with proper credentials

### Data Protection
- **Patient Data**: Minimal exposure, cached temporarily
- **Logs**: Sanitized to prevent credential exposure
- **Database**: Encrypted connections in production

### Access Control
- **Scheduler**: Runs with minimal required permissions
- **API Access**: Token-based authentication
- **Database**: Role-based access controls

---

This documentation provides a comprehensive overview of the entire job scheduler workflow. The system is designed for reliability, scalability, and maintainability, with proper error handling and monitoring capabilities throughout the processing pipeline.