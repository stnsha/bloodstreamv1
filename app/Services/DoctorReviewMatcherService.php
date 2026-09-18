<?php

namespace App\Services;

use App\Models\TestResult;
use Carbon\Carbon;

class DoctorReviewMatcherService
{
    protected RefIdNormalizerService $refIdNormalizer;

    public function __construct(RefIdNormalizerService $refIdNormalizer)
    {
        $this->refIdNormalizer = $refIdNormalizer;
    }

    /**
     * Match each test result to a blood_test_sales row (from the ODB blood
     * test API) and resolve a single combined review for it.
     *
     * Matching order:
     * 1. ref_id -> blood_test_sales.id (lab code prefix stripped).
     * 2. Fallback: collected_date in the same month and within 5 days of a
     *    sales row's date (closest date wins when more than one qualifies).
     * 3. No match: sales row stays only in the raw list.
     *
     * Review priority: blood_test_sales.doc_review (ODB) always wins when
     * non-empty. Otherwise falls back to the test result's own ai_reviews
     * row (ai_response / raw_response) -- regardless of whether a sales row
     * was matched at all.
     *
     * @param  \Illuminate\Support\Collection<int, TestResult>  $testResults
     * @param  array<int, array{id:int,date:string,doc_id:int,doc_review:?string}>  $salesRows
     * @return array{reviews: array<int, array|null>, matched_sales_ids: array<int, int>} reviews keyed by test_result id; matched_sales_ids are the blood_test_sales rows consumed by a match, regardless of whether a review was found on them
     */
    public function match($testResults, array $salesRows): array
    {
        $salesById = [];
        foreach ($salesRows as $row) {
            $salesById[(int) $row['id']] = $row;
        }

        $reviews = [];
        $matchedSalesIds = [];

        foreach ($testResults as $testResult) {
            $matched = null;
            $matchMethod = null;

            if (! empty($testResult->ref_id)) {
                $number = $this->refIdNormalizer->extractNumber($testResult->ref_id);

                if ($number !== null && isset($salesById[(int) $number])) {
                    $matched = $salesById[(int) $number];
                    $matchMethod = 'ref_id';
                }
            }

            if ($matched === null && $testResult->collected_date) {
                $found = $this->matchByDateRange($testResult->collected_date, $salesRows);

                if ($found !== null) {
                    $matched = $found;
                    $matchMethod = 'date_range';
                }
            }

            if ($matched !== null) {
                $matchedSalesIds[] = (int) $matched['id'];
            }

            if ($matched !== null && trim((string) $matched['doc_review']) !== '') {
                $reviews[$testResult->id] = [
                    'source' => 'blood_test_sales',
                    'blood_test_sales_id' => (int) $matched['id'],
                    'match_method' => $matchMethod,
                    'review' => $matched['doc_review'],
                ];

                continue;
            }

            $aiReview = $testResult->aiReview;

            if ($aiReview) {
                $reviews[$testResult->id] = [
                    'source' => 'ai_reviews',
                    'blood_test_sales_id' => $matched !== null ? (int) $matched['id'] : null,
                    'match_method' => $matchMethod,
                    'ai_response' => $aiReview->ai_response,
                    'raw_response' => $aiReview->raw_response,
                ];

                continue;
            }

            $reviews[$testResult->id] = null;
        }

        return [
            'reviews' => $reviews,
            'matched_sales_ids' => array_values(array_unique($matchedSalesIds)),
        ];
    }

    /**
     * Find the closest sales row within the same month and within 5 days of
     * the collected date. Returns null when nothing qualifies.
     */
    protected function matchByDateRange($collectedDate, array $salesRows): ?array
    {
        $collected = Carbon::parse($collectedDate);
        $best = null;
        $bestDiff = null;

        foreach ($salesRows as $row) {
            if (empty($row['date'])) {
                continue;
            }

            $salesDate = Carbon::parse($row['date']);

            if ($salesDate->year !== $collected->year || $salesDate->month !== $collected->month) {
                continue;
            }

            $diff = abs($salesDate->diffInDays($collected));

            if ($diff > 5) {
                continue;
            }

            if ($bestDiff === null || $diff < $bestDiff) {
                $best = $row;
                $bestDiff = $diff;
            }
        }

        return $best;
    }
}
