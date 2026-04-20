<?php
namespace App\Modules\Commercial\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateClientFreightTableRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $table = $this->route('freight_table');
        return [
            'name'   => [
                'required', 'string', 'max:255',
                Rule::unique('client_freight_tables', 'name')
                    ->where('client_id', $table->client_id)
                    ->ignore($table),
            ],
            'active' => ['boolean'],
            // pricing_model intentionally excluded — immutable after creation
        ];
    }
}
