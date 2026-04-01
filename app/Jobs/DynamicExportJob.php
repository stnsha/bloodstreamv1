<?php

namespace App\Jobs;

use App\Models\ExportJob;
use App\Services\DynamicExportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class DynamicExportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600;
    public int $tries   = 1;

    private string $jobUuid;
    private string $dateFrom;
    private string $dateTo;
    private array  $masterPanelItemIds;
    private array  $columns;

    public function __construct(
        string $jobUuid,
        string $dateFrom,
        string $dateTo,
        array  $masterPanelItemIds,
        array  $columns
    ) {
        $this->jobUuid            = $jobUuid;
        $this->dateFrom           = $dateFrom;
        $this->dateTo             = $dateTo;
        $this->masterPanelItemIds = $masterPanelItemIds;
        $this->columns            = $columns;
        $this->onQueue('exports');
    }

    public function handle(DynamicExportService $service): void
    {
        ini_set('memory_limit', '1024M');

        $job = ExportJob::where('job_uuid', $this->jobUuid)->firstOrFail();
        $job->update(['status' => ExportJob::STATUS_PROCESSING]);

        try {
            $includeOctopus = !empty(array_intersect(
                $this->columns,
                ['race', 'regional', 'customer_name', 'nric', 'phone']
            ));

            Log::info('DynamicExportJob: start', [
                'job_uuid'  => $this->jobUuid,
                'date_from' => $this->dateFrom,
                'date_to'   => $this->dateTo,
                'mpi_count' => count($this->masterPanelItemIds),
                'columns'   => $this->columns,
            ]);

            // Pass null to remove the record limit — queue jobs are not bound by Apache timeout
            $result = $service->generateCsv(
                $this->dateFrom,
                $this->dateTo,
                $this->masterPanelItemIds,
                $this->columns,
                $includeOctopus,
                '',
                null
            );

            if (!empty($result['error'])) {
                $job->update([
                    'status'        => ExportJob::STATUS_FAILED,
                    'error_message' => $result['error'],
                    'completed_at'  => now(),
                ]);
                return;
            }

            $directory = storage_path('app/exports');
            if (!is_dir($directory)) {
                mkdir($directory, 0755, true);
            }

            $resultPath = $directory . DIRECTORY_SEPARATOR . $this->jobUuid . '.csv';
            file_put_contents($resultPath, base64_decode($result['csv_base64']));

            $job->update([
                'status'       => ExportJob::STATUS_COMPLETED,
                'result_path'  => $resultPath,
                'row_count'    => $result['row_count'],
                'warnings'     => json_encode($result['warnings']),
                'completed_at' => now(),
            ]);

            Log::info('DynamicExportJob: complete', [
                'job_uuid'  => $this->jobUuid,
                'row_count' => $result['row_count'],
            ]);
        } catch (Throwable $e) {
            Log::error('DynamicExportJob: exception', [
                'job_uuid' => $this->jobUuid,
                'error'    => $e->getMessage(),
            ]);

            $job->update([
                'status'        => ExportJob::STATUS_FAILED,
                'error_message' => $e->getMessage(),
                'completed_at'  => now(),
            ]);

            throw $e;
        }
    }

    public function failed(Throwable $e): void
    {
        ExportJob::where('job_uuid', $this->jobUuid)->update([
            'status'        => ExportJob::STATUS_FAILED,
            'error_message' => $e->getMessage(),
            'completed_at'  => now(),
        ]);
    }
}
