<?php

namespace App\Http\Requests\ODB;

use Illuminate\Foundation\Http\FormRequest;

class MigrateRequest extends FormRequest
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
            'reports' => 'required|array|max:400',
            'reports.*.ref_id' => 'required',
            
            'reports.*.report' => 'required|array',
            'reports.*.report.ref_id' => 'required',
            'reports.*.report.ic' => 'nullable|string',
            'reports.*.report.name' => 'nullable|string',
            'reports.*.report.gender' => 'nullable|string',
            'reports.*.report.dob' => 'nullable|string',
            'reports.*.report.age' => 'nullable|string',

            'reports.*.report.lab_no' => 'nullable|string',
            'reports.*.report.test_panel' => 'nullable|string',

            'reports.*.report.collected_on' => 'nullable|string',
            'reports.*.report.register_date' => 'nullable|string',
            'reports.*.report.registered_by' => 'nullable|string',
            'reports.*.report.validated_date' => 'nullable|string',
            'reports.*.report.validated_by' => 'nullable|string',
            'reports.*.report.sampling_date' => 'nullable|string',
            'reports.*.report.exam_date' => 'nullable|string',
            'reports.*.report.received_date' => 'nullable|string',

            'reports.*.report.dr_name' => 'nullable|string',
            'reports.*.report.clinic_name' => 'nullable|string',

            'reports.*.report.overall_notes' => 'nullable|string',
            // 'reports.*.report.migration_attempts' => 'nullable|integer',

            'reports.*.parameter' => 'required|array',
            'reports.*.parameter.*.ref_id' => 'nullable',

            'reports.*.parameter.*.order_id' => 'required|integer',
            'reports.*.parameter.*.order_type' => 'required|integer',

            'reports.*.parameter.*.result_value' => 'nullable|string',
            'reports.*.parameter.*.result_flag' => 'nullable|string',
            'reports.*.parameter.*.test_notes' => 'nullable|string',

            'reports.*.parameter.*.seq' => 'nullable|integer',
            'reports.*.parameter.*.ref_range' => 'nullable|string',
            'reports.*.parameter.*.range_desc' => 'nullable|string',
            
            'reports.*.parameter.*.package_name' => 'nullable|string',
            'reports.*.parameter.*.panel_name' => 'nullable|string',

            'reports.*.parameter.*.panel_item' => 'nullable|array',
            'reports.*.parameter.*.panel_item.name' => 'nullable|string',
            'reports.*.parameter.*.panel_item.chinese_name' => 'nullable|string',
            'reports.*.parameter.*.panel_item.unit' => 'nullable|string'
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array
     */
    public function messages(): array
    {
        return [
            // Reports level
            'reports.required' => 'Reports array is required',
            'reports.max' => 'Maximum batch size is 400 reports',
            'reports.*.ref_id.required' => 'Each report must have a ref_id',

            // Report object
            'reports.*.report.required' => 'Report object is required',
            'reports.*.report.ref_id.required' => 'Report ref_id is required',
            'reports.*.report.ic.required' => 'IC number is required for each report',
            'reports.*.report.name.required' => 'Patient name is required for each report',
            'reports.*.report.gender.required' => 'Gender is required for each report',
            'reports.*.report.dob.required' => 'Date of birth is required for each report',
            'reports.*.report.age.required' => 'Age is required for each report',
            'reports.*.report.lab_no.required' => 'Lab number is required for each report',
            'reports.*.report.test_panel.required' => 'Test panel is required for each report',
            'reports.*.report.dr_name.required' => 'Doctor name is required for each report',
            'reports.*.report.clinic_name.required' => 'Clinic name is required for each report',

            // Parameters
            'reports.*.parameter.required' => 'Parameters array is required for each report',
            'reports.*.parameter.*.order_id.required' => 'Order ID is required for each parameter',
            'reports.*.parameter.*.order_type.required' => 'Order type is required for each parameter',
            'reports.*.parameter.*.result_value.required' => 'Result value is required for each parameter',
            'reports.*.parameter.*.result_flag.required' => 'Result flag is required for each parameter',
            'reports.*.parameter.*.test_notes.required' => 'Test notes is required for each parameter',
            'reports.*.parameter.*.seq.required' => 'Sequence is required for each parameter',
            'reports.*.parameter.*.range_desc.required' => 'Range description is required for each parameter',
        ];
    }
}