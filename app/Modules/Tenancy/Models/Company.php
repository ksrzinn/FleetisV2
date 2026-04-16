<?php

namespace App\Modules\Tenancy\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'cnpj', 'timezone', 'status'];
}
