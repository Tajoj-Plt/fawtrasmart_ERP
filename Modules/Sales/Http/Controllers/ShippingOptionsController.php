<?php

namespace Modules\Sales\Http\Controllers;

use App\Constants\planFeatures\SalesPlanFeatures;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\ShippingOption;
use App\Support\AccountingSettings;
use App\Support\OperationLog;
use Illuminate\Http\Request;
use Modules\Sales\Http\Requests\ShippingOptionRequest;
use Throwable;

/**
 * خيارات الشحن.
 *
 * ثلاث وصلات بإعدادات النظام لم تكن هنا:
 *
 *   • **السجلّات الثلاثة** — لم يكن إضافةُ خيار شحن ولا تعديله ولا حذفه
 *     يترك أثراً في «سجلّ النشاطات» ولا «سجلّ الأحداث» ولا «سجلّ الأخطاء».
 *     `App\Support\OperationLog` تكتب في الثلاثة بنداءٍ واحد، وهي مستعملة
 *     في وحدة المشتريات منذ إصلاحها، فبقيت المبيعات وحدها بلا أثر.
 *
 *   • **الحسابات المحاسبية** — «اتركه فارغاً لاستعمال حساب الإيرادات العام»
 *     كان وعداً في نصّ الشاشة لا سطراً في الشيفرة. الآن يُقرأ الحساب العام
 *     من `AccountingSettings` أي من شاشة «الحسابات المحاسبية» نفسها.
 *
 *   • **عدد السجلّات** — كان `paginate(20)` ثابتاً يتجاهل `per_page`.
 *     القائمة مغلقة عمداً: البند 6.3 في تقرير التحسين يرصد `per_page`
 *     غير المتحقَّق منه كثغرة إنهاك.
 */
class ShippingOptionsController extends Controller
{
    use Concerns\ChecksSubscriptionLimits;

    /** مفتاح الوحدة في السجلّات — به يربط السجلّ سطرَه بهذه الشاشة. */
    private const LOG_MODULE = 'SalesShippingOptions';

    /** الأحجام المسموحة لعدد السجلّات في الصفحة. */
    private const PAGE_SIZES = [10, 25, 50, 100];

    public function __construct()
    {
        // مُنقولة إلى كتالوج sales.shipping_options.* — نفس نمط InvoicesPurchaseController
        $this->middleware('permission:sales.shipping_options.view', ['only' => ['index', 'show']]);
        $this->middleware('permission:sales.shipping_options.manage', ['only' => [
            'create', 'store', 'edit', 'update', 'updateStatus', 'destroy',
        ]]);
    }

