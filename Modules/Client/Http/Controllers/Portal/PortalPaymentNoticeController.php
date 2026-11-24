<?php

namespace Modules\Client\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Services\Clients\ClientPortalNotifier;
use App\Models\ClientPaymentNotice;
use App\Models\Invoice;
use App\Models\PaymentMethod;
use App\Models\notifications;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Modules\Client\Http\Controllers\Concerns\GuardsClientPortal;
use Throwable;

/**
 * إشعارُ سدادٍ يرسله العميل من بوابته عن فاتورةٍ آجلة.
 *
 * ═══════════ لماذا «إشعار» لا «دفع»؟ ═══════════
 *
 * لا بوابةَ دفعٍ إلكترونيةٍ مربوطةً بالنظام (طلب المستخدم 2026-09-18:
 * «سوّها من دون بوابة دفع، يكون الربط فيما بعد»). فالعميلُ يُعلن أنه حوّل
 * أو سدّد شبكةً ويرفق إيصاله، والموظّفُ يعتمد فيُنشأ سندُ القبض المعتاد
 * بمسار المبيعات — `ClientPaymentNoticeController`.
 *
 * **لا يمسّ هذا المتحكّمُ مالاً**: لا خزينةَ ولا قيدَ ولا `due_value`. وهذا
 * مقصود — المالُ يدخل النظامَ من بابٍ واحدٍ يعرفه المحاسب، لا من قول
 * العميل. ويومَ تُربط بوابةُ الدفع يصير الإشعارُ المدفوعُ بالبطاقة معتمداً
 * آلياً بمرجع البوابة (`gateway_reference`) بلا تغييرٍ في بقيّة السلسلة.
 */
class PortalPaymentNoticeController extends Controller
{
    use GuardsClientPortal;

    /** مفتاحُ «صلاحيات بوابة العميل» الذي يفتح إرسال الإشعار */
    private const PERMISSION = 'submit_payment_notice';

    /** مجلّدُ الإيصالات داخل `public` — كمجلّدات المرفقات الأخرى */
    private const UPLOAD_DIR = 'assets/uploads/payment_notices';

