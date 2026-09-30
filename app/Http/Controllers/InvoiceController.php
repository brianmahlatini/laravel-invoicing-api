<?php

namespace App\Http\Controllers;

use App\Enums\InvoiceStatus;
use App\Http\Requests\InvoiceRequest;
use App\Http\Resources\InvoiceResource;
use App\Jobs\SendInvoiceEmail;
use App\Models\Customer;
use App\Models\Invoice;
use App\Services\InvoiceCalculator;
use App\Services\InvoiceNumberGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class InvoiceController extends Controller
{
    public function __construct(private readonly InvoiceCalculator $calculator, private readonly InvoiceNumberGenerator $numbers) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate(['status' => ['sometimes', Rule::enum(InvoiceStatus::class)], 'overdue' => ['sometimes', 'boolean']]);

        $invoices = $request->user()->invoices()
            ->with('customer') // eager load: one query for customers, not one per invoice
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->boolean('overdue'), fn ($q) => $q->overdue())
            ->latest('issue_date')->latest('id')
            ->paginate(min((int) $request->integer('per_page', 20), 100));

        return InvoiceResource::collection($invoices);
    }

    public function store(InvoiceRequest $request): JsonResponse
    {
        $data = $request->validated();
        $user = $request->user();
        $customer = Customer::findOrFail($data['customer_id']);

        $invoice = DB::transaction(function () use ($data, $user, $customer) {
            $invoice = new Invoice(Arr::except($data, ['lines']));
            $invoice->forceFill([
                'user_id' => $user->id,
                'currency' => $customer->currency,
                'number' => $this->numbers->next($user, (int) substr($data['issue_date'], 0, 4)),
            ]);
            $this->applyLines($invoice, $data['lines']);

            return $invoice;
        });

        return (new InvoiceResource($invoice->load('customer', 'lines')))->response()->setStatusCode(201);
    }

    public function show(Invoice $invoice): InvoiceResource
    {
        Gate::authorize('view', $invoice);

        return new InvoiceResource($invoice->load('customer', 'lines'));
    }

    public function update(InvoiceRequest $request, Invoice $invoice): InvoiceResource
    {
        Gate::authorize('update', $invoice);
        $data = $request->validated();

        DB::transaction(function () use ($invoice, $data) {
            $invoice->fill(Arr::except($data, ['lines']));
            if (isset($data['customer_id'])) {
                $invoice->currency = Customer::findOrFail($data['customer_id'])->currency;
            }
            if (isset($data['lines'])) {
                $invoice->lines()->delete();
                $this->applyLines($invoice, $data['lines']);
            } else {
                $invoice->save();
            }
        });

        return new InvoiceResource($invoice->fresh(['customer', 'lines']));
    }

    public function destroy(Invoice $invoice): JsonResponse
    {
        Gate::authorize('delete', $invoice);
        $invoice->delete();

        return response()->json(null, 204);
    }

    public function send(Request $request, Invoice $invoice): InvoiceResource
    {
        $this->transition($invoice, InvoiceStatus::Sent, ['sent_at' => now()]);
        // Queued after commit: the email never goes out for a change that rolled back.
        SendInvoiceEmail::dispatch($invoice->id)->afterCommit();

        return new InvoiceResource($invoice->load('customer', 'lines'));
    }

    public function pay(Invoice $invoice): InvoiceResource
    {
        $this->transition($invoice, InvoiceStatus::Paid, ['paid_at' => now()]);

        return new InvoiceResource($invoice->load('customer', 'lines'));
    }

    public function void(Invoice $invoice): InvoiceResource
    {
        $this->transition($invoice, InvoiceStatus::Void);

        return new InvoiceResource($invoice->load('customer', 'lines'));
    }

    /** @param array<string, mixed> $extra */
    private function transition(Invoice $invoice, InvoiceStatus $next, array $extra = []): void
    {
        Gate::authorize('transition', $invoice);
        DB::transaction(function () use ($invoice, $next, $extra) {
            $locked = Invoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail(); // no double-send / double-pay race
            abort_unless($locked->status->canTransitionTo($next), 409, "Cannot change a {$locked->status->value} invoice to {$next->value}.");
            $locked->forceFill(['status' => $next] + $extra)->save();
            $invoice->setRawAttributes($locked->getAttributes(), true);
        });
    }

    /** @param list<array<string, int|string>> $lines */
    private function applyLines(Invoice $invoice, array $lines): void
    {
        $normalized = array_map(fn ($l) => [
            'description' => (string) $l['description'],
            'quantity' => (int) $l['quantity'],
            'unit_price_cents' => (int) $l['unit_price_cents'],
            'tax_rate_bps' => (int) ($l['tax_rate_bps'] ?? 0),
        ], array_values($lines));
        $totals = $this->calculator->calculate($normalized);

        $invoice->forceFill([
            'subtotal_cents' => $totals['subtotal_cents'],
            'tax_cents' => $totals['tax_cents'],
            'total_cents' => $totals['total_cents'],
        ])->save();

        foreach ($normalized as $i => $line) {
            $invoice->lines()->create($line + ['position' => $i + 1] + $totals['lines'][$i]);
        }
    }
}
