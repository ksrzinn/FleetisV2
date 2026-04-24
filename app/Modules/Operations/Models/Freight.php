<?php

namespace App\Modules\Operations\Models;

use App\Modules\Commercial\Models\Client;
use App\Modules\Commercial\Models\FixedFreightRate;
use App\Modules\Commercial\Models\PerKmFreightRate;
use App\Modules\Fleet\Models\Driver;
use App\Modules\Fleet\Models\Vehicle;
use App\Modules\Operations\States\FreightState;
use App\Modules\Tenancy\Traits\BelongsToCompany;
use Database\Factories\Operations\FreightFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\ModelStates\HasStates;

class Freight extends Model
{
    /** @use HasFactory<FreightFactory> */
    use BelongsToCompany, HasFactory, HasStates, LogsActivity, SoftDeletes;

    public ?string $pendingStatusNote = null;

    protected $fillable = [
        'client_id', 'vehicle_id', 'trailer_id', 'driver_id',
        'pricing_model', 'fixed_rate_id', 'per_km_rate_id',
        'origin', 'destination',
        'distance_km', 'toll', 'fuel_price_per_liter', 'freight_value',
        'status', 'started_at', 'finished_at', 'completed_at',
    ];

    protected $casts = [
        'status'               => FreightState::class,
        'distance_km'          => 'decimal:2',
        'toll'                 => 'decimal:2',
        'fuel_price_per_liter' => 'decimal:4',
        'freight_value'        => 'decimal:2',
        'started_at'           => 'datetime',
        'finished_at'          => 'datetime',
        'completed_at'         => 'datetime',
    ];

    protected static function newFactory(): FreightFactory
    {
        return FreightFactory::new();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['status'])->logOnlyDirty();
    }

    /** @return BelongsTo<Client, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** @return BelongsTo<Vehicle, $this> */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /** @return BelongsTo<Vehicle, $this> */
    public function trailer(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'trailer_id');
    }

    /** @return BelongsTo<Driver, $this> */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    /** @return BelongsTo<FixedFreightRate, $this> */
    public function fixedRate(): BelongsTo
    {
        return $this->belongsTo(FixedFreightRate::class, 'fixed_rate_id');
    }

    /** @return BelongsTo<PerKmFreightRate, $this> */
    public function perKmRate(): BelongsTo
    {
        return $this->belongsTo(PerKmFreightRate::class, 'per_km_rate_id');
    }

    /** @return HasMany<FreightStatusHistory, $this> */
    public function statusHistory(): HasMany
    {
        return $this->hasMany(FreightStatusHistory::class)->orderBy('occurred_at');
    }

    public function estimatedLiters(): ?float
    {
        $consumo = $this->vehicle?->consumo_medio;
        if (! $this->distance_km || ! $consumo) {
            return null;
        }

        return round((float) $this->distance_km / (float) $consumo, 2);
    }

    public function estimatedFuelCost(): ?float
    {
        $liters = $this->estimatedLiters();
        if (! $liters || ! $this->fuel_price_per_liter) {
            return null;
        }

        return round($liters * (float) $this->fuel_price_per_liter, 2);
    }
}
