<?php

namespace App\Modules\Finance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMaintenanceRecordRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'vehicle_id'   => ['required', 'exists:vehicles,id'],
            'type'         => ['required', Rule::in(['preventive', 'corrective', 'emergency', 'routine'])],
            'description'  => ['required', 'string', 'max:2000'],
            'cost'         => ['required', 'numeric', 'min:0.01'],
            'odometer_km'  => ['nullable', 'integer', 'min:0'],
            'performed_on' => ['required', 'date'],
            'provider'     => ['nullable', 'string', 'max:150'],
        ];
    }
}
