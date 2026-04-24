<?php

namespace Tests\Feature\Finance;

use App\Modules\Finance\Models\Receivable;
use App\Modules\Operations\Models\Freight;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TenantTestCase;

class ReceivableControllerTest extends TenantTestCase
{
    use RefreshDatabase;

    // ── Index ────────────────────────────────────────────────────────────────

    public function test_financial_role_can_access_receivables_index(): void
    {
        $user = $this->makeUserWithRole('Financial');

        $response = $this->actingAsTenant($user)->get('/receivables');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Finance/Receivables/Index'));
    }

    public function test_operator_cannot_access_receivables_index(): void
    {
        $user = $this->makeUserWithRole('Operator');

        $response = $this->actingAsTenant($user)->get('/receivables');

        $response->assertForbidden();
    }

    public function test_admin_can_access_receivables_index(): void
    {
        $user = $this->makeUserWithRole('Admin');

        $response = $this->actingAsTenant($user)->get('/receivables');

        $response->assertOk();
    }

    public function test_unauthenticated_user_is_redirected_from_index(): void
    {
        $response = $this->get('/receivables');

        $response->assertRedirect('/login');
    }

    public function test_index_only_returns_own_company_receivables(): void
    {
        $userA = $this->makeUserWithRole('Financial');
        $userB = $this->makeUserWithRole('Financial');

        Receivable::factory()->create(['company_id' => $userA->company_id]);
        Receivable::factory()->create(['company_id' => $userA->company_id]);
        Receivable::factory()->create(['company_id' => $userB->company_id]); // other tenant

        $response = $this->actingAsTenant($userA)->get('/receivables');

        $response->assertInertia(fn ($page) => $page->has('receivables.data', 2));
    }

    public function test_index_filters_by_status(): void
    {
        $user = $this->makeUserWithRole('Financial');
        Receivable::factory()->create(['company_id' => $user->company_id, 'status' => 'open']);
        Receivable::factory()->create(['company_id' => $user->company_id, 'status' => 'paid']);

        $response = $this->actingAsTenant($user)->get('/receivables?status=open');

        $response->assertInertia(fn ($page) => $page->has('receivables.data', 1));
    }

    public function test_index_filters_by_client_id(): void
    {
        $user = $this->makeUserWithRole('Financial');
        $targetReceivable = Receivable::factory()->create(['company_id' => $user->company_id]);
        Receivable::factory()->create(['company_id' => $user->company_id]); // different client

        $response = $this->actingAsTenant($user)->get("/receivables?client_id={$targetReceivable->client_id}");

        $response->assertInertia(fn ($page) => $page->has('receivables.data', 1));
    }

    public function test_index_filters_by_due_date_from(): void
    {
        $user = $this->makeUserWithRole('Financial');
        Receivable::factory()->create(['company_id' => $user->company_id, 'due_date' => '2026-05-01']);
        Receivable::factory()->create(['company_id' => $user->company_id, 'due_date' => '2026-03-01']);

        $response = $this->actingAsTenant($user)->get('/receivables?due_date_from=2026-04-01');

        $response->assertInertia(fn ($page) => $page->has('receivables.data', 1));
    }

    // ── Show ────────────────────────────────────────────────────────────────

    public function test_financial_role_can_view_receivable_show(): void
    {
        $user = $this->makeUserWithRole('Financial');
        $receivable = Receivable::factory()->create(['company_id' => $user->company_id]);

        $response = $this->actingAsTenant($user)->get("/receivables/{$receivable->id}");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Finance/Receivables/Show'));
    }

    public function test_operator_cannot_view_receivable_show(): void
    {
        $operator = $this->makeUserWithRole('Operator');
        $financial = $this->makeUserWithRole('Financial', $operator->company);
        $receivable = Receivable::factory()->create(['company_id' => $operator->company_id]);

        $response = $this->actingAsTenant($operator)->get("/receivables/{$receivable->id}");

        $response->assertForbidden();
    }

    public function test_financial_cannot_view_other_company_receivable(): void
    {
        $userA = $this->makeUserWithRole('Financial');
        $userB = $this->makeUserWithRole('Financial');
        $receivable = Receivable::factory()->create(['company_id' => $userB->company_id]);

        $response = $this->actingAsTenant($userA)->get("/receivables/{$receivable->id}");

        $response->assertNotFound();
    }

    public function test_show_includes_payments_in_props(): void
    {
        $user = $this->makeUserWithRole('Financial');
        $receivable = Receivable::factory()->create(['company_id' => $user->company_id]);

        $response = $this->actingAsTenant($user)->get("/receivables/{$receivable->id}");

        $response->assertInertia(fn ($page) => $page->has('receivable')->has('payments'));
    }
}
