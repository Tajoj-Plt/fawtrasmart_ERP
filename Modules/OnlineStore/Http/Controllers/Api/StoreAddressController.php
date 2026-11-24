<?php

namespace Modules\OnlineStore\Http\Controllers\Api;

use App\Enums\RecipientType;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\EmailTemplate;
use App\Models\Store\ClientAddress;
use App\Services\OnlineStoreSettingsService;
use App\Support\OperationLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Modules\OnlineStore\Http\Controllers\Api\Traits\ResolvesAddressLocation;

/**
 * متحكم عناوين العملاء للمتجر الإلكتروني
 * Store Client Addresses Controller
 *
 * يدير عناوين التوصيل للعملاء
 */
class StoreAddressController extends Controller
{
    use ResolvesAddressLocation;

    /**
     * الحصول على العميل المسجل حالياً
     * Get current authenticated client
     */
    protected function client(): ?Client
    {
        return Auth::guard('client')->user();
    }

    /**
     * الحصول على المتغيرات الأساسية للإيميل من الإعدادات
     */
    private function getBaseEmailVariables(): array
    {
        $storeInfo = OnlineStoreSettingsService::getStoreInfo();

        return [
            'store_name' => $storeInfo['name'] ?? config('app.name', 'المتجر الإلكتروني'),
            'store_url' => url('/'),
            'store_phone' => $storeInfo['phone'] ?? '',
            'store_email' => $storeInfo['email'] ?? '',
            'support_email' => $storeInfo['email'] ?? config('mail.from.address', 'support@example.com'),
            'store_logo' => $storeInfo['logo'] ?? '',
            'store_address' => $storeInfo['address'] ?? '',
        ];
    }

