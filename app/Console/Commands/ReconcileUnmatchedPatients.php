<?php

namespace App\Console\Commands;

use App\Jobs\ReconcileUnmatchedPatientsJob;
use App\Models\Patient;
use App\Models\PatientMatchCandidate;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ReconcileUnmatchedPatients extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'patients:reconcile
                            {--lab-code= : Filter by lab code prefix (e.g., INN)}
                            {--batch-size=100 : Number of patients to process}
                            {--sync : Run synchronously instead of queuing}
                            {--stats : Show statistics only, do not process}';

    /**
     * The console command description.
     */
    protected $description = 'Find and create match candidates for patients without Octopus customer links';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $labCode = $this->option('lab-code');
        $batchSize = (int) $this->option('batch-size');
        $sync = $this->option('sync');
        $statsOnly = $this->option('stats');

        $this->info('Patient Reconciliation Tool');
        $this->info('============================');
        $this->newLine();

        // Show current statistics
        $this->showStatistics($labCode);

        if ($statsOnly) {
            return Command::SUCCESS;
        }

        $this->newLine();
        $this->info('Starting patient reconciliation...');
        $this->info("Lab code filter: " . ($labCode ?: 'All'));
        $this->info("Batch size: {$batchSize}");

        Log::info('ReconcileUnmatchedPatients: Command started', [
            'lab_code' => $labCode,
            'batch_size' => $batchSize,
            'sync' => $sync,
        ]);

        $job = new ReconcileUnmatchedPatientsJob($labCode, $batchSize);

        if ($sync) {
            $this->info('Running synchronously...');
            $this->newLine();

            dispatch_sync($job);

            $this->newLine();
            $this->info('Processing completed.');
        } else {
            $this->info('Dispatching to queue...');
            dispatch($job);
            $this->info('Job dispatched. Check logs for progress.');
        }

        // Show updated statistics
        $this->newLine();
        $this->showStatistics($labCode);

        return Command::SUCCESS;
    }

    /**
     * Show current statistics.
     */
    protected function showStatistics(?string $labCode): void
    {
        $this->info('Current Statistics:');
        $this->info('-------------------');

        // Count total patients
        $totalPatients = Patient::count();
        $this->info("Total patients: {$totalPatients}");

        // Count unlinked patients
        $unlinkedQuery = Patient::whereDoesntHave('customerLink');
        if ($labCode) {
            $unlinkedQuery->whereHas('testResults', function ($q) use ($labCode) {
                $q->where('ref_id', 'LIKE', $labCode . '%');
            });
        }
        $unlinkedPatients = $unlinkedQuery->count();
        $this->info("Unlinked patients" . ($labCode ? " ({$labCode})" : "") . ": {$unlinkedPatients}");

        // Count pending review candidates
        $pendingQuery = PatientMatchCandidate::where('status', 'pending_review');
        if ($labCode) {
            $pendingQuery->where('source_lab_code', $labCode);
        }
        $pendingReview = $pendingQuery->count();
        $this->info("Pending review candidates" . ($labCode ? " ({$labCode})" : "") . ": {$pendingReview}");

        // Count approved candidates
        $approvedQuery = PatientMatchCandidate::where('status', 'approved');
        if ($labCode) {
            $approvedQuery->where('source_lab_code', $labCode);
        }
        $approved = $approvedQuery->count();
        $this->info("Approved matches" . ($labCode ? " ({$labCode})" : "") . ": {$approved}");

        // Count rejected candidates
        $rejectedQuery = PatientMatchCandidate::where('status', 'rejected');
        if ($labCode) {
            $rejectedQuery->where('source_lab_code', $labCode);
        }
        $rejected = $rejectedQuery->count();
        $this->info("Rejected candidates" . ($labCode ? " ({$labCode})" : "") . ": {$rejected}");

        // Show confidence score distribution for pending
        if ($pendingReview > 0) {
            $this->newLine();
            $this->info('Pending candidates by confidence:');

            $highConfidence = PatientMatchCandidate::where('status', 'pending_review')
                ->where('confidence_score', '>=', 0.9)
                ->when($labCode, fn($q) => $q->where('source_lab_code', $labCode))
                ->count();
            $this->info("  High (>= 0.9): {$highConfidence}");

            $mediumConfidence = PatientMatchCandidate::where('status', 'pending_review')
                ->where('confidence_score', '>=', 0.7)
                ->where('confidence_score', '<', 0.9)
                ->when($labCode, fn($q) => $q->where('source_lab_code', $labCode))
                ->count();
            $this->info("  Medium (0.7-0.9): {$mediumConfidence}");

            $lowConfidence = PatientMatchCandidate::where('status', 'pending_review')
                ->where('confidence_score', '<', 0.7)
                ->when($labCode, fn($q) => $q->where('source_lab_code', $labCode))
                ->count();
            $this->info("  Low (< 0.7): {$lowConfidence}");
        }
    }
}
