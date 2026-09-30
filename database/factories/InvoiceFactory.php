<?php

namespace Database\Factories;

use App\Enums\InvoiceStatus;
use App\Models\Customer;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Invoice> */
class InvoiceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'user_id' => fn (array $a) => Customer::find($a['customer_id'])->user_id,
            'number' => 'INV-'.fake()->unique()->numerify('####-####'),
            'status' => InvoiceStatus::Draft,
            'currency' => 'ZAR',
            'issue_date' => now()->subDays(10)->toDateString(),
            'due_date' => now()->addDays(20)->toDateString(),
            'subtotal_cents' => 10000,
            'tax_cents' => 1500,
            'total_cents' => 11500,
        ];
    }

    public function sent(): static
    {
        return $this->state(['status' => InvoiceStatus::Sent, 'sent_at' => now()->subDays(5)]);
    }

    public function overdue(): static
    {
        return $this->sent()->state(['due_date' => now()->subDays(3)->toDateString()]);
    }
}
