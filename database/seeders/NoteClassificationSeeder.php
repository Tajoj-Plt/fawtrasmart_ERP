<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\NoteClassification;

class NoteClassificationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $classifications = [
            // --- تحصيل (Collection) ---
            ['name' => 'تحصيل: رأس الشهر القادم', 'frequency' => 'daily', 'day_of_week' => '1', 'is_automated' => 1],
            ['name' => 'تحصيل: أول الأسبوع القادم', 'frequency' => 'daily', 'day_of_week' => 'Sunday', 'is_automated' => 1],
            ['name' => 'تحصيل: نهاية الأسبوع الحالي', 'frequency' => 'daily', 'day_of_week' => 'Thursday', 'is_automated' => 1],
            ['name' => 'تحصيل: بعد أسبوع', 'frequency' => 'weekly', 'is_automated' => 1],
            ['name' => 'تحصيل: مراجعة مديونية (3 أيام)', 'frequency' => 'custom_days', 'interval_days' => 3, 'is_automated' => 1],

            // --- مخزون (Stock) ---
            ['name' => 'مخزون: الاستند مليان (أسبوعين)', 'frequency' => 'bi_weekly', 'is_automated' => 1],
            ['name' => 'مخزون: بضاعة قاربت على النفاذ (أسبوع)', 'frequency' => 'weekly', 'is_automated' => 1],
            ['name' => 'مخزون: طلب تنزيل جديد (غداً)', 'frequency' => 'daily', 'is_automated' => 1],
            ['name' => 'مخزون: طلب تنزيل جديد (3 أيام)', 'frequency' => 'custom_days', 'interval_days' => 3, 'is_automated' => 1],

            // --- تواجد ومتابعة (Presence & Follow-up) ---
            ['name' => 'متابعة: المسؤول غير موجود (يومين)', 'frequency' => 'custom_days', 'interval_days' => 2, 'is_automated' => 1],
            ['name' => 'متابعة: المحل مغلق (أسبوع)', 'frequency' => 'weekly', 'is_automated' => 1],
            ['name' => 'متابعة: تعال مرة ثانية (3 أيام)', 'frequency' => 'custom_days', 'interval_days' => 3, 'is_automated' => 1],
            ['name' => 'متابعة: تعال مرة ثانية (أسبوع)', 'frequency' => 'weekly', 'is_automated' => 1],

            // --- مواعيد مؤجلة (Postponed) ---
            ['name' => 'تأجيل: الأسبوع القادم (نفس اليوم)', 'frequency' => 'weekly', 'is_automated' => 1],
            ['name' => 'تأجيل: الشهر القادم (نفس اليوم)', 'frequency' => 'monthly', 'is_automated' => 1],
        ];

        foreach ($classifications as $data) {
            NoteClassification::updateOrCreate(['name' => $data['name']], $data);
        }

        // Add 1-90 days as generic options
        for ($i = 1; $i <= 90; $i++) {
            $label = "زيارة: بعد $i يوم";
            NoteClassification::updateOrCreate(
                ['name' => $label],
                [
                    'frequency' => 'custom_days',
                    'interval_days' => $i,
                    'is_automated' => 1
                ]
            );
        }
    }
}
