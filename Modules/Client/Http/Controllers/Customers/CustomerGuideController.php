<?php

namespace Modules\Client\Http\Controllers\Customers;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\CategoriesClient;
use App\Models\Client;
use App\Models\Neighborhood;
use App\Models\Statuses;
use App\Models\User;
use App\Support\DataScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CustomerGuideController extends Controller
{
    use \Modules\Client\Http\Controllers\Concerns\FiltersCustomerReports;

    public function __construct()
    {
        /* صلاحيةُ هذا التقرير وحدَه.

           كانت التركيبةُ الثلاثية `clients_view_all_reports|clients_view_own_reports|client_reports_view`
           مكرَّرةً حرفياً في التسعة، فمن فُتح له تقريرٌ انفتحت له
           التسعةُ جميعاً. واثنتان منها صلاحيتا **نطاق** لا عملية،
           وقد صار النطاقُ محوراً مستقلاً يقرؤه `App\Support\DataScope`. */
        $this->middleware('permission:clients.reports.guide');
    }

    /**
     * عرض صفحة دليل العملاء
     */
    public function customerGuide(Request $request)
    {
        /* لم تعد تُحمَّل: قائمةُ العملاء كاملةً (٣٨٥٢ نموذجاً لصفحةٍ صارت
           تبحث عنهم)، ولا قائمتا «الفرع» و«المجموعة» — حلّ محلَّهما مُنتقي
           «تابع لـ» المشترك. */
        $categories = CategoriesClient::all();
        $neighborhoods = Neighborhood::all();
        $statuses = Statuses::assignable()->get(); // حالات العميل القابلة للاختيار فقط — نشط/موقوف، بلا مراحل التسجيل ولا حالات قديمة
        $salesManagers = User::whereIn('role', ['employee', 'manager'])->get();

        // إذا كان الطلب AJAX، إرجاع البيانات فقط
        if ($request->ajax()) {
            return $this->getReportDataCustomerGuide($request);
        }

        /* النتيجةُ تُبنى هنا لا بطلبٍ ثانٍ بعد التحميل — كما في تقريرَي
           أعمار الديون: رابطُ الصفحة يحمل ما يُعرض فيه. */
        $report = $this->getReportDataCustomerGuide($request)->getData(true);

        // عرض الصفحة مع البيانات الأساسية
        return view('client::customers.CustomerReport.customer_guide', [
            'reportRows' => $report['data'] ?? [],
            'totals' => $report['totals'] ?? [],
            /* نفسُ فلاتر بقيّة تقارير العملاء ومن مصادرها نفسِها: «تابع لـ»
               شجرةً واحدة، والعميلُ بالبحث لا بقائمةٍ من ٣٨٥٢ عنصراً،
               ومسؤولُ المبيعات من أقسام إعدادات وحدات العملاء. */
            'treeNodes' => $this->affiliationTreeNodes(),
            'salesEmployees' => $this->salesEmployeeOptions(),
            'registrationStages' => $this->registrationStageOptions(),
            'customerTargets' => $this->customerTargetOptions(),
            'selectedClient' => $request->filled('customer')
                ? Client::find($request->input('customer'))
                : null,

            'categories' => $categories,
            'neighborhoods' => $neighborhoods,
            'statuses' => $statuses,
            'salesManagers' => $salesManagers,
        ]);
    }

    /**
     * إرجاع بيانات التقرير عبر AJAX
     */
    public function customerGuideAjax(Request $request)
    {
        return $this->getReportDataCustomerGuide($request);
    }

    /**
     * إرجاع بيانات الخريطة للتقرير
     */
    public function customerGuideMapAjax(Request $request)
    {
        try {
            // إعداد استعلام العملاء مع العلاقات اللازمة
            $query = Client::with(['locations', 'branch', 'categoriesClient', 'neighborhood.Region', 'status_client:id,name,color']);

            // تطبيق نفس الفلاتر
            if ($request->filled('customer') && $request->customer !== '') {
                $query->where('id', $request->customer);
            }

            /* ══════════ فلاتر العميل ══════════
               تعريفٌ واحدٌ مع بقيّة تقارير العملاء — انظر التعليق في
               `getReportDataCustomerGuide()`. */
            $this->applyClientFilters($query, $request);

            if ($request->filled('city')) {
                $query->where('city', 'like', '%'.$request->input('city').'%');
            }

            if ($request->filled('country')) {
                $query->where('country', 'like', '%'.$request->input('country').'%');
            }

            // الحصول على العملاء مع المواقع فقط
            $clients = $query->whereHas('locations')->get();

            // تنسيق البيانات للخريطة
            $clientsForMap = [];

            foreach ($clients as $client) {
                try {
                    if ($client->locations) {
                        // التحقق من أن branch هي object وليس boolean
                        /* `property_exists()` لا يرى سماتِ نموذج Eloquent (هي في
                           مصفوفةٍ داخلية لا خصائصَ للصنف)، فكان يعيد false
                           دائماً: الفرعُ «-» والحالةُ بلونٍ افتراضيّ لكلّ عميل. */
                        $branch_name = $client->branch?->name ?: '-';
                        $status_name = $client->status_client?->name ?: '—';
                        $status_color = $client->status_client?->color ?: '#4361ee';

                        $clientsForMap[] = [
                            'id' => $client->id,
                            'code' => $client->code,
                            'trade_name' => $client->trade_name,
                            'email' => $client->email,
                            'phone' => $client->phone,
                            'city' => $client->city,
                            'country' => $client->country,
                            'lat' => (float) $client->locations->latitude,
                            'lng' => (float) $client->locations->longitude,
                            'branch_name' => $branch_name,
                            'status' => $status_name,
                            'statusColor' => $status_color,
                        ];
                    }
                } catch (\Exception $clientError) {
                    Log::warning('Error processing client '.$client->id.': '.$clientError->getMessage());

                    continue;
                }
            }

            return response()->json([
                'success' => true,
                'clients' => $clientsForMap,
                'count' => count($clientsForMap),
            ]);
        } catch (\Exception $e) {
            Log::error('Error in customerGuideMapAjax: '.$e->getMessage());

            return response()->json(
                [
                    'success' => false,
                    'message' => 'خطأ في تحميل بيانات الخريطة',
                    'error' => $e->getMessage(),
                ],
                500,
            );
        }
    }

    /**
     * دالة جلب بيانات التقرير
     */
    private function getReportDataCustomerGuide(Request $request)
    {
        try {
            // إعداد استعلام العملاء مع العلاقات اللازمة
            $query = Client::with([
                'locations',
                'branch',
                'categoriesClient',
                'neighborhood.region',
                'status_client:id,name,color',
            ]);

            // تطبيق الفلاتر
            if ($request->filled('customer') && $request->customer !== '') {
                $query->where('id', $request->customer);
            }

            /* ══════════ فلاتر العميل ══════════

               تعريفٌ واحدٌ مع بقيّة تقارير العملاء (`applyClientFilters`).

               كانت هنا نسخةٌ خامسةٌ منها، وفيها ثلاثةُ فروقٍ تُخرج أرقاماً
               مختلفةً عن أخواتها للسؤال نفسِه:

                 • «الفرع» و«المجموعة» يطابقان `clients.branch_id` والحيَّ —
                   والأولُ هو الفرعُ المحاسبيُّ المشتقُّ آلياً لا تبعيّةُ
                   العميل، فعميلُ المنطقة يُنسب إلى الفرع الذي فوقها.
                 • قاعدةٌ خفيّة (`hasHierarchyFilter`) تُبطل أحدَ المنتقيَين
                   صامتاً، فيرى المستخدم فرعاً مختاراً ولا أثرَ له في النتيجة.
                 • «مسؤول المبيعات» يترجم مستخدماً إلى موظّف، فيسقط كلُّ
                   مندوبٍ بلا حساب دخول.

               و«وضع العميل» و«هدفه» يصلان الشاشةَ بهذا التوحيد ولم يكونا
               فيها أصلاً. */
            $this->applyClientFilters($query, $request);

            if ($request->filled('city')) {
                $query->where('city', 'like', '%'.$request->input('city').'%');
            }

            if ($request->filled('country')) {
                $query->where('country', 'like', '%'.$request->input('country').'%');
            }

            // فلترة حسب حالة الموقع (مع موقع / بدون موقع)
            if ($request->filled('location_status') && $request->location_status !== '') {
                if ($request->location_status === 'with_location') {
                    // العملاء الذين لديهم موقع في جدول locations
                    $query->whereHas('locations', function ($locQuery) {
                        $locQuery->whereNotNull('latitude')
                            ->whereNotNull('longitude')
                            ->where('latitude', '!=', '')
                            ->where('longitude', '!=', '');
                    });
                } elseif ($request->location_status === 'without_location') {
                    // العملاء الذين ليس لديهم موقع في جدول locations
                    $query->where(function ($q) {
                        $q->whereDoesntHave('locations')
                            ->orWhereHas('locations', function ($locQuery) {
                                $locQuery->where(function ($inner) {
                                    $inner->whereNull('latitude')
                                        ->orWhereNull('longitude')
                                        ->orWhere('latitude', '')
                                        ->orWhere('longitude', '');
                                });
                            });
                    });
                }
            }

            // الحصول على العملاء المفلترين
            $clients = $query->get();

            // معالجة البيانات للعرض
            $processedClients = $clients->map(function ($client) {
                // الحصول على بيانات الحي والمجموعة
                /* القراءاتُ أدناه بـ`?->` لا بـ`property_exists()`: الأخيرةُ لا
                   ترى سماتِ نماذج Eloquent فكانت تعيد false دائماً — فالفرعُ
                   والتصنيفُ والحيُّ والمجموعةُ فارغةٌ لكلّ العملاء الـ٣٨٥٢،
                   والتجميعُ بها ينهار كلُّه في «غير محدد». */
                $neighborhood = $client->neighborhood;
                $regionGroup = $neighborhood?->region;

                // الحصول على بيانات الموقع
                $locations = $client->locations;
                $latitude = null;
                $longitude = null;
                $phone = null;

                if ($locations) {
                    $latitude = $locations->latitude;
                    $longitude = $locations->longitude;
                    $phone = $locations->phone ?? $client->phone;
                } else {
                    $phone = $client->phone;
                }

                $branch_name = $client->branch?->name;
                $category_name = $client->categoriesClient?->name;
                $neighborhood_name = $neighborhood?->name;
                $region_group_name = $regionGroup?->name;

                return [
                    'id' => $client->id,
                    'code' => $client->code,
                    'trade_name' => $client->trade_name,
                    'first_name' => $client->first_name,
                    'email' => $client->email,
                    'phone' => $phone ?: $client->mobile,
                    'city' => $client->city,
                    'country' => $client->country,
                    'region' => $client->region,
                    'branch_name' => $branch_name,
                    'category_name' => $category_name,
                    'neighborhood' => $neighborhood_name,
                    'region_group' => $region_group_name,
                    'status_name' => ($client->status_client && is_object($client->status_client)) ? $client->status_client->name : 'غير محدد',
                    'status_color' => ($client->status_client && is_object($client->status_client)) ? ($client->status_client->color ?? '#6c757d') : '#6c757d',
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'status' => $client->status_client ?? 'active',
                ];
            });

            // تجميع البيانات حسب الخيار المحدد
            $groupBy = $request->get('group_by', 'العميل');
            $groupedClients = $this->groupClientsByType($processedClients, $groupBy);

            // حساب الإجماليات
            $clientsWithLocations = $clients->filter(function ($client) {
                return $client->locations !== null;
            })->count();

            // هاتفٌ أو جوّال — كان الجوّالُ وحدَه يُسقط ٥٧٠ عميلاً من «جهات الاتصال»
            $clientsWithPhone = $clients->filter(function ($client) {
                return ! empty($client->phone) || ! empty($client->mobile);
            })->count();

            $totals = [
                'total_clients' => $clients->count(),
                'total_customers' => $clients->count(),
                'clients_with_locations' => $clientsWithLocations,
                'total_locations' => $clientsWithLocations,
                'total_contacts' => $clientsWithPhone,
                'total_notes' => 0,
                /* عددُ ما يغطّيه **العملاء المعروضون** لا جداولُ المنشأة كلُّها:
                   كانت ١٦٧ فرعاً لمن لا يرى إلّا فرعاً واحداً. */
                'total_branches' => $clients->pluck('branch_id')->filter()->unique()->count(),
                'total_neighborhoods' => $clients->map(fn ($client) => $client->neighborhood?->id)->filter()->unique()->count(),
            ];

            // إعداد الاستجابة
            $response = [
                'success' => true,
                'data' => $processedClients->values()->all(),
                'clients' => $processedClients->values()->all(),
                'grouped_clients' => $groupedClients,
                'totals' => $totals,
                'group_by' => $groupBy,
                'records_count' => $processedClients->count(),
                'from_date' => $request->from_date ?? now()->startOfMonth()->format('Y-m-d'),
                'to_date' => $request->to_date ?? now()->format('Y-m-d'),
                'filters_applied' => [
                    'customer' => $request->filled('customer'),
                    'branch' => $request->filled('branch'),
                    'customer_type' => $request->filled('customer_type'),
                    'region_group' => $request->filled('region_group'),
                    'neighborhood' => $request->filled('neighborhood'),
                    'city' => $request->filled('city'),
                    'country' => $request->filled('country'),
                ],
            ];

            return response()->json($response);
        } catch (\Exception $e) {
            Log::error('Error in getReportDataCustomerGuide: '.$e->getMessage());

            return response()->json(
                [
                    'success' => false,
                    'message' => 'خطأ في تحميل البيانات',
                    'error' => $e->getMessage(),
                ],
                500,
            );
        }
    }

    /**
     * تجميع العملاء حسب النوع المحدد
     */
    private function groupClientsByType($clients, $groupBy)
    {
        $grouped = [];

        foreach ($clients as $client) {
            $groupKey = '';

            switch ($groupBy) {
                case 'الفرع':
                    $groupKey = $client['branch_name'] ?: 'غير محدد';
                    break;
                case 'المدينة':
                    $groupKey = $client['city'] ?: 'غير محدد';
                    break;
                case 'المجموعة':
                    $groupKey = $client['region_group'] ?: 'غير محدد';
                    break;
                case 'الحي':
                    $groupKey = $client['neighborhood'] ?: 'غير محدد';
                    break;
                default:
                    // العميل
                    $groupKey = $client['trade_name'] ?: 'غير محدد';
                    break;
            }

            if (! isset($grouped[$groupKey])) {
                $grouped[$groupKey] = [];
            }

            $grouped[$groupKey][] = $client;
        }

        return $grouped;
    }

    /**
     * جلب الأحياء حسب المجموعة
     */
    public function getNeighborhoods(Request $request)
    {
        $regionGroupId = $request->get('region_group_id');

        if (! $regionGroupId) {
            return response()->json([]);
        }

        $neighborhoods = Neighborhood::where('region_id', $regionGroupId)->select('id', 'name')->get();

        return response()->json($neighborhoods);
    }

    /**
     * جلب الفروع الفرعية ضمن شجرة الفرع المحدد (بديلاً عن المجموعات)
     */
    public function getRegionGroups(Request $request)
    {
        $branchId = $request->get('branch_id');

        if (! $branchId) {
            return response()->json([]);
        }

        $branch = Branch::withoutGlobalScopes()->find((int) $branchId);
        if (! $branch) {
            return response()->json([]);
        }

        $treeIds = $branch->descendantBranchIds()
            ->push((int) $branch->id)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $branches = DataScope::branchOptions(
            Branch::withoutGlobalScopes()
                ->whereIn('id', $treeIds)
                ->where('id', '!=', (int) $branch->id)
                ->orderBy('name')
        );

        $result = $branches->map(function ($b) {
            return [
                'id' => $b->id,
                'name' => $b->lineagePathLabel(' ← '),
            ];
        });

        return response()->json($result);
    }

    /**
     * جلب المدن
     */
    public function getCities(Request $request)
    {
        $query = Client::with([
            'locations',
            'branch',
            'categoriesClient',
            'neighborhood.region',
        ]);

        // فلترة حسب الفرع إذا تم تحديده
        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        // فلترة حسب الفرع وشجرته (بديلاً عن المجموعة)
        if ($request->filled('region_group_id')) {
            $branch = Branch::withoutGlobalScopes()->find((int) $request->region_group_id);
            if ($branch) {
                $treeIds = $branch->descendantBranchIds()
                    ->push((int) $branch->id)
                    ->map(fn ($id) => (int) $id)
                    ->unique()
                    ->values()
                    ->all();
                $query->where(function ($w) use ($treeIds) {
                    $w->whereIn('branch_id', $treeIds)
                        ->orWhereHas('neighborhood', function ($nq) use ($treeIds) {
                            $nq->whereIn('region_id', $treeIds);
                        });
                });
            }
        }

        // قائمةُ المدن تتبع نطاقَ الرؤية كبقيّة الشاشة — وإلّا أفشت
        // للمندوب خريطةَ انتشار المنشأة كلَّها من منسدلةٍ واحدة.
        $this->applyClientVisibilityScope($query);

        $cities = $query->pluck('city')->values();

        return response()->json($cities);
    }
}
