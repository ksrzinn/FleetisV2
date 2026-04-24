<?php

namespace App\Modules\Finance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // policy checked in controller
    }

    public function rules(): array
    {
        /** @var \App\Modules\Finance\Models\Receivable $receivable */
        $receivable = $this->route('receivable');

        $remaining = bcsub((string) $receivable->amount_due, (string) $receivable->amount_paid, 2);

        return [
            'amount'  => ['required', 'numeric', 'gt:0', "max:{$remaining}"],
            'method'  => ['required', 'in:pix,transferencia,dinheiro,cheque,boleto'],
            'notes'   => ['nullable', 'string', 'max:500'],
            'paid_at' => ['nullable', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'amount.max' => 'O valor não pode ser superior ao saldo devedor.',
        ];
    }
}
