<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * مفتاحُ تشغيل حجز المواد.
 *
 * الحجزُ سلوكٌ يغيّر ما يراه المستخدم من أرقام، وليس كلُّ منشأةٍ تريده:
 * ورشةٌ صغيرة بمستودعٍ واحد ومستخدمٍ واحد لا تحتاج أن تُفرَّق عندها
 * «الفعلي» عن «المتاح». فالمفتاحُ يُبقيه اختياراً معلَناً.
 *
 * **الافتراضُ مُفعَّل.** المنعُ الصامت أسوأ من الرقم الزائد: منشأةٌ لا
 * تحجز تكتشف النقص لحظةَ الصرف حين تكون قد وعدت عميلاً بموعد.
 *
 * ── لماذا هنا وقد أُجّل توسيع الإعدادات ─────────────────────────────
 *
 * أُجّل توسيعُ `manufacturing_general_settings` في المرحلة ٣ بحجّة أن
 * «أعمدة الإعدادات تُشتقّ من السلوك الذي تضبطه». وهذا العمود يضبط
 * سلوكاً يُبنى **الآن** — فشرطُ التأجيل سقط.
 */
return new class extends Migration
{
    private const TABLE = 'manufacturing_general_settings';

    public function up(): void
    {
        if (! Schema::hasTable(self::TABLE) || Schema::hasColumn(self::TABLE, 'reserve_materials_on_order')) {
            return;
        }

        Schema::table(self::TABLE, function (Blueprint $table) {
            $table->boolean('reserve_materials_on_order')
                ->default(true)
                ->after('quantity_exceeded');
        });

        /*
            صفُّ الإعدادات قد لا يكون موجوداً أصلاً — الجدول فارغٌ في
            القواعد القائمة. تُقرأ القيمةُ الافتراضية من الخدمة حينئذٍ،
            فلا يُنشأ صفٌّ هنا عنوةً.
        */
        DB::table(self::TABLE)->update(['reserve_materials_on_order' => true]);
    }

    public function down(): void
    {
        if (! Schema::hasTable(self::TABLE) || ! Schema::hasColumn(self::TABLE, 'reserve_materials_on_order')) {
            return;
        }

        Schema::table(self::TABLE, function (Blueprint $table) {
            $table->dropColumn('reserve_materials_on_order');
        });
    }
};
