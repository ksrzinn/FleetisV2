<?php

namespace App\Modules\Finance\Models;

use App\Modules\Tenancy\Traits\BelongsToCompany;
use Database\Factories\Finance\BillFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Bill extends Model
{
    /** @use HasFactory<BillFactory> */
    use BelongsToCompany, HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id', 'supplier', 'description', 'bill_type', 'total_amount',
        'due_date', 'recurrence_cadence', 'recurrence_day', 'recurrence_end',
        'installment_count',
    ];

    protected $casts = [
        'total_amount'   => 'decimal:2',
        'due_date'       => 'date',
        'recurrence_end' => 'date',
    ];

    protected static function newFactory(): BillFactory
    {
        return BillFactory::new();
    }

    /** @return HasMany<BillInstallment, $this> */
    public function installments(): HasMany
    {
        return $this->hasMany(BillInstallment::class)->orderBy('due_date');
    }

    public function hasPayments(): bool
    {
        return $this->installments()->whereNotNull('paid_amount')->exists();
    }

    public function outstandingBalance(): string
    {
        $totalAmount = (string) ($this->installments()->sum('amount') ?? 0);
        $totalPaid   = (string) ($this->installments()->sum('paid_amount') ?? 0);

        return bcsub($totalAmount, $totalPaid, 2);
    }
}
