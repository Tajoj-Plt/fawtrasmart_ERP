<?php

namespace Modules\Sales\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Client;
use App\Models\Offer;
use App\Models\Product;
use App\Support\OperationLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * عروض الحسم.
 *
 * ما أُصلح هنا:
 *
 *   • **نموذج الإضافة كان يُرسِل إلى المتحكّم الخطأ** — قالب
 *     `sitting/offers/create.blade.php` مكتوبٌ فيه
 *     `action="{{ route('gift-offers.store') }}"`، أي أن حقول عرض الحسم
 *     تذهب إلى متحكّم *عروض الهدايا*، وهو يطلب `min_quantity` و
 *     `gift_quantity` و`is_for_all_clients`. فالنموذج يرتدّ بأخطاء تحقّق
 *     عن حقولٍ لا وجود لها في الشاشة. هذه الشاشة لم تحفظ عرضاً قطّ.
 *
 *   • **الحفظ يعود إلى شاشة أخرى** — `store` و`update` و`destroy` تعيد
 *     التوجيه إلى `gift-offers.index`. فمن أضاف عرض حسم وجد نفسه في قائمة
 *     الهدايا ولا يرى ما أضافه، فيظنّ أنّ الحفظ لم يقع فيُعيده.
 *
 *   • **`updateStatus` دالّة ميّتة** — لا مسار يشير إليها، وهي تعيد التوجيه
 *     إلى `offer.show` بمفرد لا وجود له (المسار `offers.show`)، وتبدّل عمود
 *     `status` بينما بقيّة الشاشة تعمل على `is_active`. حُذفت.
 *
 *   • **بلا ترقيم ولا بحث** — `Offer::all()` تُحضر الجدول كلّه.
 *
 *   • **السجلّات الثلاثة** — لم يكن الحفظ ولا الحذف يترك أثراً في «سجلّ
 *     النشاطات» ولا «سجلّ الأحداث»، وكان الفشل يُكتب في سجلّ لارافيل
 *     (`Log::error`) لا في «سجلّ الأخطاء» الذي تعرضه شاشة الإعدادات.
 *
 *   • **رسالة الخطأ تكشف الاستثناء للمستخدم** — `'حدث خطأ … ' . $e->getMessage()`
 *     تعرض نصّ استثناء قاعدة البيانات في الشاشة: البند 7.6 في تقرير
 *     التحسين يرصده تسريباً. التفصيل صار في سجلّ الأخطاء والرسالة عامّة.
 */
class OffersController extends Controller
{
    /** مفتاح الوحدة في السجلّات — به يربط السجلّ سطرَه بهذه الشاشة. */
    private const LOG_MODULE = 'SalesDiscountOffers';

    /** الأحجام المسموحة لعدد السجلّات في الصفحة. */
    private const PAGE_SIZES = [10, 25, 50, 100];

    /** الحقول التي يُلتقط فرقُها للسجلّ. */
    private const TRACKED = [
        'name', 'valid_from', 'valid_to', 'type', 'quantity',
        'discount_type', 'discount_value', 'unit_type', 'is_active',
    ];

    public function __construct()
    {
        // مُنقولة إلى كتالوج sales.offers.* — نفس نمط InvoicesPurchaseController
        $this->middleware('permission:sales.offers.view', ['only' => ['index', 'show']]);
        $this->middleware('permission:sales.offers.create', ['only' => ['create', 'store']]);
        $this->middleware('permission:sales.offers.edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission:sales.offers.delete', ['only' => ['destroy']]);
    }

    public function index(Request $request)
    {
        // `withCount` لا `with`: الشاشة تعرض عددَ العملاء لا أسماءهم،
        // وتحميل العلاقة كاملةً لأجل عدٍّ يجرّ الجدول الوسيط كلّه
        $query = Offer::query()->withCount('clients');

        if ($request->filled('search')) {
            $query->where('name', 'like', '%'.$request->input('search').'%');
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->input('status') === 'active');
        }

