<?php

namespace Tests\Feature\Identity;

use App\Modules\Identity\Actions\SeedCompanyRolesAction;
use App\Modules\Tenancy\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SeedCompanyRolesActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_three_roles_for_a_company(): void
    {
        $company = Company::create(['name' => 'A', 'cnpj' => '11111111000111', 'timezone' => 'UTC', 'status' => 'active']);

        app(SeedCompanyRolesAction::class)->handle($company);

        $roles = Role::where('company_id', $company->id)->pluck('name')->all();
        sort($roles);
        $this->assertSame(['Admin', 'Financial', 'Operator'], $roles);
    }

    public function test_roles_are_scoped_to_their_company(): void
    {
        $a = Company::create(['name' => 'A', 'cnpj' => '11111111000111', 'timezone' => 'UTC', 'status' => 'active']);
        $b = Company::create(['name' => 'B', 'cnpj' => '22222222000122', 'timezone' => 'UTC', 'status' => 'active']);

        app(SeedCompanyRolesAction::class)->handle($a);
        app(SeedCompanyRolesAction::class)->handle($b);

        $this->assertSame(3, Role::where('company_id', $a->id)->count());
        $this->assertSame(3, Role::where('company_id', $b->id)->count());
    }
}
