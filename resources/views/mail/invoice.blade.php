<p>Hello {{ $invoice->customer->name }},</p>
<p>Please find invoice <strong>{{ $invoice->number }}</strong> below, due on {{ $invoice->due_date->toFormattedDateString() }}.</p>
<table cellpadding="6" style="border-collapse: collapse">
    <tr><th align="left">Item</th><th align="right">Qty</th><th align="right">Amount</th></tr>
    @foreach ($invoice->lines as $line)
        <tr>
            <td>{{ $line->description }}</td>
            <td align="right">{{ $line->quantity }}</td>
            <td align="right">{{ number_format(($line->net_cents + $line->tax_cents) / 100, 2) }}</td>
        </tr>
    @endforeach
    <tr><td colspan="2" align="right"><strong>Total ({{ $invoice->currency }})</strong></td><td align="right"><strong>{{ number_format($invoice->total_cents / 100, 2) }}</strong></td></tr>
</table>
