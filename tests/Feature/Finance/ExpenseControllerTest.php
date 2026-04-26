<?php

namespace Tests\Feature\Finance;

use App\Modules\Finance\Models\Expense;
use App\Modules\Finance\Models\ExpenseCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TenantTestCase;

class ExpenseControllerTest extends TenantTestCase
{
    use RefreshDatabase;

    // ── Index ─────────────────────────────────────────────────────────────────

    public function test_financial_can_access_expenses_index(): void
    {
        $user = $this->makeUserWithRole('Financial');

        $response = $this->actingAsTenant($user)->get('/expenses');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Finance/Expenses/Index'));
    }

    public function test_operator_cannot_access_expenses_index(): void
    {
        $user = $this->makeUserWithRole('Operator');

        $response = $this->actingAsTenant($user)->get('/expenses');

        $response->assertForbidden();
    }

    public function test_index_does_not_leak_other_company_expenses(): void
    {
        $userA = $this->makeUserWithRole('Financial');
        $userB = $this->makeUserWithRole('Financial');

        Expense::factory()->create(['company_id' => $userA->company_id]);
        Expense::factory()->create(['company_id' => $userA->company_id]);
        Expense::factory()->create(['company_id' => $userB->company_id]);

        $response = $this->actingAsTenant($userA)->get('/expenses');

        $response->assertInertia(fn ($page) => $page->has('expenses.data', 2));
    }

    public function test_index_filters_by_category(): void
    {
        $user     = $this->makeUserWithRole('Financial');
        $catA     = ExpenseCategory::factory()->create(['company_id' => $user->company_id]);
        $catB     = ExpenseCategory::factory()->create(['company_id' => $user->company_id]);

        Expense::factory()->create(['company_id' => $user->company_id, 'expense_category_id' => $catA->id]);
        Expense::factory()->create(['company_id' => $user->company_id, 'expense_category_id' => $catB->id]);

        $response = $this->actingAsTenant($user)->get("/expenses?filter[expense_category_id]={$catA->id}");

        $response->assertInertia(fn ($page) => $page->has('expenses.data', 1));
    }

    // ── Store ─────────────────────────────────────────────────────────────────

    public function test_financial_can_create_expense(): void
    {
        $user     = $this->makeUserWithRole('Financial');
        $category = ExpenseCategory::factory()->create(['company_id' => $user->company_id]);

        $response = $this->actingAsTenant($user)->post('/expenses', [
            'expense_category_id' => $category->id,
            'amount'              => '150.00',
            'incurred_on'         => '2026-04-20',
            'description'         => 'Test expense',
        ]);

        $response->assertRedirect(route('expenses.index'));
        $this->assertDatabaseHas('expenses', [
            'company_id'          => $user->company_id,
            'expense_category_id' => $category->id,
            'amount'              => '150.00',
        ]);
    }

    public function test_cannot_link_both_vehicle_and_freight(): void
    {
        $user     = $this->makeUserWithRole('Financial');
        $category = ExpenseCategory::factory()->create(['company_id' => $user->company_id]);

        $response = $this->actingAsTenant($user)->post('/expenses', [
            'expense_category_id' => $category->id,
            'amount'              => '100.00',
            'incurred_on'         => '2026-04-20',
            'vehicle_id'          => 1,
            'freight_id'          => 1,
        ]);

        $response->assertSessionHasErrors('vehicle_id');
    }

    // ── Update ────────────────────────────────────────────────────────────────

    public function test_financial_can_update_expense(): void
    {
        $user     = $this->makeUserWithRole('Financial');
        $category = ExpenseCategory::factory()->create(['company_id' => $user->company_id]);
        $expense  = Expense::factory()->create([
            'company_id'          => $user->company_id,
            'expense_category_id' => $category->id,
        ]);

        $response = $this->actingAsTenant($user)->put("/expenses/{$expense->id}", [
            'expense_category_id' => $category->id,
            'amount'              => '999.99',
            'incurred_on'         => '2026-04-21',
        ]);

        $response->assertRedirect(route('expenses.index'));
        $this->assertDatabaseHas('expenses', ['id' => $expense->id, 'amount' => '999.99']);
    }

    public function test_cannot_update_other_company_expense(): void
    {
        $userA   = $this->makeUserWithRole('Financial');
        $userB   = $this->makeUserWithRole('Financial');
        $expense = Expense::factory()->create(['company_id' => $userB->company_id]);

        $response = $this->actingAsTenant($userA)->put("/expenses/{$expense->id}", [
            'expense_category_id' => $expense->expense_category_id,
            'amount'              => '1.00',
            'incurred_on'         => '2026-04-21',
        ]);

        $response->assertNotFound();
    }

    // ── Destroy ───────────────────────────────────────────────────────────────

    public function test_financial_can_delete_expense(): void
    {
        $user    = $this->makeUserWithRole('Financial');
        $expense = Expense::factory()->create(['company_id' => $user->company_id]);

        $response = $this->actingAsTenant($user)->delete("/expenses/{$expense->id}");

        $response->assertRedirect(route('expenses.index'));
        $this->assertDatabaseMissing('expenses', ['id' => $expense->id]);
    }
}
