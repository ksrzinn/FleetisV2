<?php

namespace App\Modules\Tenancy\Models;

use Database\Factories\Tenancy\CompanyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    /** @use HasFactory<CompanyFactory> */
    use HasFactory;

    protected $fillable = ['name', 'cnpj', 'timezone', 'status'];

    protected static function newFactory(): CompanyFactory
    {
        return CompanyFactory::new();
    }
}
