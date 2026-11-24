<?php

namespace Modules\OnlineStore\Http\Controllers;

use App\Enums\BannerLinkType;
use App\Enums\BannerPosition;
use App\Models\Category;
use App\Models\Product;
use App\Models\Store\Brand;
use App\Models\Store\StoreBanner;
use App\Support\HeaderBranchScope;
use App\Support\OperationLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\OnlineStore\Http\Controllers\Concerns\SanitizesFilters;

/**
 * Controller لإدارة بانرات وإعلانات المتجر
 */
class StoreBannerController extends Controller
{
    use SanitizesFilters;

    public function __construct()
    {
        $this->middleware('permission.role:online_store.banners.view', ['only' => ['index', 'show']]);
        $this->middleware('permission.role:online_store.banners.create', ['only' => ['create', 'store', 'duplicate']]);
        $this->middleware('permission.role:online_store.banners.edit', ['only' => ['edit', 'update', 'updateOrder', 'removeMobileImage']]);
        $this->middleware('permission.role:online_store.banners.delete', ['only' => ['destroy']]);
        $this->middleware('permission.role:online_store.banners.toggle_status', ['only' => ['toggleStatus']]);
    }

    /** أسماء الحقول بالعربية في رسائل التحقق. */
    private const ATTRIBUTES = [
        'title' => 'عنوان البانر',
        'description' => 'الوصف',
        'image' => 'صورة البانر',
        'mobile_image' => 'صورة الجوال',
        'link' => 'الرابط',
        'link_type' => 'نوع الرابط',
        'link_id' => 'العنصر المرتبط',
        'position' => 'موقع العرض',
        'sort_order' => 'الترتيب',
        'start_date' => 'تاريخ البداية',
        'end_date' => 'تاريخ الانتهاء',
        'is_active' => 'حالة التفعيل',
        'orders' => 'الترتيب',
        'orders.*.id' => 'البانر',
        'orders.*.sort_order' => 'رقم الترتيب',
    ];

    /**
     * بانرات نطاق المستخدم: الفرع النشط وما تحته. الفرع الرئيسي = الكل لصاحب
     * `data_scope_all` وغير الموظف ومدير المقرّ فقط ({@see HeaderBranchScope}).
     */
    private function scopedBanners(): Builder
    {
        return StoreBanner::query()->forBranch(HeaderBranchScope::nodeId());
    }

    /** 404 لبانرٍ خارج النطاق. */
    private function ensureInScope(StoreBanner $banner): void
    {
        abort_unless($this->scopedBanners()->whereKey($banner->getKey())->exists(), 404);
    }

    private function rules(bool $creating): array
    {
        return [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'image' => [$creating ? 'required' : 'nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:5120'],
            'mobile_image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:3072',
            'link' => 'nullable|url|max:500',
            // قيمةٌ خارج الأنواع المعرَّفة كانت تُسقط الحفظ بخطأ 500
            'link_type' => ['required', Rule::in(array_column(BannerLinkType::cases(), 'value'))],
            'link_id' => 'nullable|integer|min:1',
            'position' => ['required', Rule::in(array_column(BannerPosition::cases(), 'value'))],
            'sort_order' => 'nullable|integer|min:0',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'is_active' => 'nullable|boolean',
        ];
    }

    /**
     * العنصر المرتبط يجب أن يوجد فعلاً: بانر «منتج» بلا منتج (أو برقمٍ لا
     * وجود له) يفتح صفحةً فارغة في المتجر.
     */
    private function assertLinkTarget(array &$validated): void
    {
        $table = match ($validated['link_type']) {
            BannerLinkType::PRODUCT->value => 'products',
            BannerLinkType::CATEGORY->value => 'categories',
            BannerLinkType::BRAND->value => 'brands',
            default => null,
        };

        if ($table === null) {
            $validated['link_id'] = null;

            return;
        }

        if (empty($validated['link_id']) || ! DB::table($table)->where('id', $validated['link_id'])->exists()) {
            throw ValidationException::withMessages([
                'link_id' => 'اختر '.(['products' => 'المنتج', 'categories' => 'الصنف', 'brands' => 'العلامة التجارية'][$table]).' الذي يفتحه البانر.',
            ]);
        }
    }

    /** يحفظ صورةً مرفوعة باسمٍ امتدادُه من نوع الملف الفعلي لا من اسمه. */
    private function storeBannerImage($file, string $prefix): string
    {
        $dir = public_path('assets/uploads/banners');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $name = $prefix.time().'_'.uniqid().'.'.($file->extension() ?: 'png');
        $file->move($dir, $name);

        return 'assets/uploads/banners/'.$name;
    }

