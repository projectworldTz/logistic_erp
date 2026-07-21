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
        .company-name { font-size: 16px; font-weight: bold; color: {{ $brand['primary'] }}; }
        .muted { color: #666; }
        .doc-title { font-size: 26px; font-weight: bold; text-transform: uppercase; text-align: right; color: {{ $brand['primary'] }}; }
        .doc-sub { text-align: right; color: #666; }
        .meta-table { width: 100%; margin: 20px 0; border-collapse: collapse; }
        .meta-table td { padding: 4px 0; }
        .employee-box { background-color: {{ $brand['primaryLighter'] }}; border-radius: 4px; padding: 12px 14px; }
        .label-caps { font-size: 10px; text-transform: uppercase; color: #666; margin-bottom: 4px; }
        .lines { width: 100%; border-collapse: collapse; margin-top: 12px; }
        .lines th { text-align: left; font-size: 10px; text-transform: uppercase; color: {{ $brand['primary'] }}; border-bottom: 1px solid {{ $brand['primaryLight'] }}; padding: 6px 0; }
        .lines td { padding: 5px 0; border-bottom: 1px solid #f0f0f0; }
        .lines .amount { text-align: right; }
        .totals { width: 100%; margin-top: 16px; border-collapse: collapse; }
        .totals td { padding: 6px 0; }
        .totals .label { text-align: right; padding-right: 16px; color: #444; }
        .totals .value { text-align: right; width: 130px; }
        .totals .net-row td { background-color: {{ $brand['primaryLight'] }}; }
        .totals .net-row .label, .totals .net-row .value { font-weight: bold; font-size: 15px; padding: 10px 0; }
        .totals .net-row .label { padding-left: 12px; border-radius: 4px 0 0 4px; }
        .totals .net-row .value { padding-right: 12px; border-radius: 0 4px 4px 0; }
        .ytd { width: 100%; margin-top: 28px; border-collapse: collapse; }
        .ytd td { padding: 4px 0; }
        .footer { margin-top: 40px; padding-top: 14px; border-top: 2px solid {{ $brand['primary'] }}; text-align: center; color: #999; font-size: 10px; }
    </style>
</head>
<body>
    <div class="band"></div>
    <div class="page">
    <table class="header">
        <tr>
            <td width="60%">
                <div class="company-name">{{ $company->name }}</div>
                <div class="muted">
                    {{ $company->address }}<br>
                    {{ $company->city }}, {{ $company->country }}
                </div>
            </td>
            <td width="40%">
                <div class="doc-title">Payslip</div>
                <div class="doc-sub">{{ $payslip->payslip_number }}</div>
            </td>
        </tr>
    </table>

    <table class="meta-table">
        <tr>
            <td width="50%">
                <div class="employee-box">
                    <div class="label-caps">Employee</div>
                    <strong>{{ $payslip->employee->name }}</strong><br>
                    @if($payslip->employee->employee_number) {{ $payslip->employee->employee_number }}<br> @endif
                    @if($payslip->employee->designation) {{ $payslip->employee->designation->name }} @endif
                </div>
            </td>
            <td width="50%">
                <table style="width:100%">
                    <tr><td class="muted">Pay Period</td><td style="text-align:right"><strong>{{ $payslip->payrollRun->period->name }}</strong></td></tr>
                    <tr><td class="muted">Period</td><td style="text-align:right">{{ \Illuminate\Support\Carbon::parse($payslip->payrollRun->period->period_start)->format('M d, Y') }} — {{ \Illuminate\Support\Carbon::parse($payslip->payrollRun->period->period_end)->format('M d, Y') }}</td></tr>
                    <tr><td class="muted">Payment Date</td><td style="text-align:right">{{ \Illuminate\Support\Carbon::parse($payslip->payrollRun->period->payment_date)->format('M d, Y') }}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    <table class="lines">
        <tr><th>Earnings</th><th class="amount">Amount</th></tr>
        @foreach($payslip->payrollRunEmployee->earnings as $earning)
            <tr><td>{{ $earning->label }}</td><td class="amount">{{ $company->currency }} {{ number_format((float) $earning->amount, 2) }}</td></tr>
        @endforeach
    </table>

    <table class="lines">
        <tr><th>Deductions</th><th class="amount">Amount</th></tr>
        @foreach($payslip->payrollRunEmployee->deductions as $deduction)
            <tr><td>{{ $deduction->label }}</td><td class="amount">{{ $company->currency }} {{ number_format((float) $deduction->amount, 2) }}</td></tr>
        @endforeach
    </table>

    <table class="totals">
        <tr><td class="label">Gross Pay</td><td class="value">{{ $company->currency }} {{ number_format((float) $payslip->gross_pay, 2) }}</td></tr>
        <tr><td class="label">Total Deductions</td><td class="value">{{ $company->currency }} {{ number_format((float) $payslip->total_deductions, 2) }}</td></tr>
        <tr class="net-row"><td class="label">Net Pay</td><td class="value">{{ $company->currency }} {{ number_format((float) $payslip->net_pay, 2) }}</td></tr>
    </table>

    <table class="ytd">
        <tr><td colspan="2" class="label-caps">Year-to-Date</td></tr>
        <tr><td class="muted">YTD Gross</td><td style="text-align:right">{{ $company->currency }} {{ number_format((float) $payslip->ytd_gross, 2) }}</td></tr>
        <tr><td class="muted">YTD Deductions</td><td style="text-align:right">{{ $company->currency }} {{ number_format((float) $payslip->ytd_deductions, 2) }}</td></tr>
        <tr><td class="muted">YTD Net</td><td style="text-align:right">{{ $company->currency }} {{ number_format((float) $payslip->ytd_net, 2) }}</td></tr>
    </table>

    <table style="width:100%; margin-top: 24px;">
        <tr>
            <td style="text-align:center;">
                <img src="{{ $qrDataUri }}" style="width:90px; height:90px;"><br>
                <span class="muted" style="font-size:10px;">Scan to verify this payslip</span>
            </td>
        </tr>
    </table>

    <div class="footer">
        {{ $company->name }}
        @if($company->registration_number) &middot; Reg. No. {{ $company->registration_number }} @endif
        @if($company->tax_number) &middot; Tax No. {{ $company->tax_number }} @endif
    </div>
    </div>
</body>
</html>
