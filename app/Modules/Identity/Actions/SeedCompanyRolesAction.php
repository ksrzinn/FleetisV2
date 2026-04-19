<?php

namespace App\Modules\Identity\Actions;

use App\Modules\Tenancy\Models\Company;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class SeedCompanyRolesAction
{
    private const ROLE_PERMISSIONS = [
        'Admin' => [
            'clients.view',
            'clients.manage',
            'clients.delete',
            'freight_tables.view',
            'freight_tables.manage',
        ],
        'Operator' => [
            'clients.view',
            'clients.manage',
            'freight_tables.view',
            'freight_tables.manage',
        ],
        'Financial' => [
            'clients.view',
            'freight_tables.view',
        ],
    ];

    public function handle(Company $company): void
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId($company->id);

        foreach (['Admin', 'Operator', 'Financial'] as $roleName) {
            $role = Role::findOrCreate($roleName, 'web');

            $permissions = array_map(
                fn (string $name) => Permission::findOrCreate($name, 'web'),
                self::ROLE_PERMISSIONS[$roleName]
            );

            $role->syncPermissions($permissions);
        }
    }
}
