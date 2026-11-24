<?php

namespace Modules\OnlineStore\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Store\ClientPaymentCard;
use App\Services\OnlineStoreSettingsService;
use App\Support\OperationLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

/**
 * متحكم بطاقات الدفع للعملاء
 * Store Payment Cards Controller
 *
 * يدير عرض وإضافة وتعديل وحذف بطاقات الدفع المحفوظة للعملاء
 */
class StorePaymentCardsController extends Controller
{
    /**
     * الحصول على العميل المسجل حالياً
     * Get current authenticated client
     */
    protected function client(): ?Client
    {
        return Auth::guard('client')->user();
    }

    /**
     * عرض قائمة بطاقات العميل
     * List client payment cards
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

        $cards = ClientPaymentCard::where('client_id', $client->id)
            ->active()
            ->valid()
            ->with('paymentMethod:id,name,icon,type')
            ->orderByDesc('is_default')
            ->orderByDesc('last_used_at')
            ->get()
            ->map(fn ($card) => $this->formatCard($card));

        return response()->json([
            'success' => true,
            'cards' => $cards,
            'total_count' => $cards->count(),
        ]);
    }

    /**
     * إضافة بطاقة جديدة
     * Add new payment card
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

        $validator = Validator::make($request->all(), [
            'payment_method_id' => 'bail|nullable|integer|exists:payment_methods,id',
            'card_type' => 'required|string|in:visa,mastercard,mada,amex,other',
            'card_holder_name' => 'required|string|max:255',
            'card_last_four' => 'required|string|size:4',
            'expiry_month' => 'required|integer|min:1|max:12',
            'expiry_year' => 'required|integer|min:'.date('Y'),
            'nickname' => 'nullable|string|max:100',
            'is_default' => 'nullable|boolean',
            // الحقول الاختيارية للبوابة
            'token' => 'nullable|string',
            'gateway_card_id' => 'nullable|string',
            'gateway_name' => 'nullable|string',
            'card_bin' => 'nullable|string|max:8',
            // عنوان الفوترة
            'billing_address' => 'nullable|string|max:500',
            'billing_city' => 'nullable|string|max:100',
            'billing_country' => 'nullable|string|max:100',
            'billing_postal_code' => 'nullable|string|max:20',
        ], [
            'card_type.required' => 'نوع البطاقة مطلوب',
            'card_type.in' => 'نوع البطاقة غير صالح',
            'card_holder_name.required' => 'اسم حامل البطاقة مطلوب',
            'card_last_four.required' => 'آخر 4 أرقام مطلوبة',
            'card_last_four.size' => 'آخر 4 أرقام يجب أن تكون 4 أرقام',
            'expiry_month.required' => 'شهر الانتهاء مطلوب',
            'expiry_year.required' => 'سنة الانتهاء مطلوبة',
            'expiry_year.min' => 'البطاقة منتهية الصلاحية',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'الرجاء التحقق من البيانات المدخلة',
                'errors' => $validator->errors(),
            ], 422);
        }

        // التحقق من أن طريقة الدفع تدعم حفظ البطاقات (اختياري)
        $paymentMethodId = $request->payment_method_id;
        if ($paymentMethodId) {
            // طرق الدفع التي يقبلها المتجر فعلاً (إعداداته أولاً) لا كل طريقةٍ نشطة في النظام
            $paymentMethod = OnlineStoreSettingsService::storePaymentMethodsQuery()->find($paymentMethodId);
            if (! $paymentMethod || ! $paymentMethod->requires_card) {
                return response()->json([
                    'success' => false,
                    'message' => 'طريقة الدفع هذه لا تدعم حفظ البطاقات',
                ], 400);
            }
        } else {
            // البحث عن طريقة دفع تدعم البطاقات تلقائياً
            $paymentMethod = OnlineStoreSettingsService::storePaymentMethodsQuery()->where('requires_card', true)->first();
            $paymentMethodId = $paymentMethod?->id;
        }

        // التحقق من عدم وجود بطاقة بنفس الأرقام الأخيرة
        $existingCard = ClientPaymentCard::where('client_id', $client->id)
            ->where('card_last_four', $request->card_last_four)
            ->where('expiry_month', $request->expiry_month)
            ->where('expiry_year', $request->expiry_year)
            ->first();

        if ($existingCard) {
            return response()->json([
                'success' => false,
                'message' => 'هذه البطاقة مضافة مسبقاً',
                'card' => $this->formatCard($existingCard),
            ], 400);
        }

        // إذا كانت هذه أول بطاقة أو طلب جعلها افتراضية
        $isDefault = $request->boolean('is_default', false);
        $hasNoCards = ClientPaymentCard::where('client_id', $client->id)->count() === 0;

        if ($hasNoCards) {
            $isDefault = true;
        }

        // إنشاء البطاقة
        $card = ClientPaymentCard::create([
            'client_id' => $client->id,
            'payment_method_id' => $paymentMethodId,
            'card_type' => $request->card_type,
            'card_holder_name' => $request->card_holder_name,
            'card_last_four' => $request->card_last_four,
            'card_bin' => $request->card_bin,
            'expiry_month' => $request->expiry_month,
            'expiry_year' => $request->expiry_year,
            'token' => $request->token,
            'gateway_card_id' => $request->gateway_card_id,
            'gateway_name' => $request->gateway_name,
            'nickname' => $request->nickname,
            'billing_address' => $request->billing_address,
            'billing_city' => $request->billing_city,
            'billing_country' => $request->billing_country,
            'billing_postal_code' => $request->billing_postal_code,
            'is_default' => $isDefault,
            'is_active' => true,
        ]);

        // إذا كانت افتراضية، إلغاء الافتراضي من البطاقات الأخرى
        if ($isDefault) {
            ClientPaymentCard::where('client_id', $client->id)
                ->where('id', '!=', $card->id)
                ->update(['is_default' => false]);
        }

        OperationLog::record('created', 'payment_card', $card, 'تمت إضافة بطاقة دفع محفوظة تنتهي بـ **'.$card->card_last_four.'**');

        return response()->json([
            'success' => true,
            'message' => 'تمت إضافة البطاقة بنجاح',
            'card' => $this->formatCard($card->fresh('paymentMethod')),
        ], 201);
    }

    /**
     * عرض تفاصيل بطاقة
     * Show card details
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

        $card = ClientPaymentCard::where('client_id', $client->id)
            ->with('paymentMethod:id,name,icon,type')
            ->find($id);

        if (! $card) {
            return response()->json([
                'success' => false,
                'message' => 'البطاقة غير موجودة',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'card' => $this->formatCard($card),
        ]);
    }

    /**
     * تحديث بطاقة
     * Update card
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

        $card = ClientPaymentCard::where('client_id', $client->id)->find($id);

        if (! $card) {
            return response()->json([
                'success' => false,
                'message' => 'البطاقة غير موجودة',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'card_holder_name' => 'nullable|string|max:255',
            'nickname' => 'nullable|string|max:100',
            'expiry_month' => 'nullable|integer|min:1|max:12',
            'expiry_year' => 'nullable|integer|min:'.date('Y'),
            'is_default' => 'nullable|boolean',
            'billing_address' => 'nullable|string|max:500',
            'billing_city' => 'nullable|string|max:100',
            'billing_country' => 'nullable|string|max:100',
            'billing_postal_code' => 'nullable|string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'الرجاء التحقق من البيانات',
                'errors' => $validator->errors(),
            ], 422);
        }

        $updateData = [];

        if ($request->has('card_holder_name')) {
            $updateData['card_holder_name'] = $request->card_holder_name;
        }
        if ($request->has('nickname')) {
            $updateData['nickname'] = $request->nickname;
        }
        if ($request->has('expiry_month')) {
            $updateData['expiry_month'] = $request->expiry_month;
        }
        if ($request->has('expiry_year')) {
            $updateData['expiry_year'] = $request->expiry_year;
        }
        if ($request->has('billing_address')) {
            $updateData['billing_address'] = $request->billing_address;
        }
        if ($request->has('billing_city')) {
            $updateData['billing_city'] = $request->billing_city;
        }
        if ($request->has('billing_country')) {
            $updateData['billing_country'] = $request->billing_country;
        }
        if ($request->has('billing_postal_code')) {
            $updateData['billing_postal_code'] = $request->billing_postal_code;
        }

        if (! empty($updateData)) {
            $card->update($updateData);

            OperationLog::record('updated', 'payment_card', $card, 'تم تعديل بيانات بطاقة الدفع المحفوظة تنتهي بـ **'.$card->card_last_four.'**');
        }

        // تعيين كافتراضية
        if ($request->boolean('is_default', false)) {
            $card->setAsDefault();

            OperationLog::record('updated', 'payment_card', $card, 'تم تعيين بطاقة الدفع المنتهية بـ **'.$card->card_last_four.'** كافتراضية');
        }

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث البطاقة بنجاح',
            'card' => $this->formatCard($card->fresh('paymentMethod')),
        ]);
    }

    /**
     * حذف بطاقة
     * Delete card
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

        $card = ClientPaymentCard::where('client_id', $client->id)->find($id);

        if (! $card) {
            return response()->json([
                'success' => false,
                'message' => 'البطاقة غير موجودة',
            ], 404);
        }

        $wasDefault = $card->is_default;

        // حذف ناعم
        $card->delete();

        // إذا كانت افتراضية، تعيين بطاقة أخرى كافتراضية
        if ($wasDefault) {
            $nextCard = ClientPaymentCard::where('client_id', $client->id)
                ->active()
                ->valid()
                ->orderByDesc('last_used_at')
                ->first();

            if ($nextCard) {
                $nextCard->update(['is_default' => true]);
            }
        }

        OperationLog::record('deleted', 'payment_card', $card, 'تم حذف بطاقة الدفع المحفوظة تنتهي بـ **'.$card->card_last_four.'**');

        return response()->json([
            'success' => true,
            'message' => 'تم حذف البطاقة بنجاح',
        ]);
    }

    /**
     * تعيين بطاقة كافتراضية
     * Set card as default
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

        $card = ClientPaymentCard::where('client_id', $client->id)
            ->active()
            ->find($id);

        if (! $card) {
            return response()->json([
                'success' => false,
                'message' => 'البطاقة غير موجودة',
            ], 404);
        }

        if ($card->isExpired()) {
            return response()->json([
                'success' => false,
                'message' => 'لا يمكن تعيين بطاقة منتهية الصلاحية كافتراضية',
            ], 400);
        }

        $card->setAsDefault();

        OperationLog::record('updated', 'payment_card', $card, 'تم تعيين بطاقة الدفع المنتهية بـ **'.$card->card_last_four.'** كافتراضية');

        return response()->json([
            'success' => true,
            'message' => 'تم تعيين البطاقة كافتراضية',
            'card' => $this->formatCard($card->fresh('paymentMethod')),
        ]);
    }

    /**
     * تنسيق بيانات البطاقة
     */
    private function formatCard(ClientPaymentCard $card): array
    {
        return [
            'id' => $card->id,
            'card_type' => $card->card_type,
            'card_type_name' => $card->card_type_name,
            'card_icon' => $card->card_icon,
            'card_holder_name' => $card->card_holder_name,
            'masked_number' => $card->masked_number,
            'card_last_four' => $card->card_last_four,
            'expiry_date' => $card->expiry_date,
            'expiry_month' => $card->expiry_month,
            'expiry_year' => $card->expiry_year,
            'is_default' => (bool) $card->is_default,
            'is_expired' => $card->isExpired(),
            'is_expiring_soon' => $card->isExpiringSoon(),
            'nickname' => $card->nickname,
            'payment_method' => $card->paymentMethod ? [
                'id' => $card->paymentMethod->id,
                'name' => $card->paymentMethod->name,
                'icon' => $card->paymentMethod->icon,
                'type' => $card->paymentMethod->type,
            ] : null,
            'billing_address' => $card->billing_address,
            'billing_city' => $card->billing_city,
            'billing_country' => $card->billing_country,
            'last_used_at' => $card->last_used_at?->toDateTimeString(),
            'created_at' => $card->created_at->toDateTimeString(),
        ];
    }
}
