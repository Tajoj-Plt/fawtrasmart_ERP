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
        Schema::table('receipts', function (Blueprint $table) {
            // إضافة عمود branch_id للربط المباشر بين الفرع وسند القبض
            if (! Schema::hasColumn('receipts', 'branch_id')) {
                $table->unsignedBigInteger('branch_id')->nullable()->after('treasury_id');
            }
            
            // إضافة foreign key
            if (! SchemaGuard::hasForeignKey('receipts', 'receipts_branch_id_foreign')) {
                $table->foreign('branch_id')
                      ->references('id')
                      ->on('branches')
                      ->onDelete('set null');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('receipts', function (Blueprint $table) {
            $table->dropForeign(['branch_id']);
            $table->dropColumn('branch_id');
        });
    }
};
