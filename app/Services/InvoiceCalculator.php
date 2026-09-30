<?php

namespace App\Services;

/**
 * Money is integer cents throughout: floats can't represent 0.10 exactly,
 * and rounding errors on invoices are legal problems, not cosmetic ones.
 * Tax is computed and rounded per line (half-up), then summed, which is
 * what customers can verify from the printed lines.
 */
final class InvoiceCalculator
{
    /**
     * @param  list<array{quantity: int, unit_price_cents: int, tax_rate_bps: int}>  $lines  tax in basis points (1500 = 15%)
     * @return array{lines: list<array{net_cents: int, tax_cents: int}>, subtotal_cents: int, tax_cents: int, total_cents: int}
     */
    public function calculate(array $lines): array
    {
        $out = [];
        $subtotal = 0;
        $tax = 0;
        foreach ($lines as $line) {
            $net = $line['quantity'] * $line['unit_price_cents'];
            $lineTax = intdiv($net * $line['tax_rate_bps'] + 5_000, 10_000); // half-up rounding in integer arithmetic
            $out[] = ['net_cents' => $net, 'tax_cents' => $lineTax];
            $subtotal += $net;
            $tax += $lineTax;
        }

        return ['lines' => $out, 'subtotal_cents' => $subtotal, 'tax_cents' => $tax, 'total_cents' => $subtotal + $tax];
    }
}