    /** يحذف ملف صورة ما لم يستعمله بانرٌ آخر (النسخ يشارك الأصل صورتَه). */
    private function deleteBannerImage(?string $path, int $exceptId): void
    {
        if (! $path) {
            return;
        }

        $shared = StoreBanner::where('id', '!=', $exceptId)
            ->where(fn ($q) => $q->where('image', $path)->orWhere('mobile_image', $path))
            ->exists();

        if (! $shared && file_exists(public_path($path))) {
            @unlink(public_path($path));
        }
    }

    /**
     * عرض قائمة البانرات
     */
    public function index(Request $request)
    {
        // `?search[]=x` في الرابط يصل مصفوفةً والقالب يطبعه نصّاً فيسقط بـ500
        $this->dropNonScalarFilters($request, array_keys($request->query()));

        $query = $this->scopedBanners();

        // البحث
        if ($request->filled('search') && is_string($request->search)) {
            $query->search($request->search);
        }

        // فلترة حسب الموقع
        if (is_string($request->position) && BannerPosition::tryFrom($request->position)) {
            $query->where('position', $request->position);
        }

        // فلترة حسب الحالة
        if ($request->filled('status')) {
            if ($request->status === 'active') {
                $query->active();
            } elseif ($request->status === 'inactive') {
                $query->where('is_active', false);
            } elseif ($request->status === 'expired') {
                $query->where('end_date', '<', now());
            }
        }

        // فلترة حسب نوع الرابط
        if (is_string($request->link_type) && BannerLinkType::tryFrom($request->link_type)) {
            $query->where('link_type', $request->link_type);
        }

        $banners = $query->ordered()->paginate(15)->withQueryString();

        // الإحصائيات — بنطاق القائمة نفسه (كانت على كل الفروع والجدول على فرعٍ واحد)
        $stats = [
            'total' => $this->scopedBanners()->count(),
            'active' => $this->scopedBanners()->active()->count(),
            'valid' => $this->scopedBanners()->valid()->count(),
            'expired' => $this->scopedBanners()->where('end_date', '<', now())->count(),
        ];

        // القوائم للفلترة
        $positions = BannerPosition::cases();
        $linkTypes = BannerLinkType::cases();

        return view('onlinestore::banners.index', compact('banners', 'stats', 'positions', 'linkTypes'));
    }

    /**
     * عرض صفحة إنشاء بانر جديد
     */
    public function create()
    {
        $positions = BannerPosition::cases();
        $linkTypes = BannerLinkType::cases();
        $categories = Category::get();
        $products = Product::active()->availableOnline()->get(['id', 'name']);
        $brands = Brand::active()->get(['id', 'name']);

        return view('onlinestore::banners.create', compact('positions', 'linkTypes', 'categories', 'products', 'brands'));
    }

    /**
     * حفظ بانر جديد
     */
    public function store(Request $request)
    {
        $validated = $request->validate($this->rules(true), [], self::ATTRIBUTES);
        $this->assertLinkTarget($validated);

        $validated['image'] = $this->storeBannerImage($request->file('image'), 'banner_');

        if ($request->hasFile('mobile_image')) {
            $validated['mobile_image'] = $this->storeBannerImage($request->file('mobile_image'), 'banner_mobile_');
        }

        $validated['is_active'] = $request->boolean('is_active');
        $validated['sort_order'] = $validated['sort_order'] ?? 0;
        // يُختم بالفرع النشط لمن أنشأه — كان يُختم بالفرع الرئيسي دائماً
        $validated['branch_id'] = current_branch_id() ?: null;

        $banner = StoreBanner::create($validated);

        OperationLog::record('created', 'banner', $banner, 'تم إنشاء بانر **'.$banner->title.'**');

        return redirect()->route('store_banners.index')
            ->with('success', 'تم إضافة البانر بنجاح');
    }

    /**
     * عرض تفاصيل بانر
     */
    public function show(StoreBanner $storeBanner)
    {
        $this->ensureInScope($storeBanner);

        return view('onlinestore::banners.show', compact('storeBanner'));
    }

    /**
     * عرض صفحة تعديل بانر
     */
    public function edit(StoreBanner $storeBanner)
    {
        $this->ensureInScope($storeBanner);

        $positions = BannerPosition::cases();
        $linkTypes = BannerLinkType::cases();
        $categories = Category::get();
        $products = Product::active()->availableOnline()->get(['id', 'name']);
        $brands = Brand::active()->get(['id', 'name']);

        return view('onlinestore::banners.edit', compact('storeBanner', 'positions', 'linkTypes', 'categories', 'products', 'brands'));
    }