    /**
     * عرض قائمة العناوين
     * List addresses
     */
    public function index(Request $request): JsonResponse
    {
        $client = $this->client();

        if (! $client) {
            return response()->json([
                'success' => false,
                'message' => 'يجب تسجيل الدخول أولاً',
            ], 401);
        }

        $addresses = ClientAddress::where('client_id', $client->id)
            ->active()
            ->orderBy('is_default', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        // جلب بيانات المتجر والإعدادات
        $storeInfo = OnlineStoreSettingsService::getStoreInfo();
        $socialLinks = OnlineStoreSettingsService::getSocialSettings();
        $seo = OnlineStoreSettingsService::getSeoSettings();

        return response()->json([
            'success' => true,
            'addresses' => $addresses->map(fn ($address) => $this->formatAddress($address)),
            'store_info' => $storeInfo,
            'social_links' => $socialLinks,
            'seo' => $seo,
            'settings' => OnlineStoreSettingsService::getAllSettingsForApi(),
        ]);
    }

    /**
     * عرض عنوان محدد
     * Show address
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $client = $this->client();

        if (! $client) {
            return response()->json([
                'success' => false,
                'message' => 'يجب تسجيل الدخول أولاً',
            ], 401);
        }

        $address = ClientAddress::where('client_id', $client->id)->active()->find($id);

        if (! $address) {
            return response()->json([
                'success' => false,
                'message' => 'العنوان غير موجود',
            ], 404);
        }

        // جلب بيانات المتجر والإعدادات
        $storeInfo = OnlineStoreSettingsService::getStoreInfo();
        $socialLinks = OnlineStoreSettingsService::getSocialSettings();
        $seo = OnlineStoreSettingsService::getSeoSettings();

        return response()->json([
            'success' => true,
            'address' => $this->formatAddress($address),
            'store_info' => $storeInfo,
            'social_links' => $socialLinks,
            'seo' => $seo,
            'settings' => OnlineStoreSettingsService::getAllSettingsForApi(),
        ]);
    }

    /**
     * قواعد التحقق المشتركة. الدولة والمدينة نصّان حرّان (قادمان من الخريطة) لا
     * معرّفان إلزاميان — يطابقهما الخادم مع جداوله إن أمكن؛ والمعرّفان القديمان
     * `country_id`/`city_id` يبقيان مقبولين.
     */
    private function addressRules(bool $partial): array
    {
        $req = $partial ? 'sometimes' : 'nullable';

        return [
            'title' => $req.'|string|max:100',
            'receiver_name' => $req.'|string|max:255',
            'receiver_phone' => $req.'|string|max:20',
            'country' => 'nullable|string|max:100',
            'country_id' => 'bail|nullable|integer|exists:countries,id',
            'city' => 'nullable|string|max:100',
            'city_id' => 'bail|nullable|integer|exists:cities,id',
            'region' => 'nullable|string|max:100',
            'district' => 'nullable|string|max:100',
            'street' => 'nullable|string|max:255',
            'building_number' => 'nullable|string|max:50',
            'floor' => 'nullable|string|max:20',
            'apartment' => 'nullable|string|max:50',
            'postal_code' => 'nullable|string|max:20',
            'address_line' => 'nullable|string|max:500',
            'formatted_address' => 'nullable|string|max:1000',
            'notes' => 'nullable|string|max:500',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'is_default' => 'nullable|boolean',
        ];
    }

    private const ADDRESS_MESSAGES = [
        'title.string' => 'اسم العنوان غير صالح',
        'receiver_name.string' => 'اسم المستلم غير صالح',
        'receiver_phone.string' => 'رقم هاتف المستلم غير صالح',
        'latitude.numeric' => 'خط العرض غير صالح',
        'latitude.between' => 'خط العرض يجب أن يكون بين -90 و90',
        'longitude.numeric' => 'خط الطول غير صالح',
        'longitude.between' => 'خط الطول يجب أن يكون بين -180 و180',
        'country_id.exists' => 'الدولة غير موجودة',
        'city_id.exists' => 'المدينة غير موجودة',
        'is_default.boolean' => 'قيمة العنوان الافتراضي غير صالحة',
    ];

    private const ADDRESS_ATTRIBUTES = [
        'title' => 'اسم العنوان',
        'receiver_name' => 'اسم المستلم',
        'receiver_phone' => 'رقم هاتف المستلم',
        'country' => 'الدولة',
        'country_id' => 'الدولة',
        'city' => 'المدينة',
        'city_id' => 'المدينة',
        'region' => 'المنطقة',
        'district' => 'الحي',
        'street' => 'الشارع',
        'building_number' => 'رقم المبنى',
        'floor' => 'الطابق',
        'apartment' => 'الشقة',
        'postal_code' => 'الرمز البريدي',
        'address_line' => 'تفاصيل العنوان',
        'formatted_address' => 'العنوان المنسَّق',
        'notes' => 'الملاحظات',
        'latitude' => 'خط العرض',
        'longitude' => 'خط الطول',
        'is_default' => 'العنوان الافتراضي',
    ];

    /** إحداثيان معاً أو لا شيء، بدقّة عمود القاعدة (8 منازل). */
    private function normalizedCoordinates(Request $request): array
    {
        if (! $request->filled('latitude') || ! $request->filled('longitude')) {
            return ['latitude' => null, 'longitude' => null];
        }

        return [
            'latitude' => round((float) $request->input('latitude'), 8),
            'longitude' => round((float) $request->input('longitude'), 8),
        ];
    }

    /** أول قيمة نصية غير فارغة. */
    private function firstFilled(array $values): ?string
    {
        foreach ($values as $value) {
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return null;
    }

    /**
     * إنشاء عنوان جديد
     * Create new address
     *
     * يقبل حمولة الخريطة: latitude, longitude, formatted_address, country (اسم أو رمز ISO),
     * city (نص), region, district, street, postal_code + الحقول القديمة.
     * المستلم (الاسم والجوال) يُملآن من ملف العميل إن لم يُرسلا.
     */
    public function store(Request $request): JsonResponse
    {
        $client = $this->client();

        if (! $client) {
            return response()->json([
                'success' => false,
                'message' => 'يجب تسجيل الدخول أولاً',
            ], 401);
        }

        $validator = Validator::make($request->all(), $this->addressRules(false), self::ADDRESS_MESSAGES, self::ADDRESS_ATTRIBUTES);

        $receiverName = $this->firstFilled([$request->input('receiver_name'), $client->full_name, $client->trade_name]);
        $receiverPhone = $this->firstFilled([$request->input('receiver_phone'), $client->phone, $client->mobile]);

        $validator->after(function ($v) use ($request, $receiverName, $receiverPhone) {
            if (! $receiverName) {
                $v->errors()->add('receiver_name', 'اسم المستلم مطلوب');
            }

            if (! $receiverPhone) {
                $v->errors()->add('receiver_phone', 'رقم هاتف المستلم مطلوب');
            }

            // موضعٌ واحد على الأقل: مدينة أو إحداثيات أو عنوان منسَّق
            $hasPlace = $request->filled('city') || $request->filled('city_id')
                || $request->filled('formatted_address')
                || ($request->filled('latitude') && $request->filled('longitude'));

            if (! $hasPlace) {
                $v->errors()->add('city', 'المدينة مطلوبة — اكتبها أو اختر الموقع من الخريطة');
            }

            if ($request->filled('latitude') xor $request->filled('longitude')) {
                $v->errors()->add('latitude', 'إحداثيا الموقع (خط العرض وخط الطول) يُرسلان معاً');
            }
        });

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'الرجاء التحقق من البيانات المدخلة',
                'errors' => $validator->errors(),
            ], 422);
        }

