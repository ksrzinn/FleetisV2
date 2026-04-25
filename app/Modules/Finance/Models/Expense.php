<?php

namespace App\Modules\Finance\Models;

use App\Modules\Fleet\Models\Vehicle;
use App\Modules\Operations\Models\Freight;
use App\Modules\Tenancy\Traits\BelongsToCompany;
use Database\Factories\Finance\ExpenseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    /** @use HasFactory<ExpenseFactory> */
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'company_id', 'expense_category_id', 'amount',
        'incurred_on', 'description', 'vehicle_id', 'freight_id',
    ];

    protected $casts = [
        'amount'      => 'decimal:2',
        'incurred_on' => 'date',
    ];

    protected static function newFactory(): ExpenseFactory
    {
        return ExpenseFactory::new();
    }

    /** @return BelongsTo<ExpenseCategory, $this> */
    public function expenseCategory(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class);
    }

    /** @return BelongsTo<Vehicle, $this> */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /** @return BelongsTo<Freight, $this> */
    public function freight(): BelongsTo
    {
        return $this->belongsTo(Freight::class);
    }
}
