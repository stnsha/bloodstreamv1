<?php

namespace App\Services;

use App\Exceptions\OdbInsytePushException;
use App\Models\Patient;
use App\Models\TestResult;
use Carbon\Carbon;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Pushes one patient to the ODB InSyte Push API (see PUSH-API.md):
 *   1. login.php                -> JWT (cached until shortly before expiry)
 *   2. receive_and_trigger.php  -> patient, blood_test, check_record, remarks
 *
 * The body carries the patient's last N years (default 2) of:
 *   - blood_test:   every lab's test results, flat {name, value, unit} items
 *   - check_record: every MyHealth parameter (not only BMI / BP)
 *   - remarks:      ODB blood_test_sales doc_review text only, never AI reviews
 *
 * ODB saves the readings and queues its own AI analysis. No local DB writes.
 */
class OdbInsytePushService
{
    private const TOKEN_CACHE_KEY = 'odb_insyte_push_token';

    private const TOKEN_EXPIRY_MARGIN_SECONDS = 300;

    private const LOGIN_TIMEOUT_SECONDS = 30;

    private const PUSH_TIMEOUT_SECONDS = 60;

    private const LOG_CHANNEL = 'odb-push';

    public function __construct(
        private readonly MyHealthService $myHealthService,
        private readonly OctopusApiService $octopusApiService
    ) {}

    /**
     * Build the receive_and_trigger.php body for one patient.
     *
     * Returns null when the patient has neither blood_test nor check_record
     * data in the window, since ODB rejects that body with HTTP 400.
     */
    public function buildPayload(Patient $patient): ?array
    {
        $cutoff = now()->subYears($this->historyYears())->startOfDay();

        Log::channel(self::LOG_CHANNEL)->info('OdbInsytePush: building payload', [
            'patient_id' => $patient->id,
            'icno' => $patient->icno,
            'cutoff' => $cutoff->toDateString(),
        ]);

        $bloodTest = $this->buildBloodTest($patient, $cutoff);
        $checkRecord = $this->buildCheckRecord($patient->icno, $cutoff);

        if (empty($bloodTest) && empty($checkRecord)) {
            Log::channel(self::LOG_CHANNEL)->warning('OdbInsytePush: no blood_test or check_record data in window, nothing to push', [
                'patient_id' => $patient->id,
                'icno' => $patient->icno,
                'cutoff' => $cutoff->toDateString(),
            ]);

            return null;
        }

        $payload = [
            'icno' => $patient->icno,
            'patient' => [
                'id' => $patient->id,
                'name' => $patient->name,
                'dob' => $this->formatDob($patient->dob),
                'gender' => $patient->gender,
                'age' => $patient->age !== null ? (string) $patient->age : null,
            ],
            'blood_test' => $bloodTest,
            // An empty object, not [], keeps check_record a JSON object as ODB expects.
            'check_record' => empty($checkRecord) ? (object) [] : $checkRecord,
        ];

        // remarks is only sent when the ODB lookup succeeded. Leaving the key out makes
        // ODB fall back to its own Doctor's Review copy; [] would mean "no remarks".
        $remarks = $this->buildRemarks($patient->icno, $cutoff);
        if ($remarks !== null) {
            $payload['remarks'] = $remarks;
        }

        Log::channel(self::LOG_CHANNEL)->info('OdbInsytePush: payload built', [
            'patient_id' => $patient->id,
            'icno' => $patient->icno,
            'blood_test_reports' => count($bloodTest),
            'blood_test_items' => array_sum(array_map(fn ($report) => count($report['items']), $bloodTest)),
            'check_record_parameters' => count($checkRecord),
            'remarks' => $remarks === null ? 'omitted' : count($remarks),
        ]);

        return $payload;
    }

