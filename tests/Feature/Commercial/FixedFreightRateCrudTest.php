<?php
namespace Tests\Feature\Commercial;

use App\Models\User;
use App\Modules\Commercial\Models\ClientFreightTable;
use App\Modules\Commercial\Models\FixedFreightRate;
use App\Modules\Identity\Actions\SeedCompanyRolesAction;
use App\Modules\Tenancy\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TenantTestCase;

class FixedFreightRateCrudTest extends TenantTestCase
{
    use RefreshDatabase;

    private function makeUser(string $role, ?Company $company = null): User
    {
        $company ??= Company::factory()->create();
        app(SeedCompanyRolesAction::class)->handle($company);
        app(PermissionRegistrar::class)->setPermissionsTeamId($company->id);
        $user = User::factory()->create(['company_id' => $company->id]);
        $user->assignRole($role);
        return $user;
    }

    public function test_operator_can_create_fixed_rate(): void
    {
        $user = $this->makeUser('Operator');
        $table = ClientFreightTable::factory()->create(['company_id' => $user->company_id]);

        $this->actingAsTenant($user)
            ->post(route('freight-tables.fixed-rates.store', $table), [
                'name' => 'Sorocaba 3',
                'price' => '1500.00',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('fixed_freight_rates', [
            'client_freight_table_id' => $table->id,
            'name' => 'Sorocaba 3',
        ]);
    }

    public function test_financial_cannot_create_fixed_rate(): void
    {
        $user = $this->makeUser('Financial');
        $table = ClientFreightTable::factory()->create(['company_id' => $user->company_id]);

        $this->actingAsTenant($user)
            ->post(route('freight-tables.fixed-rates.store', $table), [
                'name' => 'Rate X',
                'price' => '100',
            ])
            ->assertForbidden();
    }

    public function test_rate_name_must_be_unique_per_table(): void
    {
        $user = $this->makeUser('Admin');
        $table = ClientFreightTable::factory()->create(['company_id' => $user->company_id]);
        FixedFreightRate::factory()->create([
            'company_id' => $user->company_id,
            'client_freight_table_id' => $table->id,
            'name' => 'Sorocaba 3',
        ]);

        $this->actingAsTenant($user)
            ->post(route('freight-tables.fixed-rates.store', $table), [
                'name' => 'Sorocaba 3',
                'price' => '200',
            ])
            ->assertSessionHasErrors('name');
    }

    public function test_rate_not_visible_to_other_tenant(): void
    {
        $userA = $this->makeUser('Admin');
        $rate = FixedFreightRate::factory()->create([
            'company_id' => Company::factory()->create()->id,
        ]);

        $this->actingAsTenant($userA)
            ->get(route('fixed-rates.edit', $rate))
            ->assertNotFound();
    }
}
