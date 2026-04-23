<?php

namespace App\Modules\Commercial\Models;

use App\Modules\Fleet\Models\VehicleType;
use App\Modules\Tenancy\Traits\BelongsToCompany;
use Database\Factories\Commercial\FixedFreightRatePriceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FixedFreightRatePrice extends Model
{
    /** @use HasFactory<FixedFreightRatePriceFactory> */
    use BelongsToCompany, HasFactory;

    protected static function newFactory(): FixedFreightRatePriceFactory
    {
        return FixedFreightRatePriceFactory::new();
    }

    protected $fillable = [
        'company_id', 'fixed_freight_rate_id', 'vehicle_type_id',
        'price', 'tolls', 'fuel_cost',
    ];

    protected $casts = [
        'price'     => 'decimal:2',
        'tolls'     => 'decimal:2',
        'fuel_cost' => 'decimal:2',
    ];

    public function rate(): BelongsTo
    {
        return $this->belongsTo(FixedFreightRate::class, 'fixed_freight_rate_id');
    }

    public function vehicleType(): BelongsTo
    {
        return $this->belongsTo(VehicleType::class);
    }
}
