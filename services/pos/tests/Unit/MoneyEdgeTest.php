<?php

namespace Tests\Unit;

use App\Support\Money;
use PHPUnit\Framework\TestCase;

class MoneyEdgeTest extends TestCase
{
    public function test_half_up_edges(): void
    {
        $this->assertSame('0.01', Money::round('0.005'));
        $this->assertSame('0.00', Money::round('0.004'));
        $this->assertSame('2.68', Money::round('2.675'));
        $this->assertSame('-2.68', Money::round('-2.675'));
        $this->assertSame('3666.63', Money::mul('33333.00', '0.11'));
        $this->assertSame('0.00', Money::round('0'));
    }

    public function test_tax_rounding_edge_via_formula(): void
    {
        // subtotal 33.333 x 11% = 3666.63 (bukan float 3666.62999...)
        $taxable = Money::sub('33333.00', '0.00');
        $tax = Money::round(bcmul($taxable, '0.11', 6));
        $this->assertSame('3666.63', $tax);
        $this->assertSame('36999.63', Money::add($taxable, $tax));
    }

    public function test_full_discount_zero_total(): void
    {
        $subtotal = '100000.00';
        $discount = '100000.00';
        $taxable = Money::sub($subtotal, $discount);
        $this->assertSame('0.00', $taxable);
        $this->assertSame('0.00', Money::round(bcmul($taxable, '0.11', 6)));
    }
}
