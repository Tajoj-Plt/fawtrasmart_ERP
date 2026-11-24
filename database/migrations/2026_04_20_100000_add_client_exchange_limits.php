<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * إضافة حدود التبديل اليومية للعملاء (خاص + عام)
     *
     * **كان `after('referral_code')` غير مشروط، فيسقط `migrate` على أي قاعدة
     * جديدة** بـ «Unknown column referral_code in clients». والسبب أن الكتلة
     * التي تُضيف ذلك العمود معطَّلة بالتعليق في
     * `2026_02_05_000001_create_points_system_tables.php` (الأسطر 528-545)، أي
     * أن العمود لا يوجد إلا على قواعد قديمة أُضيف فيها يدوياً.
     *
     * و`after()` ترتيبُ عرضٍ لا أكثر — لا يعتمد عليه استعلام ولا نموذج، فيُسقَط
     * حين يغيب المرساة بدل أن تسقط الهجرة كلّها ومعها ما بعدها.
     */
    public function up(): void
    {
        // حد خاص لكل عميل
        if (! Schema::hasColumn('clients', 'daily_exchange_limit')) {
            Schema::table('clients', function (Blueprint $table) {
                $column = $table->integer('daily_exchange_limit')->nullable()
                    ->comment('الحد اليومي المسموح للتبديل لهذا العميل (null = يستخدم الحد العام)');

                if (Schema::hasColumn('clients', 'referral_code')) {
                    $column->after('referral_code');
                }
            });
        }

        // حد عام يسري على جميع العملاء الذين ليس لديهم حد خاص
        if (! Schema::hasColumn('account_settings', 'global_client_exchange_limit')) {
            Schema::table('account_settings', function (Blueprint $table) {
                if (! Schema::hasColumn('account_settings', 'global_client_exchange_limit')) {
                    $table->integer('global_client_exchange_limit')->nullable()
                        ->comment('الحد اليومي العام للتبديل لجميع العملاء (null = بدون حد)');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('daily_exchange_limit');
        });

        Schema::table('account_settings', function (Blueprint $table) {
            $table->dropColumn('global_client_exchange_limit');
        });
    }
};
