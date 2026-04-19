<?php
namespace App\Modules\Commercial\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePerKmFreightRateRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'rate_per_km' => ['required', 'numeric', 'min:0'],
            // state intentionally excluded — immutable (it's the unique key)
        ];
    }
}
