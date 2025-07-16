<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ImportCsvFile implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $filepath;

    public $tries = 3;
    public $timeout = 300;

    public function backoff(): int
    {
        return 120;
    }

    /**
     * Create a new job instance.
     */
    public function __construct($filepath)
    {
        $this->filepath = $filepath;
    }

    /**
     * Execute the job.
     */


    public function handle(): void
    {
        $disk = Storage::disk('sftp');
        $stream = $disk->readStream($this->filepath);
        if (!$stream) return;

        $filename = pathinfo($this->filepath, PATHINFO_FILENAME);
        $sending_facility = $filename;
        $batch_id = preg_replace('/[^0-9]/', '', $filename);

        $raw = $disk->get($this->filepath);
        Storage::disk('local')->put("temp/{$filename}.csv", $raw);
        $localPath = storage_path("app/temp/{$filename}.csv");

        $reader = IOFactory::createReader('Csv');
        $reader->setReadDataOnly(true);
        $reader->setInputEncoding('UTF-8');
        $reader->setDelimiter(',');
        $reader->setEnclosure('"');
        $reader->setSheetIndex(0);

        $spreadsheet = $reader->load($localPath);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray();

        $header = null;
        $grouped = [];

        foreach ($rows as $row) {
            if (!$header) {
                $header = $row;
                continue;
            }

            if (count($header) !== count($row)) {
                Log::warning('CSV row does not match header column count', [
                    'file' => $this->filepath,
                    'row' => $row,
                ]);
                continue;
            }

            $mapped = [];
            foreach ($header as $index => $key) {
                $value = $row[$index];
                if (strtolower($key) === 'patient_icno') {
                    if (is_numeric($value) && stripos((string) $value, 'e') !== false) {
                        $value = number_format($value, 0, '', '');
                    }
                    $value = preg_replace('/[^0-9]/', '', (string) $value);
                }
                $mapped[$key] = (string) $value;
            }

            $data = $this->checkNull($mapped);
            $formatted = $this->formatData($data);

            if ($formatted) {
                $labNo = $formatted['lab_no'];

                if (!isset($grouped[$labNo])) {
                    $grouped[$labNo] = [
                        'patient_icno' => $formatted['patient_icno'],
                        'reference_id' => $formatted['reference_id'],
                        'lab_no' => $labNo,
                        'bill_code' => $formatted['bill_code'],
                        'doctor_code' => $formatted['doctor_code'],
                        'received_date' => $formatted['received_date'],
                        'reported_date' => $formatted['reported_date'],
                        'collected_date' => $formatted['collected_date'],
                        'results' => [],
                        'sending_facility' => $sending_facility,
                        'batch_id' => $batch_id,
                        'lab_code' => 'INN',
                    ];
                }

                foreach ($formatted['results'] as $panelName => $panelData) {
                    if (!isset($grouped[$labNo]['results'][$panelName])) {
                        $grouped[$labNo]['results'][$panelName] = $panelData;
                    } else {
                        $grouped[$labNo]['results'][$panelName]['tests'] = array_merge(
                            $grouped[$labNo]['results'][$panelName]['tests'],
                            $panelData['tests']
                        );
                    }
                }
            }
        }

        $finalBatch = array_values($grouped);
        foreach ($finalBatch as $chunk) {
            Log::info('Chunk data', ['data' => $chunk]);
            $this->sendDataToApi($chunk);
        }

        Log::info('Job completed: ' . $this->filepath);
    }


    protected function formatData($row)
    {
        if (empty($row['icno']) || empty($row['labno']) || empty($row['ordername'])) return null;

        $icInfo = $this->checkIcno($row['icno']);
        $refid = $this->checkRefId($row['refid']);

        $panel = $row['panelname'];

        return [
            'patient_icno' => $icInfo['icno'],
            'ic_type' => $icInfo['type'],
            'patient_gender' => $icInfo['gender'],
            'patient_age' => $icInfo['age'],
            'reference_id' => $refid,
            'lab_no' => $row['labno'],
            'bill_code' => $row['billcode'] ?? null,
            'doctor_code' => $row['billcode'],
            'received_date' => $this->convertToDateTimeString($row['receiveddate']),
            'reported_date' => $this->convertToDateTimeString($row['reporteddate']),
            'collected_date' => $this->convertToDateTimeString($row['collecteddate']),
            'results' => [
                $panel => [
                    'panel_code' => $row['panel'],
                    'panel_sequence' => (int) ($row['sequenceno'] ?? 1),
                    'overall_notes' => $row['overallnotes'] ?? null,
                    'tests' => [
                        [
                            'test_name' => trim($row['ordername']),
                            'result_value' => trim((string) (
                                !empty($row['testnotes']) ? $row['testnotes'] : ($row['resultvalue'] ?? null)
                            )),
                            'decimal_point' => trim((string) ($row['decimalpoint'] ?? null)),
                            'result_flag' => $row['resultflag'] ?? null,
                            'unit' => trim($row['unit'] ?? null),
                            'ref_range' => $this->formatRefRange($row['refrange'] ?? null),
                            'test_note' => null,
                            'item_sequence' => (int) ($row['sequenceno'] ?? 1),
                        ],
                    ],
                ],
            ],
        ];
    }


    private function checkIcno($icno)
    {
        $type = 'PP';
        $gender = null;
        $age = null;

        if (strlen($icno) === 12) {
            $year = (int) substr($icno, 0, 2);
            $month = (int) substr($icno, 2, 2);
            $day = (int) substr($icno, 4, 2);
            $lastDigit = (int) substr($icno, -1);

            $currentYear = (int) date('Y');
            $fullYear = $year > ($currentYear % 100) ? 1900 + $year : 2000 + $year;

            if (checkdate($month, $day, $fullYear)) {
                $type = 'NRIC';
                $gender = $lastDigit % 2 === 0 ? 'F' : 'M';
                $age = $currentYear - $fullYear;
            }
        }

        return [
            'icno' => $icno,
            'type' => $type,
            'gender' => $gender,
            'age' => $age,
        ];
    }

    private function convertToDateTimeString($date)
    {
        $timestamp = strtotime($date);
        return $timestamp === false ? null : date('Y-m-d H:i:s', $timestamp);
    }
    private function checkRefId($refid)
    {
        $refid = trim($refid);

        if (empty($refid) || strpos($refid, 'INN') !== 0) {
            return null;
        }

        return $refid;
    }

    protected function checkNull($row)
    {
        foreach ($row as $key => $value) {
            if (is_string($value) && strtoupper(trim($value)) === 'NULL') {
                $row[$key] = null;
            }
        }

        return $row;
    }

    protected function formatRefRange($value)
    {
        $value = trim($value);

        if (empty($value)) {
            return null;
        }

        if (stripos($value, 'to') !== false) {
            [$from, $to] = array_map('trim', explode('to', $value, 2));

            if (is_numeric($from) && is_numeric($to)) {
                return "$from - $to";
            }
        }

        return $value;
    }

    protected function sendDataToApi(array $data)
    {
        $token = $this->getApiToken();
        // Log::info('Token', ['token' => $token]);
        // Log::info('Sending batch to API', ['count' => count($data)]);
        Log::info('Auth user: ', ['user' => Auth::guard('lab')->user()]);

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ])->post(config('services.api.url') . '/api/v1/patient/labResults', $data);

        Log::info('API response', ['response' => $response]);
        if ($response->unauthorized()) {
            Cache::forget('api_jwt_token');

            $token = $this->getApiToken();
            $response = Http::withToken($token)->post(config('services.api.url') . '/api/v1/patientResults', $data);
        }

        if (!$response->successful()) {
            Log::error('Failed to send data to API', [
                'file' => $this->filepath,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        }

        return $response;
    }

    protected function getApiToken()
    {
        return Cache::remember('api_jwt_token', now()->addDays(30), function () {
            $response = Http::post(config('services.api.url') . config('services.api.login'), [
                'username' => config('services.api.username'),
                'password' => config('services.api.password'),
            ]);

            if ($response->successful()) {
                return $response->json('access_token');
                Log::info('Access token', ['response' => $response]);
            }

            throw new \Exception('Failed to authenticate with API: ' . $response->body());
        });
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("Job failed permanently: " . $exception->getMessage());
    }
}
