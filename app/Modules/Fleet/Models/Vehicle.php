<?php

namespace App\Modules\Fleet\Models;

use App\Modules\Tenancy\Traits\BelongsToCompany;
use Database\Factories\Fleet\VehicleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vehicle extends Model
{
    /** @use HasFactory<VehicleFactory> */
    use BelongsToCompany, HasFactory, SoftDeletes;

    protected $fillable = [
        'kind', 'vehicle_type_id', 'license_plate', 'renavam',
        'brand', 'model', 'year', 'notes', 'active',
    ];

    protected $casts = ['active' => 'boolean', 'year' => 'integer'];

    protected static function newFactory(): VehicleFactory
    {
        return VehicleFactory::new();
    }

    /** @return BelongsTo<VehicleType, $this> */
    public function vehicleType(): BelongsTo
    {
        return $this->belongsTo(VehicleType::class);
    }
}
