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
}
