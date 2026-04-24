<?php

namespace App\Modules\Finance\Models;

use App\Modules\Commercial\Models\Client;
use App\Modules\Operations\Models\Freight;
use App\Modules\Tenancy\Traits\BelongsToCompany;
use Database\Factories\Finance\ReceivableFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Receivable extends Model
{
    /** @use HasFactory<ReceivableFactory> */
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'company_id', 'client_id', 'freight_id',
        'amount_due', 'amount_paid', 'due_date', 'status',
    ];

    protected $casts = [
        'amount_due'  => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'due_date'    => 'date',
    ];

    protected static function newFactory(): ReceivableFactory
    {
        return ReceivableFactory::new();
    }

    public function remainingBalance(): string
    {
        return bcsub((string) $this->amount_due, (string) $this->amount_paid, 2);
    }

    /** @return BelongsTo<Client, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** @return BelongsTo<Freight, $this> */
    public function freight(): BelongsTo
    {
        return $this->belongsTo(Freight::class);
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'payable_id')
            ->where('payable_type', 'receivable')
            ->orderBy('paid_at');
    }
}