    /**
     * POST the payload to receive_and_trigger.php. On 401 the cached token is
     * dropped and the push is retried once with a fresh login.
     *
     * @throws OdbInsytePushException
     */
    public function push(array $payload): array
    {
        $pushUrl = (string) config('services.odb_insyte.push_url');
        if ($pushUrl === '') {
            throw new OdbInsytePushException('ODB InSyte push URL is not configured (ODB_INSYTE_PUSH_URL).', null, false);
        }

        $context = ['icno' => $payload['icno'] ?? null];

        Log::channel(self::LOG_CHANNEL)->info('OdbInsytePush: pushing patient to ODB', $context);

        $response = $this->sendPush($pushUrl, $payload, $this->getToken());

        if ($response->status() === 401) {
            Log::channel(self::LOG_CHANNEL)->warning('OdbInsytePush: token rejected (401), logging in again and retrying once', $context);

            $response = $this->sendPush($pushUrl, $payload, $this->getToken(true));
        }

        $json = $response->json();
        $status = $response->status();

        if (! $response->successful() || ! is_array($json) || ($json['success'] ?? false) !== true) {
            $error = is_array($json) ? ($json['error'] ?? null) : null;

            Log::channel(self::LOG_CHANNEL)->error('OdbInsytePush: push rejected by ODB', $context + [
                'http_status' => $status,
                'error' => $error ?? mb_substr((string) $response->body(), 0, 500),
            ]);

            // 400/404/405/422: the request itself is wrong, retrying cannot help.
            // 401 after a fresh login, 5xx, 503 and malformed answers may recover later.
            $retryable = ! in_array($status, [400, 404, 405, 422], true);

            throw new OdbInsytePushException(
                "ODB InSyte push failed (HTTP {$status}): ".($error ?? 'unexpected response'),
                $status,
                $retryable
            );
        }

        $unitMismatch = $json['unit_mismatch'] ?? [];
        $remarksSkipped = (int) ($json['remarks_skipped'] ?? 0);

        Log::channel(self::LOG_CHANNEL)->info('OdbInsytePush: push accepted by ODB', $context + [
            'odb_patient_id' => $json['patient_id'] ?? null,
            'patient_created' => $json['patient_created'] ?? null,
            'saved' => $json['saved'] ?? null,
            'unmapped_blood_test_count' => count($json['unmapped_blood_test'] ?? []),
            'unmapped_check_record_count' => count($json['unmapped_check_record'] ?? []),
            'remarks_received' => $json['remarks_received'] ?? null,
            'queued' => $json['queued'] ?? null,
            'queue_id' => $json['queue_id'] ?? null,
            'queue_position' => $json['queue_position'] ?? null,
            'already_waiting' => $json['already_waiting'] ?? null,
        ]);

        if (! empty($unitMismatch)) {
            Log::channel(self::LOG_CHANNEL)->warning('OdbInsytePush: ODB refused readings because of their unit', $context + [
                'unit_mismatch' => $unitMismatch,
            ]);
        }

        if ($remarksSkipped > 0) {
            Log::channel(self::LOG_CHANNEL)->warning('OdbInsytePush: ODB could not use some remarks (no text or bad remark_date)', $context + [
                'remarks_skipped' => $remarksSkipped,
            ]);
        }

        return $json;
    }

