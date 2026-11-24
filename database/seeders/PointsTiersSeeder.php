<?php

namespace Database\Seeders;

use Database\Seeders\Concerns\SeedsMissingRows;
use Illuminate\Database\Seeder;

class PointsTiersSeeder extends Seeder
{
    use SeedsMissingRows;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tiers = [
            // مستويات العملاء
            [
                'name' => 'برونزي',
                'name_en' => 'Bronze',
                'user_type' => 'client',
                'min_points' => 0,
                'max_points' => 499,
                'color' => '#CD7F32',
                'points_multiplier' => 1.00,
                'discount_percentage' => 0.00,
                'priority_support' => false,
                'free_shipping' => false,
                'sort_order' => 1,
                'benefits' => json_encode(['features' => ['دعم أساسي', 'عروض عامة']]),
                'is_active' => true,
            ],
            [
                'name' => 'فضي',
                'name_en' => 'Silver',
                'user_type' => 'client',
                'min_points' => 500,
                'max_points' => 1999,
                'color' => '#C0C0C0',
                'points_multiplier' => 1.25,
                'discount_percentage' => 5.00,
                'priority_support' => true,
                'free_shipping' => false,
                'sort_order' => 2,
                'benefits' => json_encode(['features' => ['أولوية دعم', 'عروض حصرية', 'خصم 5%']]),
                'is_active' => true,
            ],
            [
                'name' => 'ذهبي',
                'name_en' => 'Gold',
                'user_type' => 'client',
                'min_points' => 2000,
                'max_points' => 4999,
                'color' => '#FFD700',
                'points_multiplier' => 1.50,
                'discount_percentage' => 10.00,
                'priority_support' => true,
                'free_shipping' => true,
                'sort_order' => 3,
                'benefits' => json_encode(['features' => ['شحن مجاني', 'أولوية قصوى', 'خصم 10%', 'هدايا موسمية']]),
                'is_active' => true,
            ],
            [
                'name' => 'بلاتيني',
                'name_en' => 'Platinum',
                'user_type' => 'client',
                'min_points' => 5000,
                'max_points' => null,
                'color' => '#E5E4E2',
                'points_multiplier' => 2.00,
                'discount_percentage' => 15.00,
                'priority_support' => true,
                'free_shipping' => true,
                'sort_order' => 4,
                'benefits' => json_encode(['features' => ['مدير حساب خاص', 'خصم 15%', 'وصول مبكر للعروض', 'دعوات VIP']]),
                'is_active' => true,
            ],

            // مستويات الموظفين
            [
                'name' => 'مبتدئ',
                'name_en' => 'Beginner',
                'user_type' => 'employee',
                'min_points' => 0,
                'max_points' => 999,
                'color' => '#90EE90',
                'points_multiplier' => 1.00,
                'discount_percentage' => 0.00,
                'priority_support' => false,
                'free_shipping' => false,
                'sort_order' => 1,
                'benefits' => json_encode(['features' => ['مكافآت أساسية']]),
                'is_active' => true,
            ],
            [
                'name' => 'متميز',
                'name_en' => 'Distinguished',
                'user_type' => 'employee',
                'min_points' => 1000,
                'max_points' => 4999,
                'color' => '#FFD700',
                'points_multiplier' => 1.25,
                'discount_percentage' => 0.00,
                'priority_support' => false,
                'free_shipping' => false,
                'sort_order' => 2,
                'benefits' => json_encode(['features' => ['مرونة في ساعات العمل', 'مكافآت إضافية']]),
                'is_active' => true,
            ],
            [
                'name' => 'خبير',
                'name_en' => 'Expert',
                'user_type' => 'employee',
                'min_points' => 5000,
                'max_points' => 9999,
                'color' => '#4169E1',
                'points_multiplier' => 1.50,
                'discount_percentage' => 0.00,
                'priority_support' => false,
                'free_shipping' => false,
                'sort_order' => 3,
                'benefits' => json_encode(['features' => ['أولوية في الإجازات', 'مكافآت ربع سنوية']]),
                'is_active' => true,
            ],
            [
                'name' => 'نجم',
                'name_en' => 'Star',
                'user_type' => 'employee',
                'min_points' => 10000,
                'max_points' => null,
                'color' => '#8B008B',
                'points_multiplier' => 2.00,
                'discount_percentage' => 0.00,
                'priority_support' => false,
                'free_shipping' => false,
                'sort_order' => 4,
                'benefits' => json_encode(['features' => ['مكافأة سنوية خاصة', 'برنامج تطوير مهني', 'مشاركة في صنع القرار']]),
                'is_active' => true,
            ],
        ];

        $seeded = $this->seedMissingRows('points_tiers', $tiers, ['name', 'user_type']);

        $this->reportSeeded($seeded, 'مستويات النقاط');
    }
}
