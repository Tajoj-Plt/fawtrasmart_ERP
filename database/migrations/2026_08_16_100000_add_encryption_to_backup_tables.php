<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * تعشية أرشيف النسخ الاحتياطية من الواجهة.
 *
 * كانت كلمة التعشية في `.env` وحدها (`BACKUP_ARCHIVE_PASSWORD`) وتصل فارغة،
 * فكانت كل نسخة تُكتب أرشيفاً مفتوحاً: من وصل إلى الملف وصل إلى قاعدة
 * البيانات كاملة. نقلها إلى الإعدادات يجعلها قابلة للضبط دون لمس الخادم.
 *
 * `archive_password` يُخزَّن معشّى بـ`APP_KEY` (خاصية `encrypted` في
 * النموذج) لا نصّاً صريحاً — قاعدة البيانات نفسها تُنسَخ احتياطياً، وكلمةٌ
 * صريحة فيها تعني أرشيفاً معشّى وكلمته بجانبه.
 *
 * `backups.is_encrypted` يُسجَّل لحظة الإنشاء: الأرشيفات القديمة مفتوحة
 * والجديدة معشّاة، والاسترجاع يحتاج أن يعرف الفرق قبل أن يحاول الفكّ.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('backup_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('backup_settings', 'encrypt_archives')) {
                $table->boolean('encrypt_archives')->default(false)->after('disk');
            }
            // نصّ لا `string`: ناتج تعشية Laravel أطول بكثير من المدخل
            if (! Schema::hasColumn('backup_settings', 'archive_password')) {
                $table->text('archive_password')->nullable()->after('encrypt_archives');
            }
        });

        Schema::table('backups', function (Blueprint $table) {
            if (! Schema::hasColumn('backups', 'is_encrypted')) {
                $table->boolean('is_encrypted')->default(false)->after('size_bytes');
            }
        });
    }

    public function down(): void
    {
        Schema::table('backup_settings', function (Blueprint $table) {
            $table->dropColumn(['encrypt_archives', 'archive_password']);
        });

        Schema::table('backups', function (Blueprint $table) {
            $table->dropColumn('is_encrypted');
        });
    }
};
