<?php

namespace App\Modules\Fleet\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VehicleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $companyId = $this->user()->company_id;
        $vehicleId = $this->route('vehicle')?->id;

        return [
            'kind' => ['required', 'in:vehicle,trailer'],
            'vehicle_type_id' => ['required', 'exists:vehicle_types,id'],
            'license_plate' => [
                'required', 'string', 'max:10',
                Rule::unique('vehicles', 'license_plate')
                    ->where('company_id', $companyId)
                    ->ignore($vehicleId),
            ],
            'renavam' => ['nullable', 'string', 'max:11'],
            'brand' => ['required', 'string', 'max:100'],
            'model' => ['required', 'string', 'max:100'],
            'year' => ['required', 'integer', 'min:1980', 'max:'.(date('Y') + 1)],
            'notes' => ['nullable', 'string'],
            'active' => ['boolean'],
        ];
    }
}
