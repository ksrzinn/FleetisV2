<?php

namespace Tests\Feature\Fleet;

use App\Modules\Fleet\Models\VehicleType;
use Database\Seeders\VehicleTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VehicleTypeSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_seven_vehicle_types(): void
    {
        $this->seed(VehicleTypeSeeder::class);

        $this->assertSame(7, VehicleType::count());
    }

    public function test_semi_trailer_requires_trailer(): void
    {
        $this->seed(VehicleTypeSeeder::class);

        $this->assertTrue(VehicleType::where('code', 'semi_trailer')->value('requires_trailer'));
        $this->assertTrue(VehicleType::where('code', 'rodotrem')->value('requires_trailer'));
        $this->assertTrue(VehicleType::where('code', 'bitrem')->value('requires_trailer'));
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(VehicleTypeSeeder::class);
        $this->seed(VehicleTypeSeeder::class);

        $this->assertSame(7, VehicleType::count());
    }
}
