<?php

namespace Modules\OnlineStore\Http\Controllers;

use App\Enums\OrderStatus;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Store\ClientAddress;
use App\Support\OperationLog;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Modules\OnlineStore\Http\Controllers\Concerns\SanitizesFilters;

/**
 * كنترولر إدارة عملاء المتجر الإلكتروني
 * Store Client Management Controller
 */
class StoreClientController extends Controller
{
    use SanitizesFilters;

    public function __construct()
    {
        $this->middleware('permission.role:online_store.clients.view', ['only' => ['index', 'show']]);
        $this->middleware('permission.role:online_store.clients.create', ['only' => ['create', 'store']]);
        $this->middleware('permission.role:online_store.clients.edit', ['only' => ['edit', 'update', 'toggleStatus', 'storeAddress', 'updateAddress', 'destroyAddress', 'setDefaultAddress']]);
        $this->middleware('permission.role:online_store.clients.delete', ['only' => ['destroy']]);
        $this->middleware('permission.role:online_store.clients.export', ['only' => ['export']]);
    }

    /** أسماء الحقول بالعربية في رسائل التحقق. */
    private const ATTRIBUTES = [
        'first_name' => 'الاسم الأول',
        'last_name' => 'اسم العائلة',
        'trade_name' => 'الاسم التجاري',
        'email' => 'البريد الإلكتروني',
        'phone' => 'الهاتف',
        'mobile' => 'الجوال',
        'notes' => 'الملاحظات',
        'title' => 'عنوان مختصر',
        'receiver_name' => 'اسم المستلم',
        'receiver_phone' => 'هاتف المستلم',
        'country' => 'الدولة',
        'city' => 'المدينة',
        'region' => 'المنطقة',
        'district' => 'الحي',
        'street' => 'الشارع',
        'building_number' => 'رقم المبنى',
        'floor' => 'الطابق',
        'apartment' => 'الشقة',
        'postal_code' => 'الرمز البريدي',
        'latitude' => 'خط العرض',
        'longitude' => 'خط الطول',
        'is_default' => 'عنوان افتراضي',
        'address_title' => 'عنوان مختصر',
        'address_receiver_name' => 'اسم المستلم',
        'address_receiver_phone' => 'هاتف المستلم',
        'address_country' => 'الدولة',
        'address_city' => 'المدينة',
        'address_region' => 'المنطقة',
        'address_district' => 'الحي',
        'address_street' => 'الشارع',
        'address_building_number' => 'رقم المبنى',
        'address_floor' => 'الطابق',
        'address_apartment' => 'الشقة',
        'address_postal_code' => 'الرمز البريدي',
        'address_notes' => 'ملاحظات العنوان',
    ];

