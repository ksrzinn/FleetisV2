<?php

namespace Tests\Feature;

use Spatie\LaravelData\Data;
use Tests\TestCase;

class DataPackageTest extends TestCase
{
    public function test_data_class_serializes(): void
    {
        $dto = new class('Fleetis') extends Data
        {
            public function __construct(public string $name) {}
        };
        $this->assertSame(['name' => 'Fleetis'], $dto->toArray());
    }
}
