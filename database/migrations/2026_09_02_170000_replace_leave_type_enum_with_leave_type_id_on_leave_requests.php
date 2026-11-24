<?php

use App\Support\SchemaGuard;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `leave_requests.leave_type` كان ENUM('annual','casual','sick','unpaid') —
 * بقيّةً من زمنٍ كانت فيه أنواع الإجازات ثابتةً في الكود. ثم صار للأنواع
 * جدولُها `leave_types`، وتحوّل النموذجُ والمتحكّمُ إلى `leave_type_id`
 * بالكامل… دون أن يتحوّل العمود.
 *
 * فكان كل ما يلمس نوعَ الإجازة معطّلاً ما دام الجدول فارغاً فقط:
 *   - LeaveRequest::$fillable يذكر leave_type_id، فـ create() ترمي 1054
 *   - belongsTo(LeaveType::class) يخمّن leave_type_id فيعيد null، ومنه
 *     «Attempt to read property "name" on null» أي 500 على شاشة الطلبات
 *   - getUsedLeaveDays() يستعلم where('leave_type_id', …) فيرمي 1054
 *
 * العمود القديم يُحفظ باسم legacy_leave_type بدل حذفه: لا نُفرّط في بيانات
 * صفوفٍ قائمةٍ قد تحمل نوعاً قديماً لا مقابلَ له في leave_types.
 */
return new class extends Migration
{
    /** ترجمةُ الأنواع الأربعة القديمة إلى ما يقابلها في leave_types */
    private const LEGACY_MAP = [
        'annual' => ['سنوي', 'سنوية', 'اعتيادي', 'اعتيادية', 'annual'],
        'casual' => ['عرضي', 'عرضية', 'طارئ', 'طارئة', 'casual'],
        'sick' => ['مرضي', 'مرضية', 'sick'],
        'unpaid' => ['بدون راتب', 'بدون أجر', 'unpaid'],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('leave_requests')) {
            return;
        }

        if (! Schema::hasColumn('leave_requests', 'leave_type_id')) {
            Schema::table('leave_requests', function (Blueprint $table) {
                if (! Schema::hasColumn('leave_requests', 'leave_type_id')) {
                    $table->unsignedBigInteger('leave_type_id')->nullable()->after('request_type');
                }
                if (! SchemaGuard::hasIndex('leave_requests', 'leave_requests_leave_type_id_index')) {
                    $table->index('leave_type_id');
                }
            });
        }

        if (Schema::hasColumn('leave_requests', 'leave_type')) {
            $this->backfill();

            // renameColumn() عبر Doctrine DBAL يولّد SQL غير صالح لعمود ENUM
            // له قيمة افتراضية (يُكرّر التنصيص حول القيمة) — لذا تُنفَّذ يدوياً.
            //
            // و`ALTER TABLE … CHANGE` صيغةٌ خاصّةٌ بـMySQL: على SQLite ترمي
            // «near "CHANGE": syntax error» فتتوقّف الهجرة، ومعها كلُّ ما
            // بعدها — والنظام يبقى خلف شاشة «تحديث النظام» أبداً. ولا ENUM
            // في SQLite أصلاً (العمود نصٌّ حرّ)، فالإعادةُ تسميةٍ صرفة،
            // وتدعمها SQLite أصلاً بلا DBAL.
            if (DB::connection()->getDriverName() === 'sqlite') {
                Schema::table('leave_requests', function (Blueprint $table) {
                    $table->renameColumn('leave_type', 'legacy_leave_type');
                });
            } else {
                DB::statement(
                    "ALTER TABLE `leave_requests` CHANGE `leave_type` `legacy_leave_type` "
                    ."ENUM('annual','casual','sick','unpaid') COLLATE utf8mb4_unicode_ci NULL DEFAULT 'annual'"
                );
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('leave_requests')) {
            return;
        }

        if (Schema::hasColumn('leave_requests', 'legacy_leave_type')) {
            Schema::table('leave_requests', function (Blueprint $table) {
                $table->renameColumn('legacy_leave_type', 'leave_type');
            });
        }

        if (Schema::hasColumn('leave_requests', 'leave_type_id')) {
            Schema::table('leave_requests', function (Blueprint $table) {
                $table->dropIndex(['leave_type_id']);
                $table->dropColumn('leave_type_id');
            });
        }
    }

    /**
     * يملأ leave_type_id من القيمة القديمة، مطابقةً بالاسم.
     * ما لا يجد له مقابلاً يبقى NULL — والعمود القديم محفوظٌ لمراجعته.
     */
    private function backfill(): void
    {
        if (! Schema::hasTable('leave_types')) {
            return;
        }

        $types = DB::table('leave_types')->select('id', 'name')->get();

        if ($types->isEmpty()) {
            return;
        }

        foreach (self::LEGACY_MAP as $legacy => $needles) {
            $match = $types->first(function ($type) use ($needles) {
                foreach ($needles as $needle) {
                    if (mb_stripos((string) $type->name, $needle) !== false) {
                        return true;
                    }
                }

                return false;
            });

            if ($match) {
                DB::table('leave_requests')
                    ->where('leave_type', $legacy)
                    ->whereNull('leave_type_id')
                    ->update(['leave_type_id' => $match->id]);
            }
        }
    }
};
