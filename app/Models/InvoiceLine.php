<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceLine extends Model
{
    public $timestamps = false;

    protected $fillable = ['position', 'description', 'quantity', 'unit_price_cents', 'tax_rate_bps', 'net_cents', 'tax_cents'];

    protected function casts(): array
    {
        return ['quantity' => 'integer', 'unit_price_cents' => 'integer', 'tax_rate_bps' => 'integer', 'net_cents' => 'integer', 'tax_cents' => 'integer'];
    }

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