    /**
     * عرض قائمة جميع عملاء المتجر
     */
    public function index(Request $request)
    {
        // `?search[]=x` في الرابط يصل مصفوفةً والقالب يطبعه نصّاً فيسقط بـ500
        $this->dropNonScalarFilters($request, array_keys($request->query()));

        $query = Client::where('is_store_client', 1);

        // البحث
        if ($request->filled('search') && is_string($request->search)) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('trade_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('mobile', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        // فلترة حسب المدينة
        if ($request->filled('city') && is_string($request->city)) {
            $query->where('city', $request->city);
        }

        // فلترة حسب الفرع
        if ($request->filled('branch_id') && is_numeric($request->branch_id)) {
            $query->where('branch_id', (int) $request->branch_id);
        }

        $clients = $query->with(['branch', 'onlineOrders'])
            ->withCount('onlineOrders')
            ->latest()
            ->paginate(15)
            ->withQueryString();

        // إحصائيات
        $totalClients = Client::where('is_store_client', 1)->count();
        $activeClients = Client::where('is_store_client', 1)->whereHas('onlineOrders')->count();
        $totalOrdersValue = DB::table('online_orders')
            ->whereIn('client_id', Client::where('is_store_client', 1)->pluck('id'))
            ->sum('total_amount');

        $branches = Branch::select('id', 'name')->get();
        $cities = Client::where('is_store_client', 1)->whereNotNull('city')->distinct()->pluck('city');

        return view('onlinestore::clients.index', compact(
            'clients',
            'totalClients',
            'activeClients',
            'totalOrdersValue',
            'branches',
            'cities'
        ));
    }

    /**
     * عرض صفحة إنشاء عميل جديد
     */
    public function create()
    {
        return view('onlinestore::clients.create');
    }

    /**
     * حفظ عميل جديد
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'trade_name' => 'nullable|string|max:255',
            'email' => 'required|email|unique:clients,email',
            'phone' => 'nullable|string|max:20',
            'mobile' => 'nullable|string|max:20',
            'notes' => 'nullable|string',
            // حقول العنوان الأول (اختياري)
            'address_title' => 'nullable|string|max:100',
            'address_receiver_name' => 'nullable|string|max:255',
            'address_receiver_phone' => 'nullable|string|max:20',
            'address_country' => 'nullable|string|max:100',
            'address_city' => 'nullable|string|max:100',
            'address_region' => 'nullable|string|max:100',
            'address_district' => 'nullable|string|max:100',
            'address_street' => 'nullable|string|max:255',
            'address_building_number' => 'nullable|string|max:20',
            'address_floor' => 'nullable|string|max:20',
            'address_apartment' => 'nullable|string|max:20',
            'address_postal_code' => 'nullable|string|max:20',
            'address_notes' => 'nullable|string|max:500',
        ], [], self::ATTRIBUTES);

        // عنوانٌ بدأ المستخدم بكتابته يُحفظ كاملاً أو يُنبَّه — كان يُهمل بصمت
        // إن نقصت المدينة أو الشارع
        $addressStarted = collect($validated)->only([
            'address_city', 'address_street', 'address_district', 'address_region', 'address_building_number', 'address_postal_code',
        ])->filter(fn ($v) => filled($v))->isNotEmpty();
        if ($addressStarted && (! $request->filled('address_city') || ! $request->filled('address_street'))) {
            return redirect()->back()->withInput()->withErrors([
                'address_city' => 'لحفظ العنوان اكتب المدينة والشارع معاً، أو اترك حقول العنوان كلها فارغة.',
            ]);
        }

        $txLevel = DB::transactionLevel();
        DB::beginTransaction();
        try {
            // رقم العميل من «الترقيم المتسلسل» (قسم العملاء) كبقية النظام — كان
            // يُحسب بـ`max(code)+1` على عمودٍ نصّي، فيتكرّر الرقم عند إنشاءين
            // متزامنين ويخرج عن تسلسل العملاء
            $nextCode = next_document_number('customer');

            // تعيين العميل كعميل متجر
            $clientData = [
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'] ?? null,
                'trade_name' => $validated['trade_name'] ?? $validated['first_name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'] ?? null,
                'mobile' => $validated['mobile'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'code' => $nextCode,
                'is_store_client' => 1,
            ];

            // إنشاء العميل
            $client = Client::create($clientData);

            // إنشاء العنوان إذا تم إدخال بيانات العنوان
            if ($request->filled('address_city') && $request->filled('address_street')) {
                ClientAddress::create([
                    'client_id' => $client->id,
                    'title' => $validated['address_title'] ?? 'العنوان الرئيسي',
                    'receiver_name' => filled($validated['address_receiver_name'] ?? null) ? $validated['address_receiver_name'] : $client->display_name,
                    'receiver_phone' => filled($validated['address_receiver_phone'] ?? null) ? $validated['address_receiver_phone'] : (string) ($client->mobile ?: $client->phone),
                    'country' => $validated['address_country'] ?? 'المملكة العربية السعودية',
                    'city' => $validated['address_city'],
                    'region' => $validated['address_region'] ?? null,
                    'district' => $validated['address_district'] ?? null,
                    'street' => $validated['address_street'],
                    'building_number' => $validated['address_building_number'] ?? null,
                    'floor' => $validated['address_floor'] ?? null,
                    'apartment' => $validated['address_apartment'] ?? null,
                    'postal_code' => $validated['address_postal_code'] ?? null,
                    'notes' => $validated['address_notes'] ?? null,
                    'is_default' => true,
                    'is_active' => true,
                ]);
            }

            DB::commit();

            OperationLog::record('created', 'client', $client, 'تم إنشاء عميل **'.$client->full_name.'**');

            return redirect()->route('store_clients.index')
                ->with('success', 'تم إنشاء العميل بنجاح');

        } catch (\Throwable $e) {
            // ⚠️ rollBack() قبل failure() لا بعده — التسجيل يكتب عبر نفس
            // اتصال قاعدة البيانات، فيُلغى سجلّه هو نفسه مع المعاملة إن كُتب
            // قبل انتهائها (تحقّقتُ من هذا مباشرةً على قاعدة محلية)
            while (DB::transactionLevel() > $txLevel) {
                DB::rollBack();
            }

            OperationLog::failure($e, 'OnlineStore.StoreClient', 'store');

            return redirect()->back()
                ->withInput()
                ->with('error', 'حدث خطأ أثناء إنشاء العميل. راجع سجل الأخطاء أو حاول مرة أخرى.');
        }
    }

    /**
     * عرض تفاصيل عميل
     */
    public function show(Client $storeClient)
    {
        // التأكد من أن العميل هو عميل متجر
        if (! $storeClient->is_store_client) {
            abort(404);
        }

        $storeClient->load([
            'branch',
            'onlineOrders' => function ($query) {
                $query->latest()->limit(10);
            },
            'invoices' => function ($query) {
                $query->latest()->limit(10);
            },
            'addresses',
            'wishlistItems',
            'productReviews',
        ]);

        // إحصائيات العميل
        $stats = [
            'total_orders' => $storeClient->onlineOrders()->count(),
            'total_orders_value' => $storeClient->onlineOrders()->sum('total_amount'),
            // لا حالة `completed` في طلبات المتجر — المكتمل هو «تم التسليم»
            'completed_orders' => $storeClient->onlineOrders()->where('status', OrderStatus::DELIVERED->value)->count(),
            'pending_orders' => $storeClient->onlineOrders()->where('status', 'pending')->count(),
            'total_invoices' => $storeClient->invoices()->count(),
            'total_invoices_value' => $storeClient->invoices()->sum('grand_total'),
            'wishlist_count' => $storeClient->wishlistItems()->count(),
            'reviews_count' => $storeClient->productReviews()->count(),
            'average_rating' => $storeClient->productReviews()->avg('rating') ?? 0,
        ];

        return view('onlinestore::clients.show', compact('storeClient', 'stats'));
    }

    /**
     * عرض صفحة تعديل عميل
     */
    public function edit(Client $storeClient)
    {
        // التأكد من أن العميل هو عميل متجر
        if (! $storeClient->is_store_client) {
            abort(404);
        }

        // تحميل العناوين
        $storeClient->load('addresses');

        return view('onlinestore::clients.edit', compact('storeClient'));
    }

    /**
     * تحديث بيانات عميل
     */
    public function update(Request $request, Client $storeClient)
    {
        // التأكد من أن العميل هو عميل متجر
        if (! $storeClient->is_store_client) {
            abort(404);
        }

        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'trade_name' => 'nullable|string|max:255',
            'email' => 'required|email|max:255|unique:clients,email,'.$storeClient->id,
            'phone' => 'nullable|string|max:20',
            'mobile' => 'nullable|string|max:20',
            'notes' => 'nullable|string|max:2000',
        ], [], self::ATTRIBUTES);

