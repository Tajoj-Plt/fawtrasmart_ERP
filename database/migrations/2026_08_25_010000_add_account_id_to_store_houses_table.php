<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * `StoreHouse::account()` (belongsTo Account, 'account_id') يفترض هذا
     * العمود منذ إنشائه، ويقرؤه StorehouseController وشاشات OnlineStore
     * وصفحة ملف الموظف — لكن أي migration لم يُضِفه فعلاً قط، فكل محاولة
     * لكتابته (كإنشاء مستودع افتراضي لموظف) تفشل بعمود غير موجود.
     */
    public function up(): void
    {
        Schema::table('store_houses', function (Blueprint $table) {
            if (! Schema::hasColumn('store_houses', 'account_id')) {
                $table->foreignId('account_id')->nullable()->after('branch_id')->constrained('accounts')->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('store_houses', function (Blueprint $table) {
            if (Schema::hasColumn('store_houses', 'account_id')) {
                $table->dropConstrainedForeignId('account_id');
            }
        });
    }
};
