<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * مجلَّد النسخ الاحتياطية باسم المنشأة.
 *
 * **حقلان لا واحد.** الاسم المعروض يتغيّر — يعيد التاجر تسمية منشأته أو
 * يصحّح خطأً إملائياً. لو كان المجلَّد مشتقّاً من الاسم لحظةَ كل تشغيل،
 * لانتقل النظام عند أوّل تعديل إلى مجلَّد جديد فارغ، واختفت كل النسخ
 * السابقة من الواجهة وهي سليمة على القرص. لذا يُثبَّت `establishment_folder`
 * عند أوّل ضبط ولا يتبع الاسم بعدها.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('backup_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('backup_settings', 'establishment_name')) {
                $table->string('establishment_name')->nullable()->after('disk');
            }
            if (! Schema::hasColumn('backup_settings', 'establishment_folder')) {
                $table->string('establishment_folder')->nullable()->after('establishment_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('backup_settings', function (Blueprint $table) {
            foreach (['establishment_name', 'establishment_folder'] as $column) {
                if (Schema::hasColumn('backup_settings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
