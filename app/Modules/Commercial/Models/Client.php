<?php

namespace App\Modules\Commercial\Models;

use App\Modules\Tenancy\Traits\BelongsToCompany;
use Database\Factories\Commercial\ClientFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends Model
{
    /** @use HasFactory<ClientFactory> */
    use BelongsToCompany, HasFactory, SoftDeletes;

    protected static function newFactory(): ClientFactory
    {
        return ClientFactory::new();
    }

    protected $fillable = [
        'company_id', 'name', 'document', 'email', 'phone',
        'address_street', 'address_number', 'address_complement',
        'address_neighborhood', 'address_city', 'address_state',
        'address_zip', 'active',
    ];

    protected $casts = ['active' => 'boolean'];

    public function getDocumentTypeAttribute(): string
    {
        return strlen($this->document) === 11 ? 'cpf' : 'cnpj';
    }

    public function freightTables(): HasMany
    {
        return $this->hasMany(ClientFreightTable::class);
    }

    public function perKmRates(): HasMany
    {
        return $this->hasMany(PerKmFreightRate::class);
    }
}
