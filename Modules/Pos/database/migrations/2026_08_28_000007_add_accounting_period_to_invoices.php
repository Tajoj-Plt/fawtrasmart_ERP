<?php

use App\Support\SchemaGuard;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * الفترةُ المحاسبية على الفاتورة.
 *
 * ─────────────────────── لماذا هجرةٌ منفصلة؟ ───────────────────────
 *
 * لأنّ `..._000001` كانت قد طُبّقت فعلاً حين تبيّنت الحاجة إلى هذا العمود.
 * وتعديلُ هجرةٍ مطبَّقة لا يُعيد تشغيلها — يُسجَّل اسمُها في جدول
 * `migrations` فتُتخطّى — فيبقى العمودُ غائباً عن كل بيئةٍ رُحِّلت من قبل،
 * ويظهر فقط في التنصيبات الجديدة. وهو أسوأُ صنفٍ من الاختلاف: يعمل عند من
 * كتبه ويفشل عند غيره.
 *
 * ─────────────────────── ولماذا العمود؟ ───────────────────────
 *
 * `journal_entries` تحمل `accounting_period_id` منذ البداية، و`invoices` لا
 * تحمله. فالقيدُ يُنسب إلى فترته والفاتورةُ التي وُلد منها لا تُنسب.
 *
 * وتقاريرُ نقاط البيع التي تُسأل عن «فترة» كانت مضطرّةً للمقارنة بالتواريخ
 * — وهي تختلف عن حدود الفترة كلّما عُدِّلت تلك الحدود، فيُخرج التقريرُ
 * أرقاماً لا توافق ميزانَ المراجعة للفترة نفسِها.
 *
 * والختمُ لحظةَ الإنشاء لا الاستنتاجُ لاحقاً: حدودُ الفترات تُعدَّل،
 * فمستندٌ يُسأل عن فترته بمقارنة تاريخه اليوم قد يُجيب بغير ما أُنشئ فيه.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            if (! Schema::hasColumn('invoices', 'accounting_period_id')) {
                $table->unsignedBigInteger('accounting_period_id')->nullable();

                // الاستعلامُ الغالب في التقارير: نوعُ الفاتورة داخل فترة
                if (! SchemaGuard::hasIndex('invoices', 'invoices_type_period_idx')) {
                    $table->index(['type', 'accounting_period_id'], 'invoices_type_period_idx');
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            if (Schema::hasColumn('invoices', 'accounting_period_id')) {
                $table->dropIndex('invoices_type_period_idx');
                $table->dropColumn('accounting_period_id');
            }
        });
    }
};
