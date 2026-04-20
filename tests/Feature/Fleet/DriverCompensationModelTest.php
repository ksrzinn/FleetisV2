<?php

namespace Tests\Feature\Fleet;

use App\Modules\Fleet\Models\Compensations\PercentageCompensation;
use App\Modules\Fleet\Models\Driver;
use App\Modules\Fleet\Models\DriverCompensation;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DriverCompensationModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_parental_resolves_correct_subclass(): void
    {
        $driver = Driver::factory()->create();

        DriverCompensation::factory()->create([
            'company_id' => $driver->company_id,
            'driver_id' => $driver->id,
            'type' => 'percentage',
            'percentage' => 10.00,
        ]);

        $this->assertInstanceOf(PercentageCompensation::class, DriverCompensation::first());
    }

    public function test_driver_can_have_two_active_compensations_of_different_types(): void
    {
        $driver = Driver::factory()->create();

        DriverCompensation::factory()->create([
            'company_id' => $driver->company_id,
            'driver_id' => $driver->id,
            'type' => 'percentage',
            'percentage' => 10.00,
        ]);

        DriverCompensation::factory()->fixedPerFreight()->create([
            'company_id' => $driver->company_id,
            'driver_id' => $driver->id,
        ]);

        $this->assertSame(2, $driver->activeCompensations()->count());
    }

    public function test_db_rejects_two_active_compensations_of_same_type(): void
    {
        $driver = Driver::factory()->create();

        DriverCompensation::factory()->create([
            'company_id' => $driver->company_id,
            'driver_id' => $driver->id,
            'type' => 'percentage',
            'percentage' => 10.00,
        ]);

        $this->expectException(QueryException::class);

        DriverCompensation::factory()->create([
            'company_id' => $driver->company_id,
            'driver_id' => $driver->id,
            'type' => 'percentage',
            'percentage' => 15.00,
        ]);
    }
}
