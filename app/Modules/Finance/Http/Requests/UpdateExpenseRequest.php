<?php

namespace App\Modules\Finance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateExpenseRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'expense_category_id' => ['required', 'exists:expense_categories,id'],
            'amount'              => ['required', 'numeric', 'min:0.01'],
            'incurred_on'         => ['required', 'date'],
            'description'         => ['nullable', 'string', 'max:2000'],
            'vehicle_id'          => ['nullable', 'exists:vehicles,id'],
            'freight_id'          => ['nullable', 'exists:freights,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($this->filled('vehicle_id') && $this->filled('freight_id')) {
                $validator->errors()->add('vehicle_id', 'An expense cannot be linked to both a vehicle and a freight.');
            }
        });
    }
}
