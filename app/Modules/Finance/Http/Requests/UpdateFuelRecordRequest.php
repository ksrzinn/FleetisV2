<?php

namespace App\Modules\Finance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFuelRecordRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'vehicle_id'      => ['required', 'exists:vehicles,id'],
            'driver_id'       => ['nullable', 'exists:drivers,id'],
            'freight_id'      => ['nullable', 'exists:freights,id'],
            'liters'          => ['required', 'numeric', 'min:0.001'],
            'price_per_liter' => ['required', 'numeric', 'min:0.0001'],
            'odometer_km'     => ['nullable', 'integer', 'min:0'],
            'fueled_at'       => ['required', 'date'],
            'station'         => ['nullable', 'string', 'max:150'],
        ];
    }
}
