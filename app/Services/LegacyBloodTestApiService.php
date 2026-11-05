<?php

namespace App\Services;

use App\Models\TestResult;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Client for the legacy Blood Stream (blood-test-v8.1) results API:
 * GET {BTV1_API_URL}/api/legacy/results-by-icno/{icno}?since=Y-m-d
 *
 * Returns the legacy test results and items for an IC number, in the legacy
 * format (panel > items, Biomark item names). Results whose lab_no is already
 * stored in bsv1 are dropped, so the same report is never sent twice.
 *
 * Fail-open: a disabled, unconfigured or failing API returns no results and
 * never stops the caller.
 */
class LegacyBloodTestApiService
{
    public const STATUS_OK = 'ok';

    public const STATUS_DISABLED = 'disabled';

    public const STATUS_FAILED = 'failed';

    public const SOURCE = 'blood_test_v1';

    /**
     * @return array{status: string, test_results: array<int, array>, skipped_in_bsv1: int}
     */
    public function resultsByIcno(string $icno, ?Carbon $collectedSince = null): array
    {
        $empty = ['status' => self::STATUS_DISABLED, 'test_results' => [], 'skipped_in_bsv1' => 0];

        $url = rtrim((string) config('services.blood_test_v1.url'), '/');
        $token = config('services.blood_test_v1.token');

        if (! config('services.blood_test_v1.enabled') || $url === '' || ! $token) {
            return $empty;
        }

        try {
            $response = Http::withHeaders(['Authorization' => $token])
                ->acceptJson()
                ->timeout((int) config('services.blood_test_v1.timeout', 15))
                ->get($url.'/api/legacy/results-by-icno/'.rawurlencode($icno), array_filter([
                    'since' => $collectedSince?->toDateString(),
                ]));

            if (! $response->successful() || $response->json('success') !== true) {
                Log::channel('myhealth-feed')->error('Legacy results API: request failed, continuing without legacy history', [
                    'icno' => $icno,
                    'http_status' => $response->status(),
                    'body' => mb_substr($response->body(), 0, 500),
                ]);

                return ['status' => self::STATUS_FAILED] + $empty;
            }

            $testResults = $response->json('data.test_results') ?? [];
        } catch (Throwable $e) {
            Log::channel('myhealth-feed')->error('Legacy results API: request error, continuing without legacy history', [
                'icno' => $icno,
                'error' => $e->getMessage(),
            ]);

            return ['status' => self::STATUS_FAILED] + $empty;
        }

        $labNos = array_values(array_filter(array_column($testResults, 'lab_no')));
        $inBsv1 = $labNos === []
            ? []
            : TestResult::whereIn('lab_no', $labNos)->pluck('lab_no')->map(fn ($labNo) => (string) $labNo)->all();

        $kept = array_values(array_filter(
            $testResults,
            fn ($result) => ! in_array((string) ($result['lab_no'] ?? ''), $inBsv1, true)
        ));

        return [
            'status' => self::STATUS_OK,
            'test_results' => $kept,
            'skipped_in_bsv1' => count($testResults) - count($kept),
        ];
    }

    /**
     * Shapes one legacy result like a PatientProfileService test_results entry
     * so both can sit in the same list. Only what the legacy system has is
     * filled: review = the newest legacy doctor_reviews row (HTML, like the
     * blood_test_sales review shape); no MyHealth readings or special tests,
     * and no profile/category grouping (panels only). test_result.id is null because
     * the legacy id is not a bsv1 id; it is kept as legacy_test_result_id.
     */
    public function toFeedEntry(array $legacy): array
    {
        $panels = array_map(fn (array $panel) => [
            'panel_id' => null,
            'panel_code' => $panel['panel_code'] ?? null,
            'panel_name' => $panel['panel_name'] ?? null,
            'panel_comments' => [],
            'panel_items' => array_map(fn (array $item) => [
                'panel_item_name' => $item['panel_item_name'] ?? null,
                'panel_item_unit' => $item['panel_item_unit'] ?? null,
                'result_value' => $item['result_value'] ?? null,
                'result_flag' => $item['result_flag'] ?? null,
                'reference_range' => $item['reference_range'] ?? null,
                'item_comments' => array_map(fn ($note) => ['comment' => $note], $item['notes'] ?? []),
            ], $panel['items'] ?? []),
        ], $legacy['panels'] ?? []);

        return [
            'test_result' => [
                'id' => null,
                'source' => self::SOURCE,
                'legacy_test_result_id' => $legacy['id'] ?? null,
                'lab_code' => $legacy['lab_code'] ?? null,
                'lab_no' => $legacy['lab_no'] ?? null,
                'ref_id' => $legacy['ref_id'] ?? null,
                'collected_date' => $this->serializeDate($legacy['collected_date'] ?? null),
                'received_date' => $this->serializeDate($legacy['received_date'] ?? null),
                'reported_date' => $this->serializeDate($legacy['reported_date'] ?? null),
            ],
            'review' => empty($legacy['review']['review']) ? null : [
                'source' => self::SOURCE,
                'blood_test_sales_id' => null,
                'match_method' => null,
                'legacy_doctor_review_id' => $legacy['review']['doctor_review_id'] ?? null,
                'review' => $legacy['review']['review'],
            ],
            'myhealth' => [],
            'test_result_items' => ['panels' => $panels],
            'special_tests' => [],
        ];
    }

    /**
     * Same date format as a serialized bsv1 TestResult (ISO 8601 UTC), so
     * legacy and bsv1 entries in test_results look alike.
     */
    protected function serializeDate(?string $value): ?string
    {
        return $value ? Carbon::parse($value)->toJSON() : null;
    }
}
