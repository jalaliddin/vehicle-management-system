<?php

namespace Database\Seeders;

use App\Models\Driver;
use Illuminate\Database\Seeder;

class DriverSeeder extends Seeder
{
    public function run(): void
    {
        $drivers = [
            ['organization_id' => 1, 'full_name' => 'Aliyev Hamid Rahimovich', 'license_number' => 'AA1234567', 'license_category' => 'B,C', 'pinfl' => '12345678901234', 'work_experience' => 12, 'phone' => '+998901234567', 'vehicle_id' => 1, 'status' => 'active'],
            ['organization_id' => 1, 'full_name' => 'Karimov Sherzod Baxtiyorovich', 'license_number' => 'AA2345678', 'license_category' => 'B', 'pinfl' => '23456789012345', 'work_experience' => 5, 'phone' => '+998902345678', 'vehicle_id' => 2, 'status' => 'active'],
            ['organization_id' => 2, 'full_name' => 'Toshmatov Jasur Islomovich', 'license_number' => 'BB1234567', 'license_category' => 'B,C,D', 'pinfl' => '34567890123456', 'work_experience' => 8, 'phone' => '+998903456789', 'vehicle_id' => 3, 'status' => 'active'],
            ['organization_id' => 2, 'full_name' => 'Yusupov Timur Aliyevich', 'license_number' => 'BB2345678', 'license_category' => 'C', 'pinfl' => '45678901234567', 'work_experience' => 15, 'phone' => '+998904567890', 'vehicle_id' => 4, 'status' => 'sick_leave'],
            ['organization_id' => 3, 'full_name' => 'Xasanov Bobur Normatovich', 'license_number' => 'CC1234567', 'license_category' => 'B,C,D', 'pinfl' => '56789012345678', 'work_experience' => 10, 'phone' => '+998905678901', 'vehicle_id' => 5, 'status' => 'active'],
            ['organization_id' => 5, 'full_name' => 'Raximov Sanjar Bekmurodovich', 'license_number' => 'EE1234567', 'license_category' => 'B', 'pinfl' => '67890123456789', 'work_experience' => 3, 'phone' => '+998906789012', 'vehicle_id' => 7, 'status' => 'active'],
            ['organization_id' => 5, 'full_name' => 'Nazarov Odil Tursunovich', 'license_number' => 'EE2345678', 'license_category' => 'B,C', 'pinfl' => '78901234567890', 'work_experience' => 7, 'phone' => '+998907890123', 'vehicle_id' => 8, 'status' => 'active'],
            ['organization_id' => 6, 'full_name' => 'Holmatov Dilshod Qodirovitch', 'license_number' => 'FF1234567', 'license_category' => 'C', 'pinfl' => '89012345678901', 'work_experience' => 20, 'phone' => '+998908901234', 'vehicle_id' => 9, 'status' => 'active'],
        ];

        foreach ($drivers as $driver) {
            Driver::create(array_merge($driver, ['employment_type' => 'staff']));
        }
    }
}
