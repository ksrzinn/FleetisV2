<?php

namespace App\Modules\Operations\Http\Requests;

use App\Modules\Operations\Models\Freight;
use Illuminate\Foundation\Http\FormRequest;

class TransitionFreightRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->toll !== null) {
            $this->merge(['toll' => str_replace(',', '.', preg_replace('/[^\d,]/', '', (string) $this->toll))]);
        }
        if ($this->fuel_price_per_liter !== null) {
            $this->merge(['fuel_price_per_liter' => str_replace(',', '.', preg_replace('/[^\d,]/', '', (string) $this->fuel_price_per_liter))]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var Freight $freight */
        $freight = $this->route('freight');
        $transition = $this->input('transition');
        $rules = ['transition' => ['required', 'in:to_in_route,to_finished,to_awaiting_payment']];

        if ($transition === 'to_finished') {
            $rules['toll'] = ['nullable', 'numeric', 'min:0'];
            $rules['fuel_price_per_liter'] = ['nullable', 'numeric', 'min:0'];

            if ($freight->pricing_model === 'per_km') {
                $rules['distance_km'] = ['required', 'numeric', 'min:1'];
                $rules['toll'] = ['required', 'numeric', 'min:0'];
            } else {
                $rules['distance_km'] = ['nullable', 'numeric', 'min:1'];
            }
        }

        return $rules;
    }
}
