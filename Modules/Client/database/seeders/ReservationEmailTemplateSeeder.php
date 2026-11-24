<?php

namespace Modules\Client\Database\Seeders;

use App\Services\Sales\SalesDocumentMailer;
use Carbon\Carbon;
use Database\Seeders\Concerns\SeedsMissingRows;
use Illuminate\Database\Seeder;

/**
 * قالبُ بريد «تأكيد الحجز» — نصُّ الرسالة التي يرسلها زرُّ «إرسال بالبريد»
 * في شاشة الحجز.
 *
 * الإرسالُ نفسُه `SalesDocumentMailer` برمز `TEMPLATE_CODES['reservation']`،
 * وبلا هذا الصفّ يرفض الإرسالَ ويقول إنّ القالب غير مفعّل. ويُزرع الناقصُ
 * وحده: نصٌّ حرّره صاحبُ المنشأة من «قوالب البريد» لا يُعاد كتابتُه.
 *
 * متغيّراتُه غيرُ متغيّرات مستندات المبيعات: الموعدُ والخدمةُ والموظّف هي ما
 * يهمّ العميلَ في حجزه، لا رابطٌ إلى شاشةٍ داخلية لا يدخلها.
 */
class ReservationEmailTemplateSeeder extends Seeder
{
    use SeedsMissingRows;

    public function run(): void
    {
        $now = Carbon::now();

        $seeded = $this->seedMissingRows('email_templates', [[
            'template_code' => SalesDocumentMailer::TEMPLATE_CODES['reservation'],
            'template_name' => 'إرسال تأكيد حجز',
            'template_category' => 'payments',
            'subject' => 'تأكيد حجزك رقم {{ number }} — {{ business }}',
            'html_content' => $this->html(),
            'text_content' => "مرحباً {{ client }}\nهذه تفاصيل حجزك رقم {{ number }}\nالخدمة: {{ service }}\nالتاريخ: {{ date }}\nالوقت: {{ time }}\nمع: {{ employee }}\nالإجمالي: {{ total }}\n{{ note }}\n{{ business }}",
            'variables' => json_encode(['client', 'number', 'service', 'date', 'time', 'employee', 'total', 'note', 'attachment_note', 'business']),
            'language' => 'ar',
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ], [
            // موعدُ العميل حين يُعلَّم «مشاركة مع العميل» — `AppointmentClientShare`
            'template_code' => SalesDocumentMailer::TEMPLATE_CODES['appointment'],
            'template_name' => 'مشاركة موعد مع العميل',
            'template_category' => 'payments',
            'subject' => '{{ title }} — {{ date }} {{ time }} — {{ business }}',
            'html_content' => $this->appointmentHtml(),
            'text_content' => "مرحباً {{ client }}\n{{ title }}\nالتاريخ: {{ date }}\nالوقت: {{ time }}\nالمدّة: {{ duration }}\nالإجراء: {{ action }}\nمع: {{ employee }}\n{{ note }}\n{{ business }}",
            'variables' => json_encode(['client', 'title', 'number', 'date', 'time', 'duration', 'action', 'employee', 'note', 'business']),
            'language' => 'ar',
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]], 'template_code', false);

        $this->reportSeeded($seeded, 'قالبا بريد الحجز والموعد');
    }

    private function appointmentHtml(): string
    {
        $row = fn (string $label, string $var) => '<tr><td style="padding:8px 16px;color:#6b7785;width:35%;">'.$label.'</td>'
            .'<td style="padding:8px 16px;color:#243142;font-weight:bold;">{{ '.$var.' }}</td></tr>';

        return '<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>{{ business }}</title></head>
<body style="margin:0;padding:0;background:#f4f5f7;font-family:Tahoma,Arial,sans-serif;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f5f7;padding:24px 12px;">
    <tr><td align="center">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background:#ffffff;border-radius:10px;overflow:hidden;">
        <tr><td style="background:#1f3a5f;color:#ffffff;padding:20px 24px;font-size:18px;font-weight:bold;">{{ business }}</td></tr>
        <tr><td style="padding:24px;color:#243142;font-size:14px;line-height:1.9;">
          <p style="margin:0 0 12px;">مرحباً {{ client }}،</p>
          <p style="margin:0 0 16px;"><strong>{{ title }}</strong></p>
          <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f7f9fc;border-radius:8px;margin:0 0 16px;font-size:13px;">
            '.$row('التاريخ', 'date').'
            '.$row('الوقت', 'time').'
            '.$row('المدّة', 'duration').'
            '.$row('الإجراء', 'action').'
            '.$row('مع', 'employee').'
          </table>
          <p style="margin:0 0 16px;">{{ note }}</p>
          <p style="margin:0;">نتطلّع لرؤيتك.</p>
        </td></tr>
        <tr><td style="background:#f7f9fc;padding:16px 24px;color:#7b8794;font-size:12px;">{{ business }}</td></tr>
      </table>
    </td></tr>
  </table>
</body>
</html>';
    }

    private function html(): string
    {
        $row = fn (string $label, string $var) => '<tr><td style="padding:8px 16px;color:#6b7785;width:35%;">'.$label.'</td>'
            .'<td style="padding:8px 16px;color:#243142;font-weight:bold;">{{ '.$var.' }}</td></tr>';

        return '<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>{{ business }}</title></head>
<body style="margin:0;padding:0;background:#f4f5f7;font-family:Tahoma,Arial,sans-serif;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f5f7;padding:24px 12px;">
    <tr><td align="center">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background:#ffffff;border-radius:10px;overflow:hidden;">
        <tr><td style="background:#1f3a5f;color:#ffffff;padding:20px 24px;font-size:18px;font-weight:bold;">{{ business }}</td></tr>
        <tr><td style="padding:24px;color:#243142;font-size:14px;line-height:1.9;">
          <p style="margin:0 0 12px;">مرحباً {{ client }}،</p>
          <p style="margin:0 0 16px;">هذه تفاصيل حجزك رقم <strong>{{ number }}</strong>:</p>
          <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f7f9fc;border-radius:8px;margin:0 0 16px;font-size:13px;">
            '.$row('الخدمة', 'service').'
            '.$row('التاريخ', 'date').'
            '.$row('الوقت', 'time').'
            '.$row('مع', 'employee').'
            '.$row('الإجمالي', 'total').'
          </table>
          <p style="margin:0 0 16px;">{{ note }}</p>
          <p style="margin:0 0 8px;">نتطلّع لرؤيتك.</p>
          <p style="margin:0;color:#7b8794;font-size:12px;">{{ attachment_note }}</p>
        </td></tr>
        <tr><td style="background:#f7f9fc;padding:16px 24px;color:#7b8794;font-size:12px;">{{ business }}</td></tr>
      </table>
    </td></tr>
  </table>
</body>
</html>';
    }
}
