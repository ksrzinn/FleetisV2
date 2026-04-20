<?php

namespace App\Modules\Commercial\Models;

use App\Modules\Tenancy\Traits\BelongsToCompany;
use Database\Factories\Commercial\PerKmFreightRateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PerKmFreightRate extends Model
{
    /** @use HasFactory<PerKmFreightRateFactory> */
    use BelongsToCompany, HasFactory;

    protected static function newFactory(): PerKmFreightRateFactory
    {
        return PerKmFreightRateFactory::new();
    }

    protected $fillable = ['company_id', 'client_id', 'state'];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function prices(): HasMany
    {
        return $this->hasMany(PerKmFreightRatePrice::class);
    }
}
