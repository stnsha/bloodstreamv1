<?php

namespace App\Jobs;

use App\Models\Patient;
use App\Services\IcNormalizerService;
use App\Services\PatientMatcherService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ReconcileUnmatchedPatientsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Lab code filter (e.g., 'INN').
     */
    protected ?string $labCode;

    /**
     * Batch size for processing.
     */
    protected int $batchSize;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying.
     */
    public int $backoff = 60;

    /**
     * Create a new job instance.
     *
     * @param string|null $labCode Lab code filter
     * @param int $batchSize Number of patients to process
     */
    public function __construct(?string $labCode = null, int $batchSize = 100)
    {
        $this->labCode = $labCode;
        $this->batchSize = $batchSize;
    }

    /**
     * Execute the job.
     */
    public function handle(PatientMatcherService $matcher, IcNormalizerService $icNormalizer): void
    {
        Log::info('ReconcileUnmatchedPatientsJob: Starting', [
            'lab_code' => $this->labCode,
            'batch_size' => $this->batchSize,
        ]);

        // Get patients without confirmed customer links
        // and without pending review candidates
        $query = Patient::whereDoesntHave('customerLink')
            ->whereDoesntHave('matchCandidates', function ($q) {
                $q->where('status', 'pending_review');
            });

        // Filter by lab code if specified
        if ($this->labCode) {
            $query->whereHas('testResults', function ($q) {
                $q->where('ref_id', 'LIKE', $this->labCode . '%');
            });
        }

        // Order by latest test result first
        $query->orderByDesc(function ($q) {
            $q->select('created_at')
                ->from('test_results')
                ->whereColumn('test_results.patient_id', 'patients.id')
                ->whereNull('test_results.deleted_at')
                ->orderByDesc('created_at')
                ->limit(1);
        });

        $patients = $query->limit($this->batchSize)->get();

        Log::info('ReconcileUnmatchedPatientsJob: Found unlinked patients', [
            'count' => $patients->count(),
        ]);

        $candidatesCreated = 0;
        $noMatchFound = 0;
        $errorsEncountered = 0;

        foreach ($patients as $patient) {
            try {
                $candidates = $matcher->findMatchCandidates($patient, $this->labCode);

                foreach ($candidates as $candidateData) {
                    $matcher->createMatchCandidate($patient, $candidateData, $this->labCode);
                    $candidatesCreated++;
                }

                if ($candidates->isEmpty()) {
                    $noMatchFound++;
                }
            } catch (Exception $e) {
                $errorsEncountered++;
                Log::error('ReconcileUnmatchedPatientsJob: Error processing patient', [
                    'patient_id' => $patient->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('ReconcileUnmatchedPatientsJob: Completed', [
            'patients_processed' => $patients->count(),
            'candidates_created' => $candidatesCreated,
            'no_match_found' => $noMatchFound,
            'errors_encountered' => $errorsEncountered,
        ]);
    }

    /**
     * Handle a job failure.
     */
    public function failed(Exception $exception): void
    {
        Log::error('ReconcileUnmatchedPatientsJob: Job failed', [
            'lab_code' => $this->labCode,
            'batch_size' => $this->batchSize,
            'error' => $exception->getMessage(),
        ]);
    }
}
