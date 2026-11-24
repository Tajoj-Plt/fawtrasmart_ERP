<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `reject()` يكتب سبب الرفض ومَن رفض ومتى — وثلاثتها أعمدة غير موجودة
     * في الجدول، فيسقط الحفظ ويُلتقط الاستثناء بصمت: يبقى الإذن «معلَّقاً»
     * ويظنّ المستخدم أنّ زرّ الرفض لا يعمل.
     */
    public function up(): void
    {
        Schema::table('warehouse_permits', function (Blueprint $table) {
            if (! Schema::hasColumn('warehouse_permits', 'rejection_reason')) {
                $table->text('rejection_reason')->nullable()->after('status');
            }

            if (! Schema::hasColumn('warehouse_permits', 'rejected_by')) {
                $table->unsignedBigInteger('rejected_by')->nullable()->after('rejection_reason');
            }

            if (! Schema::hasColumn('warehouse_permits', 'rejected_at')) {
                $table->timestamp('rejected_at')->nullable()->after('rejected_by');
            }
        });
    }

    public function down(): void
    {
        Schema::table('warehouse_permits', function (Blueprint $table) {
            $table->dropColumn(array_values(array_filter(
                ['rejection_reason', 'rejected_by', 'rejected_at'],
                fn ($column) => Schema::hasColumn('warehouse_permits', $column)
            )));
        });
    }
};
