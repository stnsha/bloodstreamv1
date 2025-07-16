<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LabRequest extends FormRequest
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
            'name' => ['required', 'string'],
            'path' => ['nullable', 'string'],
            'code' => ['nullable', 'string'],
            'status' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'The lab name is required.',
            'name.string'   => 'The lab name must be a valid string.',
            'path.string'   => 'The path must be a valid string.',
            'code.string'   => 'The code must be a valid string.',
            'status.string' => 'The status must be a valid string.',
        ];
    }
}