        // التحقق من عدد العناوين (النشطة فقط — المحذوفة لا تُحسب)
        $addressCount = ClientAddress::where('client_id', $client->id)->active()->count();
        if ($addressCount >= 10) {
            return response()->json([
                'success' => false,
                'message' => 'لقد وصلت للحد الأقصى من العناوين (10)',
            ], 400);
        }

        $location = $this->resolveAddressLocation($request->only(['country', 'city', 'country_id', 'city_id']));
        $coords = $this->normalizedCoordinates($request);

        $attributes = [
            'client_id' => $client->id,
            'title' => $this->firstFilled([$request->input('title')]) ?? 'عنوان '.($addressCount + 1),
            'receiver_name' => $receiverName,
            'receiver_phone' => $receiverPhone,
            'country' => $location['country'],
            'country_id' => $location['country_id'],
            'city' => $location['city'] ?? $this->firstFilled([$request->input('region'), $request->input('district')]),
            'city_id' => $location['city_id'],
            'region' => $request->input('region'),
            'district' => $request->input('district'),
            'street' => $request->input('street'),
            'building_number' => $request->input('building_number'),
            'floor' => $request->input('floor'),
            'apartment' => $request->input('apartment'),
            'postal_code' => $request->input('postal_code'),
            'address_line' => $request->input('address_line'),
            'formatted_address' => $request->input('formatted_address'),
            'notes' => $request->input('notes'),
            'latitude' => $coords['latitude'],
            'longitude' => $coords['longitude'],
        ];

        // نقرتان متتاليتان/إعادة إرسال: العنوان نفسه خلال ثوانٍ لا يتكرّر
        $duplicate = ClientAddress::where('client_id', $client->id)->active()
            ->where('created_at', '>=', now()->subSeconds(15))
            ->where(array_diff_key($attributes, array_flip($request->filled('title') ? ['latitude', 'longitude'] : ['title', 'latitude', 'longitude'])))
            ->first();

        if ($duplicate) {
            return response()->json([
                'success' => true,
                'message' => 'تم إضافة العنوان بنجاح',
                'address' => $this->formatAddress($duplicate),
                'store_info' => OnlineStoreSettingsService::getStoreInfo(),
                'social_links' => OnlineStoreSettingsService::getSocialSettings(),
                'seo' => OnlineStoreSettingsService::getSeoSettings(),
                'settings' => OnlineStoreSettingsService::getAllSettingsForApi(),
            ], 200);
        }

        // أولُ عنوانٍ للعميل (أو أولُ عنوانٍ بعد حذف الافتراضي) يصير افتراضياً
        $makeDefault = $request->boolean('is_default')
            || ! ClientAddress::where('client_id', $client->id)->active()->default()->exists();

        $address = ClientAddress::create($attributes + [
            'is_default' => $makeDefault,
            'is_active' => true,
        ]);

        // إذا كان العنوان الافتراضي، قم بإزالة الافتراضي من العناوين الأخرى
        if ($address->is_default) {
            $address->setAsDefault();
        }

        OperationLog::record('created', 'client_address', $address, 'تمت إضافة عنوان للعميل **'.$client->full_name.'**');

        // إرسال بريد إلكتروني للإشعار بإضافة العنوان
        $this->sendAddressAddedEmail($client, $address);

        // جلب بيانات المتجر والإعدادات
        $storeInfo = OnlineStoreSettingsService::getStoreInfo();
        $socialLinks = OnlineStoreSettingsService::getSocialSettings();
        $seo = OnlineStoreSettingsService::getSeoSettings();

