<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MarketRequest extends FormRequest
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
            'symbol'   => 'nullable|string|max:20',
            'symbols'  => 'nullable|string', // Used in some methods as comma-separated
            'interval' => 'nullable|string|max:10',
            'limit'    => 'nullable|integer|min:1|max:1000',
            'days'     => 'nullable|integer|min:1|max:365',
            'from'     => 'nullable',
            'to'       => 'nullable',
            'resolution' => 'nullable|string|max:5',
        ];
    }
}
