<?php

namespace App\Modules\Tenancy\Traits;

use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToCompany
{
    public static function bootBelongsToCompany(): void
    {
        static::addGlobalScope(new CompanyScope);

        static::creating(function ($model) {
            if (empty($model->company_id) && ($id = auth()->user()?->company_id)) {
                $model->company_id = $id;
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
