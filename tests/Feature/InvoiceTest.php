<?php

namespace Tests\Feature;

use App\Jobs\SendInvoiceEmail;
use App\Mail\InvoiceMail;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class InvoiceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->customer = Customer::factory()->for($this->user)->create(['currency' => 'USD']);
        $this->actingAs($this->user, 'sanctum');
    }

    /** @return array<string, mixed> */
    private function payload(array $over = []): array
    {
        return $over + [
            'customer_id' => $this->customer->id,
            'issue_date' => '2026-09-01',
            'due_date' => '2026-09-30',
            'lines' => [
                ['description' => 'Consulting', 'quantity' => 3, 'unit_price_cents' => 333, 'tax_rate_bps' => 1500],
                ['description' => 'Hosting', 'quantity' => 1, 'unit_price_cents' => 10],
            ],
        ];
    }

    public function test_create_computes_totals_and_sequential_numbers(): void
    {
        $first = $this->postJson('/api/invoices', $this->payload())->assertCreated();
        $first->assertJsonPath('data.number', 'INV-2026-0001')
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.currency', 'USD')
            ->assertJsonPath('data.subtotal_cents', 1009)
            ->assertJsonPath('data.tax_cents', 150)
            ->assertJsonPath('data.total_cents', 1159)
            ->assertJsonCount(2, 'data.lines');

        $this->postJson('/api/invoices', $this->payload())->assertJsonPath('data.number', 'INV-2026-0002');
        $this->postJson('/api/invoices', $this->payload(['issue_date' => '2027-01-05', 'due_date' => '2027-02-05']))
            ->assertJsonPath('data.number', 'INV-2027-0001'); // sequence restarts per year
    }

    public function test_client_cannot_set_totals_status_or_owner(): void
    {
        $this->postJson('/api/invoices', $this->payload(['total_cents' => 1, 'status' => 'paid', 'user_id' => 999]))
            ->assertCreated()->assertJsonPath('data.total_cents', 1159)->assertJsonPath('data.status', 'draft');
        $this->assertSame($this->user->id, Invoice::first()->user_id);
    }

    public function test_validation(): void
    {
        $this->postJson('/api/invoices', $this->payload(['due_date' => '2026-08-01', 'lines' => [['description' => '', 'quantity' => 0, 'unit_price_cents' => -1]]]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['due_date', 'lines.0.description', 'lines.0.quantity', 'lines.0.unit_price_cents']);
    }

    public function test_cannot_invoice_another_users_customer(): void
    {
        $other = Customer::factory()->create();
        $this->postJson('/api/invoices', $this->payload(['customer_id' => $other->id]))
            ->assertStatus(422)->assertJsonValidationErrors('customer_id');
    }

    public function test_users_cannot_see_each_others_invoices(): void
    {
        $theirs = Invoice::factory()->create();
        $this->getJson("/api/invoices/{$theirs->id}")->assertForbidden();
        $this->patchJson("/api/invoices/{$theirs->id}", ['notes' => 'x'])->assertForbidden();
        $this->postJson("/api/invoices/{$theirs->id}/pay")->assertForbidden();
        $this->getJson('/api/invoices')->assertJsonCount(0, 'data');
    }

    public function test_send_queues_one_email_after_commit_and_locks_editing(): void
    {
        Queue::fake();
        $id = $this->postJson('/api/invoices', $this->payload())->json('data.id');

        $this->postJson("/api/invoices/{$id}/send")->assertOk()->assertJsonPath('data.status', 'sent');
        Queue::assertPushed(SendInvoiceEmail::class, fn ($job) => $job->invoiceId === $id);

        $this->patchJson("/api/invoices/{$id}", ['notes' => 'changed'])->assertForbidden();
        $this->deleteJson("/api/invoices/{$id}")->assertForbidden();
        $this->postJson("/api/invoices/{$id}/send")->assertStatus(409); // no double send
    }

    public function test_email_job_sends_to_customer(): void
    {
        Mail::fake();
        $invoice = Invoice::factory()->for($this->user)->for($this->customer)->sent()->create();
        (new SendInvoiceEmail($invoice->id))->handle();
        Mail::assertSent(InvoiceMail::class, fn ($m) => $m->hasTo($this->customer->email));
    }

    public function test_lifecycle_rules(): void
    {
        Queue::fake();
        $id = $this->postJson('/api/invoices', $this->payload())->json('data.id');
        $this->postJson("/api/invoices/{$id}/pay")->assertStatus(409);             // draft can't be paid
        $this->postJson("/api/invoices/{$id}/send")->assertOk();
        $this->postJson("/api/invoices/{$id}/pay")->assertOk()->assertJsonPath('data.status', 'paid');
        $this->postJson("/api/invoices/{$id}/void")->assertStatus(409);            // paid is final
    }

    public function test_draft_update_recalculates(): void
    {
        $id = $this->postJson('/api/invoices', $this->payload())->json('data.id');
        $this->patchJson("/api/invoices/{$id}", ['lines' => [['description' => 'Flat fee', 'quantity' => 1, 'unit_price_cents' => 50000, 'tax_rate_bps' => 1500]]])
            ->assertOk()->assertJsonPath('data.total_cents', 57500)->assertJsonCount(1, 'data.lines');
    }

    public function test_filter_overdue_and_status(): void
    {
        Invoice::factory()->for($this->user)->for($this->customer)->overdue()->create();
        Invoice::factory()->for($this->user)->for($this->customer)->sent()->create();
        Invoice::factory()->for($this->user)->for($this->customer)->create();

        $this->getJson('/api/invoices?overdue=1')->assertJsonCount(1, 'data')->assertJsonPath('data.0.overdue', true);
        $this->getJson('/api/invoices?status=sent')->assertJsonCount(2, 'data');
        $this->getJson('/api/invoices?status=bogus')->assertStatus(422);
    }

    public function test_reminder_command_queues_only_overdue(): void
    {
        Queue::fake();
        Invoice::factory()->for($this->user)->for($this->customer)->overdue()->count(2)->create();
        Invoice::factory()->for($this->user)->for($this->customer)->sent()->create();

        $this->artisan('invoices:remind-overdue')->expectsOutputToContain('2 overdue invoice(s) queued')->assertSuccessful();
        Queue::assertPushed(SendInvoiceEmail::class, 2);
    }

    public function test_customer_with_invoices_cannot_be_deleted(): void
    {
        $this->postJson('/api/invoices', $this->payload())->assertCreated();
        $this->deleteJson("/api/customers/{$this->customer->id}")->assertStatus(409);
    }
}
