<?php

namespace App\Modules\Finance\Models;

use App\Modules\Tenancy\Traits\BelongsToCompany;
use Database\Factories\Finance\BillInstallmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BillInstallment extends Model
{
    /** @use HasFactory<BillInstallmentFactory> */
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'company_id', 'bill_id', 'sequence', 'amount',
        'due_date', 'paid_amount', 'paid_at',
    ];

    protected $casts = [
        'amount'      => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'due_date'    => 'date',
        'paid_at'     => 'datetime',
    ];

    protected $appends = ['status'];

    protected static function newFactory(): BillInstallmentFactory
    {
        return BillInstallmentFactory::new();
    }

    /** @return BelongsTo<Bill, $this> */
    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class);
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'payable_id')
            ->where('payable_type', 'bill_installment')
            ->orderBy('paid_at');
    }

    public function getStatusAttribute(): string
    {
        $paid  = (float) ($this->paid_amount ?? 0);
        $total = (float) $this->amount;

        if ($paid >= $total) {
            return 'paid';
        }

        if ($paid > 0) {
            return 'partially_paid';
        }

        if ($this->due_date->isPast()) {
            return 'overdue';
        }

        return 'open';
    }

    public function isFullyPaid(): bool
    {
        return $this->status === 'paid';
    }
}
