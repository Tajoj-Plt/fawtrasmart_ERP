<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * حذف صلاحيتَي `Edit_Client`/`Delete_Client` القديمتين نهائياً.
 *
 * أُخرجتا من `ClientsPermissions` كتالوجاً، وهذه خطوة الحذف من الجدول
 * نفسه — لا تعليقاً في الكتالوج وحده. آمنةٌ لأن كل دورٍ كان يحملهما
 * (owner, manager, admin) يحمل معه `clients_edit_data`/`clients_delete`
 * أيضاً، وحارس `ClientController` كان على الاسمين معاً — فحذفهما لا يُسقط
 * وصول أحد.
 */
return new class extends Migration
{
    private const NAMES = ['Edit_Client', 'Delete_Client'];

    public function up(): void
    {
        $ids = DB::table('permissions')->whereIn('name', self::NAMES)->pluck('id');

        if ($ids->isEmpty()) {
            return;
        }

        DB::table('role_has_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();
    }

    public function down(): void
    {
        // لا تراجع: الاسمان القديمان مستبعدان من الكتالوج عمداً، فإعادة
        // الصفّ دون تعريفٍ في `ClientsPermissions` يجعله يتيماً فوراً.
    }
};
