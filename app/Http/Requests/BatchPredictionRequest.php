<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BatchPredictionRequest extends FormRequest
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
     */
    public function rules(): array
    {
        return [
            'symbols'  => 'nullable', // Can be array or string
            'interval' => 'nullable|string|max:10',
        ];
    }
}
