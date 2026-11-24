<?php

namespace Modules\Sales\Http\Controllers;

use App\Constants\planFeatures\SalesPlanFeatures;;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\GiftOffer;
use App\Models\Product;
use App\Models\User;
use App\Support\OperationLog;
use Illuminate\Http\Request;
use Throwable;

/**
 * عروض الهدايا — «اشترِ كذا فتأخذ كذا».
 *
 * ما أُصلح هنا:
 *
 *   • **`is_for_all_employees` لا يُحفَظ عند التعديل** — الحقل مُتحقَّقٌ منه
 *     في `update()` ومبنيٌّ عليه فرعُ ربط الموظفين، لكنّه غائب عن مصفوفة
 *     `update([...])`. فمن فتح عرضاً «لكل الموظفين» وقصره على ثلاثة، رأى
 *     الثلاثة يُربطون في الجدول الوسيط ثم بقي العَلَم على «الكل» فتجاوزهم
 *     المنطقُ عند التطبيق. عرضٌ يُطبَّق على من لم يُقصَد به.
 *
 *   • **استعلام لكل صفّ (N+1)** — `GiftOffer::all()` ثم `$offer->targetProduct`
 *     و`$offer->giftProduct` في الحلقة: استعلامان لكل عرض. صارت العلاقتان
 *     مُحمَّلتين مسبقاً.
 *
 *   • **بلا ترقيم ولا بحث** — `all()` تُحضِر الجدول كلّه إلى الذاكرة وتعرضه
 *     في صفحة واحدة. البند 3.5 في تقرير التحسين يرصد غياب `paginate` كنمط
 *     في الوحدة كلّها.
 *
 *   • **السجلّات الثلاثة** — لم يكن إنشاء عرضٍ ولا تعديله ولا تعطيله يترك
 *     أثراً في «سجلّ النشاطات» ولا «سجلّ الأحداث»، ولا يُكتب شيء في «سجلّ
 *     الأخطاء» حين يفشل الحفظ. والعرض يمنح بضاعةً مجّاناً، فأثرُه المالي
 *     يستحقّ أن يُعرَف مَن أنشأه.
 *
 *   • **«حذف» ليس حذفاً** — `destroy()` تضع `is_active = false` وحدها،
 *     والزرّ كان مكتوباً عليه «حذف». الاسم الآن «تعطيل» كما هو الفعل.
 */
class GiftOfferController extends Controller
{
    use \Modules\Sales\Http\Controllers\Concerns\ChecksSubscriptionLimits;

    /** مفتاح الوحدة في السجلّات — به يربط السجلّ سطرَه بهذه الشاشة. */
    private const LOG_MODULE = 'SalesGiftOffers';

    /** الأحجام المسموحة لعدد السجلّات في الصفحة. */
    private const PAGE_SIZES = [10, 25, 50, 100];

    /** الحقول التي يُلتقط فرقُها للسجلّ. */
    private const TRACKED = [
        'name', 'target_product_id', 'min_quantity', 'gift_product_id',
        'gift_quantity', 'start_date', 'end_date',
        'is_for_all_clients', 'is_for_all_employees', 'is_active',
    ];

    public function __construct()
    {
        // مُنقولة إلى كتالوج sales.gift_offers.* — نفس نمط InvoicesPurchaseController
        $this->middleware('permission:sales.gift_offers.view', ['only' => ['index', 'show']]);
        $this->middleware('permission:sales.gift_offers.create', ['only' => ['create', 'store']]);
        $this->middleware('permission:sales.gift_offers.edit', ['only' => ['edit', 'update', 'status']]);
        $this->middleware('permission:sales.gift_offers.delete', ['only' => ['destroy']]);
    }

