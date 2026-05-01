<?php

namespace Database\Seeders;

use App\Models\Vehicle;
use Illuminate\Database\Seeder;

class VehicleSeeder extends Seeder
{
    public function run(): void
    {
        $vehicles = [
            ['organization_id' => 1, 'state_number' => '01A001AA', 'model' => 'Toyota Camry', 'manufacture_year' => 2020, 'vehicle_type' => 'car', 'fuel_type' => 'benzin', 'fuel_norm' => 9.5, 'fuel_norm_ac' => 11.0, 'status' => 'active', 'color' => 'oq'],
            ['organization_id' => 1, 'state_number' => '01A002AA', 'model' => 'Chevrolet Damas', 'manufacture_year' => 2019, 'vehicle_type' => 'car', 'fuel_type' => 'benzin', 'fuel_norm' => 7.5, 'fuel_norm_ac' => 8.5, 'status' => 'active', 'color' => 'kumush'],
            ['organization_id' => 2, 'state_number' => '02B001BB', 'model' => 'Mercedes Sprinter', 'manufacture_year' => 2021, 'vehicle_type' => 'bus', 'fuel_type' => 'dizel', 'fuel_norm' => 12.0, 'fuel_norm_ac' => 14.0, 'status' => 'active', 'color' => 'oq'],
            ['organization_id' => 2, 'state_number' => '02B002BB', 'model' => 'Kamaz 5511', 'manufacture_year' => 2018, 'vehicle_type' => 'truck', 'fuel_type' => 'dizel', 'fuel_norm' => 28.0, 'status' => 'broken', 'color' => 'ko\'k'],
            ['organization_id' => 3, 'state_number' => '03C001CC', 'model' => 'Hyundai H350', 'manufacture_year' => 2022, 'vehicle_type' => 'bus', 'fuel_type' => 'dizel', 'fuel_norm' => 11.5, 'fuel_norm_ac' => 13.5, 'status' => 'active', 'color' => 'oq'],
            ['organization_id' => 4, 'state_number' => '04D001DD', 'model' => 'Nexia 3', 'manufacture_year' => 2021, 'vehicle_type' => 'car', 'fuel_type' => 'benzin', 'fuel_norm' => 8.0, 'fuel_norm_ac' => 9.5, 'status' => 'maintenance', 'color' => 'kulrang'],
            ['organization_id' => 5, 'state_number' => '05E001EE', 'model' => 'Damas 2', 'manufacture_year' => 2023, 'vehicle_type' => 'car', 'fuel_type' => 'gaz', 'fuel_norm' => 9.0, 'status' => 'active', 'color' => 'sariq'],
            ['organization_id' => 5, 'state_number' => '05E002EE', 'model' => 'Cobalt', 'manufacture_year' => 2022, 'vehicle_type' => 'car', 'fuel_type' => 'benzin', 'fuel_norm' => 8.5, 'fuel_norm_ac' => 10.0, 'status' => 'active', 'color' => 'qora'],
            ['organization_id' => 6, 'state_number' => '06F001FF', 'model' => 'Ford Transit', 'manufacture_year' => 2020, 'vehicle_type' => 'truck', 'fuel_type' => 'dizel', 'fuel_norm' => 14.0, 'status' => 'active', 'color' => 'oq'],
            ['organization_id' => 7, 'state_number' => '07G001GG', 'model' => 'GAZelle Next', 'manufacture_year' => 2019, 'vehicle_type' => 'truck', 'fuel_type' => 'benzin', 'fuel_norm' => 16.0, 'status' => 'broken', 'color' => 'yashil'],
        ];

        foreach ($vehicles as $vehicle) {
            Vehicle::create($vehicle);
        }
    }
}
