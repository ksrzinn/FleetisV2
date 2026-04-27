<?php

namespace Tests\Feature\Reporting;

use App\Modules\Finance\Models\Bill;
use App\Modules\Finance\Models\BillInstallment;
use App\Modules\Finance\Models\Receivable;
use App\Modules\Operations\Models\Freight;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TenantTestCase;

class DashboardControllerTest extends TenantTestCase
{
    use RefreshDatabase;

    public function test_all_roles_can_access_dashboard(): void
    {
        foreach (['Admin', 'Operator', 'Financial'] as $role) {
            $user = $this->makeUserWithRole($role);
            $this->actingAsTenant($user)->get('/dashboard')->assertOk();
        }
    }

    public function test_dashboard_returns_dashboard_component_with_required_props(): void
    {
        $user = $this->makeUserWithRole('Admin');

        $this->actingAsTenant($user)->get('/dashboard')
            ->assertInertia(fn ($page) => $page
                ->component('Dashboard')
                ->has('revenueSeries')
                ->has('expenseSeries')
                ->has('arOutstanding')
                ->has('apOutstanding')
                ->has('freightByStatus')
                ->has('recentFreights')
                ->has('period')
            );
    }

    public function test_ar_outstanding_is_scoped_to_own_company(): void
    {
        $userA = $this->makeUserWithRole('Financial');
        $userB = $this->makeUserWithRole('Financial');

        Receivable::factory()->create([
            'company_id'  => $userA->company_id,
            'amount_due'  => '1000.00',
            'amount_paid' => '0.00',
            'status'      => 'open',
        ]);
        Receivable::factory()->create([
            'company_id'  => $userB->company_id,
            'amount_due'  => '9000.00',
            'amount_paid' => '0.00',
            'status'      => 'open',
        ]);

        $this->actingAsTenant($userA)->get('/dashboard')
            ->assertInertia(fn ($page) => $page
                ->where('arOutstanding', fn ($v) => (float) $v === 1000.0)
            );
    }

    public function test_ar_outstanding_excludes_paid_receivables(): void
    {
        $user = $this->makeUserWithRole('Financial');

        Receivable::factory()->create([
            'company_id'  => $user->company_id,
            'amount_due'  => '500.00',
            'amount_paid' => '500.00',
            'status'      => 'paid',
        ]);

        $this->actingAsTenant($user)->get('/dashboard')
            ->assertInertia(fn ($page) => $page
                ->where('arOutstanding', fn ($v) => (float) $v === 0.0)
            );
    }

    public function test_ap_outstanding_is_scoped_to_own_company(): void
    {
        $userA = $this->makeUserWithRole('Financial');
        $userB = $this->makeUserWithRole('Financial');

        $billA = Bill::factory()->create(['company_id' => $userA->company_id]);
        BillInstallment::factory()->create([
            'company_id'  => $userA->company_id,
            'bill_id'     => $billA->id,
            'amount'      => '500.00',
            'paid_amount' => null,
            'paid_at'     => null,
        ]);

        $billB = Bill::factory()->create(['company_id' => $userB->company_id]);
        BillInstallment::factory()->create([
            'company_id'  => $userB->company_id,
            'bill_id'     => $billB->id,
            'amount'      => '9000.00',
            'paid_amount' => null,
            'paid_at'     => null,
        ]);

        $this->actingAsTenant($userA)->get('/dashboard')
            ->assertInertia(fn ($page) => $page
                ->where('apOutstanding', fn ($v) => (float) $v === 500.0)
            );
    }

    public function test_ap_outstanding_excludes_paid_installments(): void
    {
        $user = $this->makeUserWithRole('Financial');
        $bill = Bill::factory()->create(['company_id' => $user->company_id]);

        BillInstallment::factory()->create([
            'company_id'  => $user->company_id,
            'bill_id'     => $bill->id,
            'amount'      => '300.00',
            'paid_amount' => '300.00',
            'paid_at'     => now(),
        ]);

        $this->actingAsTenant($user)->get('/dashboard')
            ->assertInertia(fn ($page) => $page
                ->where('apOutstanding', fn ($v) => (float) $v === 0.0)
            );
    }

    public function test_revenue_series_is_scoped_to_own_company(): void
    {
        $userA = $this->makeUserWithRole('Admin');
        $userB = $this->makeUserWithRole('Admin');

        Freight::factory()->create([
            'company_id'    => $userA->company_id,
            'freight_value' => '1000.00',
            'finished_at'   => now(),
        ]);
        Freight::factory()->create([
            'company_id'    => $userB->company_id,
            'freight_value' => '9000.00',
            'finished_at'   => now(),
        ]);

        $this->actingAsTenant($userA)->get('/dashboard')
            ->assertInertia(fn ($page) => $page
                ->where('revenueSeries', fn ($series) =>
                    collect($series)->sum('y') === 1000.0
                )
            );
    }

    public function test_period_query_param_is_returned_in_props(): void
    {
        $user = $this->makeUserWithRole('Admin');

        $this->actingAsTenant($user)->get('/dashboard?period=weekly')
            ->assertInertia(fn ($page) => $page->where('period', 'weekly'));
    }

    public function test_invalid_period_defaults_to_monthly(): void
    {
        $user = $this->makeUserWithRole('Admin');

        $this->actingAsTenant($user)->get('/dashboard?period=badvalue')
            ->assertInertia(fn ($page) => $page->where('period', 'monthly'));
    }
}
