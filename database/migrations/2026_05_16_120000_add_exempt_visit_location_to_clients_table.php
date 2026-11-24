<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // إصلاح ENUM تالف في visit_type يمنع أي ALTER على جدول clients.
        // SHOW COLUMNS و MODIFY كلاهما خاصٌّ بـMySQL، والعلّة نفسها لا تنشأ
        // على SQLite لأن الجدول يُبنى نظيفاً في كل مرة.
        if (DB::connection()->getDriverName() !== 'sqlite') {
            $visitTypeCol = collect(DB::select("SHOW COLUMNS FROM clients WHERE Field = 'visit_type'"))->first();
            if ($visitTypeCol && str_contains($visitTypeCol->Type, "''")) {
                DB::statement("ALTER TABLE `clients` MODIFY `visit_type` VARCHAR(10) NULL DEFAULT 'am'");
            }
        }

        if (!Schema::hasColumn('clients', 'exempt_visit_location')) {
            Schema::table('clients', function (Blueprint $table) {
                // القيمة الافتراضية 0 = غير مُستثنى → يجب التحقق من الموقع وملاحظة الزيارة
                if (! Schema::hasColumn('clients', 'exempt_visit_location')) {
                    $table->boolean('exempt_visit_location')
                        ->default(0)
                        ->comment('0=يُتحقق من الموقع (افتراضي) | 1=مُستثنى من التحقق بالموقع');
                }
            });
        }

        // ضمان أن كل العملاء (الحاليين والجدد) يبدأون بدون استبعاد
        DB::table('clients')->update(['exempt_visit_location' => 0]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('clients', 'exempt_visit_location')) {
            Schema::table('clients', function (Blueprint $table) {
                $table->dropColumn('exempt_visit_location');
            });
        }
    }
};
