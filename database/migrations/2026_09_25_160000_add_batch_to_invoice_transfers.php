<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * دفعةُ التسليم: فواتيرُ عميلٍ مستحقّة (أو فواتيرُ كلِّ عملاء الموظف) تُسلَّم
 * معاً إلى موظفٍ واحد، فيستلمها كلَّها بضغطةٍ واحدة — عادةً وهو عند العميل
 * في زيارةٍ من خطّ سيره. كلُّ فاتورةٍ تبقى صفَّ تحويلٍ مستقلّاً (قبولُها
 * ورفضُها وسجلُّها كما كانت)، و`batch` يجمع صفوف الدفعة الواحدة.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('invoice_transfers') || Schema::hasColumn('invoice_transfers', 'batch')) {
            return;
        }

        Schema::table('invoice_transfers', function (Blueprint $table) {
            $table->string('batch', 36)->nullable()->after('visit_id');
            $table->index('batch');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('invoice_transfers') || ! Schema::hasColumn('invoice_transfers', 'batch')) {
            return;
        }

        Schema::table('invoice_transfers', function (Blueprint $table) {
            $table->dropIndex(['batch']);
            $table->dropColumn('batch');
        });
    }
};
