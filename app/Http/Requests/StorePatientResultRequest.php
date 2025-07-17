<?php

namespace App\Http\Requests;

use App\Models\Patient;
use Illuminate\Foundation\Http\FormRequest;

/**
 * @property array $patient
 * @property string $reference_id
 * @property string $lab_no
 * @property string $bill_code
 * @property string $doctor_code
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
            'patient_age' => 'nullable|integer',
            'patient_gender' => 'nullable|in:F,M',
            'reference_id' => 'nullable|string',
            'lab_no' => 'required|string',
            'bill_code' => 'nullable|string',
            'doctor_code' => 'required|string',
            'received_date' => 'nullable',
            'reported_date' => 'nullable',
            'collected_date' => 'nullable',
            'validated_by' => 'nullable|string',
            'package_name' => 'nullable|string',

            'patient' => 'required|array',
            'patient.patient_icno' => 'required|string',
            'patient.patient_gender' => 'nullable|in:Female,Male',
            'patient.patient_age' => 'nullable|string',
            'patient.patient_name' => 'nullable|string',
            'patient.patient_tel' => 'nullable|string',
            'patient.ic_type' => 'in:NRIC,OTHERS',

            'results' => 'required|array',
            'results.*' => 'required|array',
            'results.*.panel_code' => 'nullable|string',
            'results.*.panel_sequence' => 'nullable|integer',
            'results.*.panel_remarks' => 'nullable|string',
            'results.*.result_status' => 'required|integer',
            'results.*.tests' => 'required|array',

            'results.*.tests.*.test_name' => 'required|string',
            'results.*.tests.*.result_value' => 'nullable|string',
            'results.*.tests.*.decimal_point' => 'nullable|string',
            'results.*.tests.*.result_flag' => 'nullable|string',
            'results.*.tests.*.unit' => 'nullable|string',
            'results.*.tests.*.ref_range' => 'nullable|string',
            'results.*.tests.*.test_note' => 'nullable|string',
            'results.*.tests.*.item_sequence' => 'nullable|integer',
        ];
    }

    /**
     * Prepare the data for validation.
     *
     * This method cleans up the input data before validation:
     * - Removes non-numeric characters from patient_icno
     * - Casts panel_sequence and item_sequence to integers
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
                    return [
                        'test_name' => trim($test['test_name'] ?? null),
                        'result_value' => trim((string) ($test['result_value'] ?? null)),
                        'decimal_point' => trim((string) ($test['decimal_point'] ?? null)),
                        'result_flag' => $test['result_flag'] ?? null,
                        'unit' => trim($test['unit'] ?? null),
                        'ref_range' => trim($test['ref_range'] ?? null),
                        'test_note' => $test['test_note'] ?? null,
                        'item_sequence' => (int) ($test['item_sequence'] ?? null),
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

        $cleanedPatient = [];
        if (is_array($this->patient)) {
            $patientIcno = trim($this->patient['patient_icno'] ?? '');
            $patientGender = trim($this->patient['patient_gender'] ?? '');
            $patientAge = trim($this->patient['patient_age'] ?? '');

            $icInfo = checkIcno($patientIcno);
            $icType = $icInfo['type'] ?? null;

            // If gender or age is null/empty, use checkIcno to extract info
            if (empty($patientGender) || empty($patientAge)) {
                $patientGender = $patientGender ?: ($icInfo['gender'] ?? null);
                $patientAge = $patientAge ?: ($icInfo['age'] ?? null);
            }

            $cleanedPatient = [
                'patient_icno' => $patientIcno,
                'patient_gender' => $patientGender,
                'patient_age' => $patientAge,
                'patient_name' => trim($this->patient['patient_name'] ?? ''),
                'patient_tel' => trim($this->patient['patient_tel'] ?? ''),
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
            'patient' => $cleanedPatient,
            'results' => $cleanedResults,
        ]);
    }
}
