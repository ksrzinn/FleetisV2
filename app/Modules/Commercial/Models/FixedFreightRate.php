<?php

namespace App\Modules\Commercial\Models;

use App\Modules\Tenancy\Traits\BelongsToCompany;
use Database\Factories\Commercial\FixedFreightRateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FixedFreightRate extends Model
{
    /** @use HasFactory<FixedFreightRateFactory> */
    use BelongsToCompany, HasFactory;

    protected static function newFactory(): FixedFreightRateFactory
    {
        return FixedFreightRateFactory::new();
    }

    protected $fillable = [
        'company_id', 'client_freight_table_id', 'name',
        'price', 'avg_km', 'tolls', 'fuel_cost',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'avg_km' => 'decimal:2',
        'tolls' => 'decimal:2',
        'fuel_cost' => 'decimal:2',
    ];

    public function freightTable(): BelongsTo
    {
        return $this->belongsTo(ClientFreightTable::class, 'client_freight_table_id');
    }
}
