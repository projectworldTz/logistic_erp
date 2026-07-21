<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 0; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1a1a1a; margin: 0; }
        .page { padding: 28px 36px 20px 36px; }
        .band { background-color: {{ $brand['primary'] }}; height: 10px; width: 100%; }
        .header { width: 100%; margin-bottom: 24px; }
        .header td { vertical-align: top; }
        .logo { max-width: 120px; max-height: 80px; }
        .company-name { font-size: 16px; font-weight: bold; color: {{ $brand['primary'] }}; }
        .muted { color: #666; }
        .doc-title { font-size: 26px; font-weight: bold; text-transform: uppercase; text-align: right; color: {{ $brand['primary'] }}; }
        .doc-status { text-align: right; text-transform: uppercase; font-size: 11px; letter-spacing: 1px; color: #666; }
        .meta-table { width: 100%; margin: 20px 0; border-collapse: collapse; }
        .meta-table td { padding: 4px 0; }
        .bill-to { background-color: {{ $brand['primaryLighter'] }}; border-radius: 4px; padding: 12px 14px; }
        .bill-to-label { font-size: 10px; text-transform: uppercase; color: #666; margin-bottom: 4px; }
        .amounts { width: 100%; margin-top: 24px; border-collapse: collapse; }
        .amounts td { padding: 6px 0; }
        .amounts .label { text-align: right; padding-right: 16px; color: #444; }
        .amounts .value { text-align: right; width: 120px; }
        .amounts .total-row td { background-color: {{ $brand['primaryLight'] }}; }
        .amounts .total-row .label, .amounts .total-row .value { font-weight: bold; font-size: 14px; padding: 10px 0; }
        .amounts .total-row .label { padding-left: 12px; border-radius: 4px 0 0 4px; }
        .amounts .total-row .value { padding-right: 12px; border-radius: 0 4px 4px 0; }
        .notes { margin-top: 32px; padding-top: 12px; border-top: 1px solid #ddd; color: #444; }
        .footer { margin-top: 40px; padding-top: 14px; border-top: 2px solid {{ $brand['primary'] }}; text-align: center; color: #999; font-size: 10px; }
    </style>
</head>
<body>
    <div class="band"></div>
    <div class="page">
    <table class="header">
        <tr>
            <td width="60%">
                @if($logoBase64)
                    <img src="{{ $logoBase64 }}" class="logo"><br>
                @endif
                <div class="company-name">{{ $company->name }}</div>
                <div class="muted">
                    {{ $company->address }}<br>
                    {{ $company->city }}, {{ $company->country }}<br>
                    @if($company->phone) {{ $company->phone }}<br> @endif
                    @if($company->email) {{ $company->email }} @endif
                </div>
            </td>
            <td width="40%">
                <div class="doc-title">{{ $isReceipt ? 'Receipt' : 'Invoice' }}</div>
                <div class="doc-status">{{ $invoice->status }}</div>
            </td>
        </tr>
    </table>

    <table class="meta-table">
        <tr>
            <td width="50%">
                <div class="bill-to">
                    <div class="bill-to-label">Bill To</div>
                    <strong>{{ $invoice->customer->company_name }}</strong><br>
                    @if($invoice->customer->address)
                        {{ $invoice->customer->address }}<br>
                        {{ $invoice->customer->city }}, {{ $invoice->customer->country }}<br>
                    @endif
                    @if($invoice->customer->email) {{ $invoice->customer->email }}<br> @endif
                    @if($invoice->customer->phone) {{ $invoice->customer->phone }} @endif
                </div>
            </td>
            <td width="50%">
                <table style="width:100%">
                    <tr><td class="muted">{{ $isReceipt ? 'Receipt' : 'Invoice' }} No.</td><td style="text-align:right"><strong>{{ $invoice->invoice_number }}</strong></td></tr>
                    <tr><td class="muted">Issue Date</td><td style="text-align:right">{{ \Illuminate\Support\Carbon::parse($invoice->issue_date)->format('M d, Y') }}</td></tr>
                    <tr><td class="muted">Due Date</td><td style="text-align:right">{{ \Illuminate\Support\Carbon::parse($invoice->due_date)->format('M d, Y') }}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    <table class="amounts">
        <tr><td class="label">Subtotal</td><td class="value">{{ $invoice->currency }} {{ number_format((float) $invoice->subtotal, 2) }}</td></tr>
        <tr><td class="label">Tax</td><td class="value">{{ $invoice->currency }} {{ number_format((float) $invoice->tax_amount, 2) }}</td></tr>
        <tr class="total-row"><td class="label">{{ $isReceipt ? 'Amount Paid' : 'Total Due' }}</td><td class="value">{{ $invoice->currency }} {{ number_format((float) $invoice->total_amount, 2) }}</td></tr>
    </table>

    @if($invoice->notes)
        <div class="notes">
            <div class="bill-to-label">Notes</div>
            {{ $invoice->notes }}
        </div>
    @endif

    @if($trackingQrDataUri)
        <table style="width:100%; margin-top: 24px;">
            <tr>
                <td style="text-align:center;">
                    <img src="{{ $trackingQrDataUri }}" style="width:100px; height:100px;"><br>
                    <span class="muted" style="font-size:10px;">Scan to track shipment {{ $invoice->shipment->shipment_number }}</span>
                </td>
            </tr>
        </table>
    @endif

    <div class="footer">
        {{ $company->name }}
        @if($company->registration_number) &middot; Reg. No. {{ $company->registration_number }} @endif
        @if($company->tax_number) &middot; Tax No. {{ $company->tax_number }} @endif
    </div>
    </div>
</body>
</html>
