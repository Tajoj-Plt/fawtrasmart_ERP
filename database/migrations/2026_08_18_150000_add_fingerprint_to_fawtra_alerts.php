<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * تمييز التنبيهات المحلّية عن الواردة من فوترة.
 *
 * **العطل:** التنبيهات المحلّية (حدود الاستخدام التي يرصدها النظام بنفسه بين
 * مزامنتين) كانت تُخزَّن بمعرّفٍ سالب كي لا تتصادم مع معرّفات فوترة الموجبة.
 * لكن `remote_id` عمودٌ **غير مُوقَّع**، فأول تنبيه محلي سقط بـ
 * «Out of range value for column remote_id».
 *
 * والحلّ الصحيح ليس توسيع العمود ليقبل السالب — ذاك يخفي المعنى في حيلة —
 * بل التصريح بأن للتنبيه مصدرين: `remote_id` لما جاء من فوترة، و`fingerprint`
 * لما رصده النظام. والبصمة تُبنى من (الميزة، العتبة، الفترة) فتمنع تكرار
 * التنبيه بلا جدول إضافي، وتسمح بظهوره من جديد في فترة جديدة.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fawtra_alerts', function (Blueprint $table) {
            // البصمة للتنبيهات المحلّية — فريدة ومنفصلة عن معرّف فوترة
            if (! Schema::hasColumn('fawtra_alerts', 'fingerprint')) {
                $table->string('fingerprint', 120)->nullable()->unique()->after('remote_id')
                    ->comment('بصمة التنبيه المحلي: usage:feature:threshold:period');
            }

            if (! Schema::hasColumn('fawtra_alerts', 'source')) {
                $table->string('source', 20)->default('remote')->after('fingerprint')
                    ->comment('remote من لوحة فوترة | local رصده النظام');
            }
        });

        // `remote_id` صار اختيارياً: التنبيه المحلي لا معرّف بعيد له.
        //
        // يُنفَّذ بـ SQL خام لا بـ `->change()`: تغييرُ عمودٍ يحمل فهرساً
        // فريداً عبر Doctrine يُسقط الفهرس ويُعيد بناءه على بعض الإصدارات،
        // وهذا أوضح وأقصر
        try {
            \Illuminate\Support\Facades\DB::statement(
                'ALTER TABLE `fawtra_alerts` MODIFY `remote_id` BIGINT UNSIGNED NULL'
            );
        } catch (\Throwable) {
            // محرّك لا يقبل الصيغة — العمود يبقى إلزامياً والتنبيهات المحلّية
            // تستعمل البصمة مع معرّفٍ صفري
        }
    }

    public function down(): void
    {
        Schema::table('fawtra_alerts', function (Blueprint $table) {
            $table->dropUnique(['fingerprint']);
            $table->dropColumn(['fingerprint', 'source']);
        });
    }
};
