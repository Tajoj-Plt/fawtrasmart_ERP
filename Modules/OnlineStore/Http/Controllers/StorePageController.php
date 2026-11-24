<?php

namespace Modules\OnlineStore\Http\Controllers;

use App\Enums\StorePageType;
use App\Models\Store\StorePage;
use App\Support\OperationLog;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Modules\OnlineStore\Http\Controllers\Concerns\SanitizesFilters;

/**
 * Controller لإدارة صفحات المتجر
 */
class StorePageController extends Controller
{
    use SanitizesFilters;

    public function __construct()
    {
        $this->middleware('permission.role:online_store.pages.view', ['only' => ['index', 'show', 'preview', 'byType']]);
        $this->middleware('permission.role:online_store.pages.create', ['only' => ['create', 'store', 'duplicate']]);
        $this->middleware('permission.role:online_store.pages.edit', ['only' => ['edit', 'update', 'updateOrder']]);
        $this->middleware('permission.role:online_store.pages.delete', ['only' => ['destroy', 'bulkDelete']]);
        $this->middleware('permission.role:online_store.pages.toggle_status', ['only' => ['toggleStatus', 'bulkActivate', 'bulkDeactivate']]);
    }

    /** أسماء الحقول بالعربية في رسائل التحقق. */
    private const ATTRIBUTES = [
        'title' => 'عنوان الصفحة',
        'slug' => 'الرابط (Slug)',
        'content' => 'محتوى الصفحة',
        'content_en' => 'المحتوى بالإنجليزية',
        'type' => 'نوع الصفحة',
        'meta_title' => 'عنوان SEO',
        'meta_description' => 'وصف SEO',
        'is_active' => 'حالة النشر',
        'sort_order' => 'الترتيب',
        'ids' => 'الصفحات المحددة',
        'ids.*' => 'الصفحة المحددة',
        'pages' => 'الصفحات',
        'pages.*.id' => 'الصفحة',
        'pages.*.sort_order' => 'رقم الترتيب',
    ];

    /**
     * عرض قائمة الصفحات
     */
    public function index(Request $request)
    {
        // `?search[]=x` في الرابط يصل مصفوفةً والقالب يطبعه نصّاً فيسقط بـ500
        $this->dropNonScalarFilters($request, array_keys($request->query()));

        $query = StorePage::query();

        // البحث
        if ($request->filled('search') && is_string($request->search)) {
            $query->search($request->search);
        }

        // فلترة حسب النوع
        if (is_string($request->type) && StorePageType::tryFrom($request->type)) {
            $query->where('type', $request->type);
        }

        // فلترة حسب الحالة
        if ($request->filled('status')) {
            if ($request->status === 'active') {
                $query->where('is_active', true);
            } elseif ($request->status === 'inactive') {
                $query->where('is_active', false);
            }
        }

        $pages = $query->ordered()->paginate(15)->withQueryString();

        // الإحصائيات
        $stats = [
            'total' => StorePage::count(),
            'active' => StorePage::active()->count(),
            'inactive' => StorePage::where('is_active', false)->count(),
            'pages' => StorePage::where('type', StorePageType::PAGE)->count(),
            'policies' => StorePage::where('type', StorePageType::POLICY)->count(),
            'faqs' => StorePage::where('type', StorePageType::FAQ)->count(),
        ];

        return view('onlinestore::pages.index', compact('pages', 'stats'));
    }

    /**
     * عرض نموذج إنشاء صفحة جديدة
     */
    public function create()
    {
        $types = StorePageType::cases();

        return view('onlinestore::pages.create', compact('types'));
    }

    /**
     * حفظ صفحة جديدة
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:store_pages,slug',
            'content' => 'required|string',
            'content_en' => 'nullable|string',
            'type' => 'required|in:page,policy,faq',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
            'is_active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0',
        ], [], self::ATTRIBUTES);

        $validated['is_active'] = $request->boolean('is_active');
        $validated['sort_order'] = $validated['sort_order'] ?? 0;

        $page = StorePage::create($validated);

        OperationLog::record('created', 'page', $page, 'تم إنشاء صفحة **'.$page->title.'**');

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'تم إنشاء الصفحة بنجاح',
                'page' => $page,
            ]);
        }

        return redirect()->route('store_pages.index')
            ->with('success', 'تم إنشاء الصفحة بنجاح');
    }

    /**
     * عرض تفاصيل صفحة
     */
    public function show(StorePage $storePage)
    {
        return view('onlinestore::pages.show', compact('storePage'));
    }

    /**
     * عرض نموذج تعديل صفحة
     */
    public function edit(StorePage $storePage)
    {
        $types = StorePageType::cases();

        return view('onlinestore::pages.edit', compact('storePage', 'types'));
    }