        $offers = $query->latest('id')
            ->paginate($this->perPage($request))
            ->appends($request->query());

        // الإحصاء على الجدول كلّه لا على نتيجة التصفية
        $all = Offer::query()->get(['is_active', 'valid_from', 'valid_to']);
        $today = now()->startOfDay();

        return view('sales::sitting.offers.index', [
            'offers' => $offers,
            'pageSizes' => self::PAGE_SIZES,
            'stats' => [
                'total' => $all->count(),
                'active' => $all->where('is_active', true)->count(),
                // «سارٍ اليوم» ليس «مفعَّل»: عرضٌ مفعَّل انتهت مدّته لا يُطبَّق
                'running' => $all->filter(fn ($o) => $o->is_active
                    && (! $o->valid_from || $o->valid_from->lte($today))
                    && (! $o->valid_to || $o->valid_to->gte($today)))->count(),
            ],
        ]);
    }

    public function create()
    {
        return view('sales::sitting.offers.form', $this->formData());
    }

    public function store(Request $request)
    {
        $this->validated($request);

        try {
            DB::beginTransaction();

            $offer = Offer::create($this->attributes($request));
            $this->syncScope($request, $offer);

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            OperationLog::failure($e, self::LOG_MODULE, 'store');

            return back()->withInput()->with('error', __('sales_settings.offers.save_failed'));
        }

        OperationLog::record(
            'created',
            self::LOG_MODULE,
            $offer,
            __('sales_settings.offers.log_created', ['name' => $this->label($offer)]),
            ['new' => $offer->only(self::TRACKED)]
        );

        // إلى قائمة عروض الحسم لا إلى قائمة الهدايا
        return redirect()->route('offers.index')
            ->with('success', __('sales_settings.offers.created'));
    }

    public function edit($id)
    {
        return view('sales::sitting.offers.form', $this->formData() + [
            'offer' => Offer::with(['clients', 'products', 'categories'])->findOrFail($id),
        ]);
    }

    public function update(Request $request, $id)
    {
        $this->validated($request);

        $offer = Offer::findOrFail($id);

        try {
            DB::beginTransaction();

            $offer->fill($this->attributes($request));

            // الفرق يُلتقط قبل الحفظ: بعده تصير القيمة القديمة هي الجديدة
            $changes = OperationLog::diff($offer, self::TRACKED);

            $offer->save();
            $this->syncScope($request, $offer);

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            OperationLog::failure($e, self::LOG_MODULE, 'update', $offer);

            return back()->withInput()->with('error', __('sales_settings.offers.save_failed'));
        }

        OperationLog::record(
            'updated',
            self::LOG_MODULE,
            $offer,
            __('sales_settings.offers.log_updated', ['name' => $this->label($offer)]),
            $changes
        );

        return redirect()->route('offers.index')
            ->with('success', __('sales_settings.offers.updated'));
    }

    public function show($id)
    {
        return view('sales::sitting.offers.show', [
            'offer' => Offer::with(['clients', 'products', 'categories'])->findOrFail($id),
        ]);
    }

    /**
     * حذفٌ ناعم — `Offer` تستعمل `SoftDeletes`، فالصفّ يبقى ولا تنكسر
     * الفواتير التي طُبِّق عليها العرض.
     */
    public function destroy($id)
    {
        $offer = Offer::findOrFail($id);
        $snapshot = $offer->only(self::TRACKED);
        $name = $this->label($offer);

        try {
            $offer->delete();
        } catch (Throwable $e) {
            OperationLog::failure($e, self::LOG_MODULE, 'destroy', $offer);

            return back()->with('error', __('sales_settings.offers.delete_failed'));
        }

        OperationLog::record(
            'deleted',
            self::LOG_MODULE,
            (int) $id,
            __('sales_settings.offers.log_deleted', ['name' => $name]),
            ['old' => $snapshot]
        );

        return redirect()->route('offers.index')
            ->with('success', __('sales_settings.offers.deleted'));
    }

    // ─────────────────────────────────────────────────────────────────
    // الداخل
    // ─────────────────────────────────────────────────────────────────

    /**
     * قواعد التحقّق — واحدة للإنشاء والتعديل.
     *
     * كانت نسختان تختلفان في الإلزام: `name` و`valid_from` و`quantity`
     * اختيارية في الإضافة إلزامية في التعديل، فعرضٌ يُنشأ بلا اسم لا يُعدَّل
     * حتى يُسمّى — رسالةُ خطأ عن حقلٍ لم يُطلَب حين أُنشئ.
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:255',
            'valid_from' => 'nullable|date',
            'valid_to' => 'nullable|date|after_or_equal:valid_from',
            'type' => 'required|integer|in:1,2',
            'quantity' => 'required|numeric|min:1',
            'discount_type' => 'required|integer|in:1,2',
            'discount_value' => 'required|numeric|min:0',
            'unit_type' => 'required|integer|in:1,2,3',
            'client_id' => 'nullable|array',
            'client_id.*' => 'exists:clients,id',
            'product_id' => 'required_if:unit_type,3|nullable|array',
            'product_id.*' => 'exists:products,id',
            'category_id' => 'required_if:unit_type,2|nullable|array',
            'category_id.*' => 'exists:categories,id',
            'is_active' => 'nullable|boolean',
        ]);
    }

    /** أعمدة العرض. */
    private function attributes(Request $request): array
    {
        return [
            'name' => $request->input('name'),
            'valid_from' => $request->input('valid_from'),
            'valid_to' => $request->input('valid_to'),
            'type' => (int) $request->input('type'),
            'quantity' => $request->input('quantity'),
            'discount_type' => (int) $request->input('discount_type'),
            'discount_value' => $request->input('discount_value'),
            'unit_type' => (int) $request->input('unit_type'),
            'is_active' => $request->boolean('is_active'),
        ];
    }

    /**
     * ربط العملاء والمنتجات والتصنيفات.
     *
     * `sync` لا `attach` في الإنشاء أيضاً: `attach` تُضيف صفّاً جديداً في
     * كل حفظ، فإعادةُ إرسال النموذج تُكرّر العميل نفسه في الجدول الوسيط.
     *
     * والنطاق يُمحى إذا لم يعد يخصّه: عرضٌ حُوِّل من «المنتجات» إلى «الكل»
     * كان يبقي قائمة منتجاته مربوطة، فتظهر في شاشة التعديل قيوداً تبدو
     * فاعلةً وهي ليست كذلك.
     */
    private function syncScope(Request $request, Offer $offer): void
    {
        $unit = (int) $request->input('unit_type');

        $offer->clients()->sync((array) $request->input('client_id', []));
        $offer->products()->sync($unit === 3 ? (array) $request->input('product_id', []) : []);
        $offer->categories()->sync($unit === 2 ? (array) $request->input('category_id', []) : []);
    }

    /** بيانات النموذج المشتركة بين الإنشاء والتعديل. */
    private function formData(): array
    {
        return [
            'products' => Product::query()->orderBy('name')->get(['id', 'name']),
            'categories' => Category::query()->orderBy('name')->get(['id', 'name']),
            'clients' => Client::query()->orderBy('id')->get(),
        ];
    }

    /** اسمٌ صالح للسجلّ حين يكون حقل الاسم فارغاً في صفٍّ قديم. */
    private function label(Offer $offer): string
    {
        return $offer->name ?: '#'.$offer->id;
    }

    /** عدد السجلّات المطلوب، بعد ردّ ما ليس في القائمة إلى الحجم المتوسّط. */
    private function perPage(Request $request): int
    {
        $requested = (int) $request->input('per_page');

        return in_array($requested, self::PAGE_SIZES, true) ? $requested : self::PAGE_SIZES[1];
    }
}
