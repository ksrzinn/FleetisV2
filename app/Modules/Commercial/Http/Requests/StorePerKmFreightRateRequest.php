<?php
namespace App\Modules\Commercial\Http\Requests;

use App\Modules\Commercial\Rules\ValidBrazilianState;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePerKmFreightRateRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'state'                     => [
                'required', 'string', 'size:2', new ValidBrazilianState,
                Rule::unique('per_km_freight_rates', 'state')
                    ->where('company_id', auth()->user()->company_id)
                    ->where('client_id', $this->route('client')->id),
            ],
            'prices'                    => ['required', 'array', 'min:1'],
            'prices.*.vehicle_type_id'  => ['required', 'integer', 'exists:vehicle_types,id'],
            'prices.*.rate_per_km'      => ['required', 'numeric', 'min:0'],
        ];
    }
}
