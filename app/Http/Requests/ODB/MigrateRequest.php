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
     * IMPORTANT: This is a batch migration endpoint that accepts partial success.
     * We validate STRUCTURE only, not individual field presence.
     * Field validation happens in the controller which skips invalid reports.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Batch structure validation
            'reports' => 'required|array|min:1|max:400',

            // Basic structure check - ensure each report has required top-level keys
            // Using 'sometimes' instead of wildcard to avoid deep traversal
            'reports.*.ref_id' => 'sometimes|required',
            'reports.*.report' => 'sometimes|required|array',
            'reports.*.parameter' => 'sometimes|array',
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
            'reports.required' => 'Reports array is required',
            'reports.array' => 'Reports must be an array',
            'reports.min' => 'At least one report is required',
            'reports.max' => 'Maximum batch size is 400 reports',

            'reports.*.ref_id.required' => 'Each report must have a ref_id',
            'reports.*.report.required' => 'Each report must have a report object',
            'reports.*.report.array' => 'Report object must be an array',
            'reports.*.parameter.array' => 'Parameter must be an array',
        ];
    }

    /**
     * Configure the validator instance.
     * Stop on first failure for batch requests to fail fast.
     */
    public function withValidator($validator)
    {
        $validator->stopOnFirstFailure();
    }
}
