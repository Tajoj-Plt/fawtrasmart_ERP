<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * استعادةُ `inventory_adjustments.status` حيث محاه ترحيلٌ سابق.
 *
 * **ما وقع**
 *
 * `2025_11_05_110000_update_inventory_adjustments_table` كتب — داخل إغلاق
 * `Schema::table` واحد — حذفَ العمود ثمّ إعادةَ إنشائه محروسةً بـ
 * `Schema::hasColumn`:
 *
 *     $table->dropColumn('status');
 *     if (! Schema::hasColumn('inventory_adjustments', 'status')) { ... }
 *
 * والحارسُ يسأل القاعدةَ في حينه، والحذفُ أمرٌ مُصطفٌّ لا يُنفَّذ إلا بعد
 * انتهاء الإغلاق — فالجواب «موجود»، فلا يُعاد، ثمّ يُحذف. عمودٌ ذهب
 * ببياناته في ترحيلٍ اسمُه «تحديث».
 *
 * **وأثرُه ليس نظريّاً**: `InventoryManagementController::store()` يكتب
 * `'status' => 'draft'` و`index()` يُصفّي بالعمود و`updateStatus()` يقرؤه
 * ويكتبه — فشاشةُ الجرد كلُّها تسقط بـ«Unknown column 'status'» على كل
 * قاعدةٍ مرّ عليها ذلك الترحيل.
 *
 * **وما تفعله هذه**: تُعيد العمودَ إن غاب، لا غير. فالقواعدُ السليمة لا
 * تُمَسّ، والمصابةُ تُشفى. والترحيلُ الأصلي صُحِّح كذلك فلا يتكرّر الأذى
 * على تركيبٍ جديد.
 *
 * **والحالات المفقودة لا تُخترع**: البياناتُ ذهبت مع العمود ولا تُستعاد
 * من عدم. غير أن `adjusted_at` باقٍ ويحمل وقتَ التسوية، فالصفُّ الذي له
 * وقتُ تسويةٍ حالتُه `adjusted` يقيناً لا ظنّاً — وما عداه يعود إلى
 * `draft` وهي القيمة الافتراضية للعمود أصلاً.
 *
 * @see \Modules\Stock\Http\Controllers\Stock\InventoryManagementController
 */
return new class extends Migration
{
    /** حالات الجرد كما يعرفها المتحكّم (`updateStatus()` يتحقّق منها). */
    private const STATES = ['draft', 'in_progress', 'completed', 'adjusted', 'cancelled'];

    public function up(): void
    {
        if (! Schema::hasTable('inventory_adjustments')) {
            return;
        }

        if (Schema::hasColumn('inventory_adjustments', 'status')) {
            return;
        }

        Schema::table('inventory_adjustments', function (Blueprint $table) {
            $column = $table->enum('status', self::STATES)->default('draft');

            // موضعُه الأصلي بعد `note` — وإن غاب `note` في قاعدةٍ قديمة
            // تُركَ في آخر الجدول: الترتيبُ تجميلٌ لا يستحقّ إسقاطَ الترحيل
            if (Schema::hasColumn('inventory_adjustments', 'note')) {
                $column->after('note');
            }
        });

        // الجردُ الذي سُوِّي فعلاً يستعيد حالته — `adjusted_at` شاهدُها
        if (Schema::hasColumn('inventory_adjustments', 'adjusted_at')) {
            DB::table('inventory_adjustments')
                ->whereNotNull('adjusted_at')
                ->update(['status' => 'adjusted']);
        }
    }

    public function down(): void
    {
        // لا تراجُع: إسقاطُ العمود هو العطلُ الذي جاء هذا الترحيل ليُصلحه.
    }
};
