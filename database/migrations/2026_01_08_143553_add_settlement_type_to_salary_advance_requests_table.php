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
        Schema::table('salary_advance_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('salary_advance_requests', 'settlement_type')) {
                $table->enum('settlement_type', ['cash', 'salary'])->nullable()->after('settled_amount')->comment('نوع التسوية: cash=نقدي، salary=من الراتب');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('salary_advance_requests', function (Blueprint $table) {
            $table->dropColumn('settlement_type');
        });
    }
};
