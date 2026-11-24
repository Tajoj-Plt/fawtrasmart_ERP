<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * وسمُ ستّةِ أعمدةٍ متقاعدةٍ بتعليقٍ في المخطَّط نفسه (2026-10-04).
 *
 * **لا تُحذف** — القاعدةُ في هذا المستودع: يُتقاعَد العمودُ ولا يُسقَط،
 * فإسقاطُه يُفقد بياناتَ من رقّى من نسخةٍ أقدم ولا يُعاد.
 *
 * لكنّ العمودَ الخاملَ بلا علامةٍ فخٌّ لمن يقرأ الجدول بعد سنة: يراه
 * فيبني عليه. وتعليقُ العمود يُقرأ في أيّ أداةٍ تعرض المخطَّط (phpMyAdmin،
 * `SHOW FULL COLUMNS`، مُصدِّرات التوثيق) — فيصل التحذيرُ إلى حيث يُنظَر.
 *
 * والأعمدةُ الستّة:
 *
 *   `attendance_settings.location_check_enabled` و`ip_check_enabled`
 *   و`employee_attendance_configs` نظيراهما — أربعةٌ لا يقرؤها سطرٌ واحد
 *   في الشيفرة كلِّها (ثبت ذلك بمسحٍ شامل). وفحصُ الموقع الحقيقيُّ ليس
 *   مفتاحاً عامّاً: هو على «المكان» (`attendance_geofences.require_geo`
 *   ونصفُ القطر والدقّة)، وعلى الجهاز (`attendance_kiosks.require_location`)،
 *   وفي محدِّدات الحضور (`AttendanceEntryGuard`). وقد أُسقطت من
 *   `$fillable`/`$casts` في النموذجَين فلا تُسنَد بالجملة.
 *
 *   `employees.account_id` و`advance_account_id` — صفرُ صفٍّ مملوءٌ فيهما،
 *   ولا `$fillable` ولا علاقةَ تشير إليهما. المستعملان فعلاً
 *   `salary_payable_account_id` و`employee_advance_account_id`.
 *
 * التعليقُ وحدَه يُغيَّر: النوعُ والسماحُ بالفراغ والافتراضُ تبقى كما هي،
 * فلا تُمسّ بياناتٌ ولا يُكسر قيدٌ.
 */
return new class extends Migration
{
    /** [الجدول، العمود، تعريفُه كما هو، التعليق] */
    private const COLUMNS = [
        ['attendance_settings', 'location_check_enabled', 'TINYINT(1) NOT NULL DEFAULT 0',
            'متقاعد 2026-10-04: لا يقرؤه أحد. فحصُ الموقع على المكان (attendance_geofences.require_geo) وعلى الجهاز (attendance_kiosks.require_location).'],
        ['attendance_settings', 'ip_check_enabled', 'TINYINT(1) NOT NULL DEFAULT 0',
            'متقاعد 2026-10-04: لا يقرؤه أحد. فحصُ الشبكة في attendance_geofences.require_network.'],
        ['employee_attendance_configs', 'location_check_enabled', 'TINYINT(1) NULL DEFAULT NULL',
            'متقاعد 2026-10-04: لا يقرؤه أحد — نظيرُ عمود إعدادات المنشأة.'],
        ['employee_attendance_configs', 'ip_check_enabled', 'TINYINT(1) NULL DEFAULT NULL',
            'متقاعد 2026-10-04: لا يقرؤه أحد — نظيرُ عمود إعدادات المنشأة.'],
        ['employees', 'account_id', 'BIGINT(20) UNSIGNED NULL DEFAULT NULL',
            'متقاعد 2026-10-04: خاملٌ بصفر صفّ. المستعمل salary_payable_account_id.'],
        ['employees', 'advance_account_id', 'BIGINT(20) UNSIGNED NULL DEFAULT NULL',
            'متقاعد 2026-10-04: خاملٌ بصفر صفّ. المستعمل employee_advance_account_id.'],
    ];

    public function up(): void
    {
        $this->applyComments(true);
    }

    /** التراجعُ يمحو التعليقَ ويترك العمودَ — لم يُغيَّر غيرُه. */
    public function down(): void
    {
        $this->applyComments(false);
    }

    private function applyComments(bool $withComment): void
    {
        // التعليقُ على العمود خاصيّةُ MySQL — تُتخطّى بهدوءٍ على غيرها (sqlite في الاختبارات)
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        foreach (self::COLUMNS as [$table, $column, $definition, $comment]) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                continue;
            }

            DB::statement(sprintf(
                'ALTER TABLE `%s` MODIFY `%s` %s COMMENT %s',
                $table,
                $column,
                $definition,
                $withComment ? DB::getPdo()->quote($comment) : "''"
            ));
        }
    }
};
