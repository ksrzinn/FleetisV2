<?php

namespace App\Modules\Commercial\Models;

use App\Modules\Tenancy\Traits\BelongsToCompany;
use Database\Factories\Commercial\ClientFreightTableFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClientFreightTable extends Model
{
    /** @use HasFactory<ClientFreightTableFactory> */
    use BelongsToCompany, HasFactory;

    protected static function newFactory(): ClientFreightTableFactory
    {
        return ClientFreightTableFactory::new();
    }

    protected $fillable = ['company_id', 'client_id', 'name', 'pricing_model', 'active'];

    protected $casts = ['active' => 'boolean'];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function fixedRates(): HasMany
    {
        return $this->hasMany(FixedFreightRate::class);
    }
}
