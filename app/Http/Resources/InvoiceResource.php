<?php

namespace App\Http\Resources;

use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Invoice */
class InvoiceResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'status' => $this->status->value,
            'overdue' => $this->isOverdue(),
            'currency' => $this->currency,
            'issue_date' => $this->issue_date->toDateString(),
            'due_date' => $this->due_date->toDateString(),
            'subtotal_cents' => $this->subtotal_cents,
            'tax_cents' => $this->tax_cents,
            'total_cents' => $this->total_cents,
            'notes' => $this->notes,
            'sent_at' => $this->sent_at?->toIso8601String(),
            'paid_at' => $this->paid_at?->toIso8601String(),
            'customer' => new CustomerResource($this->whenLoaded('customer')),
            'lines' => $this->whenLoaded('lines', fn () => $this->lines->map(fn ($l) => [
                'description' => $l->description,
                'quantity' => $l->quantity,
                'unit_price_cents' => $l->unit_price_cents,
                'tax_rate_bps' => $l->tax_rate_bps,
                'net_cents' => $l->net_cents,
                'tax_cents' => $l->tax_cents,
            ])),
        ];
    }
}
