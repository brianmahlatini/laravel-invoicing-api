<?php

namespace App\Enums;

/**
 * Invoice lifecycle. Transitions are explicit so an invoice can never jump
 * from draft to paid, or be edited after it has been sent to a customer.
 *
 *   draft ──send──► sent ──pay──► paid
 *     │               │
 *     └────void───────┴──void──► void
 */
enum InvoiceStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Paid = 'paid';
    case Void = 'void';

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, match ($this) {
            self::Draft => [self::Sent, self::Void],
            self::Sent => [self::Paid, self::Void],
            self::Paid, self::Void => [],
        }, true);
    }

    public function isEditable(): bool
    {
        return $this === self::Draft;
    }
}
