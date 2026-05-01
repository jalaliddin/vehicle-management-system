<?php

namespace Database\Seeders;

use App\Models\Organization;
use Illuminate\Database\Seeder;

class OrganizationSeeder extends Seeder
{
    public function run(): void
    {
        $organizations = [
            ['name' => 'Qoraqalpogiston MGQB', 'short_name' => 'QQP-MGQB', 'code' => 'QQP-MGQB'],
            ['name' => 'Tuley MGQB', 'short_name' => 'Tuley', 'code' => 'TLY-MGQB'],
            ['name' => 'Akchalok MGQB', 'short_name' => 'Akchalok', 'code' => 'AKC-MGQB'],
            ['name' => "Qo'ng'irot MGQB", 'short_name' => "Qo'ng'irot", 'code' => 'QNG-MGQB'],
            ['name' => 'Urganch MGQB', 'short_name' => 'Urganch', 'code' => 'URG-MGQB'],
            ['name' => 'Zaungur MGQB', 'short_name' => 'Zaungur', 'code' => 'ZNG-MGQB'],
            ['name' => 'Janubiy ustyurt sanoat maydonchasi', 'short_name' => 'JUS-SM', 'code' => 'JUS-SM'],
            ['name' => "Xo'jayli sanoat maydonchasi", 'short_name' => "Xo'jayli", 'code' => 'XJL-SM'],
            ['name' => "Qo'ng'irot TSTB", 'short_name' => "QNG-TSTB", 'code' => 'QNG-TSTB'],
            ['name' => "UTG Training o'quv markazi", 'short_name' => 'UTG-TRN', 'code' => 'UTG-TRN'],
            ['name' => "Gazenergikta'mir ICHTK", 'short_name' => 'GAZ-ICHTK', 'code' => 'GAZ-ICHTK'],
            ['name' => 'Qurilish-montaj boshqarmasi', 'short_name' => 'QMB', 'code' => 'QMB'],
            ['name' => "Moddiy-texnik ta'minot boshqarmasi", 'short_name' => 'MTT-BQ', 'code' => 'MTT-BQ'],
            ['name' => 'Sarimot sanoat maydonchasi', 'short_name' => 'Sarimot', 'code' => 'SRM-SM'],
        ];

        foreach ($organizations as $org) {
            Organization::create(array_merge($org, ['is_active' => true]));
        }
    }
}
