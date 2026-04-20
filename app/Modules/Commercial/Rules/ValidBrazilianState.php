<?php
namespace App\Modules\Commercial\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidBrazilianState implements ValidationRule
{
    private const STATES = [
        'AC','AL','AP','AM','BA','CE','DF','ES','GO','MA',
        'MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN',
        'RS','RO','RR','SC','SP','SE','TO',
    ];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! in_array(strtoupper($value), self::STATES, true)) {
            $fail('The :attribute must be a valid Brazilian state code (UF).');
        }
    }
}
