<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * أعمدةٌ اختيارية في الواجهة، إلزامية في القاعدة.
 *
 * ثلاثة حقول تُترك فارغةً في الشاشة عن قصد — ضريبتان لا تُفرضان على كل
 * مصروف، وحسابُ تحصيلٍ لا يُحدَّد عند استلام الشيك — بينما القاعدة تُعرّفها
 * `NOT NULL` بلا قيمة افتراضية. فحين لا يملأها المستخدم يُرَدّ الحفظ برسالة
 * خام:
 *
 *     SQLSTATE[HY000]: Field 'tax1_amount' doesn't have a default value
 *
 * وهي رسالة لا تدلّ المستخدم على شيء، ولا يمسكها تحقّق النموذج لأن الحقل
 * ليس مطلوباً أصلاً. الأصوب أن تعبّر القاعدة عن الواقع: ضريبةٌ لم تُفرض
 * قيمتها صفر، وحسابُ تحصيلٍ لم يُختَر بعدُ قيمته `NULL`.
 *
 * `IncomesController::store()` يمرّر `tax2_amount` كما ورد من النموذج —
 * `null` حين لا ضريبة — فكان سند القبض بلا ضريبةٍ يسقط دائماً.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('expenses')) {
            Schema::table('expenses', function (Blueprint $table) {
                $table->decimal('tax1_amount', 10, 2)->default(0)->change();
                $table->decimal('tax2_amount', 10, 2)->default(0)->change();
            });
        }

        if (Schema::hasTable('receipts')) {
            Schema::table('receipts', function (Blueprint $table) {
                $table->decimal('tax2_amount', 10, 2)->default(0)->change();
            });
        }

        // الشيك يُستلَم أوّلاً ويُودَع لاحقاً: حساب التحصيل يُملأ عند الإيداع.
        // والمتحكّم يقول ذلك صراحةً — «لا ترسل collection_account_id عند الإنشاء»
        if (Schema::hasTable('received_cheques')) {
            Schema::table('received_cheques', function (Blueprint $table) {
                $table->unsignedBigInteger('collection_account_id')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        // لا رجوع: إعادة القيود يُسقط كل صفٍّ حُفظ بالاعتماد عليها
    }
};
