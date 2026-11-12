<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>{{ $invoice->invoice_number }}</title>
  <style>
    body { margin: 0; padding: 38px; color: #071224; font-family: DejaVu Sans, sans-serif; font-size: 12px; }
    .header { display: table; width: 100%; padding-bottom: 22px; border-bottom: 3px solid #fe9b59; }
    .header > div { display: table-cell; vertical-align: top; }
    .right { text-align: right; }
    h1 { margin: 0 0 6px; font-size: 30px; }
    h2 { margin: 0; font-size: 17px; }
    .muted { color: #667085; }
    .meta { width: 100%; margin: 26px 0; border-collapse: collapse; }
    .meta td { width: 50%; padding: 8px 0; vertical-align: top; }
    table.lines { width: 100%; border-collapse: collapse; }
    .lines th { padding: 11px 9px; color: #fff; background: #071224; text-align: left; }
    .lines td { padding: 11px 9px; border-bottom: 1px solid #e5e9f0; }
    .amount { text-align: right; }
    .totals { width: 42%; margin: 22px 0 0 auto; border-collapse: collapse; }
    .totals td { padding: 6px 0; }
    .totals tr:last-child td { padding-top: 10px; border-top: 2px solid #071224; font-size: 16px; font-weight: bold; }
    .notice { margin-top: 30px; padding: 16px; border: 1px solid #e5e9f0; background: #f7f8fa; line-height: 1.55; }
  </style>
</head>
<body>
  <div class="header">
    <div><h1>Invoice</h1><h2>{{ $eventTitle }}</h2></div>
    <div class="right"><strong>{{ $invoice->invoice_number }}</strong><br><span class="muted">Issued {{ $invoice->issued_at->format('d M Y') }}<br>Due {{ $invoice->due_at->format('d M Y') }}</span></div>
  </div>
  <table class="meta">
    <tr>
      <td><strong>Bill to</strong><br>{{ $submission->primary_name }}<br>{{ $submission->primary_email }}</td>
      <td class="right"><strong>Purchase order</strong><br>{{ $invoice->purchase_order_number ?: 'Not supplied' }}</td>
    </tr>
  </table>
  <table class="lines">
    <thead><tr><th>Registration type</th><th>Quantity</th><th class="amount">Unit price</th><th class="amount">Amount</th></tr></thead>
    <tbody>
      @foreach ($invoice->line_items as $item)
        <tr><td>{{ $item['title'] }}</td><td>{{ $item['quantity'] }}</td><td class="amount">{{ $invoice->currency_symbol }}{{ number_format($item['unit_price'], 2) }}</td><td class="amount">{{ $invoice->currency_symbol }}{{ number_format($item['amount'], 2) }}</td></tr>
      @endforeach
    </tbody>
  </table>
  <table class="totals">
    <tr><td>Subtotal</td><td class="amount">{{ $invoice->currency_symbol }}{{ number_format((float) $invoice->subtotal, 2) }}</td></tr>
    <tr><td>Tax</td><td class="amount">{{ $invoice->currency_symbol }}{{ number_format((float) $invoice->tax, 2) }}</td></tr>
    <tr><td>Total</td><td class="amount">{{ $invoice->currency_symbol }}{{ number_format((float) $invoice->total, 2) }} {{ $invoice->currency }}</td></tr>
  </table>
  <div class="notice"><strong>Payment and admission</strong><br>This invoice is not a ticket or proof of admission. The organizer must verify the company payment and mark this invoice paid before individual tickets are issued.</div>
</body>
</html>
