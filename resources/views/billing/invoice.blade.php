<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Invoice {{ $payment->invoice_number ?: ('#'.$payment->id) }} · {{ $company_name }}</title>
    <style>
        :root {
            --ink: #0a2540;
            --muted: #697386;
            --line: #e3e8ee;
            --soft: #f7fafc;
            --accent: #FF6600;
            --ok: #0d9488;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            color: var(--ink);
            background: #eef2f6;
            line-height: 1.45;
        }
        .page {
            max-width: 800px;
            margin: 28px auto;
            padding: 0 16px 40px;
        }
        .toolbar {
            display: flex;
            justify-content: flex-end;
            gap: 8px;
            margin-bottom: 12px;
        }
        .toolbar a,
        .toolbar button {
            appearance: none;
            border: 1px solid #cfd7e3;
            background: #fff;
            color: var(--ink);
            border-radius: 6px;
            padding: 8px 12px;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
        }
        .toolbar .primary {
            background: var(--accent);
            border-color: var(--accent);
            color: #fff;
        }
        .invoice {
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 12px;
            box-shadow: 0 8px 30px rgba(15, 23, 42, 0.06);
            padding: 40px 44px;
        }
        .top {
            display: flex;
            justify-content: space-between;
            gap: 24px;
            align-items: flex-start;
            padding-bottom: 28px;
            border-bottom: 1px solid var(--line);
        }
        .brand img {
            height: 36px;
            width: auto;
            display: block;
        }
        .brand .fallback {
            font-size: 22px;
            font-weight: 800;
            letter-spacing: -0.02em;
            color: var(--accent);
        }
        .brand p {
            margin: 8px 0 0;
            color: var(--muted);
            font-size: 13px;
        }
        .meta {
            text-align: right;
        }
        .meta h1 {
            margin: 0;
            font-size: 28px;
            font-weight: 700;
            letter-spacing: -0.03em;
        }
        .meta p {
            margin: 6px 0 0;
            color: var(--muted);
            font-size: 13px;
        }
        .amount-due {
            margin-top: 22px;
            background: var(--soft);
            border: 1px solid var(--line);
            border-radius: 10px;
            padding: 16px 18px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
        }
        .amount-due span {
            color: var(--muted);
            font-size: 13px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .amount-due strong {
            font-size: 28px;
            letter-spacing: -0.03em;
        }
        .parties {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            margin: 28px 0;
        }
        .parties h2 {
            margin: 0 0 8px;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--muted);
        }
        .parties p {
            margin: 0;
            font-size: 14px;
        }
        .parties .muted { color: var(--muted); font-size: 13px; margin-top: 4px; }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
        }
        th {
            text-align: left;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: var(--muted);
            padding: 10px 0;
            border-bottom: 1px solid var(--line);
        }
        th.num, td.num { text-align: right; }
        td {
            padding: 16px 0;
            border-bottom: 1px solid var(--line);
            font-size: 14px;
            vertical-align: top;
        }
        .item-title { font-weight: 600; }
        .item-sub { color: var(--muted); font-size: 12px; margin-top: 4px; }
        .totals {
            margin-top: 8px;
            margin-left: auto;
            width: min(320px, 100%);
        }
        .totals-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            font-size: 14px;
            color: var(--muted);
        }
        .totals-row.is-total {
            border-top: 1px solid var(--line);
            margin-top: 6px;
            padding-top: 14px;
            color: var(--ink);
            font-weight: 700;
            font-size: 16px;
        }
        .status {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border-radius: 999px;
            padding: 4px 10px;
            font-size: 12px;
            font-weight: 700;
            background: #ecfdf5;
            color: var(--ok);
        }
        .status.is-pending { background: #fff7ed; color: #c2410c; }
        .status.is-failed, .status.is-rejected { background: #fef2f2; color: #b91c1c; }
        .footer {
            margin-top: 36px;
            padding-top: 18px;
            border-top: 1px solid var(--line);
            color: var(--muted);
            font-size: 12px;
        }
        @media print {
            body { background: #fff; }
            .page { margin: 0; max-width: none; padding: 0; }
            .toolbar { display: none; }
            .invoice { box-shadow: none; border: 0; border-radius: 0; padding: 0; }
        }
        @media (max-width: 640px) {
            .invoice { padding: 24px 18px; }
            .top, .parties { grid-template-columns: 1fr; display: grid; }
            .meta { text-align: left; }
        }
    </style>
</head>
<body>
@php
    $invoiceNo = $payment->invoice_number ?: ('INV-'.$payment->id);
    $status = strtolower((string) ($payment->status ?: 'paid'));
    $billDate = optional($payment->paid_at ?? $payment->created_at)->format('M j, Y');
    $customer = $payment->user;
    $interval = $payment->subscription?->billing_interval ?: ($payment->plan?->billing_interval ?: 'month');
@endphp
<div class="page">
    <div class="toolbar">
        <a href="{{ route('billing.index') }}">Back to billing</a>
        <button type="button" class="primary" onclick="window.print()">Print / Save PDF</button>
    </div>

    <article class="invoice">
        <div class="top">
            <div class="brand">
                @if (! empty($logo_url))
                    <img src="{{ $logo_url }}" alt="{{ $company_name }}" onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                    <div class="fallback" style="display:none">{{ $company_name }}</div>
                @else
                    <div class="fallback">{{ $company_name }}</div>
                @endif
                <p>{{ $company_name }} · Click fraud & traffic protection</p>
            </div>
            <div class="meta">
                <h1>Invoice</h1>
                <p>{{ $invoiceNo }}</p>
                <p style="margin-top:10px">
                    <span class="status {{ in_array($status, ['pending', 'failed', 'rejected'], true) ? 'is-'.$status : '' }}">
                        {{ ucfirst($status) }}
                    </span>
                </p>
            </div>
        </div>

        <div class="amount-due">
            <span>{{ $status === 'paid' ? 'Amount paid' : 'Amount due' }}</span>
            <strong>{{ $amount_label }}</strong>
        </div>

        <div class="parties">
            <div>
                <h2>Bill to</h2>
                <p>{{ $customer?->name ?: 'Customer' }}</p>
                <p class="muted">{{ $customer?->email }}</p>
            </div>
            <div>
                <h2>Invoice details</h2>
                <p>Date: {{ $billDate ?: '—' }}</p>
                <p class="muted">Payment method: {{ $payment->masked_payment ?: ($payment->payment_method ?: 'Card / Stripe') }}</p>
                @if ($payment->bank_reference)
                    <p class="muted">Reference: {{ $payment->bank_reference }}</p>
                @endif
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Description</th>
                    <th class="num">Qty</th>
                    <th class="num">Unit price</th>
                    <th class="num">Amount</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <div class="item-title">{{ $plan_name }}</div>
                        <div class="item-sub">{{ ucfirst((string) $interval) }} subscription · Clickronix platform access</div>
                    </td>
                    <td class="num">1</td>
                    <td class="num">{{ $amount_label }}</td>
                    <td class="num">{{ $amount_label }}</td>
                </tr>
            </tbody>
        </table>

        <div class="totals">
            <div class="totals-row">
                <span>Subtotal</span>
                <span>{{ $amount_label }}</span>
            </div>
            <div class="totals-row">
                <span>Tax</span>
                <span>$0.00</span>
            </div>
            <div class="totals-row is-total">
                <span>Total</span>
                <span>{{ $amount_label }}</span>
            </div>
        </div>

        <div class="footer">
            <p>Questions? Contact {{ $support_email }}.</p>
            <p>Thank you for your business. This invoice was generated by {{ $company_name }}.</p>
        </div>
    </article>
</div>
</body>
</html>