    public function index(Request $request)
    {
        // العلاقتان مُحمَّلتان مسبقاً: كانتا استعلامَين لكل صفّ في الحلقة
        $query = GiftOffer::query()->with(['targetProduct:id,name', 'giftProduct:id,name']);

        if ($request->filled('search')) {
            $term = '%'.$request->input('search').'%';

            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', $term)
                    ->orWhereHas('targetProduct', fn ($p) => $p->where('name', 'like', $term))
                    ->orWhereHas('giftProduct', fn ($p) => $p->where('name', 'like', $term));
            });
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->input('status') === 'active');
        }

        $offers = $query->latest('id')
            ->paginate($this->perPage($request))
            ->appends($request->query());

        // الإحصاء على الجدول كلّه لا على نتيجة التصفية
        $all = GiftOffer::query()->get(['is_active', 'start_date', 'end_date']);
        $today = now()->toDateString();

        return view('sales::gift_offers.index', [
            'offers' => $offers,
            'pageSizes' => self::PAGE_SIZES,
            // «عروض الهدايا» سقفٌ متزامنٌ لا عدّادٌ دوري: العبرة بكم عرضاً
            // سارياً الآن لا بكم أُنشئ هذا الشهر — ولذلك يهبط الرقم من نفسه
            // حين ينتهي تاريخ عرضٍ أو يُعطَّل
            'subscriptionLimit' => $this->subscriptionLimit(SalesPlanFeatures::GIFT_OFFERS),
            'stats' => [
                'total' => $all->count(),
                'active' => $all->where('is_active', true)->count(),
                // «سارٍ اليوم» ليس «مفعَّل»: عرضٌ مفعَّل انتهى تاريخه لا يُطبَّق
                'running' => $all->filter(fn ($o) => $o->is_active
                    && (! $o->start_date || $o->start_date <= $today)
                    && (! $o->end_date || $o->end_date >= $today))->count(),
            ],
        ]);
    }

    public function create()
    {
        return view('sales::gift_offers.create', $this->formData());
    }

    public function store(Request $request)
    {
        $this->validated($request);

        // ** سقف اشتراك فوترة ** — بعد التحقّق من المُدخلات وقبل الكتابة،
        // فلا يُردّ المستخدم برسالة الاشتراك وإدخالُه ناقصٌ أصلاً
        if ($blocked = $this->subscriptionBlock(SalesPlanFeatures::GIFT_OFFERS)) {
            return back()->withInput()->with('error', $blocked);
        }

        try {
            $giftOffer = GiftOffer::create($this->attributes($request));

            $this->syncAudiences($request, $giftOffer);
        } catch (Throwable $e) {
            OperationLog::failure($e, self::LOG_MODULE, 'store');

            return back()->withInput()->with('error', __('sales_settings.gift_offers.save_failed'));
        }

        OperationLog::record(
            'created',
            self::LOG_MODULE,
            $giftOffer,
            __('sales_settings.gift_offers.log_created', ['name' => $this->label($giftOffer)]),
            ['new' => $giftOffer->only(self::TRACKED)]
        );

        return redirect()->route('gift-offers.index')
            ->with('success', __('sales_settings.gift_offers.created'));
    }

    public function show(string $id)
    {
        // لا شاشة عرض مستقلّة: التفاصيل كلّها في شاشة التعديل
        return redirect()->route('gift-offers.edit', $id);
    }

    public function edit(GiftOffer $giftOffer)
    {
        $giftOffer->load('clients', 'users', 'excludedClients');

        return view('sales::gift_offers.create', $this->formData() + ['giftOffer' => $giftOffer]);
    }

    public function update(Request $request, $id)
    {
        $this->validated($request);

        $giftOffer = GiftOffer::findOrFail($id);

        try {
            $giftOffer->fill($this->attributes($request));

            // الفرق يُلتقط قبل الحفظ: بعده تصير القيمة القديمة هي الجديدة
            $changes = OperationLog::diff($giftOffer, self::TRACKED);

            $giftOffer->save();

            $this->syncAudiences($request, $giftOffer);
        } catch (Throwable $e) {
            OperationLog::failure($e, self::LOG_MODULE, 'update', $giftOffer);

            return back()->withInput()->with('error', __('sales_settings.gift_offers.save_failed'));
        }

        OperationLog::record(
            'updated',
            self::LOG_MODULE,
            $giftOffer,
            __('sales_settings.gift_offers.log_updated', ['name' => $this->label($giftOffer)]),
            $changes
        );

        return redirect()->route('gift-offers.index')
            ->with('success', __('sales_settings.gift_offers.updated'));
    }

    /**
     * تعطيل العرض — لا حذف.
     *
     * الاسم `destroy` يفرضه المسار المسمّى، والفعل تعطيل: العرض مذكورٌ في
     * فواتير أُنشئت تحته، فحذفُ صفّه يترك تلك الفواتير تشير إلى لا شيء.
     */
    public function destroy(GiftOffer $giftOffer)
    {
        $was = (bool) $giftOffer->is_active;

        try {
            $giftOffer->update(['is_active' => false]);
        } catch (Throwable $e) {
            OperationLog::failure($e, self::LOG_MODULE, 'destroy', $giftOffer);

            return back()->with('error', __('sales_settings.gift_offers.save_failed'));
        }

        OperationLog::record(
            'updated',
            self::LOG_MODULE,
            $giftOffer,
            __('sales_settings.gift_offers.log_disabled', ['name' => $this->label($giftOffer)]),
            ['old' => ['is_active' => $was], 'new' => ['is_active' => false]]
        );

        return redirect()->route('gift-offers.index')
            ->with('success', __('sales_settings.gift_offers.disabled'));
    }

    public function status($id)
    {
        $giftOffer = GiftOffer::findOrFail($id);
        $was = (bool) $giftOffer->is_active;

        $giftOffer->is_active = ! $was;
        $giftOffer->save();

        OperationLog::record(
            'updated',
            self::LOG_MODULE,
            $giftOffer,
            __('sales_settings.gift_offers.log_status', [
                'name' => $this->label($giftOffer),
                'state' => $giftOffer->is_active
                    ? __('sales_settings.ui.active')
                    : __('sales_settings.ui.inactive'),
            ]),
            ['old' => ['is_active' => $was], 'new' => ['is_active' => ! $was]]
        );

        return back()->with('success', __('sales_settings.gift_offers.status_updated'));
    }

    // ─────────────────────────────────────────────────────────────────
    // الداخل
    // ─────────────────────────────────────────────────────────────────

    /** قواعد التحقّق — واحدة للإنشاء والتعديل، فلا تتباعد النسختان. */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => 'nullable|string|max:255',
            'target_product_id' => 'nullable|exists:products,id',
            'min_quantity' => 'required|integer|min:1',
            'gift_product_id' => 'nullable|exists:products,id',
            'gift_quantity' => 'required|integer|min:1',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'is_for_all_clients' => 'required|boolean',
            'is_for_all_employees' => 'required|boolean',
            'clients' => 'nullable|array',
            'clients.*' => 'exists:clients,id',
            'employees' => 'nullable|array',
            'employees.*' => 'exists:users,id',
            'excluded_clients' => 'nullable|array',
            'excluded_clients.*' => 'exists:clients,id',
        ]);
    }

    /**
     * أعمدة العرض.
     *
     * `is_for_all_employees` هنا: كان غائباً عن `update` وحدها، فيبقى
     * العَلَم على قيمته القديمة بينما تُربَط قائمةُ موظفين جديدة.
     */
    private function attributes(Request $request): array
    {
        return [
            'name' => $request->input('name'),
            'target_product_id' => $request->input('target_product_id'),
            'min_quantity' => $request->input('min_quantity'),
            'gift_product_id' => $request->input('gift_product_id'),
            'gift_quantity' => $request->input('gift_quantity'),
            'start_date' => $request->input('start_date'),
            'end_date' => $request->input('end_date'),
            'is_for_all_clients' => $request->boolean('is_for_all_clients'),
            'is_for_all_employees' => $request->boolean('is_for_all_employees'),
        ];
    }

    /**
     * ربط العملاء والموظفين والمستثنين.
     *
     * عند الإنشاء كانت ثلاث حلقات `create()` — صفٌّ واستعلامٌ لكل مُنتقىً.
     * `sync` تفعل ذلك بإدراجٍ واحد، وهي نفسها المستعملة في التعديل، فلا
     * منطقان لربطٍ واحد.
     */
    private function syncAudiences(Request $request, GiftOffer $offer): void
    {
        // «لكل العملاء» يعني ألّا يبقى ربطٌ بأحد: قائمةٌ قديمة تحت العَلَم
        // العامّ تجعل العرض يبدو مقصوراً في شاشة التعديل وهو ليس كذلك
        $clients = $request->boolean('is_for_all_clients') ? [] : (array) $request->input('clients', []);
        $employees = $request->boolean('is_for_all_employees') ? [] : (array) $request->input('employees', []);
        $excluded = (array) $request->input('excluded_clients', []);

        $offer->clients()->sync($clients);
        $offer->users()->sync($employees);
        $offer->excludedClients()->sync($excluded);
    }

    /** بيانات النموذج المشتركة بين الإنشاء والتعديل. */
    private function formData(): array
    {
        return [
            'products' => Product::query()->orderBy('name')->get(['id', 'name']),
            'clients' => Client::query()->orderBy('id')->get(),
            'users' => User::query()
                ->whereIn('role', ['manager', 'employee'])
                ->orderBy('name')
                ->get(['id', 'name', 'email', 'role']),
        ];
    }

    /** اسمٌ صالح للسجلّ حين يكون حقل الاسم فارغاً (وهو مسموح). */
    private function label(GiftOffer $offer): string
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
