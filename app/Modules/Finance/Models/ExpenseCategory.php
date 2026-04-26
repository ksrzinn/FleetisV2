<?php

namespace App\Modules\Finance\Models;

use App\Modules\Tenancy\Traits\BelongsToCompany;
use Database\Factories\Finance\ExpenseCategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExpenseCategory extends Model
{
    /** @use HasFactory<ExpenseCategoryFactory> */
    use BelongsToCompany, HasFactory;

    protected $fillable = ['company_id', 'name', 'color'];

    const COLOR_PALETTE = [
        '#EF4444', '#F97316', '#EAB308', '#22C55E', '#14B8A6',
        '#3B82F6', '#6366F1', '#8B5CF6', '#EC4899', '#06B6D4',
        '#84CC16', '#F59E0B',
    ];

    const DEFAULTS = ['Combustível', 'Pedágio', 'Manutenção', 'Seguro', 'Administrativo'];

    public static function nextColor(int $existingCount): string
    {
        return self::COLOR_PALETTE[$existingCount % count(self::COLOR_PALETTE)];
    }

    protected static function newFactory(): ExpenseCategoryFactory
    {
        return ExpenseCategoryFactory::new();
    }

    /** @return HasMany<Expense, $this> */
    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }
}
