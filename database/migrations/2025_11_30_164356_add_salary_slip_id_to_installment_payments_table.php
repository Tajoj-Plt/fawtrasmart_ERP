<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('installment_payments', function (Blueprint $table) {
            if (! Schema::hasColumn('installment_payments', 'salary_slip_id')) {
                $table->unsignedBigInteger('salary_slip_id')->nullable()->after('salary_advance_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('installment_payments', function (Blueprint $table) {
            $table->dropColumn('salary_slip_id');
        });
    }
};
