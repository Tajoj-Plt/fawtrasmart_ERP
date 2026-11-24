<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Store Coupons Seeder
 * سيدر كوبونات المتجر التجريبية
 */
class StoreCouponsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * إدراج كوبونات المتجر التجريبية
     */
    public function run(): void
    {
        $coupons = [
            [
                'code' => 'WELCOME10',
                'name' => 'خصم الترحيب',
                'description' => 'خصم 10% للعملاء الجدد على أول طلب',
                'type' => 'percentage',
                'value' => 10.00,
                'min_order_amount' => 100.00,
                'max_discount' => 50.00,
                'usage_limit' => 500,
                'usage_limit_per_user' => 1,
                'used_count' => 0,
                'applicable_products' => null,
                'applicable_categories' => null,
                'excluded_products' => null,
                'applicable_clients' => null,
                'is_first_order_only' => true,
                'start_date' => now(),
                'end_date' => now()->addMonths(6),
                'is_active' => true
            ],
            [
                'code' => 'SAVE20',
                'name' => 'وفر 20 ريال',
                'description' => 'خصم ثابت 20 ريال على الطلبات أكثر من 200 ريال',
                'type' => 'fixed',
                'value' => 20.00,
                'min_order_amount' => 200.00,
                'max_discount' => null,
                'usage_limit' => 1000,
                'usage_limit_per_user' => 5,
                'used_count' => 0,
                'applicable_products' => null,
                'applicable_categories' => null,
                'excluded_products' => null,
                'applicable_clients' => null,
                'is_first_order_only' => false,
                'start_date' => now(),
                'end_date' => now()->addMonths(3),
                'is_active' => true
            ],
            [
                'code' => 'FREESHIP',
                'name' => 'شحن مجاني',
                'description' => 'شحن مجاني على جميع الطلبات',
                'type' => 'free_shipping',
                'value' => 0.00,
                'min_order_amount' => 50.00,
                'max_discount' => null,
                'usage_limit' => 200,
                'usage_limit_per_user' => 3,
                'used_count' => 0,
                'applicable_products' => null,
                'applicable_categories' => null,
                'excluded_products' => null,
                'applicable_clients' => null,
                'is_first_order_only' => false,
                'start_date' => now(),
                'end_date' => now()->addMonth(),
                'is_active' => true
            ],
            [
                'code' => 'SUMMER25',
                'name' => 'خصم الصيف الكبير',
                'description' => 'خصم 25% على المنتجات المختارة',
                'type' => 'percentage',
                'value' => 25.00,
                'min_order_amount' => 300.00,
                'max_discount' => 100.00,
                'usage_limit' => 100,
                'usage_limit_per_user' => 2,
                'used_count' => 0,
                'applicable_products' => json_encode([1, 2, 3, 4, 5]), // أرقام المنتجات التجريبية
                'applicable_categories' => json_encode([1, 2]), // أرقام الفئات التجريبية
                'excluded_products' => null,
                'applicable_clients' => null,
                'is_first_order_only' => false,
                'start_date' => Carbon::parse('2026-06-01'),
                'end_date' => Carbon::parse('2026-08-31'),
                'is_active' => false // سيتم تفعيله في الصيف
            ],
            [
                'code' => 'VIP15',
                'name' => 'خصم VIP',
                'description' => 'خصم خاص للعملاء المميزين',
                'type' => 'percentage',
                'value' => 15.00,
                'min_order_amount' => 150.00,
                'max_discount' => 75.00,
                'usage_limit' => null, // بلا حدود
                'usage_limit_per_user' => 10,
                'used_count' => 0,
                'applicable_products' => null,
                'applicable_categories' => null,
                'excluded_products' => json_encode([]), // لا يوجد منتجات مستثناة
                'applicable_clients' => json_encode([]), // سيتم تحديد العملاء المميزين لاحقاً
                'is_first_order_only' => false,
                'start_date' => now(),
                'end_date' => now()->addYear(),
                'is_active' => true
            ],
            [
                'code' => 'MOBILE50',
                'name' => 'خصم الهواتف',
                'description' => 'خصم 50 ريال على الهواتف الذكية',
                'type' => 'fixed',
                'value' => 50.00,
                'min_order_amount' => 500.00,
                'max_discount' => null,
                'usage_limit' => 50,
                'usage_limit_per_user' => 1,
                'used_count' => 0,
                'applicable_products' => null,
                'applicable_categories' => json_encode([1]), // فئة الهواتف الذكية مثلاً
                'excluded_products' => null,
                'applicable_clients' => null,
                'is_first_order_only' => false,
                'start_date' => now(),
                'end_date' => now()->addMonths(2),
                'is_active' => true
            ],
            [
                'code' => 'BLACKFRIDAY',
                'name' => 'الجمعة البيضاء',
                'description' => 'خصم 30% على جميع المنتجات - الجمعة البيضاء',
                'type' => 'percentage',
                'value' => 30.00,
                'min_order_amount' => 200.00,
                'max_discount' => 150.00,
                'usage_limit' => 1000,
                'usage_limit_per_user' => 3,
                'used_count' => 0,
                'applicable_products' => null,
                'applicable_categories' => null,
                'excluded_products' => null,
                'applicable_clients' => null,
                'is_first_order_only' => false,
                'start_date' => Carbon::parse('2026-11-29'),
                'end_date' => Carbon::parse('2026-12-02'),
                'is_active' => false // سيتم تفعيله في موعده
            ],
            [
                'code' => 'RAMADAN2026',
                'name' => 'عروض رمضان',
                'description' => 'خصم 20% احتفالاً بشهر رمضان المبارك',
                'type' => 'percentage',
                'value' => 20.00,
                'min_order_amount' => 100.00,
                'max_discount' => 80.00,
                'usage_limit' => 2000,
                'usage_limit_per_user' => 5,
                'used_count' => 0,
                'applicable_products' => null,
                'applicable_categories' => null,
                'excluded_products' => null,
                'applicable_clients' => null,
                'is_first_order_only' => false,
                'start_date' => Carbon::parse('2026-02-28'), // تواريخ تقديرية لرمضان 2026
                'end_date' => Carbon::parse('2026-03-30'),
                'is_active' => false // سيتم تفعيله في رمضان
            ],
            [
                'code' => 'STUDENT15',
                'name' => 'خصم الطلاب',
                'description' => 'خصم خاص للطلاب والطالبات',
                'type' => 'percentage',
                'value' => 15.00,
                'min_order_amount' => 80.00,
                'max_discount' => 40.00,
                'usage_limit' => null,
                'usage_limit_per_user' => 1,
                'used_count' => 0,
                'applicable_products' => null,
                'applicable_categories' => json_encode([2, 3, 4]), // فئات تعليمية مثلاً
                'excluded_products' => null,
                'applicable_clients' => null, // سيتم تحديد الطلاب لاحقاً
                'is_first_order_only' => false,
                'start_date' => now(),
                'end_date' => now()->addMonths(12),
                'is_active' => true
            ],
            [
                'code' => 'BULK100',
                'name' => 'خصم الكمية',
                'description' => 'خصم 100 ريال للطلبات الكبيرة',
                'type' => 'fixed',
                'value' => 100.00,
                'min_order_amount' => 1000.00,
                'max_discount' => null,
                'usage_limit' => 25,
                'usage_limit_per_user' => 2,
                'used_count' => 0,
                'applicable_products' => null,
                'applicable_categories' => null,
                'excluded_products' => null,
                'applicable_clients' => null,
                'is_first_order_only' => false,
                'start_date' => now(),
                'end_date' => now()->addMonths(6),
                'is_active' => true
            ]
        ];

        foreach ($coupons as $coupon) {
            DB::table('store_coupons')->updateOrInsert(
                ['code' => $coupon['code']],
                array_merge($coupon, [
                    'created_at' => now(),
                    'updated_at' => now()
                ])
            );
        }

        $this->command->info('تم إدراج كوبونات المتجر بنجاح (' . count($coupons) . ' كوبون)');
    }
}