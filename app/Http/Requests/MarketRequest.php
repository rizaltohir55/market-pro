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
    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'symbol'   => strtoupper($this->input('symbol', 'BTCUSDT')),
            'interval' => $this->input('interval', '1h'),
            'horizon'  => $this->input('horizon', 'default'),
            'limit'    => $this->input('limit', 200),
            'days'     => $this->input('days', 30),
            'resolution'=> $this->input('resolution', 'D'),
        ]);
    }

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
