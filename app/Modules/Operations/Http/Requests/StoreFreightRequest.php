<?php

namespace App\Modules\Operations\Http\Requests;

use App\Modules\Fleet\Models\Vehicle;
use Illuminate\Foundation\Http\FormRequest;

class StoreFreightRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $rules = [
            'client_id'      => ['required', 'exists:clients,id'],
            'pricing_model'  => ['required', 'in:fixed,per_km'],
            'vehicle_id'     => ['required', 'exists:vehicles,id'],
            'trailer_id'     => ['nullable', 'exists:vehicles,id'],
            'driver_id'      => ['nullable', 'exists:drivers,id'],
            'fixed_rate_id'  => ['required_if:pricing_model,fixed', 'nullable', 'exists:fixed_freight_rates,id'],
            'per_km_rate_id' => ['required_if:pricing_model,per_km', 'nullable', 'exists:per_km_freight_rates,id'],
            'origin'         => ['required_if:pricing_model,per_km', 'nullable', 'string', 'max:150'],
            'destination'    => ['required_if:pricing_model,per_km', 'nullable', 'string', 'max:150'],
        ];

        $vehicleId = $this->input('vehicle_id');
        if ($vehicleId) {
            $vehicle = Vehicle::with('vehicleType')->find($vehicleId);
            if ($vehicle?->vehicleType?->requires_trailer) {
                $rules['trailer_id'] = ['required', 'exists:vehicles,id'];
            }
        }

        return $rules;
    }
}
