<?php

use App\Support\SchemaGuard;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ربط الموردين بالفروع — العمود كان مُعرَّفاً في `Supplier::$fillable`
     * وفي منطق SupplierManagementController منذ البداية دون أن يُنشأ فعلاً
     * في القاعدة، فكان كل حفظ يفشل بصمت لولا أن الكنترولر لم يكن يكتب إليه.
     */
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            if (! Schema::hasColumn('suppliers', 'branch_id')) {
                $table->unsignedBigInteger('branch_id')->nullable()->after('updated_by');
            }
            if (! SchemaGuard::hasForeignKey('suppliers', 'suppliers_branch_id_foreign')) {
                $table->foreign('branch_id')->references('id')->on('branches')
                    ->nullOnDelete()->cascadeOnUpdate();
            }
        });
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropForeign('suppliers_branch_id_foreign');
            $table->dropColumn('branch_id');
        });
    }
};
