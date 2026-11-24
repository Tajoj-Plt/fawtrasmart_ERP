<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Store\ShippingMethod;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Shipping Methods Seeder
 * سيدر طرق الشحن والتوصيل
 */
class ShippingMethodsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * إدراج طرق الشحن الأساسية
     */ 
    public function run(): void
    {
        $shippingMethods = [
            [
                'name' => 'توصيل عادي',
                'code' => 'STANDARD',
                'description' => 'توصيل عادي خلال 3-5 أيام عمل داخل المدن الرئيسية',
                'logo' => '/images/shipping/standard.png',
                'type' => 'flat_rate',
                'cost' => 25.00,
                'min_order_amount' => null,
                'free_shipping_threshold' => 200.00,
                'estimated_days_min' => 3,
                'estimated_days_max' => 5,
                'available_cities' => json_encode([
                    'الرياض', 'جدة', 'الدمام', 'مكة المكرمة', 'المدينة المنورة',
                    'الطائف', 'بريدة', 'تبوك', 'القصيم', 'حائل', 'الأحساء',
                    'نجران', 'جازان', 'ابها', 'ينبع', 'الخبر'
                ]),
                'available_regions' => json_encode([
                    'منطقة الرياض', 'منطقة مكة المكرمة', 'المنطقة الشرقية',
                    'منطقة المدينة المنورة', 'منطقة القصيم', 'منطقة عسير',
                    'منطقة تبوك', 'منطقة حائل', 'منطقة الحدود الشمالية',
                    'منطقة جازان', 'منطقة نجران', 'منطقة الباحة', 'منطقة الجوف'
                ]),
                'is_active' => true,
                'sort_order' => 1,
                'account_code' => '21121',
            ],
            [
                'name' => 'توصيل سريع',
                'code' => 'EXPRESS',
                'description' => 'توصيل سريع خلال 1-2 أيام عمل في المدن الرئيسية',
                'logo' => '/images/shipping/express.png',
                'type' => 'flat_rate',
                'cost' => 35.00,
                'min_order_amount' => null,
                'free_shipping_threshold' => 500.00,
                'estimated_days_min' => 1,
                'estimated_days_max' => 2,
                'available_cities' => json_encode([
                    'الرياض', 'جدة', 'الدمام', 'مكة المكرمة', 'المدينة المنورة',
                    'الطائف', 'الخبر', 'ينبع'
                ]),
                'available_regions' => json_encode([
                    'منطقة الرياض', 'منطقة مكة المكرمة', 'المنطقة الشرقية',
                    'منطقة المدينة المنورة'
                ]),
                'is_active' => true,
                'sort_order' => 2,
                'account_code' => '21122',
            ],
            [
                'name' => 'توصيل فوري',
                'code' => 'SAME_DAY',
                'description' => 'توصيل في نفس اليوم لطلبات ما قبل الساعة 12 ظهراً',
                'logo' => '/images/shipping/same-day.png',
                'type' => 'flat_rate',
                'cost' => 50.00,
                'min_order_amount' => 100.00,
                'free_shipping_threshold' => 1000.00,
                'estimated_days_min' => 0,
                'estimated_days_max' => 0,
                'available_cities' => json_encode([
                    'الرياض', 'جدة', 'الدمام', 'الخبر'
                ]),
                'available_regions' => json_encode([
                    'منطقة الرياض', 'منطقة مكة المكرمة', 'المنطقة الشرقية'
                ]),
                'is_active' => true,
                'sort_order' => 3,
                'account_code' => '21123',
            ],
            [
                'name' => 'شحن مجاني',
                'code' => 'FREE',
                'description' => 'شحن مجاني للطلبات أكثر من 200 ريال',
                'logo' => '/images/shipping/free.png',
                'type' => 'free',
                'cost' => 0.00,
                'min_order_amount' => 200.00,
                'free_shipping_threshold' => 200.00,
                'estimated_days_min' => 3,
                'estimated_days_max' => 5,
                'available_cities' => json_encode([
                    'الرياض', 'جدة', 'الدمام', 'مكة المكرمة', 'المدينة المنورة',
                    'الطائف', 'بريدة', 'تبوك', 'القصيم', 'حائل', 'الأحساء'
                ]),
                'available_regions' => json_encode([
                    'منطقة الرياض', 'منطقة مكة المكرمة', 'المنطقة الشرقية',
                    'منطقة المدينة المنورة', 'منطقة القصيم'
                ]),
                'is_active' => true,
                'sort_order' => 4,
                'account_code' => '21124',
            ],
            [
                'name' => 'توصيل للمناطق النائية',
                'code' => 'REMOTE',
                'description' => 'توصيل للمناطق النائية والقرى الصغيرة',
                'logo' => '/images/shipping/remote.png',
                'type' => 'flat_rate',
                'cost' => 45.00,
                'min_order_amount' => 150.00,
                'free_shipping_threshold' => 400.00,
                'estimated_days_min' => 5,
                'estimated_days_max' => 10,
                'available_cities' => json_encode([
                    'القريات', 'عرعر', 'رفحاء', 'طريف', 'العويقيلة',
                    'وادي الدواسر', 'الأفلاج', 'حوطة بني تميم', 'المجمعة',
                    'الزلفي', 'شقراء', 'رماح', 'ثادق', 'الدوادمي'
                ]),
                'available_regions' => json_encode([
                    'منطقة الحدود الشمالية', 'منطقة الجوف', 'منطقة نجران',
                    'منطقة الباحة', 'أجزاء من منطقة الرياض'
                ]),
                'is_active' => true,
                'sort_order' => 5,
                'account_code' => '21125',
            ],
            [
                'name' => 'الاستلام من الفرع',
                'code' => 'PICKUP',
                'description' => 'استلام الطلب من أحد فروعنا المتاحة',
                'logo' => '/images/shipping/pickup.png',
                'type' => 'free',
                'cost' => 0.00,
                'min_order_amount' => null,
                'free_shipping_threshold' => 0.00,
                'estimated_days_min' => 1,
                'estimated_days_max' => 2,
                'available_cities' => json_encode([
                    'الرياض', 'جدة', 'الدمام', 'مكة المكرمة'
                ]),
                'available_regions' => json_encode([
                    'منطقة الرياض', 'منطقة مكة المكرمة', 'المنطقة الشرقية'
                ]),
                'is_active' => true,
                'sort_order' => 6,
                'account_code' => '21126',
            ]
        ];

        // التحقق من وجود عمود account_id في جدول shipping_methods
        $hasAccountIdColumn = Schema::hasColumn('shipping_methods', 'account_id');

        foreach ($shippingMethods as $method) {
            // استخراج كود الحساب
            $accountCode = $method['account_code'];
            unset($method['account_code']);
            
            $accountId = null;
            
            // إنشاء حساب محاسبي لطريقة الشحن
            if (class_exists(Account::class)) {
                $account = $this->createShippingAccount($method['name'], $accountCode);
                $accountId = $account ? $account->id : null;
            }
            
            // إضافة account_id إذا كان العمود موجوداً
            if ($hasAccountIdColumn && $accountId) {
                $method['account_id'] = $accountId;
            }

            DB::table('shipping_methods')->updateOrInsert(
                ['code' => $method['code']],
                array_merge($method, [
                    'created_at' => now(),
                    'updated_at' => now()
                ])
            );
        }

        $this->command->info('تم إدراج طرق الشحن بنجاح (' . count($shippingMethods) . ' طريقة شحن)');
    }

    /**
     * إنشاء حساب محاسبي لطريقة الشحن
     * الحساب يكون تحت حساب "شركات الشحن" (ID: 30, Code: 2112)
     */
    private function createShippingAccount(string $name, string $code): ?Account
    {
        try {
            // البحث عن حساب شركات الشحن الرئيسي (ID: 30)
            $parentAccount = Account::where('code', '2112')->first();
            
            if (!$parentAccount) {
                $this->command->warn("حساب شركات الشحن الرئيسي (2112) غير موجود");
                return null;
            }

            // التحقق من وجود الحساب مسبقاً
            $existingAccount = Account::where('code', $code)->first();
            if ($existingAccount) {
                return $existingAccount;
            }

            // إنشاء حساب جديد
            $account = Account::create([
                'name' => $name,
                'code' => $code,
                'parent_id' => $parentAccount->id,
                'type' => 'sub',
                'account_type' => 'liabilities',
                'balance_type' => 'credit',
                'is_system' => false,
                'is_active' => true,
                'balance' => 0,
            ]);

            $this->command->info("تم إنشاء حساب: {$name} ({$code})");
            
            return $account;
        } catch (\Exception $e) {
            $this->command->error("خطأ في إنشاء حساب {$name}: " . $e->getMessage());
            return null;
        }
    }
}