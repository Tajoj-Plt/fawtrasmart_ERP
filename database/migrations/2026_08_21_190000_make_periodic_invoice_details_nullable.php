<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `periodic_invoices.details_subscription` حقلٌ اختياري في الشاشة وفي
 * التحقّق، و`NOT NULL` بلا افتراضي في القاعدة.
 *
 * فالنموذج لا يوسمه مطلوباً، والمتحكّم يتحقّق منه بـ`nullable` ويمرّره
 * `?? null` — ثمّ ترفضه القاعدة:
 *
 *     SQLSTATE[23000]: Column 'details_subscription' cannot be null
 *
 * أي أنّ حفظ اشتراكٍ بلا «تفاصيل» يخرج خطأً فادحاً بدل أن يُحفَظ. الحقل
 * وصفٌ حرّ لا يقوم عليه حساب، فالصواب أن تتبع القاعدةُ الشاشةَ لا العكس.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('periodic_invoices')) {
            return;
        }

        Schema::table('periodic_invoices', function (Blueprint $table) {
            $table->string('details_subscription')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('periodic_invoices')) {
            return;
        }

        /* العودة تملأ الفارغ بنصٍّ فارغ أوّلاً: صفٌّ `null` يمنع إعادة
           العمود إلى `NOT NULL`. */
        \Illuminate\Support\Facades\DB::table('periodic_invoices')
            ->whereNull('details_subscription')
            ->update(['details_subscription' => '']);

        Schema::table('periodic_invoices', function (Blueprint $table) {
            $table->string('details_subscription')->nullable(false)->change();
        });
    }
};