    /**
     * تحديث صفحة
     */
    public function update(Request $request, StorePage $storePage)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:store_pages,slug,'.$storePage->id,
            'content' => 'required|string',
            'content_en' => 'nullable|string',
            'type' => 'required|in:page,policy,faq',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
            'is_active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0',
        ], [], self::ATTRIBUTES);

        $validated['is_active'] = $request->boolean('is_active');
        $validated['sort_order'] = $validated['sort_order'] ?? $storePage->sort_order;

        $storePage->update($validated);

        OperationLog::record('updated', 'page', $storePage, 'تم تعديل صفحة **'.$storePage->title.'**');

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'تم تحديث الصفحة بنجاح',
                'page' => $storePage,
            ]);
        }

        return redirect()->route('store_pages.index')
            ->with('success', 'تم تحديث الصفحة بنجاح');
    }

    /**
     * حذف صفحة
     */
    public function destroy(StorePage $storePage)
    {
        OperationLog::record('deleted', 'page', $storePage, 'تم حذف صفحة **'.$storePage->title.'**');

        $storePage->delete();

        if (request()->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'تم حذف الصفحة بنجاح',
            ]);
        }

        return redirect()->route('store_pages.index')
            ->with('success', 'تم حذف الصفحة بنجاح');
    }

    /**
     * تبديل حالة الصفحة (نشط/غير نشط)
     */
    public function toggleStatus(StorePage $storePage)
    {
        $storePage->update(['is_active' => ! $storePage->is_active]);

        OperationLog::record('updated', 'page', $storePage, 'تم تغيير حالة صفحة **'.$storePage->title.'** إلى '.($storePage->is_active ? 'مفعّلة' : 'غير مفعّلة'));

        return response()->json([
            'success' => true,
            'message' => $storePage->is_active ? 'تم تفعيل الصفحة' : 'تم إلغاء تفعيل الصفحة',
            'is_active' => $storePage->is_active,
        ]);
    }

    /**
     * نسخ صفحة
     */
    public function duplicate(StorePage $storePage)
    {
        try {
            $newPage = $storePage->duplicate();

            OperationLog::record('created', 'page', $newPage, 'تم نسخ صفحة **'.$storePage->title.'** إلى نسخة جديدة');

            return response()->json([
                'success' => true,
                'message' => 'تم نسخ الصفحة بنجاح',
                'page' => $newPage,
            ]);
        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'OnlineStore.StorePage', 'duplicate');

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء نسخ الصفحة',
            ], 500);
        }
    }

    /**
     * حذف عدة صفحات
     */
    public function bulkDelete(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:store_pages,id',
        ], [], self::ATTRIBUTES);

        StorePage::whereIn('id', $validated['ids'])->delete();

        OperationLog::record('deleted', 'page', null, 'تم حذف '.count($validated['ids']).' صفحة دفعة واحدة');

        return response()->json([
            'success' => true,
            'message' => 'تم حذف الصفحات المحددة بنجاح',
        ]);
    }

    /**
     * تفعيل عدة صفحات
     */
    public function bulkActivate(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:store_pages,id',
        ], [], self::ATTRIBUTES);

        StorePage::whereIn('id', $validated['ids'])->update(['is_active' => true]);

        OperationLog::record('updated', 'page', null, 'تم تفعيل '.count($validated['ids']).' صفحة دفعة واحدة');

        return response()->json([
            'success' => true,
            'message' => 'تم تفعيل الصفحات المحددة',
        ]);
    }

    /**
     * إلغاء تفعيل عدة صفحات
     */
    public function bulkDeactivate(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:store_pages,id',
        ], [], self::ATTRIBUTES);

        StorePage::whereIn('id', $validated['ids'])->update(['is_active' => false]);

        OperationLog::record('updated', 'page', null, 'تم تعطيل '.count($validated['ids']).' صفحة دفعة واحدة');

        return response()->json([
            'success' => true,
            'message' => 'تم إلغاء تفعيل الصفحات المحددة',
        ]);
    }

    /**
     * تحديث ترتيب الصفحات
     */
    public function updateOrder(Request $request)
    {
        $validated = $request->validate([
            'pages' => 'required|array',
            'pages.*.id' => 'required|integer|exists:store_pages,id',
            'pages.*.sort_order' => 'required|integer|min:0',
        ], [], self::ATTRIBUTES);

        try {
            DB::beginTransaction();

            foreach ($validated['pages'] as $pageData) {
                StorePage::where('id', $pageData['id'])
                    ->update(['sort_order' => $pageData['sort_order']]);
            }

            OperationLog::record('updated', 'page', null, 'تم تحديث ترتيب الصفحات');

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'تم تحديث الترتيب بنجاح',
            ]);
        } catch (\Exception $e) {
            // ⚠️ rollBack() قبل failure() لا بعده — التسجيل يكتب عبر نفس
            // اتصال قاعدة البيانات، فيُلغى سجلّه هو نفسه مع المعاملة إن كُتب
            // قبل انتهائها (تحقّقتُ من هذا مباشرةً على قاعدة محلية)
            DB::rollBack();

            OperationLog::failure($e, 'OnlineStore.StorePage', 'updateOrder');

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء تحديث الترتيب',
            ], 500);
        }
    }

    /**
     * معاينة الصفحة
     */
    public function preview(StorePage $storePage)
    {
        return view('onlinestore::pages.preview', compact('storePage'));
    }

    /**
     * صفحات حسب النوع
     */
    public function byType($type)
    {
        try {
            $pageType = StorePageType::from($type);
            $pages = StorePage::byType($pageType)->ordered()->paginate(15);
            $stats = [
                'total' => $pages->total(),
                'active' => StorePage::byType($pageType)->active()->count(),
            ];

            return view('onlinestore::pages.by-type', compact('pages', 'stats', 'pageType'));
        } catch (\ValueError $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'OnlineStore.StorePage', 'byType');

            abort(404);
        }
    }
}
