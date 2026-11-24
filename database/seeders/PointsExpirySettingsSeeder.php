<?php

namespace Database\Seeders;

use Database\Seeders\Concerns\SeedsMissingRows;
use Illuminate\Database\Seeder;

class PointsExpirySettingsSeeder extends Seeder
{
    use SeedsMissingRows;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = [
            // إعدادات العملاء
            [
                'user_type' => 'client',
                'expiry_type' => 'fifo',
                'expiry_days' => 365, // سنة واحدة
                'warning_days' => 30,
                'reminder_days' => json_encode([30, 14, 7, 1]),
                'exclude_tiers' => json_encode([4]), // استثناء مستوى البلاتيني
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            // إعدادات الموظفين
            [
                'user_type' => 'employee',
                'expiry_type' => 'never',
                'expiry_days' => null,
                'warning_days' => 0,
                'reminder_days' => json_encode([]),
                'exclude_tiers' => null,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        $seeded = $this->seedMissingRows('points_expiry_settings', $settings, 'user_type', false);

        $this->reportSeeded($seeded, 'إعدادات انتهاء النقاط');
    }
}
