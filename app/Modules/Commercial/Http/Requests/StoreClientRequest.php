<?php
namespace App\Modules\Commercial\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreClientRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name'                => ['required', 'string', 'max:255'],
            'document'            => [
                'required', 'string',
                'cpf_cnpj',
                Rule::unique('clients', 'document')
                    ->where('company_id', auth()->user()->company_id),
            ],
            'email'               => ['nullable', 'email', 'max:255'],
            'phone'               => ['nullable', 'string', 'max:20'],
            'address_street'      => ['nullable', 'string', 'max:255'],
            'address_number'      => ['nullable', 'string', 'max:20'],
            'address_complement'  => ['nullable', 'string', 'max:255'],
            'address_neighborhood'=> ['nullable', 'string', 'max:255'],
            'address_city'        => ['nullable', 'string', 'max:255'],
            'address_state'       => ['nullable', 'string', 'size:2'],
            'address_zip'         => ['nullable', 'string', 'size:8'],
            'active'              => ['boolean'],
            'payment_term_type'  => ['nullable', 'in:monthly,weekly,daily,days_after'],
            'payment_term_value' => ['nullable', 'integer', 'min:1'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->document) {
            $this->merge(['document' => preg_replace('/\D/', '', $this->document)]);
        }
    }
}
