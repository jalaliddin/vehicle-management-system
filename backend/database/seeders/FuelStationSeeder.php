<?php

namespace Database\Seeders;

use App\Models\FuelStation;
use Illuminate\Database\Seeder;

class FuelStationSeeder extends Seeder
{
    public function run(): void
    {
        $stations = [
            ['organization_id' => 1, 'name' => 'QQP-MGQB Benzin', 'fuel_type' => 'benzin', 'capacity' => 10000, 'current_balance' => 3200, 'min_threshold' => 1000],
            ['organization_id' => 1, 'name' => 'QQP-MGQB Dizel', 'fuel_type' => 'dizel', 'capacity' => 8000, 'current_balance' => 1500, 'min_threshold' => 800],
            ['organization_id' => 2, 'name' => 'Tuley Benzin', 'fuel_type' => 'benzin', 'capacity' => 5000, 'current_balance' => 400, 'min_threshold' => 500],
            ['organization_id' => 2, 'name' => 'Tuley Dizel', 'fuel_type' => 'dizel', 'capacity' => 6000, 'current_balance' => 4200, 'min_threshold' => 600],
            ['organization_id' => 3, 'name' => 'Akchalok Dizel', 'fuel_type' => 'dizel', 'capacity' => 7000, 'current_balance' => 2100, 'min_threshold' => 700],
            ['organization_id' => 4, 'name' => "Qo'ng'irot Benzin", 'fuel_type' => 'benzin', 'capacity' => 5000, 'current_balance' => 4800, 'min_threshold' => 500],
            ['organization_id' => 5, 'name' => 'Urganch Benzin', 'fuel_type' => 'benzin', 'capacity' => 12000, 'current_balance' => 8500, 'min_threshold' => 1200],
            ['organization_id' => 5, 'name' => 'Urganch Gaz', 'fuel_type' => 'gaz', 'capacity' => 5000, 'current_balance' => 900, 'min_threshold' => 500],
            ['organization_id' => 6, 'name' => 'Zaungur Dizel', 'fuel_type' => 'dizel', 'capacity' => 8000, 'current_balance' => 6200, 'min_threshold' => 800],
            ['organization_id' => 7, 'name' => 'JUS-SM Dizel', 'fuel_type' => 'dizel', 'capacity' => 15000, 'current_balance' => 3000, 'min_threshold' => 1500],
        ];

        foreach ($stations as $station) {
            FuelStation::create($station);
        }
    }
}
