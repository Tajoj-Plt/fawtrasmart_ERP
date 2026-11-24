<?php

namespace Modules\OnlineStore\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\Country;
use App\Support\OperationLog;
use Illuminate\Http\Request;
use Modules\OnlineStore\Http\Controllers\Concerns\SanitizesFilters;

class CityController extends Controller
{
    use SanitizesFilters;

    public function __construct()
    {
        $this->middleware('permission.role:online_store.geo.view', ['only' => ['index', 'show']]);
        $this->middleware('permission.role:online_store.geo.create', ['only' => ['create', 'store']]);
        $this->middleware('permission.role:online_store.geo.edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission.role:online_store.geo.delete', ['only' => ['destroy']]);
        $this->middleware('permission.role:online_store.geo.toggle_status', ['only' => ['toggleStatus', 'bulkActivate', 'bulkDeactivate']]);
    }

    /**
     * Display a listing of cities.
     */
    public function index(Request $request)
    {
        // `?search[]=x` في الرابط يصل مصفوفةً والقالب يطبعه نصّاً فيسقط بـ500
        $this->dropNonScalarFilters($request, array_keys($request->query()));

        $query = City::with('country');

        // البحث
        if ($request->filled('search') && is_string($request->search)) {
            $search = $request->search;
            $query->where('name', 'like', "%{$search}%");
        }

        // فلترة حسب الدولة
        if ($request->filled('country_id') && is_numeric($request->country_id)) {
            $query->where('country_id', (int) $request->country_id);
        }

        // فلترة حسب الحالة — 0/1 فقط
        if (in_array($request->status, ['0', '1', 0, 1], true)) {
            $query->where('status', (int) $request->status);
        }

        $cities = $query->latest()->paginate(20)->withQueryString();
        $countries = Country::where('status', true)->orderBy('name')->get();

        return view('onlinestore::cities.index', compact('cities', 'countries'));
    }

    /**
     * Show the form for creating a new city.
     */
    public function create()
    {
        $countries = Country::where('status', true)->orderBy('name')->get();

        return view('onlinestore::cities.create', compact('countries'));
    }

    /**
     * Store a newly created city in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'country_id' => 'required|integer|exists:countries,id',
            'name' => 'required|string|max:255',
            'status' => 'nullable|boolean',
        ], [], ['country_id' => 'الدولة', 'name' => 'اسم المدينة', 'status' => 'الحالة']);

        // القائمة المنسدلة ترسل 0/1 — `has()` كان يجعل «غير نشطة» نشطةً دائماً
        $validated['status'] = $request->boolean('status', true);

        if (City::where('country_id', $validated['country_id'])->where('name', $validated['name'])->exists()) {
            return redirect()->back()->withInput()->withErrors(['name' => 'هذه المدينة مضافة من قبل في الدولة نفسها.']);
        }

        City::create($validated);

        return redirect()->route('cities.index')
            ->with('success', 'تم إضافة المدينة بنجاح');
    }

    /**
     * Display the specified city.
     */
    public function show(City $city)
    {
        $city->load('country');

        return view('onlinestore::cities.show', compact('city'));
    }

    /**
     * Show the form for editing the specified city.
     */
    public function edit(City $city)
    {
        // الدول النشطة + دولة المدينة الحالية وإن عُطّلت — وإلا فرغ الحقل وتعذّر الحفظ
        $countries = Country::where('status', true)->orWhere('id', $city->country_id)->orderBy('name')->get();

        return view('onlinestore::cities.edit', compact('city', 'countries'));
    }

    /**
     * Update the specified city in storage.
     */
    public function update(Request $request, City $city)
    {
        $validated = $request->validate([
            'country_id' => 'required|integer|exists:countries,id',
            'name' => 'required|string|max:255',
            'status' => 'nullable|boolean',
        ], [], ['country_id' => 'الدولة', 'name' => 'اسم المدينة', 'status' => 'الحالة']);

        // القائمة المنسدلة ترسل 0/1 — ما لم يُرسَل يبقى على حاله
        $validated['status'] = $request->boolean('status', (bool) $city->status);

        if (City::where('country_id', $validated['country_id'])->where('name', $validated['name'])->where('id', '!=', $city->id)->exists()) {
            return redirect()->back()->withInput()->withErrors(['name' => 'هذه المدينة مضافة من قبل في الدولة نفسها.']);
        }

        $city->update($validated);

        return redirect()->route('cities.index')
            ->with('success', 'تم تحديث المدينة بنجاح');
    }

    /**
     * Remove the specified city from storage.
     */
    public function destroy(City $city)
    {
        try {
            $city->delete();

            return redirect()->route('cities.index')
                ->with('success', 'تم حذف المدينة بنجاح');
        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'OnlineStore.City', 'destroy');

            return redirect()->route('cities.index')
                ->with('error', 'حدث خطأ أثناء حذف المدينة');
        }
    }

    /**
     * Toggle city status.
     */
    public function toggleStatus(City $city)
    {
        $city->update(['status' => ! $city->status]);

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث حالة المدينة بنجاح',
            'status' => $city->status,
        ]);
    }

    /**
     * Bulk activate cities.
     */
    public function bulkActivate(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:cities,id',
        ], [], ['ids' => 'المدن المحددة', 'ids.*' => 'المدينة المحددة']);

        City::whereIn('id', $request->ids)->update(['status' => true]);

        return redirect()->route('cities.index')
            ->with('success', 'تم تفعيل المدن المحددة بنجاح');
    }

    /**
     * Bulk deactivate cities.
     */
    public function bulkDeactivate(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:cities,id',
        ], [], ['ids' => 'المدن المحددة', 'ids.*' => 'المدينة المحددة']);

        City::whereIn('id', $request->ids)->update(['status' => false]);

        return redirect()->route('cities.index')
            ->with('success', 'تم إيقاف المدن المحددة بنجاح');
    }
}
