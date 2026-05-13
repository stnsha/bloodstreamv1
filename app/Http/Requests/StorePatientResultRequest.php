<?php

namespace App\Http\Requests;

use App\Models\Patient;
use Illuminate\Foundation\Http\FormRequest;

/**
 * @property array $doctor
 * @property array $patient
 * @property string $reference_id
 * @property string $lab_no
 * @property string $bill_code
 * @property string $doctor_id
 * @property string $received_date
 * @property string $reported_date
 * @property string $collected_date
 * @property array $results
 */
class StorePatientResultRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'sending_facility' => 'nullable|string',
            'batch_id' => 'nullable|string',
            'reference_id' => 'nullable|string',
            'lab_no' => 'required|string',
            'bill_code' => 'nullable|string',

            'doctor' => 'required|array',
            'doctor.code' => 'nullable|string',
            'doctor.name' => 'nullable|string',
            'doctor.address' => 'nullable|string',
            'doctor.phone' => 'nullable|string',
            'doctor.type' => 'nullable|string',

            'received_date' => 'nullable',
            'reported_date' => 'nullable',
            'collected_date' => 'nullable',
            'validated_by' => 'nullable|string',
            'report_status' => 'required|boolean',
            'package_name' => 'nullable|string',

            'patient' => 'required|array',
            'patient.icno' => 'required|string',
            'patient.gender' => 'nullable|in:F,M',
            'patient.age' => 'nullable|string',
            'patient.name' => 'nullable|string',
            'patient.tel' => 'nullable|string',
            'patient.ic_type' => 'in:NRIC,OTHERS',

            'results' => 'required|array',
            'results.*' => 'required|array',
            'results.*.panel_code' => 'nullable|string',
            'results.*.panel_sequence' => 'nullable|integer',
            'results.*.panel_remarks' => 'nullable|string',
            'results.*.result_status' => 'required|boolean',
            'results.*.tests' => 'required|array',

            'results.*.tests.*.test_name' => 'required|string',
            'results.*.tests.*.test_code' => 'nullable|string',
            'results.*.tests.*.result_value' => 'nullable|string',
            'results.*.tests.*.decimal_point' => 'nullable|string',
            'results.*.tests.*.result_flag' => 'nullable|string',
            'results.*.tests.*.unit' => 'nullable|string',
            'results.*.tests.*.ref_range' => 'nullable|string',
            'results.*.tests.*.test_note' => 'nullable|string',
            'results.*.tests.*.report_sequence' => 'nullable|integer',
        ];
    }

    /**
     * Generate a 3-letter location code from doctor name
     * Extracts location name after "clinic" or "pharmacy" keywords
     * 
     * @param string $doctorName
     * @return string|null
     */
    private function generateLocationCode(string $doctorName): ?string
    {
        $doctorName = strtolower($doctorName);

        // Find position after "clinic" or "pharmacy"
        $clinicPos = stripos($doctorName, 'clinic');
        $pharmacyPos = stripos($doctorName, 'pharmacy');

        $startPos = null;
        if ($clinicPos !== false) {
            $startPos = $clinicPos + strlen('clinic');
        } elseif ($pharmacyPos !== false) {
            $startPos = $pharmacyPos + strlen('pharmacy');
        }

        if ($startPos === null) {
            return null;
        }

        // Extract location name after clinic/pharmacy
        $locationPart = trim(substr($doctorName, $startPos));

        // Remove common words and get meaningful location words
        $locationWords = preg_split('/\s+/', $locationPart);
        $locationWords = array_filter($locationWords, function ($word) {
            // Filter out common words
            $commonWords = ['the', 'and', 'of', 'in', 'at', 'on', 'for', 'with', 'by'];
            return !in_array(strtolower($word), $commonWords) && strlen($word) > 1;
        });

        if (empty($locationWords)) {
            return null;
        }

        // Take first meaningful word and get 3 letters
        $firstWord = reset($locationWords);
        $code = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $firstWord), 0, 3));

        return strlen($code) >= 3 ? $code : null;
    }

    /**
     * Prepare the data for validation.
     *
     * This method cleans up the input data before validation:
     * - Removes non-numeric characters from patient_icno
     * - Casts panel_sequence and report_sequence to integers
     * - Trims whitespace from string fields in test items
     * - Skips panels or tests if improperly structured
     */
    protected function prepareForValidation(): void
    {
        $cleanedResults = [];

        if (is_array($this->results)) {
            foreach ($this->results as $panel => $data) {
                $panelCode = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $panel), 0, 3));
                if (!isset($data['tests']) || !is_array($data['tests'])) {
                    continue;
                }

                $data['tests'] = array_map(function ($test) {
                    $test_code = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $test['test_name']), 0, 3));
                    return [
                        'test_name' => trim($test['test_name'] ?? null),
                        'test_code' => trim($test_code ?? null),
                        'result_value' => trim((string) ($test['result_value'] ?? null)),
                        'decimal_point' => trim((string) ($test['decimal_point'] ?? null)),
                        'result_flag' => $test['result_flag'] ?? null,
                        'unit' => trim($test['unit'] ?? null),
                        'ref_range' => trim($test['ref_range'] ?? null),
                        'test_note' => $test['test_note'] ?? null,
                        'report_sequence' => (int) ($test['report_sequence'] ?? null),
                    ];
                }, $data['tests']);

                $cleanedResults[$panel] = [
                    'panel_code' => $panelCode,
                    'panel_sequence' => (int) ($data['panel_sequence'] ?? null),
                    'panel_remarks' => ($data['panel_remarks'] ?? null),
                    'result_status' => ($data['result_status'] ?? null),
                    'tests' => $data['tests'],
                ];
            }
        }

        $cleanedDoctor = [];
        if (is_array($this->doctor)) {
            $doctorName = strtolower(trim($this->doctor['name'] ?? ''));
            $doctorCode = trim($this->doctor['code'] ?? '');

            // Determine doctor type based on name content
            $doctorType = null;
            if (stripos($doctorName, 'clinic') !== false) {
                $doctorType = 'clinic';
            } elseif (stripos($doctorName, 'pharmacy') !== false) {
                $doctorType = 'pharmacy';
            }

            // Generate doctor code if empty, based on location name after clinic/pharmacy
            if (empty($doctorCode) && !empty($doctorName)) {
                $locationCode = $this->generateLocationCode($doctorName);
                if ($locationCode) {
                    $doctorCode = $locationCode;
                }
            }

            $cleanedDoctor = [
                'code' => $doctorCode,
                'name' => strtoupper($doctorName),
                'address' => trim($this->doctor['address'] ?? ''),
                'phone' => trim($this->doctor['phone'] ?? ''),
                'type' => strtoupper($doctorType),
            ];
        }

        $cleanedPatient = [];
        if (is_array($this->patient)) {
            $patientIcno = trim($this->patient['icno'] ?? '');
            $patientGender = trim($this->patient['gender'] ?? '');
            $patientAge = trim($this->patient['age'] ?? '');

            $icInfo = checkIcno($patientIcno);
            $icType = $icInfo['type'] ?? null;

            // If gender or age is null/empty, use checkIcno to extract info
            if (empty($patientGender) || empty($patientAge)) {
                $patientGender = $patientGender ?: ($icInfo['gender'] ?? null);
                $patientAge = $patientAge ?: ($icInfo['age'] ?? null);
            }

            $cleanedPatient = [
                'icno' => $patientIcno,
                'gender' => $patientGender,
                'age' => $patientAge,
                'name' => trim($this->patient['name'] ?? ''),
                'tel' => trim($this->patient['tel'] ?? ''),
                'ic_type' => $icType,
            ];
        }

        /** @var \Illuminate\Http\Request $this */
        $this->merge([
            'sending_facility' => null,
            'batch_id' => null,
            'received_date' => convertToDateTimeString($this->received_date),
            'reported_date' => convertToDateTimeString($this->reported_date),
            'collected_date' => convertToDateTimeString($this->collected_date),
            'doctor' => $cleanedDoctor,
            'patient' => $cleanedPatient,
            'results' => $cleanedResults,
        ]);
    }
}
