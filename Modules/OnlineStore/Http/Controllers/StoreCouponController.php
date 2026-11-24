<?php

namespace Modules\OnlineStore\Http\Controllers;

use App\Enums\CouponType;
use App\Models\Category;
use App\Models\Client;
use App\Models\Product;
use App\Models\Store\StoreCoupon;
use App\Support\CurrencySettings;
use App\Support\HeaderBranchScope;
use App\Support\OperationLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\OnlineStore\Http\Controllers\Concerns\SanitizesFilters;

/**
 * كنترولر إدارة كوبونات الخصم
 * Store Coupon Management Controller
 */
class StoreCouponController extends Controller
{
    use SanitizesFilters;

    public function __construct()
    {
        $this->middleware('permission.role:online_store.coupons.view', ['only' => ['index', 'show', 'usages', 'validateCoupon']]);
        $this->middleware('permission.role:online_store.coupons.create', ['only' => ['create', 'store', 'generateCode']]);
        $this->middleware('permission.role:online_store.coupons.edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission.role:online_store.coupons.delete', ['only' => ['destroy']]);
        $this->middleware('permission.role:online_store.coupons.toggle_status', ['only' => ['toggleStatus']]);
    }

    /** أسماء الحقول بالعربية في رسائل التحقق. */
    private const ATTRIBUTES = [
        'code' => 'كود الكوبون',
        'name' => 'اسم الكوبون',
        'description' => 'الوصف',
        'type' => 'نوع الخصم',
        'value' => 'قيمة الخصم',
        'min_order_amount' => 'الحد الأدنى للطلب',
        'max_discount' => 'الحد الأقصى للخصم',
        'usage_limit' => 'الحد الأقصى للاستخدام الكلي',
        'usage_limit_per_user' => 'الحد الأقصى لكل مستخدم',
        'applicable_products' => 'المنتجات المشمولة',
        'applicable_products.*' => 'المنتج المشمول',
        'applicable_categories' => 'الأصناف المشمولة',
        'applicable_categories.*' => 'الصنف المشمول',
        'excluded_products' => 'المنتجات المستثناة',
        'excluded_products.*' => 'المنتج المستثنى',
        'applicable_clients' => 'العملاء المشمولون',
        'applicable_clients.*' => 'العميل المشمول',
        'is_first_order_only' => 'للطلب الأول فقط',
        'start_date' => 'تاريخ البداية',
        'end_date' => 'تاريخ الانتهاء',
        'is_active' => 'حالة التفعيل',
        'client_id' => 'العميل',
        'order_amount' => 'قيمة الطلب',
        'product_ids' => 'المنتجات',
    ];

    /**
     * كوبونات نطاق المستخدم: الفرع النشط وما تحته. الفرع الرئيسي = الكل لصاحب
     * `data_scope_all` وغير الموظف ومدير المقرّ فقط ({@see HeaderBranchScope}).
     */
    private function scopedCoupons(): Builder
    {
        return StoreCoupon::query()->forBranch(HeaderBranchScope::nodeId());
    }

    /** 404 لكوبونٍ خارج النطاق — كان يُفتح ويُعدَّل ويُحذف برقمه من أي فرع. */
    private function ensureInScope(StoreCoupon $coupon): void
    {
        abort_unless($this->scopedCoupons()->whereKey($coupon->getKey())->exists(), 404);
    }

