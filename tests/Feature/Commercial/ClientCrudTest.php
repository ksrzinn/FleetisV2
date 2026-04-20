<?php
namespace Tests\Feature\Commercial;

use App\Models\User;
use App\Modules\Commercial\Models\Client;
use App\Modules\Identity\Actions\SeedCompanyRolesAction;
use App\Modules\Tenancy\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TenantTestCase;

class ClientCrudTest extends TenantTestCase
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

    // --- viewAny ---

    public function test_admin_can_list_clients(): void
    {
        $user = $this->makeUser('Admin');
        Client::factory()->create(['company_id' => $user->company_id, 'document' => '11144477735']);

        $this->actingAsTenant($user)->get(route('clients.index'))->assertOk();
    }

    public function test_financial_can_list_clients(): void
    {
        $user = $this->makeUser('Financial');
        $this->actingAsTenant($user)->get(route('clients.index'))->assertOk();
    }

    // --- tenant isolation ---

    public function test_client_from_other_tenant_returns_404(): void
    {
        $userA = $this->makeUser('Admin');
        $client = Client::factory()->create(['company_id' => Company::factory()->create()->id, 'document' => '11144477735']);

        $this->actingAsTenant($userA)
            ->get(route('clients.edit', $client))
            ->assertNotFound();
    }

    // --- create ---

    public function test_admin_can_create_client(): void
    {
        $user = $this->makeUser('Admin');

        $this->actingAsTenant($user)
            ->post(route('clients.store'), [
                'name' => 'Transportes Silva',
                'document' => '111.444.777-35',  // masked CPF
                'active' => true,
            ])
            ->assertRedirect(route('clients.index'));

        $this->assertDatabaseHas('clients', [
            'company_id' => $user->company_id,
            'document' => '11144477735',  // stored without mask
        ]);
    }

    public function test_operator_can_create_client(): void
    {
        $user = $this->makeUser('Operator');

        $this->actingAsTenant($user)
            ->post(route('clients.store'), [
                'name' => 'Cliente Teste',
                'document' => '11144477735',
                'active' => true,
            ])
            ->assertRedirect();
    }

    public function test_financial_cannot_create_client(): void
    {
        $user = $this->makeUser('Financial');

        $this->actingAsTenant($user)
            ->post(route('clients.store'), ['name' => 'X', 'document' => '11144477735'])
            ->assertForbidden();
    }

    // --- validation ---

    public function test_invalid_cpf_is_rejected(): void
    {
        $user = $this->makeUser('Admin');

        $this->actingAsTenant($user)
            ->post(route('clients.store'), ['name' => 'X', 'document' => '11111111111'])
            ->assertSessionHasErrors('document');
    }

    public function test_document_must_be_unique_per_tenant(): void
    {
        $company = Company::factory()->create();
        $user = $this->makeUser('Admin', $company);
        Client::factory()->create(['company_id' => $company->id, 'document' => '11144477735']);

        $this->actingAsTenant($user)
            ->post(route('clients.store'), ['name' => 'Duplicate', 'document' => '11144477735'])
            ->assertSessionHasErrors('document');
    }

    public function test_same_document_allowed_across_tenants(): void
    {
        $this->makeUser('Admin', Company::factory()->create());  // seeds first tenant
        Client::factory()->create(['document' => '11144477735']);  // first tenant

        $user2 = $this->makeUser('Admin');
        $this->actingAsTenant($user2)
            ->post(route('clients.store'), ['name' => 'Other', 'document' => '11144477735', 'active' => true])
            ->assertRedirect();
    }

    // --- update ---

    public function test_operator_can_update_client(): void
    {
        $user = $this->makeUser('Operator');
        $client = Client::factory()->create(['company_id' => $user->company_id, 'document' => '11144477735']);

        $this->actingAsTenant($user)
            ->put(route('clients.update', $client), [
                'name' => 'Updated Name',
                'document' => '11144477735',
                'active' => true,
            ])
            ->assertRedirect(route('clients.index'));
    }

    // --- delete ---

    public function test_admin_can_delete_client(): void
    {
        $user = $this->makeUser('Admin');
        $client = Client::factory()->create(['company_id' => $user->company_id, 'document' => '11144477735']);

        $this->actingAsTenant($user)
            ->delete(route('clients.destroy', $client))
            ->assertRedirect(route('clients.index'));

        $this->assertSoftDeleted('clients', ['id' => $client->id]);
    }

    public function test_operator_cannot_delete_client(): void
    {
        $user = $this->makeUser('Operator');
        $client = Client::factory()->create(['company_id' => $user->company_id, 'document' => '11144477735']);

        $this->actingAsTenant($user)
            ->delete(route('clients.destroy', $client))
            ->assertForbidden();
    }
}
