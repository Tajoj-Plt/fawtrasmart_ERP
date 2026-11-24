<?php

namespace Modules\Sales\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\SalesInvoiceSetting;
use App\Services\Settings\SalesSettingsNavigation;
use App\Support\OperationLog;
use Modules\Sales\Http\Requests\SalesInvoiceSettingsRequest;

/**
 * لوحة إعدادات المبيعات وشاشة إعدادات الفواتير.
 *
 * كان هذا المتحكّم يحمل أيضاً نظام القوالب القديم (`Template`): قائمته
 * وتحريره ومعاينته وحذفه و«الطباعة التجريبية» وشاشة `dashbordtest`.
 * أُزيل كلُّه — المعاينة والطباعة كانتا تمرّران محتوى القالب إلى
 * `Blade::render()` بعد تحويل `@php` إلى PHP خام، فمن يملك تعديل الإعدادات
 * كان ينفّذ شيفرةً على الخادم، ولا مسارَ طباعةٍ حقيقيّاً كان يقرأ منه.
 * مستندات المبيعات تُطبع من `DocumentTemplate` («تصاميم مستندات المبيعات»).
 */
class SittingInvoiceController extends Controller
{
    /** مفتاح الوحدة في السجلّات — به يربط السجلّ سطرَه بهذه الشاشة. */
    private const LOG_MODULE = 'SalesInvoiceSettings';

    public function __construct()
    {
        // مُنقولة إلى كتالوج sales.settings.* — نفس نمط InvoicesPurchaseController
        $this->middleware('permission:sales.settings.view', ['only' => ['index', 'invoice']]);
        $this->middleware('permission:sales.settings.update', ['only' => ['update_invoices']]);
    }

    /**
     * لوحة إعدادات المبيعات.
     *
     * العملة واللغة كانتا تُمرَّران من هنا بطاقتَي إحصاء. صارتا — ومعهما
     * المظهر وطرق الدفع والحسابات المحاسبية والفترة ومعلومات المنشأة
     * والإشعارات — في شريط `sales::components.system-context` الذي يقرأ
     * من `SalesSettingsContext`، فكل قيمة رابطٌ إلى شاشة ضبطها بدل رقمٍ
     * يُعرَض ولا يُفعَل به شيء.
     */
    public function index()
    {
        return view('sales::sitting.index', [
            'groups' => SalesSettingsNavigation::visible(),
            'screenCount' => SalesSettingsNavigation::count(),
        ]);
    }

    /**
     * شاشة إعدادات الفواتير وعروض الأسعار.
     *
     * كانت تُعرَض بلا `$settings`، فكلّ `isset($settings[$key])` في القالب
     * تعود false مهما حُفِظ — تظهر الشاشة أبداً على القيم الافتراضية.
     */
    public function invoice()
    {
        return view('sales::sitting.invoice', [
            'settings' => SalesInvoiceSetting::current(),
        ]);
    }

    /**
     * حفظ إعدادات الفواتير.
     *
     * كان المسار يشير إلى هذه الدالّة وهي غير موجودة، والنموذج يُرسِل POST
     * إلى مسار PUT — فالزرّ كان يعطي 405 قبل أن يبلغ 500.
     *
     * ثلاث إضافات على النسخة التي أصلحت ذلك:
     *
     *   • **الفرق قبل الحفظ** — بعده تصير القيمة القديمة هي الجديدة، فلا
     *     يبقى ما يُسجَّل. اثنان وعشرون إعداداً تُبدَّل من شاشة واحدة، ومن
     *     غير الفرق يقول السجلّ «عُدّلت الإعدادات» ولا يقول أيُّها.
     *
     *   • **السجلّات الثلاثة** — لم يكن تغيير إعداد يترك أثراً في «سجلّ
     *     النشاطات» ولا «سجلّ الأحداث»، ولا يُكتب شيء في «سجلّ الأخطاء»
     *     حين يفشل الحفظ. `OperationLog` تكتب في الثلاثة بنداءٍ واحد.
     *
     *   • **الفشل مردود لا مرميّ** — `save()` على جدول ينقصه عمود يرمي
     *     استثناءً يبلغ المستخدمَ صفحةَ خطأ بيضاء بدل رسالة يفهمها.
     */
    public function update_invoices(SalesInvoiceSettingsRequest $request)
    {
        $settings = SalesInvoiceSetting::current();

        try {
            $settings->fill($request->settings());
            $settings->updated_by = auth()->id();

            $changes = OperationLog::diff($settings, [
                'max_discount_type',
                'warehouse_source',
                ...SalesInvoiceSetting::NUMBERS,
                ...SalesInvoiceSetting::FLAGS,
            ]);

            $settings->save();
        } catch (\Throwable $e) {
            OperationLog::failure($e, self::LOG_MODULE, 'update_invoices', $settings);

            return back()->withInput()->with('error', __('sales_settings.invoice.save_failed'));
        }

        OperationLog::record(
            'updated',
            self::LOG_MODULE,
            $settings,
            __('sales_settings.invoice.log_updated'),
            $changes
        );

        return redirect()->route('SittingInvoice.invoice')
            ->with('success', __('sales_settings.invoice.saved'));
    }

    // شاشات الفاتورة الإلكترونية انتقلت إلى ElectronicInvoiceController مع خلفيتها
}
