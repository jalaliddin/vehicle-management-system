<?php

namespace Tests\Unit\Fuel;

use App\Support\Fuel\Consumption;
use PHPUnit\Framework\TestCase;

class ConsumptionTest extends TestCase
{
    public function test_estimate_uses_litres_after_first_reading(): void
    {
        $this->assertSame(
            ['first_odometer' => 10000, 'last_odometer' => 10800, 'km' => 800, 'litres' => '80.00', 'per_100km' => 10.0],
            Consumption::estimate([
                ['odometer' => 10000, 'amount' => '40'],
                ['odometer' => 10400, 'amount' => '38'],
                ['odometer' => 10800, 'amount' => '42'],
            ]),
        );
    }

    public function test_estimate_is_null_with_fewer_than_two_readings_or_zero_km(): void
    {
        $this->assertNull(Consumption::estimate([['odometer' => 10000, 'amount' => '40']]));
        $this->assertNull(Consumption::estimate([]));
        $this->assertNull(Consumption::estimate([['odometer' => 10000, 'amount' => '40'], ['odometer' => 10000, 'amount' => '10']]));
    }
}
