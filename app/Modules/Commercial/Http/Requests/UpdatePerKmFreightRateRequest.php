<?php
namespace App\Modules\Commercial\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePerKmFreightRateRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'prices'                    => ['required', 'array', 'min:1'],
            'prices.*.vehicle_type_id'  => ['required', 'integer', 'exists:vehicle_types,id'],
            'prices.*.rate_per_km'      => ['required', 'numeric', 'min:0'],
        ];
    }
}