        return response()->json([
            'success' => true,
            'message' => 'تم إضافة العنوان بنجاح',
            'address' => $this->formatAddress($address->fresh()),
            'store_info' => $storeInfo,
            'social_links' => $socialLinks,
            'seo' => $seo,
            'settings' => OnlineStoreSettingsService::getAllSettingsForApi(),
        ], 201);
    }

    /**
     * تحديث عنوان
     * Update address
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $client = $this->client();

        if (! $client) {
            return response()->json([
                'success' => false,
                'message' => 'يجب تسجيل الدخول أولاً',
            ], 401);
        }

        $address = ClientAddress::where('client_id', $client->id)->active()->find($id);

        if (! $address) {
            return response()->json([
                'success' => false,
                'message' => 'العنوان غير موجود',
            ], 404);
        }

        $validator = Validator::make($request->all(), $this->addressRules(true), self::ADDRESS_MESSAGES, self::ADDRESS_ATTRIBUTES);

        $validator->after(function ($v) use ($request) {
            if ($request->filled('latitude') xor $request->filled('longitude')) {
                $v->errors()->add('latitude', 'إحداثيا الموقع (خط العرض وخط الطول) يُرسلان معاً');
            }

            foreach (['title', 'receiver_name', 'receiver_phone'] as $required) {
                if (is_string($request->input($required)) && trim($request->input($required)) === '') {
                    $v->errors()->add($required, 'هذا الحقل لا يمكن أن يكون فارغاً');
                }
            }
        });

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'الرجاء التحقق من البيانات المدخلة',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $request->only([
            'title',
            'receiver_name',
            'receiver_phone',
            'region',
            'district',
            'street',
            'building_number',
            'floor',
            'apartment',
            'postal_code',
            'address_line',
            'formatted_address',
            'notes',
        ]);

        // الموقع: لا يُعاد حسابه إلا إن أُرسل شيءٌ منه
        if ($request->hasAny(['country', 'city', 'country_id', 'city_id'])) {
            $location = $this->resolveAddressLocation([
                'country' => $request->has('country') ? $request->input('country') : $address->country,
                'city' => $request->has('city') ? $request->input('city') : $address->city,
                'country_id' => $request->input('country_id'),
                'city_id' => $request->input('city_id'),
            ]);

            $data += [
                'country' => $location['country'],
                'country_id' => $location['country_id'],
                'city' => $location['city'],
                'city_id' => $location['city_id'],
            ];
        }

        if ($request->hasAny(['latitude', 'longitude'])) {
            $data += $this->normalizedCoordinates($request);
        }

        $address->update($data);

        // تحديث الافتراضي
        if ($request->has('is_default') && $request->boolean('is_default')) {
            $address->setAsDefault();
        }

        OperationLog::record('updated', 'client_address', $address, 'تم تعديل عنوان العميل **'.$client->full_name.'**');

        // جلب بيانات المتجر والإعدادات
        $storeInfo = OnlineStoreSettingsService::getStoreInfo();
        $socialLinks = OnlineStoreSettingsService::getSocialSettings();
        $seo = OnlineStoreSettingsService::getSeoSettings();

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث العنوان بنجاح',
            'address' => $this->formatAddress($address->fresh()),
            'store_info' => $storeInfo,
            'social_links' => $socialLinks,
            'seo' => $seo,
            'settings' => OnlineStoreSettingsService::getAllSettingsForApi(),
        ]);
    }

    /**
     * حذف عنوان
     * Delete address
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $client = $this->client();

        if (! $client) {
            return response()->json([
                'success' => false,
                'message' => 'يجب تسجيل الدخول أولاً',
            ], 401);
        }

        $address = ClientAddress::where('client_id', $client->id)->active()->find($id);

        if (! $address) {
            return response()->json([
                'success' => false,
                'message' => 'العنوان غير موجود',
            ], 404);
        }

        // التحقق من عدم استخدام العنوان في طلبات نشطة
        $activeOrders = $address->onlineOrders()
            ->whereNotIn('status', ['delivered', 'cancelled', 'refunded'])
            ->exists();

        if ($activeOrders) {
            return response()->json([
                'success' => false,
                'message' => 'لا يمكن حذف عنوان مستخدم في طلبات نشطة',
            ], 400);
        }

        OperationLog::record('deleted', 'client_address', $address, 'تم حذف عنوان العميل **'.$client->full_name.'**');

        $wasDefault = (bool) $address->is_default;
        $address->update(['is_active' => false, 'is_default' => false]);

        // حُذف الافتراضي ⇒ أحدثُ عنوانٍ نشط يصير افتراضياً
        if ($wasDefault) {
            ClientAddress::where('client_id', $client->id)->active()->latest('id')->first()?->setAsDefault();
        }

        // جلب بيانات المتجر والإعدادات
        $storeInfo = OnlineStoreSettingsService::getStoreInfo();
        $socialLinks = OnlineStoreSettingsService::getSocialSettings();
        $seo = OnlineStoreSettingsService::getSeoSettings();

        return response()->json([
            'success' => true,
            'message' => 'تم حذف العنوان بنجاح',
            'store_info' => $storeInfo,
            'social_links' => $socialLinks,
            'seo' => $seo,
            'settings' => OnlineStoreSettingsService::getAllSettingsForApi(),
        ]);
    }

    /**
     * تعيين عنوان كافتراضي
     * Set as default
     */
    public function setDefault(Request $request, int $id): JsonResponse
    {
        $client = $this->client();

        if (! $client) {
            return response()->json([
                'success' => false,
                'message' => 'يجب تسجيل الدخول أولاً',
            ], 401);
        }

        $address = ClientAddress::where('client_id', $client->id)->active()->find($id);

        if (! $address) {
            return response()->json([
                'success' => false,
                'message' => 'العنوان غير موجود',
            ], 404);
        }

        $address->setAsDefault();

        OperationLog::record('updated', 'client_address', $address, 'تم تعيين عنوان افتراضي جديد للعميل **'.$client->full_name.'**');

        // جلب بيانات المتجر والإعدادات
        $storeInfo = OnlineStoreSettingsService::getStoreInfo();
        $socialLinks = OnlineStoreSettingsService::getSocialSettings();
        $seo = OnlineStoreSettingsService::getSeoSettings();

        return response()->json([
            'success' => true,
            'message' => 'تم تعيين العنوان كافتراضي',
            'address' => $this->formatAddress($address->fresh()),
            'store_info' => $storeInfo,
            'social_links' => $socialLinks,
            'seo' => $seo,
            'settings' => OnlineStoreSettingsService::getAllSettingsForApi(),
        ]);
    }

    /**
     * الحصول على العنوان الافتراضي
     * Get default address
     */
    public function getDefault(Request $request): JsonResponse
    {
        $client = $this->client();

        if (! $client) {
            return response()->json([
                'success' => false,
                'message' => 'يجب تسجيل الدخول أولاً',
            ], 401);
        }

        $address = ClientAddress::where('client_id', $client->id)
            ->active()
            ->default()
            ->first();

        if (! $address) {
            // الحصول على أول عنوان إذا لم يكن هناك افتراضي
            $address = ClientAddress::where('client_id', $client->id)
                ->active()
                ->first();
        }

        // جلب بيانات المتجر والإعدادات
        $storeInfo = OnlineStoreSettingsService::getStoreInfo();
        $socialLinks = OnlineStoreSettingsService::getSocialSettings();
        $seo = OnlineStoreSettingsService::getSeoSettings();

        return response()->json([
            'success' => true,
            'address' => $address ? $this->formatAddress($address) : null,
            'store_info' => $storeInfo,
            'social_links' => $socialLinks,
            'seo' => $seo,
            'settings' => OnlineStoreSettingsService::getAllSettingsForApi(),
        ]);
    }

    /**
     * تنسيق العنوان
     */
    private function formatAddress(ClientAddress $address): array
    {
        return [
            'id' => $address->id,
            'title' => $address->title,
            'receiver_name' => $address->receiver_name,
            'receiver_phone' => $address->receiver_phone,
            'country' => $address->country,
            'country_id' => $address->country_id,
            'city' => $address->city,
            'city_id' => $address->city_id,
            'region' => $address->region,
            'district' => $address->district,
            'street' => $address->street,
            'building_number' => $address->building_number,
            'floor' => $address->floor,
            'apartment' => $address->apartment,
            'postal_code' => $address->postal_code,
            'address_line' => $address->address_line,
            'formatted_address' => $address->formatted_address,
            'notes' => $address->notes,
            'latitude' => $address->latitude,
            'longitude' => $address->longitude,
            'full_address' => $address->full_address,
            'has_coordinates' => $address->hasCoordinates(),
            'is_default' => $address->is_default,
            'created_at' => $address->created_at->toDateTimeString(),
        ];
    }

    /**
     * إرسال بريد إلكتروني عند إضافة عنوان جديد
     */
    private function sendAddressAddedEmail($client, ClientAddress $address): void
    {
        // عميلٌ بلا بريد (سُجِّل بالجوال فقط): لا إشعار — وخطأ الإرسال لا يُسقط حفظ العنوان
        if (empty($client->email)) {
            return;
        }

        try {
            $baseVars = $this->getBaseEmailVariables();

            EmailTemplate::sendTemplatedEmail(
                to: $client->email,
                templateCode: 'account.address_added',
                variables: array_merge($baseVars, [
                    'customer_name' => $client->first_name.' '.$client->last_name,
                    'address_details' => $address->full_address,
                    'addresses_url' => url('/account/addresses'),
                ]),
                recipientType: RecipientType::Customer,
                recipientId: $client->id
            );
        } catch (\Throwable $e) {
            OperationLog::failure($e, 'OnlineStore.StoreAddress', 'store', $address);
            \Log::error('فشل إرسال بريد إضافة العنوان: '.$e->getMessage());
        }
    }
}
