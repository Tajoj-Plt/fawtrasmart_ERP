<?php

namespace App\Mail;

use App\Models\Invoice;
use App\Models\PeriodicInvoice;
use App\Support\BusinessInfo;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * إشعار العميل بصدور فاتورة من اشتراكه الدوري.
 *
 * الاشتراك يُصدر فواتيره بلا مستخدمٍ حاضر — من `cron:run-due` — فالعميل
 * لا يعلم بصدورها إلّا إن دخل الشاشة. وخيار «إرسال نسخة للعميل»
 * (`send_copy`) موجودٌ في نموذج الاشتراك منذ أوّل نسخة ولا يقرؤه شيء:
 * يُحفَظ في العمود ولا يُرسَل بريدٌ أبداً.
 *
 * الرسالة تحمل رابط الفاتورة المطبوعة لا مرفقاً: بناء PDF لكل فاتورة في
 * مهمّةٍ مجدولة يضاعف زمنها، والرابط يُظهر آخر حالة للمستند لا صورةً
 * جُمِّدت لحظة الإرسال.
 *
 * اللغة والعملة من إعدادات النظام عبر القالب، وبيانات المنشأة من
 * «معلومات المنشأة» لا مكتوبةً في الرسالة.
 */
class PeriodicInvoiceIssuedMail extends Mailable
{
    use Queueable, SerializesModels;

    public PeriodicInvoice $subscription;

    public Invoice $invoice;

    public string $viewUrl;

    public int $instanceNumber;

    public string $businessName;

    public function __construct(
        PeriodicInvoice $subscription,
        Invoice $invoice,
        string $viewUrl,
        int $instanceNumber
    ) {
        $this->subscription = $subscription;
        $this->invoice = $invoice;
        $this->viewUrl = $viewUrl;
        $this->instanceNumber = $instanceNumber;
        $this->businessName = BusinessInfo::name();
    }

    public function build()
    {
        return $this
            ->subject(__('sales_periodic.mail.subject', [
                'code' => $this->invoice->code,
                'business' => $this->businessName,
            ]))
            ->view('emails.periodic-invoice-issued');
    }
}
