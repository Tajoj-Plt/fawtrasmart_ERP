<?php

use App\Support\SchemaGuard;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * إلغاءُ الفاتورة — بأعمدةٍ صريحة لا بعمودِ حالةٍ لا وجود له.
 *
 * ─────────────────────── ما كشفه التشغيل ───────────────────────
 *
 * `invoices` **لا تحمل عمود `status` إطلاقاً**. ومع ذلك كانت الشيفرة
 * تستعلم عنه في أربعة مواضع:
 *
 *     Invoice::where('type','pos')->where('status','held')->...
 *
 * وهذا لا يُرجع قائمةً فارغة كما قد يُظنّ — بل يرمي
 * `Unknown column 'status'`، فيلتقطه `try/catch` ويردّ «حدث خطأ أثناء جلب
 * الفواتير المعلقة». أي أن ميزة التعليق لم تكن ميتةً فحسب، بل **كانت
 * تُخطئ في كل نداء**.
 *
 * و`Invoice::isEditable()` تفحص `in_array($this->status, ['pending','held'])`
 * على العمود نفسِه — فتقرأ `null` دائماً وتُجيب دائماً بـ«لا».
 *
 * ─────────────────────── ولماذا أعمدةٌ صريحة؟ ───────────────────────
 *
 * كان يمكن إضافة عمود `status` عامّ. لكنّ «الحالة» في جدولٍ تتشارك فيه
 * فواتيرُ المبيعات والمشتريات والمرتجعات ونقاطُ البيع تصير خانةً تحمل
 * معانيَ مختلفة بحسب النوع — وهو أصلُ الالتباس نفسِه الذي أنتج
 * `payment_method` (رقمٌ أحياناً ونصُّ JSON أحياناً).
 *
 * فالإلغاء هنا ثلاثةُ أعمدةٍ تقول ما تعنيه: متى أُلغيت، ومن ألغاها،
 * ولماذا. و`voided_at` فارغاً يعني «سارية» — لا حاجةَ لقيمةٍ تدلّ عليها.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            if (! Schema::hasColumn('invoices', 'voided_at')) {
                $table->datetime('voided_at')->nullable();

                /*
                    الفهرسُ مركَّب مع النوع: كلُّ استعلامٍ على فواتير نقطة
                    البيع يستثني الملغاة، فالشرطان يقعان معاً دائماً.
                */
                if (! SchemaGuard::hasIndex('invoices', 'invoices_type_voided_idx')) {
                    $table->index(['type', 'voided_at'], 'invoices_type_voided_idx');
                }
            }

            if (! Schema::hasColumn('invoices', 'voided_by')) {
                $table->unsignedBigInteger('voided_by')->nullable();
            }

            if (! Schema::hasColumn('invoices', 'void_reason')) {
                $table->string('void_reason', 500)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            if (Schema::hasColumn('invoices', 'voided_at')) {
                $table->dropIndex('invoices_type_voided_idx');
            }

            $table->dropColumn(array_values(array_filter(
                ['voided_at', 'voided_by', 'void_reason'],
                fn ($c) => Schema::hasColumn('invoices', $c)
            )));
        });
    }
};
