<?php

namespace Tests\Fixtures;

use App\Modules\Tenancy\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class TenantProbe extends Model
{
    use BelongsToCompany;

    protected $fillable = ['label', 'company_id'];
}
