<?php

namespace App\Modules\Finance\Models;

use App\Modules\Fleet\Models\Driver;
use App\Modules\Fleet\Models\Vehicle;
use App\Modules\Operations\Models\Freight;
use App\Modules\Tenancy\Traits\BelongsToCompany;
use Database\Factories\Finance\FuelRecordFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FuelRecord extends Model
{
    /** @use HasFactory<FuelRecordFactory> */
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'company_id', 'vehicle_id', 'driver_id', 'freight_id',
        'liters', 'price_per_liter', 'total_cost',
        'odometer_km', 'fueled_at', 'station',
    ];

    protected $casts = [
        'liters'          => 'decimal:3',
        'price_per_liter' => 'decimal:4',
        'total_cost'      => 'decimal:2',
        'fueled_at'       => 'date',
    ];

    protected static function newFactory(): FuelRecordFactory
    {
        return FuelRecordFactory::new();
    }

    /** @return BelongsTo<Vehicle, $this> */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /** @return BelongsTo<Driver, $this> */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    /** @return BelongsTo<Freight, $this> */
    public function freight(): BelongsTo
    {
        return $this->belongsTo(Freight::class);
    }
}
