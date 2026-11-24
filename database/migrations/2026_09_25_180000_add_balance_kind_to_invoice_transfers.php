<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * تسليم رصيد العميل لا فواتيره.
 *
 * مستحقُّ الفاتورة (`invoices.due_value`) لا ينقص بالإشعار الدائن الذي لم
 * يُحوَّل مرتجعاً، فعميلٌ رصيدُ حسابه صفرٌ قد تبقى فاتورتُه «مستحقّة ١٦٢» —
 * وفي هذه النسخة ٣٧٦ عميلاً فواتيرُهم أعلى من حسابهم. فصار للتحويل نوعان:
 *
 *   invoice ← فاتورةٌ بعينها (كما كان)، `invoice_id` مطلوب
 *   balance ← رصيدُ حساب العميل من الأستاذ، صفٌّ لكلّ عميل، `invoice_id` فارغ
 *             و`invoice_amount` = الرصيد يوم التسليم
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('invoice_transfers')) {
            return;
        }

        Schema::table('invoice_transfers', function (Blueprint $table) {
            $table->unsignedBigInteger('invoice_id')->nullable()->change();

            if (! Schema::hasColumn('invoice_transfers', 'kind')) {
                $table->string('kind', 10)->default('invoice')->after('direction');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('invoice_transfers') || ! Schema::hasColumn('invoice_transfers', 'kind')) {
            return;
        }

        Schema::table('invoice_transfers', function (Blueprint $table) {
            $table->dropColumn('kind');
        });
    }
};