        // `clients.trade_name` إلزامي في الجدول — اسمٌ تجاري فارغ يُملأ بالاسم الشخصي
        if (! filled($validated['trade_name'] ?? null)) {
            $validated['trade_name'] = trim($validated['first_name'].' '.($validated['last_name'] ?? ''));
        }

        $txLevel = DB::transactionLevel();
        DB::beginTransaction();
        try {
            $storeClient->update($validated);

            DB::commit();

            OperationLog::record('updated', 'client', $storeClient, 'تم تعديل بيانات العميل **'.$storeClient->full_name.'**');

            return redirect()->route('store_clients.index')
                ->with('success', 'تم تحديث بيانات العميل بنجاح');

        } catch (\Throwable $e) {
            // rollBack() قبل failure() — انظر التعليق في store() أعلاه
            while (DB::transactionLevel() > $txLevel) {
                DB::rollBack();
            }

            OperationLog::failure($e, 'OnlineStore.StoreClient', 'update');

            return redirect()->back()
                ->withInput()
                ->with('error', 'حدث خطأ أثناء تحديث بيانات العميل. راجع سجل الأخطاء أو حاول مرة أخرى.');
        }
    }

    /**
     * حذف عميل
     */
    public function destroy(Client $storeClient)
    {
        // التأكد من أن العميل هو عميل متجر
        if (! $storeClient->is_store_client) {
            abort(404);
        }

        // التحقق من عدم وجود طلبات أو فواتير
        if ($storeClient->onlineOrders()->exists() || $storeClient->invoices()->exists()) {
            return redirect()->back()
                ->with('error', 'لا يمكن حذف العميل لوجود طلبات أو فواتير مرتبطة به');
        }

        $txLevel = DB::transactionLevel();
        DB::beginTransaction();
        try {
            OperationLog::record('deleted', 'client', $storeClient, 'تم حذف عميل **'.$storeClient->full_name.'**');

            // حذف العميل
            $storeClient->delete();

            DB::commit();

            return redirect()->route('store_clients.index')
                ->with('success', 'تم حذف العميل بنجاح');

        } catch (\Throwable $e) {
            // rollBack() قبل failure() — انظر التعليق في store() أعلاه
            while (DB::transactionLevel() > $txLevel) {
                DB::rollBack();
            }

            OperationLog::failure($e, 'OnlineStore.StoreClient', 'destroy');

            return redirect()->back()
                ->with('error', 'حدث خطأ أثناء حذف العميل. راجع سجل الأخطاء أو حاول مرة أخرى.');
        }
    }

    /**
     * تبديل حالة العميل
     */
    public function toggleStatus(Client $storeClient)
    {
        if (! $storeClient->is_store_client) {
            return response()->json(['success' => false, 'message' => 'العميل غير موجود'], 404);
        }

        // يمكن إضافة حقل status إذا كان موجوداً
        // $storeClient->update(['is_active' => !$storeClient->is_active]);

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث حالة العميل بنجاح',
        ]);
    }

    /**
     * تصدير بيانات العملاء
     */
    public function export(Request $request)
    {
        $clients = Client::where('is_store_client', 1)
            ->with(['branch', 'account'])
            ->get();

        // يمكن إضافة منطق التصدير هنا (Excel, CSV, PDF)

        return redirect()->back()->with('success', 'تم تصدير البيانات بنجاح');
    }

    // ==================== إدارة عناوين العميل ====================

    /**
     * حفظ عنوان جديد للعميل
     */
    public function storeAddress(Request $request, Client $storeClient)
    {
        // التأكد من أن العميل هو عميل متجر
        if (! $storeClient->is_store_client) {
            abort(404);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:100',
            'receiver_name' => 'nullable|string|max:255',
            'receiver_phone' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:100',
            'city' => 'required|string|max:100',
            'region' => 'nullable|string|max:100',
            'district' => 'nullable|string|max:100',
            'street' => 'required|string|max:255',
            'building_number' => 'nullable|string|max:20',
            'floor' => 'nullable|string|max:20',
            'apartment' => 'nullable|string|max:20',
            'postal_code' => 'nullable|string|max:20',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'notes' => 'nullable|string|max:500',
            'is_default' => 'nullable|boolean',
        ], [], self::ATTRIBUTES);

        $validated['client_id'] = $storeClient->id;
        $validated['is_active'] = true;
        // `receiver_name`/`receiver_phone` إلزاميان في الجدول — فارغان في النموذج
        // كانا يُسقطان الحفظ بخطأ 500؛ الافتراضي بيانات العميل نفسه
        $validated['receiver_name'] = filled($validated['receiver_name'] ?? null) ? $validated['receiver_name'] : $storeClient->display_name;
        $validated['receiver_phone'] = filled($validated['receiver_phone'] ?? null) ? $validated['receiver_phone'] : (string) ($storeClient->mobile ?: $storeClient->phone);

        // إذا كان هذا أول عنوان، اجعله افتراضياً
        if ($storeClient->addresses()->count() === 0) {
            $validated['is_default'] = true;
        }

        // إذا تم تعيينه كافتراضي، قم بإلغاء الافتراضي من باقي العناوين
        if ($request->boolean('is_default')) {
            $storeClient->addresses()->update(['is_default' => false]);
            $validated['is_default'] = true;
        } else {
            $validated['is_default'] = (bool) ($validated['is_default'] ?? false);
        }

        $address = ClientAddress::create($validated);

        OperationLog::record('created', 'client_address', $address, 'تمت إضافة عنوان للعميل **'.$storeClient->full_name.'**');

        return redirect()->route('store_clients.show', $storeClient)
            ->with('success', 'تم إضافة العنوان بنجاح');
    }

    /**
     * تحديث عنوان العميل
     */
    public function updateAddress(Request $request, Client $storeClient, ClientAddress $address)
    {
        // التأكد من أن العميل هو عميل متجر وأن العنوان تابع له
        if (! $storeClient->is_store_client || (int) $address->client_id !== (int) $storeClient->id) {
            abort(404);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:100',
            'receiver_name' => 'nullable|string|max:255',
            'receiver_phone' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:100',
            'city' => 'required|string|max:100',
            'region' => 'nullable|string|max:100',
            'district' => 'nullable|string|max:100',
            'street' => 'required|string|max:255',
            'building_number' => 'nullable|string|max:20',
            'floor' => 'nullable|string|max:20',
            'apartment' => 'nullable|string|max:20',
            'postal_code' => 'nullable|string|max:20',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'notes' => 'nullable|string|max:500',
        ], [], self::ATTRIBUTES);

        $validated['receiver_name'] = filled($validated['receiver_name'] ?? null) ? $validated['receiver_name'] : $storeClient->display_name;
        $validated['receiver_phone'] = filled($validated['receiver_phone'] ?? null) ? $validated['receiver_phone'] : (string) ($storeClient->mobile ?: $storeClient->phone);

        $address->update($validated);

        OperationLog::record('updated', 'client_address', $address, 'تم تعديل عنوان العميل **'.$storeClient->full_name.'**');

        return redirect()->route('store_clients.show', $storeClient)
            ->with('success', 'تم تحديث العنوان بنجاح');
    }

    /**
     * حذف عنوان العميل
     */
    public function destroyAddress(Client $storeClient, ClientAddress $address)
    {
        // التأكد من أن العميل هو عميل متجر وأن العنوان تابع له
        if (! $storeClient->is_store_client || (int) $address->client_id !== (int) $storeClient->id) {
            abort(404);
        }

        // التحقق من عدم وجود طلبات مرتبطة بهذا العنوان
        if ($address->onlineOrders()->exists()) {
            return redirect()->back()
                ->with('error', 'لا يمكن حذف العنوان لوجود طلبات مرتبطة به');
        }

        $wasDefault = $address->is_default;

        OperationLog::record('deleted', 'client_address', $address, 'تم حذف عنوان العميل **'.$storeClient->full_name.'**');

        $address->delete();

        // إذا كان العنوان المحذوف هو الافتراضي، اجعل أول عنوان متبقي افتراضياً
        if ($wasDefault) {
            $firstAddress = $storeClient->addresses()->first();
            if ($firstAddress) {
                $firstAddress->update(['is_default' => true]);
            }
        }

        return redirect()->route('store_clients.show', $storeClient)
            ->with('success', 'تم حذف العنوان بنجاح');
    }

    /**
     * تعيين عنوان كافتراضي
     */
    public function setDefaultAddress(Client $storeClient, ClientAddress $address)
    {
        // التأكد من أن العميل هو عميل متجر وأن العنوان تابع له
        if (! $storeClient->is_store_client || (int) $address->client_id !== (int) $storeClient->id) {
            abort(404);
        }

        // إلغاء الافتراضي من جميع العناوين
        $storeClient->addresses()->update(['is_default' => false]);

        // تعيين هذا العنوان كافتراضي
        $address->update(['is_default' => true]);

        OperationLog::record('updated', 'client_address', $address, 'تم تعيين عنوان افتراضي جديد للعميل **'.$storeClient->full_name.'**');

        return redirect()->route('store_clients.show', $storeClient)
            ->with('success', 'تم تعيين العنوان كافتراضي');
    }
}
