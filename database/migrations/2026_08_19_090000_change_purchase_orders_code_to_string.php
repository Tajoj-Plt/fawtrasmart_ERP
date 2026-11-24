<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `purchase_orders.code` من عدد صحيح إلى نصّ.
 *
 * **لماذا؟**
 *
 * شاشة «تسلسل أرقام المستندات» تعرّف لقسم «أمر شراء» بادئةً وصيغةً
 * (`PO-000002` مثلاً)، لكن العمود كان `int(11)` فترفضه MySQL:
 *
 *     SQLSTATE[22007]: Incorrect integer value: 'PO-000002' for column `code`
 *
 * وكانت الشيفرة القديمة تتحايل بـ`str_pad($n, 5, '0')` فتنتج `00001`
 * تحوّلها القاعدة إلى `1` صامتةً — أي أن الأصفار البادئة تُفقد وإعدادُ
 * الترقيم بلا أثر أصلاً.
 *
 * الفهرس الفريد على العمود يبقى كما هو؛ تغيير النوع لا يمسّه، والقيم
 * الرقمية القائمة تُحفظ نصّاً كما هي (`12` تصير `'12'`).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('purchase_orders')) {
            return;
        }

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->string('code', 50)->nullable(false)->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('purchase_orders')) {
            return;
        }

        // الأكواد المولَّدة بعد الترحيل تحمل بادئة لا يسعها العدد الصحيح،
        // فتُجرَّد من غير الأرقام قبل التراجع وإلا فشل التحويل كلّه.
        \Illuminate\Support\Facades\DB::table('purchase_orders')
            ->update(['code' => \Illuminate\Support\Facades\DB::raw(
                "COALESCE(NULLIF(REGEXP_REPLACE(code, '[^0-9]', ''), ''), '0')"
            )]);

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->integer('code')->nullable(false)->change();
        });
    }
};
