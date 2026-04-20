<?php

namespace App\Modules\Commercial\Models;

use App\Modules\Fleet\Models\VehicleType;
use App\Modules\Tenancy\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PerKmFreightRatePrice extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'per_km_freight_rate_id', 'vehicle_type_id', 'rate_per_km',
    ];

    protected $casts = ['rate_per_km' => 'decimal:4'];

    public function rate(): BelongsTo
    {
        return $this->belongsTo(PerKmFreightRate::class, 'per_km_freight_rate_id');
    }

    public function vehicleType(): BelongsTo
    {
        return $this->belongsTo(VehicleType::class);
    }
}
