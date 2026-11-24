<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `purchase_quotations.code` من عدد صحيح إلى نصّ.
 *
 * نفس علّة `purchase_orders.code` التي عُولجت في
 * `2026_08_19_090000_change_purchase_orders_code_to_string`:
 * شاشة «تسلسل أرقام المستندات» تعرّف لقسم «طلب عرض أسعار» بادئةً (`RFQ`)
 * وصيغةً، والعمود `int(11)` يرفضها:
 *
 *     SQLSTATE[22007]: Incorrect integer value: 'RFQ-000001' for column `code`
 *
 * أختاها `purchase_quotations_view.code` و`purchase_invoices.code` نصّيتان
 * أصلاً، فلا يلزمهما شيء.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('purchase_quotations')) {
            return;
        }

        Schema::table('purchase_quotations', function (Blueprint $table) {
            $table->string('code', 50)->nullable(false)->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('purchase_quotations')) {
            return;
        }

        // الأكواد ذات البادئة لا يسعها العدد الصحيح، فتُجرَّد قبل التراجع
        DB::table('purchase_quotations')->update([
            'code' => DB::raw("COALESCE(NULLIF(REGEXP_REPLACE(code, '[^0-9]', ''), ''), '0')"),
        ]);

        Schema::table('purchase_quotations', function (Blueprint $table) {
            $table->integer('code')->nullable(false)->change();
        });
    }
};
