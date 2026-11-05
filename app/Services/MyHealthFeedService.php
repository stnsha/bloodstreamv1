<?php

namespace App\Services;

use App\Models\TestResult;
use Carbon\Carbon;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Builds and POSTs the MyHealth lab feed for a test result.
 *
 * Body: { "feed": { "success": true, "data": <GET /api/patients/by-icno/{icno} data> } },
 * limited to test results collected within the last FEED_HISTORY_YEARS years.
 * data.test_results also holds the results of the same window that exist only
 * in the legacy system (blood-test-v8.1), marked test_result.source =
 * "blood_test_v1" (see LegacyBloodTestApiService::toFeedEntry()).
 */
class MyHealthFeedService
{
    const FEED_HISTORY_YEARS = 2;

    protected PatientProfileService $patientProfileService;

    protected LegacyBloodTestApiService $legacyBloodTestApi;

    public function __construct(PatientProfileService $patientProfileService, LegacyBloodTestApiService $legacyBloodTestApi)
    {
        $this->patientProfileService = $patientProfileService;
        $this->legacyBloodTestApi = $legacyBloodTestApi;
    }

    /**
     * Build the request body for the test result's patient.
     *
     * Returns ['body' => <request body>, 'stats' => <counts for logging>].
     *
     * @throws RuntimeException when the patient has no IC number or cannot be found by it
     */
    public function buildPayload(TestResult $testResult): array
    {
        $icno = $testResult->patient->icno ?? null;

        if (! $icno) {
            throw new RuntimeException("Test result {$testResult->id}: patient has no IC number, cannot build feed");
        }

        return $this->buildPayloadForIcno($icno, $testResult->id);
    }

    /**
     * Build the request body for a patient by IC number. $testResultId is the
     * result this feed is sent for; Insyte reports its processing status back
     * for that id (POST /api/v1/insyte/updateCompletionByTestResultId).
     *
     * Returns ['body' => <request body>, 'stats' => <counts for logging>].
     *
     * @throws RuntimeException when no patient has this IC number
     */
    public function buildPayloadForIcno(string $icno, ?int $testResultId = null): array
    {
        $since = now()->subYears(self::FEED_HISTORY_YEARS)->startOfDay();
        $profile = $this->patientProfileService->build($icno, $since);

        if (! $profile) {
            throw new RuntimeException("Patient not found by IC number {$icno}, cannot build feed");
        }

        // History kept only in the legacy system (blood-test-v8.1), merged into
        // test_results (newest collected first); never fails the feed.
        $legacy = $this->legacyBloodTestApi->resultsByIcno($icno, $since);

        $data = $profile['data'];
        $data['test_results'] = collect($data['test_results'])
            ->concat(array_map([$this->legacyBloodTestApi, 'toFeedEntry'], $legacy['test_results']))
            ->sortByDesc(fn ($entry) => $this->collectedTimestamp($entry))
            ->values();

        return [
            'body' => [
                'feed' => [
                    'success' => true,
                    'test_result_id' => $testResultId,
                    'data' => $data,
                ],
            ],
            'stats' => $profile['stats'] + [
                'legacy_status' => $legacy['status'],
                'legacy_test_result_count' => count($legacy['test_results']),
                'legacy_skipped_in_bsv1' => $legacy['skipped_in_bsv1'],
            ],
        ];
    }

    /**
     * Collected date of a feed test_results entry as a sortable timestamp
     * (bsv1 entries carry a TestResult model, legacy entries an array).
     */
    protected function collectedTimestamp(array $entry): int
    {
        $testResult = $entry['test_result'] ?? null;
        $collected = is_array($testResult) ? ($testResult['collected_date'] ?? null) : ($testResult->collected_date ?? null);

        return $collected ? Carbon::parse($collected)->getTimestamp() : 0;
    }

    /**
     * POST a body built by buildPayload() to MH_KEY_FEED_URL.
     *
     * @throws RuntimeException when the feed is not configured
     */
    public function send(array $body): Response
    {
        $url = config('services.myhealth_feed.url');
        $key = config('services.myhealth_feed.key');

        if (! $url || ! $key) {
            throw new RuntimeException('MyHealth feed is not configured (MH_KEY_FEED_URL / MH_KEY_FEED)');
        }

        return Http::withHeaders(['X-Api-Key' => $key])
            ->acceptJson()
            ->timeout((int) config('services.myhealth_feed.timeout', 60))
            ->post($url, $body);
    }

    /**
     * True when MyHealth accepted the feed: HTTP 2xx and no explicit success=false.
     */
    public function isAccepted(Response $response): bool
    {
        return $response->successful() && $response->json('success') !== false;
    }
}
