<?php

namespace Tests\Unit\Fuel;

use App\Support\Fuel\Quantity;
use PHPUnit\Framework\TestCase;

class QuantityTest extends TestCase
{
    public function test_to_hundredths_rounds_half_up(): void
    {
        $this->assertSame(3334, Quantity::toHundredths('33.335'));
        $this->assertSame(1200, Quantity::toHundredths(12));
        $this->assertSame(10, Quantity::toHundredths('0.1'));
        $this->assertSame(3333, Quantity::toHundredths(33.33));
    }

    public function test_format_pads_two_decimals(): void
    {
        $this->assertSame('0.01', Quantity::format(1));
        $this->assertSame('1250.50', Quantity::format(125050));
        $this->assertSame('-5.00', Quantity::format(-500));
    }
}