    public function store(Request $request)
    {
        [$deny, $clientId] = $this->guardPortal(self::PERMISSION);

        if ($deny) {
            return $deny;
        }

        $data = $request->validate([
            'invoice_id' => ['required', 'integer'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            // الطريقةُ من «طرق الدفع» المفعَّلة — لا من قائمةٍ في الشيفرة
            'payment_method_id' => ['required', 'integer'],
            'paid_at' => ['nullable', 'date', 'before_or_equal:today'],
            'reference' => ['nullable', 'string', 'max:120'],
            'note' => ['nullable', 'string', 'max:500'],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:4096'],
        ], [], [
            'invoice_id' => 'الفاتورة',
            'amount' => 'المبلغ',
            'payment_method_id' => 'طريقة السداد',
            'paid_at' => 'تاريخ السداد',
            'attachment' => 'الإيصال',
        ]);

        /* `manual()` لا `is_active`: ما يُعرض للعميل هو ما يقبله سندُ
           القبض، فلا يختار طريقةً يعجز الموظّف عن تسجيلها. */
        $method = PaymentMethod::query()
            ->manual()
            ->where('id', $data['payment_method_id'])
            ->first();

        if (! $method) {
            return back()->withInput()->with('error', 'طريقة السداد غير متاحة.');
        }

        /* حدودُ الطريقة من إعداداتها: طريقةٌ لها حدٌّ أدنى أو أقصى تُرفَض هنا
           قبل أن يكتشفها الموظّف عند تسجيل السند. */
        if ($method->min_amount && (float) $data['amount'] < (float) $method->min_amount) {
            return back()->withInput()->with('error',
                'أقلّ مبلغ لـ«'.$method->name.'» هو '.number_format((float) $method->min_amount, 2).'.');
        }

        if ($method->max_amount && (float) $data['amount'] > (float) $method->max_amount) {
            return back()->withInput()->with('error',
                'أعلى مبلغ لـ«'.$method->name.'» هو '.number_format((float) $method->max_amount, 2).'.');
        }

        /* الفاتورةُ تُقرأ بشرط العميل لا بمعرّفها وحده: `exists:invoices,id`
           تقبل فاتورةَ عميلٍ آخر، فيصير حقلٌ مخفيٌّ في النموذج بابَ اطّلاعٍ
           على أرقام غيره. */
        $invoice = Invoice::where('id', $data['invoice_id'])
            ->where('client_id', $clientId)
            ->first();

        if (! $invoice) {
            return back()->with('error', 'الفاتورة غير موجودة في حسابك.');
        }

        $due = (float) $invoice->due_value;

        if ($due <= 0) {
            return back()->with('error', 'هذه الفاتورة مسدَّدة بالكامل.');
        }

        /* الإشعاراتُ المعلّقة تُحسب ضمن المستحقّ: عميلٌ يرسل إشعارين بكامل
           المبلغ يُنتج سندين يتجاوزان الفاتورة حين يُعتمدان معاً. */
        $pending = (float) ClientPaymentNotice::where('invoice_id', $invoice->id)
            ->where('status', ClientPaymentNotice::STATUS_PENDING)
            ->sum('amount');

        if ((float) $data['amount'] > round($due - $pending, 2)) {
            return back()->withInput()->with('error', $pending > 0
                ? 'لديك إشعارُ سدادٍ قيد المراجعة على هذه الفاتورة؛ المتبقّي القابل للإشعار '.number_format($due - $pending, 2)
                : 'المبلغ أكبر من المتبقّي على الفاتورة ('.number_format($due, 2).').');
        }

        $path = null;

        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $name = time().'_'.bin2hex(random_bytes(6)).'.'.$file->getClientOriginalExtension();
            $file->move(public_path(self::UPLOAD_DIR), $name);
            $path = self::UPLOAD_DIR.'/'.$name;
        }

        $notice = ClientPaymentNotice::create([
            'client_id' => $clientId,
            'user_id' => auth()->id(),
            'invoice_id' => $invoice->id,
            'amount' => $data['amount'],
            'payment_method_id' => $method->id,
            'paid_at' => $data['paid_at'] ?? null,
            'reference' => $data['reference'] ?? null,
            'attachment' => $path,
            'note' => $data['note'] ?? null,
            'status' => ClientPaymentNotice::STATUS_PENDING,
        ]);

        $this->notifyStaff($notice, $invoice);

        return back()->with('success', 'تم استلام إشعار السداد. سيراجعه موظّفونا ويُسجَّل سندُ قبضٍ بعد التحقّق.');
    }

    /** يسحب العميلُ إشعاره ما دام لم يُراجَع. */
    public function cancel(Request $request, ClientPaymentNotice $notice)
    {
        [$deny, $clientId] = $this->guardPortal(self::PERMISSION);

        if ($deny) {
            return $deny;
        }

        if ($notice->client_id !== $clientId) {
            abort(404);
        }

        if (! $notice->isPending()) {
            return back()->with('error', 'لا يمكن سحب إشعارٍ تمّت مراجعته.');
        }

        $notice->update(['status' => ClientPaymentNotice::STATUS_CANCELLED]);

        return back()->with('success', 'تم سحب إشعار السداد.');
    }

    /**
     * إشعارُ **موظّف العميل المسند** (طلب المستخدم 2026-09-19) — وإن لم
     * يكن له حسابُ دخول بقيت الرؤيةُ بصلاحيات قائمة الإشعارات.
     */
    private function notifyStaff(ClientPaymentNotice $notice, Invoice $invoice): void
    {
        ClientPortalNotifier::notify(
            $notice->client ?? $notice->client_id,
            'client_payment_notice',
            'إشعار سداد من بوابة العميل',
            'أعلن العميل '.($notice->client?->trade_name ?? '#'.$notice->client_id)
                .' سدادَ '.number_format((float) $notice->amount, 2).' على الفاتورة '
                .($invoice->code ?? '#'.$invoice->id).' ('.$notice->methodLabel().') — بانتظار الاعتماد.',
        );
    }
}
