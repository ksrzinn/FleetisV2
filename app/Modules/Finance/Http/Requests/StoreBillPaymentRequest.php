<?php

namespace App\Modules\Finance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBillPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // policy checked in controller
    }

    public function rules(): array
    {
        return [
            'amount'  => ['required', 'numeric', 'min:0.01'],
            'method'  => ['required', Rule::in(['pix', 'transferencia', 'dinheiro', 'cheque', 'boleto'])],
            'paid_at' => ['required', 'date'],
            'notes'   => ['nullable', 'string', 'max:1000'],
        ];
    }
}