    public function index(Request $request)
    {
        $query = ShippingOption::query()->with('defaultAccount');

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $term = '%'.$request->input('search').'%';
                $q->where('name', 'like', $term)->orWhere('description', 'like', $term);
            });
        }

        if ($request->filled('status')) {
            $query->where('status', (int) $request->input('status'));
        }

        if ($request->filled('tax')) {
            $query->where('tax', (int) $request->input('tax'));
        }

        // `appends` تُبقي التصفية عبر الصفحات، وبدونها يعود الانتقال إلى
        // الصفحة الثانية بنتائج غير مصفّاة
        $shippingOptions = $query->ordered()
            ->paginate($this->perPage($request))
            ->appends($request->query());

        // الإحصاء على الجدول كلّه لا على نتيجة التصفية: «إجمالي الخيارات»
        // الذي يتغيّر مع كلّ بحث ليس إجمالاً
        $all = ShippingOption::query()->get(['status', 'cost']);

        return view('sales::sitting.shipping_options.index', [
            'subscriptionLimit' => $this->subscriptionLimit(SalesPlanFeatures::SHIPPING_OPTIONS),
            'shippingOptions' => $shippingOptions,
            'pageSizes' => self::PAGE_SIZES,
            'stats' => [
                'total' => $all->count(),
                'active' => $all->where('status', ShippingOption::STATUS_ACTIVE)->count(),
                'avg_cost' => $all->isEmpty() ? 0 : round($all->avg('cost'), 2),
            ],
        ]);
    }

    public function create()
    {
        return view('sales::sitting.shipping_options.form', [
            'shippingOption' => new ShippingOption([
                'status' => ShippingOption::STATUS_ACTIVE,
                'tax' => ShippingOption::TAX_VAT,
                'cost' => 0,
            ]),
            'accounts' => $this->accounts(),
            'fallbackAccount' => $this->fallbackAccount(),
        ]);
    }

    public function store(ShippingOptionRequest $request)
    {
        // ** حدُّ اشتراك فوترة ** — قبل أيّ كتابة، بالقياس الحيّ لا باللقطة
        if ($blocked = $this->subscriptionBlock(SalesPlanFeatures::SHIPPING_OPTIONS)) {
            return back()->withInput()->with('error', $blocked);
        }

        try {
            $option = ShippingOption::create($request->validated());
        } catch (Throwable $e) {
            OperationLog::failure($e, self::LOG_MODULE, 'store');

            return back()->withInput()->with('error', __('sales_settings.shipping.save_failed'));
        }

        OperationLog::record(
            'created',
            self::LOG_MODULE,
            $option,
            __('sales_settings.shipping.log_created', ['name' => $option->name]),
            ['new' => $option->only(['name', 'cost', 'tax', 'status', 'display_order', 'default_account_id'])]
        );

        return redirect()->route('shippingOptions.index')
            ->with('success', __('sales_settings.shipping.created'));
    }

    public function edit($id)
    {
        return view('sales::sitting.shipping_options.form', [
            'shippingOption' => ShippingOption::findOrFail($id),
            'accounts' => $this->accounts(),
            'fallbackAccount' => $this->fallbackAccount(),
        ]);
    }

    public function update(ShippingOptionRequest $request, $id)
    {
        $option = ShippingOption::findOrFail($id);

        try {
            $option->fill($request->validated());

            // الفرق يُلتقط قبل الحفظ: بعده تصير القيمة القديمة هي الجديدة
            $changes = OperationLog::diff($option, [
                'name', 'description', 'cost', 'tax', 'status', 'display_order', 'default_account_id',
            ]);

            $option->save();
        } catch (Throwable $e) {
            OperationLog::failure($e, self::LOG_MODULE, 'update', $option);

            return back()->withInput()->with('error', __('sales_settings.shipping.save_failed'));
        }

        OperationLog::record(
            'updated',
            self::LOG_MODULE,
            $option,
            __('sales_settings.shipping.log_updated', ['name' => $option->name]),
            $changes
        );

        return redirect()->route('shippingOptions.index')
            ->with('success', __('sales_settings.shipping.updated'));
    }

    public function show($id)
    {
        return view('sales::sitting.shipping_options.show', [
            'shippingOption' => ShippingOption::with('defaultAccount')->findOrFail($id),
            'fallbackAccount' => $this->fallbackAccount(),
        ]);
    }

    public function updateStatus($id)
    {
        $option = ShippingOption::findOrFail($id);
        $was = $option->status;

        $option->status = $option->isActive()
            ? ShippingOption::STATUS_INACTIVE
            : ShippingOption::STATUS_ACTIVE;

        $option->save();

        OperationLog::record(
            'updated',
            self::LOG_MODULE,
            $option,
            __('sales_settings.shipping.log_status', [
                'name' => $option->name,
                'state' => $option->isActive() ? __('sales_settings.ui.active') : __('sales_settings.ui.inactive'),
            ]),
            ['old' => ['status' => $was], 'new' => ['status' => $option->status]]
        );

        return back()->with('success', __('sales_settings.shipping.status_updated'));
    }

    public function destroy($id)
    {
        $option = ShippingOption::findOrFail($id);
        $snapshot = $option->only(['name', 'cost', 'tax', 'status', 'display_order', 'default_account_id']);
        $name = $option->name;

        try {
            $option->delete();
        } catch (Throwable $e) {
            OperationLog::failure($e, self::LOG_MODULE, 'destroy', $option);

            return back()->with('error', __('sales_settings.shipping.save_failed'));
        }

        OperationLog::record(
            'deleted',
            self::LOG_MODULE,
            $id,
            __('sales_settings.shipping.log_deleted', ['name' => $name]),
            ['old' => $snapshot]
        );

        return redirect()->route('shippingOptions.index')
            ->with('success', __('sales_settings.shipping.deleted'));
    }

    /**
     * عدد السجلّات المطلوب، بعد ردّ ما ليس في القائمة إلى أوّلها.
     */
    private function perPage(Request $request): int
    {
        $requested = (int) $request->input('per_page');

        return in_array($requested, self::PAGE_SIZES, true) ? $requested : self::PAGE_SIZES[1];
    }

    /**
     * الحساب الذي تذهب إليه إيرادات الشحن حين لا يُحدَّد حساب للخيار.
     *
     * من شاشة «الحسابات المحاسبية» لا من رقم مكتوب في الشيفرة، فتغييرُ
     * المحاسب لربطه يسري هنا فور حفظه.
     */
    private function fallbackAccount(): ?Account
    {
        return AccountingSettings::account('sales_revenue')
            ?? AccountingSettings::account('sales');
    }

    /**
     * الحسابات التفصيلية وحدها — حساب أب لا تُقيَّد عليه حركة، فعرضه
     * في القائمة دعوة إلى ربط لا يصحّ.
     */
    private function accounts()
    {
        return Account::query()
            ->whereNotExists(function ($sub) {
                $sub->selectRaw('1')
                    ->from('accounts as child')
                    ->whereColumn('child.parent_id', 'accounts.id');
            })
            ->orderBy('code')
            ->get(['id', 'code', 'name']);
    }
}
