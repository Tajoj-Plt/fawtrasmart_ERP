<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * مخازنُ المتجر التي بقيت بلا فرع تُسنَد إلى الفرع الرئيسي.
 *
 * شاشةُ مخازن المتجر كانت تحفظ المخزن بلا حقل فرع، وقبل أن يُعلَّم فرعٌ
 * بـ`is_main` كان يُحفظ `branch_id = null`. والمخزنُ بلا فرع لا يلتقطه نطاقُ
 * فرعٍ ولا فلترُه — فيُعامَل في الشيفرة كأنه في الرئيسي، وهذا الترحيل يكتب
 * ذلك في البيانات نفسها كي تتّفق عليه كلُّ الشاشات (المخزون، المشتريات،
 * الطلبات) لا شاشةُ المتجر وحدها.
 *
 * آمنٌ للتكرار: لا يمسّ مخزناً له فرع، ولا يعمل إن لم يُعرَّف فرعٌ رئيسي.
 * ولا يمسّ مخازن المخزون العام (غير مخازن المتجر) — غيابُ الفرع عنها مقصود.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('store_houses') || ! Schema::hasTable('branches')
            || ! Schema::hasColumn('store_houses', 'branch_id')
            || ! Schema::hasColumn('store_houses', 'is_store_storehouse')
            || ! Schema::hasColumn('branches', 'is_main')) {
            return;
        }

        $mainBranchId = DB::table('branches')->where('is_main', 1)->orderBy('id')->value('id');

        if (! $mainBranchId) {
            return;
        }

        DB::table('store_houses')
            ->where('is_store_storehouse', 1)
            ->whereNull('branch_id')
            ->update(['branch_id' => $mainBranchId]);
    }

    public function down(): void
    {
        // لا رجوع: لا يُعرف بعد الترحيل أيُّ المخازن كان بلا فرع
    }
};
