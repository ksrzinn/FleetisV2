<?php

namespace App\Modules\Finance\Models;

use App\Modules\Fleet\Models\Vehicle;
use App\Modules\Tenancy\Traits\BelongsToCompany;
use Database\Factories\Finance\MaintenanceRecordFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenanceRecord extends Model
{
    /** @use HasFactory<MaintenanceRecordFactory> */
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'company_id', 'vehicle_id', 'type', 'description',
        'cost', 'odometer_km', 'performed_on', 'provider',
    ];

    protected $casts = [
        'cost'         => 'decimal:2',
        'performed_on' => 'date',
    ];

    protected static function newFactory(): MaintenanceRecordFactory
    {
        return MaintenanceRecordFactory::new();
    }

    /** @return BelongsTo<Vehicle, $this> */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }
}
