<?php

namespace App\Modules\Identity\Actions;

use App\Modules\Tenancy\Models\Company;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class SeedCompanyRolesAction
{
    public function handle(Company $company): void
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId($company->id);

        foreach (['Admin', 'Operator', 'Financial'] as $role) {
            Role::findOrCreate($role, 'web');
        }
    }
}
