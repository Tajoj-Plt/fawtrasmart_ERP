<?php

namespace Modules\Sales\Http\Controllers;

use App\Constants\planFeatures\SalesPlanFeatures;;
use App\Http\Controllers\Controller;
use App\Models\ExchangeReason;
use App\Support\OperationLog;
use Illuminate\Http\Request;
use Modules\Sales\Http\Requests\ExchangeReasonRequest;
use Throwable;

/**
 * أسباب استبدال المنتجات — تُعرَض في شاشة الاستبدال ويُبنى عليها تقريره.
 *
 * ثلاث وصلات بإعدادات النظام لم تكن هنا:
 *
 *   • **السجلّات الثلاثة** — لم يكن إضافةُ سببٍ ولا تعديله ولا حذفه ولا
 *     تبديل حالته يترك أثراً في «سجلّ النشاطات» ولا «سجلّ الأحداث»، ولا
 *     يُكتب شيء في «سجلّ الأخطاء» حين يفشل الحفظ. السبب يُستعمَل في
 *     تبريرِ مرتجعٍ يمسّ المخزون والقيود، فحذفُه بلا أثر يعني تقريراً
 *     يتغيّر ولا يُعرف مَن غيّره.
 *
 *   • **عدد السجلّات** — كان `paginate(20)` ثابتاً يتجاهل `per_page`.
 *     القائمة مغلقة عمداً: البند 6.3 في تقرير التحسين يرصد `per_page`
 *     غير المتحقَّق منه كثغرة إنهاك.
 *
 *   • **الفشل مردود لا مرميّ** — سببٌ مرتبطٌ بعمليات استبدال يرفض الحذفَ
 *     بقيد مفتاح أجنبي. كان الاستثناء يبلغ المستخدم صفحةَ خطأ بيضاء بدل
 *     رسالة تقول إنّ السبب مستعمَل.
 */
class ExchangeReasonController extends Controller
{
    use \Modules\Sales\Http\Controllers\Concerns\ChecksSubscriptionLimits;

    /** مفتاح الوحدة في السجلّات — به يربط السجلّ سطرَه بهذه الشاشة. */
    private const LOG_MODULE = 'SalesExchangeReasons';

    /** الأحجام المسموحة لعدد السجلّات في الصفحة. */
    private const PAGE_SIZES = [10, 25, 50, 100];

    /** الحقول التي يُلتقط فرقُها للسجلّ — دون طوابع الوقت. */
    private const TRACKED = ['name', 'description', 'sort_order', 'is_active'];

    public function __construct()
    {
        // إعدادٌ إداري بصلاحية واحدة — نفس نمط InvoicesPurchaseController
        $this->middleware('permission:sales.exchange_reasons.manage');
    }

    public function index(Request $request)
    {
        $query = ExchangeReason::query();

        if ($request->filled('search')) {
            // الوصف كذلك: البحث باسمٍ وحده يُخفي سبباً كُتِب شرحُه ولم
            // يُسمَّ بما يبحث عنه المستخدم
            $query->where(function ($q) use ($request) {
                $term = '%'.$request->input('search').'%';
                $q->where('name', 'like', $term)->orWhere('description', 'like', $term);
            });
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->input('status') === 'active');
        }

        // `appends` تُبقي التصفية عبر الصفحات، وبدونها يعود الانتقال إلى
        // الصفحة الثانية بنتائج غير مصفّاة
        $reasons = $query->ordered()
            ->paginate($this->perPage($request))
            ->appends($request->query());

        // الإحصاء على الجدول كلّه لا على نتيجة التصفية: «الإجمالي» الذي
        // يتغيّر مع كل بحث ليس إجمالاً
        $all = ExchangeReason::query()->get(['is_active']);

