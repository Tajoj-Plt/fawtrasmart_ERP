<?php

namespace Modules\OnlineStore\Http\Controllers\Api;

use App\Enums\ShippingMethodType;
use App\Http\Controllers\Controller;
use App\Models\Store\ShippingMethod;
use App\Services\OnlineStoreSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * متحكم طرق الشحن للمتجر الإلكتروني
 * Store Shipping Controller
 *
 * يدير عرض طرق الشحن المتاحة بشكل مبسط
 * - لا يستخدم نظام المناطق (Shipping Zones)
 * - يعتمد على تكلفة ثابتة لكل طريقة شحن
 * - يدعم الحد الأدنى للطلب والشحن المجاني
 */
class StoreShippingController extends Controller
{
    /**
     * عرض قائمة طرق الشحن المتاحة
     * List available shipping methods
     */
    public function index(Request $request): JsonResponse
    {
        $orderAmount = floatval($request->get('order_amount', 0));

        // جلب إعدادات الشحن (للرسوم الإضافية فقط)
        $shippingSettings = OnlineStoreSettingsService::getShippingSettings();
        $revenuePercentage = floatval($shippingSettings['shipping_revenue_percentage'] ?? 0);
        $expressFee = floatval($shippingSettings['express_shipping_fee'] ?? 50);
        $defaultShippingCost = floatval($shippingSettings['default_shipping_cost'] ?? 25);

        // جلب طرق الشحن النشطة
        $shippingMethods = ShippingMethod::active()->ordered()->get();

        $results = [];

        foreach ($shippingMethods as $method) {
            $isAvailable = true;
            $availabilityReason = null;

            // التحقق من الحد الأدنى للطلب
            $minOrderAmount = floatval($method->min_order_amount ?? 0);
            $meetsMinOrder = $minOrderAmount <= 0 || $orderAmount >= $minOrderAmount;

            if (! $meetsMinOrder) {
                $isAvailable = false;
                $availabilityReason = 'الحد الأدنى للطلب '.number_format($minOrderAmount, 2).' ر.س';
            }

            /*
             * التكلفة الأساسية وقرار المجانية من `ShippingMethod::calculateShippingCost()`
             * نفسه المستخدم فعلياً عند إتمام الطلب في StoreOrderController — لا يُعاد
             * اشتقاقهما هنا. كان هذا المتحكم يتجاهل `type === FREE` تماماً (يفحص
             * `free_shipping_threshold` فقط)، فتُعرَض طريقةُ شحنٍ نوعها "مجاني" بتكلفةٍ
             * غير صفرية هنا بينما يُحاسَب العميل صفراً فعلياً عند الدفع.
             */
            $baseCost = $method->calculateShippingCost($orderAmount);
            $revenueAmount = $baseCost * ($revenuePercentage / 100);
            $normalCost = $baseCost + $revenueAmount;

            $methodFreeThreshold = floatval($method->free_shipping_threshold ?? 0);
            $supportsFreeShipping = $methodFreeThreshold > 0 || $method->type === ShippingMethodType::FREE;
            $methodHasFreeShipping = $normalCost <= 0;

            // حساب تكلفة الشحن السريع
            $expressCost = $normalCost + $expressFee;

            $results[] = [
                'id' => $method->id,
                'name' => $method->name,
                'code' => $method->code,
                'description' => $method->description,
                'logo' => $method->logo ? asset($method->logo) : null,
                // التوفر
                'is_available' => $isAvailable,
                'availability_reason' => $availabilityReason,
                // الحد الأدنى
                'min_order_amount' => $minOrderAmount,
                'meets_min_order' => $meetsMinOrder,
                // الشحن المجاني - يعتمد فقط على إعداد طريقة الشحن
                'free_shipping_threshold' => $methodFreeThreshold,
                'supports_free_shipping' => $supportsFreeShipping,
                'is_free' => $methodHasFreeShipping,
                // التكلفة
                'base_cost' => round($baseCost, 2),
                'revenue_amount' => round($revenueAmount, 2),
                'cost' => round($normalCost, 2),
                // ضريبة الشحن مضافةٌ فوق الرسم بنسبة ضريبة المتجر (صفر للشحن المجاني)
                'shipping_tax' => OnlineStoreSettingsService::shippingVatFor($normalCost),
                'cost_with_tax' => round($normalCost + OnlineStoreSettingsService::shippingVatFor($normalCost), 2),
                // الشحن السريع - يعتمد على إعداد الطريقة
                'supports_express' => (bool) ($method->supports_express ?? true),
                'express_fee' => $expressFee,
                'express_cost' => round($expressCost, 2),
                // التوصيل
                'delivery_time' => $method->estimated_delivery_text,
                'express_delivery_time' => '1-2 يوم عمل',
                'min_days' => $method->estimated_days_min,
                'max_days' => $method->estimated_days_max,
            ];
        }

        // إذا لم توجد طرق شحن، أضف الافتراضي (بدون شحن مجاني)
        if (empty($results)) {
            $revenueAmount = $defaultShippingCost * ($revenuePercentage / 100);
            $normalCost = $defaultShippingCost + $revenueAmount;
            $expressCost = $normalCost + $expressFee;

            $results[] = [
                'id' => 'default',
                'name' => 'الشحن الافتراضي',
                'code' => 'default',
                'description' => 'خدمة توصيل قياسية لجميع المناطق',
                'logo' => null,
                'is_available' => true,
                'availability_reason' => null,
                'min_order_amount' => 0,
                'meets_min_order' => true,
                'free_shipping_threshold' => 0,
                'supports_free_shipping' => false,
                'is_free' => false,
                'base_cost' => round($defaultShippingCost, 2),
                'revenue_amount' => round($revenueAmount, 2),
                'cost' => round($normalCost, 2),
                'supports_express' => true,
                'express_fee' => $expressFee,
                'express_cost' => round($expressCost, 2),
                'delivery_time' => '3-5 أيام عمل',
                'express_delivery_time' => '1-2 يوم عمل',
                'min_days' => 3,
                'max_days' => 5,
                'is_default' => true,
            ];
        }

        return response()->json([
            'success' => true,
            'express_fee' => $expressFee,
            'revenue_percentage' => $revenuePercentage,
            'order_amount' => $orderAmount,
            'shipping_methods' => $results,
            'available_count' => count(array_filter($results, fn ($m) => $m['is_available'])),
            'total_count' => count($results),
        ]);
    }

