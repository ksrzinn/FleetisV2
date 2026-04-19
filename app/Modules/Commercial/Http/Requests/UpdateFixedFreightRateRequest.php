<?php
namespace App\Modules\Commercial\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFixedFreightRateRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $rate = $this->route('fixed_rate');
        return [
            'name'      => [
                'required', 'string', 'max:255',
                Rule::unique('fixed_freight_rates', 'name')
                    ->where('client_freight_table_id', $rate->client_freight_table_id)
                    ->ignore($rate),
            ],
            'price'     => ['required', 'numeric', 'min:0'],
            'avg_km'    => ['nullable', 'numeric', 'min:0'],
            'tolls'     => ['nullable', 'numeric', 'min:0'],
            'fuel_cost' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