    /**
     * Get a push token from login.php, cached for expires_in minus a safety margin.
     *
     * @throws OdbInsytePushException
     */
    public function getToken(bool $forceRefresh = false): string
    {
        if ($forceRefresh) {
            Cache::forget(self::TOKEN_CACHE_KEY);
        }

        $cached = Cache::get(self::TOKEN_CACHE_KEY);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $loginUrl = (string) config('services.odb_insyte.login_url');
        $username = (string) config('credentials.odb_insyte.username');
        $password = (string) config('credentials.odb_insyte.password');

        if ($loginUrl === '' || $username === '' || $password === '') {
            throw new OdbInsytePushException(
                'ODB InSyte login is not configured (ODB_INSYTE_LOGIN_URL / ODB_INSYTE_USERNAME / ODB_INSYTE_PASSWORD).',
                null,
                false
            );
        }

        Log::channel(self::LOG_CHANNEL)->info('OdbInsytePush: logging in to ODB', ['force_refresh' => $forceRefresh]);

        try {
            $response = Http::timeout(self::LOGIN_TIMEOUT_SECONDS)
                ->acceptJson()
                ->asJson()
                ->post($loginUrl, ['username' => $username, 'password' => $password]);
        } catch (ConnectionException $e) {
            Log::channel(self::LOG_CHANNEL)->error('OdbInsytePush: ODB login connection failed', ['error' => $e->getMessage()]);

            throw new OdbInsytePushException('ODB InSyte login connection failed: '.$e->getMessage(), null, true, $e);
        }

        $token = $response->json('token');

        if (! $response->successful() || ! is_string($token) || $token === '') {
            Log::channel(self::LOG_CHANNEL)->error('OdbInsytePush: ODB login failed', [
                'http_status' => $response->status(),
                'error' => $response->json('error'),
            ]);

            throw new OdbInsytePushException(
                'ODB InSyte login failed (HTTP '.$response->status().'): '.($response->json('error') ?? 'no token returned'),
                $response->status()
            );
        }

        $expiresIn = (int) ($response->json('expires_in') ?? 86400);
        $ttl = max(60, $expiresIn - self::TOKEN_EXPIRY_MARGIN_SECONDS);

        Cache::put(self::TOKEN_CACHE_KEY, $token, $ttl);

        Log::channel(self::LOG_CHANNEL)->info('OdbInsytePush: ODB login succeeded, token cached', ['ttl_seconds' => $ttl]);

        return $token;
    }

    /**
     * @throws OdbInsytePushException
     */
    private function sendPush(string $pushUrl, array $payload, string $token): Response
    {
        try {
            return Http::timeout(self::PUSH_TIMEOUT_SECONDS)
                ->acceptJson()
                ->asJson()
                ->withToken($token)
                ->post($pushUrl, $payload);
        } catch (ConnectionException $e) {
            Log::channel(self::LOG_CHANNEL)->error('OdbInsytePush: push connection failed', [
                'icno' => $payload['icno'] ?? null,
                'error' => $e->getMessage(),
            ]);

            throw new OdbInsytePushException('ODB InSyte push connection failed: '.$e->getMessage(), null, true, $e);
        }
    }

    /**
     * One entry per test result in the window (all labs):
     * {collected_date, lab_no, items: [{name, value, unit}]}.
     * Calculated special tests (e.g. eGFR) are appended unless the lab already
     * reported a reading with the same name on that report.
     */
    private function buildBloodTest(Patient $patient, Carbon $cutoff): array
    {
        $testResults = $patient->testResults()
            ->where('collected_date', '>=', $cutoff)
            ->with([
                'testResultItems.panelPanelItem.panelItem',
                'testResultSpecialTests.panelPanelItem.panelItem',
            ])
            ->orderByDesc('collected_date')
            ->get();

        $reports = [];

        /** @var TestResult $testResult */
        foreach ($testResults as $testResult) {
            if (! $testResult->collected_date) {
                continue;
            }

            $items = [];
            $seenNames = [];

            foreach ($testResult->testResultItems as $item) {
                $row = $this->toBloodTestItem($item->panelPanelItem?->panelItem, $item->value);
                if ($row !== null) {
                    $items[] = $row;
                    $seenNames[mb_strtolower($row['name'])] = true;
                }
            }

            foreach ($testResult->testResultSpecialTests as $specialTest) {
                $row = $this->toBloodTestItem($specialTest->panelPanelItem?->panelItem, $specialTest->value);
                if ($row !== null && ! isset($seenNames[mb_strtolower($row['name'])])) {
                    $items[] = $row;
                    $seenNames[mb_strtolower($row['name'])] = true;
                }
            }

            if (empty($items)) {
                continue;
            }

            $reports[] = [
                'collected_date' => $testResult->collected_date->format('Y-m-d'),
                'lab_no' => $testResult->lab_no,
                'items' => $items,
            ];
        }

        return $reports;
    }