    /**
     * تحديث بانر
     */
    public function update(Request $request, StoreBanner $storeBanner)
    {
        $this->ensureInScope($storeBanner);

        $validated = $request->validate($this->rules(false), [], self::ATTRIBUTES);
        $this->assertLinkTarget($validated);

        // صورة جديدة تستبدل القديمة؛ بلا رفعٍ تبقى القديمة (لا يُكتب null فوقها)
        unset($validated['image'], $validated['mobile_image']);

        if ($request->hasFile('image')) {
            $old = $storeBanner->image;
            $validated['image'] = $this->storeBannerImage($request->file('image'), 'banner_');
            $this->deleteBannerImage($old, $storeBanner->id);
        }

        if ($request->hasFile('mobile_image')) {
            $old = $storeBanner->mobile_image;
            $validated['mobile_image'] = $this->storeBannerImage($request->file('mobile_image'), 'banner_mobile_');
            $this->deleteBannerImage($old, $storeBanner->id);
        }

        $validated['is_active'] = $request->boolean('is_active');
        if (! array_key_exists('sort_order', $validated) || $validated['sort_order'] === null) {
            $validated['sort_order'] = $storeBanner->sort_order ?? 0;
        }

        $storeBanner->update($validated);

        OperationLog::record('updated', 'banner', $storeBanner, 'تم تعديل بانر **'.$storeBanner->title.'**');

        return redirect()->route('store_banners.index')
            ->with('success', 'تم تحديث البانر بنجاح');
    }

    /**
     * حذف بانر
     */
    public function destroy(StoreBanner $storeBanner)
    {
        $this->ensureInScope($storeBanner);

        // حذف الصور — إلا ما يشاركه بانرٌ منسوخ (كان حذفُ النسخة يمسح صورة الأصل)
        $this->deleteBannerImage($storeBanner->image, $storeBanner->id);
        $this->deleteBannerImage($storeBanner->mobile_image, $storeBanner->id);

        OperationLog::record('deleted', 'banner', $storeBanner, 'تم حذف بانر **'.$storeBanner->title.'**');

        $storeBanner->delete();

        return redirect()->route('store_banners.index')
            ->with('success', 'تم حذف البانر بنجاح');
    }

    /**
     * تبديل حالة التفعيل
     */
    public function toggleStatus(StoreBanner $storeBanner)
    {
        $this->ensureInScope($storeBanner);

        $storeBanner->update(['is_active' => ! $storeBanner->is_active]);

        OperationLog::record('updated', 'banner', $storeBanner, 'تم تغيير حالة بانر **'.$storeBanner->title.'** إلى '.($storeBanner->is_active ? 'مفعّل' : 'غير مفعّل'));

        return response()->json([
            'success' => true,
            'is_active' => $storeBanner->is_active,
            'message' => $storeBanner->is_active ? 'تم تفعيل البانر' : 'تم إيقاف البانر',
        ]);
    }

    /**
     * نسخ بانر
     */
    public function duplicate(StoreBanner $storeBanner)
    {
        $this->ensureInScope($storeBanner);

        $newBanner = $storeBanner->duplicate();

        OperationLog::record('created', 'banner', $newBanner, 'تم نسخ بانر **'.$storeBanner->title.'** إلى نسخة جديدة');

        return redirect()->route('store_banners.edit', $newBanner)
            ->with('success', 'تم نسخ البانر بنجاح - يمكنك تعديله الآن');
    }

    /**
     * تحديث ترتيب البانرات
     */
    public function updateOrder(Request $request)
    {
        $validated = $request->validate([
            'orders' => 'required|array',
            'orders.*.id' => 'required|integer|exists:store_banners,id',
            'orders.*.sort_order' => 'required|integer|min:0',
        ], [], self::ATTRIBUTES);

        foreach ($validated['orders'] as $order) {
            $this->scopedBanners()->where('id', $order['id'])->update(['sort_order' => $order['sort_order']]);
        }

        OperationLog::record('updated', 'banner', null, 'تم تحديث ترتيب البانرات');

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث الترتيب بنجاح',
        ]);
    }

    /**
     * حذف صورة الموبايل
     */
    public function removeMobileImage(StoreBanner $storeBanner)
    {
        $this->ensureInScope($storeBanner);

        $this->deleteBannerImage($storeBanner->mobile_image, $storeBanner->id);

        $storeBanner->update(['mobile_image' => null]);

        OperationLog::record('updated', 'banner', $storeBanner, 'تم حذف صورة الجوال لبانر **'.$storeBanner->title.'**');

        return response()->json([
            'success' => true,
            'message' => 'تم حذف صورة الموبايل',
        ]);
    }
}
