<?php
namespace Tests\Feature\Commercial;

use App\Models\User;
use App\Modules\Commercial\Models\Client;
use App\Modules\Commercial\Models\ClientFreightTable;
use App\Modules\Identity\Actions\SeedCompanyRolesAction;
use App\Modules\Tenancy\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TenantTestCase;

class ClientFreightTableCrudTest extends TenantTestCase
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

    private function makeClient(User $user): Client
    {
        return Client::factory()->create([
            'company_id' => $user->company_id,
            'document' => '11144477735',
        ]);
    }

    public function test_operator_can_create_freight_table(): void
    {
        $user = $this->makeUser('Operator');
        $client = $this->makeClient($user);

        $this->actingAsTenant($user)
            ->post(route('clients.freight-tables.store', $client), [
                'name' => 'Tabela SP',
                'pricing_model' => 'fixed',
                'active' => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('client_freight_tables', [
            'client_id' => $client->id,
            'name' => 'Tabela SP',
        ]);
    }

    public function test_financial_cannot_create_freight_table(): void
    {
        $user = $this->makeUser('Financial');
        $client = $this->makeClient($user);

        $this->actingAsTenant($user)
            ->post(route('clients.freight-tables.store', $client), [
                'name' => 'Tabela',
                'pricing_model' => 'fixed',
            ])
            ->assertForbidden();
    }

    public function test_freight_table_name_must_be_unique_per_client(): void
    {
        $user = $this->makeUser('Admin');
        $client = $this->makeClient($user);
        ClientFreightTable::factory()->create([
            'company_id' => $user->company_id,
            'client_id' => $client->id,
            'name' => 'Tabela SP',
        ]);

        $this->actingAsTenant($user)
            ->post(route('clients.freight-tables.store', $client), [
                'name' => 'Tabela SP',
                'pricing_model' => 'fixed',
            ])
            ->assertSessionHasErrors('name');
    }

    public function test_pricing_model_is_immutable(): void
    {
        $user = $this->makeUser('Admin');
        $table = ClientFreightTable::factory()->create([
            'company_id' => $user->company_id,
            'pricing_model' => 'fixed',
        ]);

        $this->actingAsTenant($user)
            ->put(route('freight-tables.update', $table), [
                'name' => $table->name,
                'pricing_model' => 'per_km',  // should be ignored
                'active' => true,
            ]);

        $this->assertDatabaseHas('client_freight_tables', [
            'id' => $table->id,
            'pricing_model' => 'fixed',
        ]);
    }

    public function test_freight_table_not_visible_to_other_tenant(): void
    {
        $userA = $this->makeUser('Admin');
        $table = ClientFreightTable::factory()->create([
            'company_id' => Company::factory()->create()->id,
        ]);

        $this->actingAsTenant($userA)
            ->get(route('freight-tables.show', $table))
            ->assertNotFound();
    }

    public function test_operator_can_delete_freight_table(): void
    {
        $user = $this->makeUser('Operator');
        $table = ClientFreightTable::factory()->create(['company_id' => $user->company_id]);

        $this->actingAsTenant($user)
            ->delete(route('freight-tables.destroy', $table))
            ->assertRedirect();

        $this->assertDatabaseMissing('client_freight_tables', ['id' => $table->id]);
    }
}
