<?php

namespace Modules\OnlineStore\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Support\OperationLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\OnlineStore\Http\Controllers\Concerns\SanitizesFilters;

class CountryController extends Controller
{
    use SanitizesFilters;

    public function __construct()
    {
        $this->middleware('permission.role:online_store.geo.view', ['only' => ['index', 'show', 'getCities']]);
        $this->middleware('permission.role:online_store.geo.create', ['only' => ['create', 'store']]);
        $this->middleware('permission.role:online_store.geo.edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission.role:online_store.geo.delete', ['only' => ['destroy']]);
        $this->middleware('permission.role:online_store.geo.toggle_status', ['only' => ['toggleStatus', 'toggleDefault', 'bulkActivate', 'bulkDeactivate']]);
    }

    /**
     * Display a listing of countries.
     */
    /** أسماء الحقول بالعربية في رسائل التحقق. */
    private const ATTRIBUTES = [
        'name' => 'اسم الدولة',
        'code' => 'رمز الدولة',
        'phone_code' => 'مفتاح الاتصال',
        'status' => 'الحالة',
        'is_default' => 'دولة افتراضية',
    ];

    public function index(Request $request)
    {
        // `?search[]=x` في الرابط يصل مصفوفةً والقالب يطبعه نصّاً فيسقط بـ500
        $this->dropNonScalarFilters($request, array_keys($request->query()));

        $query = Country::withCount('cities');

        // البحث
        if ($request->filled('search') && is_string($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('phone_code', 'like', "%{$search}%");
            });
        }

        // فلترة حسب الحالة — 0/1 فقط
        if (in_array($request->status, ['0', '1', 0, 1], true)) {
            $query->where('status', (int) $request->status);
        }

        $countries = $query->latest()->paginate(20)->withQueryString();

