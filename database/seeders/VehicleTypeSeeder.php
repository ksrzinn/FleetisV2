<?php

namespace Database\Seeders;

use App\Modules\Fleet\Models\VehicleType;
use Illuminate\Database\Seeder;

class VehicleTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['code' => 'vuc',            'label' => 'VUC',          'requires_trailer' => false],
            ['code' => 'toco',           'label' => 'Toco',         'requires_trailer' => false],
            ['code' => 'three_quarters', 'label' => '3/4',          'requires_trailer' => false],
            ['code' => 'truck',          'label' => 'Truck',        'requires_trailer' => false],
            ['code' => 'semi_trailer',   'label' => 'Semirreboque', 'requires_trailer' => true],
            ['code' => 'rodotrem',       'label' => 'Rodotrem',     'requires_trailer' => true],
            ['code' => 'bitrem',         'label' => 'Bitrem',       'requires_trailer' => true],
        ];

        foreach ($types as $type) {
            VehicleType::updateOrCreate(['code' => $type['code']], $type);
        }
    }
}
