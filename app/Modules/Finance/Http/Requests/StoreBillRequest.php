<?php

namespace App\Modules\Finance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBillRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // policy checked in controller
    }

    public function rules(): array
    {
        return [
            'supplier'           => ['required', 'string', 'max:200'],
            'description'        => ['nullable', 'string', 'max:2000'],
            'bill_type'          => ['required', Rule::in(['one_time', 'recurring', 'installment'])],
            'total_amount'       => ['required', 'numeric', 'min:0.01'],
            'due_date'           => ['required', 'date'],
            'recurrence_cadence' => [
                Rule::requiredIf(fn () => in_array($this->input('bill_type'), ['recurring', 'installment'])),
                'nullable',
                Rule::in(['weekly', 'biweekly', 'monthly', 'yearly']),
            ],
            'recurrence_day' => [
                Rule::requiredIf(fn () => in_array($this->input('bill_type'), ['recurring', 'installment'])
                    && in_array($this->input('recurrence_cadence'), ['monthly', 'yearly'])),
                'nullable',
                'integer',
                'min:1',
                'max:28',
            ],
            'recurrence_end'    => ['nullable', 'date', 'after:due_date'],
            'installment_count' => [
                Rule::requiredIf(fn () => $this->input('bill_type') === 'installment'),
                'nullable',
                'integer',
                'min:2',
                'max:360',
            ],
        ];
    }
}
