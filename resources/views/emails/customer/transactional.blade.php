@php
    /** @var array $brand */
    $businessName = $brand['business_name'] ?? 'The88Coffees';
    $slogan = $brand['slogan'] ?? null;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>{{ $subject ?? $businessName }}</title>
</head>
<body style="margin:0;padding:0;background-color:#f3ebe3;font-family:Georgia,'Times New Roman',serif;color:#2c2118;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color:#f3ebe3;padding:24px 12px;">
    <tr>
        <td align="center">
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:560px;background-color:#fffaf5;border:1px solid #e4d4c4;border-radius:12px;overflow:hidden;">
                <tr>
                    <td style="background-color:#3d2918;color:#f7efe6;padding:22px 24px;">
                        <div style="font-size:22px;font-weight:700;letter-spacing:0.02em;">{{ $businessName }}</div>
                        @if ($slogan)
                            <div style="margin-top:6px;font-size:13px;color:#d9c4ad;font-family:Arial,Helvetica,sans-serif;">{{ $slogan }}</div>
                        @endif
                    </td>
                </tr>
                <tr>
                    <td style="padding:28px 24px 8px;font-family:Arial,Helvetica,sans-serif;font-size:15px;line-height:1.55;color:#2c2118;">
                        @if (! empty($greeting))
                            <p style="margin:0 0 16px;font-size:16px;">{{ $greeting }}</p>
                        @endif

                        @if (! empty($statusLabel))
                            @php
                                $tone = $statusTone ?? 'neutral';
                                $bg = match ($tone) {
                                    'success' => '#e7f6ee',
                                    'warning' => '#fff4df',
                                    'danger' => '#fdecea',
                                    default => '#f1e7dc',
                                };
                                $fg = match ($tone) {
                                    'success' => '#0f5c38',
                                    'warning' => '#8a5a12',
                                    'danger' => '#8a1f1f',
                                    default => '#4a3728',
                                };
                            @endphp
                            <div style="margin:0 0 18px;padding:12px 14px;border-radius:8px;background:{{ $bg }};color:{{ $fg }};font-weight:700;">
                                {{ $statusLabel }}
                            </div>
                        @endif

                        @foreach (($introLines ?? []) as $line)
                            <p style="margin:0 0 12px;">{{ $line }}</p>
                        @endforeach

                        @isset($order)
                            @include('emails.customer.partials.order-summary', ['order' => $order])
                        @endisset

                        @if (! empty($actionText) && ! empty($actionUrl))
                            <table role="presentation" cellspacing="0" cellpadding="0" style="margin:22px 0;">
                                <tr>
                                    <td style="border-radius:999px;background-color:#7c5a3b;">
                                        <a href="{{ $actionUrl }}" style="display:inline-block;padding:12px 22px;color:#fffaf5;text-decoration:none;font-weight:700;font-size:14px;">
                                            {{ $actionText }}
                                        </a>
                                    </td>
                                </tr>
                            </table>
                            <p style="margin:0 0 12px;font-size:12px;color:#6b5646;word-break:break-all;">
                                Or open: {{ $actionUrl }}
                            </p>
                        @endif

                        @foreach (($outroLines ?? []) as $line)
                            <p style="margin:0 0 12px;">{{ $line }}</p>
                        @endforeach
                    </td>
                </tr>
                <tr>
                    <td style="padding:18px 24px 24px;border-top:1px solid #eadfce;font-family:Arial,Helvetica,sans-serif;font-size:12px;line-height:1.5;color:#6b5646;">
                        @include('emails.customer.partials.footer', ['brand' => $brand])
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
