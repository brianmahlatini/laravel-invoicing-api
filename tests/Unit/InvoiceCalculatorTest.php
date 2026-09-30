<?php

namespace Tests\Unit;

use App\Enums\InvoiceStatus;
use App\Services\InvoiceCalculator;
use PHPUnit\Framework\TestCase;

class InvoiceCalculatorTest extends TestCase
{
    public function test_totals_use_integer_cents_and_per_line_half_up_tax(): void
    {
        $r = (new InvoiceCalculator)->calculate([
            ['quantity' => 3, 'unit_price_cents' => 333, 'tax_rate_bps' => 1500], // 999 net, 149.85 -> 150 tax
            ['quantity' => 1, 'unit_price_cents' => 10, 'tax_rate_bps' => 1500],  // 10 net, 1.5 -> 2 tax
            ['quantity' => 2, 'unit_price_cents' => 5000, 'tax_rate_bps' => 0],
        ]);
        $this->assertSame([['net_cents' => 999, 'tax_cents' => 150], ['net_cents' => 10, 'tax_cents' => 2], ['net_cents' => 10000, 'tax_cents' => 0]], $r['lines']);
        $this->assertSame(11009, $r['subtotal_cents']);
        $this->assertSame(152, $r['tax_cents']);
        $this->assertSame(11161, $r['total_cents']);
    }

    public function test_classic_float_trap_is_exact(): void
    {
        // 0.1 + 0.2 != 0.3 in floating point; in cents it is exact.
        $r = (new InvoiceCalculator)->calculate([
            ['quantity' => 1, 'unit_price_cents' => 10, 'tax_rate_bps' => 0],
            ['quantity' => 1, 'unit_price_cents' => 20, 'tax_rate_bps' => 0],
        ]);
        $this->assertSame(30, $r['total_cents']);
    }

    public function test_status_transitions(): void
    {
        $this->assertTrue(InvoiceStatus::Draft->canTransitionTo(InvoiceStatus::Sent));
        $this->assertTrue(InvoiceStatus::Sent->canTransitionTo(InvoiceStatus::Paid));
        $this->assertFalse(InvoiceStatus::Draft->canTransitionTo(InvoiceStatus::Paid));
        $this->assertFalse(InvoiceStatus::Paid->canTransitionTo(InvoiceStatus::Void));
        $this->assertFalse(InvoiceStatus::Void->canTransitionTo(InvoiceStatus::Sent));
        $this->assertTrue(InvoiceStatus::Draft->isEditable());
        $this->assertFalse(InvoiceStatus::Sent->isEditable());
    }
}
