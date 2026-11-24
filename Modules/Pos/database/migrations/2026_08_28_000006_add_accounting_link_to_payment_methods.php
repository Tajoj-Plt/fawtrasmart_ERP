<?php

use App\Support\SchemaGuard;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ربطُ طريقة الدفع بحسابها المحاسبي — وبعمولتها.
 *
 * ─────────────────────── ما كان ───────────────────────
 *
 * `payment_methods` جدولٌ فيه اسمُ الطريقة ونوعُها وبياناتُ بنكها نصّاً
 * (`bank_name`، `bank_iban`) — **ولا عمودَ يربطها بحسابٍ في شجرة الحسابات**.
 *
 * فكان مولّدُ القيد يستنتج الوجهةَ بقاعدةٍ ثنائية مكتوبة في الشيفرة:
 *
 *     if ($paymentMethod->is_online) {  // ← يذهب للبنك الافتراضي
 *         $onlineAmount += $amount;
 *     } else {                          // ← يذهب للخزينة
 *         $offlineAmount += $amount;
 *     }
 *
 * وبنكٌ افتراضيٌّ واحد لكل الطرق الإلكترونية. فمنشأةٌ تقبل «مدى» و«فيزا»
 * و«آبل باي» عبر ثلاثة مزوّدين بثلاثة حسابات بنكية تُرحَّل مبيعاتُها كلُّها
 * إلى حسابٍ واحد — ثم تُفكَّك يدوياً عند مطابقة كشوف البنوك.
 *
 * ─────────────────────── والعمولة ───────────────────────
 *
 * `fee_type`/`fee_amount` موجودان في الجدول ولا يقرؤهما قيدٌ محاسبي. فعمولةُ
 * الشبكة — وهي مصروفٌ حقيقي على كل عملية — لا تُقيَّد إطلاقاً: يُسجَّل
 * المبلغُ كاملاً على البنك، بينما يُودَع منقوصاً بالعمولة. والفرقُ يظهر عند
 * المطابقة ولا حساب يحمله.
 *
 * `fee_account_id` هنا يجعل قيدَه ممكناً حين تريده المنشأة.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_methods', function (Blueprint $table) {
            /*
                الحسابُ الذي تُرحَّل إليه حصيلةُ هذه الطريقة.

                `null` يُبقي السلوك القديم عاملاً: البنكُ الافتراضي للطرق
                الإلكترونية، والخزينةُ لغيرها. فالربطُ ترقيةٌ اختيارية لا
                شرطُ تشغيل.
            */
            if (! Schema::hasColumn('payment_methods', 'account_id')) {
                $table->unsignedBigInteger('account_id')->nullable()->after('type');
                if (! SchemaGuard::hasIndex('payment_methods', 'payment_methods_account_idx')) {
                    $table->index('account_id', 'payment_methods_account_idx');
                }
            }

            /** حسابُ مصروف عمولة المزوّد. */
            if (! Schema::hasColumn('payment_methods', 'fee_account_id')) {
                $table->unsignedBigInteger('fee_account_id')->nullable()->after('account_id');
            }

            /*
                أتحتاج هذه الطريقةُ تسويةً لاحقة؟

                النقدُ يدخل الدرج فوراً، والشبكةُ تُودَع بعد يومٍ أو ثلاثة.
                والتمييزُ بينهما هو ما يجعل تقرير «غير المُسوّى» ممكناً.
            */
            if (! Schema::hasColumn('payment_methods', 'requires_settlement')) {
                $table->boolean('requires_settlement')->default(false)->after('fee_account_id');
            }

            /** أتظهر هذه الطريقةُ في شاشة نقطة البيع؟ */
            if (! Schema::hasColumn('payment_methods', 'available_in_pos')) {
                $table->boolean('available_in_pos')->default(true)->after('requires_settlement');
            }
        });
    }

    public function down(): void
    {
        Schema::table('payment_methods', function (Blueprint $table) {
            if (Schema::hasColumn('payment_methods', 'account_id')) {
                $table->dropIndex('payment_methods_account_idx');
            }

            $table->dropColumn(array_values(array_filter(
                ['account_id', 'fee_account_id', 'requires_settlement', 'available_in_pos'],
                fn ($c) => Schema::hasColumn('payment_methods', $c)
            )));
        });
    }
};
