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
            'name'                      => [
                'required', 'string', 'max:255',
                Rule::unique('fixed_freight_rates', 'name')
                    ->where('client_freight_table_id', $rate->client_freight_table_id)
                    ->ignore($rate),
            ],
            'avg_km'                    => ['nullable', 'numeric', 'min:0'],
            'prices'                    => ['required', 'array', 'min:1'],
            'prices.*.vehicle_type_id'  => ['required', 'integer', 'exists:vehicle_types,id'],
            'prices.*.price'            => ['required', 'numeric', 'min:0'],
            'prices.*.tolls'            => ['nullable', 'numeric', 'min:0'],
            'prices.*.fuel_cost'        => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
