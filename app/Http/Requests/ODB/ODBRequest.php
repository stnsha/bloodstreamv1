<?php

namespace App\Http\Requests\ODB;

use Illuminate\Foundation\Http\FormRequest;

class ODBRequest extends FormRequest
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
            '*.icno' => 'required|string',
            '*.refid' => 'nullable|string',
            '*.month' => 'nullable|integer',
            '*.year' => 'nullable|integer',
            '*.labid' => 'nullable|integer'
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
            '*.icno.required' => 'IC No. is required.',
        ];
    }
}