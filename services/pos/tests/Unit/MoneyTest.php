<?php

namespace Tests\Unit;

use App\Support\Money;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function test_add_sub(): void
    {
        $this->assertSame('111000.00', Money::add('100000.00', '11000.00'));
        $this->assertSame('39000.00', Money::sub('150000.00', '111000.00'));
    }

    public function test_mul_and_tax(): void
    {
        // S1: 100.000 x 11% = 11.000 ; 50.000 x 11% = 5.500
        $this->assertSame('11000.00', Money::mul('100000.00', '0.11'));
        $this->assertSame('55500.00', Money::add('50000.00', Money::mul('50000.00', '0.11')));
    }

    public function test_round_half_up(): void
    {
        $this->assertSame('2.68', Money::round('2.675'));
        $this->assertSame('-5000.00', Money::sub('306000.00', '311000.00'));
        $this->assertSame('311000.00', Money::normalize(311000));
    }
}