        return view('onlinestore::countries.index', compact('countries'));
    }

    /**
     * Show the form for creating a new country.
     */
    public function create()
    {
        return view('onlinestore::countries.create');
    }

    /**
     * Store a newly created country in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:2|unique:countries,code',
            'phone_code' => 'required|string|max:10',
            'status' => 'nullable|boolean',
            'is_default' => 'nullable|boolean',
        ], [], self::ATTRIBUTES);

        // القوائم المنسدلة ترسل 0/1 — `has()` كان يجعل كل دولةٍ جديدة «افتراضية»
        // (وينزع الافتراضية عن غيرها) و«نشطة» مهما اختار المستخدم
        $validated['code'] = strtoupper($validated['code']);
        $validated['status'] = $request->boolean('status', true);
        $validated['is_default'] = $request->boolean('is_default', false);

        // إذا تم تحديد هذه الدولة كافتراضية، إزالة الافتراضية من باقي الدول
        if ($validated['is_default']) {
            Country::where('is_default', true)->update(['is_default' => false]);
        }

        Country::create($validated);

        return redirect()->route('countries.index')
            ->with('success', 'تم إضافة الدولة بنجاح');
    }

    /**
     * Display the specified country.
     */
    public function show(Country $country)
    {
        $country->load(['cities' => function ($query) {
            $query->latest();
        }]);

        $stats = [
            'total_cities' => $country->cities->count(),
            'active_cities' => $country->cities->where('status', true)->count(),
            'inactive_cities' => $country->cities->where('status', false)->count(),
        ];

        return view('onlinestore::countries.show', compact('country', 'stats'));
    }

    /**
     * Show the form for editing the specified country.
     */
    public function edit(Country $country)
    {
        return view('onlinestore::countries.edit', compact('country'));
    }

    /**
     * Update the specified country in storage.
     */
    public function update(Request $request, Country $country)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:2|unique:countries,code,'.$country->id,
            'phone_code' => 'required|string|max:10',
            'status' => 'nullable|boolean',
            'is_default' => 'nullable|boolean',
        ], [], self::ATTRIBUTES);

        // 0/1 من القائمة المنسدلة؛ ما لم يُرسَل يبقى على حاله
        $validated['code'] = strtoupper($validated['code']);
        $validated['status'] = $request->boolean('status', (bool) $country->status);
        $validated['is_default'] = $request->boolean('is_default', (bool) $country->is_default);

        // إذا تم تحديد هذه الدولة كافتراضية، إزالة الافتراضية من باقي الدول
        if ($validated['is_default']) {
            Country::where('id', '!=', $country->id)->where('is_default', true)->update(['is_default' => false]);
        }

        $country->update($validated);

        return redirect()->route('countries.index')
            ->with('success', 'تم تحديث الدولة بنجاح');
    }

    /**
     * Remove the specified country from storage.
     */
    public function destroy(Country $country)
    {
        try {
            // التحقق من وجود مدن مرتبطة
            if ($country->cities()->count() > 0) {
                return redirect()->route('countries.index')
                    ->with('error', 'لا يمكن حذف الدولة لأنها تحتوي على مدن مرتبطة');
            }

            $country->delete();

            return redirect()->route('countries.index')
                ->with('success', 'تم حذف الدولة بنجاح');
        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'OnlineStore.Country', 'destroy');

            return redirect()->route('countries.index')
                ->with('error', 'حدث خطأ أثناء حذف الدولة');
        }
    }

    /**
     * Toggle country status.
     */
    public function toggleStatus(Country $country)
    {
        $country->update(['status' => ! $country->status]);

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث حالة الدولة بنجاح',
            'status' => $country->status,
        ]);
    }

    /**
     * Get cities for a specific country (AJAX).
     */
    public function getCities(Country $country)
    {
        $cities = $country->cities()->where('status', true)->get();

        return response()->json([
            'success' => true,
            'cities' => $cities,
        ]);
    }

    /**
     * Toggle default status for a country.
     */
    public function toggleDefault(Country $country)
    {
        try {
            DB::beginTransaction();

            if (! $country->is_default) {
                // إلغاء تفعيل جميع الدول الأخرى كافتراضية
                Country::where('is_default', true)->update(['is_default' => false]);

                // تفعيل الدولة الحالية كافتراضية
                $country->update(['is_default' => true]);

                $message = 'تم تعيين '.$country->name.' كدولة افتراضية';
            } else {
                // إلغاء تفعيل الدولة الحالية
                $country->update(['is_default' => false]);

                $message = 'تم إلغاء '.$country->name.' كدولة افتراضية';
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'is_default' => $country->is_default,
                'message' => $message,
            ]);
        } catch (\Exception $e) {
            // ⚠️ rollBack() قبل failure() لا بعده — التسجيل يكتب عبر نفس
            // اتصال قاعدة البيانات، فيُلغى سجلّه هو نفسه مع المعاملة إن كُتب
            // قبل انتهائها (تحقّقتُ من هذا مباشرةً على قاعدة محلية)
            DB::rollBack();

            OperationLog::failure($e, 'OnlineStore.Country', 'toggleDefault');

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء تحديث الدولة الافتراضية',
            ], 500);
        }
    }

    /**
     * Bulk activate countries.
     */
    public function bulkActivate(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:countries,id',
        ], [], ['ids' => 'الدول المحددة', 'ids.*' => 'الدولة المحددة']);

        Country::whereIn('id', $request->ids)->update(['status' => true]);

        return redirect()->route('countries.index')
            ->with('success', 'تم تفعيل الدول المحددة بنجاح');
    }

    /**
     * Bulk deactivate countries.
     */
    public function bulkDeactivate(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:countries,id',
        ], [], ['ids' => 'الدول المحددة', 'ids.*' => 'الدولة المحددة']);

        Country::whereIn('id', $request->ids)->update(['status' => false]);

        return redirect()->route('countries.index')
            ->with('success', 'تم إيقاف الدول المحددة بنجاح');
    }
}