        return view('sales::exchange_reasons.index', [
            'reasons' => $reasons,
            'pageSizes' => self::PAGE_SIZES,
            // سقفٌ متزامن: يعدّ الأسباب **النشطة** الآن، فتعطيلُ سببٍ قديم
            // يفرّغ مكاناً لجديد بلا حاجةٍ إلى ترقية الباقة
            'subscriptionLimit' => $this->subscriptionLimit(SalesPlanFeatures::EXCHANGE_REASONS),
            'stats' => [
                'total' => $all->count(),
                'active' => $all->where('is_active', true)->count(),
            ],
        ]);
    }

    public function create()
    {
        return view('sales::exchange_reasons.form', [
            'reason' => new ExchangeReason(['is_active' => true]),
        ]);
    }

    public function store(ExchangeReasonRequest $request)
    {
        // ** سقف اشتراك فوترة ** — بعد التحقّق من المُدخلات وقبل الكتابة
        if ($blocked = $this->subscriptionBlock(SalesPlanFeatures::EXCHANGE_REASONS)) {
            return back()->withInput()->with('error', $blocked);
        }

        try {
            $reason = ExchangeReason::create($request->validated());
        } catch (Throwable $e) {
            OperationLog::failure($e, self::LOG_MODULE, 'store');

            return back()->withInput()->with('error', __('sales_settings.exchange_reasons.save_failed'));
        }

        OperationLog::record(
            'created',
            self::LOG_MODULE,
            $reason,
            __('sales_settings.exchange_reasons.log_created', ['name' => $reason->name]),
            ['new' => $reason->only(self::TRACKED)]
        );

        return redirect()->route('exchange-reasons.index')
            ->with('success', __('sales_settings.exchange_reasons.created'));
    }

    public function edit($id)
    {
        return view('sales::exchange_reasons.form', [
            'reason' => ExchangeReason::findOrFail($id),
        ]);
    }

    public function update(ExchangeReasonRequest $request, $id)
    {
        $reason = ExchangeReason::findOrFail($id);

        try {
            $reason->fill($request->validated());

            // الفرق يُلتقط قبل الحفظ: بعده تصير القيمة القديمة هي الجديدة
            $changes = OperationLog::diff($reason, self::TRACKED);

            $reason->save();
        } catch (Throwable $e) {
            OperationLog::failure($e, self::LOG_MODULE, 'update', $reason);

            return back()->withInput()->with('error', __('sales_settings.exchange_reasons.save_failed'));
        }

        OperationLog::record(
            'updated',
            self::LOG_MODULE,
            $reason,
            __('sales_settings.exchange_reasons.log_updated', ['name' => $reason->name]),
            $changes
        );

        return redirect()->route('exchange-reasons.index')
            ->with('success', __('sales_settings.exchange_reasons.updated'));
    }

    public function destroy($id)
    {
        $reason = ExchangeReason::findOrFail($id);
        $snapshot = $reason->only(self::TRACKED);
        $name = $reason->name;

        try {
            $reason->delete();
        } catch (Throwable $e) {
            OperationLog::failure($e, self::LOG_MODULE, 'destroy', $reason);

            return back()->with('error', __('sales_settings.exchange_reasons.delete_failed'));
        }

        OperationLog::record(
            'deleted',
            self::LOG_MODULE,
            (int) $id,
            __('sales_settings.exchange_reasons.log_deleted', ['name' => $name]),
            ['old' => $snapshot]
        );

        return redirect()->route('exchange-reasons.index')
            ->with('success', __('sales_settings.exchange_reasons.deleted'));
    }

    public function toggleActive($id)
    {
        $reason = ExchangeReason::findOrFail($id);
        $was = (bool) $reason->is_active;

        $reason->update(['is_active' => ! $was]);

        OperationLog::record(
            'updated',
            self::LOG_MODULE,
            $reason,
            __('sales_settings.exchange_reasons.log_status', [
                'name' => $reason->name,
                'state' => $reason->is_active
                    ? __('sales_settings.ui.active')
                    : __('sales_settings.ui.inactive'),
            ]),
            ['old' => ['is_active' => $was], 'new' => ['is_active' => ! $was]]
        );

        return back()->with('success', __('sales_settings.exchange_reasons.status_updated'));
    }

    /**
     * عدد السجلّات المطلوب، بعد ردّ ما ليس في القائمة إلى الحجم المتوسّط.
     */
    private function perPage(Request $request): int
    {
        $requested = (int) $request->input('per_page');

        return in_array($requested, self::PAGE_SIZES, true) ? $requested : self::PAGE_SIZES[1];
    }
}
