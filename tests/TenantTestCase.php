<?php

namespace Tests;

use App\Models\User;
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
}
