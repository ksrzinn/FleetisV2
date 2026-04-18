<?php

namespace Tests;

use App\Models\User;
use App\Modules\Identity\Actions\SeedCompanyRolesAction;
use App\Modules\Tenancy\Models\Company;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

abstract class TenantTestCase extends TestCase
{
    protected function actingAsTenant(User $user): static
    {
        $this->actingAs($user);

        DB::statement('SET LOCAL app.current_company_id = '.(int) $user->company_id);
        app(PermissionRegistrar::class)->setPermissionsTeamId($user->company_id);

        return $this;
    }

    protected function makeUserWithRole(string $role, ?Company $company = null): User
    {
        $company ??= Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        app(SeedCompanyRolesAction::class)->handle($company);
        app(PermissionRegistrar::class)->setPermissionsTeamId($company->id);
        $user->assignRole($role);

        return $user;
    }
}
