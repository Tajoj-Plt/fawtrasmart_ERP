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
        Schema::table('store_houses', function (Blueprint $table) {
            if (! Schema::hasColumn('store_houses', 'branch_id')) {
                // إضافة عمود branch_id للربط المباشر بين المستودع والفرع
                $table->unsignedBigInteger('branch_id')->nullable()->after('id');

                if (! SchemaGuard::hasForeignKey('store_houses', 'store_houses_branch_id_foreign')) {
                    $table->foreign('branch_id')
                        ->references('id')
                        ->on('branches')
                        ->onDelete('set null');
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('store_houses', function (Blueprint $table) {
            if (Schema::hasColumn('store_houses', 'branch_id')) {
                $table->dropForeign(['branch_id']);
                $table->dropColumn('branch_id');
            }
        });
    }
};