    /**
     * الحصول على إعدادات الشحن الافتراضية
     * ملاحظة: الشحن المجاني يعتمد فقط على إعداد كل طريقة شحن وليس إعداد عام
     */
    public function getDefaultSettings(Request $request): JsonResponse
    {
        $orderAmount = floatval($request->get('order_amount', 0));

        $shippingSettings = OnlineStoreSettingsService::getShippingSettings();
        $revenuePercentage = floatval($shippingSettings['shipping_revenue_percentage'] ?? 0);
        $expressFee = floatval($shippingSettings['express_shipping_fee'] ?? 50);
        $defaultShippingCost = floatval($shippingSettings['default_shipping_cost'] ?? 25);

        // حساب التكاليف (بدون شحن مجاني افتراضي - كل طريقة لها حدها الخاص)
        $revenueAmount = $defaultShippingCost * ($revenuePercentage / 100);
        $normalCost = $defaultShippingCost + $revenueAmount;
        $expressCost = $normalCost + $expressFee;

        return response()->json([
            'success' => true,
            'default_cost' => round($normalCost, 2),
            'express_cost' => round($expressCost, 2),
            'base_cost' => $defaultShippingCost,
            'revenue_percentage' => $revenuePercentage,
            'revenue_amount' => round($revenueAmount, 2),
            'express_fee' => $expressFee,
            'note' => 'الشحن المجاني يعتمد على إعداد free_shipping_threshold لكل طريقة شحن',
        ]);
    }

