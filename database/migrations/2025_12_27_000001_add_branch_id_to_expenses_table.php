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
        Schema::table('expenses', function (Blueprint $table) {
            // إضافة عمود الفرع إذا لم يكن موجوداً
            if (!Schema::hasColumn('expenses', 'branch_id')) {
                $table->unsignedBigInteger('branch_id')->nullable()->after('account_id');
                if (! SchemaGuard::hasForeignKey('expenses', 'expenses_branch_id_foreign')) {
                    $table->foreign('branch_id')->references('id')->on('branches')->onDelete('set null');
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            if (Schema::hasColumn('expenses', 'branch_id')) {
                $table->dropForeign(['branch_id']);
                $table->dropColumn('branch_id');
            }
        });
    }
};
