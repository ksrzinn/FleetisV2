<?php

namespace App\Modules\Fleet\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DriverCompensationRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $type = $this->input('type');

        return [
            'type'           => ['required', 'in:percentage,fixed_per_freight,monthly_salary'],
            'percentage'     => [$type === 'percentage' ? 'required' : 'prohibited', 'numeric', 'min:0.01', 'max:100'],
            'fixed_amount'   => [$type === 'fixed_per_freight' ? 'required' : 'prohibited', 'numeric', 'min:0.01'],
            'monthly_salary' => [$type === 'monthly_salary' ? 'required' : 'prohibited', 'numeric', 'min:0.01'],
            'effective_from' => ['required', 'date'],
        ];
    }
}
