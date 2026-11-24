<?php

namespace Database\Seeders;

use App\Services\Sales\SalesDocumentMailer;
use Carbon\Carbon;
use Database\Seeders\Concerns\SeedsMissingRows;
use Illuminate\Database\Seeder;

/**
 * قوالبُ البريد لمستندات المبيعات الستّة.
 *
 * `EmailTemplateSeeder` يزرع ٥١ قالباً كلُّها للمتجر الإلكتروني — تسجيلُ
 * دخولٍ وسلّةٌ متروكةٌ وكوبون — ولا واحدَ منها لمستندٍ محاسبيّ. فزرُّ
 * «إرسال بالبريد» في شاشة الفاتورة أو العرض لم يكن له نصٌّ يرسله.
 *
 * الرموزُ هي `SalesDocumentMailer::TEMPLATE_CODES` نفسُها، ومن غيرها
 * ترفض الخدمةُ الإرسال وتقول «القالب غير مفعّل» — فلا تُرسَل رسالةٌ
 * باسم المنشأة لم يكتبها أحد.
 *
 * والصفوفُ تُزرع بـ`seedMissingRows`: ما كُتب منها مرّةً لا يُعاد كتابتُه،
 * فنصُّ القالب الذي حرّره صاحبُ المنشأة يبقى له.
 */
class SalesDocumentEmailTemplateSeeder extends Seeder
{
    use SeedsMissingRows;

    public function run(): void
    {
        $now = Carbon::now();

        $documents = [
            'quote' => [
                'name' => 'إرسال عرض سعر',
                'subject' => 'عرض سعر رقم {{ number }} من {{ business }}',
                'lead' => 'يسرّنا أن نرفق لك عرض السعر رقم <strong>{{ number }}</strong>.',
                'cta' => 'عرض المستند',
            ],
            'sales_order' => [
                'name' => 'إرسال أمر بيع',
                'subject' => 'أمر بيع رقم {{ number }} من {{ business }}',
                'lead' => 'نرفق لك أمر البيع رقم <strong>{{ number }}</strong>.',
                'cta' => 'عرض المستند',
            ],
            'sales_invoice' => [
                'name' => 'إرسال فاتورة مبيعات',
                'subject' => 'فاتورة رقم {{ number }} من {{ business }}',
                'lead' => 'نرفق لك الفاتورة رقم <strong>{{ number }}</strong>.',
                'cta' => 'عرض الفاتورة',
            ],
            'sales_return' => [
                'name' => 'إرسال مرتجع مبيعات',
                'subject' => 'مرتجع رقم {{ number }} من {{ business }}',
                'lead' => 'نرفق لك مستند المرتجع رقم <strong>{{ number }}</strong>.',
                'cta' => 'عرض المستند',
            ],
            'credit_note' => [
                'name' => 'إرسال إشعار دائن',
                'subject' => 'إشعار دائن رقم {{ number }} من {{ business }}',
                'lead' => 'نرفق لك الإشعار الدائن رقم <strong>{{ number }}</strong>.',
                'cta' => 'عرض الإشعار',
            ],
            'receipt_voucher' => [
                'name' => 'إرسال سند قبض',
                'subject' => 'سند قبض رقم {{ number }} من {{ business }}',
                'lead' => 'نرفق لك سند القبض رقم <strong>{{ number }}</strong>، وشكراً لسدادك.',
                'cta' => 'عرض السند',
            ],
        ];

        $templates = [];

        foreach ($documents as $type => $document) {
            $code = SalesDocumentMailer::TEMPLATE_CODES[$type] ?? null;

            if ($code === null) {
                continue;
            }

            $templates[] = [
                'template_code' => $code,
                'template_name' => $document['name'],
                // `payments` وسمُها في الشاشة «المدفوعات والفواتير»
                'template_category' => 'payments',
                'subject' => $document['subject'],
                'html_content' => $this->wrap($document['lead'], $document['cta']),
                'text_content' => "مرحباً {{ client }}\n".strip_tags($document['lead'])."\nالتاريخ: {{ date }}\nالإجمالي: {{ total }}\n{{ note }}\n{{ link }}\n{{ business }}",
                'variables' => json_encode(['client', 'number', 'date', 'total', 'link', 'note', 'attachment_note', 'business']),
                'language' => 'ar',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $seeded = $this->seedMissingRows('email_templates', $templates, 'template_code', false);

        $this->reportSeeded($seeded, 'قوالب بريد مستندات المبيعات');
    }

    /**
     * غلافُ الرسالة.
     *
     * `{{ note }}` سطرُ المرسِل الحرّ، و`{{ attachment_note }}` سطرُ
     * «نسخةٌ مرفقة» — وكلاهما يُترك فارغاً فلا يظهر شيء، وهو السبب في أن
     * حول كلٍّ منهما فقرةً مستقلّة لا جملةً داخل نصّ. والثاني بالذات لا
     * يُكتب حرفياً في القالب: رسالةٌ خرجت بلا PDF (لا قالبَ مصمَّم ولا
     * مدمجٌ يُصيَّر) تَعِد بمرفقٍ ليس فيها.
     */
    private function wrap(string $lead, string $cta): string
    {
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
          <p style="margin:0 0 16px;">'.$lead.'</p>
          <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f7f9fc;border-radius:8px;margin:0 0 16px;">
            <tr><td style="padding:14px 16px;font-size:13px;color:#4a5a6a;">
              <div>الرقم: <strong>{{ number }}</strong></div>
              <div>التاريخ: <strong>{{ date }}</strong></div>
              <div>الإجمالي: <strong>{{ total }}</strong></div>
            </td></tr>
          </table>
          <p style="margin:0 0 16px;">{{ note }}</p>
          <div style="text-align:center;margin:24px 0;">
            <a href="{{ link }}" style="display:inline-block;background:#1f3a5f;color:#ffffff;padding:12px 32px;text-decoration:none;border-radius:6px;font-weight:600;font-size:14px;">'.$cta.'</a>
          </div>
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
