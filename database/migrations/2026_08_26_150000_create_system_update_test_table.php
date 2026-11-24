<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * هجرة تجريبية — لاختبار شاشة «تحديث النظام» على الخادم.
 *
 * ⚠️ **ليست جزءاً من النظام.** غرضها الوحيد أن ترى الدورة كاملة أمامك:
 * الملفّ يُرفَع ⇐ يظهر «معلّقاً» في الشاشة ⇐ تضغط الزرّ ⇐ يُنفَّذ ⇐ يصير
 * المعلّق صفراً. احذفها بعد ما تتأكّد (الطريقة في آخر هذا التعليق).
 *
 * **لماذا جدولٌ جديد لا عمودٌ في جدولٍ قائم؟** لأن التجربة يجب ألّا تترك
 * أثراً في بياناتك. عمودٌ يُضاف إلى `products` أو `purchase_invoices` يبقى
 * في البنية حتى لو فرغ، ويظهر في كل تصدير وكل نسخة احتياطية. أمّا جدول
 * قائم بذاته فيُحذف بسطرٍ واحد ولا يبقى منه شيء.
 *
 * **كيف تتأكّد أنها نجحت — بلا سطر أوامر:**
 *   الإعدادات ← أحجام الجداول ← ابحث عن `system_update_test`
 *   يظهر الجدول بصفٍّ واحد فيه تاريخ التنفيذ ودقيقته.
 *
 * **كيف تحذفها بعد التجربة:**
 *   1. احذف هذا الملفّ من `database/migrations/` على الخادم.
 *   2. احذف سطره من جدول `migrations` (اسمه:
 *      `2026_08_26_150000_create_system_update_test_table`).
 *   3. احذف جدول `system_update_test` نفسه.
 *   الخطوتان 2 و3 من phpMyAdmin، أو `php artisan migrate:rollback --step=1`
 *   لمن عنده سطر أوامر — فـ`down()` أدناه تفعلهما معاً.
 */
return new class extends Migration
{
    private const TABLE = 'system_update_test';

    public function up(): void
    {
        // الجدول موجود من تشغيلٍ سابق — لا يُعاد إنشاؤه ولا يسقط الترحيل
        if (Schema::hasTable(self::TABLE)) {
            echo "\n  ℹ️  جدول الاختبار موجود مسبقاً — لم يُنشأ من جديد.\n\n";

            return;
        }

        Schema::create(self::TABLE, function (Blueprint $table) {
            $table->id();

            // ← هذا هو «الحقل» الذي أردت أن تراه يُضاف
            $table->string('note', 191)->nullable()->comment('ملاحظة التجربة');

            $table->timestamp('checked_at')->nullable()->comment('لحظة تنفيذ الهجرة');
            $table->timestamps();
        });

        // صفٌّ واحد كي ترى شيئاً ملموساً في شاشة «أحجام الجداول»
        DB::table(self::TABLE)->insertOrIgnore([
            'note' => 'نجح تشغيل الترحيلات من شاشة «تحديث النظام».',
            'checked_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        echo "\n  ✓ أُنشئ جدول الاختبار «".self::TABLE."» بحقل «note» وصفٍّ واحد.";
        echo "\n    للتأكّد: الإعدادات ← أحجام الجداول ← ابحث عن ".self::TABLE;
        echo "\n    ثم احذف هذه الهجرة — غرضها التجربة فقط.\n\n";
    }

    public function down(): void
    {
        Schema::dropIfExists(self::TABLE);
    }
};