    /** قواعد الحفظ — واحدةٌ للإنشاء والتعديل. */
    private function rules(?StoreCoupon $coupon = null): array
    {
        return [
            'code' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9_\-]+$/', Rule::unique('store_coupons', 'code')->ignore($coupon?->id)],
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'type' => ['required', Rule::in(array_column(CouponType::cases(), 'value'))],
            // «شحن مجاني» بلا قيمة؛ والنسبة لا تتجاوز 100
            'value' => ['nullable', 'required_unless:type,free_shipping', 'numeric', 'min:0', 'max:99999999'],
            'min_order_amount' => 'nullable|numeric|min:0|max:99999999',
            'max_discount' => 'nullable|numeric|min:0|max:99999999',
            'usage_limit' => 'nullable|integer|min:1',
            'usage_limit_per_user' => 'nullable|integer|min:1',
            'applicable_products' => 'nullable|array',
            'applicable_products.*' => 'integer|exists:products,id',
            'applicable_categories' => 'nullable|array',
            'applicable_categories.*' => 'integer|exists:categories,id',
            'excluded_products' => 'nullable|array',
            'excluded_products.*' => 'integer|exists:products,id',
            'applicable_clients' => 'nullable|array',
            'applicable_clients.*' => ['integer', Rule::exists('clients', 'id')->where('is_store_client', 1)],
            'is_first_order_only' => 'nullable|boolean',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'is_active' => 'nullable|boolean',
        ];
    }

    private function validateCouponData(Request $request, ?StoreCoupon $coupon = null): array
    {
        $validated = $request->validate($this->rules($coupon), [
            'code.regex' => 'كود الكوبون يقبل الحروف الإنجليزية والأرقام والشرطة فقط، بلا مسافات.',
            'value.required_unless' => 'قيمة الخصم مطلوبة.',
        ], self::ATTRIBUTES);

        $type = $validated['type'];
        $value = (float) ($validated['value'] ?? 0);

        if ($type === CouponType::PERCENTAGE->value && ($value <= 0 || $value > 100)) {
            throw ValidationException::withMessages([
                'value' => 'نسبة الخصم يجب أن تكون أكبر من 0 ولا تتجاوز 100%.',
            ]);
        }
        if ($type === CouponType::FIXED->value && $value <= 0) {
            throw ValidationException::withMessages([
                'value' => 'مبلغ الخصم يجب أن يكون أكبر من صفر.',
            ]);
        }

        $validated['value'] = $type === CouponType::FREE_SHIPPING->value ? 0 : $value;
        // الحد الأقصى للخصم للنسبة المئوية وحدها
        if ($type !== CouponType::PERCENTAGE->value) {
            $validated['max_discount'] = null;
        }
        $validated['code'] = strtoupper($validated['code']);

        return $validated;
    }

    /** عملاء المتجر فقط — قائمة «العملاء المشمولون». */
    private function storeClients()
    {
        return Client::where('is_store_client', 1)
            ->select('id', 'trade_name as name', 'trade_name', 'first_name', 'last_name')
            ->orderBy('trade_name')->orderBy('first_name')
            ->get();
    }

    /**
     * عرض قائمة جميع الكوبونات
     */
    public function index(Request $request)
    {
        // `?search[]=x` في الرابط يصل مصفوفةً والقالب يطبعه نصّاً فيسقط بـ500
        $this->dropNonScalarFilters($request, array_keys($request->query()));

        $query = $this->scopedCoupons();

        // البحث
        if ($request->filled('search') && is_string($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // فلترة حسب الحالة
        if ($request->filled('status')) {
            if ($request->status === 'active') {
                $query->active();
            } elseif ($request->status === 'inactive') {
                $query->where('is_active', false);
            } elseif ($request->status === 'expired') {
                $query->expired();
            } elseif ($request->status === 'valid') {
                $query->valid();
            }
        }

        // فلترة حسب النوع
        if (is_string($request->type) && CouponType::tryFrom($request->type)) {
            $query->where('type', $request->type);
        }

        $coupons = $query->withCount('usages')
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $couponTypes = CouponType::cases();

        // بطاقات الأرقام على كل كوبونات النطاق لا على صفحة الجدول الحالية
        // (كانت تُحسب في القالب من عمودٍ لا وجود له `expires_at` فتظهر أصفاراً)
        $stats = [
            'total' => $this->scopedCoupons()->count(),
            'active' => $this->scopedCoupons()->valid()->count(),
            'expired' => $this->scopedCoupons()->expired()->count(),
            'used' => (int) $this->scopedCoupons()->sum('used_count'),
        ];

        return view('onlinestore::coupons.index', compact('coupons', 'couponTypes', 'stats'));
    }

    /**
     * عرض صفحة إنشاء كوبون جديد
     */
    public function create()
    {
        $couponTypes = CouponType::cases();
        $products = Product::active()->select('id', 'name')->get();
        $categories = Category::select('id', 'name')->get();
        $clients = $this->storeClients();

        return view('onlinestore::coupons.create', compact('couponTypes', 'products', 'categories', 'clients'));
    }

    /**
     * حفظ كوبون جديد
     */
    public function store(Request $request)
    {
        $validated = $this->validateCouponData($request);

        // الكوبون يُختم بالفرع النشط لمن أنشأه — كان يُختم بالفرع الرئيسي دائماً
        // فلا يراه منشئُه الواقف على فرعٍ آخر
        $validated['branch_id'] = current_branch_id() ?: null;

        // تعيين القيم الافتراضية
        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['is_first_order_only'] = $request->boolean('is_first_order_only', false);
        $validated['used_count'] = 0;

        $storeCoupon = StoreCoupon::create($validated);

        OperationLog::record('created', 'coupon', $storeCoupon, 'تم إنشاء كوبون **'.$storeCoupon->code.'**');

        return redirect()->route('store_coupons.index')
            ->with('success', 'تم إنشاء الكوبون بنجاح');
    }

    /**
     * عرض تفاصيل كوبون
     */
    public function show(StoreCoupon $storeCoupon)
    {
        $this->ensureInScope($storeCoupon);

        $storeCoupon->load(['usages.client', 'usages.invoice']);

        // إحصائيات الاستخدام
        $stats = [
            'total_usages' => $storeCoupon->usages->count(),
            'total_discount' => $storeCoupon->usages->sum('discount_amount'),
            'unique_users' => $storeCoupon->usages->unique('client_id')->count(),
        ];

        return view('onlinestore::coupons.show', compact('storeCoupon', 'stats'));
    }

    /**
     * عرض صفحة تعديل كوبون
     */
    public function edit(StoreCoupon $storeCoupon)
    {
        $this->ensureInScope($storeCoupon);

        $couponTypes = CouponType::cases();
        $products = Product::active()->select('id', 'name')->get();
        $categories = Category::select('id', 'name')->get();
        $clients = $this->storeClients();

        return view('onlinestore::coupons.edit', compact('storeCoupon', 'couponTypes', 'products', 'categories', 'clients'));
    }

    /**
     * تحديث كوبون
     */
    public function update(Request $request, StoreCoupon $storeCoupon)
    {
        $this->ensureInScope($storeCoupon);

        $validated = $this->validateCouponData($request, $storeCoupon);

        // حدٌّ كليّ أقل مما استُخدم فعلاً يجعل الكوبون «منتهياً» بلا تفسير
        if (! empty($validated['usage_limit']) && $validated['usage_limit'] < (int) $storeCoupon->used_count) {
            return redirect()->back()->withInput()
                ->withErrors(['usage_limit' => 'الحد الأقصى للاستخدام لا يصح أن يقل عن عدد مرات الاستخدام الحالية ('.$storeCoupon->used_count.').']);
        }

        // حقول لا يرسلها النموذج تبقى على حالها (القوائم المشمولة/المستثناة)
        foreach (['applicable_products', 'applicable_categories', 'excluded_products', 'applicable_clients'] as $listField) {
            if (! $request->has($listField)) {
                unset($validated[$listField]);
            }
        }

        // تعيين القيم — ما لم يُرسَل يبقى على حاله
        $validated['is_active'] = $request->boolean('is_active', (bool) $storeCoupon->is_active);
        $validated['is_first_order_only'] = $request->boolean('is_first_order_only', (bool) $storeCoupon->is_first_order_only);

        $storeCoupon->update($validated);

        OperationLog::record('updated', 'coupon', $storeCoupon, 'تم تعديل كوبون **'.$storeCoupon->code.'**');

        return redirect()->route('store_coupons.index')
            ->with('success', 'تم تحديث الكوبون بنجاح');
    }

    /**
     * حذف كوبون
     */
    public function destroy(StoreCoupon $storeCoupon)
    {
        $this->ensureInScope($storeCoupon);

        // التحقق من عدم وجود استخدامات — سجلّ الاستخدام أو طلبٌ يحمل الكوبون
        if ($storeCoupon->usages()->exists()
            || (int) $storeCoupon->used_count > 0
            || DB::table('online_orders')->where('coupon_id', $storeCoupon->id)->exists()) {
            return redirect()->route('store_coupons.index')
                ->with('error', 'لا يمكن حذف الكوبون «'.$storeCoupon->code.'» لأنه استُخدم في طلبات. عطّله بدل حذفه.');
        }

        OperationLog::record('deleted', 'coupon', $storeCoupon, 'تم حذف كوبون **'.$storeCoupon->code.'**');

        $storeCoupon->delete();

        return redirect()->route('store_coupons.index')
            ->with('success', 'تم حذف الكوبون بنجاح');
    }

    /**
     * تبديل حالة النشاط
     */
    public function toggleStatus(StoreCoupon $storeCoupon)
    {
        $this->ensureInScope($storeCoupon);

        $storeCoupon->update(['is_active' => ! $storeCoupon->is_active]);

        OperationLog::record('updated', 'coupon', $storeCoupon, ($storeCoupon->is_active ? 'تم تفعيل كوبون **' : 'تم تعطيل كوبون **').$storeCoupon->code.'**');

        return response()->json([
            'success' => true,
            'message' => $storeCoupon->is_active ? 'تم تفعيل الكوبون' : 'تم تعطيل الكوبون',
            'is_active' => $storeCoupon->is_active,
        ]);
    }

    /**
     * توليد كود كوبون عشوائي
     */
    public function generateCode()
    {
        do {
            $code = strtoupper(Str::random(8));
        } while (StoreCoupon::where('code', $code)->exists());

        return response()->json([
            'success' => true,
            'code' => $code,
        ]);
    }

    /**
     * عرض سجل استخدامات كوبون
     */
    public function usages(StoreCoupon $storeCoupon)
    {
        $this->ensureInScope($storeCoupon);

        $usages = $storeCoupon->usages()
            ->with(['client', 'invoice'])
            ->latest()
            ->paginate(20);

        return view('onlinestore::coupons.usages', compact('storeCoupon', 'usages'));
    }

    /**
     * التحقق من صلاحية كوبون (API)
     */
    /**
     * كان اسمها `validate` — وهو اسم دالةٍ معرَّفةٍ في `Controller` الأساس
     * بتوقيعٍ مختلف (`$rules, $messages, $attributes`). فمجرّد تحميل هذا
     * الملفّ يُنتج خطأً قاتلاً: «Declaration must be compatible». أي أن
     * كل طلبٍ يمرّ على مسارات الكوبونات كان يسقط قبل أن يبدأ.
     */
    public function validateCoupon(Request $request)
    {
        $request->validate([
            'code' => 'required|string|max:50',
            'client_id' => 'required|integer|exists:clients,id',
            'order_amount' => 'required|numeric|min:0',
            'product_ids' => 'nullable|array',
            'product_ids.*' => 'integer',
        ], [], self::ATTRIBUTES);

        $coupon = $this->scopedCoupons()->where('code', strtoupper($request->code))->first();

        if (! $coupon) {
            return response()->json([
                'valid' => false,
                'message' => 'الكوبون غير موجود',
            ]);
        }

        if (! $coupon->canBeUsedByClient($request->client_id)) {
            return response()->json([
                'valid' => false,
                'message' => 'لا يمكن استخدام هذا الكوبون',
            ]);
        }

        $discount = $coupon->calculateDiscount(
            $request->order_amount,
            $request->product_ids ?? []
        );

        return response()->json([
            'valid' => true,
            'coupon' => [
                'id' => $coupon->id,
                'code' => $coupon->code,
                'name' => $coupon->name,
                'type' => $coupon->type,
                'discount' => $discount,
                'currency' => CurrencySettings::symbol(),
                'description' => $coupon->discount_description,
            ],
        ]);
    }
}
