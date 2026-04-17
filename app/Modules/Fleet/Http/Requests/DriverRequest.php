<?php

namespace App\Modules\Fleet\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DriverRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $companyId = $this->user()->company_id;
        $driverId = $this->route('driver')?->id;

        return [
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:20'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'cpf' => [
                'required', 'cpf',
                Rule::unique('drivers', 'cpf')
                    ->where('company_id', $companyId)
                    ->ignore($driverId),
            ],
            'active' => ['boolean'],
        ];
    }
}
