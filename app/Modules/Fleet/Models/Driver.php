<?php

namespace App\Modules\Fleet\Models;

use App\Modules\Tenancy\Traits\BelongsToCompany;
use Database\Factories\Fleet\DriverFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Driver extends Model
{
    /** @use HasFactory<DriverFactory> */
    use BelongsToCompany, HasFactory, SoftDeletes;

    protected $fillable = ['name', 'phone', 'birth_date', 'cpf', 'active'];

    protected $casts = ['active' => 'boolean', 'birth_date' => 'date'];

    protected static function newFactory(): DriverFactory
    {
        return DriverFactory::new();
    }

    /** @return HasMany<DriverCompensation, $this> */
    public function compensations(): HasMany
    {
        return $this->hasMany(DriverCompensation::class);
    }

    /** @return HasMany<DriverCompensation, $this> */
    public function activeCompensations(): HasMany
    {
        return $this->hasMany(DriverCompensation::class)->whereNull('effective_to');
    }
}
