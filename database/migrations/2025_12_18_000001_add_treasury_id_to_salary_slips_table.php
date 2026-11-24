<?php

use App\Support\SchemaGuard;
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
        Schema::table('salary_slips', function (Blueprint $table) {
            if (! Schema::hasColumn('salary_slips', 'treasury_id')) {
                $table->unsignedBigInteger('treasury_id')->nullable()->after('currency');
            }
            if (! SchemaGuard::hasForeignKey('salary_slips', 'salary_slips_treasury_id_foreign')) {
                $table->foreign('treasury_id')->references('id')->on('accounts')->onDelete('set null');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('salary_slips', function (Blueprint $table) {
            $table->dropForeign(['treasury_id']);
            $table->dropColumn('treasury_id');
        });
    }
};
