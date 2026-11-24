<?php

namespace Modules\OnlineStore\Http\Controllers\Api;

use App\Models\City;
use App\Models\Country;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * API Controller لإدارة المواقع (الدول والمدن)
 */
class LocationController extends Controller
{
    /**
     * جلب جميع الدول مع مدنها
     *
     * @return JsonResponse
     */
    public function index(Request $request)
    {
        $query = Country::with(['cities' => function ($q) use ($request) {
            // فلترة المدن النشطة فقط إذا كان مطلوب
            if ($request->boolean('active_only', true)) {
                $q->where('status', true);
            }
            $q->orderBy('name', 'asc');
        }]);

        // فلترة الدول النشطة فقط
        if ($request->boolean('active_only', true)) {
            $query->where('status', true);
        }

        // البحث في اسم الدولة
        if ($request->filled('search')) {
            $query->where('name', 'like', '%'.$request->search.'%');
        }

        // الترتيب
        $sortBy = $request->input('sort_by', 'name');
        if (! in_array($sortBy, ['id', 'name', 'created_at'], true)) {
            $sortBy = 'name'; // قائمةٌ بيضاء — عمودٌ مجهول كان يُسقط الاستعلام بـ500
        }
        $sortOrder = strtolower((string) $request->input('sort_order', 'asc')) === 'desc' ? 'desc' : 'asc';
        $query->orderBy($sortBy, $sortOrder);

        // جلب البيانات
        $countries = $query->get();

        // تنسيق البيانات
        $data = $countries->map(function ($country) {
            return [
                'id' => $country->id,
                'name' => $country->name,
                'code' => $country->code,
                'phone_code' => $country->phone_code,
                'status' => $country->status,
                'is_default' => $country->is_default ?? false,
                'cities_count' => $country->cities->count(),
                'cities' => $country->cities->map(function ($city) {
                    return [
                        'id' => $city->id,
                        'name' => $city->name,
                        'status' => $city->status,
                    ];
                }),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data,
            'total_countries' => $countries->count(),
            'total_cities' => $countries->sum(fn ($c) => $c->cities->count()),
            'message' => 'تم جلب جميع الدول والمدن بنجاح',
        ]);
    }

    /**
     * جلب جميع الدول
     *
     * @return JsonResponse
     */
    public function getCountries(Request $request)
    {
        $query = Country::query();

        // فلترة الدول النشطة فقط
        if ($request->boolean('active_only', true)) {
            $query->where('status', true);
        }

        // البحث
        if ($request->filled('search')) {
            $query->where('name', 'like', '%'.$request->search.'%');
        }

        // الترتيب
        $sortBy = $request->input('sort_by', 'name');
        if (! in_array($sortBy, ['id', 'name', 'created_at'], true)) {
            $sortBy = 'name'; // قائمةٌ بيضاء — عمودٌ مجهول كان يُسقط الاستعلام بـ500
        }
        $sortOrder = strtolower((string) $request->input('sort_order', 'asc')) === 'desc' ? 'desc' : 'asc';

        // الدولة الافتراضية أولاً
        $query->orderBy('is_default', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // مع عدد المدن
        if ($request->boolean('with_cities_count', false)) {
            $query->withCount('cities');
        }

        // Pagination أو All
        if ($request->boolean('paginate', false)) {
            $perPage = $request->input('per_page', 15);
            $countries = $query->paginate($perPage);
        } else {
            $countries = $query->get();
        }

        return response()->json([
            'success' => true,
            'data' => $countries,
            'message' => 'تم جلب الدول بنجاح',
        ]);
    }

    /**
     * جلب دولة محددة
     *
     * @param  int  $id
     * @return JsonResponse
     */
    public function getCountry($id)
    {
        $country = Country::with('cities')->find($id);

        if (! $country) {
            return response()->json([
                'success' => false,
                'message' => 'الدولة غير موجودة',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $country,
            'message' => 'تم جلب بيانات الدولة بنجاح',
        ]);
    }

    /**
     * جلب جميع المدن
     *
     * @return JsonResponse
     */
    public function getCities(Request $request)
    {
        $query = City::with('country');

        // فلترة المدن النشطة فقط
        if ($request->boolean('active_only', true)) {
            $query->where('status', true);
        }

        // فلترة حسب الدولة
        if ($request->filled('country_id')) {
            $query->where('country_id', $request->country_id);
        }

        // البحث
        if ($request->filled('search')) {
            $query->where('name', 'like', '%'.$request->search.'%');
        }

        // الترتيب
        $sortBy = $request->input('sort_by', 'name');
        if (! in_array($sortBy, ['id', 'name', 'created_at'], true)) {
            $sortBy = 'name'; // قائمةٌ بيضاء — عمودٌ مجهول كان يُسقط الاستعلام بـ500
        }
        $sortOrder = strtolower((string) $request->input('sort_order', 'asc')) === 'desc' ? 'desc' : 'asc';
        $query->orderBy($sortBy, $sortOrder);

        // Pagination أو All
        if ($request->boolean('paginate', false)) {
            $perPage = $request->input('per_page', 15);
            $cities = $query->paginate($perPage);
        } else {
            $cities = $query->get();
        }

        return response()->json([
            'success' => true,
            'data' => $cities,
            'message' => 'تم جلب المدن بنجاح',
        ]);
    }

    /**
     * جلب مدينة محددة
     *
     * @param  int  $id
     * @return JsonResponse
     */
    public function getCity($id)
    {
        $city = City::with('country')->find($id);

        if (! $city) {
            return response()->json([
                'success' => false,
                'message' => 'المدينة غير موجودة',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $city,
            'message' => 'تم جلب بيانات المدينة بنجاح',
        ]);
    }

    /**
     * جلب المدن حسب الدولة
     *
     * @param  int  $countryId
     * @return JsonResponse
     */
    public function getCitiesByCountry($countryId, Request $request)
    {
        $country = Country::find($countryId);

        if (! $country) {
            return response()->json([
                'success' => false,
                'message' => 'الدولة غير موجودة',
            ], 404);
        }

        $query = City::where('country_id', $countryId);

        // فلترة المدن النشطة فقط
        if ($request->boolean('active_only', true)) {
            $query->where('status', true);
        }

        // البحث
        if ($request->filled('search')) {
            $query->where('name', 'like', '%'.$request->search.'%');
        }

        // الترتيب
        $sortBy = $request->input('sort_by', 'name');
        if (! in_array($sortBy, ['id', 'name', 'created_at'], true)) {
            $sortBy = 'name'; // قائمةٌ بيضاء — عمودٌ مجهول كان يُسقط الاستعلام بـ500
        }
        $sortOrder = strtolower((string) $request->input('sort_order', 'asc')) === 'desc' ? 'desc' : 'asc';
        $query->orderBy($sortBy, $sortOrder);

        $cities = $query->get();

        return response()->json([
            'success' => true,
            'data' => [
                'country' => $country,
                'cities' => $cities,
            ],
            'message' => 'تم جلب مدن '.$country->name.' بنجاح',
        ]);
    }

    /**
     * البحث في الدول والمدن
     *
     * @return JsonResponse
     */
    public function search(Request $request)
    {
        $search = $request->input('search', '');

        if (empty($search)) {
            return response()->json([
                'success' => false,
                'message' => 'يجب إدخال كلمة بحث',
            ], 400);
        }

        $countries = Country::where('status', true)
            ->where('name', 'like', '%'.$search.'%')
            ->limit(10)
            ->get();

        $cities = City::where('status', true)
            ->where('name', 'like', '%'.$search.'%')
            ->with('country')
            ->limit(20)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'countries' => $countries,
                'cities' => $cities,
            ],
            'message' => 'تم البحث بنجاح',
        ]);
    }
}
