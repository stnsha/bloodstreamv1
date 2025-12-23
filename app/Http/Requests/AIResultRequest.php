<?php

namespace App\Http\Requests;

use App\Models\AIError;
use App\Models\AIReview;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\ValidationException;


class AIResultRequest extends FormRequest
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
            'success' => ['required', 'boolean'],
            'status' => ['required', 'string', 'in:DONE'],
            'test_result_id' => ['required', 'integer'],

            'data' => ['required', 'array'],
            'data.ai_analysis' => ['required', 'array'],

            'data.ai_analysis.success' => ['required', 'boolean'],
            'data.ai_analysis.status' => ['required', 'integer'],
            'data.ai_analysis.answer' => ['required', 'array'],

            // section_a1: array of objects
            'data.ai_analysis.answer.section_a1' => ['required', 'array'],
            'data.ai_analysis.answer.section_a1.*.health_area' => ['required', 'string'],
            'data.ai_analysis.answer.section_a1.*.status' => ['required', 'string'],
            'data.ai_analysis.answer.section_a1.*.notes' => ['nullable', 'string'],

            // section_a2: array of strings
            'data.ai_analysis.answer.section_a2' => ['required', 'array'],
            'data.ai_analysis.answer.section_a2.*' => ['string'],

            // section_b: array of action plans
            'data.ai_analysis.answer.section_b' => ['required', 'array'],
            'data.ai_analysis.answer.section_b.*.timeline' => ['required', 'string'],
            'data.ai_analysis.answer.section_b.*.action' => ['required', 'string'],
            'data.ai_analysis.answer.section_b.*.goals' => ['required', 'string'],
            'data.ai_analysis.answer.section_b.*.alpro_care' => ['nullable', 'string'],
            'data.ai_analysis.answer.section_b.*.appointment' => ['nullable'],

            // section_c: sometimes empty string
            'data.ai_analysis.answer.section_c' => ['nullable'],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        $aiReview = AIReview::where('test_result_id', $this->input('test_result_id'))->first();

        if($aiReview)
        {
            $aiReview->forceDelete();
        }
        
        AIError::create([
            'test_result_id' => $this->input('test_result_id'),
            'processing_status' => 'FAILED',
            'http_status' => 500,
            'error_message' => $validator->errors()->toJson(),
            'attempt_count' => 1,
        ]);

        throw new ValidationException($validator);
    }

}