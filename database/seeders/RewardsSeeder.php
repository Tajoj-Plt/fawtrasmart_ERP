<?php

namespace Database\Seeders;

use Database\Seeders\Concerns\SeedsMissingRows;
use Illuminate\Database\Seeder;

class RewardsSeeder extends Seeder
{
    use SeedsMissingRows;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $rewards = [
            // مكافآت العملاء
            [
                'name' => 'خصم 5%',
                'name_en' => '5% Discount',
                'description' => 'خصم 5% على المشتريات بحد أقصى 50 ريال',
                'user_type' => 'client',
                'points_required' => 100,
                'reward_type' => 'discount_percentage',
                'reward_value' => 5.00,
                'max_discount_value' => 50.00,
                'min_order_amount' => 100.00,
                'validity_days' => 30,
                'quantity_available' => null,
                'is_active' => true,
            ],
            [
                'name' => 'خصم 10%',
                'name_en' => '10% Discount',
                'description' => 'خصم 10% على المشتريات بحد أقصى 100 ريال',
                'user_type' => 'client',
                'points_required' => 200,
                'reward_type' => 'discount_percentage',
                'reward_value' => 10.00,
                'max_discount_value' => 100.00,
                'min_order_amount' => 200.00,
                'validity_days' => 30,
                'quantity_available' => null,
                'is_active' => true,
            ],
            [
                'name' => 'شحن مجاني',
                'name_en' => 'Free Shipping',
                'description' => 'شحن مجاني على الطلبات',
                'user_type' => 'client',
                'points_required' => 150,
                'reward_type' => 'free_shipping',
                'reward_value' => null,
                'max_discount_value' => null,
                'min_order_amount' => null,
                'validity_days' => null,
                'quantity_available' => null,
                'is_active' => true,
            ],
            [
                'name' => 'قسيمة 50 ريال',
                'name_en' => '50 SAR Voucher',
                'description' => 'قسيمة شرائية بقيمة 50 ريال صالحة لمدة 60 يوم',
                'user_type' => 'client',
                'points_required' => 500,
                'reward_type' => 'voucher',
                'reward_value' => 50.00,
                'max_discount_value' => null,
                'min_order_amount' => 50.00,
                'validity_days' => 60,
                'quantity_available' => 100,
                'is_active' => true,
            ],
            [
                'name' => 'قسيمة 100 ريال',
                'name_en' => '100 SAR Voucher',
                'description' => 'قسيمة شرائية بقيمة 100 ريال صالحة لمدة 90 يوم',
                'user_type' => 'client',
                'points_required' => 1000,
                'reward_type' => 'voucher',
                'reward_value' => 100.00,
                'max_discount_value' => null,
                'min_order_amount' => 100.00,
                'validity_days' => 90,
                'quantity_available' => 50,
                'is_active' => true,
            ],
            [
                'name' => 'هدية خاصة',
                'name_en' => 'Special Gift',
                'description' => 'هدية مميزة من اختيارنا',
                'user_type' => 'client',
                'points_required' => 300,
                'reward_type' => 'gift',
                'reward_value' => null,
                'max_discount_value' => null,
                'min_order_amount' => null,
                'validity_days' => null,
                'quantity_available' => 20,
                'is_active' => true,
            ],

            // مكافآت الموظفين
            [
                'name' => 'إجازة نصف يوم',
                'name_en' => 'Half Day Leave',
                'description' => 'إجازة نصف يوم مدفوعة',
                'user_type' => 'employee',
                'points_required' => 500,
                'reward_type' => 'leave_day',
                'reward_value' => 0.5,
                'max_discount_value' => null,
                'min_order_amount' => null,
                'validity_days' => null,
                'quantity_available' => null,
                'is_active' => true,
            ],
            [
                'name' => 'إجازة يوم كامل',
                'name_en' => 'Full Day Leave',
                'description' => 'إجازة يوم كامل مدفوعة',
                'user_type' => 'employee',
                'points_required' => 1000,
                'reward_type' => 'leave_day',
                'reward_value' => 1.0,
                'max_discount_value' => null,
                'min_order_amount' => null,
                'validity_days' => null,
                'quantity_available' => null,
                'is_active' => true,
            ],
            [
                'name' => 'مكافأة 100 ريال',
                'name_en' => '100 SAR Bonus',
                'description' => 'مكافأة نقدية 100 ريال',
                'user_type' => 'employee',
                'points_required' => 1000,
                'reward_type' => 'cash',
                'reward_value' => 100.00,
                'max_discount_value' => null,
                'min_order_amount' => null,
                'validity_days' => null,
                'quantity_available' => null,
                'is_active' => true,
            ],
            [
                'name' => 'مكافأة 200 ريال',
                'name_en' => '200 SAR Bonus',
                'description' => 'مكافأة نقدية 200 ريال',
                'user_type' => 'employee',
                'points_required' => 2000,
                'reward_type' => 'cash',
                'reward_value' => 200.00,
                'max_discount_value' => null,
                'min_order_amount' => null,
                'validity_days' => null,
                'quantity_available' => null,
                'is_active' => true,
            ],
            [
                'name' => 'تذكرة سينما',
                'name_en' => 'Movie Ticket',
                'description' => 'تذكرة سينما مجانية',
                'user_type' => 'employee',
                'points_required' => 300,
                'reward_type' => 'gift',
                'reward_value' => null,
                'max_discount_value' => null,
                'min_order_amount' => null,
                'validity_days' => null,
                'quantity_available' => 30,
                'is_active' => true,
            ],
            [
                'name' => 'شهادة تقدير',
                'name_en' => 'Certificate of Appreciation',
                'description' => 'شهادة تقدير رسمية',
                'user_type' => 'employee',
                'points_required' => 150,
                'reward_type' => 'gift',
                'reward_value' => null,
                'max_discount_value' => null,
                'min_order_amount' => null,
                'validity_days' => null,
                'quantity_available' => null,
                'is_active' => true,
            ],
        ];

        $seeded = $this->seedMissingRows('rewards', $rewards, ['name', 'user_type']);

        $this->reportSeeded($seeded, 'المكافآت');
    }
}