    /**
     * عرض تفاصيل طريقة شحن
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $method = ShippingMethod::active()->find($id);

        if (! $method) {
            return response()->json([
                'success' => false,
                'message' => 'طريقة الشحن غير موجودة',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'shipping_method' => [
                'id' => $method->id,
                'name' => $method->name,
                'code' => $method->code,
                'description' => $method->description,
                'logo' => $method->logo ? asset($method->logo) : null,
                'type' => $method->type?->value,
                'cost' => $method->cost,
                'free_shipping_threshold' => $method->free_shipping_threshold,
                'min_order_amount' => $method->min_order_amount,
                'estimated_delivery' => $method->estimated_delivery_text,
                'min_days' => $method->estimated_days_min,
                'max_days' => $method->estimated_days_max,
            ],
        ]);
    }

    /**
     * حساب تكلفة الشحن
     */
    public function calculate(Request $request): JsonResponse
    {
        $orderAmount = floatval($request->get('order_amount', 0));
        $methodId = $request->get('shipping_method_id');
        $isExpress = $request->boolean('is_express');

        $shippingSettings = OnlineStoreSettingsService::getShippingSettings();
        $revenuePercentage = floatval($shippingSettings['shipping_revenue_percentage'] ?? 0);
        $expressFee = floatval($shippingSettings['express_shipping_fee'] ?? 50);
        $defaultShippingCost = floatval($shippingSettings['default_shipping_cost'] ?? 25);
        $freeShippingThreshold = floatval($shippingSettings['free_shipping_threshold'] ?? 200);

        $globalFreeShipping = $orderAmount >= $freeShippingThreshold && $freeShippingThreshold > 0;

        // إذا تم تحديد طريقة شحن معينة
        if ($methodId && $methodId !== 'default') {
            $method = ShippingMethod::active()->find($methodId);

            if ($method) {
                // نفس المصدر المستخدم فعلياً عند إتمام الطلب — انظر التعليق في index()
                $baseCost = $method->calculateShippingCost($orderAmount);
                $revenueAmount = $baseCost * ($revenuePercentage / 100);
                $normalCost = $baseCost + $revenueAmount;
                $methodHasFreeShipping = $normalCost <= 0;
                $finalCost = $isExpress ? ($normalCost + $expressFee) : $normalCost;

                return response()->json([
                    'success' => true,
                    'data' => [
                        'shipping_method_id' => $method->id,
                        'shipping_method_name' => $method->name,
                        'base_cost' => round($baseCost, 2),
                        'revenue_amount' => round($revenueAmount, 2),
                        'cost' => round($finalCost, 2),
                        'shipping_tax' => OnlineStoreSettingsService::shippingVatFor(round($finalCost, 2)),
                        'cost_with_tax' => round($finalCost + OnlineStoreSettingsService::shippingVatFor(round($finalCost, 2)), 2),
                        'is_free' => $methodHasFreeShipping && ! $isExpress,
                        'is_express' => $isExpress,
                        'express_fee' => $isExpress ? $expressFee : 0,
                        'delivery_time' => $isExpress ? '1-2 يوم عمل' : $method->estimated_delivery_text,
                    ],
                ]);
            }
        }

        // الشحن الافتراضي
        $revenueAmount = $defaultShippingCost * ($revenuePercentage / 100);
        $normalCost = $globalFreeShipping ? 0 : ($defaultShippingCost + $revenueAmount);
        $finalCost = $isExpress ? ($normalCost + $expressFee) : $normalCost;

        return response()->json([
            'success' => true,
            'data' => [
                'shipping_method_id' => 'default',
                'shipping_method_name' => 'الشحن الافتراضي',
                'base_cost' => round($defaultShippingCost, 2),
                'revenue_amount' => round($revenueAmount, 2),
                'cost' => round($finalCost, 2),
                'is_free' => $globalFreeShipping && ! $isExpress,
                'is_express' => $isExpress,
                'express_fee' => $isExpress ? $expressFee : 0,
                'delivery_time' => $isExpress ? '1-2 يوم عمل' : '3-5 أيام عمل',
            ],
        ]);
    }

    /**
     * التحقق من توفر الشحن
     */
    public function checkAvailability(Request $request): JsonResponse
    {
        $orderAmount = floatval($request->get('order_amount', 0));

        $availableMethods = ShippingMethod::active()
            ->ordered()
            ->get()
            ->filter(function ($method) use ($orderAmount) {
                return $method->meetsMinimumOrder($orderAmount);
            });

        return response()->json([
            'success' => true,
            'is_available' => $availableMethods->isNotEmpty(),
            'available_methods' => $availableMethods->map(fn ($method) => [
                'id' => $method->id,
                'name' => $method->name,
                'cost' => $method->cost,
            ])->values(),
        ]);
    }
}
