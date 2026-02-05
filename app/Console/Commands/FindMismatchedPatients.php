<?php

namespace App\Console\Commands;

use App\Models\Patient;
use App\Models\PatientMatchCandidate;
use App\Services\IcNormalizerService;
use App\Services\PatientMatcherService;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class FindMismatchedPatients extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'patients:find-mismatches
                            {--lab-code= : Filter by lab code prefix (e.g., INN)}
                            {--batch-size=50 : Number of patients to process per batch}
                            {--max-batches=100 : Maximum number of batches to run}
                            {--stop-on-mismatch=5 : Stop after finding this many mismatches}
                            {--min-confidence=0.99 : Consider mismatch if confidence below this}';

    /**
     * The console command description.
     */
    protected $description = 'Process patients until finding actual IC/RefID mismatches (confidence < 1.0)';

    /**
     * Execute the console command.
     */
    public function handle(PatientMatcherService $matcher, IcNormalizerService $icNormalizer): int
    {
        $labCode = $this->option('lab-code');
        $batchSize = (int) $this->option('batch-size');
        $maxBatches = (int) $this->option('max-batches');
        $stopOnMismatch = (int) $this->option('stop-on-mismatch');
        $minConfidence = (float) $this->option('min-confidence');

        $this->info('Find Mismatched Patients Tool');
        $this->info('==============================');
        $this->info("Lab code: " . ($labCode ?: 'All'));
        $this->info("Batch size: {$batchSize}");
        $this->info("Max batches: {$maxBatches}");
        $this->info("Stop after {$stopOnMismatch} mismatches (confidence < {$minConfidence})");
        $this->newLine();

        Log::info('FindMismatchedPatients: Starting', [
            'lab_code' => $labCode,
            'batch_size' => $batchSize,
            'max_batches' => $maxBatches,
            'stop_on_mismatch' => $stopOnMismatch,
            'min_confidence' => $minConfidence,
        ]);

        $totalProcessed = 0;
        $totalExactMatches = 0;
        $totalMismatches = 0;
        $totalNoMatch = 0;
        $totalErrors = 0;
        $mismatchDetails = [];

        for ($batch = 1; $batch <= $maxBatches; $batch++) {
            $this->info("Processing batch {$batch}...");

            // Get patients without confirmed customer links and without pending candidates
            $query = Patient::whereDoesntHave('customerLink')
                ->whereDoesntHave('matchCandidates', function ($q) {
                    $q->where('status', 'pending_review');
                });

            if ($labCode) {
                $query->whereHas('testResults', function ($q) use ($labCode) {
                    $q->whereHas('doctor', function ($dq) use ($labCode) {
                        $dq->whereHas('lab', function ($lq) use ($labCode) {
                            $lq->where('code', $labCode);
                        });
                    });
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

            $patients = $query->limit($batchSize)->get();

            if ($patients->isEmpty()) {
                $this->warn("No more patients to process.");
                break;
            }

            foreach ($patients as $patient) {
                $totalProcessed++;

                try {
                    $candidates = $matcher->findMatchCandidates($patient, $labCode);

                    if ($candidates->isEmpty()) {
                        $totalNoMatch++;
                        $this->line("  [{$patient->id}] {$patient->icno} - No match found");
                        continue;
                    }

                    $topCandidate = $candidates->first();
                    $confidence = $topCandidate['confidence_score'];

                    // Create the candidate record
                    $matcher->createMatchCandidate($patient, $topCandidate, $labCode);

                    if ($confidence >= $minConfidence) {
                        $totalExactMatches++;
                        $this->line("  [{$patient->id}] {$patient->icno} - Exact match (confidence: {$confidence})");
                    } else {
                        $totalMismatches++;
                        $this->warn("  [{$patient->id}] {$patient->icno} - MISMATCH FOUND (confidence: {$confidence})");
                        $this->warn("    -> Candidate IC: {$topCandidate['candidate_ic']}");
                        $this->warn("    -> Method: {$topCandidate['ic_match_method']}");

                        $mismatchDetails[] = [
                            'patient_id' => $patient->id,
                            'source_ic' => $patient->icno,
                            'candidate_ic' => $topCandidate['candidate_ic'],
                            'confidence' => $confidence,
                            'method' => $topCandidate['ic_match_method'],
                        ];

                        Log::warning('FindMismatchedPatients: Mismatch found', [
                            'patient_id' => $patient->id,
                            'source_ic' => $patient->icno,
                            'candidate_ic' => $topCandidate['candidate_ic'],
                            'confidence' => $confidence,
                            'ic_match_method' => $topCandidate['ic_match_method'],
                        ]);

                        if ($totalMismatches >= $stopOnMismatch) {
                            $this->newLine();
                            $this->info("Reached {$stopOnMismatch} mismatches. Stopping.");
                            break 2;
                        }
                    }
                } catch (Exception $e) {
                    $totalErrors++;
                    $this->error("  [{$patient->id}] {$patient->icno} - Error: {$e->getMessage()}");
                }
            }

            $this->newLine();
        }

        // Summary
        $this->newLine();
        $this->info('=== SUMMARY ===');
        $this->info("Total processed: {$totalProcessed}");
        $this->info("Exact matches: {$totalExactMatches}");
        $this->info("Mismatches found: {$totalMismatches}");
        $this->info("No match: {$totalNoMatch}");
        $this->info("Errors: {$totalErrors}");

        if (!empty($mismatchDetails)) {
            $this->newLine();
            $this->info('=== MISMATCH DETAILS ===');
            $this->table(
                ['Patient ID', 'Source IC', 'Candidate IC', 'Confidence', 'Method'],
                array_map(function ($m) {
                    return [
                        $m['patient_id'],
                        $m['source_ic'],
                        $m['candidate_ic'],
                        $m['confidence'],
                        $m['method'],
                    ];
                }, $mismatchDetails)
            );
        }

        Log::info('FindMismatchedPatients: Completed', [
            'total_processed' => $totalProcessed,
            'exact_matches' => $totalExactMatches,
            'mismatches' => $totalMismatches,
            'no_match' => $totalNoMatch,
            'errors' => $totalErrors,
        ]);

        return Command::SUCCESS;
    }
}
