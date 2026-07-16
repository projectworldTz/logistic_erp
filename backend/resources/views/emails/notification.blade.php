<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
</head>
<body style="font-family: sans-serif; color: #1f2937; margin: 0; padding: 24px; background: #f9fafb;">
    <table role="presentation" width="100%" style="max-width: 480px; margin: 0 auto; background: #ffffff; border-radius: 8px; overflow: hidden;">
        <tr>
            <td style="padding: 20px 24px; background: {{ $company?->primary_color ?? '#1a56db' }};">
                @if ($company?->logo_url)
                    <img src="{{ $company->logo_url }}" alt="{{ $company->name }}" style="max-height: 40px; display: block;">
                @else
                    <span style="color: #ffffff; font-size: 16px; font-weight: 700;">{{ $company->name ?? config('app.name') }}</span>
                @endif
            </td>
        </tr>
        <tr>
            <td style="padding: 24px;">
                <h1 style="font-size: 18px; margin: 0 0 16px;">{{ $title }}</h1>
                <p style="font-size: 14px; line-height: 1.5; margin: 0; white-space: pre-line;">{{ $body }}</p>
            </td>
        </tr>
        @if ($company?->email_footer_text)
            <tr>
                <td style="padding: 16px 24px; border-top: 1px solid #e5e7eb;">
                    <p style="font-size: 12px; color: #6b7280; line-height: 1.5; margin: 0; white-space: pre-line;">{{ $company->email_footer_text }}</p>
                </td>
            </tr>
        @endif
    </table>
</body>
</html>
