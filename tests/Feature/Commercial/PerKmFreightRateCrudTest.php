<?php
namespace Tests\Feature\Commercial;

use App\Models\User;
use App\Modules\Commercial\Models\Client;
use App\Modules\Commercial\Models\PerKmFreightRate;
use App\Modules\Identity\Actions\SeedCompanyRolesAction;
use App\Modules\Tenancy\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TenantTestCase;

class PerKmFreightRateCrudTest extends TenantTestCase
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

    public function test_operator_can_create_per_km_rate(): void
    {
        $user = $this->makeUser('Operator');
        $client = $this->makeClient($user);

        $this->actingAsTenant($user)
            ->post(route('clients.per-km-rates.store', $client), [
                'state' => 'SP',
                'rate_per_km' => '3.5000',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('per_km_freight_rates', [
            'client_id' => $client->id,
            'state' => 'SP',
        ]);
    }

    public function test_invalid_state_is_rejected(): void
    {
        $user = $this->makeUser('Admin');
        $client = $this->makeClient($user);

        $this->actingAsTenant($user)
            ->post(route('clients.per-km-rates.store', $client), [
                'state' => 'XX',
                'rate_per_km' => '3.00',
            ])
            ->assertSessionHasErrors('state');
    }

    public function test_state_must_be_unique_per_client(): void
    {
        $user = $this->makeUser('Admin');
        $client = $this->makeClient($user);
        PerKmFreightRate::factory()->create([
            'company_id' => $user->company_id,
            'client_id' => $client->id,
            'state' => 'SP',
        ]);

        $this->actingAsTenant($user)
            ->post(route('clients.per-km-rates.store', $client), [
                'state' => 'SP',
                'rate_per_km' => '5.00',
            ])
            ->assertSessionHasErrors('state');
    }

    public function test_financial_cannot_create_per_km_rate(): void
    {
        $user = $this->makeUser('Financial');
        $client = $this->makeClient($user);

        $this->actingAsTenant($user)
            ->post(route('clients.per-km-rates.store', $client), [
                'state' => 'SP',
                'rate_per_km' => '3.00',
            ])
            ->assertForbidden();
    }

    public function test_rate_not_visible_to_other_tenant(): void
    {
        $userA = $this->makeUser('Admin');
        $rate = PerKmFreightRate::factory()->create([
            'company_id' => Company::factory()->create()->id,
        ]);

        $this->actingAsTenant($userA)
            ->get(route('per-km-rates.edit', $rate))
            ->assertNotFound();
    }
}
