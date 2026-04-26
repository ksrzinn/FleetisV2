<?php

namespace App\Modules\Finance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBillRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // policy checked in controller
    }

    public function rules(): array
    {
        return [
            'supplier'           => ['sometimes', 'string', 'max:200'],
            'description'        => ['nullable', 'string', 'max:2000'],
            'bill_type'          => ['sometimes', Rule::in(['one_time', 'recurring', 'installment'])],
            'total_amount'       => ['sometimes', 'numeric', 'min:0.01'],
            'due_date'           => ['sometimes', 'date'],
            'recurrence_cadence' => ['nullable', Rule::in(['weekly', 'biweekly', 'monthly', 'yearly'])],
            'recurrence_day'     => ['nullable', 'integer', 'min:1', 'max:28'],
            'recurrence_end'     => ['nullable', 'date'],
            'installment_count'  => ['nullable', 'integer', 'min:2', 'max:360'],
        ];
    }
}
