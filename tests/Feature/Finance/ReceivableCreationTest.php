<?php

namespace Tests\Feature\Finance;

use App\Modules\Commercial\Models\Client;
use App\Modules\Finance\Models\Receivable;
use App\Modules\Operations\Events\FreightEnteredAwaitingPayment;
use App\Modules\Operations\Listeners\CreateReceivableForFreight;
use App\Modules\Operations\Models\Freight;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TenantTestCase;

class ReceivableCreationTest extends TenantTestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow(null);
        parent::tearDown();
    }

    public function test_listener_creates_receivable_from_freight(): void
    {
        $user = $this->makeUserWithRole('Financial');
        $freight = Freight::factory()->awaitingPayment()->create([
            'company_id'    => $user->company_id,
            'freight_value' => '1500.00',
        ]);

        $this->actingAsTenant($user);
        event(new FreightEnteredAwaitingPayment($freight));

        $this->assertDatabaseHas('receivables', [
            'freight_id' => $freight->id,
            'client_id'  => $freight->client_id,
            'company_id' => $freight->company_id,
            'amount_due' => '1500.00',
            'status'     => 'open',
        ]);
    }

    public function test_receivable_amount_due_matches_freight_value(): void
    {
        $user = $this->makeUserWithRole('Financial');
        $freight = Freight::factory()->awaitingPayment()->create([
            'company_id'    => $user->company_id,
            'freight_value' => '3750.50',
        ]);

        $this->actingAsTenant($user);
        event(new FreightEnteredAwaitingPayment($freight));

        $this->assertDatabaseHas('receivables', [
            'freight_id' => $freight->id,
            'amount_due' => '3750.50',
        ]);
    }

    public function test_due_date_uses_client_days_after_payment_term(): void
    {
        Carbon::setTestNow('2026-04-15');
        $user = $this->makeUserWithRole('Financial');
        $client = Client::factory()->create([
            'company_id'          => $user->company_id,
            'payment_term_type'   => 'days_after',
            'payment_term_value'  => 7,
        ]);
        $freight = Freight::factory()->awaitingPayment()->create([
            'company_id'    => $user->company_id,
            'client_id'     => $client->id,
            'freight_value' => '1000.00',
        ]);

        $this->actingAsTenant($user);
        event(new FreightEnteredAwaitingPayment($freight));

        $this->assertDatabaseHas('receivables', [
            'freight_id' => $freight->id,
            'due_date'   => '2026-04-22',
        ]);
    }

    public function test_due_date_uses_client_monthly_payment_term(): void
    {
        Carbon::setTestNow('2026-04-10');
        $user = $this->makeUserWithRole('Financial');
        $client = Client::factory()->create([
            'company_id'         => $user->company_id,
            'payment_term_type'  => 'monthly',
            'payment_term_value' => 20,
        ]);
        $freight = Freight::factory()->awaitingPayment()->create([
            'company_id'    => $user->company_id,
            'client_id'     => $client->id,
            'freight_value' => '1000.00',
        ]);

        $this->actingAsTenant($user);
        event(new FreightEnteredAwaitingPayment($freight));

        $this->assertDatabaseHas('receivables', [
            'freight_id' => $freight->id,
            'due_date'   => '2026-04-20',
        ]);
    }

    public function test_unconfigured_client_payment_term_defaults_to_30_days(): void
    {
        Carbon::setTestNow('2026-04-15');
        $user = $this->makeUserWithRole('Financial');
        $client = Client::factory()->create([
            'company_id'        => $user->company_id,
            'payment_term_type' => null,
        ]);
        $freight = Freight::factory()->awaitingPayment()->create([
            'company_id'    => $user->company_id,
            'client_id'     => $client->id,
            'freight_value' => '1000.00',
        ]);

        $this->actingAsTenant($user);
        event(new FreightEnteredAwaitingPayment($freight));

        $this->assertDatabaseHas('receivables', [
            'freight_id' => $freight->id,
            'due_date'   => '2026-05-15',
        ]);
    }

    public function test_listener_throws_when_freight_value_is_null(): void
    {
        $user = $this->makeUserWithRole('Financial');
        $freight = Freight::factory()->awaitingPayment()->create([
            'company_id'    => $user->company_id,
            'freight_value' => null,
        ]);

        $this->actingAsTenant($user);

        $this->expectException(\InvalidArgumentException::class);

        (new CreateReceivableForFreight())->handle(new FreightEnteredAwaitingPayment($freight));
    }

    public function test_receivable_is_not_visible_across_tenants(): void
    {
        $userA = $this->makeUserWithRole('Financial');
        $userB = $this->makeUserWithRole('Financial');

        $freightA = Freight::factory()->awaitingPayment()->create([
            'company_id'    => $userA->company_id,
            'freight_value' => '1000.00',
        ]);

        $this->actingAsTenant($userA);
        event(new FreightEnteredAwaitingPayment($freightA));

        // userB should not see userA's receivable
        $this->actingAsTenant($userB);
        $this->assertDatabaseMissing('receivables', [
            'company_id' => $userB->company_id,
            'freight_id' => $freightA->id,
        ]);
    }
}
