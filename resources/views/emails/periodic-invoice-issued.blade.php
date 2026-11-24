{{--
    رسالة صدور فاتورة من اشتراك دوري.

    كل ما يظهر فيها من إعدادات النظام لا مكتوباً هنا:
      • الاتجاه واللغة  ← اللغة النشطة (`LanguageSettings`)
      • المبالغ والعملة ← «العملات» (`CurrencySettings`)
      • التاريخ         ← صيغة اللغة النشطة
      • اسم المنشأة     ← «معلومات المنشأة» (`BusinessInfo`)

    بلا CSS خارجيّ ولا خطوط بعيدة: عملاء البريد يحجبونها، والتنسيق
    المضمَّن هو ما يصل.
--}}
@php
    use App\Support\CurrencySettings;
    use App\Support\LanguageSettings;

    $rtl = LanguageSettings::isRtl();
    $align = $rtl ? 'right' : 'left';
    $clientName = $subscription->client
        ? ($subscription->client->trade_name
            ?: trim(($subscription->client->first_name ?? '').' '.($subscription->client->last_name ?? '')))
        : '';
@endphp
<!doctype html>
<html dir="{{ $rtl ? 'rtl' : 'ltr' }}" lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('sales_periodic.mail.subject', ['code' => $invoice->code, 'business' => $businessName]) }}</title>
</head>
<body style="margin:0;padding:24px;background:#f4f5f7;font-family:Tahoma,Arial,sans-serif;color:#333;direction:{{ $rtl ? 'rtl' : 'ltr' }};text-align:{{ $align }}">

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:620px;margin:0 auto;background:#fff;border-radius:8px;overflow:hidden;border:1px solid #e4e6eb">
    <tr>
        <td style="background:#7367f0;padding:20px 24px;color:#fff">
            <div style="font-size:18px;font-weight:bold">{{ $businessName }}</div>
            <div style="font-size:13px;opacity:.9;margin-top:4px">{{ __('sales_periodic.mail.header') }}</div>
        </td>
    </tr>

    <tr>
        <td style="padding:24px">
            <p style="margin:0 0 16px;font-size:15px">
                {{ __('sales_periodic.mail.greeting', ['client' => $clientName]) }}
            </p>

            <p style="margin:0 0 20px;font-size:14px;line-height:1.9;color:#555">
                {{ __('sales_periodic.mail.body', [
                    'number' => $instanceNumber,
                    'total' => $subscription->repeat_count,
                    'subscription' => $subscription->invoice_number,
                ]) }}
            </p>

            <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
                   style="border-collapse:collapse;font-size:14px;margin-bottom:20px">
                <tr>
                    <td style="padding:10px 12px;background:#f8f8fb;border:1px solid #e4e6eb;width:45%">
                        {{ __('sales_periodic.mail.invoice_code') }}
                    </td>
                    <td style="padding:10px 12px;border:1px solid #e4e6eb;font-weight:bold">
                        {{ $invoice->code }}
                    </td>
                </tr>
                <tr>
                    <td style="padding:10px 12px;background:#f8f8fb;border:1px solid #e4e6eb">
                        {{ __('sales_periodic.mail.invoice_date') }}
                    </td>
                    <td style="padding:10px 12px;border:1px solid #e4e6eb">
                        {{ LanguageSettings::formatDate($invoice->invoice_date ?: $invoice->created_at) }}
                    </td>
                </tr>
                <tr>
                    <td style="padding:10px 12px;background:#f8f8fb;border:1px solid #e4e6eb">
                        {{ __('sales_periodic.mail.grand_total') }}
                    </td>
                    <td style="padding:10px 12px;border:1px solid #e4e6eb;font-weight:bold">
                        {{ CurrencySettings::format($invoice->grand_total ?? 0) }}
                    </td>
                </tr>
            </table>

            <p style="margin:0 0 24px;text-align:center">
                <a href="{{ $viewUrl }}"
                   style="display:inline-block;padding:12px 28px;background:#7367f0;color:#fff;text-decoration:none;border-radius:6px;font-size:14px;font-weight:bold">
                    {{ __('sales_periodic.mail.view_invoice') }}
                </a>
            </p>

            <p style="margin:0;font-size:12px;color:#888;line-height:1.8">
                {{ __('sales_periodic.mail.footer_note') }}
            </p>
        </td>
    </tr>

    <tr>
        <td style="padding:14px 24px;background:#fafafb;border-top:1px solid #e4e6eb;font-size:12px;color:#888">
            {{ __('sales_periodic.mail.signature', ['business' => $businessName]) }}
        </td>
    </tr>
</table>

</body>
</html>
