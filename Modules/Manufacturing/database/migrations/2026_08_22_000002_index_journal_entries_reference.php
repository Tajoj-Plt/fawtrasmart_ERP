<?php

use App\Support\SchemaGuard;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * فهرس ربط القيد بمستنده.
 *
 * ── لماذا ────────────────────────────────────────────────────────────
 *
 * `undoCompletion` يبحث عن قيود الأمر بالوصف النصّي، وتعليقه يزعم أن
 * الجدول لا يحمل معرّف الأمر. وهو غير صحيح: `reference_type` و
 * `reference_id` موجودان في `$fillable` ويُكتبان فعلاً عند الإنشاء.
 *
 * البحث بالمفتاح يستبدل البحث النصّي (انظر ManufacturingJournalService)،
 * لكنه بلا فهرس يمسح الجدول كله في كل تراجع. الفهرس هنا يخدم النظام كله
 * لا التصنيع وحده: كل استعلام يربط قيداً بفاتورة أو إذن أو سند يمرّ به.
 *
 * ── ما لا تفعله هذه الهجرة ───────────────────────────────────────────
 *
 * لا تضيف أعمدة عكس: `is_reversed` موجود في `$fillable` سلفاً، وهو كافٍ
 * لمنع عكس القيد مرتين ولوسم القيد العكسي نفسه. لا داعي لعمود جديد.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('journal_entries')) {
            return;
        }

        // الفهرس قد يكون موجوداً من هجرة سابقة أو من ضبط يدوي
        try {
            Schema::table('journal_entries', function (Blueprint $table) {
                if (! SchemaGuard::hasIndex('journal_entries', 'je_reference_idx')) {
                    $table->index(['reference_type', 'reference_id'], 'je_reference_idx');
                }
            });

            echo "\n  ✓ أُضيف الفهرس je_reference_idx على (reference_type, reference_id)\n\n";
        } catch (\Throwable $e) {
            echo "\n  · الفهرس موجود سلفاً — تُخطّي.\n\n";
        }

        // تحقق تشخيصي: هل عمود العكس موجود فعلاً؟
        if (! Schema::hasColumn('journal_entries', 'is_reversed')) {
            echo "  ⚠️  العمود `is_reversed` غير موجود رغم وجوده في \$fillable.\n";
            echo "      ManufacturingJournalService::reverseFor يعتمد عليه.\n";
            echo "      أضفه قبل استعمال التراجع عن إنهاء أمر التصنيع.\n\n";
        }
    }

    public function down(): void
    {
        try {
            Schema::table('journal_entries', function (Blueprint $table) {
                $table->dropIndex('je_reference_idx');
            });
        } catch (\Throwable $e) {
        }
    }
};
