<?php

namespace Database\Seeders;

use Database\Seeders\Concerns\SeedsMissingRows;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * العطلُ الرسمية في المملكة العربية السعودية — قائمةٌ جاهزة تُسند للموظفين.
 *
 * حسب اللائحة التنفيذية لنظام العمل (القطاع الخاص):
 *   • يوم التأسيس — 22 فبراير، كل سنة (تتكرر تلقائياً).
 *   • اليوم الوطني — 23 سبتمبر، كل سنة (تتكرر تلقائياً).
 *   • عيد الفطر — أربعة أيام: من 1 إلى 4 شوال.
 *   • عيد الأضحى — أربعة أيام: يوم عرفة (9 ذو الحجة) إلى 12 ذو الحجة.
 *
 * العيدان بالتقويم الهجري فيتغيّر موعدهما الميلادي كل سنة؛ التواريخ أدناه
 * حسب تقويم أم القرى، وقد يُعلَن العيد بالرؤية قبلها أو بعدها بيوم — يُعدَّل
 * اليومُ حينها من شاشة «قوائم العطل».
 *
 * آمنٌ للإعادة من «تحديث النظام»: يُنشئ القائمة إن لم توجد، ويضيف العطلة
 * الناقصة وحدها (بالاسم والتاريخ)، ولا يعدّل ولا يحذف عطلةً قائمة — أي
 * تعديلٍ أجراه صاحب النظام على تاريخٍ أو اسم يبقى.
 */
class SaudiPublicHolidaysSeeder extends Seeder
{
    use SeedsMissingRows;

    public const LIST_NAME = 'العطل الرسمية - السعودية';

    /**
     * أول شوال ويوم عرفة لكل سنةٍ هجرية — تقويم أم القرى.
     *
     * @var array<int, array{fitr: string, arafah: string}>
     */
    private const EIDS = [
        1447 => ['fitr' => '2026-03-20', 'arafah' => '2026-05-26'],
        1448 => ['fitr' => '2027-03-10', 'arafah' => '2027-05-15'],
        1449 => ['fitr' => '2028-02-27', 'arafah' => '2028-05-04'],
        1450 => ['fitr' => '2029-02-15', 'arafah' => '2029-04-23'],
        1451 => ['fitr' => '2030-02-05', 'arafah' => '2030-04-12'],
    ];

    public function run(): void
    {
        if (! Schema::hasTable('holiday_lists') || ! Schema::hasTable('holidays')) {
            $this->command?->warn('جداول العطل غير موجودة — شغّل الترحيلات أولاً.');

            return;
        }

        $listId = DB::table('holiday_lists')->where('name', self::LIST_NAME)->value('id');
        if (! $listId) {
            $listId = DB::table('holiday_lists')->insertGetId([
                'name' => self::LIST_NAME,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $rows = [
            $this->yearly($listId, 'يوم التأسيس', 2, 22),
            $this->yearly($listId, 'اليوم الوطني', 9, 23),
        ];

        $fitrDays = ['اليوم الأول', 'اليوم الثاني', 'اليوم الثالث', 'اليوم الرابع'];
        $adhaDays = ['يوم عرفة', 'اليوم الأول', 'اليوم الثاني', 'اليوم الثالث'];

        foreach (self::EIDS as $hijriYear => $dates) {
            // عيدٌ انقضى لا يُزرع — القائمة لما سيأتي
            if ($dates['arafah'] < now()->subYear()->toDateString()) {
                continue;
            }

            foreach ($fitrDays as $i => $label) {
                $rows[] = $this->once($listId, "عيد الفطر {$hijriYear} - {$label}", date('Y-m-d', strtotime($dates['fitr']." +{$i} day")));
            }
            foreach ($adhaDays as $i => $label) {
                $rows[] = $this->once($listId, "عيد الأضحى {$hijriYear} - {$label}", date('Y-m-d', strtotime($dates['arafah']." +{$i} day")));
            }
        }

        // المفتاح: القائمة + الاسم — عطلةٌ عدّل صاحبُ النظام تاريخها لا تُزرع ثانيةً بتاريخها القديم
        $seeded = $this->seedMissingRows('holidays', $rows, ['holiday_list_id', 'named']);

        $this->reportSeeded($seeded, 'العطل الرسمية السعودية');
    }

    private function yearly(int $listId, string $name, int $month, int $day): array
    {
        return [
            'holiday_list_id' => $listId,
            'named' => $name,
            'holiday_date' => null,
            'recurrence_type' => 'yearly',
            'recurrence_day_of_week' => null,
            'recurrence_day_of_month' => $day,
            'recurrence_month' => $month,
            'is_active' => 1,
            'is_paid' => 1,
        ];
    }

    private function once(int $listId, string $name, string $date): array
    {
        return [
            'holiday_list_id' => $listId,
            'named' => $name,
            'holiday_date' => $date,
            'recurrence_type' => 'once',
            'recurrence_day_of_week' => null,
            'recurrence_day_of_month' => null,
            'recurrence_month' => null,
            'is_active' => 1,
            'is_paid' => 1,
        ];
    }
}
