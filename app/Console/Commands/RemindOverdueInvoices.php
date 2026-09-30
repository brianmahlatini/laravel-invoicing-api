<?php

namespace App\Console\Commands;

use App\Jobs\SendInvoiceEmail;
use App\Models\Invoice;
use Illuminate\Console\Command;

class RemindOverdueInvoices extends Command
{
    protected $signature = 'invoices:remind-overdue {--dry-run : List what would be sent}';

    protected $description = 'Queue reminder emails for sent invoices past their due date';

    public function handle(): int
    {
        $count = 0;
        // chunkById keeps memory flat on large tables (no loading everything at once).
        Invoice::query()->overdue()->chunkById(200, function ($invoices) use (&$count) {
            foreach ($invoices as $invoice) {
                $count++;
                if ($this->option('dry-run')) {
                    $this->line("would remind {$invoice->number}");
                } else {
                    SendInvoiceEmail::dispatch($invoice->id);
                }
            }
        });
        $this->info("{$count} overdue invoice(s) ".($this->option('dry-run') ? 'found' : 'queued for reminder'));

        return self::SUCCESS;
    }
}
