<?php

namespace App\Modules\Commercial\Models;

use App\Modules\Tenancy\Traits\BelongsToCompany;
use Database\Factories\Commercial\FixedFreightRateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FixedFreightRate extends Model
{
    /** @use HasFactory<FixedFreightRateFactory> */
    use BelongsToCompany, HasFactory;

    protected static function newFactory(): FixedFreightRateFactory
    {
        return FixedFreightRateFactory::new();
    }

    protected $fillable = ['company_id', 'client_freight_table_id', 'name', 'avg_km'];

    protected $casts = ['avg_km' => 'decimal:2'];

    public function freightTable(): BelongsTo
    {
        return $this->belongsTo(ClientFreightTable::class, 'client_freight_table_id');
    }

    public function prices(): HasMany
    {
        return $this->hasMany(FixedFreightRatePrice::class);
    }
}
