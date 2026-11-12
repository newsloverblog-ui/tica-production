<!doctype html>
<html><head><meta charset="utf-8"><style>
  body{font-family:DejaVu Sans,sans-serif;color:#172033;font-size:11px}h1{font-size:22px;margin:0 0 4px}.muted{color:#657087}.summary{width:100%;border-collapse:collapse;margin:20px 0}.summary td{width:25%;padding:10px;border:1px solid #dde2ea}.summary strong{display:block;font-size:15px;margin-top:4px}.ledger{width:100%;border-collapse:collapse}.ledger th,.ledger td{padding:7px 5px;border-bottom:1px solid #dde2ea;text-align:left}.ledger th{background:#f3f5f8}.right{text-align:right!important}.footer{margin-top:18px;color:#657087;font-size:9px}
</style></head><body>
@php $symbol = optional($currency)->base_currency_symbol ?: '₦'; $money = fn ($value) => $symbol . number_format((float) $value, 2); @endphp
<h1>Tica finance statement</h1>
<div class="muted">{{ $organizer->verified_legal_name ?: $organizer->username }} · {{ $statement['from']->format('Y-m-d') }} to {{ $statement['to']->format('Y-m-d') }}</div>
<table class="summary"><tr><td>Gross sales<strong>{{ $money($statement['gross_sales']) }}</strong></td><td>Platform fees<strong>{{ $money($statement['platform_fees']) }}</strong></td><td>Refunds<strong>{{ $money($statement['refunds']) }}</strong></td><td>Net sales<strong>{{ $money($statement['net_sales']) }}</strong></td></tr><tr><td>Available balance<strong>{{ $money($statement['available_balance']) }}</strong></td><td>Pending payouts<strong>{{ $money($statement['pending_payouts']) }}</strong></td><td>Paid out<strong>{{ $money($statement['paid_out']) }}</strong></td><td>Payout fees<strong>{{ $money($statement['payout_fees']) }}</strong></td></tr></table>
<table class="ledger"><thead><tr><th>Date</th><th>Reference</th><th>Type</th><th>Status</th><th class="right">Gross</th><th class="right">Fee</th><th class="right">Net</th></tr></thead><tbody>
@foreach ($statement['transactions'] as $row)<tr><td>{{ $row['occurred_at'] ? \Carbon\Carbon::parse($row['occurred_at'])->format('Y-m-d H:i') : '-' }}</td><td>{{ $row['reference'] }}</td><td>{{ str_replace('_', ' ', $row['type']) }}</td><td>{{ $row['status'] }}</td><td class="right">{{ $money($row['gross_amount']) }}</td><td class="right">{{ $money($row['platform_fee']) }}</td><td class="right">{{ $money($row['net_amount']) }}</td></tr>@endforeach
</tbody></table>
<div class="footer">Generated {{ now()->toIso8601String() }}. Gateway processing fees are excluded because they are reconciled at settlement level and are not yet allocated per organizer.</div>
</body></html>
