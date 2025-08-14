<?php

namespace App\Jobs;

use App\Http\Controllers\ImportController;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Exception;

class ProcessCsvFilesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public $tries = 2;

    /**
     * The maximum number of seconds the job can run before timing out.
     */
    public $timeout = 1800; // 30 minutes for large files

    /**
     * The number of seconds to wait before retrying the job.
     */
    public $backoff = 300; // 5 minutes between retries

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            Log::info('ProcessCsvFilesJob started');
            
            // Create instance of ImportController and call files() method
            $importController = new ImportController();
            $result = $importController->files();
            
            // Log the result for monitoring
            if ($result->getStatusCode() === 200) {
                $data = json_decode($result->getContent(), true);
                Log::info('ProcessCsvFilesJob completed successfully', [
                    'total_csv_files' => $data['total_csv_files'] ?? 0,
                    'message' => $data['message'] ?? 'No message'
                ]);
            } else {
                $data = json_decode($result->getContent(), true);
                Log::warning('ProcessCsvFilesJob completed with issues', [
                    'status_code' => $result->getStatusCode(),
                    'message' => $data['message'] ?? 'No message'
                ]);
            }
            
        } catch (Exception $e) {
            Log::error('ProcessCsvFilesJob failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Re-throw the exception to mark the job as failed
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(Exception $exception): void
    {
        Log::error('ProcessCsvFilesJob failed permanently', [
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts()
        ]);
    }
}
