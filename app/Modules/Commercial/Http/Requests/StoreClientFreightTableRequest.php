<?php
namespace App\Modules\Commercial\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreClientFreightTableRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name'          => [
                'required', 'string', 'max:255',
                Rule::unique('client_freight_tables', 'name')
                    ->where('client_id', $this->route('client')->id),
            ],
            'pricing_model' => ['required', Rule::in(['fixed', 'per_km'])],
            'active'        => ['boolean'],
        ];
    }
}
