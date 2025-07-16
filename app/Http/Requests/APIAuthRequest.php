<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class APIAuthRequest extends FormRequest
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
            'username' => 'required|string|exists:lab_credentials,username',
            'password' =>  [
                'required',
                'min:8',
            ],
        ];
    }

    public function messages()
    {
        return [
            'username.required' => 'Username is required.',
            'username.exists' => 'Username not found.',
            'password.required' => 'Password is required.',
            'password.min' => 'Password must be at least 8 characters.',
        ];
    }
}
