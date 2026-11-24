<?php

namespace Modules\OnlineStore\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Store\StoreCoupon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

/**
 * متحكم كوبونات الخصم للمتجر الإلكتروني
 * Store Coupons Controller
 *
 * يدير التحقق من الكوبونات وتطبيقها
 */
class StoreCouponController extends Controller
{
    /**
     * التحقق من صلاحية كوبون
     * Validate coupon
     */
    /**
     * كان اسمها `validate` — وهو اسم دالةٍ معرَّفةٍ في `Controller` الأساس
     * بتوقيعٍ مختلف (`$rules, $messages, $attributes`). فمجرّد تحميل هذا
     * الملفّ يُنتج خطأً قاتلاً: «Declaration must be compatible». أي أن
     * كل طلبٍ يمرّ على مسارات الكوبونات كان يسقط قبل أن يبدأ.
     */
    public function validateCoupon(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required|string|max:50',
            'order_amount' => 'nullable|numeric|min:0|max:999999999',
            'product_ids' => 'nullable|array|max:200',
            'product_ids.*' => 'integer',
        ], [
            'code.required' => 'رمز الكوبون مطلوب',
            'code.string' => 'رمز الكوبون غير صالح',
            'code.max' => 'رمز الكوبون طويل جداً',
            'order_amount.numeric' => 'قيمة الطلب غير صالحة',
            'order_amount.min' => 'قيمة الطلب غير صالحة',
            'order_amount.max' => 'قيمة الطلب غير صالحة',
            'product_ids.array' => 'قائمة المنتجات غير صالحة',
            'product_ids.*.integer' => 'معرّف المنتج غير صالح',
        ], [
            'code' => 'رمز الكوبون',
            'order_amount' => 'قيمة الطلب',
            'product_ids' => 'المنتجات',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
                'valid' => false,
            ], 422);
        }

        $coupon = StoreCoupon::where('code', $request->code)->first();

        if (! $coupon) {
            return response()->json([
                'success' => false,
                'message' => 'رمز الكوبون غير صحيح',
                'valid' => false,
            ], 400);
        }

        // التحقق من صلاحية الكوبون
        if (! $coupon->isValid()) {
            $message = 'الكوبون غير صالح';

            if ($coupon->isExpired()) {
                $message = 'الكوبون منتهي الصلاحية';
            } elseif (! $coupon->isStarted()) {
                $message = 'الكوبون لم يبدأ بعد';
            }

            return response()->json([
                'success' => false,
                'message' => $message,
                'valid' => false,
            ], 400);
        }

        $clientId = (Auth::guard('client')->user() ?? $request->user())?->id;

        if ($clientId && ! empty($coupon->applicable_clients) && ! in_array($clientId, $coupon->applicable_clients)) {
            return response()->json([
                'success' => false,
                'message' => 'هذا الكوبون غير متاح لحسابك',
                'valid' => false,
            ], 400);
        }

        // التحقق من إمكانية استخدام العميل للكوبون
        if ($clientId && ! $coupon->canBeUsedByClient($clientId)) {
            return response()->json([
                'success' => false,
                'message' => 'لقد وصلت للحد الأقصى من استخدام هذا الكوبون',
                'valid' => false,
            ], 400);
        }

        // التحقق من الحد الأدنى للطلب
        $orderAmount = (float) $request->get('order_amount', 0);
        if ($coupon->min_order_amount && $orderAmount < $coupon->min_order_amount) {
            return response()->json([
                'success' => false,
                'message' => 'الحد الأدنى للطلب هو '.$coupon->min_order_amount,
                'valid' => false,
                'min_order_amount' => $coupon->min_order_amount,
            ], 400);
        }

        // حساب الخصم
        $discount = 0;
        if ($orderAmount > 0) {
            $productIds = $request->get('product_ids', []);
            $discount = $coupon->calculateDiscount($orderAmount, $productIds);
        }

        return response()->json([
            'success' => true,
            'valid' => true,
            'coupon' => [
                'code' => $coupon->code,
                'name' => $coupon->name,
                'description' => $coupon->description,
                'type' => $coupon->type->value,
                'discount_value' => $coupon->value,
                'min_order_amount' => $coupon->min_order_amount,
                'max_discount_amount' => $coupon->max_discount,
                'starts_at' => $coupon->start_date?->toDateTimeString(),
                'expires_at' => $coupon->end_date?->toDateTimeString(),
                'remaining_usages' => $coupon->remaining_usages,
            ],
            'calculated_discount' => $discount,
        ]);
    }

    /**
     * الحصول على تفاصيل كوبون
     * Get coupon details
     */
    public function show(Request $request, string $code): JsonResponse
    {
        $coupon = StoreCoupon::where('code', $code)
            ->active()
            ->valid()
            ->first();

        if (! $coupon) {
            return response()->json([
                'success' => false,
                'message' => 'الكوبون غير موجود أو غير صالح',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'coupon' => [
                'code' => $coupon->code,
                'name' => $coupon->name,
                'description' => $coupon->description,
                'type' => $coupon->type->value,
                'discount_value' => $coupon->value,
                'min_order_amount' => $coupon->min_order_amount,
                'max_discount_amount' => $coupon->max_discount,
                'starts_at' => $coupon->start_date?->toDateTimeString(),
                'expires_at' => $coupon->end_date?->toDateTimeString(),
            ],
        ]);
    }

    /**
     * الكوبونات المتاحة للعميل
     * Available coupons for client
     */
    public function available(Request $request): JsonResponse
    {
        $client = Auth::guard('client')->user() ?? $request->user();

        // لا عمودَ «عام/خاص» لجدول الكوبونات (كان الاستعلام عن `is_public` يسقط بـ500). والكوبونات
        // العامة أكوادُها تُوزَّع بقنوات المتجر، فالمعروض هنا ما خُصِّص لهذا العميل بالذات
        $coupons = StoreCoupon::active()
            ->valid()
            ->get()
            ->filter(function ($coupon) use ($client) {
                if (! $client || empty($coupon->applicable_clients) || ! in_array($client->id, $coupon->applicable_clients)) {
                    return false;
                }

                // التحقق من إمكانية استخدام العميل للكوبون
                return $coupon->canBeUsedByClient($client->id);
            })
            ->values();

        return response()->json([
            'success' => true,
            'coupons' => $coupons->map(fn ($coupon) => [
                'code' => $coupon->code,
                'name' => $coupon->name,
                'description' => $coupon->description,
                'type' => $coupon->type->value,
                'discount_value' => $coupon->value,
                'min_order_amount' => $coupon->min_order_amount,
                'max_discount_amount' => $coupon->max_discount,
                'expires_at' => $coupon->end_date?->toDateTimeString(),
            ]),
        ]);
    }
}
