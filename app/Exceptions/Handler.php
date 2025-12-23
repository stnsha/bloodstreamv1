<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Support\Facades\Log;
use PDOException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of exception types with their corresponding custom log levels.
     *
     * @var array<class-string<\Throwable>, \Psr\Log\LogLevel::*>
     */
    protected $levels = [
        //
    ];

    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<\Throwable>>
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });

        // Handle database deadlocks gracefully in queue jobs
        $this->renderable(function (PDOException $e, $request) {
            if ($this->isDeadlock($e)) {
                // Log the deadlock but don't fail the job
                // Laravel's queue worker will automatically retry
                Log::warning('Database deadlock detected in queue job', [
                    'error' => $e->getMessage(),
                    'code' => $e->getCode(),
                    'trace' => $e->getTraceAsString()
                ]);

                // Return null to allow Laravel's default handling (auto-retry)
                return null;
            }
        });
    }

    /**
     * Check if the exception is a MySQL deadlock error
     */
    protected function isDeadlock(PDOException $e): bool
    {
        return $e->getCode() == 40001 ||
               str_contains($e->getMessage(), 'Deadlock found') ||
               str_contains($e->getMessage(), 'SQLSTATE[40001]');
    }
}
