<?php

namespace App\Services;

use App\Models\Patient;
use App\Models\PatientMatchAuditLog;
use App\Models\PatientMatchCandidate;
use App\Models\PatientCustomerLink;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PatientMatcherService
{
    /**
     * Minimum confidence score to create a candidate for review.
     */
    const THRESHOLD_MINIMUM = 0.50;

    /**
     * Weight configuration for scoring.
     */
    const WEIGHTS = [
        'ic' => 0.45,
        'refid' => 0.25,
        'dob' => 0.20,
        'gender' => 0.10,
    ];

    /**
     * Adjusted weights when DOB is invalid (0000-00-00).
     */
    const WEIGHTS_NO_DOB = [
        'ic' => 0.50,
        'refid' => 0.35,
        'dob' => 0.00,
        'gender' => 0.15,
    ];

    /**
     * Current algorithm version.
     */
    const ALGORITHM_VERSION = '1.0';

    protected IcNormalizerService $icNormalizer;
    protected RefIdNormalizerService $refIdNormalizer;
    protected OctopusApiService $octopusApi;

    public function __construct(
        IcNormalizerService $icNormalizer,
        RefIdNormalizerService $refIdNormalizer,
        OctopusApiService $octopusApi
    ) {
        $this->icNormalizer = $icNormalizer;
        $this->refIdNormalizer = $refIdNormalizer;
        $this->octopusApi = $octopusApi;
    }

    /**
     * Find and score match candidates for a patient.
     *
     * @param Patient $patient The patient to find matches for
     * @param string|null $labCode The lab code filter (e.g., 'INN')
     * @return Collection Scored candidates above threshold
     */
    public function findMatchCandidates(Patient $patient, ?string $labCode = null): Collection
    {
        Log::info('PatientMatcherService: Finding candidates', [
            'patient_id' => $patient->id,
            'ic' => $patient->icno,
            'lab_code' => $labCode,
        ]);

        // Normalize input
        $normalizedIc = $this->icNormalizer->normalize($patient->icno);

        // Get refid from test results
        $refId = $patient->testResults()
            ->whereNotNull('ref_id')
            ->value('ref_id');

        $normalizedRefId = null;
        if ($refId) {
            $normalizedRefId = $this->refIdNormalizer->normalize($refId);
        }

        try {
            // Call Octopus fuzzy search API
            $response = $this->octopusApi->fuzzySearch([
                'ic' => $patient->icno,
                'ic_normalized' => $normalizedIc,
                'dob' => $patient->dob,
                'gender' => $patient->gender,
                'refid' => $normalizedRefId,
                'lab_code' => $labCode,
            ]);

            if ($response['exact_match']) {
                // Exact match found - still needs review but high confidence
                $exactCandidate = $this->scoreCandidate($patient, $response['exact_match'], $labCode, true);

                $this->logMatchAttempt($patient, collect([$exactCandidate]), 'candidates_found');

                return collect([$exactCandidate]);
            }

            // Score each candidate
            $scoredCandidates = collect($response['candidates'])
                ->map(fn($candidate) => $this->scoreCandidate($patient, $candidate, $labCode, false))
                ->filter(fn($candidate) => $candidate['confidence_score'] >= self::THRESHOLD_MINIMUM)
                ->sortByDesc('confidence_score')
                ->values();

            // Log the attempt
            $action = $scoredCandidates->isEmpty() ? 'no_candidates_found' : 'candidates_found';
            $this->logMatchAttempt($patient, $scoredCandidates, $action);

            return $scoredCandidates;
        } catch (Exception $e) {
            Log::error('PatientMatcherService: Error finding candidates', [
                'patient_id' => $patient->id,
                'error' => $e->getMessage(),
            ]);

            $this->logMatchAttempt($patient, collect(), 'match_attempted', [
                'error' => $e->getMessage(),
            ]);

            return collect();
        }
    }

    /**
     * Score a single candidate against the patient.
     *
     * @param Patient $patient The patient
     * @param array $candidate The candidate data from Octopus
     * @param string|null $labCode The lab code
     * @param bool $isExact Whether this is an exact IC match
     * @return array Scored candidate data
     */
    protected function scoreCandidate(Patient $patient, array $candidate, ?string $labCode, bool $isExact): array
    {
        $scores = [];
        $methods = [];

        // Determine if DOB is valid
        $dobValid = $this->isDobValid($patient->dob);
        $weights = $dobValid ? self::WEIGHTS : self::WEIGHTS_NO_DOB;

        // IC Score
        $icResult = $this->calculateIcScore(
            $patient->icno,
            $candidate['ic'],
            $dobValid ? $patient->dob : null
        );
        $scores['ic'] = $icResult['score'];
        $methods['ic'] = $icResult['method'];

        // RefID Score
        $patientRefId = $patient->testResults()->whereNotNull('ref_id')->value('ref_id');
        $candidateRefId = $candidate['refid'] ?? null;

        if ($patientRefId && $candidateRefId) {
            $refIdResult = $this->calculateRefIdScore($patientRefId, $candidateRefId);
            $scores['refid'] = $refIdResult['score'];
            $methods['refid'] = $refIdResult['method'];
        } else {
            $scores['refid'] = 0;
            $methods['refid'] = 'not_available';
        }

        // DOB Score
        if ($dobValid) {
            $scores['dob'] = $this->calculateDobScore($patient->dob, $candidate['birth_date'] ?? null);
        } else {
            $scores['dob'] = 0.5; // Neutral when invalid
        }

        // Gender Score
        $scores['gender'] = $this->calculateGenderScore($patient->gender, $candidate['gender'] ?? null);

        // Calculate weighted confidence
        $confidence = 0;
        foreach ($weights as $field => $weight) {
            $confidence += ($scores[$field] ?? 0) * $weight;
        }

        // Boost for exact match
        if ($isExact) {
            $confidence = 1.0;
            $methods['ic'] = 'exact';
        }

        return [
            'candidate_customer_id' => $candidate['customer_id'],
            'candidate_ic' => $candidate['ic'],
            'candidate_name' => $candidate['customer_name'] ?? null,
            'candidate_dob' => $candidate['birth_date'] ?? null,
            'candidate_gender' => $candidate['gender'] ?? null,
            'candidate_refid' => $candidate['refid'] ?? null,
            'ic_score' => round($scores['ic'], 4),
            'ic_match_method' => $methods['ic'],
            'refid_score' => round($scores['refid'], 4),
            'refid_match_method' => $methods['refid'] ?? null,
            'dob_score' => round($scores['dob'], 4),
            'gender_score' => round($scores['gender'], 4),
            'confidence_score' => round($confidence, 4),
            'weights_used' => $dobValid ? 'standard' : 'no_dob',
        ];
    }

    /**
     * Calculate IC similarity score.
     *
     * @param string $sourceIc Source IC from MyHealth
     * @param string $targetIc Target IC from Octopus
     * @param string|null $sourceDob Source DOB for validation
     * @return array Score and method
     */
    protected function calculateIcScore(string $sourceIc, string $targetIc, ?string $sourceDob): array
    {
        $normalizedSource = $this->icNormalizer->normalize($sourceIc);
        $normalizedTarget = $this->icNormalizer->normalize($targetIc);

        // Exact normalized match
        if ($normalizedSource === $normalizedTarget) {
            return ['score' => 1.0, 'method' => 'normalized_exact'];
        }

        // DOB prefix validation (for Malaysian NRIC)
        $dobPrefixMatch = false;
        if ($sourceDob && $this->icNormalizer->validateDobPrefix($targetIc, $sourceDob)) {
            $dobPrefixMatch = true;
        }

        // Levenshtein similarity
        $distance = levenshtein($normalizedSource, $normalizedTarget);
        $maxLen = max(strlen($normalizedSource), strlen($normalizedTarget));
        $similarity = $maxLen > 0 ? 1 - ($distance / $maxLen) : 0;

        // If DOB prefix matches, boost score for remaining digits
        if ($dobPrefixMatch) {
            // Compare only last 6 digits (state code + sequence)
            $sourceSuffix = substr($normalizedSource, 6);
            $targetSuffix = substr($normalizedTarget, 6);
            $suffixDistance = levenshtein($sourceSuffix, $targetSuffix);
            $suffixMaxLen = max(strlen($sourceSuffix), strlen($targetSuffix));
            $suffixSimilarity = $suffixMaxLen > 0 ? 1 - ($suffixDistance / $suffixMaxLen) : 0;

            // Weighted: DOB prefix match gives base 0.5, suffix similarity adds up to 0.5
            $score = 0.5 + ($suffixSimilarity * 0.5);
            return ['score' => $score, 'method' => 'dob_prefix_match'];
        }

        return ['score' => $similarity, 'method' => 'levenshtein'];
    }

    /**
     * Calculate RefID similarity score.
     *
     * @param string $sourceRefId Source RefID from MyHealth
     * @param string $targetRefId Target RefID from Octopus
     * @return array Score and method
     */
    protected function calculateRefIdScore(string $sourceRefId, string $targetRefId): array
    {
        $normalizedSource = $this->refIdNormalizer->normalize($sourceRefId);
        $normalizedTarget = $this->refIdNormalizer->normalize($targetRefId);

        // Exact normalized match
        if ($normalizedSource === $normalizedTarget) {
            return ['score' => 1.0, 'method' => 'normalized_exact'];
        }

        // Levenshtein similarity
        $distance = levenshtein($normalizedSource, $normalizedTarget);
        $maxLen = max(strlen($normalizedSource), strlen($normalizedTarget));
        $similarity = $maxLen > 0 ? 1 - ($distance / $maxLen) : 0;

        return ['score' => $similarity, 'method' => 'levenshtein'];
    }

    /**
     * Calculate DOB score.
     *
     * @param string|null $sourceDob Source DOB from MyHealth
     * @param string|null $targetDob Target DOB from Octopus
     * @return float Score 0.0 to 1.0
     */
    protected function calculateDobScore(?string $sourceDob, ?string $targetDob): float
    {
        if (!$sourceDob || !$targetDob) {
            return 0.5; // Neutral
        }

        // Normalize both to YYYY-MM-DD
        $sourceNorm = $this->normalizeDob($sourceDob);
        $targetNorm = $this->normalizeDob($targetDob);

        if ($sourceNorm === $targetNorm) {
            return 1.0;
        }

        return 0.0;
    }

    /**
     * Calculate gender score.
     *
     * @param string|null $sourceGender Source gender from MyHealth
     * @param string|null $targetGender Target gender from Octopus
     * @return float Score 0.0 to 1.0
     */
    protected function calculateGenderScore(?string $sourceGender, ?string $targetGender): float
    {
        if (!$sourceGender || !$targetGender) {
            return 0.5; // Neutral
        }

        return strtoupper($sourceGender) === strtoupper($targetGender) ? 1.0 : 0.0;
    }

    /**
     * Check if DOB is valid (not 0000-00-00 or empty).
     *
     * @param string|null $dob The DOB to check
     * @return bool True if valid
     */
    protected function isDobValid(?string $dob): bool
    {
        if (!$dob) {
            return false;
        }

        $invalidValues = ['0000-00-00', '00000000', '0000/00/00', ''];
        return !in_array($dob, $invalidValues, true);
    }

    /**
     * Normalize DOB to YYYY-MM-DD format.
     *
     * @param string $dob The DOB to normalize
     * @return string Normalized DOB
     */
    protected function normalizeDob(string $dob): string
    {
        // Handle YYYYMMDD format
        if (preg_match('/^\d{8}$/', $dob)) {
            return substr($dob, 0, 4) . '-' . substr($dob, 4, 2) . '-' . substr($dob, 6, 2);
        }

        // Already in YYYY-MM-DD or similar
        $timestamp = strtotime($dob);
        if ($timestamp === false) {
            return $dob;
        }

        return date('Y-m-d', $timestamp);
    }

    /**
     * Create a match candidate record.
     * If confidence is 100% (1.0), auto-approve and update related records.
     *
     * @param Patient $patient The patient
     * @param array $candidateData Scored candidate data
     * @param string|null $labCode The lab code
     * @return PatientMatchCandidate The created record
     */
    public function createMatchCandidate(Patient $patient, array $candidateData, ?string $labCode = null): PatientMatchCandidate
    {
        $isExactMatch = $candidateData['confidence_score'] >= 1.0;

        Log::info('PatientMatcherService: Creating match candidate', [
            'patient_id' => $patient->id,
            'candidate_customer_id' => $candidateData['candidate_customer_id'],
            'confidence_score' => $candidateData['confidence_score'],
            'is_exact_match' => $isExactMatch,
        ]);

        $refId = $patient->testResults()->whereNotNull('ref_id')->value('ref_id');

        try {
            DB::beginTransaction();

            // Create candidate record
            $candidate = PatientMatchCandidate::create([
                'patient_id' => $patient->id,
                'source_ic' => $patient->icno,
                'source_ic_normalized' => $this->icNormalizer->normalize($patient->icno),
                'source_refid' => $refId,
                'source_refid_normalized' => $refId ? $this->refIdNormalizer->normalize($refId) : null,
                'source_name' => $patient->name,
                'source_dob' => $patient->dob,
                'source_gender' => $patient->gender,
                'source_lab_code' => $labCode,
                'candidate_customer_id' => $candidateData['candidate_customer_id'],
                'candidate_ic' => $candidateData['candidate_ic'],
                'candidate_name' => $candidateData['candidate_name'],
                'candidate_dob' => $candidateData['candidate_dob'],
                'candidate_gender' => $candidateData['candidate_gender'],
                'candidate_refid' => $candidateData['candidate_refid'],
                'ic_score' => $candidateData['ic_score'],
                'ic_match_method' => $candidateData['ic_match_method'],
                'refid_score' => $candidateData['refid_score'],
                'refid_match_method' => $candidateData['refid_match_method'],
                'dob_score' => $candidateData['dob_score'],
                'gender_score' => $candidateData['gender_score'],
                'confidence_score' => $candidateData['confidence_score'],
                'status' => $isExactMatch
                    ? PatientMatchCandidate::STATUS_APPROVED
                    : PatientMatchCandidate::STATUS_PENDING_REVIEW,
                'reviewed_at' => $isExactMatch ? now() : null,
                'review_notes' => $isExactMatch ? 'Auto-approved: 100% confidence match' : null,
                'match_algorithm_version' => self::ALGORITHM_VERSION,
            ]);

            // Auto-approve exact matches
            if ($isExactMatch) {
                $this->autoApproveExactMatch($patient, $candidate, $candidateData, $labCode);
            }

            DB::commit();

            return $candidate;
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('PatientMatcherService: Error creating match candidate', [
                'patient_id' => $patient->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Auto-approve exact match and update related records.
     *
     * @param Patient $patient The patient
     * @param PatientMatchCandidate $candidate The candidate record
     * @param array $candidateData The candidate data
     * @param string|null $labCode The lab code
     */
    protected function autoApproveExactMatch(Patient $patient, PatientMatchCandidate $candidate, array $candidateData, ?string $labCode): void
    {
        Log::info('PatientMatcherService: Auto-approving exact match', [
            'patient_id' => $patient->id,
            'candidate_customer_id' => $candidateData['candidate_customer_id'],
        ]);

        // Create patient-customer link
        $link = PatientCustomerLink::create([
            'patient_id' => $patient->id,
            'customer_id' => $candidateData['candidate_customer_id'],
            'link_type' => PatientCustomerLink::LINK_TYPE_EXACT_MATCH,
            'confidence_score' => $candidateData['confidence_score'],
            'match_candidate_id' => $candidate->id,
            'linked_by' => null,
            'linked_at' => now(),
            'notes' => 'Auto-linked: 100% confidence exact match',
        ]);

        // Update patient IC if different (normalize both for comparison)
        $patientIcNormalized = $this->icNormalizer->normalize($patient->icno);
        $candidateIcNormalized = $this->icNormalizer->normalize($candidateData['candidate_ic']);

        if ($patientIcNormalized !== $candidateIcNormalized) {
            $oldIc = $patient->icno;
            $patient->icno = $candidateData['candidate_ic'];
            $patient->updated_at = now();
            $patient->save();

            Log::info('PatientMatcherService: Updated patient IC', [
                'patient_id' => $patient->id,
                'old_ic' => $oldIc,
                'new_ic' => $candidateData['candidate_ic'],
            ]);
        }

        // Update test_results ref_id ONLY if currently NULL and candidate has refid
        if (!empty($candidateData['candidate_refid'])) {
            $testResultsUpdated = $patient->testResults()
                ->where(function ($q) {
                    $q->whereNull('ref_id')
                        ->orWhere('ref_id', '');
                })
                ->update([
                    'ref_id' => $candidateData['candidate_refid'],
                    'updated_at' => now(),
                ]);

            if ($testResultsUpdated > 0) {
                Log::info('PatientMatcherService: Updated test_results ref_id (was NULL)', [
                    'patient_id' => $patient->id,
                    'new_ref_id' => $candidateData['candidate_refid'],
                    'records_updated' => $testResultsUpdated,
                ]);
            }
        }

        // Log audit trail
        PatientMatchAuditLog::create([
            'patient_id' => $patient->id,
            'customer_id' => $candidateData['candidate_customer_id'],
            'match_candidate_id' => $candidate->id,
            'patient_customer_link_id' => $link->id,
            'action' => PatientMatchAuditLog::ACTION_CANDIDATE_APPROVED,
            'input_data' => [
                'auto_approved' => true,
                'confidence_score' => $candidateData['confidence_score'],
            ],
            'output_data' => [
                'link_id' => $link->id,
                'link_type' => PatientCustomerLink::LINK_TYPE_EXACT_MATCH,
            ],
            'triggered_by' => PatientMatchAuditLog::TRIGGERED_BY_SYSTEM,
        ]);

        Log::info('PatientMatcherService: Exact match auto-approved successfully', [
            'patient_id' => $patient->id,
            'customer_id' => $candidateData['candidate_customer_id'],
            'link_id' => $link->id,
        ]);
    }

    /**
     * Approve a match candidate and create a patient-customer link.
     *
     * @param PatientMatchCandidate $candidate The candidate to approve
     * @param int $reviewerId The admin user ID
     * @param string|null $notes Review notes
     * @return PatientCustomerLink The created link
     */
    public function approveCandidate(PatientMatchCandidate $candidate, int $reviewerId, ?string $notes = null): PatientCustomerLink
    {
        Log::info('PatientMatcherService: Approving candidate', [
            'candidate_id' => $candidate->id,
            'patient_id' => $candidate->patient_id,
            'customer_id' => $candidate->candidate_customer_id,
            'reviewer_id' => $reviewerId,
        ]);

        try {
            DB::beginTransaction();

            // Update candidate status
            $candidate->update([
                'status' => PatientMatchCandidate::STATUS_APPROVED,
                'reviewed_by' => $reviewerId,
                'reviewed_at' => now(),
                'review_notes' => $notes,
            ]);

            // Determine link type based on confidence
            $linkType = $candidate->confidence_score >= 1.0
                ? PatientCustomerLink::LINK_TYPE_EXACT_MATCH
                : PatientCustomerLink::LINK_TYPE_FUZZY_MATCH;

            // Create patient-customer link
            $link = PatientCustomerLink::create([
                'patient_id' => $candidate->patient_id,
                'customer_id' => $candidate->candidate_customer_id,
                'link_type' => $linkType,
                'confidence_score' => $candidate->confidence_score,
                'match_candidate_id' => $candidate->id,
                'linked_by' => $reviewerId,
                'linked_at' => now(),
                'notes' => $notes,
            ]);

            // Log the approval
            PatientMatchAuditLog::create([
                'patient_id' => $candidate->patient_id,
                'customer_id' => $candidate->candidate_customer_id,
                'match_candidate_id' => $candidate->id,
                'patient_customer_link_id' => $link->id,
                'action' => PatientMatchAuditLog::ACTION_CANDIDATE_APPROVED,
                'input_data' => [
                    'candidate_id' => $candidate->id,
                    'confidence_score' => $candidate->confidence_score,
                ],
                'output_data' => [
                    'link_id' => $link->id,
                    'link_type' => $linkType,
                ],
                'triggered_by' => PatientMatchAuditLog::TRIGGERED_BY_ADMIN,
                'user_id' => $reviewerId,
            ]);

            DB::commit();

            Log::info('PatientMatcherService: Candidate approved successfully', [
                'candidate_id' => $candidate->id,
                'link_id' => $link->id,
            ]);

            return $link;
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('PatientMatcherService: Error approving candidate', [
                'candidate_id' => $candidate->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Reject a match candidate.
     *
     * @param PatientMatchCandidate $candidate The candidate to reject
     * @param int $reviewerId The admin user ID
     * @param string|null $notes Review notes
     * @return PatientMatchCandidate The updated candidate
     */
    public function rejectCandidate(PatientMatchCandidate $candidate, int $reviewerId, ?string $notes = null): PatientMatchCandidate
    {
        Log::info('PatientMatcherService: Rejecting candidate', [
            'candidate_id' => $candidate->id,
            'patient_id' => $candidate->patient_id,
            'reviewer_id' => $reviewerId,
        ]);

        try {
            DB::beginTransaction();

            // Update candidate status
            $candidate->update([
                'status' => PatientMatchCandidate::STATUS_REJECTED,
                'reviewed_by' => $reviewerId,
                'reviewed_at' => now(),
                'review_notes' => $notes,
            ]);

            // Log the rejection
            PatientMatchAuditLog::create([
                'patient_id' => $candidate->patient_id,
                'customer_id' => $candidate->candidate_customer_id,
                'match_candidate_id' => $candidate->id,
                'action' => PatientMatchAuditLog::ACTION_CANDIDATE_REJECTED,
                'input_data' => [
                    'candidate_id' => $candidate->id,
                    'confidence_score' => $candidate->confidence_score,
                ],
                'output_data' => [
                    'rejection_notes' => $notes,
                ],
                'triggered_by' => PatientMatchAuditLog::TRIGGERED_BY_ADMIN,
                'user_id' => $reviewerId,
            ]);

            DB::commit();

            Log::info('PatientMatcherService: Candidate rejected', [
                'candidate_id' => $candidate->id,
            ]);

            return $candidate;
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('PatientMatcherService: Error rejecting candidate', [
                'candidate_id' => $candidate->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Create a manual link between patient and customer.
     *
     * @param Patient $patient The patient
     * @param int $customerId The Octopus customer ID
     * @param int $linkedBy The admin user ID
     * @param string|null $notes Link notes
     * @return PatientCustomerLink The created link
     */
    public function createManualLink(Patient $patient, int $customerId, int $linkedBy, ?string $notes = null): PatientCustomerLink
    {
        Log::info('PatientMatcherService: Creating manual link', [
            'patient_id' => $patient->id,
            'customer_id' => $customerId,
            'linked_by' => $linkedBy,
        ]);

        try {
            DB::beginTransaction();

            // Create patient-customer link
            $link = PatientCustomerLink::create([
                'patient_id' => $patient->id,
                'customer_id' => $customerId,
                'link_type' => PatientCustomerLink::LINK_TYPE_MANUAL_LINK,
                'confidence_score' => null,
                'match_candidate_id' => null,
                'linked_by' => $linkedBy,
                'linked_at' => now(),
                'notes' => $notes,
            ]);

            // Log the manual link
            PatientMatchAuditLog::create([
                'patient_id' => $patient->id,
                'customer_id' => $customerId,
                'patient_customer_link_id' => $link->id,
                'action' => PatientMatchAuditLog::ACTION_LINK_CREATED,
                'input_data' => [
                    'patient_id' => $patient->id,
                    'customer_id' => $customerId,
                    'link_type' => 'manual',
                ],
                'output_data' => [
                    'link_id' => $link->id,
                ],
                'triggered_by' => PatientMatchAuditLog::TRIGGERED_BY_ADMIN,
                'user_id' => $linkedBy,
            ]);

            DB::commit();

            Log::info('PatientMatcherService: Manual link created', [
                'link_id' => $link->id,
            ]);

            return $link;
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('PatientMatcherService: Error creating manual link', [
                'patient_id' => $patient->id,
                'customer_id' => $customerId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Log match attempt to audit table.
     *
     * @param Patient $patient The patient
     * @param Collection $candidates Found candidates
     * @param string $action The action type
     * @param array $extra Additional data
     */
    protected function logMatchAttempt(Patient $patient, Collection $candidates, string $action, array $extra = []): void
    {
        PatientMatchAuditLog::create([
            'patient_id' => $patient->id,
            'action' => $action,
            'input_data' => array_merge([
                'ic' => $patient->icno,
                'dob' => $patient->dob,
                'gender' => $patient->gender,
            ], $extra),
            'output_data' => [
                'candidate_count' => $candidates->count(),
                'top_confidence' => $candidates->first()['confidence_score'] ?? null,
            ],
            'triggered_by' => PatientMatchAuditLog::TRIGGERED_BY_SYSTEM,
        ]);
    }
}
