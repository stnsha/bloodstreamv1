<?php

namespace App\Console\Commands;

use App\Jobs\AIReviewJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RunAIReview extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'ai:review {--direct : Run directly without queue}';

    /**
     * The console command description.
     */
    protected $description = 'Dispatch AI Review Job to process unreviewed test results';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting AI Review Process...');
        Log::info('AI Review command started');

        try {
            if ($this->option('direct')) {
                // Run directly without queue
                $this->info('Running AI Review directly...');
                Log::info('Running AI Review directly without queue');
                
                $job = new AIReviewJob();
                $result = $job->handle();
                
                $this->info('AI Review completed successfully!');
                Log::info('AI Review direct execution completed');
            } else {
                // Dispatch to queue
                $this->info('Dispatching AI Review Job to queue...');
                Log::info('Dispatching AI Review Job to ai-review queue');
                
                AIReviewJob::dispatch();
                
                $this->info('AI Review Job dispatched successfully!');
                $this->info('Check your queue worker to see job processing.');
                Log::info('AI Review Job dispatched to queue successfully');
            }
        } catch (\Exception $e) {
            $this->error('Failed to run AI Review: ' . $e->getMessage());
            Log::error('AI Review command failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }

        return 0;
    }
}