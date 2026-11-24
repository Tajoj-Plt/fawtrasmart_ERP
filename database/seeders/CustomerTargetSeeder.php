<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CustomerTargetSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * الأهداف مرتبة من الأعلى أولوية (الأفضل) إلى الأدنى.
     * الأولوية Priority: كلما كانت أعلى، يُختار هذا الهدف أولاً عند تطابق أكثر من هدف.
     */
    public function run(): void
    {
        // تعطيل قيود المفاتيح الأجنبية مؤقتاً لتمكين الحذف
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table('customer_targets')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $targets = [
            // ===================================================
            // 🥇 العميل الذهبي
            // ===================================================
            [
                'name'                    => 'العميل الذهبي',
                'color'                   => '#f59e0b',  // ذهبي لامع
                'description'             => 'معدل سداد شهري يبلغ 192 ريال فما فوق',
                'is_active'               => true,
                'priority'                => 140,  // الأعلى أولوية
                'min_sales'               => 192.00, // معدل السداد الشهري
                'min_collections'         => 0.00,
                'min_collection_ratio'    => 0.00,
                'max_avg_collection_days' => 0,
                'max_late_ratio'          => 100.00,
                'max_debt'                => 999999.00,
                'min_invoices_count'      => 0,
                'sort_order'              => 1,
                'created_at'              => now(),
                'updated_at'              => now(),
            ],

            // ===================================================
            // 🥈 العميل الممتاز
            // ===================================================
            [
                'name'                    => 'العميل الممتاز',
                'color'                   => '#10b981',  // أخضر زمردي ممتاز
                'description'             => 'معدل سداد شهري من 150 إلى 191 ريال',
                'is_active'               => true,
                'priority'                => 120,
                'min_sales'               => 150.00,
                'min_collections'         => 0.00,
                'min_collection_ratio'    => 0.00,
                'max_avg_collection_days' => 0,
                'max_late_ratio'          => 100.00,
                'max_debt'                => 999999.00,
                'min_invoices_count'      => 0,
                'sort_order'              => 2,
                'created_at'              => now(),
                'updated_at'              => now(),
            ],

            // ===================================================
            // 🥉 العميل جيد جداً
            // ===================================================
            [
                'name'                    => 'العميل جيد جداً',
                'color'                   => '#6366f1',  // بنفسجي/نيلي
                'description'             => 'معدل سداد شهري من 100 إلى 149 ريال',
                'is_active'               => true,
                'priority'                => 100,
                'min_sales'               => 100.00,
                'min_collections'         => 0.00,
                'min_collection_ratio'    => 0.00,
                'max_avg_collection_days' => 0,
                'max_late_ratio'          => 100.00,
                'max_debt'                => 999999.00,
                'min_invoices_count'      => 0,
                'sort_order'              => 3,
                'created_at'              => now(),
                'updated_at'              => now(),
            ],

            // ===================================================
            // 🏅 العميل الجيد
            // ===================================================
            [
                'name'                    => 'العميل الجيد',
                'color'                   => '#3b82f6',  // أزرق
                'description'             => 'معدل سداد شهري من 80 إلى 99 ريال',
                'is_active'               => true,
                'priority'                => 80,
                'min_sales'               => 80.00,
                'min_collections'         => 0.00,
                'min_collection_ratio'    => 0.00,
                'max_avg_collection_days' => 0,
                'max_late_ratio'          => 100.00,
                'max_debt'                => 999999.00,
                'min_invoices_count'      => 0,
                'sort_order'              => 4,
                'created_at'              => now(),
                'updated_at'              => now(),
            ],

            // ===================================================
            // 🌟 مقبول
            // ===================================================
            [
                'name'                    => 'مقبول',
                'color'                   => '#14b8a6',  // تيل/تركواز
                'description'             => 'معدل سداد شهري من 50 إلى 79 ريال',
                'is_active'               => true,
                'priority'                => 60,
                'min_sales'               => 50.00,
                'min_collections'         => 0.00,
                'min_collection_ratio'    => 0.00,
                'max_avg_collection_days' => 0,
                'max_late_ratio'          => 100.00,
                'max_debt'                => 999999.00,
                'min_invoices_count'      => 0,
                'sort_order'              => 5,
                'created_at'              => now(),
                'updated_at'              => now(),
            ],

            // ===================================================
            // ⚠️ ضعيف
            // ===================================================
            [
                'name'                    => 'ضعيف',
                'color'                   => '#f97316',  // برتقالي
                'description'             => 'معدل سداد شهري من 1 إلى 49 ريال',
                'is_active'               => true,
                'priority'                => 40,
                'min_sales'               => 1.00,
                'min_collections'         => 0.00,
                'min_collection_ratio'    => 0.00,
                'max_avg_collection_days' => 0,
                'max_late_ratio'          => 100.00,
                'max_debt'                => 999999.00,
                'min_invoices_count'      => 0,
                'sort_order'              => 6,
                'created_at'              => now(),
                'updated_at'              => now(),
            ],

            // ===================================================
            // 🔴 مشكوك فية
            // ===================================================
            [
                'name'                    => 'مشكوك فية',
                'color'                   => '#ef4444',  // أحمر
                'description'             => 'العميل الذي لم يقم بأي سداد (0 ريال)',
                'is_active'               => true,
                'priority'                => 20,
                'min_sales'               => 0.00,
                'min_collections'         => 0.00,
                'min_collection_ratio'    => 0.00,
                'max_avg_collection_days' => 0,
                'max_late_ratio'          => 100.00,
                'max_debt'                => 999999.99,
                'min_invoices_count'      => 0,
                'sort_order'              => 7,
                'created_at'              => now(),
                'updated_at'              => now(),
            ],
        ];

        DB::table('customer_targets')->insert($targets);

        $this->command->info('✅ تم إنشاء ' . count($targets) . ' أهداف عملاء بنجاح!');
        $this->command->table(
            ['الاسم', 'الأولوية', 'الحد الأدنى للمبيعات', 'الحد الأدنى للتحصيلات', 'الحد الأقصى للمديونية'],
            collect($targets)->map(fn($t) => [
                $t['name'],
                $t['priority'],
                $t['min_sales'] . ' ريال',
                $t['min_collections'] . ' ريال',
                $t['max_debt'] . ' ريال',
            ])->toArray()
        );
    }
}