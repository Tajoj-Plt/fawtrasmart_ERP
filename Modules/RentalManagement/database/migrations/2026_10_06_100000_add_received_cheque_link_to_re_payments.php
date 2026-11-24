<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * دفعة الشيك تُربط بالشيك المستلم في الحسابات (`received_cheques`): الشيك
 * يُدار من شاشة الشيكات، والدفعة تُرحَّل عند تحصيله وتُلغى إن ارتُجع.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('re_payments', function (Blueprint $table) {
            if (! Schema::hasColumn('re_payments', 'received_cheque_id')) {
                $table->unsignedBigInteger('received_cheque_id')->nullable()->index();
            }

            if (! Schema::hasColumn('re_payments', 'cheque_due_date')) {
                $table->date('cheque_due_date')->nullable();
            }

            if (! Schema::hasColumn('re_payments', 'cheque_bank')) {
                $table->string('cheque_bank', 191)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('re_payments', function (Blueprint $table) {
            foreach (['received_cheque_id', 'cheque_due_date', 'cheque_bank'] as $column) {
                if (Schema::hasColumn('re_payments', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
