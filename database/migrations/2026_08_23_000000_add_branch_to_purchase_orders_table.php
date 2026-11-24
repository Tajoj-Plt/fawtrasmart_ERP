<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ربط أوامر الشراء بالفروع — كل أمر يتبع فرعاً واحداً.
     * 
     * يُعيّن تلقائياً من فرع المستخدم/الموظف في قسم الإنشاء،
     * ويُسمح بالتعديل إن كانت الصلاحيات تسمح.
     */
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->unsignedBigInteger('branch_id')->nullable()->after('updated_by');
            $table->foreign('branch_id')->references('id')->on('branches')
                ->nullOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropForeign('purchase_orders_branch_id_foreign');
            $table->dropColumn('branch_id');
        });
    }
};
