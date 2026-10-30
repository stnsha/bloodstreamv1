<?php

namespace App\Services;

use App\Models\TestResult;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Builds and POSTs the MyHealth lab feed for a test result.
 *
 * Body: { "feed": { "success": true, "data": <GET /api/patients/by-icno/{icno} data> } },
 * limited to test results collected within the last FEED_HISTORY_YEARS years.
 */
class MyHealthFeedService
{
    const FEED_HISTORY_YEARS = 2;

    protected PatientProfileService $patientProfileService;

    public function __construct(PatientProfileService $patientProfileService)
    {
        $this->patientProfileService = $patientProfileService;
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
        $profile = $this->patientProfileService->build(
            $icno,
            now()->subYears(self::FEED_HISTORY_YEARS)->startOfDay()
        );

        if (! $profile) {
            throw new RuntimeException("Patient not found by IC number {$icno}, cannot build feed");
        }

        return [
            'body' => [
                'feed' => [
                    'success' => true,
                    'test_result_id' => $testResultId,
                    'data' => $profile['data'],
                ],
            ],
            'stats' => $profile['stats'],
        ];
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
