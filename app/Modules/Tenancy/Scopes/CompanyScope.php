<?php

namespace App\Modules\Tenancy\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class CompanyScope implements Scope
{
    /**
     * @param  Builder<Model>  $builder
     */
    public function apply(Builder $builder, Model $model): void
    {
        if ($companyId = auth()->user()?->company_id) {
            $builder->where($model->qualifyColumn('company_id'), $companyId);
        }
    }
}
