<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ربط جداول المتجر الإلكتروني بالفروع.
 *
 * `store_houses` يملك `branch_id` فعلاً (2026_08_23_210000)، لكن الطلبات
 * والكوبونات وطرق الشحن والبانرات لم تكن تملكه — فلا يمكن حصر أيٍّ منها
 * بفرع، ولا تصفيتها في الشاشات حسب فرع المستخدم النشط.
 *
 * `payment_methods` مستثناة عمداً: طريقة الدفع إعدادٌ عامٌّ للمتجر كله
 * (بطاقة/تحويل بنكي/دفع عند الاستلام...)، لا مفهوم لربطها بفرعٍ بعينه.
 *
 * `nullable` عمداً: `null` يعني "كل الفروع" (نفس دلالة `BelongsToBranch::scopeForBranch`)،
 * فالسجلات القديمة تبقى ظاهرة للجميع بدل أن تختفي فجأة بعد الترحيل. عند
 * الإنشاء الفعلي من كنترولرات المتجر يُعبَّأ بالفرع الرئيسي افتراضياً
 * (انظر `main_branch_id()` في `app/Helpers/BranchSettingsHelper.php`).
 */
return new class extends Migration
{
    private array $tables = ['online_orders', 'store_coupons', 'shipping_methods', 'store_banners'];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table) || Schema::hasColumn($table, 'branch_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->foreignId('branch_id')->nullable()->after('id')
                    ->constrained('branches')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'branch_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropConstrainedForeignId('branch_id');
            });
        }
    }
};
