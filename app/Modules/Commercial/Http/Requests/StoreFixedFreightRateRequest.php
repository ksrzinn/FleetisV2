<?php
namespace App\Modules\Commercial\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFixedFreightRateRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name'      => [
                'required', 'string', 'max:255',
                Rule::unique('fixed_freight_rates', 'name')
                    ->where('client_freight_table_id', $this->route('freight_table')->id),
            ],
            'price'     => ['required', 'numeric', 'min:0'],
            'avg_km'    => ['nullable', 'numeric', 'min:0'],
            'tolls'     => ['nullable', 'numeric', 'min:0'],
            'fuel_cost' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
