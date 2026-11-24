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
        Schema::table('warehouse_permits', function (Blueprint $table) {
            // إضافة عمود permission_source_id إذا لم يكن موجوداً
            if (!Schema::hasColumn('warehouse_permits', 'permission_source_id')) {
                $table->unsignedBigInteger('permission_source_id')->nullable()->after('permission_type')->comment('معرف مصدر الإذن');
                
                // إضافة فهرس للعمود
                if (! SchemaGuard::hasIndex('warehouse_permits', 'warehouse_permits_permission_source_id_index')) {
                    $table->index('permission_source_id');
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('warehouse_permits', function (Blueprint $table) {
            if (Schema::hasColumn('warehouse_permits', 'permission_source_id')) {
                $table->dropIndex(['permission_source_id']);
                $table->dropColumn('permission_source_id');
            }
        });
    }
};
