<?php

namespace Tests\Feature\Tenancy;

use App\Models\User;
use App\Modules\Tenancy\Http\Middleware\EnsureTenantContext;
use App\Modules\Tenancy\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class EnsureTenantContextMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['web', 'auth', 'tenant'])->get('/__tenant-probe', function () {
            return response()->json([
                'company_id' => DB::selectOne("select current_setting('app.current_company_id', true) as v")->v,
            ]);
        });

        Route::middleware(['web', 'auth', 'tenant'])->get('/__tenant-probe-throws', function () {
            throw new \RuntimeException('boom');
        });
    }

    public function test_rejects_user_without_company_id(): void
    {
        $user = User::factory()->create(['company_id' => null]);

        $this->actingAs($user)
            ->get('/__tenant-probe')
            ->assertForbidden();
    }

    public function test_sets_company_id_session_variable_for_authenticated_user(): void
    {
        $company = Company::create([
            'name' => 'Acme',
            'cnpj' => '11111111000111',
            'timezone' => 'UTC',
            'status' => 'active',
        ]);
        $user = User::factory()->create(['company_id' => $company->id]);

        $this->actingAs($user)
            ->get('/__tenant-probe')
            ->assertOk()
            ->assertJson(['company_id' => (string) $company->id]);
    }

    public function test_rolls_back_transaction_when_downstream_throws(): void
    {
        $company = Company::create([
            'name' => 'Acme',
            'cnpj' => '11111111000111',
            'timezone' => 'UTC',
            'status' => 'active',
        ]);
        $user = User::factory()->create(['company_id' => $company->id]);

        $this->actingAs($user);
        $initialLevel = DB::transactionLevel();

        try {
            $this->withoutExceptionHandling()->get('/__tenant-probe-throws');
            $this->fail('Expected exception was not thrown.');
        } catch (\RuntimeException $e) {
            $this->assertSame('boom', $e->getMessage());
        }

        $this->assertSame($initialLevel, DB::transactionLevel());
    }
}
