<?php

namespace Tests\Feature\Finance;

use App\Modules\Finance\Models\ExpenseCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TenantTestCase;

class ExpenseCategoryControllerTest extends TenantTestCase
{
    use RefreshDatabase;

    public function test_financial_can_create_new_category(): void
    {
        $user = $this->makeUserWithRole('Financial');

        $response = $this->actingAsTenant($user)->postJson('/expense-categories', [
            'name' => 'Ferramentas',
        ]);

        $response->assertCreated();
        $response->assertJsonStructure(['id', 'name', 'color']);
        $response->assertJsonFragment(['name' => 'Ferramentas']);
    }

    public function test_creating_existing_name_returns_existing_category_with_200(): void
    {
        $user = $this->makeUserWithRole('Financial');
        $existing = ExpenseCategory::factory()->create([
            'company_id' => $user->company_id,
            'name'       => 'Ferramentas',
        ]);

        $countBefore = ExpenseCategory::withoutGlobalScopes()->count();

        $response = $this->actingAsTenant($user)->postJson('/expense-categories', [
            'name' => 'Ferramentas',
        ]);

        $response->assertOk();
        $response->assertJsonFragment(['id' => $existing->id]);
        $this->assertSame($countBefore, ExpenseCategory::withoutGlobalScopes()->count());
    }

    public function test_operator_cannot_create_category(): void
    {
        $user = $this->makeUserWithRole('Operator');

        $response = $this->actingAsTenant($user)->postJson('/expense-categories', [
            'name' => 'Ferramentas',
        ]);

        $response->assertForbidden();
    }

    public function test_color_is_auto_assigned_from_palette(): void
    {
        $user = $this->makeUserWithRole('Financial');

        $response = $this->actingAsTenant($user)->postJson('/expense-categories', [
            'name' => 'NovaCat',
        ]);

        $response->assertCreated();
        $color = $response->json('color');
        $this->assertContains($color, ExpenseCategory::COLOR_PALETTE);
    }

    public function test_category_belongs_to_current_company(): void
    {
        $userA = $this->makeUserWithRole('Financial');
        $userB = $this->makeUserWithRole('Financial');

        $responseA = $this->actingAsTenant($userA)->postJson('/expense-categories', ['name' => 'SharedName']);
        $responseA->assertCreated();

        // userB creates same name — should create separate record for their company
        $responseB = $this->actingAsTenant($userB)->postJson('/expense-categories', ['name' => 'SharedName']);
        $responseB->assertCreated();

        $idA = $responseA->json('id');
        $idB = $responseB->json('id');
        $this->assertNotSame($idA, $idB);
    }
}