    private function toBloodTestItem($panelItem, $value): ?array
    {
        if (! $panelItem || $value === null || trim((string) $value) === '') {
            return null;
        }

        $name = trim((string) $panelItem->name);
        if ($name === '') {
            return null;
        }

        $unit = $panelItem->unit !== null ? trim(strip_tags((string) $panelItem->unit)) : null;

        return [
            'name' => $name,
            'value' => (string) $value,
            'unit' => $unit === '' ? null : $unit,
        ];
    }

    /**
     * Every MyHealth check_record parameter in the window, keyed by parameter
     * name, newest reading first. Same reading shape as
     * GET /api/myhealth/check-record/{ic}.
     */
    private function buildCheckRecord(string $icno, Carbon $cutoff): array
    {
        $records = $this->myHealthService->getCheckRecordsByICSince($icno, $cutoff);

        if ($records->isEmpty()) {
            return [];
        }

        $detailsByParameter = $this->myHealthService->getAllRecordDetailsBatch($records->pluck('id')->all());

        $parameters = [];
        foreach ($detailsByParameter as $parameterName => $rows) {
            $parameters[$parameterName] = $rows->map(fn ($row) => [
                'value' => $row->result,
                'range' => $row->range,
                'min_range' => $row->min_range,
                'max_range' => $row->max_range,
                'unit' => $row->unit,
                'date_time' => $row->date_time,
                'record_id' => $row->record_id,
            ])->values()->all();
        }

        return $parameters;
    }

    /**
     * Doctor remarks from ODB blood_test_sales (doc_review), one per sales row.
     * Returns null when the ODB lookup fails so the caller omits the field.
     */
    private function buildRemarks(string $icno, Carbon $cutoff): ?array
    {
        try {
            $sales = $this->octopusApiService->getBloodTestSalesByIcNo($icno);
        } catch (Throwable $e) {
            Log::channel(self::LOG_CHANNEL)->error('OdbInsytePush: blood_test_sales lookup failed, remarks field omitted', [
                'icno' => $icno,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        $cutoffDate = $cutoff->toDateString();
        $remarks = [];
        $seen = [];

        foreach ($sales as $sale) {
            $saleId = $sale['id'] ?? null;
            $date = mb_substr((string) ($sale['date'] ?? ''), 0, 10);
            $text = $this->htmlToText((string) ($sale['doc_review'] ?? ''));

            $key = $saleId !== null ? 'sale:'.$saleId : 'text:'.md5($text);

            if (isset($seen[$key]) || $date === '' || $date < $cutoffDate || $text === '') {
                continue;
            }

            if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                continue;
            }

            $seen[$key] = true;
            $remarks[] = [
                'remark_date' => $date,
                'staff_name' => '',
                'staff_role' => 'doctor',
                'remark_text' => $text,
            ];
        }

        return $remarks;
    }

    /**
     * Review HTML to readable plain text (mirrors push_to_odb.php html_to_text()).
     */
    private function htmlToText(string $html): string
    {
        $html = preg_replace('#<(br|/p|/div|/tr|/li|/h[1-6])\b[^>]*>#i', "\n", $html);
        $html = preg_replace('#</t[dh]>#i', ' | ', $html);
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace("\xC2\xA0", ' ', $text);

        $lines = array_filter(
            array_map(
                fn ($line) => trim(preg_replace('/[ \t]+/u', ' ', trim($line, " |\t"))),
                explode("\n", $text)
            ),
            'strlen'
        );

        return implode("\n", $lines);
    }

    /**
     * ODB expects dob as YYYYMMDD. Accepts Y-m-d, Y/m/d, Ymd or a datetime.
     */
    private function formatDob($dob): ?string
    {
        if ($dob === null || $dob === '') {
            return null;
        }

        $digits = preg_replace('/\D/', '', mb_substr((string) $dob, 0, 10));

        return strlen($digits) === 8 ? $digits : null;
    }

    private function historyYears(): int
    {
        return max(1, (int) config('services.odb_insyte.history_years', 2));
    }
}
