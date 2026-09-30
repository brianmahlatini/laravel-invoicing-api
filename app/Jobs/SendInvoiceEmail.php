<?php

namespace App\Jobs;

use App\Mail\InvoiceMail;
use App\Models\Invoice;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

/**
 * Sends one invoice email. Carries only the id (not a serialized model) so
 * a retry always sees current data; unique per invoice so a double click
 * can't queue two emails.
 */
class SendInvoiceEmail implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public function __construct(public readonly int $invoiceId) {}

    /** @return list<int> exponential-ish backoff in seconds between retries */
    public function backoff(): array
    {
        return [10, 60, 300, 900];
    }

    public function uniqueId(): string
    {
        return (string) $this->invoiceId;
    }

    public function handle(): void
    {
        $invoice = Invoice::with('customer', 'lines')->find($this->invoiceId);
        if ($invoice === null || $invoice->sent_at === null) {
            return; // deleted or never actually sent: nothing to do
        }
        Mail::to($invoice->customer->email)->send(new InvoiceMail($invoice));
    }
}
