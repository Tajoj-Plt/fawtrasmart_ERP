<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ReferralSettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = [
            [
                'referrer_points' => 50,
                'referred_points' => 25,
                'confirmation_type' => 'first_purchase',
                'confirmation_value' => 100.00,
                'max_referrals_per_month' => 10,
                'bonus_milestones' => json_encode([
                    ['count' => 5, 'points' => 100],
                    ['count' => 10, 'points' => 250],
                    ['count' => 20, 'points' => 600],
                ]),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        // صفٌّ واحد يحكم النظام كلَّه: وجودُ أيّ صفٍّ يعني أنّ الإعداد
        // مضبوطٌ من شاشته، فالزرعُ للفراغ وحده
        if (DB::table('referral_settings')->exists()) {
            $this->command?->info('إعدادات الإحالة مضبوطةٌ مسبقاً — لا جديد.');

            return;
        }

        DB::table('referral_settings')->insert($settings);

        $this->command?->info('تمّ زرع إعدادات الإحالة.');
    }
}
