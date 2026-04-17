<?php

namespace App\Modules\Fleet\Models;

use Database\Factories\Fleet\VehicleTypeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VehicleType extends Model
{
    use HasFactory;

    protected $fillable = ['code', 'label', 'requires_trailer'];

    protected $casts = ['requires_trailer' => 'boolean'];

    protected static function newFactory(): VehicleTypeFactory
    {
        return VehicleTypeFactory::new();
    }

    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class);
    }
}
